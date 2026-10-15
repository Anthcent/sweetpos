<?php
require_once __DIR__ . '/auth_api.php';
apiRequireRole(['admin', 'gerente']);
session_write_close();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Método no permitido.';
    exit();
}

try {
    $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
    $format = is_string($_GET['format'] ?? null) ? strtolower($_GET['format']) : '';
    $data = reportBuildData($pdo, $type, $_GET);
    if ($format === 'excel') {
        require_once __DIR__ . '/../lib/xlsx_export.php';
        require_once __DIR__ . '/../lib/report_documents.php';
        $period = preg_replace('/[^0-9A-Za-z-]+/', '_', (string)$data['period']);
        $slug = [
            'cierre_diario' => 'cierre-diario', 'cierre_mensual' => 'cierre-mensual',
            'mas_vendidos' => 'mas-vendidos', 'reponer' => 'reponer',
        ][$data['type']] ?? 'reporte';
        $filename = "sweetpos-{$slug}-{$period}.xlsx";
        $content = sweetposBuildXlsx(reportBuildWorkbookSheets($data));
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    } elseif ($format === 'pdf') {
        require_once __DIR__ . '/../lib/report_documents.php';
        $document = reportBuildPdfDocument($data);
        $filename = $document['filename'];
        $content = $document['content'];
        header('Content-Type: application/pdf');
    } else {
        throw new ReportRequestException('Formato de exportación no válido.');
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $content;
} catch (ReportRequestException $e) {
    http_response_code($e->status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    error_log('SweetPOS report export failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar la exportación.';
}
