<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/auth_api.php';
apiRequireRole(['admin', 'gerente']);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
    $data = reportBuildData($pdo, $type, $_GET);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (ReportRequestException $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('SweetPOS report query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo generar el reporte.'], JSON_UNESCAPED_UNICODE);
}

\n