<?php
require __DIR__ . '/setup_guard.php';
$db_path = __DIR__ . '/db/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabla de materiales / insumos (materia prima)
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        unit TEXT NOT NULL DEFAULT 'unidades',
        stock REAL NOT NULL DEFAULT 0,
        min_stock REAL NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'materials' creada o ya existe.\n";

    // Receta (BOM): cuanto material necesita cada producto por unidad elaborada
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_materials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        material_id INTEGER NOT NULL,
        quantity_required REAL NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
        UNIQUE(product_id, material_id)
    )");
    echo "Tabla 'product_materials' creada o ya existe.\n";

    // Historial de elaboraciones de producto
    $pdo->exec("CREATE TABLE IF NOT EXISTS production_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        quantity_produced INTEGER NOT NULL,
        username TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    echo "Tabla 'production_log' creada o ya existe.\n";

    echo "Actualizacion de BD completa (Fase 5 - Materiales y Elaboracion).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
