<?php
// tests/test_pdf.php — CLI tests for the PDF documents (lib/pdf_documents.php).
// Works on a temporary snapshot of db/database.sqlite (VACUUM INTO, read-only on
// the live file) plus seeded data; the real database is never written.
//   php tests/test_pdf.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);
$tmpDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sweetpos_test_pdf_' . getmypid() . '.sqlite';
@unlink($tmpDb);

// Snapshot of the live database; fall back to an empty one if it cannot be read.
try {
    $live = new PDO('sqlite:' . $root . '/db/database.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
    ]);
    $live->exec("VACUUM INTO " . $live->quote($tmpDb));
    $live = null;
    echo "Snapshot of db/database.sqlite -> $tmpDb\n";
} catch (Throwable $e) {
    echo "Snapshot unavailable (" . $e->getMessage() . "), using an empty database\n";
}

putenv('SWEETPOS_DB_PATH=' . $tmpDb);
require $root . '/api/db.php';          // runs the migrations on the temp copy
require_once $root . '/lib/pdf_documents.php';
register_shutdown_function(function () use ($tmpDb) {
    $GLOBALS['pdo'] = null;
    @unlink($tmpDb);
});

$failures = 0;
$checks = 0;
function check(bool $condition, string $label): void {
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n";
    }
}
function expectError(callable $fn, int $status, string $label): void {
    try {
        $fn();
        check(false, "$label (no exception)");
    } catch (PdfRequestException $e) {
        check($e->status === $status, "$label -> HTTP $status");
    }
}
$opts = ['compress' => false]; // uncompressed streams so the text can be searched
$cp = fn(string $s) => pdfText($s);  // expected bytes inside the PDF (Windows-1252)

// ---- Seed ----
$pdo->exec("INSERT INTO clients (cedula, name, phone, address) VALUES ('98765432', 'José Peña Ñúñez', '0412-5550000', 'Av. Bolívar, Mérida')");
$clientId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO products (name, category, price, stock, min_stock) VALUES ('Helado de Limón', 'Helados', 2.50, 20, 5)");
$prodA = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO products (name, category, price, stock, min_stock) VALUES ('Barquilla Clásica', 'Conos', 1.25, 0, 3)");
$prodB = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO materials (name, unit, stock, min_stock, cost_per_unit) VALUES ('Azúcar refinada', 'kg', 4.5, 2, 1.10)");
$matId = (int)$pdo->lastInsertId();

function seedSale(PDO $pdo, string $status, ?int $clientId, array $lines, ?string $method, ?string $ref, $table = null): int {
    $total = array_sum(array_map(fn($l) => $l[2], $lines));
    $stmt = $pdo->prepare("INSERT INTO sales (total_amount, status, table_number, payment_method, client_id, reference_number, username, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, 'cajera_ana', datetime('now'))");
    $stmt->execute([$total, $status, $table, $method, $clientId, $ref]);
    $id = (int)$pdo->lastInsertId();
    foreach ($lines as [$productId, $qty, $subtotal]) {
        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)")
            ->execute([$id, $productId, $qty, $subtotal]);
        $pdo->prepare("INSERT INTO stock_movements (item_type, item_id, qty_delta, reason, ref_id, username) VALUES ('product', ?, ?, 'sale', ?, 'cajera_ana')")
            ->execute([$productId, -$qty, $id]);
    }
    return $id;
}
$paid1 = seedSale($pdo, 'paid', $clientId, [[$prodA, 3, 7.50], [$prodB, 2, 2.50]], 'pagomovil', 'REF-4455', 4);
$paid2 = seedSale($pdo, 'paid', null, [[$prodA, 1, 2.50]], 'cash', null, 999);
$voided = seedSale($pdo, 'voided', $clientId, [[$prodB, 1, 1.25]], 'card', null);
$pdo->prepare("UPDATE sales SET voided_at = datetime('now'), voided_by = 'gerente_luis', void_reason = 'Error de digitación' WHERE id = ?")->execute([$voided]);
$pending = seedSale($pdo, 'pending', null, [[$prodA, 2, 5.00]], null, null, 2);
$pdo->prepare("INSERT INTO stock_movements (item_type, item_id, qty_delta, reason, username, note) VALUES ('material', ?, 5, 'purchase', 'gerente_luis', 'Compra en mercado')")->execute([$matId]);

$baseSeq = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'delivery_notes'")->fetchColumn()
    ? (int)$pdo->query("SELECT COALESCE(MAX(seq), 0) FROM delivery_notes")->fetchColumn() : 0;
$n = fn(int $offset) => deliveryNoteFormat($baseSeq + $offset);

// ---- Nota de entrega ----
echo "Nota de entrega\n";
$note1 = pdfBuildDeliveryNote($pdo, $paid1, 'cajera_ana', $opts);
check(strncmp($note1['content'], '%PDF-', 5) === 0, 'output starts with %PDF-');
check($note1['filename'] === $n(1) . '.pdf', "first note is {$n(1)}.pdf (got {$note1['filename']})");
check(strpos($note1['content'], '(' . $n(1) . ')') !== false, 'number printed in the document');
check(strpos($note1['content'], $cp('José Peña Ñúñez')) !== false, 'accented client name encoded as Windows-1252');
check(strpos($note1['content'], $cp('Helado de Limón')) !== false, 'accented product name encoded');
check(strpos($note1['content'], 'V-98765432') !== false, 'cédula printed with V- prefix');
check(strpos($note1['content'], $cp('Pago Móvil')) !== false && strpos($note1['content'], 'REF-4455') !== false, 'payment method and reference');
check(strpos($note1['content'], 'Mesa 4') !== false && strpos($note1['content'], 'cajera_ana') !== false, 'table and seller');
check(strpos($note1['content'], '$2.50') !== false && strpos($note1['content'], '$10.00') !== false, 'unit price and total');
check(strpos($note1['content'], 'Documento no fiscal') !== false, 'footer says "Documento no fiscal"');
check(strpos($note1['content'], $cp('Página') . ' 1/1') !== false, 'page X/Y in footer');
check(strpos($note1['content'], 'ANULADA') === false, 'paid note has no ANULADA watermark');
check(!preg_match('/[\xC3\xC2][\x80-\xBF]/', $note1['content'] . ''), 'no raw UTF-8 sequences leaked into text');

$note2 = pdfBuildDeliveryNote($pdo, $paid2, 'cajera_ana', $opts);
check($note2['filename'] === $n(2) . '.pdf', "second sale gets the next number {$n(2)}");
check(strpos($note2['content'], 'Para llevar') !== false, 'table 999 printed as "Para llevar"');
$again = pdfBuildDeliveryNote($pdo, $paid1, 'otro_usuario', $opts);
check($again['filename'] === $n(1) . '.pdf', 'regenerating reuses the same number');
$rows = $pdo->query("SELECT COUNT(*) FROM delivery_notes")->fetchColumn();
check((int)$rows === $baseSeq + 2, 'regeneration does not insert a new row');
check($pdo->query("SELECT username FROM delivery_notes WHERE sale_id = $paid1")->fetchColumn() === 'cajera_ana', 'note keeps the user who first issued it');

$voidNote = pdfBuildDeliveryNote($pdo, $voided, 'gerente_luis', $opts);
check($voidNote['filename'] === $n(3) . '.pdf', 'voided sale can still get its note');
check(strpos($voidNote['content'], '(ANULADA) Tj') !== false, 'voided note has the ANULADA watermark');
check(strpos($voidNote['content'], $cp('Error de digitación')) !== false, 'void reason printed');

expectError(fn() => pdfBuildDeliveryNote($pdo, $pending, 'x', $opts), 409, 'pending sale is rejected');
expectError(fn() => pdfBuildDeliveryNote($pdo, 999999, 'x', $opts), 404, 'unknown sale');
check((int)$pdo->query("SELECT COUNT(*) FROM delivery_notes")->fetchColumn() === $baseSeq + 3, 'rejected requests consume no number');
$logoOk = pdfResolveLogo(pdfBusinessConfig()['logo_path']) !== null;
check(!$logoOk || strpos($note1['content'], '/Subtype /Image') !== false, 'logo embedded when usable' . ($logoOk ? '' : ' (skipped: no usable logo)'));

// ---- Reportes ----
echo "Reporte de ventas\n";
$today = (new DateTime('now', pdfTimezone()))->format('Y-m-d');
$sales = pdfBuildSalesReport($pdo, $today, $today, $opts);
check(strncmp($sales['content'], '%PDF-', 5) === 0, 'output starts with %PDF-');
check($sales['filename'] === "reporte-ventas_{$today}_{$today}.pdf", 'filename carries the range');
check(strpos($sales['content'], $cp('Helado de Limón')) !== false, 'top products listed');
check(strpos($sales['content'], $cp('Pago Móvil')) !== false && strpos($sales['content'], 'Efectivo') !== false, 'totals per payment method');
check(strpos($sales['content'], $cp('Ventas por día')) !== false, 'totals per day section');
expectError(fn() => pdfBuildSalesReport($pdo, '2026-13-01', $today, $opts), 400, 'invalid date');
expectError(fn() => pdfBuildSalesReport($pdo, $today, '2000-01-01', $opts), 400, 'from after to');
expectError(fn() => pdfBuildSalesReport($pdo, '2020-01-01', '2026-01-01', $opts), 400, 'range too long');

echo "Reporte de inventario\n";
$inv = pdfBuildInventoryReport($pdo, 3, $opts);
check(strncmp($inv['content'], '%PDF-', 5) === 0, 'output starts with %PDF-');
check(strpos($inv['content'], $cp('Azúcar refinada')) !== false, 'materials listed');
check(strpos($inv['content'], $cp('Barquilla Clásica')) !== false && strpos($inv['content'], 'Agotado') !== false, 'products with status');

echo "Reporte de movimientos\n";
$mov = pdfBuildMovementsReport($pdo, ['item_type' => 'material', 'item_id' => (string)$matId, 'reason' => 'purchase', 'date_from' => $today, 'date_to' => $today], $opts);
check(strncmp($mov['content'], '%PDF-', 5) === 0, 'output starts with %PDF-');
check(strpos($mov['content'], 'Compra en mercado') !== false, 'filtered movement listed');
check(strpos($mov['content'], '1 movimiento(s)') !== false, 'filters narrow the result to one row');
$all = pdfBuildMovementsReport($pdo, [], $opts);
check(strpos($all['content'], 'Venta') !== false, 'unfiltered report includes sale movements');
expectError(fn() => pdfBuildMovementsReport($pdo, ['reason' => 'robo'], $opts), 400, 'invalid reason');
expectError(fn() => pdfBuildMovementsReport($pdo, ['item_type' => 'otro'], $opts), 400, 'invalid item type');

$compressed = pdfBuildDeliveryNote($pdo, $paid1, 'cajera_ana');
check(strncmp($compressed['content'], '%PDF-', 5) === 0 && strlen($compressed['content']) < strlen($note1['content']), 'default (compressed) output is valid and smaller');

echo "\n$checks checks, $failures failed\n";
exit($failures ? 1 : 0);
