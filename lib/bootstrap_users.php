<?php

/**
 * Creates the initial SweetPOS accounts on a fresh database.
 * Passwords come from the runtime secret manager and are never stored in code
 * or image build arguments.
 */
function sweetposBootstrapInitialUsers(PDO $pdo): void
{
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount > 0) {
        return;
    }

    $accounts = [
        ['admin', 'admin', 'SWEETPOS_BOOTSTRAP_ADMIN_PASSWORD'],
        ['gerente', 'gerente', 'SWEETPOS_BOOTSTRAP_GERENTE_PASSWORD'],
        ['vendedor', 'vendedor', 'SWEETPOS_BOOTSTRAP_VENDEDOR_PASSWORD'],
    ];
    $passwords = [];
    $missing = [];
    $tooShort = [];

    foreach ($accounts as [$username, $role, $variable]) {
        $password = getenv($variable);
        if ($password === false || $password === '') {
            $missing[] = $variable;
            continue;
        }
        if (strlen($password) < 12) {
            $tooShort[] = $variable;
            continue;
        }
        $passwords[] = [$username, $role, $password];
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Initial account setup requires runtime secrets: ' . implode(', ', $missing)
        );
    }
    if ($tooShort !== []) {
        throw new RuntimeException(
            'Initial account secrets must contain at least 12 characters: ' . implode(', ', $tooShort)
        );
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
    );
    $pdo->beginTransaction();
    try {
        foreach ($passwords as [$username, $role, $password]) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Unable to hash an initial account password.');
            }
            $insert->execute([$username, $hash, $role]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
