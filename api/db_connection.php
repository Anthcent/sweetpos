<?php

/** Return the active PDO driver name. */
function sweetposDatabaseDriver(PDO $pdo): string {
    return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * Parse the managed PostgreSQL URL without exposing it or putting credentials
 * into the PDO DSN. The username and password are passed separately to PDO.
 */
function sweetposPostgresConnectionParts(string $url): array {
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['postgres', 'postgresql'], true)) {
        throw new InvalidArgumentException('DATABASE_URL debe ser una URL PostgreSQL válida.');
    }

    $host = trim((string)($parts['host'] ?? ''), '[]');
    $port = isset($parts['port']) ? (int)$parts['port'] : 5432;
    $database = rawurldecode(ltrim((string)($parts['path'] ?? ''), '/'));
    $username = rawurldecode((string)($parts['user'] ?? ''));
    $password = rawurldecode((string)($parts['pass'] ?? ''));
    foreach ([$host, $database, $username, $password] as $value) {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('DATABASE_URL contiene un campo inválido.');
        }
    }
    if ($host === '' || $database === '' || $username === '' || $port < 1 || $port > 65535) {
        throw new InvalidArgumentException('DATABASE_URL debe incluir host, base de datos y usuario.');
    }
    if (strpbrk($host . $database, ";\r\n") !== false) {
        throw new InvalidArgumentException('DATABASE_URL contiene un campo inválido.');
    }

    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    $sslMode = (string)($query['sslmode'] ?? '');
    if ($sslMode !== '' && !in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        throw new InvalidArgumentException('DATABASE_URL contiene un modo SSL no compatible.');
    }

    return [
        'dsn' => 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';connect_timeout=5' . ($sslMode !== '' ? ';sslmode=' . $sslMode : ''),
        'username' => $username,
        'password' => $password,
    ];
}

/** Open the managed PostgreSQL database or the local SQLite database. */
function sweetposConnectFromEnvironment(): PDO {
    $databaseUrl = getenv('DATABASE_URL');
    if (is_string($databaseUrl) && trim($databaseUrl) !== '') {
        $parts = sweetposPostgresConnectionParts($databaseUrl);
        $pdo = new PDO($parts['dsn'], $parts['username'], $parts['password']);
        $pdo->exec("SET TIME ZONE 'UTC'");
    } else {
        $dbPath = getenv('SWEETPOS_DB_PATH');
        if (!$dbPath) {
            if (getenv('VERCEL') || getenv('AWS_LAMBDA_FUNCTION_NAME') || !empty($_ENV['VERCEL'])) {
                $dbPath = sys_get_temp_dir() . '/database.sqlite';
            } else {
                $dbDir = __DIR__ . '/../db';
                if (!is_dir($dbDir) && !@mkdir($dbDir, 0777, true) && !is_dir($dbDir)) {
                    throw new RuntimeException('No se pudo preparar el directorio de la base local.');
                }
                $dbPath = $dbDir . '/database.sqlite';
            }
        }
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/** Read the generated ID for either SQLite or PostgreSQL. */
function sweetposLastInsertId(PDO $pdo, string $table): int {
    $id = sweetposDatabaseDriver($pdo) === 'pgsql'
        ? $pdo->lastInsertId($table . '_id_seq')
        : $pdo->lastInsertId();
    if ($id === false || $id === '') throw new RuntimeException('No se pudo recuperar el identificador creado.');
    return (int)$id;
}

function sweetposBeginWrite(PDO $pdo): void {
    if (sweetposDatabaseDriver($pdo) === 'pgsql') $pdo->beginTransaction();
    else $pdo->exec('BEGIN IMMEDIATE');
}

function sweetposCommitWrite(PDO $pdo): void {
    if (sweetposDatabaseDriver($pdo) === 'pgsql') $pdo->commit();
    else $pdo->exec('COMMIT');
}

function sweetposRollbackWrite(PDO $pdo): void {
    if (sweetposDatabaseDriver($pdo) === 'pgsql') {
        if ($pdo->inTransaction()) $pdo->rollBack();
    } elseif ($pdo->inTransaction()) {
        $pdo->exec('ROLLBACK');
    }
}

?>
