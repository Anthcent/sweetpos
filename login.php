<?php
require 'auth.php';

// Si ya está logueado, redirigir al inicio
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        try {
            $db_path = __DIR__ . '/db/database.sqlite';
            $pdo = new PDO("sqlite:" . $db_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error al conectar con la base de datos. Verifica que el sistema este inicializado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sweet POS</title>
    <script src="assets/tailwindcss.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/lucide.min.js"></script>
    <script>
        tailwind.config = {
            theme: { 
                extend: { 
                    colors: { primary: '#f5b8d0', background: '#faf7f4' } 
                } 
            }
        }
    </script>
    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.5s ease-out forwards; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50%       { transform: translateY(-6px) rotate(1deg); }
        }
        .float-logo { animation: float 4s ease-in-out infinite; }

        details.credentials > summary { list-style: none; cursor: pointer; }
        details.credentials > summary::-webkit-details-marker { display: none; }
        details.credentials .chevron { transition: transform 0.2s ease; }
        details.credentials[open] .chevron { transform: rotate(180deg); }
        details.credentials[open] summary { border-radius: 1rem 1rem 0 0; }
    </style>
</head>
<body class="min-h-dvh bg-background flex items-center justify-center p-4 relative overflow-x-hidden">

    <!-- Soft minimal pastel background clouds -->
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-pink-100/40 rounded-full filter blur-3xl pointer-events-none -translate-y-1/3 translate-x-1/3"></div>
    <div class="fixed bottom-0 left-0 w-[450px] h-[450px] bg-purple-100/30 rounded-full filter blur-3xl pointer-events-none translate-y-1/3 -translate-x-1/3"></div>
    <div class="fixed top-1/2 left-1/2 w-[350px] h-[350px] bg-amber-50/40 rounded-full filter blur-3xl pointer-events-none -translate-x-1/2 -translate-y-1/2"></div>

    <div class="w-full max-w-md relative z-10 fade-up">

        <!-- Logo integrado: flota sobre el borde superior de la card -->
        <div class="flex justify-center -mb-10 relative z-20">
            <div class="w-20 h-20 sm:w-22 sm:h-22 rounded-3xl overflow-hidden shadow-xl shadow-pink-900/10 border-4 border-white float-logo bg-white">
                <img src="assets/ice_cream_branding.png" alt="Sweet POS Logo" class="w-full h-full object-cover">
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-card border border-slate-200/80 pt-14 px-6 sm:px-10 pb-8 sm:pb-10">

            <!-- Header -->
            <div class="flex flex-col items-center mb-6 text-center">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-pink-50 border border-pink-100 text-pink-700 text-xs font-bold mb-2">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 stroke-[2]"></i>
                    <span>Helados & Postres Artesanales</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight font-heading">Sweet POS</h1>
                <p class="text-slate-400 text-xs sm:text-sm mt-1">Ingresa tus credenciales para acceder a la terminal</p>
            </div>

            <!-- Alerta de error -->
            <?php if (!empty($error)): ?>
            <div class="mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold animate-[pulse_1s]">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" action="" class="flex flex-col gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Usuario</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        <input
                            type="text"
                            name="username"
                            id="username"
                            required
                            autofocus
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            placeholder="Tu nombre de usuario"
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-slate-800 font-semibold placeholder-slate-400 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Contraseña</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            placeholder="••••••••"
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-slate-800 font-semibold placeholder-slate-400 text-sm">
                    </div>
                </div>

                <!-- Credenciales rápidas colapsadas -->
                <details class="credentials bg-slate-50/80 border border-slate-200/80 rounded-2xl">
                    <summary class="flex items-center justify-between gap-2 px-4 py-2.5 text-slate-500 hover:text-slate-800 transition-colors">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest flex items-center gap-1.5">
                            <i data-lucide="key" class="w-3.5 h-3.5 text-pink-500"></i> Credenciales de demostración
                        </span>
                        <i data-lucide="chevron-down" class="chevron w-3.5 h-3.5 text-slate-400"></i>
                    </summary>
                    <div class="flex flex-col gap-2 px-3.5 pb-3.5 pt-1">
                        <button type="button" onclick="fillCredentials('admin', 'admin123')" class="w-full flex items-center justify-between px-3.5 py-2 bg-white hover:bg-pink-50/70 hover:text-pink-700 hover:border-pink-200 border border-slate-200/80 rounded-xl transition-all group/btn shadow-xs">
                            <span class="text-xs font-bold text-slate-700">Administrador</span>
                            <span class="text-[10px] font-mono text-slate-400 bg-slate-100 group-hover/btn:bg-pink-100 group-hover/btn:text-pink-700 px-2 py-0.5 rounded font-bold">admin123</span>
                        </button>
                        <button type="button" onclick="fillCredentials('gerente', 'gerente123')" class="w-full flex items-center justify-between px-3.5 py-2 bg-white hover:bg-pink-50/70 hover:text-pink-700 hover:border-pink-200 border border-slate-200/80 rounded-xl transition-all group/btn shadow-xs">
                            <span class="text-xs font-bold text-slate-700">Gerente</span>
                            <span class="text-[10px] font-mono text-slate-400 bg-slate-100 group-hover/btn:bg-pink-100 group-hover/btn:text-pink-700 px-2 py-0.5 rounded font-bold">gerente123</span>
                        </button>
                        <button type="button" onclick="fillCredentials('vendedor', 'vendedor123')" class="w-full flex items-center justify-between px-3.5 py-2 bg-white hover:bg-pink-50/70 hover:text-pink-700 hover:border-pink-200 border border-slate-200/80 rounded-xl transition-all group/btn shadow-xs">
                            <span class="text-xs font-bold text-slate-700">Vendedor</span>
                            <span class="text-[10px] font-mono text-slate-400 bg-slate-100 group-hover/btn:bg-pink-100 group-hover/btn:text-pink-700 px-2 py-0.5 rounded font-bold">vendedor123</span>
                        </button>
                    </div>
                </details>

                <button
                    type="submit"
                    class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl shadow-lg transition-all flex items-center justify-center gap-2 group font-heading text-base">
                    <span>Acceder al Sistema</span>
                    <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform stroke-[2.2]"></i>
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 mt-6 font-medium">Sweet POS &copy; <?= date('Y') ?> &mdash; Boutique & Pastelería</p>
        </div>
    </div>

    <script>
        function fillCredentials(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
            document.querySelector('details.credentials').removeAttribute('open');
        }
        lucide.createIcons();
    </script>
</body>
</html>
