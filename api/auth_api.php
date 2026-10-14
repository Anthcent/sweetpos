<?php
// api/auth_api.php — helper de sesiones para endpoints de la API
// Devuelve JSON en lugar de redirigir (apropiado para fetch() desde el frontend)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function apiRequireLogin() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "No autenticado. Inicia sesion."]);
        exit();
    }
    global $pdo;
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare('SELECT username, role, is_active, password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['is_active'] !== 1 ||
        !hash_equals(hash('sha256', $user['password_hash']), (string)($_SESSION['credential_stamp'] ?? ''))) {
        $_SESSION = [];
        session_destroy();
        http_response_code(401);
        echo json_encode(["error" => "Sesion vencida. Inicia sesion."]);
        exit();
    }
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

function apiRequireRole(array $roles) {
    apiRequireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        echo json_encode(["error" => "Sin permisos para esta operacion."]);
        exit();
    }
}

function apiCurrentRole() {
    return $_SESSION['role'] ?? '';
}
?>
