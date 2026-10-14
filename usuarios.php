<?php
require 'auth.php';
requireRole(['admin']);
require_once __DIR__ . '/lib/user_accounts.php';

if (empty($_SESSION['users_csrf'])) $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
$error = '';
$message = isset($_GET['saved']) ? 'Usuario guardado correctamente.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['users_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        $error = 'La sesión del formulario venció. Recarga la página.';
    } else {
        try {
            sweetposSaveUser($pdo, (int)$_SESSION['user_id'], $_POST);
            if (isset($_POST['id']) && (int)$_POST['id'] === (int)$_SESSION['user_id']) {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['credential_stamp'] = hash('sha256', $stmt->fetchColumn());
            }
            header('Location: usuarios.php?saved=1', true, 303);
            exit();
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('SweetPOS user management failed.');
            http_response_code(500);
            $error = 'No se pudo guardar el usuario. Intenta de nuevo.';
        }
    }
}
$users = $pdo->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY id')->fetchAll();
$escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Sweet POS</title>
    <script src="assets/tailwindcss.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/lucide.min.js"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#f5b8d0', background: '#faf7f4' } } } };</script>
</head>
<body class="h-screen overflow-hidden flex text-slate-800">
    <?php require '_sidebar.php'; ?>
    <main class="flex-1 min-w-0 overflow-y-auto bg-background">
        <header class="px-5 sm:px-8 py-5 bg-white border-b border-slate-200">
            <h1 class="text-xl sm:text-2xl font-extrabold">Usuarios</h1>
            <p class="text-sm text-slate-500 mt-1">Crea cuentas y actualiza su rol, contraseña o acceso.</p>
        </header>
        <div class="max-w-5xl mx-auto p-4 sm:p-8 space-y-6">
            <?php if ($message): ?><p role="status" class="p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200"><?= $escape($message) ?></p><?php endif; ?>
            <?php if ($error): ?><p role="alert" class="p-4 rounded-xl bg-rose-50 text-rose-800 border border-rose-200"><?= $escape($error) ?></p><?php endif; ?>

            <section class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-bold mb-4">Nuevo usuario</h2>
                <form method="post" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['users_csrf']) ?>">
                    <label class="text-sm font-semibold">Usuario
                        <input name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="off" class="mt-1 w-full rounded-xl border border-slate-300 p-3" placeholder="Nombre de usuario">
                    </label>
                    <label class="text-sm font-semibold">Rol
                        <select name="role" required class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                            <option value="vendedor">Vendedor</option><option value="gerente">Gerente</option><option value="admin">Administrador</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Contraseña
                        <input type="password" name="password" required minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-300 p-3" placeholder="Mínimo 12 caracteres">
                    </label>
                    <input type="hidden" name="is_active" value="1">
                    <button class="rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold p-3">Crear usuario</button>
                </form>
            </section>

            <section class="space-y-4" aria-label="Usuarios existentes">
                <h2 class="text-lg font-bold">Cuentas existentes</h2>
                <?php foreach ($users as $user): ?>
                <form method="post" class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-sm grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['users_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                    <label class="text-sm font-semibold">Usuario
                        <input name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" value="<?= $escape($user['username']) ?>" class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                    </label>
                    <label class="text-sm font-semibold">Rol
                        <select name="role" class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                            <?php foreach (['vendedor' => 'Vendedor', 'gerente' => 'Gerente', 'admin' => 'Administrador'] as $role => $label): ?>
                            <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Nueva contraseña
                        <input type="password" name="password" minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-300 p-3" placeholder="Dejar vacío para conservar">
                    </label>
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-semibold flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$user['is_active'] === 1 ? 'checked' : '' ?> class="w-4 h-4">
                            Activo
                        </label>
                        <button class="rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold px-4 py-3">Guardar</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
    <script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
