<?php

require_once __DIR__ . '/../lib/bootstrap_users.php';

$checks = 0;
$failures = 0;

function checkBootstrap(string $name, bool $condition): void
{
    global $checks, $failures;
    $checks++;
    if ($condition) {
        echo "ok   {$name}\n";
    } else {
        $failures++;
        echo "FAIL {$name}\n";
    }
}

$variables = [
    'SWEETPOS_BOOTSTRAP_ADMIN_PASSWORD',
    'SWEETPOS_BOOTSTRAP_GERENTE_PASSWORD',
    'SWEETPOS_BOOTSTRAP_VENDEDOR_PASSWORD',
];
$secrets = [];
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE users (' .
    'username TEXT PRIMARY KEY, password_hash TEXT NOT NULL, role TEXT NOT NULL)'
);

try {
    foreach ($variables as $variable) {
        putenv($variable);
    }

    $missingSecretsRejected = false;
    try {
        sweetposBootstrapInitialUsers($pdo);
    } catch (RuntimeException $exception) {
        $missingSecretsRejected = str_contains($exception->getMessage(), 'requires runtime secrets');
    }
    checkBootstrap('fresh database requires runtime secrets', $missingSecretsRejected);
    checkBootstrap('missing secrets create no partial users', (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);

    foreach ($variables as $variable) {
        $secrets[$variable] = bin2hex(random_bytes(24));
        putenv($variable . '=' . $secrets[$variable]);
    }

    $pdo->exec(
        "CREATE TRIGGER fail_last_account BEFORE INSERT ON users " .
        "WHEN NEW.username = 'vendedor' BEGIN SELECT RAISE(ABORT, 'test failure'); END"
    );
    $insertFailureRolledBack = false;
    try {
        sweetposBootstrapInitialUsers($pdo);
    } catch (PDOException $exception) {
        $insertFailureRolledBack = true;
    }
    checkBootstrap('failed initial setup rolls back every user', $insertFailureRolledBack && (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);

    $pdo->exec('DROP TRIGGER fail_last_account');
    sweetposBootstrapInitialUsers($pdo);
    $rows = $pdo->query('SELECT username, role, password_hash FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $expected = [
        'admin' => ['admin', $secrets['SWEETPOS_BOOTSTRAP_ADMIN_PASSWORD']],
        'gerente' => ['gerente', $secrets['SWEETPOS_BOOTSTRAP_GERENTE_PASSWORD']],
        'vendedor' => ['vendedor', $secrets['SWEETPOS_BOOTSTRAP_VENDEDOR_PASSWORD']],
    ];
    $accountsValid = count($rows) === 3;
    foreach ($rows as $row) {
        $accountsValid = $accountsValid
            && isset($expected[$row['username']])
            && $row['role'] === $expected[$row['username']][0]
            && password_verify($expected[$row['username']][1], $row['password_hash']);
    }
    checkBootstrap('initial accounts store password hashes with expected roles', $accountsValid);

    foreach ($variables as $variable) {
        putenv($variable);
    }
    sweetposBootstrapInitialUsers($pdo);
    checkBootstrap('existing accounts make setup idempotent', (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3);
} finally {
    foreach ($variables as $variable) {
        putenv($variable);
    }
}

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
