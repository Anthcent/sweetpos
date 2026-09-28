<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin(); // Todos los endpoints requieren sesion activa
require 'db.php';
require 'inventory.php';

// Reglas de stock de productos:
// - Con receta: el stock solo sube por Elaboracion (api/production.php). La unica
//   excepcion es un 'adjustment' hecho por admin con una nota obligatoria.
// - Sin receta (reventa, ej. bebidas embotelladas): se permiten entradas 'purchase'
//   y 'adjustment' (admin/gerente). Todo queda registrado en stock_movements.

$method = $_SERVER['REQUEST_METHOD'];
$role   = apiCurrentRole();

// Aplica una entrada o ajuste manual de stock respetando las reglas de arriba.
function applyProductStockEntry(PDO $pdo, int $productId, string $reason, int $delta, ?string $note, string $role): void {
    if (!in_array($reason, [StockReason::PURCHASE, StockReason::ADJUSTMENT], true)) {
        throw new StockException("Tipo de movimiento invalido.", 400);
    }
    if ($delta === 0) throw new StockException("La cantidad debe ser distinta de 0.", 400);

    $check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $check->execute([$productId]);
    if (!$check->fetch()) throw new StockException("El producto no existe.", 404);

    if (productHasRecipe($pdo, $productId)) {
        if ($reason === StockReason::PURCHASE) {
            throw new StockException("Este producto tiene receta: su stock solo aumenta mediante Elaboración.", 409);
        }
        if ($role !== 'admin') {
            throw new StockException("Solo un administrador puede ajustar el stock de un producto con receta.", 403);
        }
        if ($note === null || $note === '') {
            throw new StockException("El ajuste de un producto con receta requiere una nota que lo justifique.", 400);
        }
    }
    if ($reason === StockReason::PURCHASE && $delta < 0) {
        throw new StockException("Una entrada de compra debe ser positiva.", 400);
    }

    changeProductStock($pdo, $productId, $delta, $reason, null, $note);
}

// Creates or updates a product, its recipe and an optional first stock entry in
// ONE transaction (the caller opens and commits it). Payload:
//   id?               present = edit, absent = create
//   name, category, price, image_color?, min_stock?, is_active?
//   recipe?           [{material_id, quantity_required}]; absent on edit = keep current
//   initial_stock?    create only, products WITHOUT recipe ('purchase' movement)
//   produce_quantity? products WITH recipe: elaborates N units right away
// Any failure (e.g. not enough material to produce) throws and the caller rolls
// back everything, including the new product row.
function saveFullProduct(PDO $pdo, array $data): array {
    $productId = isset($data['id']) && $data['id'] !== '' && $data['id'] !== null ? (int)$data['id'] : null;
    $current = null;
    if ($productId !== null) {
        $cur = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $cur->execute([$productId]);
        $current = $cur->fetch();
        if (!$current) throw new StockException("El producto no existe.", 404);
    }

    $name = trim((string)($data['name'] ?? ($current['name'] ?? '')));
    $category = trim((string)($data['category'] ?? ($current['category'] ?? '')));
    $price = $data['price'] ?? ($current['price'] ?? null);
    if ($name === '' || $category === '') throw new StockException("El nombre y la categoría son obligatorios.", 400);
    if (mb_strlen($name) > 120) throw new StockException("El nombre es demasiado largo (máximo 120 caracteres).", 400);
    if (!is_numeric($price) || (float)$price < 0) throw new StockException("Precio invalido.", 400);
    $color = $data['image_color'] ?? ($current['image_color'] ?? '#fbcfe8');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$color)) $color = '#fbcfe8';
    $minStock = max(0, (int)($data['min_stock'] ?? ($current['min_stock'] ?? 0)));
    $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)($current['is_active'] ?? 1);
    $initialStock = max(0, (int)($data['initial_stock'] ?? 0));
    $produceQty = max(0, (int)($data['produce_quantity'] ?? 0));

    $dup = $pdo->prepare("SELECT id FROM products WHERE lower(trim(name)) = lower(?) AND id <> ? LIMIT 1");
    $dup->execute([$name, $productId ?? 0]);
    if ($dup->fetchColumn()) throw new StockException("Ya existe un producto llamado \"$name\".", 409);

    $recipe = null;
    if (array_key_exists('recipe', $data) && $data['recipe'] !== null) {
        if (!is_array($data['recipe'])) throw new StockException("La receta debe ser una lista.", 400);
        $recipe = normalizeRecipeItems($data['recipe'], true);
    }
    $willHaveRecipe = $recipe !== null ? count($recipe) > 0 : ($productId !== null && productHasRecipe($pdo, $productId));

    // Recipe rules for stock: products with recipe only grow through production.
    if ($initialStock > 0 && $productId !== null) {
        throw new StockException("El stock de un producto existente se modifica desde Movimiento de stock.", 400);
    }
    if ($initialStock > 0 && $willHaveRecipe) {
        throw new StockException("Un producto con receta aumenta su stock solo mediante Elaboración.", 400);
    }
    if ($produceQty > 0 && !$willHaveRecipe) {
        throw new StockException("Para elaborar unidades el producto necesita una receta.", 400);
    }

    if ($productId === null) {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock, image_color, min_stock, is_active) VALUES (?, ?, ?, 0, ?, ?, ?)");
        $stmt->execute([$name, $category, (float)$price, $color, $minStock, $isActive]);
        $productId = (int)$pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, price=?, image_color=?, min_stock=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $category, (float)$price, $color, $minStock, $isActive, $productId]);
    }

    if ($recipe !== null) saveRecipe($pdo, $productId, $recipe);
    if ($initialStock > 0) changeProductStock($pdo, $productId, $initialStock, StockReason::PURCHASE, null, 'Stock inicial');
    $productionId = $produceQty > 0 ? produceProduct($pdo, $productId, $produceQty) : null;

    return [
        "id" => $productId,
        "created" => $current === null,
        "recipe_items" => $recipe !== null ? count($recipe) : null,
        "production_id" => $productionId,
    ];
}

switch ($method) {
    case 'GET':
        // ?id=N: un producto con su receta (para el formulario de edicion)
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([(int)$_GET['id']]);
            $product = $stmt->fetch();
            if (!$product) {
                jsonError("El producto no existe.", 404);
                break;
            }
            $recipe = $pdo->prepare(
                "SELECT pm.material_id, pm.quantity_required, m.name, m.unit, m.stock AS material_stock
                 FROM product_materials pm JOIN materials m ON m.id = pm.material_id
                 WHERE pm.product_id = ? ORDER BY pm.id ASC"
            );
            $recipe->execute([(int)$product['id']]);
            $product['recipe'] = $recipe->fetchAll();
            echo json_encode($product);
            break;
        }
        // Todos los roles autenticados pueden ver productos activos.
        // include_inactive=1 (admin/gerente) incluye los desactivados.
        $includeInactive = isset($_GET['include_inactive']) && in_array($role, ['admin', 'gerente'], true);
        $stmt = $pdo->query(
            "SELECT p.*, (SELECT COUNT(*) FROM product_materials pm WHERE pm.product_id = p.id) AS recipe_items
             FROM products p" . ($includeInactive ? "" : " WHERE p.is_active = 1") . " ORDER BY p.id ASC"
        );
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Solo gerente y admin pueden crear productos o registrar entradas de stock
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        $action = $data['action'] ?? 'create';

        if ($action === 'save_full') {
            $pdo->beginTransaction();
            try {
                $result = saveFullProduct($pdo, $data);
                $pdo->commit();
                echo json_encode(array_merge(["message" => $result['created'] ? "Producto creado" : "Producto actualizado"], $result));
            } catch (StockException $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), $e->status, $e->extra);
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonError("Error al guardar el producto", 500);
            }
            break;
        }

        if ($action === 'stock_entry') {
            $pdo->beginTransaction();
            try {
                $note = isset($data['note']) ? trim((string)$data['note']) : null;
                applyProductStockEntry($pdo, (int)($data['id'] ?? 0), (string)($data['reason'] ?? ''),
                                       (int)($data['quantity'] ?? 0), $note, $role);
                $pdo->commit();
                echo json_encode(["message" => "Movimiento de stock registrado"]);
            } catch (StockException $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), $e->status, $e->extra);
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonError("Error al registrar el movimiento de stock", 500);
            }
            break;
        }

        if (!isset($data['name'], $data['price'], $data['category']) || trim($data['name']) === '' || !is_numeric($data['price']) || $data['price'] < 0) {
            jsonError("Faltan datos requeridos (nombre, categoría y precio válido)");
            break;
        }
        $initialStock = max(0, (int)($data['stock'] ?? 0));

        $pdo->beginTransaction();
        try {
            // El producto se crea con stock 0; el stock inicial entra como movimiento
            // 'purchase' (el formulario solo lo envia para productos sin receta).
            $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock, image_color, min_stock) VALUES (?, ?, ?, 0, ?, ?)");
            $stmt->execute([
                trim($data['name']),
                $data['category'],
                $data['price'],
                $data['image_color'] ?? '#fbcfe8',
                max(0, (int)($data['min_stock'] ?? 0))
            ]);
            $productId = (int)$pdo->lastInsertId();
            if ($initialStock > 0) {
                changeProductStock($pdo, $productId, $initialStock, StockReason::PURCHASE, null, 'Stock inicial');
            }
            $pdo->commit();
            echo json_encode(["message" => "Producto creado", "id" => $productId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonError("Error al crear el producto", 500);
        }
        break;

    case 'PUT':
        // Solo gerente y admin pueden actualizar
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        if (!isset($data['id'])) {
            jsonError("ID requerido");
            break;
        }
        $productId = (int)$data['id'];

        $pdo->beginTransaction();
        try {
            $cur = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $cur->execute([$productId]);
            $current = $cur->fetch();
            if (!$current) throw new StockException("El producto no existe.", 404);

            $price = $data['price'] ?? $current['price'];
            if (!is_numeric($price) || $price < 0) throw new StockException("Precio invalido.", 400);

            $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, price=?, image_color=?, min_stock=?, is_active=? WHERE id=?");
            $stmt->execute([
                trim($data['name'] ?? $current['name']),
                $data['category']    ?? $current['category'],
                $price,
                $data['image_color'] ?? $current['image_color'],
                max(0, (int)($data['min_stock'] ?? $current['min_stock'])),
                isset($data['is_active']) ? ((int)(bool)$data['is_active']) : (int)$current['is_active'],
                $productId
            ]);

            // Un cambio directo de stock ya no se escribe tal cual: se convierte en
            // un 'adjustment' auditado y sujeto a las reglas de receta.
            if (isset($data['stock']) && (int)$data['stock'] !== (int)$current['stock']) {
                $note = isset($data['note']) ? trim((string)$data['note']) : null;
                if (!productHasRecipe($pdo, $productId) && ($note === null || $note === '')) {
                    $note = 'Ajuste desde edición de producto';
                }
                applyProductStockEntry($pdo, $productId, StockReason::ADJUSTMENT,(int)$data['stock'] - (int)$current['stock'], $note, $role);
            }

            $pdo->commit();
            echo json_encode(["message" => "Producto actualizado"]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (PDOException $e) {
            $pdo->rollBack();
            jsonError("Error al actualizar el producto", 500);
        }
        break;

    case 'DELETE':
        // SOLO admin puede eliminar productos
        apiRequireRole(['admin']);
        $data = jsonInput();
        if (!isset($data['id'])) {
            jsonError("ID requerido");
            break;
        }
        $productId = (int)$data['id'];
        // Con foreign_keys=ON no se puede borrar un producto con ventas o elaboraciones
        // registradas; en ese caso se desactiva para conservar el historial.
        $refs = $pdo->prepare("SELECT (SELECT COUNT(*) FROM sale_items WHERE product_id = ?) + (SELECT COUNT(*) FROM production_log WHERE product_id = ?)");
        $refs->execute([$productId, $productId]);
        if ((int)$refs->fetchColumn() > 0) {
            $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->execute([$productId]);
            echo json_encode(["message" => "El producto tiene historial de ventas o elaboración: se desactivó en lugar de eliminarse.", "deactivated" => true]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            echo json_encode(["message" => "Producto eliminado"]);
        }
        break;

    default:
        jsonError("Metodo no permitido", 405);
        break;
}
?>
