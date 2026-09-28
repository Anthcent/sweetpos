<?php
// _sidebar.php — Barra de navegacion lateral compartida con control de roles
// Requiere que auth.php ya haya sido cargado en la pagina padre.
$currentPage = basename($_SERVER['PHP_SELF']);
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
        <a href="panel.php" title="Panel de decisiones" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="index.php" title="Punto de Venta" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="mesas.php" title="Zonas de Mesa" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="inventario.php" title="Inventario de Productos" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="produccion.php" title="Elaboración & Recetas" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="materiales.php" title="Insumos & Materias Primas" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
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
        <a href="historial.php" title="Historial & Ventas" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="history" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Historial</span>
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
        <a href="movimientos.php" title="Movimientos de stock" class="w-full aspect-square rounded-2xl <?= $cls ?> flex flex-col items-center justify-center transition-all group">
            <i data-lucide="arrow-left-right" class="w-5 h-5 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] tracking-tight">Movim.</span>
            <?php if ($active): ?><div class="absolute inset-y-1.5 -left-3 w-1 bg-pink-500 rounded-r-full shadow-sm"></div><?php endif; ?>
        </a>
        <?php endif; ?>

    </div>

    <!-- Acciones del pie de sidebar -->
    <div class="sidebar-actions px-3 w-full flex flex-col gap-2 mt-4">

        <!-- Boton Registrar Cliente — solo en index.php y para gerente/admin -->
        <?php if ($currentPage === 'index.php' && canAccess('clientes')): ?>
        <button
            onclick="document.getElementById('client-modal').classList.remove('hidden')"
            title="Registrar Cliente Nuevo"
            class="w-full aspect-square rounded-2xl bg-pink-500 hover:bg-pink-600 text-white flex flex-col items-center justify-center transition-all shadow-md shadow-pink-500/20 group">
            <i data-lucide="user-plus" class="w-4 h-4 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[9px] font-bold tracking-tight">Cliente</span>
        </button>
        <?php endif; ?>

        <!-- Boton Cerrar Sesion — siempre visible -->
        <a href="logout.php"
            title="Cerrar sesión (<?= htmlspecialchars(currentUser()) ?> - <?= getRoleLabel() ?>)"
            class="w-full aspect-square rounded-2xl bg-slate-100/80 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex flex-col items-center justify-center transition-all group">
            <i data-lucide="log-out" class="w-4 h-4 mb-1 group-hover:scale-110 transition-transform"></i>
            <span class="text-[9px] font-semibold tracking-tight">Salir</span>
        </a>
    </div>
</nav>
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
