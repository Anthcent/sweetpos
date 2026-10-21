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

// ---------------------------------------------------------------------------
// Shared helpers for the period-based reports
// ---------------------------------------------------------------------------

function reportGeneratedAt(): string {
    return reportLocalTimestamp(gmdate('Y-m-d H:i:s'), 'd/m/Y H:i');
}

/** Validate the from/to local date filters and return the range with its UTC bounds. */
function reportRange(array $filters, int $maxDays = 366): array {
    $today = reportToday();
    $from = reportRequireDate($filters['from'] ?? substr($today, 0, 8) . '01', 'La fecha inicial');
    $to = reportRequireDate($filters['to'] ?? $today, 'La fecha final');
    if ($from > $to) throw new ReportRequestException('La fecha inicial es posterior a la fecha final.');
    $days = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
    if ($days > $maxDays) throw new ReportRequestException("El rango máximo para este reporte es de $maxDays días.");
    $after = (new DateTimeImmutable($to, reportTimezone()))->modify('+1 day')->format('Y-m-d');
    [$start, $end] = reportUtcBounds($from, $after);
    return [
        'from' => $from, 'to' => $to, 'after' => $after, 'start' => $start, 'end' => $end, 'days' => $days,
        'period' => "$from - $to",
        'label' => $from === $to ? reportDayLabel($from) : reportDayLabel($from) . ' al ' . reportDayLabel($to),
    ];
}

function reportResult(string $type, string $title, array $range, array $metrics, array $tables, array $notes): array {
    return [
        'type' => $type, 'title' => $title,
        'period' => $range['period'], 'period_label' => $range['label'],
        'generated_at' => reportGeneratedAt(),
        'metrics' => $metrics, 'tables' => $tables, 'notes' => $notes,
    ];
}

function reportMetric(string $label, $value, string $format = 'text'): array {
    return ['label' => $label, 'value' => $value, 'format' => $format];
}

function reportShare(float $part, float $total): float {
    return $total > 0 ? round($part * 100 / $total, 2) : 0.0;
}

function reportWeekdayName(int $isoDay): string {
    return ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][$isoDay - 1] ?? '';
}

function reportSellerName($value): string {
    $name = trim((string)($value ?? ''));
    return $name !== '' ? $name : 'Sin asignar';
}

/** Paid sales collected inside the range (by payment date). */
function reportPaidSales(PDO $pdo, array $range): array {
    $stmt = $pdo->prepare(
        "SELECT id, total_amount, payment_method, username, paid_at
         FROM sales WHERE status = 'paid' AND paid_at IS NOT NULL AND paid_at >= ? AND paid_at < ?
         ORDER BY paid_at, id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Units sold per sale id for the paid sales of the range. */
function reportPaidUnitsBySale(PDO $pdo, array $range): array {
    $stmt = $pdo->prepare(
        "SELECT si.sale_id, SUM(si.quantity) AS units
         FROM sale_items si JOIN sales s ON s.id = si.sale_id
         WHERE s.status = 'paid' AND s.paid_at IS NOT NULL AND s.paid_at >= ? AND s.paid_at < ?
         GROUP BY si.sale_id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $units = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $units[(int)$row['sale_id']] = (int)$row['units'];
    return $units;
}

/** Units and amount sold per product id for the paid sales of the range. */
function reportPaidSalesByProduct(PDO $pdo, array $range): array {
    $stmt = $pdo->prepare(
        "SELECT si.product_id, SUM(si.quantity) AS units, COUNT(DISTINCT s.id) AS orders_count,
                COALESCE(SUM(si.subtotal), 0) AS amount
         FROM sale_items si JOIN sales s ON s.id = si.sale_id
         WHERE s.status = 'paid' AND s.paid_at IS NOT NULL AND s.paid_at >= ? AND s.paid_at < ?
         GROUP BY si.product_id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $sold = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sold[(int)$row['product_id']] = [
            'units' => (int)$row['units'], 'orders' => (int)$row['orders_count'], 'amount' => (float)$row['amount'],
        ];
    }
    return $sold;
}

/**
 * Current unit cost of every product: recipe cost (sum of quantity x material
 * cost_per_unit) for products with a recipe, otherwise the last purchase unit
 * cost recorded in the stock ledger. Returns [id => ['cost' => ?float, 'source' => string]].
 */
function reportProductUnitCosts(PDO $pdo): array {
    $costs = [];
    $recipes = $pdo->query(
        "SELECT pm.product_id, SUM(pm.quantity_required * COALESCE(m.cost_per_unit, 0)) AS cost,
                SUM(CASE WHEN COALESCE(m.cost_per_unit, 0) <= 0 THEN 1 ELSE 0 END) AS missing
         FROM product_materials pm JOIN materials m ON m.id = pm.material_id
         GROUP BY pm.product_id"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recipes as $row) {
        $missing = (int)$row['missing'] > 0;
        $cost = (float)$row['cost'];
        $costs[(int)$row['product_id']] = [
            'cost' => $cost > 0 ? round($cost, 4) : null,
            'source' => $cost <= 0 ? 'Receta sin costos' : ($missing ? 'Receta incompleta' : 'Receta'),
            'has_recipe' => true,
        ];
    }
    $purchases = $pdo->query(
        "SELECT item_id, unit_cost FROM stock_movements
         WHERE item_type = 'product' AND reason = 'purchase' AND unit_cost IS NOT NULL
         ORDER BY item_id, created_at DESC, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($purchases as $row) {
        $id = (int)$row['item_id'];
        if (isset($costs[$id])) continue;
        $costs[$id] = ['cost' => round((float)$row['unit_cost'], 4), 'source' => 'Última compra', 'has_recipe' => false];
    }
    return $costs;
}

function reportProductCost(array $costs, int $productId): array {
    return $costs[$productId] ?? ['cost' => null, 'source' => 'Sin costo', 'has_recipe' => false];
}

const REPORT_DECREASE_REASONS = ['Merma', 'Vencido', 'Dañado', 'Consumo interno', 'Corrección de conteo', 'Otro'];

/** Split an adjustment note stored as "<Motivo>: <detalle>" into [motivo, detalle]. */
function reportParseDecreaseNote(?string $note): array {
    $note = trim((string)($note ?? ''));
    if ($note === '') return ['Sin motivo', ''];
    $parts = explode(':', $note, 2);
    $prefix = trim($parts[0]);
    foreach (REPORT_DECREASE_REASONS as $reason) {
        if (mb_strtolower($prefix, 'UTF-8') === mb_strtolower($reason, 'UTF-8')) {
            return [$reason, isset($parts[1]) ? trim($parts[1]) : ''];
        }
    }
    return ['Ajuste manual', $note];
}

// ---------------------------------------------------------------------------
// Sales reports
// ---------------------------------------------------------------------------

function reportBuildSalesPeriod(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $sales = reportPaidSales($pdo, $range);
    $units = reportPaidUnitsBySale($pdo, $range);
    $byDay = [];
    $first = new DateTimeImmutable($range['from'], reportTimezone());
    $last = new DateTimeImmutable($range['after'], reportTimezone());
    for ($day = $first; $day < $last; $day = $day->modify('+1 day')) {
        $byDay[$day->format('Y-m-d')] = [0, 0, 0.0];
    }
    $total = 0.0;
    $totalUnits = 0;
    foreach ($sales as $sale) {
        $amount = (float)$sale['total_amount'];
        $saleUnits = $units[(int)$sale['id']] ?? 0;
        $total += $amount;
        $totalUnits += $saleUnits;
        $day = reportLocalTimestamp($sale['paid_at'], 'Y-m-d');
        $byDay[$day] = $byDay[$day] ?? [0, 0, 0.0];
        $byDay[$day][0]++;
        $byDay[$day][1] += $saleUnits;
        $byDay[$day][2] += $amount;
    }
    $rows = [];
    $bestDay = null;
    $daysWithSales = 0;
    foreach ($byDay as $date => [$count, $dayUnits, $amount]) {
        if ($count > 0) $daysWithSales++;
        if ($count > 0 && ($bestDay === null || $amount > $byDay[$bestDay][2])) $bestDay = $date;
        $weekday = (int)DateTimeImmutable::createFromFormat('!Y-m-d', $date, reportTimezone())->format('N');
        $rows[] = [reportDayLabel($date), reportWeekdayName($weekday), $count, $dayUnits, round($amount, 2),
                   $count ? round($amount / $count, 2) : null];
    }
    $total = round($total, 2);
    $count = count($sales);
    [$pendingCount, $pendingTotal] = reportStatusSummary($pdo, 'pending', 'created_at', $range['from'], $range['after']);
    [$voidedCount, $voidedTotal] = reportStatusSummary($pdo, 'voided', 'COALESCE(voided_at, created_at)', $range['from'], $range['after']);

    return reportResult('ventas_periodo', 'Ventas por período', $range, [
        reportMetric('Total vendido', $total, 'money'),
        reportMetric('Ventas cobradas', $count, 'integer'),
        reportMetric('Ticket promedio', $count ? round($total / $count, 2) : 0, 'money'),
        reportMetric('Unidades vendidas', $totalUnits, 'integer'),
        reportMetric('Promedio por día', round($total / max(1, $range['days']), 2), 'money'),
        reportMetric('Días con ventas', $daysWithSales, 'integer'),
        reportMetric('Mejor día', $bestDay ? reportDayLabel($bestDay) : 'Sin ventas'),
        reportMetric('Venta del mejor día', $bestDay ? round($byDay[$bestDay][2], 2) : 0, 'money'),
        reportMetric('Órdenes pendientes', $pendingCount, 'integer'),
        reportMetric('Pendiente por cobrar', $pendingTotal, 'money'),
        reportMetric('Órdenes anuladas', $voidedCount, 'integer'),
        reportMetric('Monto anulado', $voidedTotal, 'money'),
    ], [
        reportTable('Ventas por día', [
            reportColumn('Fecha', 'text', 'L', 2), reportColumn('Día', 'text', 'L', 2),
            reportColumn('Ventas', 'integer', 'R', 1), reportColumn('Unidades', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Ticket promedio', 'money', 'R', 2),
        ], $rows),
    ], [
        'Se consideran las ventas cobradas por su fecha de pago. Las órdenes pendientes y anuladas se muestran aparte y no suman al total.',
    ]);
}

function reportBuildSalesByCategory(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $stmt = $pdo->prepare(
        "SELECT p.category, COUNT(DISTINCT s.id) AS orders_count, SUM(si.quantity) AS units,
                COALESCE(SUM(si.subtotal), 0) AS amount, COUNT(DISTINCT si.product_id) AS products_count
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'paid' AND s.paid_at IS NOT NULL AND s.paid_at >= ? AND s.paid_at < ?
         GROUP BY p.category"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $categories = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)($row['category'] ?? '')) ?: 'Sin categoría';
        $categories[$name] = $categories[$name] ?? [0, 0, 0.0, 0];
        $categories[$name][0] += (int)$row['orders_count'];
        $categories[$name][1] += (int)$row['units'];
        $categories[$name][2] += (float)$row['amount'];
        $categories[$name][3] += (int)$row['products_count'];
    }
    $total = array_sum(array_map(fn($c) => $c[2], $categories));
    uasort($categories, fn($a, $b) => $b[2] <=> $a[2]);

    $stmt = $pdo->prepare(
        "SELECT p.category, COALESCE(p.name, 'Producto #' || CAST(si.product_id AS TEXT)) AS name,
                SUM(si.quantity) AS units, COALESCE(SUM(si.subtotal), 0) AS amount
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'paid' AND s.paid_at IS NOT NULL AND s.paid_at >= ? AND s.paid_at < ?
         GROUP BY si.product_id, p.id, p.name, p.category"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $productRows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $category = trim((string)($row['category'] ?? '')) ?: 'Sin categoría';
        $amount = (float)$row['amount'];
        $productRows[] = [$category, $row['name'], (int)$row['units'], round($amount, 2),
                          reportShare($amount, $categories[$category][2] ?? 0)];
    }
    $order = array_flip(array_keys($categories));
    usort($productRows, fn($a, $b) => (($order[$a[0]] ?? 0) <=> ($order[$b[0]] ?? 0)) ?: ($b[3] <=> $a[3]) ?: strcasecmp($a[1], $b[1]));

    $rows = [];
    foreach ($categories as $name => [$orders, $units, $amount, $products]) {
        $rows[] = [$name, $products, $units, $orders, round($amount, 2), reportShare($amount, $total)];
    }
    $leader = array_key_first($categories);
    return reportResult('ventas_categoria', 'Ventas por categoría', $range, [
        reportMetric('Total vendido', round($total, 2), 'money'),
        reportMetric('Categorías con ventas', count($categories), 'integer'),
        reportMetric('Unidades vendidas', array_sum(array_map(fn($c) => $c[1], $categories)), 'integer'),
        reportMetric('Categoría líder', $leader ?? 'Sin ventas'),
    ], [
        reportTable('Resumen por categoría', [
            reportColumn('Categoría', 'text', 'L', 3), reportColumn('Productos', 'integer', 'R', 1),
            reportColumn('Unidades', 'integer', 'R', 1), reportColumn('Órdenes', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], $rows),
        reportTable('Productos por categoría', [
            reportColumn('Categoría', 'text', 'L', 2), reportColumn('Producto', 'text', 'L', 4),
            reportColumn('Unidades', 'integer', 'R', 1), reportColumn('Monto', 'money', 'R', 2),
            reportColumn('Part. en la categoría', 'percent', 'R', 2),
        ], $productRows),
    ], [
        'Se consideran las ventas cobradas por su fecha de pago y la categoría actual de cada producto.',
        'Una orden con productos de varias categorías se cuenta en cada una de ellas.',
    ]);
}

function reportBuildSalesBySeller(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $sales = reportPaidSales($pdo, $range);
    $units = reportPaidUnitsBySale($pdo, $range);
    $sellers = [];
    $total = 0.0;
    foreach ($sales as $sale) {
        $name = reportSellerName($sale['username']);
        $amount = (float)$sale['total_amount'];
        $sellers[$name] = $sellers[$name] ?? [0, 0, 0.0];
        $sellers[$name][0]++;
        $sellers[$name][1] += $units[(int)$sale['id']] ?? 0;
        $sellers[$name][2] += $amount;
        $total += $amount;
    }
    uksort($sellers, fn($a, $b) => ($sellers[$b][2] <=> $sellers[$a][2]) ?: strcasecmp($a, $b));
    $rows = [];
    foreach ($sellers as $name => [$count, $sellerUnits, $amount]) {
        $rows[] = [$name, $count, $sellerUnits, round($amount, 2), round($amount / max(1, $count), 2), reportShare($amount, $total)];
    }

    $voided = reportStatusDetails($pdo, 'voided', 'COALESCE(voided_at, created_at)', $range['from'], $range['after']);
    $voidRows = [];
    foreach ($voided as $sale) {
        $name = reportSellerName($sale['voided_by']);
        $voidRows[$name] = $voidRows[$name] ?? [$name, 0, 0.0];
        $voidRows[$name][1]++;
        $voidRows[$name][2] += (float)$sale['total_amount'];
    }
    $voidRows = array_values($voidRows);
    usort($voidRows, fn($a, $b) => ($b[2] <=> $a[2]) ?: strcasecmp($a[0], $b[0]));
    $voidRows = array_map(fn($r) => [$r[0], $r[1], round($r[2], 2)], $voidRows);

    $leader = array_key_first($sellers);
    return reportResult('ventas_vendedor', 'Ventas por vendedor', $range, [
        reportMetric('Total vendido', round($total, 2), 'money'),
        reportMetric('Ventas cobradas', count($sales), 'integer'),
        reportMetric('Vendedores con ventas', count($sellers), 'integer'),
        reportMetric('Vendedor con más ventas', $leader ?? 'Sin ventas'),
    ], [
        reportTable('Ventas por vendedor', [
            reportColumn('Vendedor', 'text', 'L', 3), reportColumn('Ventas', 'integer', 'R', 1),
            reportColumn('Unidades', 'integer', 'R', 1), reportColumn('Monto', 'money', 'R', 2),
            reportColumn('Ticket promedio', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], $rows),
        reportTable('Anulaciones por usuario', [
            reportColumn('Usuario', 'text', 'L', 3), reportColumn('Anulaciones', 'integer', 'R', 1),
            reportColumn('Monto anulado', 'money', 'R', 2),
        ], $voidRows),
    ], [
        'El vendedor es el usuario que registró la orden. Se consideran las ventas cobradas por su fecha de pago.',
        'Las anulaciones se agrupan por el usuario que anuló la orden, según la fecha de anulación.',
    ]);
}

function reportBuildPeakHours(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $sales = reportPaidSales($pdo, $range);
    $hours = array_fill(0, 24, [0, 0.0]);
    $weekdays = array_fill(1, 7, [0, 0.0]);
    $total = 0.0;
    foreach ($sales as $sale) {
        $amount = (float)$sale['total_amount'];
        $hour = (int)reportLocalTimestamp($sale['paid_at'], 'G');
        $weekday = (int)reportLocalTimestamp($sale['paid_at'], 'N');
        $hours[$hour][0]++;
        $hours[$hour][1] += $amount;
        if (isset($weekdays[$weekday])) {
            $weekdays[$weekday][0]++;
            $weekdays[$weekday][1] += $amount;
        }
        $total += $amount;
    }
    $active = array_keys(array_filter($hours, fn($h) => $h[0] > 0));
    $hourRows = [];
    $peakHour = null;
    if ($active) {
        for ($hour = min($active); $hour <= max($active); $hour++) {
            [$count, $amount] = $hours[$hour];
            if ($count > 0 && ($peakHour === null || $count > $hours[$peakHour][0]
                || ($count === $hours[$peakHour][0] && $amount > $hours[$peakHour][1]))) {
                $peakHour = $hour;
            }
            $hourRows[] = [sprintf('%02d:00 - %02d:59', $hour, $hour), $count, round($amount, 2),
                           $count ? round($amount / $count, 2) : null,
                           round($count / max(1, $range['days']), 2), reportShare($amount, $total)];
        }
    }
    $weekdayRows = [];
    $peakDay = null;
    foreach ($weekdays as $day => [$count, $amount]) {
        if ($count > 0 && ($peakDay === null || $amount > $weekdays[$peakDay][1])) $peakDay = $day;
        $weekdayRows[] = [reportWeekdayName($day), $count, round($amount, 2),
                          $count ? round($amount / $count, 2) : null, reportShare($amount, $total)];
    }
    return reportResult('horas_pico', 'Horas pico de venta', $range, [
        reportMetric('Hora pico', $peakHour === null ? 'Sin ventas' : sprintf('%02d:00 - %02d:59', $peakHour, $peakHour)),
        reportMetric('Ventas en la hora pico', $peakHour === null ? 0 : $hours[$peakHour][0], 'integer'),
        reportMetric('Día más fuerte', $peakDay === null ? 'Sin ventas' : reportWeekdayName($peakDay)),
        reportMetric('Ventas cobradas', count($sales), 'integer'),
    ], [
        reportTable('Ventas por hora del día', [
            reportColumn('Franja horaria', 'text', 'L', 2), reportColumn('Ventas', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Ticket promedio', 'money', 'R', 2),
            reportColumn('Ventas por día', 'quantity', 'R', 1), reportColumn('Participación', 'percent', 'R', 2),
        ], $hourRows),
        reportTable('Ventas por día de la semana', [
            reportColumn('Día', 'text', 'L', 2), reportColumn('Ventas', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Ticket promedio', 'money', 'R', 2),
            reportColumn('Participación', 'percent', 'R', 2),
        ], $weekdayRows),
    ], [
        'Las horas usan la zona horaria del negocio y la hora de cobro de cada venta.',
        '"Ventas por día" es el promedio de ventas en esa franja por cada día del período.',
    ]);
}

function reportBuildPaymentMethods(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $sales = reportPaidSales($pdo, $range);
    $known = ['cash', 'card', 'transfer', 'pagomovil'];
    $byMethod = [];
    $byDay = [];
    $total = 0.0;
    $hasOther = false;
    foreach ($sales as $sale) {
        $amount = (float)$sale['total_amount'];
        $method = (string)($sale['payment_method'] ?? '');
        $byMethod[$method] = $byMethod[$method] ?? [0, 0.0];
        $byMethod[$method][0]++;
        $byMethod[$method][1] += $amount;
        $bucket = in_array($method, $known, true) ? $method : 'other';
        if ($bucket === 'other') $hasOther = true;
        $day = reportLocalTimestamp($sale['paid_at'], 'Y-m-d');
        $byDay[$day] = $byDay[$day] ?? array_fill_keys([...$known, 'other'], 0.0);
        $byDay[$day][$bucket] += $amount;
        $total += $amount;
    }
    $total = round($total, 2);
    $methodRows = [];
    foreach (reportPaymentRows($byMethod, $total) as $row) {
        $methodRows[] = [$row[0], $row[1], $row[2], $row[1] ? round($row[2] / $row[1], 2) : null, $row[3]];
    }
    ksort($byDay);
    $dayColumns = [reportColumn('Fecha', 'text', 'L', 2)];
    foreach ($known as $method) $dayColumns[] = reportColumn(reportPaymentLabel($method), 'money', 'R', 2);
    if ($hasOther) $dayColumns[] = reportColumn('Otros', 'money', 'R', 2);
    $dayColumns[] = reportColumn('Total', 'money', 'R', 2);
    $dayRows = [];
    foreach ($byDay as $day => $amounts) {
        $row = [reportDayLabel($day)];
        foreach ($known as $method) $row[] = round($amounts[$method], 2);
        if ($hasOther) $row[] = round($amounts['other'], 2);
        $row[] = round(array_sum($amounts), 2);
        $dayRows[] = $row;
    }
    $cash = (float)($byMethod['cash'][1] ?? 0);
    $leader = null;
    foreach ($byMethod as $method => [$count, $amount]) {
        if ($leader === null || $amount > $byMethod[$leader][1]) $leader = $method;
    }
    return reportResult('metodos_pago', 'Métodos de pago', $range, [
        reportMetric('Total cobrado', $total, 'money'),
        reportMetric('Efectivo', round($cash, 2), 'money'),
        reportMetric('Medios electrónicos', max(0, round($total - $cash, 2)), 'money'),
        reportMetric('Método principal', $leader === null ? 'Sin cobros' : reportPaymentLabel($leader === '' ? null : (string)$leader)),
    ], [
        reportTable('Cobros por método', [
            reportColumn('Método', 'text', 'L', 3), reportColumn('Cobros', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Ticket promedio', 'money', 'R', 2),
            reportColumn('Participación', 'percent', 'R', 2),
        ], $methodRows),
        reportTable('Métodos de pago por día', $dayColumns, $dayRows),
    ], [
        'Se consideran las ventas cobradas por su fecha de pago. "Medios electrónicos" incluye todo lo que no se cobró en efectivo.',
    ]);
}

function reportBuildVoids(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $stmt = $pdo->prepare(
        "SELECT s.id, s.total_amount, s.created_at, s.paid_at, s.voided_at, s.voided_by, s.void_reason, s.username,
                (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si WHERE si.sale_id = s.id) AS units
         FROM sales s
         WHERE s.status = 'voided' AND COALESCE(s.voided_at, s.created_at) >= ? AND COALESCE(s.voided_at, s.created_at) < ?
         ORDER BY COALESCE(s.voided_at, s.created_at), s.id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $voided = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0.0;
    $afterPayment = 0;
    $byUser = [];
    $byReason = [];
    $detail = [];
    foreach ($voided as $sale) {
        $amount = (float)$sale['total_amount'];
        $total += $amount;
        $wasPaid = $sale['paid_at'] !== null && $sale['paid_at'] !== '';
        if ($wasPaid) $afterPayment++;
        $user = reportSellerName($sale['voided_by']);
        $byUser[$user] = $byUser[$user] ?? [0, 0.0];
        $byUser[$user][0]++;
        $byUser[$user][1] += $amount;
        $reason = trim((string)($sale['void_reason'] ?? ''));
        $reasonKey = $reason === '' ? 'Sin motivo' : mb_strtoupper(mb_substr($reason, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($reason, 1, null, 'UTF-8');
        $byReason[$reasonKey] = $byReason[$reasonKey] ?? [0, 0.0];
        $byReason[$reasonKey][0]++;
        $byReason[$reasonKey][1] += $amount;
        $detail[] = [
            (int)$sale['id'], reportLocalTimestamp($sale['created_at']),
            reportLocalTimestamp($sale['voided_at'] ?: $sale['created_at']),
            reportSellerName($sale['username']), $user, $wasPaid ? 'Cobrada' : 'Pendiente',
            (int)$sale['units'], $reason !== '' ? $reason : 'Sin motivo', round($amount, 2),
        ];
    }
    $groupRows = function (array $groups) use ($total): array {
        uksort($groups, fn($a, $b) => ($groups[$b][1] <=> $groups[$a][1]) ?: strcasecmp($a, $b));
        $rows = [];
        foreach ($groups as $name => [$count, $amount]) $rows[] = [$name, $count, round($amount, 2), reportShare($amount, $total)];
        return $rows;
    };
    $paidTotal = array_sum(array_map(fn($s) => (float)$s['total_amount'], reportPaidSales($pdo, $range)));
    return reportResult('anulaciones', 'Órdenes anuladas', $range, [
        reportMetric('Órdenes anuladas', count($voided), 'integer'),
        reportMetric('Monto anulado', round($total, 2), 'money'),
        reportMetric('Anuladas después de cobrar', $afterPayment, 'integer'),
        reportMetric('Anulado frente a lo cobrado', reportShare($total, $paidTotal), 'percent'),
    ], [
        reportTable('Anulaciones por usuario', [
            reportColumn('Anuló', 'text', 'L', 3), reportColumn('Órdenes', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], $groupRows($byUser)),
        reportTable('Anulaciones por motivo', [
            reportColumn('Motivo', 'text', 'L', 4), reportColumn('Órdenes', 'integer', 'R', 1),
            reportColumn('Monto', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], $groupRows($byReason)),
        reportTable('Detalle de anulaciones', [
            reportColumn('Orden', 'integer', 'R', 1), reportColumn('Creada', 'text', 'L', 2),
            reportColumn('Anulada', 'text', 'L', 2), reportColumn('Vendedor', 'text', 'L', 2),
            reportColumn('Anuló', 'text', 'L', 2), reportColumn('Estado previo', 'text', 'L', 1),
            reportColumn('Unidades', 'integer', 'R', 1), reportColumn('Motivo', 'text', 'L', 3),
            reportColumn('Monto', 'money', 'R', 2),
        ], $detail),
    ], [
        'Las anulaciones se cuentan por su fecha de anulación. Al anular una orden el stock de sus productos se restituye.',
        '"Anulado frente a lo cobrado" compara el monto anulado con las ventas cobradas del mismo período.',
    ]);
}

// ---------------------------------------------------------------------------
// Production reports
// ---------------------------------------------------------------------------

function reportBuildProduction(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $stmt = $pdo->prepare(
        "SELECT pl.id, pl.product_id, pl.quantity_produced, pl.username, pl.created_at,
                COALESCE(p.name, 'Producto #' || CAST(pl.product_id AS TEXT)) AS name,
                COALESCE(p.category, 'Sin categoría') AS category
         FROM production_log pl LEFT JOIN products p ON p.id = pl.product_id
         WHERE pl.voided_at IS NULL AND pl.created_at >= ? AND pl.created_at < ?
         ORDER BY pl.created_at, pl.id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT sm.ref_id, SUM(-sm.qty_delta * COALESCE(m.cost_per_unit, 0)) AS cost
         FROM stock_movements sm
         JOIN production_log pl ON pl.id = sm.ref_id
         LEFT JOIN materials m ON m.id = sm.item_id
         WHERE sm.item_type = 'material' AND sm.reason = 'production'
           AND pl.voided_at IS NULL AND pl.created_at >= ? AND pl.created_at < ?
         GROUP BY sm.ref_id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $costByLog = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $costByLog[(int)$row['ref_id']] = (float)$row['cost'];
    $sold = reportPaidSalesByProduct($pdo, $range);

    $byProduct = [];
    $byUser = [];
    $detail = [];
    $totalUnits = 0;
    $totalCost = 0.0;
    foreach ($logs as $log) {
        $productId = (int)$log['product_id'];
        $qty = (int)$log['quantity_produced'];
        $cost = $costByLog[(int)$log['id']] ?? 0.0;
        $totalUnits += $qty;
        $totalCost += $cost;
        $byProduct[$productId] = $byProduct[$productId] ?? [$log['name'], $log['category'], 0, 0, 0.0];
        $byProduct[$productId][2]++;
        $byProduct[$productId][3] += $qty;
        $byProduct[$productId][4] += $cost;
        $user = reportSellerName($log['username']);
        $byUser[$user] = $byUser[$user] ?? [0, 0];
        $byUser[$user][0]++;
        $byUser[$user][1] += $qty;
        $detail[] = [reportLocalTimestamp($log['created_at']), $log['name'], $qty, $user, round($cost, 2)];
    }
    uasort($byProduct, fn($a, $b) => ($b[3] <=> $a[3]) ?: strcasecmp($a[0], $b[0]));
    $productRows = [];
    foreach ($byProduct as $productId => [$name, $category, $batches, $units, $cost]) {
        $productRows[] = [$name, $category, $batches, $units, $sold[$productId]['units'] ?? 0,
                          round($cost, 2), $units ? round($cost / $units, 2) : null];
    }
    uksort($byUser, fn($a, $b) => ($byUser[$b][1] <=> $byUser[$a][1]) ?: strcasecmp($a, $b));
    $userRows = [];
    foreach ($byUser as $user => [$batches, $units]) $userRows[] = [$user, $batches, $units];

    return reportResult('elaboracion', 'Elaboración por producto', $range, [
        reportMetric('Elaboraciones', count($logs), 'integer'),
        reportMetric('Unidades elaboradas', $totalUnits, 'integer'),
        reportMetric('Productos elaborados', count($byProduct), 'integer'),
        reportMetric('Costo estimado de insumos', round($totalCost, 2), 'money'),
    ], [
        reportTable('Elaboración por producto', [
            reportColumn('Producto', 'text', 'L', 3), reportColumn('Categoría', 'text', 'L', 2),
            reportColumn('Elaboraciones', 'integer', 'R', 1), reportColumn('Unidades', 'integer', 'R', 1),
            reportColumn('Vendidas en el período', 'integer', 'R', 1), reportColumn('Costo de insumos', 'money', 'R', 2),
            reportColumn('Costo por unidad', 'money', 'R', 2),
        ], $productRows),
        reportTable('Elaboración por usuario', [
            reportColumn('Usuario', 'text', 'L', 3), reportColumn('Elaboraciones', 'integer', 'R', 1),
            reportColumn('Unidades', 'integer', 'R', 1),
        ], $userRows),
        reportTable('Detalle de elaboraciones', [
            reportColumn('Fecha', 'text', 'L', 2), reportColumn('Producto', 'text', 'L', 3),
            reportColumn('Unidades', 'integer', 'R', 1), reportColumn('Usuario', 'text', 'L', 2),
            reportColumn('Costo de insumos', 'money', 'R', 2),
        ], $detail),
    ], [
        'Las elaboraciones deshechas no se incluyen.',
        'El costo se estima con las cantidades de insumo descontadas y el costo unitario actual de cada insumo.',
    ]);
}

function reportBuildMaterialUsage(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $stmt = $pdo->prepare(
        "SELECT item_id, reason, CASE WHEN qty_delta < 0 THEN 1 ELSE 0 END AS is_out,
                SUM(qty_delta) AS qty, SUM(CASE WHEN unit_cost IS NULL THEN 0 ELSE qty_delta * unit_cost END) AS purchase_cost
         FROM stock_movements
         WHERE item_type = 'material' AND created_at >= ? AND created_at < ?
         GROUP BY item_id, reason, CASE WHEN qty_delta < 0 THEN 1 ELSE 0 END"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $usage = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['item_id'];
        $usage[$id] = $usage[$id] ?? ['production' => 0.0, 'adjust_out' => 0.0, 'purchase' => 0.0, 'purchase_cost' => 0.0];
        $qty = (float)$row['qty'];
        switch ($row['reason']) {
            // A production undo returns material with 'void' movements, so both net out.
            case 'production': case 'void': $usage[$id]['production'] -= $qty; break;
            case 'adjustment': if ((int)$row['is_out'] === 1) $usage[$id]['adjust_out'] -= $qty; break;
            case 'purchase':
                $usage[$id]['purchase'] += $qty;
                $usage[$id]['purchase_cost'] += (float)$row['purchase_cost'];
                break;
        }
    }
    $materials = [];
    foreach ($pdo->query("SELECT id, name, unit, stock, cost_per_unit FROM materials")->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $materials[(int)$m['id']] = $m;
    }
    $rows = [];
    $totals = ['production' => 0.0, 'adjust' => 0.0, 'purchase' => 0.0];
    foreach ($usage as $id => $u) {
        $material = $materials[$id] ?? ['name' => "Insumo #$id", 'unit' => '', 'stock' => null, 'cost_per_unit' => 0];
        $production = max(0.0, round($u['production'], 6));
        $adjust = round($u['adjust_out'], 6);
        if ($production == 0 && $adjust == 0 && $u['purchase'] == 0) continue;
        $unitCost = (float)$material['cost_per_unit'];
        $productionCost = $production * $unitCost;
        $adjustCost = $adjust * $unitCost;
        $totals['production'] += $productionCost;
        $totals['adjust'] += $adjustCost;
        $totals['purchase'] += $u['purchase_cost'];
        $rows[] = [
            $material['name'], $material['unit'], round($production, 4), round($adjust, 4), round($production + $adjust, 4),
            $unitCost > 0 ? round($unitCost, 4) : null, $unitCost > 0 ? round($productionCost + $adjustCost, 2) : null,
            round($u['purchase'], 4), $material['stock'] === null ? null : round((float)$material['stock'], 4),
        ];
    }
    usort($rows, fn($a, $b) => ($b[6] <=> $a[6]) ?: ($b[4] <=> $a[4]) ?: strcasecmp($a[0], $b[0]));
    $consumed = count(array_filter($rows, fn($r) => $r[4] > 0));
    return reportResult('consumo_insumos', 'Consumo de insumos', $range, [
        reportMetric('Insumos consumidos', $consumed, 'integer'),
        reportMetric('Costo total consumido', round($totals['production'] + $totals['adjust'], 2), 'money'),
        reportMetric('Costo en elaboración', round($totals['production'], 2), 'money'),
        reportMetric('Costo de mermas y ajustes', round($totals['adjust'], 2), 'money'),
        reportMetric('Compras registradas', round($totals['purchase'], 2), 'money'),
    ], [
        reportTable('Consumo por insumo', [
            reportColumn('Insumo', 'text', 'L', 3), reportColumn('Unidad', 'text', 'L', 1),
            reportColumn('Usado en elaboración', 'quantity', 'R', 1), reportColumn('Mermas y ajustes', 'quantity', 'R', 1),
            reportColumn('Total consumido', 'quantity', 'R', 1), reportColumn('Costo unitario', 'money', 'R', 1),
            reportColumn('Costo consumido', 'money', 'R', 2), reportColumn('Compras', 'quantity', 'R', 1),
            reportColumn('Stock actual', 'quantity', 'R', 1),
        ], $rows),
    ], [
        'El consumo en elaboración descuenta las elaboraciones deshechas. "Mermas y ajustes" son las disminuciones manuales de stock.',
        'El costo consumido usa el costo unitario actual del insumo; "Compras registradas" suma el costo indicado en cada compra.',
    ]);
}

// ---------------------------------------------------------------------------
// Inventory reports
// ---------------------------------------------------------------------------

function reportBuildShrinkage(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $stmt = $pdo->prepare(
        "SELECT sm.id, sm.item_type, sm.item_id, sm.qty_delta, sm.note, sm.username, sm.created_at,
                p.name AS product_name, m.name AS material_name, m.unit AS material_unit, m.cost_per_unit
         FROM stock_movements sm
         LEFT JOIN products p ON sm.item_type = 'product' AND p.id = sm.item_id
         LEFT JOIN materials m ON sm.item_type = 'material' AND m.id = sm.item_id
         WHERE sm.reason = 'adjustment' AND sm.qty_delta < 0 AND sm.created_at >= ? AND sm.created_at < ?
         ORDER BY sm.created_at, sm.id"
    );
    $stmt->execute([$range['start'], $range['end']]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productCosts = reportProductUnitCosts($pdo);

    $byReason = [];
    $byItem = [];
    $detail = [];
    $totalCost = 0.0;
    $withoutCost = 0;
    foreach ($movements as $move) {
        $isProduct = $move['item_type'] === 'product';
        $qty = -(float)$move['qty_delta'];
        if ($isProduct) {
            $name = $move['product_name'] ?? ('Producto #' . $move['item_id']);
            $unit = 'unid.';
            $unitCost = reportProductCost($productCosts, (int)$move['item_id'])['cost'];
        } else {
            $name = $move['material_name'] ?? ('Insumo #' . $move['item_id']);
            $unit = (string)($move['material_unit'] ?? '');
            $unitCost = (float)($move['cost_per_unit'] ?? 0) > 0 ? (float)$move['cost_per_unit'] : null;
        }
        $cost = $unitCost === null ? null : round($qty * $unitCost, 2);
        if ($cost === null) $withoutCost++;
        else $totalCost += $cost;
        [$reason, $detailText] = reportParseDecreaseNote($move['note']);
        $byReason[$reason] = $byReason[$reason] ?? [0, 0.0];
        $byReason[$reason][0]++;
        $byReason[$reason][1] += $cost ?? 0;
        $key = $move['item_type'] . ':' . $move['item_id'];
        $byItem[$key] = $byItem[$key] ?? [$name, $isProduct ? 'Producto' : 'Insumo', $unit, 0, 0.0, 0.0, false];
        $byItem[$key][3]++;
        $byItem[$key][4] += $qty;
        $byItem[$key][5] += $cost ?? 0;
        if ($cost === null) $byItem[$key][6] = true;
        $detail[] = [reportLocalTimestamp($move['created_at']), $name, $isProduct ? 'Producto' : 'Insumo',
                     round($qty, 4), $unit, $reason, $detailText, reportSellerName($move['username']), $cost];
    }
    uksort($byReason, fn($a, $b) => ($byReason[$b][1] <=> $byReason[$a][1]) ?: ($byReason[$b][0] <=> $byReason[$a][0]) ?: strcasecmp($a, $b));
    $reasonRows = [];
    foreach ($byReason as $reason => [$count, $cost]) $reasonRows[] = [$reason, $count, round($cost, 2), reportShare($cost, $totalCost)];
    $itemRows = array_map(fn($i) => [$i[0], $i[1], $i[3], round($i[4], 4), $i[2], $i[6] && $i[5] == 0 ? null : round($i[5], 2)], array_values($byItem));
    usort($itemRows, fn($a, $b) => (($b[5] ?? 0) <=> ($a[5] ?? 0)) ?: ($b[2] <=> $a[2]) ?: strcasecmp($a[0], $b[0]));

    return reportResult('mermas', 'Mermas y disminuciones', $range, [
        reportMetric('Disminuciones registradas', count($movements), 'integer'),
        reportMetric('Costo estimado', round($totalCost, 2), 'money'),
        reportMetric('Motivo principal', array_key_first($byReason) ?? 'Sin registros'),
        reportMetric('Artículos afectados', count($byItem), 'integer'),
        reportMetric('Registros sin costo', $withoutCost, 'integer'),
    ], [
        reportTable('Disminuciones por motivo', [
            reportColumn('Motivo', 'text', 'L', 3), reportColumn('Registros', 'integer', 'R', 1),
            reportColumn('Costo estimado', 'money', 'R', 2), reportColumn('Participación', 'percent', 'R', 2),
        ], $reasonRows),
        reportTable('Disminuciones por artículo', [
            reportColumn('Artículo', 'text', 'L', 3), reportColumn('Tipo', 'text', 'L', 1),
            reportColumn('Registros', 'integer', 'R', 1), reportColumn('Cantidad', 'quantity', 'R', 1),
            reportColumn('Unidad', 'text', 'L', 1), reportColumn('Costo estimado', 'money', 'R', 2),
        ], $itemRows),
        reportTable('Detalle de disminuciones', [
            reportColumn('Fecha', 'text', 'L', 2), reportColumn('Artículo', 'text', 'L', 3),
            reportColumn('Tipo', 'text', 'L', 1), reportColumn('Cantidad', 'quantity', 'R', 1),
            reportColumn('Unidad', 'text', 'L', 1), reportColumn('Motivo', 'text', 'L', 2),
            reportColumn('Detalle', 'text', 'L', 3), reportColumn('Usuario', 'text', 'L', 2),
            reportColumn('Costo estimado', 'money', 'R', 2),
        ], $detail),
    ], [
        'Incluye las disminuciones y ajustes negativos de productos e insumos. Las ventas y elaboraciones no se cuentan aquí.',
        'El costo se estima con el costo actual: receta o última compra para productos y costo unitario para insumos. "Ajuste manual" agrupa los ajustes registrados sin un motivo de la lista.',
    ]);
}

function reportBuildInventoryValue(PDO $pdo, array $filters): array {
    $costs = reportProductUnitCosts($pdo);
    $productRows = [];
    $productCost = 0.0;
    $productRetail = 0.0;
    $withoutCost = 0;
    $products = $pdo->query(
        "SELECT id, name, category, price, stock FROM products WHERE is_active = 1 OR stock <> 0 ORDER BY category, name"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $product) {
        $stock = (int)$product['stock'];
        $cost = reportProductCost($costs, (int)$product['id']);
        $value = $cost['cost'] === null ? null : round(max(0, $stock) * $cost['cost'], 2);
        $retail = round(max(0, $stock) * (float)$product['price'], 2);
        if ($value === null && $stock > 0) $withoutCost++;
        $productCost += $value ?? 0;
        $productRetail += $retail;
        $productRows[] = [$product['name'], $product['category'] ?: 'Sin categoría', $stock, $cost['cost'], $cost['source'],
                          $value, (float)$product['price'], $retail];
    }
    usort($productRows, fn($a, $b) => (($b[5] ?? -1) <=> ($a[5] ?? -1)) ?: ($b[7] <=> $a[7]) ?: strcasecmp($a[0], $b[0]));

    $materialRows = [];
    $materialValue = 0.0;
    $materialsWithoutCost = 0;
    foreach ($pdo->query("SELECT name, unit, stock, cost_per_unit FROM materials ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $material) {
        $stock = (float)$material['stock'];
        $unitCost = (float)$material['cost_per_unit'];
        $value = $unitCost > 0 ? round(max(0, $stock) * $unitCost, 2) : null;
        if ($value === null && $stock > 0) $materialsWithoutCost++;
        $materialValue += $value ?? 0;
        $materialRows[] = [$material['name'], $material['unit'], round($stock, 4), $unitCost > 0 ? round($unitCost, 4) : null, $value];
    }
    usort($materialRows, fn($a, $b) => (($b[4] ?? -1) <=> ($a[4] ?? -1)) ?: strcasecmp($a[0], $b[0]));

    $today = reportToday();
    return reportResult('valorizacion', 'Valorización de inventario', [
        'period' => $today, 'label' => 'Existencias al ' . reportDayLabel($today),
    ], [
        reportMetric('Valor total al costo', round($productCost + $materialValue, 2), 'money'),
        reportMetric('Productos al costo', round($productCost, 2), 'money'),
        reportMetric('Insumos al costo', round($materialValue, 2), 'money'),
        reportMetric('Productos a precio de venta', round($productRetail, 2), 'money'),
        reportMetric('Productos con stock sin costo', $withoutCost, 'integer'),
        reportMetric('Insumos con stock sin costo', $materialsWithoutCost, 'integer'),
    ], [
        reportTable('Productos en existencia', [
            reportColumn('Producto', 'text', 'L', 3), reportColumn('Categoría', 'text', 'L', 2),
            reportColumn('Stock', 'integer', 'R', 1), reportColumn('Costo unitario', 'money', 'R', 1),
            reportColumn('Origen del costo', 'text', 'L', 2), reportColumn('Valor al costo', 'money', 'R', 2),
            reportColumn('Precio', 'money', 'R', 1), reportColumn('Valor a precio de venta', 'money', 'R', 2),
        ], $productRows),
        reportTable('Insumos en existencia', [
            reportColumn('Insumo', 'text', 'L', 3), reportColumn('Unidad', 'text', 'L', 1),
            reportColumn('Stock', 'quantity', 'R', 1), reportColumn('Costo unitario', 'money', 'R', 1),
            reportColumn('Valor al costo', 'money', 'R', 2),
        ], $materialRows),
    ], [
        'Los productos con receta se valoran con el costo actual de su receta; los de reventa, con el costo de su última compra registrada.',
        'Los insumos ya usados en productos elaborados no se cuentan de nuevo: solo se valora el stock disponible.',
    ]);
}

function reportBuildProfitability(PDO $pdo, array $filters): array {
    $range = reportRange($filters);
    $costs = reportProductUnitCosts($pdo);
    $sold = reportPaidSalesByProduct($pdo, $range);
    $products = [];
    foreach ($pdo->query("SELECT id, name, category, price, is_active FROM products")->fetchAll(PDO::FETCH_ASSOC) as $product) {
        $products[(int)$product['id']] = $product;
    }
    $rows = [];
    $revenue = 0.0;
    $costOfSales = 0.0;
    $revenueWithCost = 0.0;
    $withoutCost = 0;
    foreach ($products as $id => $product) {
        $sale = $sold[$id] ?? ['units' => 0, 'amount' => 0.0];
        if (!(int)$product['is_active'] && $sale['units'] === 0) continue;
        $price = (float)$product['price'];
        $cost = reportProductCost($costs, $id);
        $unitCost = $cost['cost'];
        $margin = $unitCost === null ? null : round($price - $unitCost, 2);
        $marginPercent = $unitCost === null || $price <= 0 ? null : round(($price - $unitCost) * 100 / $price, 2);
        $amount = round($sale['amount'], 2);
        $soldCost = $unitCost === null ? null : round($unitCost * $sale['units'], 2);
        $profit = $soldCost === null ? null : round($amount - $soldCost, 2);
        $revenue += $amount;
        if ($unitCost === null) $withoutCost++;
        else { $costOfSales += $soldCost; $revenueWithCost += $amount; }
        $rows[] = [$product['name'], $product['category'] ?: 'Sin categoría', $price, $unitCost, $margin, $marginPercent,
                   $sale['units'], $amount, $soldCost, $profit, $cost['source']];
    }
    // Products sold but already deleted from the catalog.
    foreach ($sold as $id => $sale) {
        if (isset($products[$id])) continue;
        $revenue += $sale['amount'];
        $withoutCost++;
        $rows[] = ["Producto #$id", 'Sin categoría', null, null, null, null, $sale['units'], round($sale['amount'], 2), null, null, 'Sin costo'];
    }
    usort($rows, fn($a, $b) => (($b[9] ?? -INF) <=> ($a[9] ?? -INF)) ?: ($b[7] <=> $a[7]) ?: strcasecmp($a[0], $b[0]));
    $profit = $revenueWithCost - $costOfSales;
    return reportResult('rentabilidad', 'Rentabilidad por producto', $range, [
        reportMetric('Ventas del período', round($revenue, 2), 'money'),
        reportMetric('Costo de lo vendido', round($costOfSales, 2), 'money'),
        reportMetric('Ganancia bruta', round($profit, 2), 'money'),
        reportMetric('Margen bruto', reportShare($profit, $revenueWithCost), 'percent'),
        reportMetric('Productos sin costo', $withoutCost, 'integer'),
    ], [
        reportTable('Rentabilidad por producto', [
            reportColumn('Producto', 'text', 'L', 3), reportColumn('Categoría', 'text', 'L', 2),
            reportColumn('Precio', 'money', 'R', 1), reportColumn('Costo unitario', 'money', 'R', 1),
            reportColumn('Margen unitario', 'money', 'R', 1), reportColumn('Margen', 'percent', 'R', 1),
            reportColumn('Unidades vendidas', 'integer', 'R', 1), reportColumn('Ventas', 'money', 'R', 2),
            reportColumn('Costo de lo vendido', 'money', 'R', 2), reportColumn('Ganancia bruta', 'money', 'R', 2),
            reportColumn('Origen del costo', 'text', 'L', 2),
        ], $rows),
    ], [
        'El costo unitario es el costo actual: receta para productos elaborados y última compra con costo para productos de reventa.',
        'La ganancia y el margen bruto solo consideran productos con costo; los marcados "Sin costo" no se incluyen en esos totales.',
        'Las ventas son los montos cobrados en el período, por fecha de pago.',
    ]);
}

// ---------------------------------------------------------------------------
// Catalog and dispatcher
// ---------------------------------------------------------------------------

/** Report types with their export file slug and PDF header title. */
function reportCatalog(): array {
    return [
        'cierre_diario' => ['slug' => 'cierre-diario', 'pdf' => 'CIERRE DIARIO', 'build' => 'reportBuildDailyClose'],
        'cierre_mensual' => ['slug' => 'cierre-mensual', 'pdf' => 'CIERRE MENSUAL', 'build' => 'reportBuildMonthlyClose'],
        'metodos_pago' => ['slug' => 'metodos-pago', 'pdf' => 'METODOS DE PAGO', 'build' => 'reportBuildPaymentMethods'],
        'anulaciones' => ['slug' => 'anulaciones', 'pdf' => 'ANULACIONES', 'build' => 'reportBuildVoids'],
        'ventas_periodo' => ['slug' => 'ventas-periodo', 'pdf' => 'VENTAS POR PERIODO', 'build' => 'reportBuildSalesPeriod'],
        'ventas_categoria' => ['slug' => 'ventas-categoria', 'pdf' => 'VENTAS POR CATEGORIA', 'build' => 'reportBuildSalesByCategory'],
        'ventas_vendedor' => ['slug' => 'ventas-vendedor', 'pdf' => 'VENTAS POR VENDEDOR', 'build' => 'reportBuildSalesBySeller'],
        'horas_pico' => ['slug' => 'horas-pico', 'pdf' => 'HORAS PICO', 'build' => 'reportBuildPeakHours'],
        'mas_vendidos' => ['slug' => 'mas-vendidos', 'pdf' => 'MAS VENDIDOS', 'build' => 'reportBuildTopSellers'],
        'rentabilidad' => ['slug' => 'rentabilidad', 'pdf' => 'RENTABILIDAD', 'build' => 'reportBuildProfitability'],
        'elaboracion' => ['slug' => 'elaboracion', 'pdf' => 'ELABORACION', 'build' => 'reportBuildProduction'],
        'consumo_insumos' => ['slug' => 'consumo-insumos', 'pdf' => 'CONSUMO DE INSUMOS', 'build' => 'reportBuildMaterialUsage'],
        'reponer' => ['slug' => 'reponer', 'pdf' => 'REPOSICION', 'build' => 'reportBuildRestock'],
        'mermas' => ['slug' => 'mermas', 'pdf' => 'MERMAS', 'build' => 'reportBuildShrinkage'],
        'valorizacion' => ['slug' => 'valorizacion', 'pdf' => 'VALORIZACION', 'build' => 'reportBuildInventoryValue'],
    ];
}

function reportBuildData(PDO $pdo, string $type, array $filters): array {
    $catalog = reportCatalog();
    if (!isset($catalog[$type])) throw new ReportRequestException('Tipo de reporte no válido.');
    $data = ($catalog[$type]['build'])($pdo, $filters);
    $config = reportBusinessConfig();
    $data['slug'] = $catalog[$type]['slug'];
    $data['pdf_title'] = $catalog[$type]['pdf'];
    $data['currency'] = (string)($config['currency'] ?? '$');
    $data['business_name'] = (string)($config['name'] ?? 'Sweet POS');
    return $data;
}
