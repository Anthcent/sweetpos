<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireRole(['admin', 'gerente']);
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    try {
        // Fetch all sales, joined with clients
        $stmt = $pdo->query("
            SELECT s.*, c.name as client_name, c.cedula as client_cedula 
            FROM sales s 
            LEFT JOIN clients c ON s.client_id = c.id 
            ORDER BY s.created_at DESC
        ");
        $sales = $stmt->fetchAll();
        
        // Optimize fetching items by getting all items at once and grouping them in PHP
        // This is much faster than running a query inside a loop for history views
        $stmt_items = $pdo->query("
            SELECT si.sale_id, si.quantity, si.subtotal, p.name 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id
        ");
        $all_items = $stmt_items->fetchAll();
        
        // Group items by sale_id
        $items_by_sale = [];
        foreach($all_items as $item) {
            $items_by_sale[$item['sale_id']][] = [
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
                'name' => $item['name']
            ];
        }
        
        // Attach items to each sale
        foreach($sales as &$sale) {
            $sale['items'] = $items_by_sale[$sale['id']] ?? [];
        }
        
        echo json_encode($sales);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener el historial: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>
