<?php
// api/insights_lib.php — Decision-support calculations for the "Panel de decisiones".
// Pure function over the database: no session, no output. Used by api/insights.php
// and by the CLI test. Everything comes from 5 aggregate queries (no N+1).
//
// Definitions:
//   units sold     = sale_items of sales with status 'paid' or 'pending' (voided sales
//                    keep status 'voided' and are excluded).
//   avg_daily      = sold in the window / window_days, where window_days is 30, or the
//                    days since the first sale when the shop has less history than that.
//   coverage_days  = stock / avg_daily (NULL when there are no sales: "no se agota").
//   can_make       = MIN over recipe of floor(material.stock / quantity_required); NULL
//                    when the product has no recipe.
//   need           = max(0, ceil(avg_daily * target_days) - stock)
//   suggested      = min(need, can_make)   (need when there is no recipe)

const INSIGHTS_WINDOW_DAYS = 30;
const INSIGHTS_TOP_SELLERS = 3;

// Status thresholds shared by products and materials.
//   agotado: stock <= 0
//   critico: coverage < 1 day, or stock <= half of min_stock
//   bajo:    stock <= min_stock, or coverage < target_days
function insightsStatus(float $stock, float $minStock, ?float $coverage, int $targetDays): string {
    if ($stock <= 0) return 'agotado';
    if (($coverage !== null && $coverage < 1) || ($minStock > 0 && $stock <= $minStock / 2)) return 'critico';
    if (($minStock > 0 && $stock <= $minStock) || ($coverage !== null && $coverage < $targetDays)) return 'bajo';
    return 'ok';
}

function insightsDays(float $days): string {
    return ($days == 1 ? '1 día' : rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.') . ' días');
}

// Rounds a material quantity up to 2 decimals (what the purchase form accepts).
function insightsCeil2(float $value): float {
    return ceil(round($value * 100, 6)) / 100;
}

function computeInsights(PDO $pdo, int $targetDays = 3): array {
    $targetDays = max(1, min(60, $targetDays));
    $window = INSIGHTS_WINDOW_DAYS;

    // 1. Sales KPIs + history length, in one pass over the window.
    $kpi = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN date(created_at, 'localtime') = date('now', 'localtime') THEN total_amount END), 0) AS sales_today,
            COALESCE(SUM(CASE WHEN date(created_at, 'localtime') = date('now', 'localtime') THEN 1 END), 0) AS orders_today,
            COALESCE(SUM(CASE WHEN created_at >= datetime('now', '-7 days') THEN total_amount END), 0) AS sales_7d,
            (SELECT MIN(created_at) FROM sales WHERE status IN ('paid', 'pending')) AS first_sale_at,
            (SELECT julianday('now') - julianday(MIN(created_at)) FROM sales WHERE status IN ('paid', 'pending')) AS history_days
         FROM sales
         WHERE status IN ('paid', 'pending') AND created_at >= datetime('now', '-$window days')"
    )->fetch();
    $historyDays = $kpi['history_days'] === null ? $window : (float)$kpi['history_days'];
    $windowDays = (int)max(1, min($window, ceil($historyDays)));

    // Daily totals for the sparkline (last 14 local days). Days are generated in SQL
    // so they use the same clock as date(created_at, 'localtime').
    $sparkline = array_map(fn($r) => ['day' => $r['day'], 'total' => (float)$r['total']], $pdo->query(
        "WITH RECURSIVE days(day) AS (
            SELECT date('now', 'localtime', '-13 days')
            UNION ALL SELECT date(day, '+1 day') FROM days WHERE day < date('now', 'localtime')
         ), totals AS (
            SELECT date(created_at, 'localtime') AS day, SUM(total_amount) AS total
            FROM sales
            WHERE status IN ('paid', 'pending') AND created_at >= datetime('now', '-15 days')
            GROUP BY 1
         )
         SELECT days.day, ROUND(COALESCE(totals.total, 0), 2) AS total
         FROM days LEFT JOIN totals ON totals.day = days.day
         ORDER BY days.day"
    )->fetchAll());

    // 2. Products: sales aggregates + producible units, one query.
    //    ROUND(..., 6) before the integer cast avoids 0.3/0.1 = 2.9999 -> 2.
    $products = $pdo->query(
        "WITH sold AS (
            SELECT si.product_id,
                   SUM(CASE WHEN s.created_at >= datetime('now', '-7 days') THEN si.quantity ELSE 0 END) AS sold_7d,
                   SUM(si.quantity) AS sold_30d
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE s.status IN ('paid', 'pending') AND s.created_at >= datetime('now', '-$window days')
            GROUP BY si.product_id
         ), recipe AS (
            SELECT pm.product_id,
                   COUNT(*) AS recipe_items,
                   MIN(CAST(ROUND(MAX(m.stock, 0) / pm.quantity_required, 6) AS INTEGER)) AS can_make
            FROM product_materials pm
            JOIN materials m ON m.id = pm.material_id
            WHERE pm.quantity_required > 0
            GROUP BY pm.product_id
         )
         SELECT p.id, p.name, p.category, p.stock, p.min_stock, p.image_color,
                COALESCE(sold.sold_7d, 0) AS sold_7d,
                COALESCE(sold.sold_30d, 0) AS sold_30d,
                recipe.can_make, COALESCE(recipe.recipe_items, 0) AS recipe_items
         FROM products p
         LEFT JOIN sold ON sold.product_id = p.id
         LEFT JOIN recipe ON recipe.product_id = p.id
         WHERE p.is_active = 1
         ORDER BY sold_30d DESC, sold_7d DESC, p.name ASC"
    )->fetchAll();

    // 3. Materials and 4. recipe lines (active products only).
    $materials = $pdo->query("SELECT id, name, unit, stock, min_stock, cost_per_unit FROM materials ORDER BY name ASC")->fetchAll();
    $recipeLines = $pdo->query(
        "SELECT pm.product_id, pm.material_id, pm.quantity_required
         FROM product_materials pm
         JOIN products p ON p.id = pm.product_id
         WHERE p.is_active = 1 AND pm.quantity_required > 0"
    )->fetchAll();

    // ---- Products ----
    $productsById = [];
    $rank = 0;
    foreach ($products as $p) {
        $rank++;
        $stock   = (int)$p['stock'];
        $sold30  = (int)$p['sold_30d'];
        $avg     = round($sold30 / $windowDays, 4);
        $canMake = $p['can_make'] === null ? null : max(0, (int)$p['can_make']);
        $coverage = $avg > 0 ? round($stock / $avg, 1) : null;
        $need = max(0, (int)ceil(round($avg * $targetDays, 6)) - $stock);
        $suggested = $canMake === null ? $need : min($need, $canMake);

        $productsById[(int)$p['id']] = [
            'id'            => (int)$p['id'],
            'name'          => $p['name'],
            'category'      => $p['category'],
            'image_color'   => $p['image_color'],
            'rank'          => $rank,
            'is_top_seller' => $rank <= INSIGHTS_TOP_SELLERS && $sold30 > 0,
            'sold_7d'       => (int)$p['sold_7d'],
            'sold_30d'      => $sold30,
            'avg_daily'     => $avg,
            'stock'         => $stock,
            'min_stock'     => (int)$p['min_stock'],
            'coverage_days' => $coverage,
            'has_recipe'    => (int)$p['recipe_items'] > 0,
            'can_make'      => $canMake,
            'need'          => $need,
            'suggested_production' => $suggested,
            'status'        => insightsStatus($stock, (float)$p['min_stock'], $coverage, $targetDays),
        ];
    }

    // ---- Materials ----
    $linesByMaterial = [];
    foreach ($recipeLines as $line) {
        $linesByMaterial[(int)$line['material_id']][] = [(int)$line['product_id'], (float)$line['quantity_required']];
    }

    $materialsOut = [];
    foreach ($materials as $m) {
        $id = (int)$m['id'];
        $stock = (float)$m['stock'];
        $minStock = (float)$m['min_stock'];
        $consumption = 0.0;
        $requiredForNeed = 0.0;
        $blocks = [];
        $usedBy = 0;
        foreach ($linesByMaterial[$id] ?? [] as [$productId, $qtyRequired]) {
            $product = $productsById[$productId] ?? null;
            if (!$product) continue;
            $usedBy++;
            $consumption += $product['avg_daily'] * $qtyRequired;
            $requiredForNeed += $product['need'] * $qtyRequired;
            // Blocks a selling product when it cannot cover its production need
            // (or, if no production is needed yet, not even one unit).
            $affordable = (int)floor(round(max(0, $stock) / $qtyRequired, 6));
            if ($product['sold_30d'] > 0 && $affordable < max(1, $product['need'])) {
                $blocks[] = [
                    'product_id' => $productId,
                    'name'       => $product['name'],
                    'rank'       => $product['rank'],
                    'need'       => $product['need'],
                    'affordable' => $affordable,
                    'qty_required' => $qtyRequired,
                ];
            }
        }
        usort($blocks, fn($a, $b) => $a['rank'] <=> $b['rank']);
        $consumption = round($consumption, 4);
        $coverage = $consumption > 0 ? round($stock / $consumption, 1) : null;
        // Enough to cover the production need of every product plus min_stock, or
        // target_days of consumption plus min_stock, whichever is larger.
        $suggestedPurchase = insightsCeil2(max(0,
            max($requiredForNeed, $consumption * $targetDays) + $minStock - $stock));

        $materialsOut[$id] = [
            'id'                => $id,
            'name'              => $m['name'],
            'unit'              => $m['unit'],
            'stock'             => round($stock, 6),
            'min_stock'         => round($minStock, 6),
            'used_by'           => $usedBy,
            'daily_consumption' => $consumption,
            'coverage_days'     => $coverage,
            'status'            => insightsStatus($stock, $minStock, $coverage, $targetDays),
            'blocks'            => $blocks,
            'suggested_purchase' => $suggestedPurchase,
        ];
    }

    $alerts = buildInsightAlerts($productsById, $materialsOut, $targetDays);

    $critical = 0;
    foreach ($alerts as $a) if ($a['severity'] === 'critico') $critical++;

    return [
        'generated_at' => date('c'),
        'target_days'  => $targetDays,
        'window_days'  => $windowDays,
        'kpis' => [
            'sales_today'     => round((float)$kpi['sales_today'], 2),
            'orders_today'    => (int)$kpi['orders_today'],
            'sales_7d'        => round((float)$kpi['sales_7d'], 2),
            'critical_alerts' => $critical,
            'total_alerts'    => count($alerts),
            'sparkline'       => $sparkline,
        ],
        'products'  => array_values($productsById),
        'materials' => array_values($materialsOut),
        'alerts'    => $alerts,
    ];
}

// Alert priority = severity weight + best-seller weight + type weight + rank bonus.
// The best-seller weight (500) is smaller than the gap between severities (1000),
// and a missing ingredient for a top seller is always 'critico', so it lands first.
function buildInsightAlerts(array $products, array $materials, int $targetDays): array {
    $severityWeight = ['critico' => 3000, 'alto' => 2000, 'medio' => 1000];
    $typeWeight = ['material_blocks' => 200, 'produce' => 100, 'restock_product' => 60, 'material_low' => 50];
    $alerts = [];

    $push = function (string $type, string $severity, ?int $rank, bool $topSeller, string $message,
                      array $productIds, array $materialIds, ?array $action) use (&$alerts, $severityWeight, $typeWeight) {
        $score = $severityWeight[$severity] + ($topSeller ? 500 : 0) + $typeWeight[$type]
               + ($rank !== null ? max(0, 100 - $rank) : 0);
        $alerts[] = [
            'type'         => $type,
            'severity'     => $severity,
            'priority'     => $score,
            'message'      => $message,
            'product_ids'  => $productIds,
            'material_ids' => $materialIds,
            'action'       => $action,
        ];
    };

    // Materials that block selling products (one alert per material).
    foreach ($materials as $m) {
        if (!empty($m['blocks'])) {
            $first = $m['blocks'][0];
            $firstProduct = $products[$first['product_id']];
            $top = $firstProduct['is_top_seller'];
            $urgent = $top || in_array($firstProduct['status'], ['agotado', 'critico'], true);
            $names = array_map(fn($b) => $b['name'], array_slice($m['blocks'], 0, 3));
            $more = count($m['blocks']) > 3 ? ' y ' . (count($m['blocks']) - 3) . ' más' : '';
            $detail = $first['affordable'] === 0
                ? "no alcanza para ninguna unidad"
                : "alcanza para {$first['affordable']} de {$first['need']} unidades necesarias";
            $msg = "Falta {$m['name']} para elaborar " . implode(', ', $names) . $more
                 . " (#{$first['rank']} en ventas): $detail.";
            $push('material_blocks', $urgent ? 'critico' : 'alto', $first['rank'], $top, $msg,
                  array_map(fn($b) => $b['product_id'], $m['blocks']), [$m['id']],
                  ['type' => 'purchase', 'material_id' => $m['id'], 'quantity' => $m['suggested_purchase']]);
        } elseif ($m['status'] !== 'ok' && $m['used_by'] > 0) {
            $coverage = $m['coverage_days'] !== null ? " (cubre " . insightsDays($m['coverage_days']) . ")" : '';
            $msg = $m['status'] === 'agotado'
                ? "{$m['name']} está agotado."
                : "{$m['name']} está bajo: quedan {$m['stock']} {$m['unit']}$coverage.";
            $push('material_low', $m['status'] === 'bajo' ? 'medio' : 'alto', null, false, $msg,
                  [], [$m['id']],
                  ['type' => 'purchase', 'material_id' => $m['id'], 'quantity' => $m['suggested_purchase']]);
        }
    }

    // Products that need stock.
    foreach ($products as $p) {
        if ($p['status'] === 'ok') continue;
        $urgent = in_array($p['status'], ['agotado', 'critico'], true);
        $severity = $urgent ? ($p['sold_30d'] > 0 ? 'critico' : 'alto') : 'medio';
        $state = $p['status'] === 'agotado'
            ? 'está agotado'
            : ($p['coverage_days'] !== null ? "tiene stock para " . insightsDays($p['coverage_days']) : "está bajo el mínimo ({$p['stock']} u.)");
        $rankText = $p['sold_30d'] > 0 ? " (#{$p['rank']} en ventas)" : '';

        if ($p['has_recipe']) {
            if ($p['suggested_production'] <= 0) continue; // the blocking material alert covers it
            $msg = "{$p['name']}$rankText $state. Elabora {$p['suggested_production']} unidades para cubrir " . insightsDays($targetDays) . ".";
            $push('produce', $severity, $p['rank'], $p['is_top_seller'], $msg, [$p['id']], [],
                  ['type' => 'produce', 'product_id' => $p['id'], 'quantity' => $p['suggested_production']]);
        } else {
            $qty = max($p['need'], $p['min_stock'] - $p['stock']);
            $msg = "{$p['name']}$rankText $state y no tiene receta: repón" . ($qty > 0 ? " $qty unidades" : '') . " desde Inventario.";
            $push('restock_product', $severity, $p['rank'], $p['is_top_seller'], $msg, [$p['id']], [],
                  ['type' => 'inventory', 'product_id' => $p['id'], 'quantity' => $qty]);
        }
    }

    usort($alerts, fn($a, $b) => $b['priority'] <=> $a['priority']);
    return $alerts;
}
?>
