<?php
// api/movements.php — GET: paginated browser over the stock_movements ledger (admin/gerente).
//   ?options=1                         products and materials for the filter selects
//   ?item_type=material|product        optional
//   ?item_id=N                         optional (requires item_type)
//   ?reason=purchase|production|sale|adjustment|void
//   ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD   local dates, inclusive
//   ?page=1&per_page=50
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    error_log("SweetPOS movements API warning: $message in $file:$line");
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    error_log('SweetPOS movements API failed: ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No se pudieron cargar los movimientos.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

require_once __DIR__ . '/auth_api.php';
apiRequireRole(['admin', 'gerente']);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inventory.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError("Metodo no permitido", 405);
    exit();
}

const MOVEMENT_REASONS = StockReason::ALL;

if (!empty($_GET['options'])) {
    echo json_encode([
        "products"  => $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(),
        "materials" => $pdo->query("SELECT id, name, unit FROM materials ORDER BY name ASC")->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

$where = [];
$params = [];

$itemType = $_GET['item_type'] ?? '';
if ($itemType !== '') {
    if (!in_array($itemType, ['material', 'product'], true)) {
        jsonError("Tipo de item invalido.");
        exit();
    }
    $where[] = "sm.item_type = ?";
    $params[] = $itemType;
    if (!empty($_GET['item_id'])) {
        $where[] = "sm.item_id = ?";
        $params[] = (int)$_GET['item_id'];
    }
}

$reason = $_GET['reason'] ?? '';
if ($reason !== '') {
    if (!in_array($reason, MOVEMENT_REASONS, true)) {
        jsonError("Motivo invalido.");
        exit();
    }
    $where[] = "sm.reason = ?";
    $params[] = $reason;
}

// created_at is UTC; convert shop-local calendar days to UTC in PHP so the
// indexed timestamp column can be compared by both SQLite and PostgreSQL.
$businessConfig = require __DIR__ . '/../config/business.php';
$shopTimezone = new DateTimeZone($businessConfig['timezone'] ?? 'UTC');
$dateBound = function ($value, bool $exclusiveEnd = false) use ($shopTimezone): ?string {
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)
        || !checkdate((int)$match[2], (int)$match[3], (int)$match[1])) return null;
    $date = new DateTimeImmutable($value . ' 00:00:00', $shopTimezone);
    if ($exclusiveEnd) $date = $date->modify('+1 day');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
};
foreach (['date_from' => false, 'date_to' => true] as $key => $exclusiveEnd) {
    if (!isset($_GET[$key]) || $_GET[$key] === '') continue;
    $bound = $dateBound($_GET[$key], $exclusiveEnd);
    if ($bound === null) {
        jsonError('Fecha inválida. Usa el formato YYYY-MM-DD.');
        exit();
    }
    $where[] = $key === 'date_from' ? 'sm.created_at >= ?' : 'sm.created_at < ?';
    $params[] = $bound;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

try {
    $count = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm $whereSql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT sm.id, sm.item_type, sm.item_id, sm.qty_delta, sm.reason, sm.ref_id, sm.username,
                sm.note, sm.unit_cost, sm.created_at,
                COALESCE(p.name, m.name) AS item_name,
                CASE WHEN sm.item_type = 'material' THEN m.unit ELSE 'u.' END AS unit
         FROM stock_movements sm
         LEFT JOIN products p  ON sm.item_type = 'product'  AND p.id = sm.item_id
         LEFT JOIN materials m ON sm.item_type = 'material' AND m.id = sm.item_id
         $whereSql
         ORDER BY sm.created_at DESC, sm.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    echo json_encode([
        "items"    => $stmt->fetchAll(),
        "page"     => $page,
        "per_page" => $perPage,
        "total"    => $total,
        "pages"    => max(1, (int)ceil($total / $perPage)),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log('SweetPOS movements query failed: ' . $e->getMessage());
    jsonError("Error al obtener los movimientos", 500);
}
?>
