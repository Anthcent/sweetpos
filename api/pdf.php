<?php
// api/pdf.php — Server-side PDF documents (FPDF).
//   ?type=nota&sale_id=N                    delivery note of a paid or voided sale (any logged-in user)
//   ?type=ventas&from=YYYY-MM-DD&to=YYYY-MM-DD   sales report (admin/gerente)
//   ?type=inventario[&target_days=N]        product and material stock (admin/gerente)
//   ?type=movimientos&item_type=&item_id=&reason=&date_from=&date_to=   stock ledger (admin/gerente)
//   &inline=1                                preview in the browser instead of downloading
// Errors are plain text: these URLs are opened as downloads, not fetched as JSON.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function pdfFail(string $message, int $status): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') pdfFail('Método no permitido.', 405);
if (!isset($_SESSION['user_id'])) pdfFail('No autenticado. Inicia sesión.', 401);
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? null;
session_write_close(); // do not block other requests of this session while rendering

$type = (string)($_GET['type'] ?? '');
$reports = ['ventas', 'inventario', 'movimientos'];
if ($type !== 'nota' && !in_array($type, $reports, true)) pdfFail('Tipo de documento inválido.', 400);
if (in_array($type, $reports, true) && !in_array($role, ['admin', 'gerente'], true)) {
    pdfFail('Sin permisos para este reporte.', 403);
}

require 'db.php';
require_once __DIR__ . '/../lib/pdf_documents.php';

try {
    switch ($type) {
        case 'nota':
            $saleId = (int)($_GET['sale_id'] ?? 0);
            if ($saleId <= 0) pdfFail('Venta inválida.', 400);
            $doc = pdfBuildDeliveryNote($pdo, $saleId, $username);
            break;
        case 'ventas':
            $doc = pdfBuildSalesReport($pdo, $_GET['from'] ?? null, $_GET['to'] ?? null);
            break;
        case 'inventario':
            $doc = pdfBuildInventoryReport($pdo, (int)($_GET['target_days'] ?? 3));
            break;
        case 'movimientos':
            $filters = [];
            foreach (['item_type', 'item_id', 'reason', 'date_from', 'date_to'] as $key) {
                $filters[$key] = is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
            }
            $doc = pdfBuildMovementsReport($pdo, $filters);
            break;
    }
} catch (PdfRequestException $e) {
    pdfFail($e->getMessage(), $e->status);
} catch (Throwable $e) {
    error_log('api/pdf.php: ' . $e->getMessage());
    pdfFail('No se pudo generar el documento.', 500);
}

$disposition = !empty($_GET['inline']) ? 'inline' : 'attachment';
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['filename']);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($doc['content']));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $doc['content'];
