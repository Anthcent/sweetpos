<?php
// api/reports.php — Report data as JSON for reportes.php.
// preview=1 caps each table for the on-screen preview (row_count and totals
// keep the full figures); api/report_export.php builds the same dataset.
// Warnings are logged instead of printed so they cannot corrupt the JSON body.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    error_log("SweetPOS reports API warning: $message in $file:$line");
    return true;
});

require_once __DIR__ . '/auth_api.php';
apiRequireRole(['admin', 'gerente']);
session_write_close();
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
    if (($_GET['preview'] ?? '') === '1') {
        $data = reportLimitPreviewRows($data);
        $data['preview_limit'] = REPORT_PREVIEW_ROW_LIMIT;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (ReportRequestException $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('SweetPOS report query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo generar el reporte.'], JSON_UNESCAPED_UNICODE);
}
