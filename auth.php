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
