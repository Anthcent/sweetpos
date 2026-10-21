<?php
// _sidebar.php — Barra de navegacion lateral compartida con control de roles
// Requiere que auth.php ya haya sido cargado en la pagina padre.
$currentPage = basename($_SERVER['PHP_SELF']);

// Módulos que se muestran en la hoja "Más" del menú móvil.
$mobileSheetModules = [];
if (canAccess('panel')) $mobileSheetModules[] = ['panel.php', 'gauge', 'Panel'];
$mobileSheetModules[] = ['index.php', 'layout-grid', 'POS'];
$mobileSheetModules[] = ['mesas.php', 'coffee', 'Mesas'];
if (canAccess('inventario')) $mobileSheetModules[] = ['inventario.php', 'package', 'Stock'];
if (canAccess('produccion')) $mobileSheetModules[] = ['produccion.php', 'chef-hat', 'Elaborar'];
if (canAccess('materiales')) $mobileSheetModules[] = ['materiales.php', 'wheat', 'Insumos'];
if (canAccess('historial')) $mobileSheetModules[] = ['historial.php', 'history', 'Historial'];
if (canAccess('reportes')) $mobileSheetModules[] = ['reportes.php', 'clipboard-list', 'Reportes'];
if (canAccess('movimientos')) $mobileSheetModules[] = ['movimientos.php', 'arrow-left-right', 'Movimientos'];
if (canAccess('usuarios')) $mobileSheetModules[] = ['usuarios.php', 'users', 'Usuarios'];
?>
<!-- Helpers compartidos (escapeHtml) disponibles para los scripts de cada pagina -->
<script src="assets/utils.js"></script>
<nav class="app-sidebar w-20 bg-white border-r border-slate-200/80 flex flex-col items-center py-6 z-10 shadow-sm relative shrink-0" aria-label="Navegación principal">

    <!-- Logo de la heladería / dulcería -->
    <div class="sidebar-brand-wrapper mb-6">
        <a href="index.php" title="Sweet POS - Inicio" class="sidebar-brand w-12 h-12 rounded-2xl flex items-center justify-center overflow-hidden block">
            <img src="assets/ice_cream_branding.png" alt="Sweet POS" class="w-full h-full object-cover">
        </a>
    </div>

    <!-- Links de navegacion -->
    <div class="sidebar-links no-scrollbar flex flex-col gap-2.5 w-full px-3 flex-1">

        <!-- Panel de decisiones — solo gerente y admin (con contador de alertas criticas) -->
        <?php if (canAccess('panel')): ?>
        <?php
        $active = $currentPage === 'panel.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600 relative';
        ?>
        <a href="panel.php" title="Panel de decisiones" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-primary flex flex-col items-center justify-center transition-all group">
            <i data-lucide="gauge" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Panel</span>
            <span id="sidebar-alert-badge" class="hidden absolute top-1 right-1 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-rose-500 text-white text-[9px] font-extrabold leading-[1.1rem] text-center shadow-sm" aria-live="polite"></span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- POS — todos los roles -->
        <?php
        $active = $currentPage === 'index.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="index.php" title="Punto de Venta" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-primary flex flex-col items-center justify-center transition-all group">
            <i data-lucide="layout-grid" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">POS</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>

        <!-- Mesas — todos los roles -->
        <?php
        $active = $currentPage === 'mesas.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="mesas.php" title="Zonas de Mesa" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-primary flex flex-col items-center justify-center transition-all group">
            <i data-lucide="coffee" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Mesas</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>

        <!-- Inventario — solo gerente y admin -->
        <?php if (canAccess('inventario')): ?>
        <?php
        $active = $currentPage === 'inventario.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="inventario.php" title="Inventario de Productos" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="package" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Stock</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Elaboracion de Productos — solo gerente y admin -->
        <?php if (canAccess('produccion')): ?>
        <?php
        $active = $currentPage === 'produccion.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="produccion.php" title="Elaboración & Recetas" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="chef-hat" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Elaborar</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Stock de Materiales — solo gerente y admin -->
        <?php if (canAccess('materiales')): ?>
        <?php
        $active = $currentPage === 'materiales.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="materiales.php" title="Insumos & Materias Primas" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="wheat" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Insumos</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Historial — solo gerente y admin -->
        <?php if (canAccess('historial')): ?>
        <?php
        $active = $currentPage === 'historial.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="historial.php" title="Historial & Ventas" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="history" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Historial</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Reportes de caja, ventas e inventario — solo gerente y admin -->
        <?php if (canAccess('reportes')): ?>
        <?php
        $active = $currentPage === 'reportes.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="reportes.php" title="Reportes" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="clipboard-list" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Reportes</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Movimientos de stock — solo gerente y admin -->
        <?php if (canAccess('movimientos')): ?>
        <?php
        $active = $currentPage === 'movimientos.php';
        $cls = $active
            ? 'bg-pink-50 text-pink-600 shadow-sm border border-pink-200/90 relative font-bold'
            : 'text-slate-400 hover:bg-pink-50/50 hover:text-pink-600';
        ?>
        <a href="movimientos.php" title="Movimientos de stock" class="w-full aspect-square rounded-2xl <?= $cls ?> mobile-secondary <?= $active ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="arrow-left-right" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Movim.</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

    </div>

    <!-- Acciones del pie de sidebar -->
    <div class="sidebar-actions px-3 w-full flex flex-col gap-2 mt-4">

        <?php if (canAccess('materiales')): ?>
        <button type="button" onclick="openRestockModal()" title="Reposición rápida" class="w-full aspect-square rounded-2xl bg-emerald-50/90 text-emerald-700 hover:bg-emerald-100 mobile-secondary flex flex-col items-center justify-center transition-all">
            <i data-lucide="package-plus" class="w-4 h-4 mb-1"></i>
            <span class="text-[9px] font-semibold">Reponer</span>
        </button>
        <?php endif; ?>

        <?php if (canAccess('usuarios')): ?>
        <a href="usuarios.php" title="Administrar usuarios" class="w-full aspect-square rounded-2xl <?= $currentPage === 'usuarios.php' ? 'bg-pink-50 text-pink-600' : 'bg-slate-100/80 text-slate-500 hover:bg-pink-50 hover:text-pink-600' ?> mobile-secondary <?= $currentPage === 'usuarios.php' ? 'mobile-current' : '' ?> flex flex-col items-center justify-center transition-all">
            <i data-lucide="users" class="w-4 h-4 mb-1"></i>
            <span class="text-[9px] font-semibold">Usuarios</span>
        </a>
        <?php endif; ?>

        <!-- Boton Registrar Cliente — solo en index.php y para gerente/admin -->
        <?php if ($currentPage === 'index.php' && canAccess('clientes')): ?>
        <button
            onclick="document.getElementById('client-modal').classList.remove('hidden')"
            title="Registrar Cliente Nuevo"
            class="w-full aspect-square rounded-2xl bg-pink-500 hover:bg-pink-600 text-white mobile-primary mobile-secondary flex flex-col items-center justify-center transition-all shadow-md shadow-pink-500/20 group">
            <i data-lucide="user-plus" class="w-4 h-4 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[9px] font-bold tracking-tight">Cliente</span>
        </button>
        <?php endif; ?>

        <!-- Boton Cerrar Sesion — siempre visible -->
        <a href="logout.php"
            title="Cerrar sesión (<?= htmlspecialchars(currentUser()) ?> - <?= getRoleLabel() ?>)"
            class="w-full aspect-square rounded-2xl bg-slate-100/80 text-slate-500 hover:bg-rose-50 hover:text-rose-600 mobile-secondary flex flex-col items-center justify-center transition-all group">
            <i data-lucide="log-out" class="w-4 h-4 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[9px] font-semibold tracking-tight">Salir</span>
        </a>

        <!-- Botón "Más" — solo móvil: abre la hoja con todos los módulos -->
        <button type="button" id="mobile-more-btn" class="mobile-more-btn" onclick="openMobileNav()" aria-haspopup="dialog" aria-controls="mobile-nav-sheet" aria-expanded="false" title="Más opciones">
            <i data-lucide="menu" class="w-5 h-5 mb-1"></i>
            <span>Más</span>
        </button>
    </div>
</nav>

<!-- Hoja de navegación móvil: todos los módulos sin saturar la barra inferior -->
<div id="mobile-nav-sheet" class="mobile-nav-sheet" aria-hidden="true" inert>
    <div id="mobile-nav-backdrop" class="mobile-nav-backdrop" onclick="closeMobileNav()"></div>
    <section id="mobile-nav-panel" class="mobile-nav-panel" role="dialog" aria-modal="true" aria-labelledby="mobile-nav-title">
        <div class="mobile-nav-head">
            <div>
                <p class="mobile-nav-eyebrow">Navegación</p>
                <h2 id="mobile-nav-title">Todos los módulos</h2>
            </div>
            <button type="button" class="mobile-nav-close" onclick="closeMobileNav()" aria-label="Cerrar menú">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="mobile-nav-grid">
            <?php foreach ($mobileSheetModules as [$href, $icon, $label]): ?>
            <a href="<?= $href ?>" class="mobile-nav-link <?= $currentPage === $href ? 'is-active' : '' ?>" onclick="closeMobileNav()">
                <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                <span><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="mobile-nav-actions">
            <?php if ($currentPage === 'index.php' && canAccess('clientes')): ?>
            <button type="button" class="mobile-nav-action" onclick="closeMobileNav(); document.getElementById('client-modal').classList.remove('hidden')">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                <span>Registrar cliente</span>
            </button>
            <?php endif; ?>
            <?php if (canAccess('materiales')): ?>
            <button type="button" class="mobile-nav-action" onclick="closeMobileNav(); openRestockModal()">
                <i data-lucide="package-plus" class="w-5 h-5"></i>
                <span>Reposición rápida</span>
            </button>
            <?php endif; ?>
            <a href="logout.php" class="mobile-nav-action mobile-nav-logout">
                <i data-lucide="log-out" class="w-5 h-5"></i>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </section>
</div>
<script>
(function () {
    const sheet = document.getElementById('mobile-nav-sheet');
    const moreBtn = document.getElementById('mobile-more-btn');
    if (!sheet || !moreBtn) return;

    let lastFocused = null;
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function openMobileNav() {
        lastFocused = document.activeElement;
        sheet.inert = false;
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        moreBtn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('mobile-nav-open');
        const first = sheet.querySelector(focusableSelector);
        if (first) window.setTimeout(() => first.focus(), 60);
    }

    function closeMobileNav() {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        sheet.inert = true;
        moreBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('mobile-nav-open');
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    window.openMobileNav = openMobileNav;
    window.closeMobileNav = closeMobileNav;

    // If the viewport grows to desktop while the sheet is open, restore the page.
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768 && sheet.classList.contains('is-open')) closeMobileNav();
    });

    document.addEventListener('keydown', function (event) {
        if (!sheet.classList.contains('is-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMobileNav();
            return;
        }
        if (event.key !== 'Tab') return;
        const items = Array.from(sheet.querySelectorAll(focusableSelector)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
</script>

<?php require __DIR__ . '/_restock_modal.php'; ?>

<?php if (canAccess('panel')): ?>
<script>
// Critical alert badge. The count is cached in sessionStorage for 60s so moving
// between pages does not recompute the insights; panel.php refreshes it directly.
(function () {
    const TTL_MS = 60000;
    const KEY = 'sweetpos.criticalAlerts';
    const badge = document.getElementById('sidebar-alert-badge');
    if (!badge) return;

    window.setSidebarAlertCount = function (count) {
        try { sessionStorage.setItem(KEY, JSON.stringify({ count, at: Date.now() })); } catch (e) {}
        const n = Number(count) || 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.title = n + ' alerta(s) crítica(s)';
        badge.classList.toggle('hidden', n === 0);
    };

    async function refresh() {
        let cached = null;
        try { cached = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) {}
        if (cached && Date.now() - cached.at < TTL_MS) {
            window.setSidebarAlertCount(cached.count);
            return;
        }
        if (document.hidden) return;
        try {
            const res = await fetch('api/insights.php?count=1');
            const data = await res.json();
            if (res.ok) window.setSidebarAlertCount(data.critical_alerts);
        } catch (e) { /* badge is optional */ }
    }

    <?php if ($currentPage !== 'panel.php'): ?>
    refresh();
    setInterval(refresh, TTL_MS);
    <?php else: ?>
    // panel.php calls setSidebarAlertCount() with its own data; show the cached value meanwhile.
    try {
        const cached = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        if (cached) window.setSidebarAlertCount(cached.count);
    } catch (e) {}
    <?php endif; ?>
})();
</script>
<?php endif; ?>
