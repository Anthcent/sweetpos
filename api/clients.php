<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin();
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    if(isset($_GET['cedula'])) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE cedula = ?");
        $stmt->execute([$_GET['cedula']]);
        $client = $stmt->fetch();
        if($client) {
            echo json_encode($client);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Cliente no encontrado"]);
        }
    } else {
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
    }
} else if ($method == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if(isset($data['cedula']) && isset($data['name'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (cedula, name, phone, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['cedula'],
                $data['name'],
                $data['phone'] ?? null,
                $data['address'] ?? null
            ]);
            echo json_encode(["message" => "Cliente registrado", "id" => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            // Error 19 is Constraint Violation in SQLite (e.g. Unique Cedula)
            http_response_code(400);
            echo json_encode(["error" => "Error al registrar: Puede que la cédula ya exista."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos requeridos (Cédula y Nombre)"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>
