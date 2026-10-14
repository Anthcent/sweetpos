<?php
require_once __DIR__ . '/../api/db_connection.php';
require_once __DIR__ . '/../lib/user_accounts.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
$initialPassword = bin2hex(random_bytes(16));
$firstPassword = bin2hex(random_bytes(16));
$replacementPassword = bin2hex(random_bytes(16));
$pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')->execute(['admin', password_hash($initialPassword, PASSWORD_DEFAULT), 'admin']);

$checks = 0;
$failures = 0;
function verifyAccount(string $label, bool $condition): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures++;
    echo ($condition ? 'ok   ' : 'FAIL ') . $label . "\n";
}

sweetposSaveUser($pdo, 1, ['username' => 'cajero2', 'role' => 'vendedor', 'password' => $firstPassword, 'is_active' => '1']);
$user = $pdo->query("SELECT * FROM users WHERE username = 'cajero2'")->fetch(PDO::FETCH_ASSOC);
verifyAccount('creates user with selected role and hashed password', $user && $user['role'] === 'vendedor' && password_verify($firstPassword, $user['password_hash']));

sweetposSaveUser($pdo, 1, ['id' => $user['id'], 'username' => 'cajero2', 'role' => 'gerente', 'password' => '', 'is_active' => '1']);
$updated = $pdo->query("SELECT * FROM users WHERE username = 'cajero2'")->fetch(PDO::FETCH_ASSOC);
verifyAccount('changes role without changing password', $updated['role'] === 'gerente' && $updated['password_hash'] === $user['password_hash']);

sweetposSaveUser($pdo, 1, ['id' => $user['id'], 'username' => 'cajero2', 'role' => 'gerente', 'password' => $replacementPassword]);
$updated = $pdo->query("SELECT * FROM users WHERE username = 'cajero2'")->fetch(PDO::FETCH_ASSOC);
verifyAccount('disables account and changes password', (int)$updated['is_active'] === 0 && password_verify($replacementPassword, $updated['password_hash']));

$denied = false;
try { sweetposSaveUser($pdo, 1, ['id' => 1, 'username' => 'admin', 'role' => 'vendedor', 'password' => '', 'is_active' => '1']); }
catch (DomainException $e) { $denied = true; }
verifyAccount('cannot demote own admin account', $denied);

$denied = false;
try { sweetposSaveUser($pdo, 999, ['id' => 1, 'username' => 'admin', 'role' => 'gerente', 'password' => '', 'is_active' => '1']); }
catch (DomainException $e) { $denied = true; }
verifyAccount('cannot remove final active admin', $denied);

$denied = false;
try { sweetposSaveUser($pdo, 1, ['username' => 'bad', 'role' => 'vendedor', 'password' => 'short', 'is_active' => '1']); }
catch (DomainException $e) { $denied = true; }
verifyAccount('rejects short password', $denied);

echo "{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
