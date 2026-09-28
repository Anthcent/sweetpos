<?php
// api/insights.php — GET: decision-support data for panel.php (admin/gerente).
//   ?target_days=N  days of stock to aim for when suggesting production (default 3)
//   ?count=1        only the critical alert count (sidebar badge)
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireRole(['admin', 'gerente']);
require 'db.php';
require 'inventory.php';
require 'insights_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError("Metodo no permitido", 405);
    exit();
}

$targetDays = isset($_GET['target_days']) ? (int)$_GET['target_days'] : 3;

try {
    $insights = computeInsights($pdo, $targetDays);
    if (!empty($_GET['count'])) {
        echo json_encode([
            "critical_alerts" => $insights['kpis']['critical_alerts'],
            "total_alerts"    => $insights['kpis']['total_alerts'],
        ]);
    } else {
        echo json_encode($insights);
    }
} catch (PDOException $e) {
    jsonError("Error al calcular el panel de decisiones", 500);
}
?>
