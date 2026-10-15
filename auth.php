<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    global $pdo;
    require_once __DIR__ . '/api/db.php';
    $stmt = $pdo->prepare('SELECT username, role, is_active, password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['is_active'] !== 1 ||
        !hash_equals(hash('sha256', $user['password_hash']), (string)($_SESSION['credential_stamp'] ?? ''))) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

function requireRole(array $roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        header('Location: index.php');
        exit();
    }
}

function currentUser() {
    return $_SESSION['username'] ?? 'Usuario';
}

function currentRole() {
    return $_SESSION['role'] ?? '';
}

function getRoleLabel() {
    $labels = ['admin' => 'Administrador', 'gerente' => 'Gerente', 'vendedor' => 'Vendedor'];
    return $labels[currentRole()] ?? currentRole();
}

function getRoleBadgeClass() {
    $classes = [
        'admin'    => 'bg-violet-100 text-violet-700',
        'gerente'  => 'bg-blue-100 text-blue-700',
        'vendedor' => 'bg-emerald-100 text-emerald-700',
    ];
    return $classes[currentRole()] ?? 'bg-slate-100 text-slate-600';
}

function canAccess($module) {
    $role = currentRole();
    $perms = [
        'panel'             => ['admin', 'gerente'],
        'movimientos'       => ['admin', 'gerente'],
        'pos'               => ['admin', 'gerente', 'vendedor'],
        'mesas'             => ['admin', 'gerente', 'vendedor'],
        'historial'         => ['admin', 'gerente'],
        'reportes'          => ['admin', 'gerente'],
        'inventario'        => ['admin', 'gerente'],
        'inventario_delete' => ['admin'],
        'materiales'        => ['admin', 'gerente'],
        'produccion'        => ['admin', 'gerente'],
        'clientes'          => ['admin', 'gerente', 'vendedor'],
        'usuarios'          => ['admin'],
    ];
    return in_array($role, $perms[$module] ?? [], true);
}
?>
