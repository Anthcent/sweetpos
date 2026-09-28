<?php
// api/inventory.php — Shared helpers for stock changes and the stock_movements ledger.
// Every stock change in the system must go through these helpers so that it is
// atomic (conditional UPDATE) and always leaves an audit row.

// Allowed values of stock_movements.reason (mirrors the CHECK constraint of
// migration 2). Always use these constants instead of string literals.
final class StockReason {
    const PURCHASE   = 'purchase';    // goods received (materials, resale products)
    const PRODUCTION = 'production';  // materials consumed / product units made
    const SALE       = 'sale';        // product units sold
    const ADJUSTMENT = 'adjustment';  // manual correction with a note
    const VOID       = 'void';        // reversal of a sale or a production
    const ALL = [self::PURCHASE, self::PRODUCTION, self::SALE, self::ADJUSTMENT, self::VOID];
}

// A production can be undone only this soon after it was registered (the UI
// offers undo for 5s; the extra margin absorbs network latency).
const PRODUCTION_UNDO_WINDOW_SECONDS = 60;

// Domain error that carries an HTTP status and optional extra payload.
class StockException extends Exception {
    public $status;
    public $extra;
    public function __construct(string $message, int $status = 409, array $extra = []) {
        parent::__construct($message);
        $this->status = $status;
        $this->extra  = $extra;
    }
}

function jsonInput(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

function jsonError(string $message, int $status = 400, array $extra = []): void {
    http_response_code($status);
    echo json_encode(array_merge(["error" => $message], $extra));
}

function currentUsername(): ?string {
    return $_SESSION['username'] ?? null;
}

function logStockMovement(PDO $pdo, string $itemType, int $itemId, float $qtyDelta, string $reason,
                          ?int $refId = null, ?string $note = null, ?float $unitCost = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO stock_movements (item_type, item_id, qty_delta, reason, ref_id, username, note, unit_cost)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$itemType, $itemId, $qtyDelta, $reason, $refId, currentUsername(), $note, $unitCost]);
}

function productHasRecipe(PDO $pdo, int $productId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_materials WHERE product_id = ?");
    $stmt->execute([$productId]);
    return (int)$stmt->fetchColumn() > 0;
}

// Adds (positive) or removes (negative) product units. A removal only succeeds
// if enough stock exists at the moment of the UPDATE, so stock never goes negative
// even with concurrent requests. Must be called inside a transaction.
function changeProductStock(PDO $pdo, int $productId, int $delta, string $reason,
                            ?int $refId = null, ?string $note = null): void {
    if ($delta === 0) return;
    if ($delta > 0) {
        $stmt = $pdo->prepare("UPDATE products SET stock = stock + CAST(? AS INTEGER) WHERE id = ?");
        $stmt->execute([$delta, $productId]);
    } else {
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - CAST(? AS INTEGER) WHERE id = ? AND stock >= CAST(? AS INTEGER)");
        $stmt->execute([-$delta, $productId, -$delta]);
    }
    if ($stmt->rowCount() === 0) {
        $row = $pdo->prepare("SELECT name, stock FROM products WHERE id = ?");
        $row->execute([$productId]);
        $product = $row->fetch();
        if (!$product) throw new StockException("El producto #$productId no existe.", 404);
        throw new StockException(
            "Stock insuficiente de \"{$product['name']}\": disponible {$product['stock']}, requerido " . (-$delta) . ".",
            409,
            ["product_id" => $productId, "available" => (int)$product['stock'], "needed" => -$delta]
        );
    }
    logStockMovement($pdo, 'product', $productId, $delta, $reason, $refId, $note);
}

// Same contract as changeProductStock, for materials (fractional quantities).
// Quantities are rounded to 6 decimals to avoid float drift (0.1 * 3 != 0.3).
function changeMaterialStock(PDO $pdo, int $materialId, float $delta, string $reason,
                             ?int $refId = null, ?string $note = null, ?float $unitCost = null): void {
    $delta = round($delta, 6);
    if ($delta == 0) return;
    if ($delta > 0) {
        $stmt = $pdo->prepare("UPDATE materials SET stock = ROUND(stock + CAST(? AS REAL), 6) WHERE id = ?");
        $stmt->execute([$delta, $materialId]);
    } else {
        $stmt = $pdo->prepare("UPDATE materials SET stock = ROUND(stock - CAST(? AS REAL), 6) WHERE id = ? AND ROUND(stock, 6) >= CAST(? AS REAL)");
        $stmt->execute([-$delta, $materialId, -$delta]);
    }
    if ($stmt->rowCount() === 0) {
        $row = $pdo->prepare("SELECT name, unit, stock FROM materials WHERE id = ?");
        $row->execute([$materialId]);
        $material = $row->fetch();
        if (!$material) throw new StockException("El insumo #$materialId no existe.", 404);
        throw new StockException(
            "Stock insuficiente de \"{$material['name']}\".",
            409,
            ["material_id" => $materialId, "name" => $material['name'], "unit" => $material['unit'],
             "available" => (float)$material['stock'], "needed" => -$delta]
        );
    }
    logStockMovement($pdo, 'material', $materialId, $delta, $reason, $refId, $note, $unitCost);
}

// ---------------------------------------------------------------------------
// Recipes
// ---------------------------------------------------------------------------

// Recipe rules: one line per material (repeated materials are summed), the
// quantity per unit must be > 0, and every material must exist.
// Returns [material_id => quantity_required]. Throws on invalid lines when
// $strict is true; otherwise silently skips them (legacy PUT behaviour).
function normalizeRecipeItems(array $items, bool $strict = true): array {
    $out = [];
    foreach ($items as $i => $item) {
        $materialId = (int)($item['material_id'] ?? 0);
        $qty = isset($item['quantity_required']) && is_numeric($item['quantity_required'])
            ? round((float)$item['quantity_required'], 6) : 0.0;
        if ($materialId <= 0 || $qty <= 0) {
            if ($strict) {
                throw new StockException("La línea " . ($i + 1) . " de la receta necesita un insumo y una cantidad mayor a 0.", 400,
                                         ["line" => $i]);
            }
            continue;
        }
        $out[$materialId] = round(($out[$materialId] ?? 0) + $qty, 6);
    }
    return $out;
}

// Replaces the recipe of a product. Must be called inside a transaction.
function saveRecipe(PDO $pdo, int $productId, array $normalizedItems): void {
    if ($normalizedItems) {
        $ids = array_keys($normalizedItems);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM materials WHERE id IN ($marks)");
        $stmt->execute($ids);
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($ids, $found));
        if ($missing) {
            throw new StockException("La receta usa insumos que no existen: #" . implode(', #', $missing) . ".", 400,
                                     ["missing_material_ids" => $missing]);
        }
    }
    $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?")->execute([$productId]);
    $ins = $pdo->prepare("INSERT INTO product_materials (product_id, material_id, quantity_required) VALUES (?, ?, ?)");
    foreach ($normalizedItems as $materialId => $qty) {
        $ins->execute([$productId, $materialId, $qty]);
    }
}

// ---------------------------------------------------------------------------
// Production
// ---------------------------------------------------------------------------

// Consumes the recipe materials and adds $quantity units of the product.
// Returns the production_log id. On shortage it throws a 409 StockException
// whose extra payload lists every missing material; the caller must roll back.
// Must be called inside a transaction.
function produceProduct(PDO $pdo, int $productId, int $quantity): int {
    if ($quantity <= 0) throw new StockException("La cantidad a elaborar debe ser mayor a 0.", 400);

    $prod = $pdo->prepare("SELECT id, is_active FROM products WHERE id = ?");
    $prod->execute([$productId]);
    $product = $prod->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new StockException("El producto no existe.", 404);
    if (!(int)$product['is_active']) throw new StockException("El producto esta desactivado.", 409);

    $stmt = $pdo->prepare(
        "SELECT pm.material_id, pm.quantity_required
         FROM product_materials pm JOIN materials m ON m.id = pm.material_id
         WHERE pm.product_id = ?"
    );
    $stmt->execute([$productId]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($recipe)) throw new StockException("Este producto no tiene una receta configurada.", 400);

    $log = $pdo->prepare("INSERT INTO production_log (product_id, quantity_produced, username) VALUES (?, ?, ?)");
    $log->execute([$productId, $quantity, currentUsername()]);
    $productionId = (int)$pdo->lastInsertId();

    // Try every material and report all shortages together.
    $missing = [];
    foreach ($recipe as $item) {
        $needed = round((float)$item['quantity_required'] * $quantity, 6);
        try {
            changeMaterialStock($pdo, (int)$item['material_id'], -$needed, StockReason::PRODUCTION, $productionId);
        } catch (StockException $e) {
            if ($e->status !== 409) throw $e;
            $missing[] = $e->extra;
        }
    }
    if (!empty($missing)) {
        throw new StockException("Material insuficiente para elaborar esta cantidad.", 409, ["missing" => $missing]);
    }

    changeProductStock($pdo, $productId, $quantity, StockReason::PRODUCTION, $productionId);
    return $productionId;
}

// Undoes a recent production with reversing 'void' movements: the product
// units go back out (fails with 409 if they were already sold) and exactly the
// material amounts that were consumed are returned. Must be called inside a
// transaction.
function undoProduction(PDO $pdo, int $productionId): array {
    // Conditional UPDATE: only one request can void a production.
    $upd = $pdo->prepare(
        "UPDATE production_log SET voided_at = CURRENT_TIMESTAMP, voided_by = ?
         WHERE id = ? AND voided_at IS NULL
           AND (julianday('now') - julianday(created_at)) * 86400 <= ?"
    );
    $upd->execute([currentUsername(), $productionId, PRODUCTION_UNDO_WINDOW_SECONDS]);
    if ($upd->rowCount() === 0) {
        throw new StockException("La elaboración no existe, ya fue deshecha o pasó el tiempo para deshacerla.", 409);
    }

    $row = $pdo->prepare("SELECT product_id, quantity_produced FROM production_log WHERE id = ?");
    $row->execute([$productionId]);
    $production = $row->fetch(PDO::FETCH_ASSOC);
    $note = "Deshacer elaboración #$productionId";

    changeProductStock($pdo, (int)$production['product_id'], -(int)$production['quantity_produced'],
                       StockReason::VOID, $productionId, $note);

    $consumed = $pdo->prepare(
        "SELECT item_id, SUM(qty_delta) AS qty FROM stock_movements
         WHERE item_type = 'material' AND reason = ? AND ref_id = ?
         GROUP BY item_id"
    );
    $consumed->execute([StockReason::PRODUCTION, $productionId]);
    foreach ($consumed->fetchAll(PDO::FETCH_ASSOC) as $line) {
        changeMaterialStock($pdo, (int)$line['item_id'], -(float)$line['qty'], StockReason::VOID, $productionId, $note);
    }

    return ["product_id" => (int)$production['product_id'], "quantity" => (int)$production['quantity_produced']];
}

// ---------------------------------------------------------------------------
// Material purchases
// ---------------------------------------------------------------------------

// Adds purchased stock and, when a unit cost is given, updates cost_per_unit
// with the weighted average of the existing stock and the purchase.
// Must be called inside a transaction.
function applyMaterialPurchase(PDO $pdo, int $materialId, float $qty, ?float $unitCost, ?string $note): void {
    $qty = round($qty, 6);
    if ($materialId <= 0 || $qty <= 0) {
        throw new StockException("Se requiere el insumo y una cantidad mayor a 0", 400);
    }
    if ($unitCost !== null && $unitCost < 0) {
        throw new StockException("El costo no puede ser negativo", 400);
    }
    $cur = $pdo->prepare("SELECT stock, cost_per_unit FROM materials WHERE id = ?");
    $cur->execute([$materialId]);
    $material = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$material) throw new StockException("El insumo no existe.", 404);

    if ($unitCost !== null) {
        $oldStock = max(0, (float)$material['stock']);
        $oldCost  = (float)$material['cost_per_unit'];
        $newCost  = $oldStock > 0 && $oldCost > 0
            ? ($oldStock * $oldCost + $qty * $unitCost) / ($oldStock + $qty)
            : $unitCost;
        $pdo->prepare("UPDATE materials SET cost_per_unit = ? WHERE id = ?")->execute([round($newCost, 6), $materialId]);
    }
    changeMaterialStock($pdo, $materialId, $qty, StockReason::PURCHASE, null, $note, $unitCost);
}

// Reads the unit cost of a purchase line: unit_cost wins, else total_cost / qty.
function purchaseUnitCost(array $line, float $qty): ?float {
    $filled = fn($k) => isset($line[$k]) && $line[$k] !== '' && $line[$k] !== null;
    if ($filled('unit_cost')) {
        if (!is_numeric($line['unit_cost'])) throw new StockException("Costo unitario invalido.", 400);
        return (float)$line['unit_cost'];
    }
    if ($filled('total_cost') && $qty > 0) {
        if (!is_numeric($line['total_cost'])) throw new StockException("Costo total invalido.", 400);
        return (float)$line['total_cost'] / $qty;
    }
    return null;
}
?>
