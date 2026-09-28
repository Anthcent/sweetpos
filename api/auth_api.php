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
