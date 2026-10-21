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
            require_once __DIR__ . '/api/db.php';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['credential_stamp'] = hash('sha256', $user['password_hash']);
                header('Location: index.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error al conectar con la base de datos. Verifica que el sistema esté inicializado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet POS - Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700;800;900&family=Nunito:wght@400;500;600;700;800;900&family=Outfit:wght@500;600;700;800;900&family=Titan+One&display=swap" rel="stylesheet">
    <script src="assets/tailwindcss.js"></script>
    <script src="assets/lucide.min.js"></script>
    <style>
        :root {
            /* Paleta Rosada Vibrante & Deliciosa Sweet POS */
            --pink-vibrant: #FF4D80;
            --pink-main: #ED2866;
            --pink-deep: #D41450;
            --pink-candy: #FF7098;
            --pink-pastel: #F8B4C8;
            --pink-blush: #FCE6EE;
            --pink-canvas: #FFF0F5;

            /* Superficies & Chocolate */
            --chocolate: #3A1B14;
            --chocolate-border: #4E3730;
            --cream-drip: #FFF7ED;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: #251d22;
            overflow-x: hidden;
        }

        .font-bubble {
            font-family: 'Fredoka', 'Titan One', cursive, sans-serif;
        }

        .font-heading {
            font-family: 'Outfit', 'Nunito', sans-serif;
        }

        /* 3D Bubble Logo Sticker Effect */
        .sweet-brand-title {
            font-family: 'Fredoka', 'Titan One', cursive, sans-serif;
            font-weight: 900;
            color: #361912;
            letter-spacing: -0.01em;
            -webkit-text-stroke: 8px #ffffff;
            paint-order: stroke fill;
            filter: drop-shadow(0 8px 18px rgba(50, 15, 25, 0.4));
            display: inline-block;
            user-select: none;
        }

        /* Suave flotación del cono */
        @keyframes gentleFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-9px) rotate(1.2deg); }
        }
        .animate-float {
            animation: gentleFloat 4.2s ease-in-out infinite;
        }

        /* Destellos de estrellas */
        @keyframes twinkle {
            0%, 100% { opacity: 0.85; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.25); }
        }
        .animate-twinkle {
            animation: twinkle 2.6s ease-in-out infinite;
        }

        /* Gradientes Vivos Animados para el Hero */
        @keyframes pinkGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .animated-pink-hero {
            background: linear-gradient(135deg, #FF6B93 0%, #F53872 35%, #ED2162 70%, #CC0E4B 100%);
            background-size: 200% 200%;
            animation: pinkGradientShift 10s ease infinite;
        }

        /* Cápsulas de Entrada (Input Pills) */
        .pos-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 2px solid var(--chocolate-border);
            border-radius: 9999px;
            padding: 0 0.85rem 0 1.15rem;
            height: 52px;
            box-shadow: 0 2px 6px rgba(58, 27, 20, 0.05);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .pos-input-wrapper:focus-within {
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(237, 40, 102, 0.24);
        }

        /* Botón con Gradiente Rosado Vibrante y Shimmer */
        .btn-sweet-wrapper {
            position: relative;
            width: 100%;
            margin-top: 0.75rem;
        }

        .btn-sweet-action {
            position: relative;
            z-index: 2;
            width: 100%;
            background: linear-gradient(135deg, #FF4D80 0%, #ED2866 50%, #D41450 100%);
            background-size: 180% auto;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            padding: 0.95rem 1.5rem;
            border-radius: 9999px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            box-shadow: 0 10px 26px -3px rgba(237, 40, 102, 0.45);
            transition: all 0.25s ease;
        }
        .btn-sweet-action:hover {
            background-position: right center;
            transform: translateY(-2px);
            box-shadow: 0 14px 32px -3px rgba(237, 40, 102, 0.55);
        }
        .btn-sweet-action:active {
            transform: translateY(1px);
            box-shadow: 0 4px 12px rgba(237, 40, 102, 0.35);
        }

        .btn-drip-shelf {
            position: absolute;
            top: calc(100% - 7px);
            left: 0;
            width: 100%;
            height: 38px;
            z-index: 1;
            pointer-events: none;
            filter: drop-shadow(0 4px 6px rgba(50, 25, 20, 0.12));
            transition: transform 0.22s ease;
        }
        .btn-sweet-action:hover + .btn-drip-shelf {
            transform: translateY(-2px);
        }

        /* Checkbox Personalizado */
        .pos-checkbox {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--chocolate-border);
            border-radius: 5px;
            outline: none;
            cursor: pointer;
            position: relative;
            background: #ffffff;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .pos-checkbox:checked {
            background-color: var(--pink-main);
            border-color: var(--pink-main);
        }
        .pos-checkbox:checked::after {
            content: '';
            position: absolute;
            left: 4.5px;
            top: 1px;
            width: 5px;
            height: 9px;
            border: solid white;
            border-width: 0 2.2px 2.2px 0;
            transform: rotate(45deg);
        }

        /* Animación Shake para Alerta de Error */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .shake-error {
            animation: shake 0.4s ease-in-out;
        }

        /* =========================================================
           SPLASH DE BIENVENIDA — SOLO CELULARES
           Muestra Sweet POS y luego lleva el formulario a la vista.
           ========================================================= */
        .mobile-login-splash {
            display: none;
        }

        @media (max-width: 1023px) {
            html,
            body {
                height: 100%;
                overflow: hidden !important;
            }

            body {
                min-height: 100dvh;
            }

            /* En móvil/tableta el hero se reutiliza como splash a pantalla completa. */
            .animated-pink-hero {
                display: none !important;
            }

            body.login-splash-active .animated-pink-hero {
                display: flex !important;
                position: fixed !important;
                inset: 0 !important;
                z-index: 9999 !important;
                width: 100% !important;
                height: 100dvh !important;
                min-height: 100dvh !important;
                max-height: 100dvh !important;
                margin: 0 !important;
                padding: 1.5rem 1.25rem !important;
                border-right: 0 !important;
                box-shadow: none !important;
                cursor: pointer;
                transition: opacity 360ms cubic-bezier(0.16, 1, 0.3, 1), transform 360ms cubic-bezier(0.16, 1, 0.3, 1);
            }

            body.login-splash-active .animated-pink-hero.login-hero-leaving {
                opacity: 0;
                transform: scale(1.04);
                pointer-events: none;
            }

            #login-panel {
                height: 100dvh;
                min-height: 100dvh;
                justify-content: center !important;
                padding: 1rem 1.25rem !important;
                overflow: hidden;
            }

            body.login-splash-active {
                overflow: hidden !important;
                height: 100dvh;
            }

            .mobile-login-splash {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                padding: 2rem 1.5rem;
                text-align: center;
                color: #ffffff;
                background:
                    radial-gradient(circle at 20% 15%, rgba(255, 255, 255, 0.22), transparent 13rem),
                    radial-gradient(circle at 85% 80%, rgba(255, 209, 82, 0.18), transparent 12rem),
                    linear-gradient(145deg, #FF6B93 0%, #ED2866 48%, #B70D43 100%);
                overflow: hidden;
                cursor: pointer;
            }

            .mobile-login-splash::before,
            .mobile-login-splash::after {
                content: '';
                position: absolute;
                border-radius: 9999px;
                pointer-events: none;
            }

            .mobile-login-splash::before {
                width: 18rem;
                height: 18rem;
                top: -7rem;
                right: -7rem;
                background: rgba(255, 255, 255, 0.12);
                filter: blur(2px);
            }

            .mobile-login-splash::after {
                width: 14rem;
                height: 14rem;
                bottom: -6rem;
                left: -6rem;
                background: rgba(255, 255, 255, 0.1);
            }

            .splash-logo {
                width: 5.5rem;
                height: 5.5rem;
                border-radius: 1.6rem;
                overflow: hidden;
                background: rgba(255, 255, 255, 0.18);
                border: 1.5px solid rgba(255, 255, 255, 0.55);
                box-shadow: 0 18px 36px -10px rgba(80, 0, 30, 0.45);
                animation: splashFloat 2.8s ease-in-out infinite;
                position: relative;
                z-index: 1;
            }

            .splash-logo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .splash-title {
                position: relative;
                z-index: 1;
                margin-top: 1rem;
                font-size: clamp(2.25rem, 13vw, 3.6rem);
                -webkit-text-stroke: 4px #ffffff;
                paint-order: stroke fill;
                filter: drop-shadow(0 10px 22px rgba(60, 0, 25, 0.35));
                animation: splashTitleIn 650ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .splash-subtitle {
                position: relative;
                z-index: 1;
                margin-top: 0.1rem;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: rgba(255, 255, 255, 0.9);
                animation: splashTitleIn 650ms 120ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .splash-loader {
                position: relative;
                z-index: 1;
                width: 7rem;
                height: 0.4rem;
                margin-top: 1.5rem;
                border-radius: 9999px;
                background: rgba(255, 255, 255, 0.24);
                overflow: hidden;
            }

            .splash-loader span {
                display: block;
                width: 45%;
                height: 100%;
                border-radius: inherit;
                background: #ffffff;
                box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
                animation: splashLoad 1.15s ease-in-out infinite;
            }

            .splash-hint {
                position: relative;
                z-index: 1;
                margin-top: 0.85rem;
                font-size: 0.68rem;
                font-weight: 700;
                color: rgba(255, 255, 255, 0.82);
            }

            .mobile-login-splash.is-leaving {
                animation: splashOut 360ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
                pointer-events: none;
            }

            #login-form-container {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                max-height: 100%;
                padding-top: 0.75rem !important;
                padding-bottom: 1.25rem !important;
            }

            #login-form-container > .text-center {
                margin-bottom: 1.25rem !important;
            }

            .login-form-reveal {
                animation: loginFormReveal 480ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }
        }

        /* Pantallas móviles muy bajas: compactar sin necesidad de scroll. */
        @media (max-width: 1023px) and (max-height: 540px) {
            body.login-splash-active .login-hero-content {
                transform: scale(0.78);
                transform-origin: center;
            }

            #login-form-container {
                padding-top: 0.4rem !important;
                padding-bottom: 0.6rem !important;
            }

            #login-form-container > .text-center {
                margin-bottom: 0.5rem !important;
            }

            #login-form-container > .text-center h2 {
                font-size: 1.65rem !important;
            }

            #login-form-container > .text-center p {
                margin-top: 0.2rem !important;
                font-size: 0.68rem !important;
            }

            #loginForm {
                gap: 0.55rem !important;
            }

            .pos-input-wrapper {
                height: 46px;
            }

            .btn-sweet-action {
                padding: 0.7rem 1rem !important;
                font-size: 0.95rem !important;
            }
        }

        @keyframes splashFloat {
            0%, 100% { transform: translateY(0) rotate(-1deg); }
            50% { transform: translateY(-7px) rotate(1deg); }
        }

        @keyframes splashTitleIn {
            from { opacity: 0; transform: translateY(14px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes splashLoad {
            0% { transform: translateX(-120%); }
            55% { transform: translateX(70%); }
            100% { transform: translateX(240%); }
        }

        @keyframes splashOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(1.04); }
        }

        @keyframes loginFormReveal {
            from { opacity: 0.35; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (min-width: 1024px) {
            .mobile-login-splash {
                display: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .mobile-login-splash,
            .splash-logo,
            .splash-title,
            .splash-subtitle,
            .splash-loader span,
            .login-form-reveal {
                animation: none !important;
            }
        }
    </style>
    <noscript>
        <style>
            body.login-splash-active { overflow: auto !important; height: auto !important; }
        </style>
    </noscript>
</head>
<body class="flex flex-col min-h-screen">

    <?php if (empty($error)): ?>
    <!-- En móvil el hero original se muestra como splash antes del formulario. -->
    <script>
        (function () {
            var mobile = window.matchMedia('(max-width: 1023px)').matches;
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (mobile && !reduce) {
                document.body.classList.add('login-splash-active');
            }
        })();
    </script>
    <?php endif; ?>

    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 min-h-screen">

        <!-- ======================================================== -->
        <!-- PANEL IZQUIERDO: HERO ROSADO VIBRANTE CON GRADIENTE DINÁMICO -->
        <!-- ======================================================== -->
        <div class="lg:col-span-5 relative flex flex-col justify-center items-center px-6 py-12 select-none overflow-hidden min-h-[480px] lg:min-h-screen z-10 animated-pink-hero"
             style="box-shadow: 12px 0 40px -8px rgba(220, 30, 85, 0.28);
                    border-right: 1.5px solid rgba(255, 255, 255, 0.35);">

            <!-- Destellos de luz ambiental rosada en el hero -->
            <div class="absolute -top-16 -left-16 w-80 h-80 bg-white/20 rounded-full filter blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -right-16 w-80 h-80 bg-pink-400/30 rounded-full filter blur-3xl pointer-events-none"></div>

            <!-- CAPA SUPERIOR: FROSTING DE CHOCOLATE CON CHISPAS (Ondulación suave y elegante, sin divisiones) -->
            <div class="absolute top-0 left-0 w-full pointer-events-none z-10">
                <svg viewBox="0 0 800 110" preserveAspectRatio="none" class="w-full h-20 sm:h-24 md:h-26 drop-shadow-[0_6px_14px_rgba(40,15,10,0.22)]">
                    <!-- Capa 1: Crema blanca/vainilla suave bajo el chocolate -->
                    <path d="M 0,0 L 800,0 L 800,42
                             C 775,42 760,62 740,62
                             C 720,62 710,38 680,38
                             C 655,38 645,72 620,72
                             C 595,72 585,42 555,42
                             C 530,42 520,58 495,58
                             C 470,58 460,36 425,36
                             C 395,36 385,76 355,76
                             C 325,76 315,40 280,40
                             C 255,40 245,64 215,64
                             C 185,64 175,34 140,34
                             C 110,34 100,72 70,72
                             C 45,72 30,44 0,44 Z"
                          fill="#FFF5EB" />

                    <!-- Capa 2: Glaseado de Chocolate suave principal -->
                    <path d="M 0,0 L 800,0 L 800,36
                             C 775,36 760,56 740,56
                             C 720,56 710,32 680,32
                             C 655,32 645,66 620,66
                             C 595,66 585,36 555,36
                             C 530,36 520,52 495,52
                             C 470,52 460,30 425,30
                             C 395,30 385,70 355,70
                             C 325,70 315,34 280,34
                             C 255,34 245,58 215,58
                             C 185,58 175,28 140,28
                             C 110,28 100,66 70,66
                             C 45,66 30,38 0,38 Z"
                          fill="#3E1F17" />

                    <!-- Gotas delicadas de chocolate (dentro de los límites del viewBox para evitar divisiones) -->
                    <circle cx="355" cy="80" r="3.5" fill="#3E1F17" />
                    <circle cx="620" cy="76" r="3" fill="#3E1F17" />
                    <circle cx="70" cy="76" r="3" fill="#3E1F17" />

                    <!-- CHISPAS / SPRINKLES sobre el chocolate (colocadas armónicamente) -->
                    <!-- Chispas Cyan -->
                    <rect x="55" y="16" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(22 55 16)" />
                    <rect x="185" y="24" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(-30 185 24)" />
                    <rect x="335" y="36" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(35 335 36)" />
                    <rect x="475" y="20" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(-20 475 20)" />
                    <rect x="625" y="24" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(25 625 24)" />
                    <rect x="735" y="26" width="15" height="5.5" rx="2.75" fill="#58D3E6" transform="rotate(-40 735 26)" />

                    <!-- Chispas Blancas -->
                    <rect x="90" y="26" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(-35 90 26)" />
                    <rect x="235" y="28" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(15 235 28)" />
                    <rect x="365" y="18" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(-45 365 18)" />
                    <rect x="525" y="20" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(30 525 20)" />
                    <rect x="675" y="16" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(-15 675 16)" />
                    <rect x="765" y="18" width="15" height="5.5" rx="2.75" fill="#FFFFFF" transform="rotate(45 765 18)" />

                    <!-- Chispas Rosa Vibrante -->
                    <rect x="125" y="16" width="15" height="5.5" rx="2.75" fill="#FF7098" transform="rotate(50 125 16)" />
                    <rect x="295" y="20" width="15" height="5.5" rx="2.75" fill="#FF7098" transform="rotate(-25 295 20)" />
                    <rect x="425" y="15" width="15" height="5.5" rx="2.75" fill="#FF7098" transform="rotate(40 425 15)" />
                    <rect x="605" y="32" width="15" height="5.5" rx="2.75" fill="#FF7098" transform="rotate(-30 605 32)" />

                    <!-- Chispas Amarillas Vainilla -->
                    <rect x="155" y="24" width="15" height="5.5" rx="2.75" fill="#FFD152" transform="rotate(12 155 24)" />
                    <rect x="255" y="18" width="15" height="5.5" rx="2.75" fill="#FFD152" transform="rotate(-60 255 18)" />
                    <rect x="500" y="28" width="15" height="5.5" rx="2.75" fill="#FFD152" transform="rotate(20 500 28)" />
                    <rect x="710" y="22" width="15" height="5.5" rx="2.75" fill="#FFD152" transform="rotate(-65 710 22)" />

                    <!-- Puntos redondos de chispas -->
                    <circle cx="95" cy="40" r="2.5" fill="#FFFFFF" />
                    <circle cx="215" cy="22" r="2.5" fill="#58D3E6" />
                    <circle cx="385" cy="36" r="2.5" fill="#FFD152" />
                    <circle cx="565" cy="24" r="2.5" fill="#FF7098" />
                    <circle cx="655" cy="30" r="2.5" fill="#FFFFFF" />
                </svg>
            </div>

            <!-- CONTENIDO CENTRAL: TÍTULO, CONO Y LEMA (Equilibrado y centrado) -->
            <div class="login-hero-content flex flex-col items-center text-center z-20 my-auto pt-12 sm:pt-16">

                <!-- Logo Sweet POS -->
                <div class="mb-3 sm:mb-5">
                    <h1 class="sweet-brand-title text-4xl sm:text-5xl md:text-6xl tracking-tight">
                        Sweet POS
                    </h1>
                </div>

                <!-- Ilustración Vectorial del Cono de Helado Artesanal -->
                <div class="relative w-36 h-48 sm:w-44 sm:h-56 my-2 animate-float">
                    <!-- Destellos y estrellitas mágicas alrededor -->
                    <div class="absolute -top-1 left-5 text-white text-xl animate-twinkle">✦</div>
                    <div class="absolute top-10 right-3 text-pink-100 text-sm animate-twinkle" style="animation-delay: 1.2s;">✧</div>
                    <div class="absolute top-20 -left-2 text-amber-100 text-base animate-twinkle" style="animation-delay: 0.6s;">✦</div>

                    <svg viewBox="0 0 160 220" class="w-full h-full drop-shadow-[0_12px_24px_rgba(50,15,25,0.35)]" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <!-- Gradiente de Helado Suave Fresa Artesanal -->
                            <linearGradient id="iceCreamSwirlGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#FFFFFF"/>
                                <stop offset="40%" stop-color="#FFD6E2"/>
                                <stop offset="100%" stop-color="#FF9EBA"/>
                            </linearGradient>

                            <!-- Gradiente de Barquillo Waffle -->
                            <linearGradient id="waffleConeGrad" x1="0%" y1="0%" x2="100%" y2="50%">
                                <stop offset="0%" stop-color="#F1B66D"/>
                                <stop offset="50%" stop-color="#E59E52"/>
                                <stop offset="100%" stop-color="#C7782A"/>
                            </linearGradient>

                            <!-- Clip path para el barquillo -->
                            <clipPath id="coneClip">
                                <path d="M 44,120 L 116,120 L 80,210 Z"/>
                            </clipPath>
                        </defs>

                        <!-- Sombra suave debajo del cono -->
                        <ellipse cx="80" cy="214" rx="28" ry="5" fill="#3A1B14" opacity="0.3" filter="blur(3px)" />

                        <!-- BARQUILLO / WAFFLE CONE -->
                        <!-- Cuerpo triangular del cono -->
                        <path d="M 44,120 L 116,120 L 80,210 Z" fill="url(#waffleConeGrad)" stroke="#3A1B14" stroke-width="2.5" stroke-linejoin="round"/>

                        <!-- Cuadrícula waffle dentro del clip -->
                        <g clip-path="url(#coneClip)" stroke="#A65818" stroke-width="1.8" opacity="0.75">
                            <line x1="30" y1="110" x2="120" y2="200" />
                            <line x1="45" y1="110" x2="120" y2="185" />
                            <line x1="60" y1="110" x2="120" y2="170" />
                            <line x1="75" y1="110" x2="120" y2="155" />
                            <line x1="20" y1="125" x2="105" y2="210" />
                            <line x1="20" y1="140" x2="90" y2="210" />
                            <line x1="20" y1="155" x2="75" y2="210" />

                            <line x1="130" y1="110" x2="40" y2="200" />
                            <line x1="115" y1="110" x2="40" y2="185" />
                            <line x1="100" y1="110" x2="40" y2="170" />
                            <line x1="85" y1="110" x2="40" y2="155" />
                            <line x1="140" y1="125" x2="55" y2="210" />
                            <line x1="140" y1="140" x2="70" y2="210" />
                            <line x1="140" y1="155" x2="85" y2="210" />
                        </g>

                        <!-- Borde superior del cono (anillo) -->
                        <path d="M 40,118 C 40,115 42,113 46,113 L 114,113 C 118,113 120,115 120,118 C 120,121 118,123 114,123 L 46,123 C 42,123 40,121 40,118 Z"
                              fill="#EDAB63" stroke="#3A1B14" stroke-width="2.5" />

                        <!-- HELADO SUAVE (SWIRL DE FRESA ARTESANAL ROSADO VIBRANTE) -->
                        <!-- Capa Base 3 (inferior) -->
                        <path d="M 44,116
                                 C 32,116 30,95 44,92
                                 C 52,90 60,94 72,94
                                 C 88,94 96,88 108,92
                                 C 124,95 128,116 116,116
                                 C 106,120 54,120 44,116 Z"
                              fill="url(#iceCreamSwirlGrad)" stroke="#3A1B14" stroke-width="2.5" stroke-linejoin="round" />

                        <!-- Capa Media 2 -->
                        <path d="M 48,93
                                 C 38,90 38,72 52,70
                                 C 62,68 70,72 82,72
                                 C 96,72 104,66 114,72
                                 C 124,78 120,93 108,93
                                 C 92,94 62,95 48,93 Z"
                              fill="url(#iceCreamSwirlGrad)" stroke="#3A1B14" stroke-width="2.5" stroke-linejoin="round" />

                        <!-- Capa Superior 1 con el Pico Curvado -->
                        <path d="M 58,70
                                 C 50,68 54,48 68,44
                                 C 76,40 84,32 82,24
                                 C 81,18 76,20 74,22
                                 C 72,24 68,26 68,22
                                 C 68,16 78,12 86,16
                                 C 94,20 98,34 94,44
                                 C 104,50 108,68 96,70
                                 C 86,72 68,72 58,70 Z"
                              fill="url(#iceCreamSwirlGrad)" stroke="#3A1B14" stroke-width="2.5" stroke-linejoin="round" />

                        <!-- Líneas de pliegue y volumen en rosa vibrante -->
                        <path d="M 52,86 C 64,88 85,86 102,84" stroke="#ED2866" stroke-width="2.2" stroke-linecap="round" fill="none" />
                        <path d="M 60,64 C 70,66 84,65 96,62" stroke="#ED2866" stroke-width="2.2" stroke-linecap="round" fill="none" />
                        <path d="M 72,42 C 78,44 86,43 92,38" stroke="#ED2866" stroke-width="2.2" stroke-linecap="round" fill="none" />

                        <!-- Brillos de luz blanca en el helado -->
                        <path d="M 44,98 C 42,94 44,88 48,87" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" fill="none" />
                        <path d="M 54,74 C 52,70 54,66 58,65" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" fill="none" />
                        <path d="M 72,28 C 76,28 80,32 80,36" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" fill="none" />

                        <!-- Chispitas en el helado -->
                        <rect x="55" y="96" width="6" height="2.5" rx="1.2" fill="#58D3E6" transform="rotate(20 55 96)" />
                        <rect x="75" y="102" width="6" height="2.5" rx="1.2" fill="#FFD152" transform="rotate(-30 75 102)" />
                        <rect x="98" y="98" width="6" height="2.5" rx="1.2" fill="#FFFFFF" transform="rotate(45 98 98)" />
                        <rect x="65" y="74" width="6" height="2.5" rx="1.2" fill="#FFFFFF" transform="rotate(-15 65 74)" />
                        <rect x="88" y="76" width="6" height="2.5" rx="1.2" fill="#58D3E6" transform="rotate(35 88 76)" />
                        <rect x="78" y="52" width="5" height="2" rx="1" fill="#FFD152" transform="rotate(-25 78 52)" />
                    </svg>
                </div>

                <!-- Lema y Pastilla de Helados Artesanales -->
                <div class="mt-3 flex flex-col items-center">
                    <h2 class="text-white font-extrabold text-xl sm:text-2xl tracking-tight mb-2.5 drop-shadow-md font-heading">
                        El sistema para tu negocio
                    </h2>


                    <p class="text-pink-100 font-extrabold text-sm mt-2 tracking-wide drop-shadow-xs">
                        Boutique &amp; Pastelería
                    </p>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- PANEL DERECHO: FORMULARIO LIMPIO EN FONDO BLANCO PURO    -->
        <!-- Abarca todo su espacio sin tarjeta superpuesta           -->
        <!-- ======================================================== -->
        <div id="login-panel" class="lg:col-span-7 bg-white relative flex flex-col justify-center items-center p-6 sm:p-12 lg:p-16 min-h-screen">

            <!-- CONTENEDOR CENTRAL: FORMULARIO INTEGRADO DIRECTAMENTE AL FONDO BLANCO -->
            <div id="login-form-container" class="w-full max-w-md mx-auto my-auto py-6">

                <!-- Encabezado del Formulario -->
                <div class="text-center mb-8">
                    <!-- Título llamativo con estrellas decorativas -->
                    <div class="inline-block relative">
                        <!-- Estrella decorativa izquierda -->
                        <svg class="absolute -top-2.5 -left-5 sm:-left-7 w-5 h-5 sm:w-6 sm:h-6 text-[#FF4D80] animate-bounce" style="animation-duration: 2.8s;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0L14.2 9.8L24 12L14.2 14.2L12 24L9.8 14.2L0 12L9.8 9.8L12 0Z"/>
                        </svg>

                        <!-- Estrella decorativa derecha -->
                        <svg class="absolute -top-2.5 -right-5 sm:-right-7 w-6 h-6 sm:w-7 sm:h-7 text-[#ED2866] animate-pulse" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0L14.2 9.8L24 12L14.2 14.2L12 24L9.8 14.2L0 12L9.8 9.8L12 0Z"/>
                        </svg>

                        <h2 class="text-3xl sm:text-4xl lg:text-[2.65rem] font-black tracking-tight font-heading leading-tight">
                            <span class="text-[#251d22]">Iniciar </span>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#ED2866] via-[#FF4D80] to-[#E0265E] drop-shadow-xs">Sesión</span>
                        </h2>

                        <!-- Ondita decorativa de glaseado pastel con perlas -->
                        <div class="flex items-center justify-center gap-1.5 mt-1.5">
                            <span class="h-0.5 w-6 bg-gradient-to-r from-transparent to-[#FF4D80]/50 rounded-full"></span>
                            <svg class="w-36 sm:w-44 h-3.5 drop-shadow-xs" viewBox="0 0 140 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M 4,6 C 22,1 36,11 52,6 C 68,1 82,11 98,6 C 112,1 126,10 136,6" stroke="url(#frostingWaveGrad)" stroke-width="3.5" stroke-linecap="round"/>
                                <circle cx="70" cy="6" r="2.5" fill="#ED2866" />
                                <circle cx="34" cy="9" r="1.8" fill="#FFB3C7" />
                                <circle cx="106" cy="9" r="1.8" fill="#FFB3C7" />
                                <defs>
                                    <linearGradient id="frostingWaveGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#FF7098"/>
                                        <stop offset="50%" stop-color="#ED2866"/>
                                        <stop offset="100%" stop-color="#FF4D80"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <span class="h-0.5 w-6 bg-gradient-to-l from-transparent to-[#FF4D80]/50 rounded-full"></span>
                        </div>
                    </div>

                    <p class="text-[#6c5f68] text-xs sm:text-sm font-semibold mt-2.5">
                        Ingresa tus credenciales para acceder a la terminal
                    </p>
                </div>

                <!-- Alerta de Error -->
                <?php if (!empty($error)): ?>
                <div id="login-error" class="mb-6 flex items-center gap-3 bg-[#FFF0F4] border-2 border-pink-300 text-pink-800 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold shadow-sm shake-error">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 stroke-[2.5] text-[#ED2866]"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Formulario de Acceso -->
                <form method="POST" action="" class="flex flex-col gap-4 sm:gap-5" id="loginForm">

                    <!-- Campo: Usuario o Email -->
                    <div>
                        <label for="username" class="block text-xs font-black text-[#4D3A34] uppercase tracking-wider mb-2">
                            USUARIO O EMAIL
                        </label>
                        <div class="pos-input-wrapper">
                            <i data-lucide="user" class="w-5 h-5 text-[#6c5f68] shrink-0 stroke-[2]"></i>
                            <input
                                type="text"
                                name="username"
                                id="username"
                                required
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                placeholder="admin@example.com"
                                class="w-full pl-3 pr-2 text-sm font-bold text-[#251d22] placeholder-slate-400 focus:outline-none bg-transparent">
                            <button
                                type="button"
                                id="clearUsernameBtn"
                                onclick="clearUsernameInput()"
                                title="Limpiar"
                                class="text-slate-400 hover:text-slate-700 p-1 rounded-full transition-colors hidden">
                                <i data-lucide="x" class="w-4 h-4 stroke-[2.5]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Campo: Contraseña -->
                    <div>
                        <label for="password" class="block text-xs font-black text-[#4D3A34] uppercase tracking-wider mb-2">
                            CONTRASEÑA
                        </label>
                        <div class="pos-input-wrapper">
                            <i data-lucide="lock" class="w-5 h-5 text-[#6c5f68] shrink-0 stroke-[2]"></i>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                placeholder="••••••••"
                                class="w-full pl-3 pr-2 text-sm font-bold text-[#251d22] placeholder-slate-400 focus:outline-none bg-transparent">
                            <button
                                type="button"
                                id="togglePasswordBtn"
                                onclick="togglePasswordVisibility()"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black text-pink-700 bg-pink-50 hover:bg-pink-100/90 border border-pink-200/80 transition-all">
                                <span id="togglePasswordText">Mostrar</span>
                                <i data-lucide="eye" id="togglePasswordIcon" class="w-4 h-4 stroke-[2]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Fila: Mantener sesión & Olvidaste contraseña -->
                    <div class="flex items-center justify-between text-xs font-bold text-[#3F2B25] pt-0.5">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="remember" class="pos-checkbox" id="rememberMe">
                            <span>Mantener sesión iniciada</span>
                        </label>

                        <a href="javascript:void(0)" onclick="openHelpModal('password')" class="text-[#3F2B25] hover:text-[#ED2866] hover:underline transition-colors font-extrabold">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <!-- Botón Principal con Gradiente Rosado Intenso y Repisa Glaseada -->
                    <div class="btn-sweet-wrapper">
                        <button type="submit" class="btn-sweet-action group">
                            <span>Acceder al Sistema</span>
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1.5 transition-transform stroke-[2.5]"></i>
                        </button>

                        <!-- Goteo de Crema Vainilla debajo del Botón -->
                        <div class="btn-drip-shelf">
                            <svg viewBox="0 0 420 38" preserveAspectRatio="none" class="w-full h-full">
                                <path d="M 0,0 L 420,0
                                         C 400,0 395,14 382,14
                                         C 370,14 366,4 350,4
                                         C 336,4 330,22 316,22
                                         C 304,22 300,8 285,8
                                         C 272,8 266,28 250,28
                                         C 236,28 232,10 215,10
                                         C 200,10 194,26 180,26
                                         C 166,26 162,6 145,6
                                         C 132,6 128,24 112,24
                                         C 98,24 94,8 80,8
                                         C 66,8 60,18 45,18
                                         C 32,18 26,0 0,0 Z"
                                      fill="#FFF7ED" stroke="#EADBCE" stroke-width="1.2" />

                                <circle cx="250" cy="32" r="3" fill="#FFF7ED" stroke="#EADBCE" stroke-width="0.8" />
                                <circle cx="316" cy="26" r="2.5" fill="#FFF7ED" />
                                <circle cx="112" cy="28" r="2.5" fill="#FFF7ED" />
                                <circle cx="180" cy="30" r="2" fill="#ED2866" />
                            </svg>
                        </div>
                    </div>


                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL DE AYUDA Y CREDENCIALES DEL SISTEMA   -->
    <!-- ========================================== -->
    <div id="helpModal" class="fixed inset-0 bg-[#3A1B14]/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden transition-opacity">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border-2 border-pink-200 relative animate-float" style="animation-iteration-count: 1;">
            <!-- Botón Cerrar -->
            <button
                type="button"
                onclick="closeHelpModal()"
                class="absolute top-4 right-4 text-[#9c8f98] hover:text-[#251d22] p-1.5 rounded-full hover:bg-pink-50 transition-colors">
                <i data-lucide="x" class="w-5 h-5 stroke-[2.5]"></i>
            </button>

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-2xl bg-pink-100 text-[#ED2866] flex items-center justify-center font-bold">
                    <i data-lucide="info" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-[#251d22] font-heading">Sweet POS Terminal</h3>
                    <p class="text-xs text-[#6c5f68] font-semibold">Información del Sistema</p>
                </div>
            </div>

            <div class="space-y-3 text-xs text-[#251d22] mb-6">
                <div class="p-3.5 rounded-2xl bg-[#FFF2F6] border border-pink-200">
                    <p class="font-extrabold text-sm mb-1 text-[#251d22]">🔑 Cuentas de Acceso:</p>
                    <ul class="space-y-1.5 mt-2">
                        <li class="flex items-center justify-between">
                            <span class="font-bold">Administrador:</span>
                            <code class="bg-white px-2 py-0.5 rounded border border-pink-200 font-mono text-[#ED2866] font-bold">admin</code>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="font-bold">Gerente de Tienda:</span>
                            <code class="bg-white px-2 py-0.5 rounded border border-pink-200 font-mono text-[#ED2866] font-bold">gerente</code>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="font-bold">Vendedor / Cajero:</span>
                            <code class="bg-white px-2 py-0.5 rounded border border-pink-200 font-mono text-[#ED2866] font-bold">vendedor</code>
                        </li>
                    </ul>
                </div>

                <p class="text-[11px] text-[#6c5f68]">
                    Para restablecer accesos o cambiar contraseñas, consulta con el administrador técnico o las variables del entorno del sistema.
                </p>
            </div>

            <button
                type="button"
                onclick="closeHelpModal()"
                class="w-full py-2.5 rounded-xl bg-gradient-to-r from-pink-600 to-rose-600 hover:from-pink-500 hover:to-rose-500 text-white font-extrabold text-xs transition-all shadow-md">
                Entendido
            </button>
        </div>
    </div>

    <!-- Scripts de Interacción -->
    <script>
        // Inicializar Iconos de Lucide
        lucide.createIcons();

        // Control de Visibilidad de Contraseña
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            const toggleText = document.getElementById('togglePasswordText');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleText.textContent = 'Ocultar';
                toggleIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                toggleText.textContent = 'Mostrar';
                toggleIcon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        // Botón para limpiar campo de usuario
        const usernameInput = document.getElementById('username');
        const clearBtn = document.getElementById('clearUsernameBtn');

        function checkUsernameInput() {
            if (usernameInput && clearBtn) {
                if (usernameInput.value.length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
        }

        function clearUsernameInput() {
            if (usernameInput) {
                usernameInput.value = '';
                checkUsernameInput();
                usernameInput.focus();
            }
        }

        if (usernameInput) {
            usernameInput.addEventListener('input', checkUsernameInput);
            checkUsernameInput();
        }

        // Modal de Ayuda (invocado al hacer clic en "¿Olvidaste tu contraseña?")
        function openHelpModal(context) {
            document.getElementById('helpModal').classList.remove('hidden');
        }

        function closeHelpModal() {
            document.getElementById('helpModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera del contenido
        document.getElementById('helpModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHelpModal();
            }
        });

        // Splash móvil: reutiliza el hero original y luego deja solo el formulario.
        (function setupMobileLoginFlow() {
            const isMobileLayout = window.matchMedia('(max-width: 1023px)').matches;
            const form = document.getElementById('login-form-container');
            const username = document.getElementById('username');
            const hero = document.querySelector('.animated-pink-hero');
            const splashActive = document.body.classList.contains('login-splash-active');

            if (!isMobileLayout) {
                if (username) username.focus({ preventScroll: true });
                return;
            }

            if (!form) return;
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

            let finished = false;
            const revealForm = () => {
                form.classList.add('login-form-reveal');
            };

            // Error de credenciales o movimiento reducido: sin splash.
            if (!splashActive || !hero) {
                document.body.classList.remove('login-splash-active');
                revealForm();
                return;
            }

            const finish = () => {
                if (finished) return;
                finished = true;
                window.clearTimeout(timer);
                hero.classList.add('login-hero-leaving');
                window.setTimeout(() => {
                    hero.classList.remove('login-hero-leaving');
                    document.body.classList.remove('login-splash-active');
                    window.requestAnimationFrame(revealForm);
                }, 380);
            };

            const timer = window.setTimeout(finish, 2000);
            hero.addEventListener('click', finish, { once: true });
            hero.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') finish();
            });
        })();
    </script>
</body>
</html>
