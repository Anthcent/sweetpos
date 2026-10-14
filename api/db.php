<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/migrate.php';

try {
    $pdo = sweetposConnectFromEnvironment();
    runMigrations($pdo);
} catch (Throwable $e) {
    // Do not return connection details or DATABASE_URL content to callers.
    error_log('SweetPOS database initialization failed.');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "No se pudo inicializar la base de datos. Verifica DATABASE_URL y el acceso al servicio PostgreSQL.\n");
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No se pudo inicializar la base de datos.']);
    }
    exit(1);
}
?>
