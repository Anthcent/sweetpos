<?php

// Kept for compatibility with older setup instructions.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require __DIR__ . '/init_db.php';