<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin();
require 'db.php';
require 'inventory.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['product_id'])) {
            // Receta detallada de un producto puntual, con disponibilidad por material
            $stmt = $pdo->prepare(
                "SELECT pm.id, pm.material_id, m.name, m.unit, m.stock AS material_stock, pm.quantity_required
                 FROM product_materials pm
                 JOIN materials m ON m.id = pm.material_id
                 WHERE pm.product_id = ?
                 ORDER BY m.name ASC"
            );
            $stmt->execute([(int)$_GET['product_id']]);
            echo json_encode($stmt->fetchAll());
        } else {
            // Listado de productos activos con lo que se puede elaborar ahora (can_make)
            // y el insumo que lo limita. ROUND(..., 6) evita 0.3/0.1 = 2.9999 -> 2.
            $stmt = $pdo->query(
                "WITH ratios AS (
                    SELECT pm.product_id, m.name AS material_name,
                           CAST(ROUND(MAX(m.stock, 0) / pm.quantity_required, 6) AS INTEGER) AS units
                    FROM product_materials pm JOIN materials m ON m.id = pm.material_id
                    WHERE pm.quantity_required > 0
                 ), ranked AS (
                    SELECT product_id, material_name, units,
                           COUNT(*) OVER (PARTITION BY product_id) AS recipe_items,
                           ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY units ASC, material_name ASC) AS rn
                    FROM ratios
                 )
                 SELECT p.id, p.name, p.category, p.stock, p.min_stock, p.image_color,
                        COALESCE(r.recipe_items, 0) AS recipe_items,
                        r.units AS max_producible,
                        r.material_name AS limiting_material
                 FROM products p
                 LEFT JOIN ranked r ON r.product_id = p.id AND r.rn = 1
                 WHERE p.is_active = 1
                 ORDER BY p.name ASC"
            );
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'PUT':
        // Guardar/editar la receta (BOM) de un producto — solo gerente y admin.
        // Se mantiene por compatibilidad; el formulario usa products.php action=save_full.
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        if (!isset($data['product_id']) || !isset($data['items']) || !is_array($data['items'])) {
            jsonError("Se requiere product_id e items[]");
            break;
        }
        $productId = (int)$data['product_id'];
        $items = normalizeRecipeItems($data['items'], false);

        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ?");
            $check->execute([$productId]);
            if (!$check->fetchColumn()) throw new StockException("El producto no existe.", 404);

            saveRecipe($pdo, $productId, $items);

            $pdo->commit();
            echo json_encode(["message" => "Receta actualizada", "items" => count($items)]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (PDOException $e) {
            $pdo->rollBack();
            jsonError("Error al guardar la receta: verifica que los insumos existan.");
        }
        break;

    case 'POST':
        // Elaborar (descuenta materiales y suma stock) o deshacer una elaboracion reciente.
        // Solo gerente y admin.
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        $action = $data['action'] ?? 'produce';

        if ($action === 'undo') {
            $productionId = (int)($data['production_id'] ?? 0);
            if ($productionId <= 0) {
                jsonError("Se requiere production_id");
                break;
            }
            $pdo->beginTransaction();
            try {
                $undone = undoProduction($pdo, $productionId);
                $pdo->commit();
                echo json_encode(array_merge(["message" => "Elaboracion deshecha", "production_id" => $productionId], $undone));
            } catch (StockException $e) {
                $pdo->rollBack();
                $message = isset($e->extra['product_id'])
                    ? "No se puede deshacer: ya se vendieron unidades de esta elaboración."
                    : $e->getMessage();
                jsonError($message, $e->status, $e->extra);
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonError("Error al deshacer la elaboracion", 500);
            }
            break;
        }

        $productId = (int)($data['product_id'] ?? 0);
        $quantity  = (int)($data['quantity'] ?? 0);
        if ($productId <= 0 || $quantity <= 0) {
            jsonError("Se requiere product_id y quantity (mayor a 0)");
            break;
        }

        // Todo el chequeo de stock ocurre dentro de la transaccion: cada descuento es
        // un UPDATE condicional (stock >= necesario), asi dos elaboraciones simultaneas
        // nunca pueden dejar un insumo en negativo.
        $pdo->beginTransaction();
        try {
            $productionId = produceProduct($pdo, $productId, $quantity);
            $pdo->commit();
            echo json_encode([
                "message" => "Elaboracion registrada",
                "quantity_produced" => $quantity,
                "production_id" => $productionId,
                "undo_seconds" => PRODUCTION_UNDO_WINDOW_SECONDS,
            ]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (PDOException $e) {
            $pdo->rollBack();
            jsonError("Error al registrar la elaboracion", 500);
        }
        break;

    default:
        jsonError("Metodo no permitido", 405);
        break;
}
?>
