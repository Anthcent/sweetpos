<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin(); // Todos los endpoints requieren sesion activa
require 'db.php';
require 'inventory.php';

$method = $_SERVER['REQUEST_METHOD'];

// Maximo de lineas por compra rapida (protege la transaccion de payloads enormes).
const PURCHASE_BATCH_MAX_ITEMS = 200;

function optionalNote(array $data): ?string {
    return isset($data['note']) && trim((string)$data['note']) !== '' ? mb_substr(trim((string)$data['note']), 0, 200) : null;
}

switch ($method) {
    case 'GET':
        // Todos los roles autenticados pueden ver el stock de materiales.
        // used_in = cantidad de recetas que usan el insumo.
        $stmt = $pdo->query(
            "SELECT m.*, COUNT(pm.id) AS used_in
             FROM materials m
             LEFT JOIN product_materials pm ON pm.material_id = m.id
             GROUP BY m.id
             ORDER BY m.name ASC"
        );
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Solo gerente y admin pueden dar de alta materiales o registrar compras
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        $action = $data['action'] ?? 'create';

        if ($action === 'purchase') {
            // Compra de un insumo: suma cantidad y (opcional) actualiza el costo unitario
            // con promedio ponderado entre el stock existente y lo comprado.
            $pdo->beginTransaction();
            try {
                $qty = (float)($data['quantity'] ?? 0);
                applyMaterialPurchase($pdo, (int)($data['id'] ?? 0), $qty, purchaseUnitCost($data, $qty), optionalNote($data));
                $pdo->commit();
                echo json_encode(["message" => "Compra registrada"]);
            } catch (StockException $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), $e->status, $e->extra);
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonError("Error al registrar la compra", 500);
            }
            break;
        }

        if ($action === 'purchase_batch') {
            // Compra rapida: varias lineas {id, quantity, total_cost|unit_cost} en una sola
            // transaccion. Si una linea falla no se registra ninguna (todo o nada).
            $items = $data['items'] ?? null;
            if (!is_array($items) || count($items) === 0) {
                jsonError("Agrega al menos un insumo a la compra");
                break;
            }
            if (count($items) > PURCHASE_BATCH_MAX_ITEMS) {
                jsonError("Una compra admite hasta " . PURCHASE_BATCH_MAX_ITEMS . " lineas");
                break;
            }
            $note = optionalNote($data);

            $pdo->beginTransaction();
            $index = 0;
            try {
                foreach (array_values($items) as $index => $line) {
                    if (!is_array($line)) throw new StockException("Linea invalida.", 400);
                    $qty = is_numeric($line['quantity'] ?? null) ? (float)$line['quantity'] : 0.0;
                    applyMaterialPurchase($pdo, (int)($line['id'] ?? 0), $qty, purchaseUnitCost($line, $qty), $note);
                }
                $pdo->commit();
                echo json_encode(["message" => "Compra registrada", "lines" => count($items)]);
            } catch (StockException $e) {
                $pdo->rollBack();
                jsonError("Linea " . ($index + 1) . ": " . $e->getMessage(), $e->status,
                          array_merge($e->extra, ["line" => $index]));
            } catch (PDOException $e) {
                $pdo->rollBack();
                jsonError("Error al registrar la compra", 500);
            }
            break;
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            jsonError("Faltan datos requeridos (nombre)");
            break;
        }
        $unit = trim((string)($data['unit'] ?? ''));
        if ($unit === '') $unit = 'unidades';
        $initialStock = max(0, round((float)($data['stock'] ?? 0), 6));
        $cost = max(0, (float)($data['cost_per_unit'] ?? 0));

        $pdo->beginTransaction();
        try {
            // Evita duplicados por nombre (sin distinguir mayusculas): el selector de
            // recetas depende de que cada nombre identifique un solo insumo.
            $dup = $pdo->prepare("SELECT id FROM materials WHERE lower(trim(name)) = lower(?) LIMIT 1");
            $dup->execute([$name]);
            $existingId = $dup->fetchColumn();
            if ($existingId) {
                throw new StockException("Ya existe un insumo llamado \"$name\".", 409, ["existing_id" => (int)$existingId]);
            }

            $stmt = $pdo->prepare("INSERT INTO materials (name, unit, stock, min_stock, cost_per_unit) VALUES (?, ?, 0, ?, ?)");
            $stmt->execute([$name, mb_substr($unit, 0, 20), max(0, (float)($data['min_stock'] ?? 0)), $cost]);
            $materialId = (int)$pdo->lastInsertId();
            if ($initialStock > 0) {
                changeMaterialStock($pdo, $materialId, $initialStock, StockReason::PURCHASE, null, 'Stock inicial', $cost > 0 ? $cost : null);
            }
            $pdo->commit();
            echo json_encode(["message" => "Material creado", "id" => $materialId]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonError("Error al crear el material", 500);
        }
        break;

    case 'PUT':
        // Solo gerente y admin pueden actualizar. Un cambio de stock se registra
        // como 'adjustment' (con la diferencia) en lugar de sobrescribir sin rastro.
        apiRequireRole(['admin', 'gerente']);
        $data = jsonInput();
        if (!isset($data['id'])) {
            jsonError("ID requerido");
            break;
        }
        $materialId = (int)$data['id'];

        $pdo->beginTransaction();
        try {
            $cur = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
            $cur->execute([$materialId]);
            $current = $cur->fetch();
            if (!$current) throw new StockException("El insumo no existe.", 404);

            $stmt = $pdo->prepare("UPDATE materials SET name=?, unit=?, min_stock=?, cost_per_unit=? WHERE id=?");
            $stmt->execute([
                trim($data['name'] ?? $current['name']),
                $data['unit'] ?? $current['unit'],
                max(0, (float)($data['min_stock'] ?? $current['min_stock'])),
                max(0, (float)($data['cost_per_unit'] ?? $current['cost_per_unit'])),
                $materialId
            ]);

            if (isset($data['stock'])) {
                $newStock = round((float)$data['stock'], 6);
                if ($newStock < 0) throw new StockException("El stock no puede ser negativo.", 400);
                $delta = round($newStock - (float)$current['stock'], 6);
                if ($delta != 0) {
                    $note = optionalNote($data) ?? 'Ajuste manual desde edición de insumo';
                    changeMaterialStock($pdo, $materialId, $delta, StockReason::ADJUSTMENT, null, $note);
                }
            }

            $pdo->commit();
            echo json_encode(["message" => "Material actualizado"]);
        } catch (StockException $e) {
            $pdo->rollBack();
            jsonError($e->getMessage(), $e->status, $e->extra);
        } catch (PDOException $e) {
            $pdo->rollBack();
            jsonError("Error al actualizar el material", 500);
        }
        break;

    case 'DELETE':
        // SOLO admin puede eliminar materiales
        apiRequireRole(['admin']);
        $data = jsonInput();
        if (!isset($data['id'])) {
            jsonError("ID requerido");
            break;
        }
        $materialId = (int)$data['id'];
        // Con foreign_keys=ON el borrado eliminaria en cascada las filas de receta;
        // se bloquea para no dejar productos con recetas incompletas sin aviso.
        $used = $pdo->prepare("SELECT COUNT(*) FROM product_materials WHERE material_id = ?");
        $used->execute([$materialId]);
        $count = (int)$used->fetchColumn();
        if ($count > 0) {
            jsonError("Este insumo se usa en $count receta(s). Quítalo de las recetas antes de eliminarlo.", 409);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id=?");
        $stmt->execute([$materialId]);
        echo json_encode(["message" => "Material eliminado"]);
        break;

    default:
        jsonError("Metodo no permitido", 405);
        break;
}
?>
