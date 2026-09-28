<?php
require __DIR__ . '/setup_guard.php';
$db_path = __DIR__ . '/db/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Intentar agregar columnas a la tabla sales
    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN status TEXT DEFAULT 'paid'");
        echo "Columna 'status' añadida.\n";
    } catch (PDOException $e) { echo "La columna 'status' ya existe o hubo un error.\n"; }

    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN table_number INTEGER DEFAULT NULL");
        echo "Columna 'table_number' añadida.\n";
    } catch (PDOException $e) { echo "La columna 'table_number' ya existe o hubo un error.\n"; }

    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN payment_method TEXT DEFAULT NULL");
        echo "Columna 'payment_method' añadida.\n";
    } catch (PDOException $e) { echo "La columna 'payment_method' ya existe o hubo un error.\n"; }

    echo "Actualización de BD completa.\n";
} catch (PDOException $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
?>
