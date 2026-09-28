<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin();
require 'db.php';
require 'inventory.php';

// Politica de cobro (PUT):
// Cualquier rol autenticado (incluido vendedor) puede cobrar una orden PENDIENTE,
// porque las mesas son compartidas en el salon y las ordenes historicas no tienen
// vendedor asignado. Lo que se restringe es la transicion: solo pending -> paid.
// Una orden pagada no puede volver a pendiente ni cambiar de metodo de pago, y
// anular (con devolucion de stock) queda reservado a admin/gerente.

const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'pagomovil'];
const METHODS_WITH_REFERENCE = ['transfer', 'pagomovil'];

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

    // Crear nueva orden
    if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        jsonError("El carrito está vacío");
        exit();
    }

    // Agrupa cantidades por producto; el precio y el total se calculan en el servidor
    $quantities = [];
    foreach ($data['items'] as $item) {
        $productId = (int)($item['id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) {
            jsonError("Hay productos con cantidad invalida en el carrito.");
            exit();
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
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
    $table_number = isset($data['table_number']) && $data['table_number'] !== '' ? substr(trim((string)$data['table_number']), 0, 20) : null;
    $client_id    = !empty($data['client_id']) ? (int)$data['client_id'] : null;

    $pdo->beginTransaction();
    try {
        $ids = array_keys($quantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price, is_active FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $productsById = [];
        foreach ($stmt->fetchAll() as $p) $productsById[(int)$p['id']] = $p;

        $lines = [];
        $total_amount = 0;
        foreach ($quantities as $productId => $qty) {
            $product = $productsById[$productId] ?? null;
            if (!$product || !(int)$product['is_active']) {
                throw new StockException("Uno de los productos ya no esta disponible (#$productId).", 409);
            }
            $subtotal = round((float)$product['price'] * $qty, 2);
            $lines[] = [$productId, $qty, $subtotal];
            $total_amount += $subtotal;
        }
        $total_amount = round($total_amount, 2);

        $stmt = $pdo->prepare("INSERT INTO sales (total_amount, status, table_number, payment_method, client_id, reference_number, username) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$total_amount, $status, $table_number, $payment_method, $client_id, $reference_number, currentUsername()]);
        $sale_id = (int)$pdo->lastInsertId();

        $stmt_item = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        foreach ($lines as [$productId, $qty, $subtotal]) {
            // Descuento condicional: si otro cajero vendio el ultimo helado, se rechaza la orden completa
            changeProductStock($pdo, $productId, -$qty, StockReason::SALE, $sale_id);
            $stmt_item->execute([$sale_id, $productId, $qty, $subtotal]);
        }

        $pdo->commit();
        echo json_encode(["message" => "Orden registrada con éxito", "sale_id" => $sale_id, "total_amount" => $total_amount]);

    } catch (StockException $e) {
        $pdo->rollBack();
        jsonError($e->getMessage(), $e->status, $e->extra);
    } catch (Exception $e) {
        $pdo->rollBack();
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
        $stmt = $pdo->prepare("UPDATE sales SET status = 'paid', payment_method = ?, reference_number = COALESCE(?, reference_number)
                               WHERE id = ? AND status = 'pending'");
        $stmt->execute([$payment_method, $reference, $saleId]);
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
