<?php

/** Create or update a staff account. Only callers that have checked admin access may use this. */
function sweetposSaveUser(PDO $pdo, int $actorId, array $data): void {
    $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
    $username = trim((string)($data['username'] ?? ''));
    $role = (string)($data['role'] ?? '');
    $password = (string)($data['password'] ?? '');
    $active = !empty($data['is_active']) ? 1 : 0;

    if (!preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username)) {
        throw new DomainException('El usuario debe tener 3 a 50 caracteres: letras, números, punto, guion o guion bajo.');
    }
    if (!in_array($role, ['admin', 'gerente', 'vendedor'], true)) {
        throw new DomainException('Selecciona un rol válido.');
    }
    if ($id === null && $password === '') {
        throw new DomainException('La contraseña es obligatoria para un usuario nuevo.');
    }
    if ($password !== '' && strlen($password) < 12) {
        throw new DomainException('La contraseña debe tener al menos 12 caracteres.');
    }
    if ($id === $actorId && ($role !== 'admin' || $active !== 1)) {
        throw new DomainException('No puedes quitarte el acceso de administrador.');
    }

    sweetposBeginWrite($pdo);
    try {
        if (sweetposDatabaseDriver($pdo) === 'pgsql') {
            $pdo->exec('LOCK TABLE users IN EXCLUSIVE MODE');
        }
        if ($id === null) {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
        } else {
            $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) throw new DomainException('El usuario no existe.');
            if ($existing['role'] === 'admin' && (int)$existing['is_active'] === 1 && ($role !== 'admin' || $active !== 1)) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($count <= 1) throw new DomainException('Debe permanecer al menos un administrador activo.');
            }
            if ($password === '') {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$username, $role, $active, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?');
                $stmt->execute([$username, $role, $active, password_hash($password, PASSWORD_DEFAULT), $id]);
            }
        }
        sweetposCommitWrite($pdo);
    } catch (Throwable $exception) {
        sweetposRollbackWrite($pdo);
        if ($exception instanceof PDOException && in_array($exception->getCode(), ['23000', '23505'], true)) {
            throw new DomainException('Ese nombre de usuario ya existe.');
        }
        throw $exception;
    }
}
