<?php
header('Content-Type: application/json');
require 'auth_api.php';
apiRequireLogin();
require 'db.php';
require_once __DIR__ . '/../lib/validation.php';

const CLIENT_SEARCH_LIMIT = 8;

$method = $_SERVER['REQUEST_METHOD'];

// SQL expression that compacts a stored cedula the same way sweetposCompactCedula() does,
// so legacy rows saved as "V-12.345.678" still match the canonical "12345678".
const CLIENT_CEDULA_SQL_KEY = "REPLACE(REPLACE(REPLACE(UPPER(cedula), '.', ''), '-', ''), ' ', '')";

// Comparable key: compact form without the default "V" prefix.
function clientCedulaKey(string $value): string {
    $compact = sweetposCompactCedula($value);
    return str_starts_with($compact, 'V') ? substr($compact, 1) : $compact;
}

function findClientByCedula(PDO $pdo, string $cedula): ?array {
    $key = clientCedulaKey($cedula);
    if ($key === '') return null;
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE " . CLIENT_CEDULA_SQL_KEY . " IN (?, ?) LIMIT 1");
    $stmt->execute([$key, 'V' . $key]);
    $client = $stmt->fetch();
    return $client ?: null;
}

function publicClient(array $client): array {
    return [
        'id'             => (int)$client['id'],
        'cedula'         => (string)$client['cedula'],
        'cedula_display' => sweetposFormatCedula($client['cedula']),
        'name'           => (string)$client['name'],
        'phone'          => $client['phone'] ?? null,
    ];
}

// Partial, accent-insensitive search by cedula OR name, best matches first.
// Filtering runs in PHP so it behaves the same on SQLite and PostgreSQL.
function searchClients(PDO $pdo, string $term): array {
    $term = trim($term);
    if ($term === '') return [];

    $isCedula = sweetposLooksLikeCedula($term);
    $cedulaKey = $isCedula ? clientCedulaKey($term) : '';
    $words = array_values(array_filter(explode(' ', sweetposFoldText($term)), fn($w) => $w !== ''));

    $rows = $pdo->query("SELECT id, cedula, name, phone FROM clients")->fetchAll();
    $scored = [];
    foreach ($rows as $row) {
        $score = 0;
        if ($isCedula) {
            $key = clientCedulaKey((string)$row['cedula']);
            if ($key === $cedulaKey) $score = 100;
            else if (str_starts_with($key, $cedulaKey)) $score = 80;
            else if (str_contains($key, $cedulaKey)) $score = 60;
        } else if ($words) {
            $name = sweetposFoldText((string)$row['name']);
            $all = true;
            foreach ($words as $word) {
                if (!str_contains($name, $word)) { $all = false; break; }
            }
            if ($all) $score = str_starts_with($name, $words[0]) ? 70 : 50;
        }
        if ($score > 0) $scored[] = [$score, $row];
    }

    usort($scored, fn($a, $b) => $b[0] <=> $a[0] ?: strcmp((string)$a[1]['name'], (string)$b[1]['name']));
    return array_map(fn($entry) => publicClient($entry[1]), array_slice($scored, 0, CLIENT_SEARCH_LIMIT));
}

if ($method == 'GET') {
    if (isset($_GET['q'])) {
        echo json_encode(searchClients($pdo, (string)$_GET['q']));
    } else if (isset($_GET['cedula'])) {
        $client = findClientByCedula($pdo, (string)$_GET['cedula']);
        if ($client) {
            echo json_encode(publicClient($client) + $client);
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
    if (!is_array($data)) $data = [];

    if (trim((string)($data['cedula'] ?? '')) === '' || trim((string)($data['name'] ?? '')) === '') {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos requeridos (Cédula y Nombre)"]);
        exit();
    }

    [$client, $error] = sweetposValidateClient($data);
    if ($error !== null) {
        http_response_code(400);
        echo json_encode(["error" => $error]);
        exit();
    }

    $existing = findClientByCedula($pdo, $client['cedula']);
    if ($existing) {
        http_response_code(409);
        echo json_encode([
            "error"  => "Ya existe un cliente con la cédula " . sweetposFormatCedula($existing['cedula']) . ".",
            "client" => publicClient($existing),
        ]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (cedula, name, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client['cedula'], $client['name'], $client['phone'], $client['address']]);
        $id = (int)sweetposLastInsertId($pdo, 'clients');
        echo json_encode([
            "message" => "Cliente registrado",
            "id"      => $id,
            "client"  => publicClient(['id' => $id] + $client),
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(["error" => "Error al registrar: Puede que la cédula ya exista."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>
