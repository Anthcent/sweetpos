<?php
// Shared report queries and normalized report data for the UI and exports.

require_once __DIR__ . '/insights_lib.php';

class ReportRequestException extends RuntimeException {
    public int $status;

    public function __construct(string $message, int $status = 400) {
        parent::__construct($message);
        $this->status = $status;
    }
}

function reportBusinessConfig(): array {
    $config = require __DIR__ . '/../config/business.php';
    return is_array($config) ? $config : [];
}

function reportTimezone(): DateTimeZone {
    $name = reportBusinessConfig()['timezone'] ?? 'America/Caracas';
    try { return new DateTimeZone($name); }
    catch (Throwable $e) { return new DateTimeZone('America/Caracas'); }
}

function reportToday(): string {
    return (new DateTimeImmutable('now', reportTimezone()))->format('Y-m-d');
}

function reportDateIsValid(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

function reportRequireDate($value, string $label): string {
    if (!is_string($value) || !reportDateIsValid($value)) {
        throw new ReportRequestException("$label debe tener el formato AAAA-MM-DD.");
    }
    return $value;
}

/** Return local calendar boundaries as UTC timestamps for comparisons with the database. */
function reportUtcBounds(string $from, string $toExclusive): array {
    $utc = new DateTimeZone('UTC');
    $start = new DateTimeImmutable($from . ' 00:00:00', reportTimezone());
    $end = new DateTimeImmutable($toExclusive . ' 00:00:00', reportTimezone());
    return [
        $start->setTimezone($utc)->format('Y-m-d H:i:s'),
        $end->setTimezone($utc)->format('Y-m-d H:i:s'),
    ];
}

function reportLocalTimestamp(?string $timestamp, string $format = 'd/m/Y H:i'): string {
    if ($timestamp === null || $timestamp === '') return '';
    try {
        return (new DateTimeImmutable($timestamp, new DateTimeZone('UTC')))
            ->setTimezone(reportTimezone())->format($format);
    } catch (Throwable $e) {
        return $timestamp;
    }
}

function reportDayLabel(string $day): string {
    return DateTimeImmutable::createFromFormat('!Y-m-d', $day, reportTimezone())->format('d/m/Y');
}

function reportPaymentLabel(?string $method): string {
    $labels = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'pagomovil' => 'Pago móvil',
    ];
    if ($method === null || trim($method) === '') return 'Sin registrar';
    return $labels[$method] ?? $method;
}

function reportStatusLabel(string $status): string {
    return ['ok' => 'OK', 'bajo' => 'Bajo', 'critico' => 'Crítico', 'agotado' => 'Agotado'][$status] ?? $status;
}

function reportColumn(string $label, string $format = 'text', string $align = 'L', int $weight = 1): array {
    return ['label' => $label, 'format' => $format, 'align' => $align, 'weight' => $weight];
}

function reportTable(string $title, array $columns, array $rows): array {
    return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
}

function reportStatusSummary(PDO $pdo, string $status, string $dateExpression, string $from, string $to): array {
    [$start, $end] = reportUtcBounds($from, $to);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_amount), 0) AS amount
         FROM sales WHERE status = ? AND $dateExpression >= ? AND $dateExpression < ?"
    );
    $stmt->execute([$status, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [(int)($row['orders_count'] ?? 0), round((float)($row['amount'] ?? 0), 2)];
}

function reportStatusDetails(PDO $pdo, string $status, string $dateExpression, string $from, string $to): array {
    [$start, $end] = reportUtcBounds($from, $to);
    $stmt = $pdo->prepare(
        "SELECT id, total_amount, created_at, voided_at, voided_by, void_reason, username
         FROM sales WHERE status = ? AND $dateExpression >= ? AND $dateExpression < ?
         ORDER BY $dateExpression, id"
    );
    $stmt->execute([$status, $start, $end]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reportStatusTables(array $pending, array $voided): array {
    $tables = [];
    if ($pending) {
        $rows = [];
        foreach ($pending as $sale) {
            $rows[] = [
                (int)$sale['id'], reportLocalTimestamp($sale['created_at']),
                $sale['username'] ?: 'Sin asignar', round((float)$sale['total_amount'], 2),
            ];
        }
        $tables[] = reportTable('Órdenes pendientes', [
            reportColumn('Orden', 'integer', 'R', 1), reportColumn('Creada', 'text', 'L', 3),
            reportColumn('Vendedor', 'text', 'L', 3), reportColumn('Monto', 'money', 'R', 2),
        ], $rows);
    }
    if ($voided) {
        $rows = [];
        foreach ($voided as $sale) {
            $rows[] = [
                (int)$sale['id'], reportLocalTimestamp($sale['voided_at'] ?: $sale['created_at']),
                $sale['voided_by'] ?: 'Sin asignar', $sale['void_reason'] ?: 'Sin motivo',
                round((float)$sale['total_amount'], 2),
            ];
        }
        $tables[] = reportTable('Órdenes anuladas (se muestran separadas)', [
            reportColumn('Orden', 'integer', 'R', 1), reportColumn('Anulada', 'text', 'L', 3),
            reportColumn('Anuló', 'text', 'L', 2), reportColumn('Motivo', 'text', 'L', 4),
            reportColumn('Monto', 'money', 'R', 2),
        ], $rows);
    }
    return $tables;
}

function reportPaymentRows(array $totals, float $grandTotal): array {
    $methods = ['cash', 'card', 'transfer', 'pagomovil'];
    foreach ($methods as $method) $totals[$method] = $totals[$method] ?? [0, 0.0];
    uksort($totals, function ($a, $b) use ($methods) {
        $aIndex = array_search($a, $methods, true);
        $bIndex = array_search($b, $methods, true);
        if ($aIndex !== false || $bIndex !== false) {
            if ($aIndex === false) return 1;
            if ($bIndex === false) return -1;
            return $aIndex <=> $bIndex;
        }
        return strcmp((string)$a, (string)$b);
    });
    $rows = [];
    foreach ($totals as $method => [$count, $amount]) {
        $rows[] = [
            reportPaymentLabel($method === '' ? null : (string)$method),
            (int)$count, round((float)$amount, 2),
            $grandTotal > 0 ? round((float)$amount * 100 / $grandTotal, 2) : 0,
        ];
    }
    return $rows;
}

function reportCashierRows(array $totals): array {
    uasort($totals, fn($a, $b) => ($b[1] <=> $a[1]) ?: strcmp((string)$a[0], (string)$b[0]));
    $rows = [];
    foreach ($totals as [$name, $count, $amount]) {
        $rows[] = [$name, (int)$count, round((float)$amount, 2)];
    }
    return $rows;
}

function reportBuildDailyClose(PDO $pdo, array $filters): array {
    $day = reportRequireDate($filters['date'] ?? reportToday(), 'La fecha');
    $next = (new DateTimeImmutable($day, reportTimezone()))->modify('+1 day')->format('Y-m-d');
    [$start, $end] = reportUtcBounds($day, $next);
    $stmt = $pdo->prepare(
        "SELECT id, total_amount, payment_method, username, paid_at, status
         FROM sales WHERE paid_at IS NOT NULL AND paid_at >= ? AND paid_at < ? ORDER BY paid_at, id"
    );
    $stmt->execute([$start, $end]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paidTotal = 0.0;
    $byMethod = [];
    $byCashier = [];
    $detailRows = [];
    foreach ($sales as $sale) {
        $amount = (float)$sale['total_amount'];
        $paidTotal += $amount;
        $method = (string)($sale['payment_method'] ?? '');
        $byMethod[$method] = $byMethod[$method] ?? [0, 0.0];
        $byMethod[$method][0]++;
        $byMethod[$method][1] += $amount;
        $cashier = trim((string)($sale['username'] ?? '')) ?: 'Sin asignar';
        $byCashier[$cashier] = $byCashier[$cashier] ?? [$cashier, 0, 0.0];
        $byCashier[$cashier][1]++;
        $byCashier[$cashier][2] += $amount;
        $detailRows[] = [
            (int)$sale['id'], reportLocalTimestamp($sale['paid_at'], 'H:i'), $cashier,
            reportPaymentLabel($method), round($amount, 2), $sale['status'] === 'voided' ? 'Anulada' : 'Vigente',
        ];
    }
    $paidTotal = round($paidTotal, 2);
    [$pendingCount, $pendingTotal] = reportStatusSummary($pdo, 'pending', 'created_at', $day, $next);
    [$voidedCount, $voidedTotal] = reportStatusSummary($pdo, 'voided', 'COALESCE(voided_at, created_at)', $day, $next);
    $pending = reportStatusDetails($pdo, 'pending', 'created_at', $day, $next);
    $voided = reportStatusDetails($pdo, 'voided', 'COALESCE(voided_at, created_at)', $day, $next);
    $cashTotal = (float)($byMethod['cash'][1] ?? 0);

    $metrics = [
        ['label' => 'Total cobrado', 'value' => $paidTotal, 'format' => 'money'],
        ['label' => 'Cobros registrados', 'value' => count($sales), 'format' => 'integer'],
        ['label' => 'Ticket promedio', 'value' => count($sales) ? round($paidTotal / count($sales), 2) : 0, 'format' => 'money'],
        ['label' => 'Efectivo registrado', 'value' => round($cashTotal, 2), 'format' => 'money'],
        ['label' => 'Órdenes pendientes', 'value' => $pendingCount, 'format' => 'integer'],
        ['label' => 'Pendiente por cobrar', 'value' => $pendingTotal, 'format' => 'money'],
        ['label' => 'Órdenes anuladas', 'value' => $voidedCount, 'format' => 'integer'],
        ['label' => 'Monto anulado', 'value' => $voidedTotal, 'format' => 'money'],
    ];
    $tables = [
        reportTable('Cobros por método de pago', [
            reportColumn('Método', 'text', 'L', 3), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], reportPaymentRows($byMethod, $paidTotal)),
        reportTable('Cobros por vendedor', [
            reportColumn('Vendedor', 'text', 'L', 3), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2),
        ], reportCashierRows($byCashier)),
        reportTable('Detalle de cobros', [
            reportColumn('Orden', 'integer', 'R', 1), reportColumn('Hora', 'text', 'L', 1),
            reportColumn('Vendedor', 'text', 'L', 3), reportColumn('Método', 'text', 'L', 3),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Estado', 'text', 'L', 1),
        ], $detailRows),
    ];
    $tables = array_merge($tables, reportStatusTables($pending, $voided));

    return [
        'type' => 'cierre_diario', 'title' => 'Cierre de caja diario',
        'period' => $day, 'period_label' => reportDayLabel($day),
        'generated_at' => reportLocalTimestamp(gmdate('Y-m-d H:i:s'), 'd/m/Y H:i'),
        'metrics' => $metrics, 'tables' => $tables,
        'notes' => [
            'El cierre suma cobros registrados durante la fecha seleccionada. Las órdenes pendientes se muestran aparte.',
            'El sistema todavía no registra fondo inicial, efectivo contado, retiros, gastos ni devoluciones. Las anulaciones se muestran aparte y no se descuentan del total cobrado.',
            'Los cobros ya existentes antes de registrar la fecha de pago usan su fecha de creación como referencia; las anulaciones antiguas que ya no tengan fecha de cobro no se pueden reconstruir.',
        ],
    ];
}

function reportBuildMonthlyClose(PDO $pdo, array $filters): array {
    $month = $filters['month'] ?? substr(reportToday(), 0, 7);
    if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new ReportRequestException('El mes debe tener el formato AAAA-MM.');
    }
    [$year, $monthNumber] = array_map('intval', explode('-', $month));
    if (!checkdate($monthNumber, 1, $year)) throw new ReportRequestException('El mes seleccionado no es válido.');
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $monthNumber), reportTimezone());
    $afterLast = $first->modify('first day of next month');
    $from = $first->format('Y-m-d');
    $to = $afterLast->format('Y-m-d');
    [$start, $end] = reportUtcBounds($from, $to);

    $stmt = $pdo->prepare(
        "SELECT total_amount, payment_method, username, paid_at
         FROM sales WHERE paid_at IS NOT NULL AND paid_at >= ? AND paid_at < ? ORDER BY paid_at"
    );
    $stmt->execute([$start, $end]);
    $byDay = [];
    $byMethod = [];
    $byCashier = [];
    $paidTotal = 0.0;
    $paidCount = 0;
    for ($day = $first; $day < $afterLast; $day = $day->modify('+1 day')) {
        $byDay[$day->format('Y-m-d')] = [0, 0.0];
    }
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sale) {
        $amount = (float)$sale['total_amount'];
        $paidTotal += $amount;
        $paidCount++;
        $day = reportLocalTimestamp($sale['paid_at'], 'Y-m-d');
        $byDay[$day] = $byDay[$day] ?? [0, 0.0];
        $byDay[$day][0]++;
        $byDay[$day][1] += $amount;
        $method = (string)($sale['payment_method'] ?? '');
        $byMethod[$method] = $byMethod[$method] ?? [0, 0.0];
        $byMethod[$method][0]++;
        $byMethod[$method][1] += $amount;
        $cashier = trim((string)($sale['username'] ?? '')) ?: 'Sin asignar';
        $byCashier[$cashier] = $byCashier[$cashier] ?? [$cashier, 0, 0.0];
        $byCashier[$cashier][1]++;
        $byCashier[$cashier][2] += $amount;
    }
    $paidTotal = round($paidTotal, 2);
    [$pendingCount, $pendingTotal] = reportStatusSummary($pdo, 'pending', 'created_at', $from, $to);
    [$voidedCount, $voidedTotal] = reportStatusSummary($pdo, 'voided', 'COALESCE(voided_at, created_at)', $from, $to);

    $dayRows = [];
    $daysWithSales = 0;
    foreach ($byDay as $date => [$count, $amount]) {
        if ($count > 0) $daysWithSales++;
        $dayRows[] = [reportDayLabel($date), $count, round($amount, 2)];
    }
    $cashTotal = (float)($byMethod['cash'][1] ?? 0);
    $otherPayments = max(0, round($paidTotal - $cashTotal, 2));
    $metrics = [
        ['label' => 'Total cobrado', 'value' => $paidTotal, 'format' => 'money'],
            ['label' => 'Cobros registrados', 'value' => $paidCount, 'format' => 'integer'],
        ['label' => 'Ticket promedio', 'value' => $paidCount ? round($paidTotal / $paidCount, 2) : 0, 'format' => 'money'],
        ['label' => 'Días con ventas', 'value' => $daysWithSales, 'format' => 'integer'],
        ['label' => 'Efectivo registrado', 'value' => round($cashTotal, 2), 'format' => 'money'],
        ['label' => 'Otros medios de pago', 'value' => $otherPayments, 'format' => 'money'],
        ['label' => 'Órdenes pendientes', 'value' => $pendingCount, 'format' => 'integer'],
        ['label' => 'Pendiente por cobrar', 'value' => $pendingTotal, 'format' => 'money'],
        ['label' => 'Órdenes anuladas', 'value' => $voidedCount, 'format' => 'integer'],
        ['label' => 'Monto anulado', 'value' => $voidedTotal, 'format' => 'money'],
    ];
    $tables = [
        reportTable('Ventas por día', [
            reportColumn('Fecha', 'text', 'L', 2), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto cobrado', 'money', 'R', 2),
        ], $dayRows),
        reportTable('Cobros por método de pago', [
            reportColumn('Método', 'text', 'L', 3), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], reportPaymentRows($byMethod, $paidTotal)),
        reportTable('Cobros por vendedor', [
            reportColumn('Vendedor', 'text', 'L', 3), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2),
        ], reportCashierRows($byCashier)),
    ];
    return [
        'type' => 'cierre_mensual', 'title' => 'Cierre de caja mensual',
        'period' => $month, 'period_label' => $first->format('m/Y'),
        'generated_at' => reportLocalTimestamp(gmdate('Y-m-d H:i:s'), 'd/m/Y H:i'),
        'metrics' => $metrics, 'tables' => $tables,
        'notes' => [
            'Las órdenes pendientes se cuentan por fecha de creación y las anulaciones por fecha de anulación.',
            'El sistema todavía no registra fondo inicial, efectivo contado, retiros, gastos ni devoluciones. Las anulaciones se muestran aparte y no se descuentan del total cobrado.',
            'Los cobros ya existentes antes de registrar la fecha de pago usan su fecha de creación como referencia; las anulaciones antiguas que ya no tengan fecha de cobro no se pueden reconstruir.',
        ],
    ];
}

function reportBuildTopSellers(PDO $pdo, array $filters): array {
    $today = reportToday();
    $from = reportRequireDate($filters['from'] ?? substr($today, 0, 8) . '01', 'La fecha inicial');
    $to = reportRequireDate($filters['to'] ?? $today, 'La fecha final');
    if ($from > $to) throw new ReportRequestException('La fecha inicial es posterior a la fecha final.');
    $span = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
    if ($span > 366) throw new ReportRequestException('El rango máximo para este reporte es de 366 días.');
    $limit = filter_var($filters['limit'] ?? 20, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 5 || $limit > 100) throw new ReportRequestException('La cantidad de productos debe estar entre 5 y 100.');
    $after = (new DateTimeImmutable($to, reportTimezone()))->modify('+1 day')->format('Y-m-d');
    [$start, $end] = reportUtcBounds($from, $after);
    $stmt = $pdo->prepare(
        "SELECT COALESCE(p.name, 'Producto #' || CAST(si.product_id AS TEXT)) AS name,
                COALESCE(p.category, 'Sin categoría') AS category,
                SUM(si.quantity) AS units, COUNT(DISTINCT s.id) AS orders_count,
                COALESCE(SUM(si.subtotal), 0) AS amount
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'paid' AND s.paid_at >= ? AND s.paid_at < ?
         GROUP BY si.product_id, p.id, p.name, p.category
         ORDER BY SUM(si.quantity) DESC, SUM(si.subtotal) DESC, name ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $start, PDO::PARAM_STR);
    $stmt->bindValue(2, $end, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = [];
    $totalUnits = 0;
    $totalAmount = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index => $product) {
        $units = (int)$product['units'];
        $amount = round((float)$product['amount'], 2);
        $totalUnits += $units;
        $totalAmount += $amount;
        $rows[] = [$index + 1, $product['name'], $product['category'], $units, (int)$product['orders_count'], $amount];
    }
    return [
        'type' => 'mas_vendidos', 'title' => 'Productos más vendidos',
        'period' => "$from - $to", 'period_label' => reportDayLabel($from) . ' al ' . reportDayLabel($to),
        'generated_at' => reportLocalTimestamp(gmdate('Y-m-d H:i:s'), 'd/m/Y H:i'),
        'metrics' => [
            ['label' => 'Productos listados', 'value' => count($rows), 'format' => 'integer'],
            ['label' => 'Unidades vendidas', 'value' => $totalUnits, 'format' => 'integer'],
            ['label' => 'Monto de los productos listados', 'value' => round($totalAmount, 2), 'format' => 'money'],
        ],
        'tables' => [reportTable('Ranking de productos', [
            reportColumn('#', 'integer', 'R', 1), reportColumn('Producto', 'text', 'L', 4),
            reportColumn('Categoría', 'text', 'L', 2), reportColumn('Unidades', 'integer', 'R', 1),
            reportColumn('Órdenes', 'integer', 'R', 1), reportColumn('Monto vendido', 'money', 'R', 2),
        ], $rows)],
        'notes' => ['Se consideran únicamente ventas cobradas en el período, por su fecha de pago.'],
    ];
}

function reportBuildRestock(PDO $pdo, array $filters): array {
    $targetDays = filter_var($filters['target_days'] ?? 7, FILTER_VALIDATE_INT);
    if ($targetDays === false || $targetDays < 1 || $targetDays > 60) {
        throw new ReportRequestException('Los días objetivo deben estar entre 1 y 60.');
    }
    $insights = computeInsights($pdo, $targetDays);
    $productRows = [];
    $materialsRows = [];
    $productsToMake = 0;
    $productsToBuy = 0;
    $blockedProducts = 0;
    foreach ($insights['products'] as $product) {
        if ($product['need'] <= 0 && $product['status'] === 'ok') continue;
        $required = max((int)$product['need'], (int)$product['min_stock'] - (int)$product['stock'], 0);
        $suggestedProduction = $product['has_recipe']
            ? ($product['can_make'] === null ? $required : min($required, (int)$product['can_make']))
            : 0;
        $action = 'Sin sugerencia';
        if ($product['has_recipe']) {
            if ($suggestedProduction > 0) {
                $action = 'Elaborar ' . $suggestedProduction;
                $productsToMake++;
            } elseif ($required > 0) {
                $action = 'Revisar insumos';
                $blockedProducts++;
            }
        } else {
            $recommendation = $required;
            if ($recommendation > 0) {
                $action = 'Reponer ' . $recommendation;
                $productsToBuy++;
            }
        }
        $productRows[] = [
            $product['name'], $product['category'], (int)$product['stock'], (int)$product['min_stock'],
            (int)$product['sold_30d'], (float)$product['avg_daily'],
            $product['coverage_days'] === null ? null : (float)$product['coverage_days'],
            $required, $action, reportStatusLabel($product['status']),
        ];
    }
    foreach ($insights['materials'] as $material) {
        if ($material['suggested_purchase'] <= 0 && $material['status'] === 'ok') continue;
        $blocks = array_map(fn($block) => $block['name'], $material['blocks']);
        $materialsRows[] = [
            $material['name'], $material['unit'], (float)$material['stock'], (float)$material['min_stock'],
            (float)$material['daily_consumption'],
            $material['coverage_days'] === null ? null : (float)$material['coverage_days'],
            (float)$material['suggested_purchase'], implode(', ', $blocks), reportStatusLabel($material['status']),
        ];
    }
    usort($productRows, fn($a, $b) => (($a[9] === 'OK') <=> ($b[9] === 'OK')) ?: ($a[2] <=> $b[2]) ?: strcasecmp($a[0], $b[0]));
    usort($materialsRows, fn($a, $b) => (($a[8] === 'OK') <=> ($b[8] === 'OK')) ?: ($a[2] <=> $b[2]) ?: strcasecmp($a[0], $b[0]));

    return [
        'type' => 'reponer', 'title' => 'Reposición de mercancía e insumos',
        'period' => (string)$targetDays, 'period_label' => "Objetivo de cobertura: $targetDays días",
        'generated_at' => reportLocalTimestamp(gmdate('Y-m-d H:i:s'), 'd/m/Y H:i'),
        'metrics' => [
            ['label' => 'Productos por reponer', 'value' => $productsToBuy, 'format' => 'integer'],
            ['label' => 'Productos por elaborar', 'value' => $productsToMake, 'format' => 'integer'],
            ['label' => 'Productos bloqueados por insumos', 'value' => $blockedProducts, 'format' => 'integer'],
            ['label' => 'Insumos por comprar', 'value' => count(array_filter($insights['materials'], fn($m) => $m['suggested_purchase'] > 0)), 'format' => 'integer'],
        ],
        'tables' => [
            reportTable('Productos', [
                reportColumn('Producto', 'text', 'L', 3), reportColumn('Categoría', 'text', 'L', 2),
                reportColumn('Stock', 'quantity', 'R', 1), reportColumn('Mínimo', 'quantity', 'R', 1),
                reportColumn('Unid. vendidas', 'quantity', 'R', 1), reportColumn('Venta/día', 'quantity', 'R', 1),
                reportColumn('Cobertura (d)', 'days', 'R', 1), reportColumn('Necesidad', 'quantity', 'R', 1),
                reportColumn('Acción sugerida', 'text', 'L', 2), reportColumn('Estado', 'text', 'L', 1),
            ], $productRows),
            reportTable('Insumos y materias primas', [
                reportColumn('Insumo', 'text', 'L', 3), reportColumn('Unidad', 'text', 'L', 1),
                reportColumn('Stock', 'quantity', 'R', 1), reportColumn('Mínimo', 'quantity', 'R', 1),
                reportColumn('Consumo/día', 'quantity', 'R', 1), reportColumn('Cobertura (d)', 'days', 'R', 1),
                reportColumn('Compra sugerida', 'quantity', 'R', 2), reportColumn('Productos afectados', 'text', 'L', 3),
                reportColumn('Estado', 'text', 'L', 1),
            ], $materialsRows),
        ],
        'notes' => [
            "La estimación usa las ventas de los últimos {$insights['window_days']} días y el objetivo de cobertura seleccionado.",
            'Los productos con receta muestran unidades que se pueden elaborar según los insumos disponibles. La compra sugerida de insumos incluye el mínimo configurado y las necesidades estimadas de producción.',
        ],
    ];
}

function reportBuildData(PDO $pdo, string $type, array $filters): array {
    $data = match ($type) {
        'cierre_diario' => reportBuildDailyClose($pdo, $filters),
        'cierre_mensual' => reportBuildMonthlyClose($pdo, $filters),
        'mas_vendidos' => reportBuildTopSellers($pdo, $filters),
        'reponer' => reportBuildRestock($pdo, $filters),
        default => throw new ReportRequestException('Tipo de reporte no válido.'),
    };
    $config = reportBusinessConfig();
    $data['currency'] = (string)($config['currency'] ?? '$');
    $data['business_name'] = (string)($config['name'] ?? 'Sweet POS');
    return $data;
}
