<?php
// api/clients.php — Client search (?q=), lookup (?cedula=), list and registration.
// The production image ships without php.ini, so display_errors defaults to on:
// any PHP warning would be printed into the JSON body and the POS would only
// see "Error al buscar cliente". Warnings are logged instead, and uncaught
// errors always answer with a JSON message.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    error_log("SweetPOS clients API warning: $message in $file:$line");
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    error_log('SweetPOS clients API failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No se pudo consultar los clientes. Intenta de nuevo.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

require_once __DIR__ . '/auth_api.php';
apiRequireLogin(); // also opens $pdo (api/db.php)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../lib/client_search.php';

function clientsRespond($payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, CLIENT_JSON_FLAGS);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    if (isset($_GET['q'])) {
        clientsRespond(searchClients($pdo, (string)$_GET['q']));
    } else if (isset($_GET['cedula'])) {
        $client = findClientByCedula($pdo, (string)$_GET['cedula']);
        if ($client) {
            clientsRespond(publicClient($client) + $client);
        } else {
            clientsRespond(["error" => "Cliente no encontrado"], 404);
        }
    } else {
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC");
        clientsRespond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} else if ($method == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) $data = [];

    if (trim((string)($data['cedula'] ?? '')) === '' || trim((string)($data['name'] ?? '')) === '') {
        clientsRespond(["error" => "Faltan datos requeridos (Cédula y Nombre)"], 400);
        exit();
    }

    [$client, $error] = sweetposValidateClient($data);
    if ($error !== null) {
        clientsRespond(["error" => $error], 400);
        exit();
    }

    $existing = findClientByCedula($pdo, $client['cedula']);
    if ($existing) {
        clientsRespond([
            "error"  => "Ya existe un cliente con la cédula " . sweetposFormatCedula($existing['cedula']) . ".",
            "client" => publicClient($existing),
        ], 409);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (cedula, name, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client['cedula'], $client['name'], $client['phone'], $client['address']]);
        $id = (int)sweetposLastInsertId($pdo, 'clients');
        clientsRespond([
            "message" => "Cliente registrado",
            "id"      => $id,
            "client"  => publicClient(['id' => $id] + $client),
        ]);
    } catch (PDOException $e) {
        error_log('SweetPOS client insert failed: ' . $e->getMessage());
        clientsRespond(["error" => "Error al registrar: Puede que la cédula ya exista."], 400);
    }
} else {
    clientsRespond(["error" => "Método no permitido"], 405);
}
