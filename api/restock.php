<?php
// api/restock.php — consolidated quick-restock plan for the POS/backoffice modal.
// GET ?target_days=7
// Returns products that need production/restock, missing materials, and a
// purchase queue with each material's optional purchase presentation.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/auth_api.php';
apiRequireRole(['admin', 'gerente']);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/insights_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonError('Método no permitido', 405);
    exit();
}

$targetDays = isset($_GET['target_days']) ? (int)$_GET['target_days'] : 7;
$targetDays = max(1, min(60, $targetDays));

try {
    $insights = computeInsights($pdo, $targetDays);

    $presentationRows = $pdo->query(
        "SELECT id, presentation_label, presentation_qty FROM materials"
    )->fetchAll();
    $presentationById = [];
    foreach ($presentationRows as $row) {
        $presentationById[(int)$row['id']] = [
            'label' => trim((string)($row['presentation_label'] ?? '')),
            'qty' => max(0, round((float)($row['presentation_qty'] ?? 0), 6)),
        ];
    }

    $productsById = [];
    foreach ($insights['products'] as $product) {
        $productsById[(int)$product['id']] = $product;
    }

    $materialsById = [];
    foreach ($insights['materials'] as $material) {
        $materialsById[(int)$material['id']] = $material;
    }

    $recipeStmt = $pdo->query(
        "SELECT pm.product_id, pm.material_id, pm.quantity_required,
                m.name AS material_name, m.unit, m.stock AS material_stock
         FROM product_materials pm
         JOIN materials m ON m.id = pm.material_id
         JOIN products p ON p.id = pm.product_id
         WHERE p.is_active = 1 AND pm.quantity_required > 0"
    );

    $missingByProduct = [];
    foreach ($recipeStmt->fetchAll() as $line) {
        $productId = (int)$line['product_id'];
        $product = $productsById[$productId] ?? null;
        if (!$product) continue;
        // At least one unit, or the target production need when there is demand.
        $units = max(1, (int)$product['need']);
        $required = (float)$line['quantity_required'] * $units;
        $stock = max(0, (float)$line['material_stock']);
        $missing = max(0, round($required - $stock, 6));
        if ($missing <= 0.000001) continue;
        $missingByProduct[$productId][] = [
            'id' => (int)$line['material_id'],
            'name' => $line['material_name'],
            'unit' => $line['unit'],
            'required' => round($required, 6),
            'stock' => round($stock, 6),
            'missing' => round($missing, 6),
        ];
    }

    $products = [];
    foreach ($insights['products'] as $product) {
        $missing = $missingByProduct[(int)$product['id']] ?? [];
        $hasRecipe = !empty($product['has_recipe']);
        $canMake = $product['can_make'] === null ? null : max(0, (int)$product['can_make']);
        $suggestedUnits = max(1, (int)$product['need']);
        if ($hasRecipe && $canMake !== null) {
            $suggestedUnits = max(0, min($suggestedUnits, $canMake));
        }
        $product['missing_materials'] = $missing;
        $product['missing_count'] = count($missing);
        $product['suggested_units'] = $suggestedUnits;
        $product['restock_needed'] = $product['status'] !== 'ok';
        $products[] = $product;
    }

    $materials = [];
    foreach ($insights['materials'] as $material) {
        $id = (int)$material['id'];
        $presentation = $presentationById[$id] ?? ['label' => '', 'qty' => 0.0];
        $suggestedPurchase = max(0, (float)$material['suggested_purchase']);
        $presentationQty = (float)$presentation['qty'];
        $material['presentation_label'] = $presentation['label'];
        $material['presentation_qty'] = $presentationQty;
        $material['suggested_purchase'] = round($suggestedPurchase, 6);
        $material['suggested_packages'] = $presentationQty > 0
            ? max(1, (int)ceil($suggestedPurchase / $presentationQty))
            : null;
        $material['needs_purchase'] = $material['status'] !== 'ok'
            || $suggestedPurchase > 0
            || !empty($material['blocks']);
        $materials[] = $material;
    }

    $purchase = array_values(array_filter($materials, fn($m) => !empty($m['needs_purchase'])));
    $production = array_values(array_filter($products, function ($p) {
        return !empty($p['has_recipe'])
            && $p['can_make'] !== null
            && (int)$p['can_make'] > 0
            && (!empty($p['restock_needed']) || (int)$p['suggested_production'] > 0);
    }));
    $resale = array_values(array_filter($products, function ($p) {
        return empty($p['has_recipe']) && !empty($p['restock_needed']);
    }));

    $missingMaterialIds = [];
    foreach ($products as $product) {
        foreach ($product['missing_materials'] as $missing) {
            $missingMaterialIds[(int)$missing['id']] = true;
        }
    }

    echo json_encode([
        'generated_at' => date('c'),
        'target_days' => $targetDays,
        'summary' => [
            'products_low' => count(array_filter($products, fn($p) => !empty($p['restock_needed']))),
            'materials_low' => count(array_filter($materials, fn($m) => $m['status'] !== 'ok')),
            'purchase_count' => count($purchase),
            'production_count' => count($production),
            'resale_count' => count($resale),
            'missing_materials' => count($missingMaterialIds),
        ],
        'products' => $products,
        'materials' => $materials,
        'purchase' => $purchase,
        'production' => $production,
        'resale' => $resale,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log('SweetPOS restock query failed: ' . $e->getMessage());
    jsonError('No se pudo calcular la reposición.', 500);
} catch (Throwable $e) {
    error_log('SweetPOS restock failed: ' . $e->getMessage());
    jsonError('No se pudo calcular la reposición.', 500);
}
