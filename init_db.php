<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/lib/bootstrap_users.php';

sweetposBootstrapInitialUsers($pdo);
fwrite(STDOUT, "Base de datos lista y cuentas iniciales configuradas.\n");