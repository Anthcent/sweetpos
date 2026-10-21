<?php
// api/migrate.php — Versioned, idempotent schema migrations.
// Included by api/db.php on every connection. Each migration runs once, inside
// its own write transaction, and is recorded in the schema_version table.

function migrationDriver(PDO $pdo): string {
    return function_exists('sweetposDatabaseDriver')
        ? sweetposDatabaseDriver($pdo)
        : (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Apply the small set of schema type/transaction differences between SQLite and PostgreSQL. */
function migrationExec(PDO $pdo, string $sql): int|false {
    $driver = migrationDriver($pdo);
    $command = strtoupper(trim($sql));
    if ($command === 'BEGIN IMMEDIATE') {
        return $driver === 'pgsql' ? (int)$pdo->beginTransaction() : $pdo->exec($sql);
    }
    if ($command === 'COMMIT') {
        if ($driver === 'pgsql') { $pdo->commit(); return 0; }
        return $pdo->exec($sql);
    }
    if ($command === 'ROLLBACK') {
        if ($driver === 'pgsql') { if ($pdo->inTransaction()) $pdo->rollBack(); return 0; }
        return $pdo->exec($sql);
    }
    if ($driver === 'pgsql') {
        $sql = preg_replace('/\\bINTEGER\\s+PRIMARY\\s+KEY\\s+AUTOINCREMENT\\b/i', 'SERIAL PRIMARY KEY', $sql);
        $sql = preg_replace('/\\bDATETIME\\b/i', 'TIMESTAMP', $sql);
    }
    return $pdo->exec($sql);
}

function migrationColumnExists(PDO $pdo, string $table, string $column): bool {
    if (migrationDriver($pdo) === 'pgsql') {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?');
        $stmt->execute([strtolower($table), strtolower($column)]);
        return (bool)$stmt->fetchColumn();
    }
    foreach ($pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (strcasecmp($col['name'], $column) === 0) return true;
    }
    return false;
}

function migrationAddColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!migrationColumnExists($pdo, $table, $column)) {
        migrationExec($pdo, "ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

// Ordered list of migrations: version => callable(PDO).
// Never edit or reorder an applied migration; append new ones at the end.
function migrationList(): array {
    return [
        // 1. Baseline: every table created by init_db.php / update_db*.php, so a
        //    fresh install works even if those scripts were never run.
        1 => function (PDO $pdo) {
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category TEXT NOT NULL,
                price REAL NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                image_color TEXT DEFAULT '#fbcfe8'
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                total_amount REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            migrationAddColumn($pdo, 'sales', 'status', "TEXT DEFAULT 'paid'");
            migrationAddColumn($pdo, 'sales', 'table_number', 'INTEGER DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'payment_method', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'client_id', 'INTEGER DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'reference_number', 'TEXT DEFAULT NULL');
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                subtotal REAL NOT NULL,
                FOREIGN KEY (sale_id) REFERENCES sales(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cedula TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ('admin', 'gerente', 'vendedor')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                unit TEXT NOT NULL DEFAULT 'unidades',
                stock REAL NOT NULL DEFAULT 0,
                min_stock REAL NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS product_materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                quantity_required REAL NOT NULL,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
                UNIQUE(product_id, material_id)
            )");
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS production_log (
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
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS stock_movements (
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
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_stock_movements_item ON stock_movements(item_type, item_id)");
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_stock_movements_reason_ref ON stock_movements(reason, ref_id)");
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
            migrationExec($pdo, "DELETE FROM product_materials
                        WHERE product_id NOT IN (SELECT id FROM products)
                           OR material_id NOT IN (SELECT id FROM materials)");
        },

        // 6. Sale audit fields (who created it, and void information).
        6 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'sales', 'username', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'voided_at', 'DATETIME DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'voided_by', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'sales', 'void_reason', 'TEXT DEFAULT NULL');
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_sale_items_sale ON sale_items(sale_id)");
        },

        // 7. Indexes for the movements browser and the decision panel date windows.
        7 => function (PDO $pdo) {
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_stock_movements_item_date ON stock_movements(item_type, item_id, created_at)");
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_stock_movements_date ON stock_movements(created_at)");
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_sales_status_date ON sales(status, created_at)");
        },

        // 8. Integrity report: records every foreign-key orphan found by
        //    PRAGMA foreign_key_check. Nothing is deleted (sales history is kept);
        //    `php api/migrate.php --check` lists current orphans at any time.
        8 => function (PDO $pdo) {
            migrationExec($pdo, "CREATE TABLE IF NOT EXISTS integrity_report (
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

        // 10. Sample products if the table is empty.
        10 => function (PDO $pdo) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM products");
            if ($stmt->fetchColumn() == 0) {
                migrationExec($pdo, "INSERT INTO products (name, category, price, stock, image_color) VALUES
                    ('Helado Vainilla', 'Helados', 2.50, 50, '#fef3c7'),
                    ('Helado Chocolate', 'Helados', 3.00, 40, '#fed7aa'),
                    ('Helado Fresa', 'Helados', 2.50, 60, '#fbcfe8'),
                    ('Cheesecake', 'Postres', 4.50, 20, '#e9d5ff'),
                    ('Brownie', 'Postres', 3.00, 30, '#d8b4fe'),
                    ('Café Americano', 'Varios', 1.50, 100, '#d6d3d1')
                ");
            }
        },

        // 11. Administrators can disable accounts without deleting sales history.
        11 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'users', 'is_active', 'INTEGER NOT NULL DEFAULT 1');
        },

        // 12. Record when an order was actually paid so cash reports group it
        //     on the collection date, including orders paid after creation.
        12 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'sales', 'paid_at', 'DATETIME DEFAULT NULL');
            migrationExec($pdo, "UPDATE sales SET paid_at = created_at WHERE status = 'paid' AND paid_at IS NULL");
            migrationExec($pdo, "CREATE INDEX IF NOT EXISTS idx_sales_paid_at ON sales(status, paid_at)");
        },

        // 13. Optional purchase presentation per material (e.g. "Saco 1 kg" = 1).
        //     Used by the quick-restock modal to buy by packages instead of base units.
        13 => function (PDO $pdo) {
            migrationAddColumn($pdo, 'materials', 'presentation_label', 'TEXT DEFAULT NULL');
            migrationAddColumn($pdo, 'materials', 'presentation_qty', 'REAL NOT NULL DEFAULT 0');
        },
    ];
}

// Rows whose foreign key points to a missing parent. Read-only.
function findForeignKeyOrphans(PDO $pdo): array {
    if (migrationDriver($pdo) === 'pgsql') {
        $foreignKeys = $pdo->query("SELECT child.relname AS table_name, parent.relname AS parent_table,
                child_col.attname AS child_column, parent_col.attname AS parent_column,
                keys.ordinality AS fk_index
            FROM pg_constraint fk
            JOIN pg_class child ON child.oid = fk.conrelid
            JOIN pg_namespace ns ON ns.oid = child.relnamespace
            JOIN pg_class parent ON parent.oid = fk.confrelid
            JOIN LATERAL unnest(fk.conkey, fk.confkey) WITH ORDINALITY AS keys(child_attnum, parent_attnum, ordinality) ON TRUE
            JOIN pg_attribute child_col ON child_col.attrelid = child.oid AND child_col.attnum = keys.child_attnum
            JOIN pg_attribute parent_col ON parent_col.attrelid = parent.oid AND parent_col.attnum = keys.parent_attnum
            WHERE fk.contype = 'f' AND ns.nspname = current_schema()")->fetchAll(PDO::FETCH_ASSOC);
        $orphans = [];
        foreach ($foreignKeys as $fk) {
            $quote = fn(string $name): string => '"' . str_replace('"', '""', $name) . '"';
            $table = $quote($fk['table_name']);
            $parent = $quote($fk['parent_table']);
            $childColumn = $quote($fk['child_column']);
            $parentColumn = $quote($fk['parent_column']);
            $stmt = $pdo->query("SELECT child.id FROM $table child
                LEFT JOIN $parent parent ON parent.$parentColumn = child.$childColumn
                WHERE child.$childColumn IS NOT NULL AND parent.$parentColumn IS NULL");
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rowId) {
                $orphans[] = [
                    'table' => $fk['table_name'],
                    'rowid' => $rowId === null ? null : (int)$rowId,
                    'parent' => $fk['parent_table'],
                    'fkid' => (int)$fk['fk_index'],
                ];
            }
        }
        return $orphans;
    }

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
    migrationExec($pdo, "CREATE TABLE IF NOT EXISTS schema_version (
        version INTEGER PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $driver = migrationDriver($pdo);
    if ($driver === 'pgsql') $pdo->query('SELECT pg_advisory_lock(728374625)');
    try {
        $migrations = migrationList();
        $latest = max(array_keys($migrations));
        if (currentSchemaVersion($pdo) >= $latest) return;

        ksort($migrations);
        foreach ($migrations as $version => $migrate) {
            // SQLite takes its write lock up front; PostgreSQL uses an advisory
            // lock for the whole runner so parallel app starts cannot race.
            migrationExec($pdo, "BEGIN IMMEDIATE");
            try {
                if (currentSchemaVersion($pdo) >= $version) {
                    migrationExec($pdo, "COMMIT");
                    continue;
                }
                $migrate($pdo);
                $stmt = $pdo->prepare("INSERT INTO schema_version (version) VALUES (?)");
                $stmt->execute([$version]);
                migrationExec($pdo, "COMMIT");
            } catch (Throwable $e) {
                migrationExec($pdo, "ROLLBACK");
                throw $e;
            }
        }
    } finally {
        if ($driver === 'pgsql') $pdo->query('SELECT pg_advisory_unlock(728374625)');
    }
}

// CLI entry point:
//   php api/migrate.php          applies pending migrations and exits.
//   php api/migrate.php --check  read-only: prints foreign-key orphans (exit 1 if any).
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/db_connection.php';
    $cliPdo = sweetposConnectFromEnvironment();

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
