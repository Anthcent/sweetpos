<?php
// api/receipt.php — JSON data for the on-screen receipt card (assets/receipt.js).
//   GET ?sale_id=N   paid or voided sale; same note number as api/pdf.php?type=nota
// Access: admin/gerente any sale; vendedor only sales they created or collected.
// Errors: {"error": "..."} with 400, 401, 403, 404, 405, 409 (pending) or 500.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

require 'auth_api.php';
apiRequireLogin(); // also loads api/db.php (migrations) and refreshes role/username

function receiptFail(string $message, int $status): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') receiptFail('Método no permitido.', 405);

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? null;
session_write_close(); // do not block other requests of this session

$saleId = filter_var($_GET['sale_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($saleId === false) receiptFail('Venta inválida.', 400);

require_once __DIR__ . '/../lib/delivery_notes.php';

try {
    $receipt = deliveryNoteReceipt($pdo, $saleId, $username, ['role' => $role, 'username' => $username]);
} catch (PdfRequestException $e) {
    receiptFail($e->getMessage(), $e->status);
} catch (Throwable $e) {
    error_log('api/receipt.php: ' . $e->getMessage());
    receiptFail('No se pudo cargar el comprobante.', 500);
}

echo json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
