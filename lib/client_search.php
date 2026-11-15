<?php
// lib/client_search.php — Client lookup shared by api/clients.php and its tests.
// Works the same on SQLite and PostgreSQL: filtering runs in PHP.
require_once __DIR__ . '/validation.php';

const CLIENT_SEARCH_LIMIT = 8;

// SQL expression that compacts a stored cedula the same way sweetposCompactCedula() does,
// so legacy rows saved as "V-12.345.678" still match the canonical "12345678".
const CLIENT_CEDULA_SQL_KEY = "REPLACE(REPLACE(REPLACE(UPPER(cedula), '.', ''), '-', ''), ' ', '')";

// JSON flags for client payloads: a legacy row with invalid UTF-8 must not
// turn the whole response into an empty body (json_encode would return false).
const CLIENT_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

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
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    return $client ?: null;
}

function publicClient(array $client): array {
    return [
        'id'             => (int)$client['id'],
        'cedula'         => (string)($client['cedula'] ?? ''),
        'cedula_display' => sweetposFormatCedula($client['cedula'] ?? ''),
        'name'           => (string)($client['name'] ?? ''),
        'phone'          => $client['phone'] ?? null,
    ];
}

// Partial, accent-insensitive search by cedula OR name, best matches first.
// SELECT * on purpose: databases created by older scripts may lack optional
// columns (phone), and a missing column must not break the cashier's search.
function searchClients(PDO $pdo, string $term): array {
    $term = trim($term);
    if ($term === '') return [];

    $isCedula = sweetposLooksLikeCedula($term);
    $cedulaKey = $isCedula ? clientCedulaKey($term) : '';
    $words = array_values(array_filter(explode(' ', sweetposFoldText($term)), fn($w) => $w !== ''));

    $rows = $pdo->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);
    $scored = [];
    foreach ($rows as $row) {
        $score = 0;
        if ($isCedula) {
            $key = clientCedulaKey((string)($row['cedula'] ?? ''));
            if ($key === $cedulaKey) $score = 100;
            else if (str_starts_with($key, $cedulaKey)) $score = 80;
            else if (str_contains($key, $cedulaKey)) $score = 60;
        } else if ($words) {
            $name = sweetposFoldText((string)($row['name'] ?? ''));
            $all = true;
            foreach ($words as $word) {
                if (!str_contains($name, $word)) { $all = false; break; }
            }
            if ($all) $score = str_starts_with($name, $words[0]) ? 70 : 50;
        }
        if ($score > 0) $scored[] = [$score, $row];
    }

    usort($scored, fn($a, $b) => $b[0] <=> $a[0] ?: strcmp((string)($a[1]['name'] ?? ''), (string)($b[1]['name'] ?? '')));
    return array_map(fn($entry) => publicClient($entry[1]), array_slice($scored, 0, CLIENT_SEARCH_LIMIT));
}
