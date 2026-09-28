<?php
// api/index.php — Front Controller / Serverless Entrypoint for Vercel & Local Router
// Routes requests to the appropriate root page or API script.

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = rawurldecode($path);

// If running in PHP's built-in web server, let static files be served directly
if (php_sapi_name() === 'cli-server') {
    $localStatic = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (is_file($localStatic) && !str_ends_with($localStatic, '.php')) {
        return false;
    }
}

$trimmed = trim($path, '/');
$root = dirname(__DIR__);

// Determine target file
if ($trimmed === '' || $trimmed === 'index' || $trimmed === 'index.php') {
    $targetRel = 'index.php';
} elseif (preg_match('#^api/(.+\.php)$#', $trimmed, $m)) {
    $targetRel = 'api/' . $m[1];
} elseif (str_starts_with($trimmed, 'api/')) {
    $targetRel = $trimmed . '.php';
} else {
    // Top-level pages: check if file directly exists or with .php
    if (is_file($root . '/' . $trimmed)) {
        $targetRel = $trimmed;
    } elseif (is_file($root . '/' . $trimmed . '.php')) {
        $targetRel = $trimmed . '.php';
    } else {
        $targetRel = $trimmed;
    }
}

// Disallow directory traversal or hidden files
if (str_contains($targetRel, '..') || str_starts_with($targetRel, '.')) {
    http_response_code(403);
    echo "403 Prohibido";
    exit();
}

$targetFile = $root . '/' . $targetRel;

if (!file_exists($targetFile)) {
    // If it's a static file request (e.g. assets) that bypassed Vercel routing
    if (str_starts_with($targetRel, 'assets/') && file_exists($root . '/' . $targetRel)) {
        $mime_types = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2'
        ];
        $ext = strtolower(pathinfo($targetRel, PATHINFO_EXTENSION));
        $mime = $mime_types[$ext] ?? 'application/octet-stream';
        header("Content-Type: $mime");
        readfile($root . '/' . $targetRel);
        exit();
    }

    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "404 No encontrado: " . htmlspecialchars($targetRel);
    exit();
}

// Set up server environment for target script
$_SERVER['SCRIPT_NAME'] = '/' . $targetRel;
$_SERVER['PHP_SELF']    = '/' . $targetRel;
$_SERVER['SCRIPT_FILENAME'] = $targetFile;

// Change directory to the target file's directory so relative includes (e.g. auth.php) work
chdir(dirname($targetFile));

require $targetFile;
