<?php
require_once __DIR__ . '/../api/db_connection.php';

$checks = 0;
$failures = 0;
function checkConnection(string $name, bool $condition): void {
    global $checks, $failures;
    $checks++;
    if ($condition) echo "ok   {$name}\n";
    else { $failures++; echo "FAIL {$name}\n"; }
}

$parts = sweetposPostgresConnectionParts('postgresql://deploy%2Duser@db.internal:5433/sweetpos?sslmode=require');
checkConnection('PostgreSQL URL becomes a PDO pgsql DSN', $parts['dsn'] === 'pgsql:host=db.internal;port=5433;dbname=sweetpos;connect_timeout=5;sslmode=require');
checkConnection('credentials are excluded from the DSN', !str_contains($parts['dsn'], 'deploy-user'));
checkConnection('username is percent-decoded for PDO parameters', $parts['username'] === 'deploy-user' && $parts['password'] === '');

$invalidRejected = false;
try {
    sweetposPostgresConnectionParts('https://example.invalid/database');
} catch (InvalidArgumentException $exception) {
    $invalidRejected = true;
}
checkConnection('non-PostgreSQL URL is rejected', $invalidRejected);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
