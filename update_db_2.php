<?php
require __DIR__ . '/setup_guard.php';
$db_path = __DIR__ . '/db/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Clients Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cedula TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        phone TEXT,
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'clients' creada o ya existe.\n";

    // Add columns to sales table
    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN client_id INTEGER DEFAULT NULL");
        echo "Columna 'client_id' añadida a sales.\n";
    } catch (PDOException $e) { echo "La columna 'client_id' ya existe o hubo un error.\n"; }

    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN reference_number TEXT DEFAULT NULL");
        echo "Columna 'reference_number' añadida a sales.\n";
    } catch (PDOException $e) { echo "La columna 'reference_number' ya existe o hubo un error.\n"; }

    echo "Actualización de BD completa (Fase 3).\n";
} catch (PDOException $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
?>
