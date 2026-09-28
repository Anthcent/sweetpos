<?php
// lib/pdf_documents.php — Builders for every PDF the system produces.
// Pure functions over PDO: no session and no output, so api/pdf.php and the CLI
// tests share them. Each builder returns ['filename' => ..., 'content' => ...].
//
// Timestamps in the database are UTC (SQLite CURRENT_TIMESTAMP); they are shown and
// filtered in the shop timezone (config/business.php, America/Caracas).

require_once __DIR__ . '/pdf_base.php';

const PDF_PAYMENT_LABELS = [
    'cash'      => 'Efectivo',
    'card'      => 'Tarjeta',
    'transfer'  => 'Transferencia',
    'pagomovil' => 'Pago Móvil',
];
const PDF_MOVEMENT_REASONS = [
    'purchase'   => 'Compra',
    'production' => 'Elaboración',
    'sale'       => 'Venta',
    'adjustment' => 'Ajuste',
    'void'       => 'Anulación',
];
const PDF_STOCK_STATUS = ['ok' => 'OK', 'bajo' => 'Bajo', 'critico' => 'Crítico', 'agotado' => 'Agotado'];
const PDF_MAX_REPORT_DAYS = 366;
const PDF_MAX_MOVEMENT_ROWS = 5000;

// Carries the HTTP status for api/pdf.php.
class PdfRequestException extends RuntimeException {
    public $status;
    public function __construct(string $message, int $status = 400) {
        parent::__construct($message);
        $this->status = $status;
    }
}

// ---------------------------------------------------------------------------
// Delivery-note numbering
// ---------------------------------------------------------------------------

// Idempotent; TODO: move into api/migrate.php as a versioned migration.
function deliveryNotesEnsureSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER NOT NULL UNIQUE,
        seq INTEGER NOT NULL UNIQUE,
        number TEXT NOT NULL UNIQUE,
        username TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id)
    )");
}

function deliveryNoteFormat(int $seq): string {
    return 'NE-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

// Returns the note of a sale, creating it the first time. BEGIN IMMEDIATE takes
// the write lock before reading MAX(seq), so two concurrent requests can neither
// reuse a number nor create two notes for the same sale.
function deliveryNoteAssign(PDO $pdo, int $saleId, ?string $username): array {
    deliveryNotesEnsureSchema($pdo);
    $select = $pdo->prepare("SELECT sale_id, seq, number, username, created_at FROM delivery_notes WHERE sale_id = ?");
    $select->execute([$saleId]);
    $note = $select->fetch(PDO::FETCH_ASSOC);
    if ($note) return $note;

    $pdo->exec("BEGIN IMMEDIATE");
    try {
        $select->execute([$saleId]);
        $note = $select->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            $seq = (int)$pdo->query("SELECT COALESCE(MAX(seq), 0) + 1 FROM delivery_notes")->fetchColumn();
            $pdo->prepare("INSERT INTO delivery_notes (sale_id, seq, number, username) VALUES (?, ?, ?, ?)")
                ->execute([$saleId, $seq, deliveryNoteFormat($seq), $username]);
            $select->execute([$saleId]);
            $note = $select->fetch(PDO::FETCH_ASSOC);
        }
        $pdo->exec("COMMIT");
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK");
        throw $e;
    }
    return $note;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function pdfIsDate($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
}

// Local day (shop timezone) -> UTC 'Y-m-d H:i:s', comparable with created_at.
function pdfLocalDayToUtc(string $day, int $addDays = 0): string {
    $date = new DateTime($day . ' 00:00:00', pdfTimezone());
    if ($addDays) $date->modify(($addDays > 0 ? '+' : '') . $addDays . ' day');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function pdfDisplayDay(string $day): string {
    return DateTime::createFromFormat('Y-m-d', $day)->format('d/m/Y');
}

function pdfTableLabel($tableNumber): string {
    if ($tableNumber === null || $tableNumber === '') return '';
    return (string)$tableNumber === '999' ? 'Para llevar' : 'Mesa ' . $tableNumber;
}

function pdfQty(float $value): string {
    return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
}

function pdfFinish(SweetPdf $pdf, string $filename): array {
    return ['filename' => $filename, 'content' => $pdf->Output('S')];
}

// ---------------------------------------------------------------------------
// Nota de entrega
// ---------------------------------------------------------------------------

function pdfBuildDeliveryNote(PDO $pdo, int $saleId, ?string $username, array $options = []): array {
    $stmt = $pdo->prepare(
        "SELECT s.*, c.name AS client_name, c.cedula AS client_cedula, c.phone AS client_phone, c.address AS client_address
         FROM sales s LEFT JOIN clients c ON c.id = s.client_id
         WHERE s.id = ?"
    );
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) throw new PdfRequestException("La venta #$saleId no existe.", 404);
    if ($sale['status'] === 'pending') {
        throw new PdfRequestException("La orden #$saleId aún no está cobrada; la nota de entrega se emite al registrar el pago.", 409);
    }

    $items = $pdo->prepare(
        "SELECT si.product_id, si.quantity, si.subtotal, COALESCE(p.name, 'Producto #' || si.product_id) AS name
         FROM sale_items si LEFT JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = ? ORDER BY si.id"
    );
    $items->execute([$saleId]);
    $items = $items->fetchAll(PDO::FETCH_ASSOC);

    $note = deliveryNoteAssign($pdo, $saleId, $username);
    $voided = $sale['status'] === 'voided';

    $pdf = new SweetPdf('NOTA DE ENTREGA', $note['number'], 'P', $options);
    if ($voided) $pdf->setWatermark('ANULADA');
    $pdf->AddPage();

    $pdf->sectionTitle('Datos de la nota');
    $pdf->field('Número', $note['number']);
    $pdf->field('Fecha de venta', pdfLocalTime($sale['created_at']));
    $pdf->field('Emitida', pdfLocalTime($note['created_at']));
    $pdf->field('Orden', '#' . $sale['id']);
    if (pdfTableLabel($sale['table_number']) !== '') $pdf->field('Mesa', pdfTableLabel($sale['table_number']));
    if (!empty($sale['username'])) $pdf->field('Vendedor', $sale['username']);
    if ($voided) {
        $pdf->field('Estado', 'Anulada el ' . pdfLocalTime($sale['voided_at'])
            . (!empty($sale['voided_by']) ? ' por ' . $sale['voided_by'] : ''));
        if (!empty($sale['void_reason'])) $pdf->field('Motivo', $sale['void_reason']);
    }

    $pdf->sectionTitle('Cliente');
    if (!empty($sale['client_id']) && $sale['client_name'] !== null) {
        $pdf->field('Nombre', $sale['client_name']);
        $pdf->field('Cédula', 'V-' . $sale['client_cedula']);
        $pdf->field('Teléfono', $sale['client_phone']);
        $pdf->field('Dirección', $sale['client_address']);
    } else {
        $pdf->field('Nombre', 'Cliente rápido');
    }

    $pdf->sectionTitle('Detalle');
    $pdf->beginTable([['Producto', 0], ['Cant.', 20, 'R'], ['Precio unit.', 32, 'R'], ['Subtotal', 32, 'R']]);
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $unit = $qty > 0 ? (float)$item['subtotal'] / $qty : 0;
        $pdf->tableRow([$item['name'], (string)$qty, pdfMoney($unit), pdfMoney($item['subtotal'])]);
    }
    if (!$items) $pdf->emptyRow('La orden no tiene productos.');
    $pdf->tableRow(['TOTAL', '', '', pdfMoney($sale['total_amount'])], true, true);
    $pdf->endTable();

    $pdf->sectionTitle('Pago');
    $pdf->field('Método', PDF_PAYMENT_LABELS[$sale['payment_method']] ?? ($sale['payment_method'] ?: 'Sin registrar'));
    if (!empty($sale['reference_number'])) $pdf->field('Referencia', $sale['reference_number']);

    return pdfFinish($pdf, $note['number'] . '.pdf');
}

// ---------------------------------------------------------------------------
// Reporte de ventas
// ---------------------------------------------------------------------------

function pdfBuildSalesReport(PDO $pdo, ?string $from, ?string $to, array $options = []): array {
    $today = (new DateTime('now', pdfTimezone()))->format('Y-m-d');
    $to = ($to === null || $to === '') ? $today : $to;
    $from = ($from === null || $from === '') ? substr($to, 0, 8) . '01' : $from;
    if (!pdfIsDate($from) || !pdfIsDate($to)) throw new PdfRequestException("Las fechas deben tener el formato AAAA-MM-DD.");
    if ($from > $to) throw new PdfRequestException("La fecha inicial es posterior a la final.");
    $span = (new DateTime($from))->diff(new DateTime($to))->days + 1;
    if ($span > PDF_MAX_REPORT_DAYS) throw new PdfRequestException("El rango máximo es de " . PDF_MAX_REPORT_DAYS . " días.");

    $bounds = [pdfLocalDayToUtc($from), pdfLocalDayToUtc($to, 1)];
    $stmt = $pdo->prepare(
        "SELECT id, total_amount, status, payment_method, created_at FROM sales
         WHERE created_at >= ? AND created_at < ? ORDER BY created_at"
    );
    $stmt->execute($bounds);

    $summary = ['paid' => [0, 0.0], 'pending' => [0, 0.0], 'voided' => [0, 0.0]];
    $byDay = [];
    $byMethod = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sale) {
        $status = isset($summary[$sale['status']]) ? $sale['status'] : 'paid';
        $amount = (float)$sale['total_amount'];
        $summary[$status][0]++;
        $summary[$status][1] += $amount;
        if ($status !== 'paid') continue;
        $day = pdfLocalTime($sale['created_at'], 'Y-m-d');
        $byDay[$day] = [($byDay[$day][0] ?? 0) + 1, ($byDay[$day][1] ?? 0) + $amount];
        $method = $sale['payment_method'] ?: '';
        $byMethod[$method] = [($byMethod[$method][0] ?? 0) + 1, ($byMethod[$method][1] ?? 0) + $amount];
    }
    ksort($byDay);
    uasort($byMethod, fn($a, $b) => $b[1] <=> $a[1]);

    $top = $pdo->prepare(
        "SELECT COALESCE(p.name, 'Producto #' || si.product_id) AS name, SUM(si.quantity) AS units, SUM(si.subtotal) AS amount
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'paid' AND s.created_at >= ? AND s.created_at < ?
         GROUP BY si.product_id
         ORDER BY units DESC, amount DESC
         LIMIT 10"
    );
    $top->execute($bounds);
    $top = $top->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new SweetPdf('REPORTE DE VENTAS', pdfDisplayDay($from) . ' - ' . pdfDisplayDay($to), 'P', $options);
    $pdf->AddPage();

    [$paidCount, $paidTotal] = $summary['paid'];
    $pdf->sectionTitle('Resumen');
    $pdf->field('Ventas cobradas', $paidCount . ' órdenes · ' . pdfMoney($paidTotal), 40);
    $pdf->field('Ticket promedio', $paidCount ? pdfMoney($paidTotal / $paidCount) : '—', 40);
    $pdf->field('Pendientes', $summary['pending'][0] . ' órdenes · ' . pdfMoney($summary['pending'][1]) . ' (no incluidas)', 40);
    $pdf->field('Anuladas', $summary['voided'][0] . ' órdenes · ' . pdfMoney($summary['voided'][1]) . ' (no incluidas)', 40);

    $pdf->sectionTitle('Ventas por día');
    $pdf->beginTable([['Fecha', 0], ['Órdenes', 30, 'R'], ['Total', 40, 'R']]);
    foreach ($byDay as $day => [$count, $amount]) $pdf->tableRow([pdfDisplayDay($day), (string)$count, pdfMoney($amount)]);
    if (!$byDay) $pdf->emptyRow('Sin ventas cobradas en el período.');
    $pdf->tableRow(['TOTAL', (string)$paidCount, pdfMoney($paidTotal)], true, true);
    $pdf->endTable();

    $pdf->sectionTitle('Por método de pago');
    $pdf->beginTable([['Método', 0], ['Órdenes', 30, 'R'], ['Total', 40, 'R'], ['%', 25, 'R']]);
    foreach ($byMethod as $method => [$count, $amount]) {
        $pdf->tableRow([
            PDF_PAYMENT_LABELS[$method] ?? ($method === '' ? 'Sin registrar' : $method),
            (string)$count, pdfMoney($amount),
            $paidTotal > 0 ? number_format($amount * 100 / $paidTotal, 1) . '%' : '—',
        ]);
    }
    if (!$byMethod) $pdf->emptyRow('Sin ventas cobradas en el período.');
    $pdf->endTable();

    $pdf->sectionTitle('Productos más vendidos');
    $pdf->beginTable([['#', 10, 'R'], ['Producto', 0], ['Unidades', 30, 'R'], ['Monto', 40, 'R']]);
    foreach ($top as $i => $row) $pdf->tableRow([(string)($i + 1), $row['name'], (string)(int)$row['units'], pdfMoney($row['amount'])]);
    if (!$top) $pdf->emptyRow('Sin productos vendidos en el período.');
    $pdf->endTable();

    return pdfFinish($pdf, "reporte-ventas_{$from}_{$to}.pdf");
}

// ---------------------------------------------------------------------------
// Reporte de inventario
// ---------------------------------------------------------------------------

function pdfBuildInventoryReport(PDO $pdo, int $targetDays = 3, array $options = []): array {
    require_once __DIR__ . '/../api/insights_lib.php';
    $data = computeInsights($pdo, $targetDays);
    $today = (new DateTime('now', pdfTimezone()))->format('Y-m-d');

    $pdf = new SweetPdf('REPORTE DE INVENTARIO', pdfDisplayDay($today), 'P', $options);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->umulti(0, 4.5, "Cobertura = días de stock según el promedio de ventas de los últimos {$data['window_days']} días. "
        . "Estado calculado para un objetivo de " . $data['target_days'] . " día(s).");

    $pdf->sectionTitle('Productos (' . count($data['products']) . ')');
    $pdf->beginTable([['Producto', 0], ['Categoría', 32], ['Stock', 18, 'R'], ['Mín.', 16, 'R'], ['Venta/día', 20, 'R'], ['Cobertura', 22, 'R'], ['Estado', 20]]);
    foreach ($data['products'] as $p) {
        $pdf->tableRow([
            $p['name'], $p['category'], (string)$p['stock'], (string)$p['min_stock'], pdfQty($p['avg_daily']),
            $p['coverage_days'] === null ? '—' : pdfQty($p['coverage_days']) . ' d',
            PDF_STOCK_STATUS[$p['status']] ?? $p['status'],
        ], $p['status'] !== 'ok');
    }
    if (!$data['products']) $pdf->emptyRow('No hay productos activos.');
    $pdf->endTable();

    $totalValue = 0.0;
    $pdf->sectionTitle('Insumos (' . count($data['materials']) . ')');
    $pdf->beginTable([['Insumo', 0], ['Unidad', 20], ['Stock', 20, 'R'], ['Mín.', 18, 'R'], ['Costo/u', 22, 'R'], ['Valor', 24, 'R'], ['Estado', 20]]);
    $costs = [];
    foreach ($pdo->query("SELECT id, cost_per_unit FROM materials") as $row) $costs[(int)$row['id']] = (float)$row['cost_per_unit'];
    foreach ($data['materials'] as $m) {
        $cost = $costs[$m['id']] ?? 0.0;
        $value = max(0, $m['stock']) * $cost;
        $totalValue += $value;
        $pdf->tableRow([
            $m['name'], $m['unit'], pdfQty($m['stock']), pdfQty($m['min_stock']), pdfMoney($cost), pdfMoney($value),
            PDF_STOCK_STATUS[$m['status']] ?? $m['status'],
        ], $m['status'] !== 'ok');
    }
    if (!$data['materials']) $pdf->emptyRow('No hay insumos registrados.');
    $pdf->tableRow(['VALOR TOTAL DE INSUMOS', '', '', '', '', pdfMoney($totalValue), ''], true, true);
    $pdf->endTable();

    return pdfFinish($pdf, "reporte-inventario_{$today}.pdf");
}

// ---------------------------------------------------------------------------
// Reporte de movimientos (same filters as api/movements.php)
// ---------------------------------------------------------------------------

function pdfBuildMovementsReport(PDO $pdo, array $filters, array $options = []): array {
    $where = [];
    $params = [];
    $described = [];

    $itemType = (string)($filters['item_type'] ?? '');
    if ($itemType !== '') {
        if (!in_array($itemType, ['material', 'product'], true)) throw new PdfRequestException("Tipo de ítem inválido.");
        $where[] = "sm.item_type = ?";
        $params[] = $itemType;
        $described[] = $itemType === 'product' ? 'Productos' : 'Insumos';
        if (!empty($filters['item_id'])) {
            $itemId = (int)$filters['item_id'];
            $where[] = "sm.item_id = ?";
            $params[] = $itemId;
            $name = $pdo->prepare("SELECT name FROM " . ($itemType === 'product' ? 'products' : 'materials') . " WHERE id = ?");
            $name->execute([$itemId]);
            $described[] = $name->fetchColumn() ?: "#$itemId";
        }
    }
    $reason = (string)($filters['reason'] ?? '');
    if ($reason !== '') {
        if (!isset(PDF_MOVEMENT_REASONS[$reason])) throw new PdfRequestException("Motivo inválido.");
        $where[] = "sm.reason = ?";
        $params[] = $reason;
        $described[] = PDF_MOVEMENT_REASONS[$reason];
    }
    $from = $filters['date_from'] ?? '';
    $to = $filters['date_to'] ?? '';
    if ($from !== '' && !pdfIsDate($from)) throw new PdfRequestException("Fecha inicial inválida.");
    if ($to !== '' && !pdfIsDate($to)) throw new PdfRequestException("Fecha final inválida.");
    if ($from !== '') {
        $where[] = "sm.created_at >= ?";
        $params[] = pdfLocalDayToUtc($from);
    }
    if ($to !== '') {
        $where[] = "sm.created_at < ?";
        $params[] = pdfLocalDayToUtc($to, 1);
    }
    if ($from !== '' || $to !== '') {
        $described[] = ($from !== '' ? 'desde ' . pdfDisplayDay($from) : '') . ($from !== '' && $to !== '' ? ' ' : '')
                     . ($to !== '' ? 'hasta ' . pdfDisplayDay($to) : '');
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm $whereSql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT sm.item_type, sm.item_id, sm.qty_delta, sm.reason, sm.ref_id, sm.username, sm.note, sm.created_at,
                COALESCE(p.name, m.name, '#' || sm.item_id) AS item_name,
                CASE WHEN sm.item_type = 'material' THEN m.unit ELSE 'u.' END AS unit
         FROM stock_movements sm
         LEFT JOIN products p  ON sm.item_type = 'product'  AND p.id = sm.item_id
         LEFT JOIN materials m ON sm.item_type = 'material' AND m.id = sm.item_id
         $whereSql
         ORDER BY sm.created_at DESC, sm.id DESC
         LIMIT " . PDF_MAX_MOVEMENT_ROWS
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new SweetPdf('MOVIMIENTOS DE STOCK', $described ? '' : 'Todos', 'L', $options);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->umulti(0, 5, 'Filtros: ' . ($described ? implode(' · ', $described) : 'ninguno') . '. '
        . $total . ' movimiento(s)' . ($total > count($rows) ? ', se muestran los ' . count($rows) . ' más recientes' : '') . '.');
    $pdf->Ln(2);

    $pdf->beginTable([['Fecha', 32], ['Tipo', 18], ['Ítem', 0], ['Motivo', 24], ['Cantidad', 26, 'R'], ['Ref.', 16, 'R'], ['Usuario', 26], ['Nota', 55]]);
    foreach ($rows as $r) {
        $delta = (float)$r['qty_delta'];
        $pdf->tableRow([
            pdfLocalTime($r['created_at'], 'd/m/Y h:i A'),
            $r['item_type'] === 'product' ? 'Producto' : 'Insumo',
            $r['item_name'],
            PDF_MOVEMENT_REASONS[$r['reason']] ?? $r['reason'],
            ($delta > 0 ? '+' : '') . pdfQty($delta) . ' ' . $r['unit'],
            $r['ref_id'] !== null ? '#' . $r['ref_id'] : '',
            (string)$r['username'],
            (string)$r['note'],
        ]);
    }
    if (!$rows) $pdf->emptyRow('No hay movimientos con estos filtros.');
    $pdf->endTable();

    $today = (new DateTime('now', pdfTimezone()))->format('Y-m-d');
    return pdfFinish($pdf, "movimientos_{$today}.pdf");
}
