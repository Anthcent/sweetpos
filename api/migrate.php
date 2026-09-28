<?php
// api/migrate.php — Versioned, idempotent schema migrations.
// Included by api/db.php on every connection. Each migration runs once, inside
// its own write transaction, and is recorded in the schema_version table.

function migrationColumnExists(PDO $pdo, string $table, string $column): bool {
    foreach ($pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (strcasecmp($col['name'], $column) === 0) return true;
    }
    return false;
}

function migrationAddColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!migrationColumnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

// Ordered list of migrations: version => callable(PDO).
// Never edit or reorder an applied migration; append new ones at the end.
function migrationList(): array {
    return [
        // 1. Baseline: every table created by init_db.php / update_db*.php, so a
        //    fresh install works even if those scripts were never run.
        1 => function (PDO $pdo) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category TEXT NOT NULL,
                price REAL NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                image_color TEXT DEFAULT '#fbcfe8'
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                total_amount REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            migrationAddColumn($pdo, 'sales', 'status', "TEXT DEFAULT 'paid'");
            migrationAddColumn($pdo, 'sales', 'table_number', 'INTEGER DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'payment_method', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'client_id', 'INTEGER DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'reference_number', 'TEXT DEFAULT NULL');
            $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                subtotal REAL NOT NULL,
                FOREIGN KEY (sale_id) REFERENCES sales(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cedula TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ('admin', 'gerente', 'vendedor')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                unit TEXT NOT NULL DEFAULT 'unidades',
                stock REAL NOT NULL DEFAULT 0,
                min_stock REAL NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                quantity_required REAL NOT NULL,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
                UNIQUE(product_id, material_id)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS production_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                quantity_produced INTEGER NOT NULL,
                username TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )");
        },

        // 2. Stock ledger: every stock change for materials and products.
        //    item_id is polymorphic (material or product), so it has no FK.
        2 => function (PDO $pdo) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_type TEXT NOT NULL CHECK(item_type IN ('material', 'product')),
                item_id INTEGER NOT NULL,
                qty_delta REAL NOT NULL,
                reason TEXT NOT NULL CHECK(reason IN ('purchase', 'production', 'sale', 'adjustment', 'void')),
                ref_id INTEGER,
                username TEXT,
                note TEXT,
                unit_cost REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_movements_item ON stock_movements(item_type, item_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_movements_reason_ref ON stock_movements(reason, ref_id)");
        },

        // 3. Product flags.
        3 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'products', 'min_stock', 'INTEGER NOT NULL DEFAULT 0');
            migrationAddColumn($pdo, 'products', 'is_active', 'INTEGER NOT NULL DEFAULT 1');
        },

        // 4. Material cost.
        4 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'materials', 'cost_per_unit', 'REAL NOT NULL DEFAULT 0');
        },

        // 5. Remove recipe rows left behind while foreign keys were disabled.
        5 => function (PDO $pdo) {
            $pdo->exec("DELETE FROM product_materials
                        WHERE product_id NOT IN (SELECT id FROM products)
                           OR material_id NOT IN (SELECT id FROM materials)");
        },

        // 6. Sale audit fields (who created it, and void information).
        6 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'sales', 'username', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'voided_at', 'DATETIME DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'voided_by', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'void_reason', 'TEXT DEFAULT NULL');
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sale_items_sale ON sale_items(sale_id)");
        },

        // 7. Indexes for the movements browser and the decision panel date windows.
        7 => function (PDO $pdo) {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_movements_item_date ON stock_movements(item_type, item_id, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_movements_date ON stock_movements(created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_status_date ON sales(status, created_at)");
        },

        // 8. Integrity report: records every foreign-key orphan found by
        //    PRAGMA foreign_key_check. Nothing is deleted (sales history is kept);
        //    `php api/migrate.php --check` lists current orphans at any time.
        8 => function (PDO $pdo) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS integrity_report (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration_version INTEGER NOT NULL,
                table_name TEXT NOT NULL,
                row_id INTEGER,
                parent_table TEXT NOT NULL,
                fk_index INTEGER,
                detected_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $ins = $pdo->prepare("INSERT INTO integrity_report (migration_version, table_name, row_id, parent_table, fk_index) VALUES (8, ?, ?, ?, ?)");
            foreach (findForeignKeyOrphans($pdo) as $orphan) {
                $ins->execute([$orphan['table'], $orphan['rowid'], $orphan['parent'], $orphan['fkid']]);
            }
        },

        // 9. Production undo: a production can be reversed shortly after it was
        //    registered; the log row is kept and marked as voided.
        9 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'production_log', 'voided_at', 'DATETIME DEFAULT NULL');
            migrationAddColumn($pdo, 'production_log', 'voided_by', 'TEXT DEFAULT NULL');
        },

        // 10. Default users and sample products if tables are empty (enables zero-config deploy)
        10 => function (PDO $pdo) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            if ($stmt->fetchColumn() == 0) {
                $insert = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                $insert->execute(['admin',    password_hash('admin123',    PASSWORD_BCRYPT), 'admin']);
                $insert->execute(['gerente',  password_hash('gerente123',  PASSWORD_BCRYPT), 'gerente']);
                $insert->execute(['vendedor', password_hash('vendedor123', PASSWORD_BCRYPT), 'vendedor']);
            }
            $stmt = $pdo->query("SELECT COUNT(*) FROM products");
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO products (name, category, price, stock, image_color) VALUES 
                    ('Helado Vainilla', 'Helados', 2.50, 50, '#fef3c7'),
                    ('Helado Chocolate', 'Helados', 3.00, 40, '#fed7aa'),
                    ('Helado Fresa', 'Helados', 2.50, 60, '#fbcfe8'),
                    ('Cheesecake', 'Postres', 4.50, 20, '#e9d5ff'),
                    ('Brownie', 'Postres', 3.00, 30, '#d8b4fe'),
                    ('Café Americano', 'Varios', 1.50, 100, '#d6d3d1')
                ");
            }
        },
    ];
}

// Rows whose foreign key points to a missing parent. Read-only.
function findForeignKeyOrphans(PDO $pdo): array {
    $rows = $pdo->query("PRAGMA foreign_key_check")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($r) => [
        'table'  => $r['table'],
        'rowid'  => $r['rowid'] === null ? null : (int)$r['rowid'],
        'parent' => $r['parent'],
        'fkid'   => (int)$r['fkid'],
    ], $rows);
}

function currentSchemaVersion(PDO $pdo): int {
    return (int)$pdo->query("SELECT COALESCE(MAX(version), 0) FROM schema_version")->fetchColumn();
}

// Applies pending migrations. Safe to call on every request: when the schema is
// up to date it costs a single SELECT.
function runMigrations(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
        version INTEGER PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $migrations = migrationList();
    $latest = max(array_keys($migrations));
    if (currentSchemaVersion($pdo) >= $latest) return;

    ksort($migrations);
    foreach ($migrations as $version => $migrate) {
        // BEGIN IMMEDIATE takes the write lock up front, so two concurrent
        // requests cannot apply the same migration twice.
        $pdo->exec("BEGIN IMMEDIATE");
        try {
            if (currentSchemaVersion($pdo) >= $version) {
                $pdo->exec("COMMIT");
                continue;
            }
            $migrate($pdo);
            $stmt = $pdo->prepare("INSERT INTO schema_version (version) VALUES (?)");
            $stmt->execute([$version]);
            $pdo->exec("COMMIT");
        } catch (Throwable $e) {
            $pdo->exec("ROLLBACK");
            throw $e;
        }
    }
}

// CLI entry point:
//   php api/migrate.php          applies pending migrations and exits.
//   php api/migrate.php --check  read-only: prints foreign-key orphans (exit 1 if any).
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $cliDbPath = getenv('SWEETPOS_DB_PATH') ?: __DIR__ . '/../db/database.sqlite';
    $cliPdo = new PDO("sqlite:" . $cliDbPath);
    $cliPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cliPdo->exec("PRAGMA foreign_keys = ON");

    if (in_array('--check', $argv ?? [], true)) {
        $orphans = findForeignKeyOrphans($cliPdo);
        if (!$orphans) {
            echo "Integridad OK: no hay registros huerfanos.\n";
            exit(0);
        }
        echo count($orphans) . " registro(s) huerfano(s):\n";
        foreach ($orphans as $o) {
            echo "  {$o['table']} rowid={$o['rowid']} -> falta el registro padre en {$o['parent']} (fk #{$o['fkid']})\n";
        }
        exit(1);
    }

    runMigrations($cliPdo);
    echo "Esquema actualizado a la version " . currentSchemaVersion($cliPdo) . ".\n";
}
?>
