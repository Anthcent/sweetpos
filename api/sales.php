<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin();
require 'db.php';
require 'inventory.php';
require_once __DIR__ . '/../lib/validation.php';

// Politica de cobro (PUT):
// Cualquier rol autenticado (incluido vendedor) puede cobrar una orden PENDIENTE,
// porque las mesas son compartidas en el salon y las ordenes historicas no tienen
// vendedor asignado. Lo que se restringe es la transicion: solo pending -> paid.
// Una orden pagada no puede volver a pendiente ni cambiar de metodo de pago, y
// anular (con devolucion de stock) queda reservado a admin/gerente.

const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'pagomovil'];
const METHODS_WITH_REFERENCE = ['transfer', 'pagomovil'];
// table_number used for take-away orders: several can be pending at once.
const TAKEOUT_TABLE_NUMBER = 999;
const MAX_LINE_QUANTITY = 9999;
// Namespace for PostgreSQL advisory locks that serialize ticket creation per table.
const TABLE_LOCK_NAMESPACE = 7301;

$method = $_SERVER['REQUEST_METHOD'];

function attachSaleItems(PDO $pdo, array $sales): array {
    if (empty($sales)) return $sales;
    $ids = array_map(fn($s) => (int)$s['id'], $sales);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT si.sale_id, si.quantity, si.subtotal, p.name
         FROM sale_items si
         JOIN products p ON si.product_id = p.id
         WHERE si.sale_id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $bySale = [];
    foreach ($stmt->fetchAll() as $item) {
        $bySale[$item['sale_id']][] = [
            'quantity' => $item['quantity'],
            'subtotal' => $item['subtotal'],
            'name'     => $item['name'],
        ];
    }
    foreach ($sales as &$sale) {
        $sale['items'] = $bySale[$sale['id']] ?? [];
    }
    return $sales;
}

function validatePayment(?string $paymentMethod, ?string $reference): ?string {
    if (!in_array($paymentMethod, PAYMENT_METHODS, true)) {
        return "Metodo de pago invalido.";
    }
    if (in_array($paymentMethod, METHODS_WITH_REFERENCE, true) && trim((string)$reference) === '') {
        return "Debes ingresar el numero de referencia.";
    }
    return null;
}

// Groups cart lines by product. Quantities must be positive whole units.
// Returns [product_id => quantity]; throws a 400 StockException otherwise.
function parseSaleQuantities($items): array {
    if (!is_array($items) || count($items) === 0) {
        throw new StockException("No hay productos para registrar.", 400);
    }
    $quantities = [];
    foreach ($items as $item) {
        $productId = is_array($item) ? (int)($item['id'] ?? 0) : 0;
        $rawQty = is_array($item) ? ($item['quantity'] ?? null) : null;
        if ($productId <= 0 || !sweetposIsNonNegativeNumber($rawQty, true) || (float)$rawQty < 1) {
            throw new StockException("Hay productos con cantidad inválida: usa números enteros mayores a 0.", 400);
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + (int)$rawQty;
        if ($quantities[$productId] > MAX_LINE_QUANTITY) {
            throw new StockException("La cantidad máxima por producto es " . MAX_LINE_QUANTITY . ".", 400);
        }
    }
    return $quantities;
}

// Prices each line with the current product price (never trusts the client).
// Returns [[[product_id, qty, unit_price, subtotal], ...], total].
function priceSaleLines(PDO $pdo, array $quantities): array {
    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, is_active FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $productsById = [];
    foreach ($stmt->fetchAll() as $p) $productsById[(int)$p['id']] = $p;

    $lines = [];
    $total = 0;
    foreach ($quantities as $productId => $qty) {
        $product = $productsById[$productId] ?? null;
        if (!$product || !(int)$product['is_active']) {
            throw new StockException("Uno de los productos ya no esta disponible (#$productId).", 409);
        }
        $unitPrice = round((float)$product['price'], 2);
        $subtotal = round((float)$product['price'] * $qty, 2);
        $lines[] = [$productId, $qty, $unitPrice, $subtotal];
        $total += $subtotal;
    }
    return [$lines, round($total, 2)];
}

// null = no table; otherwise a whole number between 1 and 999 (999 = para llevar).
function normalizeTableNumber($raw): ?int {
    if ($raw === null || (is_string($raw) && trim($raw) === '')) return null;
    $value = is_string($raw) ? trim($raw) : $raw;
    if (!sweetposIsNonNegativeNumber($value, true) || (int)$value < 1 || (int)$value > TAKEOUT_TABLE_NUMBER) {
        throw new StockException("Número de mesa inválido.", 400);
    }
    return (int)$value;
}

// Rejects a new pending ticket for a dine-in table that already has one.
// Must run inside sweetposBeginWrite(): SQLite holds the write lock from
// BEGIN IMMEDIATE, and PostgreSQL takes a per-table advisory lock so two
// cashiers racing for the same table are serialized until commit.
function assertTableIsFree(PDO $pdo, int $tableNumber): void {
    if ($tableNumber === TAKEOUT_TABLE_NUMBER) return;
    if (sweetposDatabaseDriver($pdo) === 'pgsql') {
        $lock = $pdo->prepare("SELECT pg_advisory_xact_lock(CAST(? AS INTEGER), CAST(? AS INTEGER))");
        $lock->execute([TABLE_LOCK_NAMESPACE, $tableNumber]);
    }
    $stmt = $pdo->prepare("SELECT id FROM sales WHERE status = 'pending' AND table_number = ? LIMIT 1");
    $stmt->execute([$tableNumber]);
    $saleId = $stmt->fetchColumn();
    if ($saleId !== false) {
        throw new StockException(
            "La mesa $tableNumber ya está ocupada con una comanda pendiente. Agrega los productos desde Zonas de Mesa o elige otra mesa.",
            409,
            ["table_number" => $tableNumber, "sale_id" => (int)$saleId]
        );
    }
}

// Appends lines to a pending ticket: deducts stock exactly like a new order
// (changeProductStock with reason 'sale' and ref = sale id), merges each line
// into an existing one with the same product and unit price, and recalculates
// the ticket total from its items. Must run inside sweetposBeginWrite().
function appendItemsToPendingSale(PDO $pdo, int $saleId, array $quantities): float {
    // Conditional no-op UPDATE: locks the ticket row (PostgreSQL) so a
    // concurrent payment or void waits, and fails if it is no longer pending.
    $guard = $pdo->prepare("UPDATE sales SET total_amount = total_amount WHERE id = ? AND status = 'pending'");
    $guard->execute([$saleId]);
    if ($guard->rowCount() === 0) {
        throw new StockException("La comanda ya no está pendiente: fue cobrada o anulada.", 409);
    }

    [$lines] = priceSaleLines($pdo, $quantities);

    $existing = $pdo->prepare("SELECT id, product_id, quantity, subtotal FROM sale_items WHERE sale_id = ?");
    $existing->execute([$saleId]);
    $existingLines = $existing->fetchAll();

    $insert = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
    $update = $pdo->prepare("UPDATE sale_items SET quantity = ?, subtotal = ? WHERE id = ?");
    foreach ($lines as [$productId, $qty, $unitPrice, $subtotal]) {
        changeProductStock($pdo, $productId, -$qty, StockReason::SALE, $saleId);

        $merged = false;
        foreach ($existingLines as &$line) {
            $lineQty = (int)$line['quantity'];
            if ((int)$line['product_id'] !== $productId || $lineQty <= 0) continue;
            if (abs(round((float)$line['subtotal'] / $lineQty, 2) - $unitPrice) >= 0.005) continue;
            $line['quantity'] = $lineQty + $qty;
            $line['subtotal'] = round((float)$line['subtotal'] + $subtotal, 2);
            $update->execute([$line['quantity'], $line['subtotal'], (int)$line['id']]);
            $merged = true;
            break;
        }
        unset($line);
        if (!$merged) $insert->execute([$saleId, $productId, $qty, $subtotal]);
    }

    $sum = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM sale_items WHERE sale_id = ?");
    $sum->execute([$saleId]);
    $total = round((float)$sum->fetchColumn(), 2);
    $pdo->prepare("UPDATE sales SET total_amount = ? WHERE id = ?")->execute([$total, $saleId]);
    return $total;
}

if ($method == 'GET') {
    // Ordenes pendientes (vista de mesas), con sus items en una sola consulta
    $stmt = $pdo->query("SELECT s.*, c.name as client_name, c.cedula as client_cedula
                         FROM sales s
                         LEFT JOIN clients c ON s.client_id = c.id
                         WHERE s.status = 'pending'
                         ORDER BY s.created_at DESC");
    echo json_encode(attachSaleItems($pdo, $stmt->fetchAll()));

} else if ($method == 'POST') {
    $data = jsonInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'void') {
        // Anular orden y devolver stock — solo admin y gerente
        apiRequireRole(['admin', 'gerente']);
        $saleId = (int)($data['id'] ?? 0);
        $reason = trim((string)($data['reason'] ?? ''));
        if ($saleId <= 0) {
            jsonError("ID de orden requerido");
            exit();
        }

        $pdo->beginTransaction();
        try {
            // UPDATE condicional: solo una peticion puede anular la orden (evita doble devolucion)
            $upd = $pdo->prepare(
                "UPDATE sales SET status = 'voided', voided_at = CURRENT_TIMESTAMP, voided_by = ?, void_reason = ?
                 WHERE id = ? AND status IN ('pending', 'paid')"
            );
            $upd->execute([currentUsername(), $reason !== '' ? $reason : null, $saleId]);
            if ($upd->rowCount() === 0) {
                throw new StockException("La orden no existe o ya fue anulada.", 409);
            }

            $items = $pdo->prepare("SELECT product_id, SUM(quantity) AS quantity FROM sale_items WHERE sale_id = ? GROUP BY product_id");
            $items->execute([$saleId]);
            foreach ($items->fetchAll() as $item) {
                changeProductStock($pdo, (int)$item['product_id'], (int)$item['quantity'], StockReason::VOID, $saleId, $reason !== '' ? $reason : null);
            }

            $pdo->commit();
            echo json_encode(["message" => "Orden anulada y stock restituido", "sale_id" => $saleId]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (PDOException $e) {
            $pdo->rollBack();
            jsonError("Error al anular la orden", 500);
        }
        exit();
    }

    if ($action === 'add_items') {
        // Agregar productos a una comanda pendiente: mismo permiso que crearla
        // (cualquier rol autenticado) y mismo descuento de stock.
        $saleId = (int)($data['id'] ?? 0);
        if ($saleId <= 0) {
            jsonError("ID de comanda requerido");
            exit();
        }
        try {
            $quantities = parseSaleQuantities($data['items'] ?? null);
        } catch (StockException $e) {
            jsonError($e->getMessage(), $e->status, $e->extra);
            exit();
        }

        try {
            sweetposBeginWrite($pdo);
            $total = appendItemsToPendingSale($pdo, $saleId, $quantities);
            sweetposCommitWrite($pdo);
            echo json_encode(["message" => "Productos agregados a la comanda", "sale_id" => $saleId, "total_amount" => $total]);
        } catch (StockException $e) {
            sweetposRollbackWrite($pdo);
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (Exception $e) {
            sweetposRollbackWrite($pdo);
            jsonError("Error al agregar productos a la comanda", 500);
        }
        exit();
    }

    // Crear nueva orden
    try {
        // Agrupa cantidades por producto; el precio y el total se calculan en el servidor
        $quantities = parseSaleQuantities($data['items'] ?? null);
        $table_number = normalizeTableNumber($data['table_number'] ?? null);
    } catch (StockException $e) {
        jsonError($e->getMessage(), $e->status, $e->extra);
        exit();
    }

    $status = $data['status'] ?? 'paid';
    if (!in_array($status, ['paid', 'pending'], true)) {
        jsonError("Estado de orden invalido.");
        exit();
    }
    $payment_method   = $data['payment_method'] ?? null;
    $reference_number = isset($data['reference_number']) && trim((string)$data['reference_number']) !== ''
        ? trim((string)$data['reference_number']) : null;
    if ($status === 'paid') {
        $paymentError = validatePayment($payment_method, $reference_number);
        if ($paymentError) {
            jsonError($paymentError);
            exit();
        }
    } else {
        $payment_method = null;
    }
    $client_id = !empty($data['client_id']) ? (int)$data['client_id'] : null;

    try {
        sweetposBeginWrite($pdo);
        // Una mesa del salon solo puede tener una comanda pendiente a la vez
        if ($status === 'pending' && $table_number !== null) {
            assertTableIsFree($pdo, $table_number);
        }

        [$lines, $total_amount] = priceSaleLines($pdo, $quantities);

        $paidAt = $status === 'paid'
            ? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            : null;
        $stmt = $pdo->prepare("INSERT INTO sales (total_amount, status, table_number, payment_method, client_id, reference_number, username, paid_at, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$total_amount, $status, $table_number, $payment_method, $client_id, $reference_number, currentUsername(), $paidAt, $status === 'paid' ? currentUsername() : null]);
        $sale_id = sweetposLastInsertId($pdo, 'sales');

        $stmt_item = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        foreach ($lines as [$productId, $qty, , $subtotal]) {
            // Descuento condicional: si otro cajero vendio el ultimo helado, se rechaza la orden completa
            changeProductStock($pdo, $productId, -$qty, StockReason::SALE, $sale_id);
            $stmt_item->execute([$sale_id, $productId, $qty, $subtotal]);
        }

        sweetposCommitWrite($pdo);
        echo json_encode(["message" => "Orden registrada con éxito", "sale_id" => $sale_id, "total_amount" => $total_amount]);

    } catch (StockException $e) {
        sweetposRollbackWrite($pdo);
        jsonError($e->getMessage(), $e->status, $e->extra);
    } catch (Exception $e) {
        sweetposRollbackWrite($pdo);
        jsonError("Error al procesar orden", 500);
    }

} else if ($method == 'PUT') {
    // Cobrar una orden pendiente (ver politica al inicio del archivo)
    $data = jsonInput();
    $saleId = (int)($data['id'] ?? 0);
    if ($saleId <= 0 || ($data['status'] ?? null) !== 'paid') {
        jsonError("Solo se puede marcar como pagada una orden pendiente (id y status='paid').");
        exit();
    }
    $payment_method = $data['payment_method'] ?? null;
    $reference = isset($data['reference_number']) && trim((string)$data['reference_number']) !== ''
        ? trim((string)$data['reference_number']) : null;
    $paymentError = validatePayment($payment_method, $reference);
    if ($paymentError) {
        jsonError($paymentError);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE sales SET status = 'paid', payment_method = ?, reference_number = COALESCE(?, reference_number), paid_at = CURRENT_TIMESTAMP, paid_by = ?
                               WHERE id = ? AND status = 'pending'");
        $stmt->execute([$payment_method, $reference, currentUsername(), $saleId]);
        if ($stmt->rowCount() === 0) {
            jsonError("La orden no existe o ya no esta pendiente.", 409);
            exit();
        }
        echo json_encode(["message" => "Orden actualizada con éxito"]);
    } catch (Exception $e) {
        jsonError("Error al actualizar orden", 500);
    }
} else {
    jsonError("Método no permitido", 405);
}
?>
