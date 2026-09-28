<?php
// setup_guard.php — Restricts the legacy setup scripts (init_db.php, update_db*.php).
// They may run from the command line (iniciar_sistema.bat) or from the browser
// only by a logged-in administrator. Everyone else gets 403.
if (php_sapi_name() === 'cli') {
    return;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (($_SESSION['role'] ?? '') !== 'admin' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Acceso denegado: este script solo puede ejecutarse desde la línea de comandos o por un administrador.\n";
    exit();
}
header('Content-Type: text/plain; charset=utf-8');
?>
