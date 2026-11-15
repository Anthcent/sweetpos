<?php
// api/changes.php — GET: cheap change fingerprints polled by assets/live.js so
// open screens (POS, Zonas de Mesa, Historial, Panel) refresh when another
// cashier creates, extends, pays or voids a ticket, or when stock changes.
// Only hashes are returned (no row payloads). Sales use indexed aggregates;
// the small products and materials tables are read whole and hashed.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    error_log("SweetPOS changes API warning: $message in $file:$line");
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    error_log('SweetPOS changes API failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No se pudo consultar los cambios.'], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/auth_api.php';
apiRequireLogin(); // also opens $pdo (api/db.php)
// Polling must never hold the session lock while other requests of this
// cashier (checkout, payment) are waiting for it.
session_write_close();

// The client gives up after 10s; a stalled query must not keep this worker
// busy for Apache's 300s timeout while the next polls pile up behind it.
if (sweetposDatabaseDriver($pdo) === 'pgsql') {
    $pdo->exec("SET statement_timeout = 5000");
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Sales: every mutation moves at least one value.
//  - new ticket/sale        -> max_id
//  - pay a pending ticket   -> pending_count (a simultaneous new ticket moves max_id), last_paid_at
//  - void (pending or paid) -> voided_count
//  - items added to ticket  -> pending_units / pending_lines / pending_total
$salesRow = $pdo->query("SELECT
        (SELECT MAX(id) FROM sales) AS max_id,
        (SELECT COUNT(*) FROM sales WHERE status = 'pending') AS pending_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE status = 'pending') AS pending_total,
        (SELECT COUNT(*) FROM sales WHERE status = 'voided') AS voided_count,
        (SELECT MAX(paid_at) FROM sales WHERE status = 'paid') AS last_paid_at,
        (SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'pending') AS pending_lines,
        (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'pending') AS pending_units")
    ->fetch(PDO::FETCH_ASSOC);

// Stock: changeProductStock()/changeMaterialStock() always append a
// stock_movements row, so its MAX(id) moves on every stock change. Catalog
// edits (renames, prices, min_stock, colors, materials) do not, so the full
// products and materials rows are hashed too: both tables are small, and an
// aggregate such as a sum of lengths misses same-length renames.
$stockRow = [
    'movement_max_id' => $pdo->query("SELECT MAX(id) FROM stock_movements")->fetchColumn(),
    'products'        => $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
    'materials'       => $pdo->query("SELECT * FROM materials ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
];

$fingerprint = static fn(array $data): string =>
    substr(hash('sha256', json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE)), 0, 16);

echo json_encode([
    'sales'       => $fingerprint($salesRow ?: []),
    'stock'       => $fingerprint($stockRow),
    'server_time' => gmdate('c'),
]);
