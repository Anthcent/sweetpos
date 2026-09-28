<?php
// SWEETPOS_DB_PATH lets CLI tests point the API at a copy of the database.
$db_path = getenv('SWEETPOS_DB_PATH') ?: __DIR__ . '/../db/database.sqlite';
try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Wait up to 5s for a concurrent writer instead of failing with "database is locked"
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    // SQLite ships with foreign keys disabled; they must be enabled per connection
    $pdo->exec("PRAGMA foreign_keys = ON");

    require_once __DIR__ . '/migrate.php';
    runMigrations($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión: " . $e->getMessage()]);
    exit();
}
?>
