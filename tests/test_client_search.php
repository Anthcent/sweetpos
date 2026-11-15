<?php
// Client search used by the POS checkout (api/clients.php?q=).
require_once __DIR__ . '/../lib/client_search.php';

$checks = 0;
$failures = 0;
function verifyClientSearch(string $label, bool $condition): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures++;
    echo ($condition ? 'ok   ' : 'FAIL ') . $label . "\n";
}

// Warnings are failures: in production they would be printed inside the JSON body.
set_error_handler(static function (int $severity, string $message): bool {
    verifyClientSearch("no PHP warning ($message)", false);
    return true;
});

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Legacy schema without the optional phone column.
$pdo->exec("CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT, cedula TEXT UNIQUE NOT NULL, name TEXT NOT NULL)");
$insert = $pdo->prepare('INSERT INTO clients (cedula, name) VALUES (?, ?)');
$insert->execute(['12345678', 'Ana María Pérez']);
$insert->execute(['V-12.399.000', 'Anabel Rojas']);
$insert->execute(['E1234567', 'José Ñúñez']);

$byCedula = searchClients($pdo, 'V-12345678');
verifyClientSearch('exact cedula match comes first', ($byCedula[0]['id'] ?? null) === 1 && $byCedula[0]['cedula_display'] === 'V-12345678');

$partial = array_column(searchClients($pdo, '123'), 'id');
verifyClientSearch('partial cedula matches legacy formatted rows', in_array(2, $partial, true) && in_array(3, $partial, true));

$byName = array_column(searchClients($pdo, 'ana'), 'name');
verifyClientSearch('name search is case-insensitive', $byName === ['Ana María Pérez', 'Anabel Rojas']);

$accent = searchClients($pdo, 'nunez');
verifyClientSearch('name search ignores accents and ñ', ($accent[0]['id'] ?? null) === 3);

verifyClientSearch('missing phone column yields null phone', array_key_exists('phone', $accent[0] ?? []) && $accent[0]['phone'] === null);
verifyClientSearch('empty term returns no results', searchClients($pdo, '   ') === []);

$encoded = json_encode([publicClient(['id' => 9, 'cedula' => '1', 'name' => "Bad \xB1 name"])], CLIENT_JSON_FLAGS);
verifyClientSearch('invalid UTF-8 still encodes to JSON', is_string($encoded) && json_decode($encoded, true)[0]['id'] === 9);

restore_error_handler();
echo "\n$checks checks, $failures failures\n";
exit($failures === 0 ? 0 : 1);
