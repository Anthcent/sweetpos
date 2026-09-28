<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de decisiones - Sweet POS</title>
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
</head>
<body class="h-screen overflow-hidden flex text-slate-800">

    <?php require '_sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <div class="absolute top-0 right-0 w-[26rem] h-[26rem] bg-pink-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

        <header class="min-h-[4rem] sm:min-h-[4.5rem] py-2.5 px-3.5 sm:px-8 flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-2.5 sm:gap-3">
            <div class="flex items-center justify-between w-full sm:w-auto gap-2.5 sm:gap-3">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                        <i data-lucide="gauge" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                    </div>
                    <div>
                        <h1 class="text-base sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Panel de decisiones</h1>
                        <p class="text-[10px] sm:text-xs text-slate-500 font-medium">Qué elaborar y comprar según ventas (30 días)</p>
                    </div>
                </div>
                <div class="sm:hidden flex items-center gap-1.5 shrink-0">
                    <span class="text-[9px] px-2 py-0.5 rounded-full font-bold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                <div class="flex items-center gap-2 bg-white sm:bg-transparent px-2.5 py-1 sm:p-0 rounded-xl border sm:border-0 border-slate-200/80 shadow-2xs sm:shadow-none flex-1 sm:flex-initial">
                    <label for="target-days" class="text-xs font-bold text-slate-500 shrink-0">Cubrir:</label>
                    <select id="target-days" class="w-full sm:w-auto px-2 sm:px-3 py-1.5 sm:py-2 bg-transparent sm:bg-white border-0 sm:border border-slate-200 rounded-xl text-xs sm:text-sm font-bold focus:outline-none focus:ring-2 focus:ring-pink-300">
                        <option value="1">1 día</option>
                        <option value="2">2 días</option>
                        <option value="3" selected>3 días</option>
                        <option value="5">5 días</option>
                        <option value="7">7 días</option>
                        <option value="14">14 días</option>
                    </select>
                </div>
                <button type="button" onclick="loadInsights()" title="Actualizar datos" class="p-2 sm:p-2.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-pink-600 hover:border-pink-200 transition-colors shrink-0 shadow-2xs">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
                <div class="hidden xl:flex flex-col items-end pl-2 border-l border-slate-200/80">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 lg:p-8 z-10 relative space-y-4 sm:space-y-6 pb-32 sm:pb-12">
            <div id="panel-error" class="hidden text-sm font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3"></div>

            <!-- KPI tiles -->
            <section id="kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4" aria-label="Indicadores">
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 p-3.5 sm:p-4 min-h-[5.5rem] animate-pulse"></div>
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 p-3.5 sm:p-4 min-h-[5.5rem] animate-pulse"></div>
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 p-3.5 sm:p-4 min-h-[5.5rem] animate-pulse"></div>
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 p-3.5 sm:p-4 min-h-[5.5rem] animate-pulse"></div>
            </section>

            <!-- Alerts -->
            <section class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h2 class="font-extrabold text-slate-800 font-heading text-sm sm:text-base flex items-center gap-2">
                        <i data-lucide="bell-ring" class="w-4 h-4 text-pink-500"></i> Alertas priorizadas
                    </h2>
                    <span id="alerts-count" class="text-[10px] sm:text-[11px] font-bold text-slate-400"></span>
                </div>
                <ul id="alerts-list" class="divide-y divide-slate-100/80">
                    <li class="px-4 sm:px-6 py-6 sm:py-8 text-center text-slate-400 text-xs sm:text-sm font-medium">Calculando...</li>
                </ul>
            </section>

            <!-- Products -->
            <section class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h2 class="font-extrabold text-slate-800 font-heading text-sm sm:text-base flex items-center gap-2">
                        <i data-lucide="ice-cream-cone" class="w-4 h-4 text-pink-500"></i> Productos por ventas
                    </h2>
                    <span id="products-count" class="text-[10px] sm:text-[11px] font-bold text-slate-400"></span>
                </div>

                <!-- Vista Móvil: Tarjetas compactas (< md) -->
                <div id="products-cards" class="md:hidden divide-y divide-slate-100">
                    <div class="text-center py-8 text-slate-400 font-medium text-xs">Cargando productos...</div>
                </div>

                <!-- Vista Escritorio: Tabla completa (>= md) -->
                <div class="hidden md:block overflow-x-auto w-full">
                    <table class="w-full min-w-[760px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 py-3 font-bold">#</th>
                                <th class="px-5 py-3 font-bold">Producto</th>
                                <th class="px-5 py-3 font-bold text-right">Vendido 7d</th>
                                <th class="px-5 py-3 font-bold text-right">Vendido 30d</th>
                                <th class="px-5 py-3 font-bold text-right">Stock</th>
                                <th class="px-5 py-3 font-bold text-right">Cobertura</th>
                                <th class="px-5 py-3 font-bold text-right">Se puede elaborar</th>
                                <th class="px-5 py-3 font-bold text-right">Sugerido</th>
                                <th class="px-5 py-3 font-bold">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="products-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="9" class="text-center py-10 text-slate-400 font-medium">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Materials at risk -->
            <section class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h2 class="font-extrabold text-slate-800 font-heading text-sm sm:text-base flex items-center gap-2">
                        <i data-lucide="wheat" class="w-4 h-4 text-amber-600"></i> Insumos en riesgo
                    </h2>
                    <span id="materials-count" class="text-[10px] sm:text-[11px] font-bold text-slate-400"></span>
                </div>

                <!-- Vista Móvil: Tarjetas compactas (< md) -->
                <div id="materials-cards" class="md:hidden divide-y divide-slate-100">
                    <div class="text-center py-8 text-slate-400 font-medium text-xs">Cargando insumos...</div>
                </div>

                <!-- Vista Escritorio: Tabla completa (>= md) -->
                <div class="hidden md:block overflow-x-auto w-full">
                    <table class="w-full min-w-[720px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 py-3 font-bold">Insumo</th>
                                <th class="px-5 py-3 font-bold text-right">Stock</th>
                                <th class="px-5 py-3 font-bold text-right">Mínimo</th>
                                <th class="px-5 py-3 font-bold text-right">Consumo/día</th>
                                <th class="px-5 py-3 font-bold text-right">Cobertura</th>
                                <th class="px-5 py-3 font-bold">Bloquea</th>
                                <th class="px-5 py-3 font-bold">Estado</th>
                                <th class="px-5 py-3 font-bold text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="materials-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="8" class="text-center py-10 text-slate-400 font-medium">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p id="panel-footnote" class="text-[10px] sm:text-[11px] text-slate-400 font-medium pb-2 text-center sm:text-left"></p>
        </div>
    </main>

    <script>
        const STATUS_STYLES = {
            agotado: ['Agotado', 'bg-rose-100 text-rose-700 border-rose-200'],
            critico: ['Crítico', 'bg-orange-100 text-orange-700 border-orange-200'],
            bajo:    ['Bajo', 'bg-amber-100 text-amber-800 border-amber-200'],
            ok:      ['OK', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        };
        const SEVERITY_STYLES = {
            critico: ['Crítico', 'bg-rose-500', 'text-rose-700'],
            alto:    ['Alto', 'bg-orange-400', 'text-orange-700'],
            medio:   ['Medio', 'bg-amber-300', 'text-amber-700'],
        };
        const TARGET_KEY = 'sweetpos.panel.targetDays';

        const money = n => '$' + Number(n || 0).toFixed(2);
        const num = (n, digits = 2) => Number(n || 0).toLocaleString('es-VE', { maximumFractionDigits: digits });
        const days = d => d === null || d === undefined ? '—' : (Number(d) >= 999 ? '999+ d' : num(d, 1) + ' d');

        function statusPill(status) {
            const [label, cls] = STATUS_STYLES[status] || STATUS_STYLES.ok;
            return `<span class="text-[10px] sm:text-[11px] font-bold px-2 sm:px-2.5 py-0.5 rounded-full border ${cls} shrink-0">${label}</span>`;
        }

        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            const select = document.getElementById('target-days');
            try {
                const saved = localStorage.getItem(TARGET_KEY);
                if (saved && select.querySelector(`option[value="${Number(saved)}"]`)) select.value = String(Number(saved));
            } catch (e) {}
            select.addEventListener('change', () => {
                try { localStorage.setItem(TARGET_KEY, select.value); } catch (e) {}
                loadInsights();
            });
            loadInsights();
        });

        async function loadInsights() {
            const targetDays = document.getElementById('target-days').value;
            const errBox = document.getElementById('panel-error');
            try {
                const res = await fetch(`api/insights.php?target_days=${encodeURIComponent(targetDays)}`);
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'No se pudo cargar el panel.');
                errBox.classList.add('hidden');
                renderKpis(data);
                renderAlerts(data.alerts);
                renderProducts(data.products);
                renderMaterials(data.materials);
                document.getElementById('panel-footnote').textContent =
                    `Promedio diario calculado sobre ${data.window_days} día(s) de ventas pagadas y pendientes (las anuladas no cuentan). ` +
                    `Actualizado: ${new Date(data.generated_at).toLocaleString('es-VE')}.`;
                if (window.setSidebarAlertCount) window.setSidebarAlertCount(data.kpis.critical_alerts);
                lucide.createIcons();
            } catch (e) {
                errBox.textContent = e.message || 'No se pudo cargar el panel.';
                errBox.classList.remove('hidden');
            }
        }

        function sparkline(points) {
            const values = points.map(p => Number(p.total) || 0);
            const max = Math.max(...values, 0);
            if (max <= 0) return '';
            const w = 120, h = 32, step = w / Math.max(1, values.length - 1);
            const coords = values.map((v, i) => `${(i * step).toFixed(1)},${(h - 2 - (v / max) * (h - 4)).toFixed(1)}`);
            return `<svg viewBox="0 0 ${w} ${h}" class="w-full h-7 sm:h-8 mt-1" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="${coords.join(' ')}" fill="none" stroke="#ec4899" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>`;
        }

        function kpiTile(icon, label, value, sub, extra = '', tone = 'text-slate-900') {
            return `
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-card border border-slate-200/90 p-3.5 sm:p-4 flex flex-col justify-between min-h-[5.5rem] sm:min-h-[6.5rem]">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 sm:gap-1.5 truncate">
                            <i data-lucide="${icon}" class="w-3.5 h-3.5 text-pink-500 shrink-0"></i> ${label}
                        </span>
                        <span class="text-xl sm:text-2xl font-extrabold font-heading mt-1 block truncate ${tone}">${value}</span>
                    </div>
                    <div class="mt-1">
                        <span class="text-[11px] text-slate-500 font-medium block truncate">${sub}</span>
                        ${extra}
                    </div>
                </div>`;
        }

        function renderKpis(data) {
            const k = data.kpis;
            const toProduce = data.products.filter(p => p.suggested_production > 0).length;
            document.getElementById('kpis').innerHTML =
                kpiTile('wallet', 'Ventas de hoy', money(k.sales_today), `${k.orders_today} orden(es)`) +
                kpiTile('trending-up', 'Ventas 7 días', money(k.sales_7d), k.sales_7d > 0 ? 'Últimos 14 días:' : 'Sin ventas registradas', sparkline(k.sparkline)) +
                kpiTile('siren', 'Alertas críticas', k.critical_alerts, `${k.total_alerts} alerta(s) en total`, '',
                        k.critical_alerts > 0 ? 'text-rose-600' : 'text-emerald-600') +
                kpiTile('chef-hat', 'Por elaborar', toProduce, `para cubrir ${data.target_days} día(s)`);
        }

        function alertAction(action) {
            if (!action) return '';
            if (action.type === 'produce') {
                return `<a href="produccion.php?produce=${Number(action.product_id)}&qty=${Number(action.quantity)}"
                           class="btn-sweet-accent text-white px-3 sm:px-3.5 py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm w-full sm:w-auto shrink-0">
                            <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar ${Number(action.quantity)}
                        </a>`;
            }
            if (action.type === 'purchase') {
                return `<a href="materiales.php?purchase=${Number(action.material_id)}&qty=${Number(action.quantity)}"
                           class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 sm:px-3.5 py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm w-full sm:w-auto shrink-0 transition-colors">
                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Registrar compra
                        </a>`;
            }
            return `<a href="inventario.php"
                       class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 sm:px-3.5 py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 transition-colors">
                        <i data-lucide="package" class="w-3.5 h-3.5"></i> Ver inventario
                    </a>`;
        }

        function renderAlerts(alerts) {
            const list = document.getElementById('alerts-list');
            document.getElementById('alerts-count').textContent = alerts.length ? `${alerts.length} alerta(s)` : '';
            if (!alerts || !alerts.length) {
                list.innerHTML = `<li class="px-4 sm:px-6 py-6 text-center text-emerald-700 text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 bg-emerald-50/40">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0"></i> Todo en orden: no hay alertas para el objetivo elegido.</li>`;
                return;
            }
            list.innerHTML = alerts.map(a => {
                const [label, dot, text] = SEVERITY_STYLES[a.severity] || SEVERITY_STYLES.medio;
                return `
                    <li class="px-4 sm:px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-start gap-2.5 flex-1 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full ${dot} shrink-0 mt-1" aria-hidden="true"></span>
                            <div class="flex-1 min-w-0">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider ${text}">${label}</span>
                                <p class="text-xs sm:text-sm text-slate-700 font-medium leading-snug break-words mt-0.5">${escapeHtml(a.message)}</p>
                            </div>
                        </div>
                        <div class="w-full sm:w-auto pl-5 sm:pl-0 shrink-0">
                            ${alertAction(a.action)}
                        </div>
                    </li>`;
            }).join('');
        }

        function renderProducts(products) {
            const table = document.getElementById('products-table');
            const cards = document.getElementById('products-cards');
            const countEl = document.getElementById('products-count');
            if (countEl) countEl.textContent = products.length ? `${products.length} producto(s)` : '';

            if (!products.length) {
                const emptyMsg = '<div class="text-center py-8 text-slate-400 font-medium text-xs sm:text-sm">No hay productos activos.</div>';
                if (cards) cards.innerHTML = emptyMsg;
                if (table) table.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-slate-400 font-medium">No hay productos activos.</td></tr>';
                return;
            }

            // Desktop table
            if (table) {
                table.innerHTML = products.map(p => `
                    <tr class="hover:bg-pink-50/30 transition-colors">
                        <td class="px-5 py-3 text-xs font-extrabold text-slate-400 font-heading">${p.sold_30d > 0 ? Number(p.rank) : '—'}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 border border-slate-300" style="background:${safeColor(p.image_color)}"></span>
                                <span class="font-bold text-slate-800 font-heading">${escapeHtml(p.name)}</span>
                                ${p.is_top_seller ? '<i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-300" title="Top ventas"></i>' : ''}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold">${Number(p.sold_7d)}</td>
                        <td class="px-5 py-3 text-right font-semibold">${Number(p.sold_30d)} <span class="text-[10px] text-slate-400">(${num(p.avg_daily)}/d)</span></td>
                        <td class="px-5 py-3 text-right font-bold">${Number(p.stock)}</td>
                        <td class="px-5 py-3 text-right">${days(p.coverage_days)}</td>
                        <td class="px-5 py-3 text-right">${p.can_make === null ? '<span class="text-[11px] text-slate-400">Sin receta</span>' : Number(p.can_make)}</td>
                        <td class="px-5 py-3 text-right">
                            ${p.suggested_production > 0
                                ? `<a href="produccion.php?produce=${Number(p.id)}&qty=${Number(p.suggested_production)}" class="font-extrabold text-pink-600 hover:underline">${Number(p.suggested_production)}</a>`
                                : '<span class="text-slate-300">0</span>'}
                            ${p.need > p.suggested_production && p.has_recipe ? `<span class="block text-[10px] text-rose-500 font-semibold">faltan insumos para ${Number(p.need - p.suggested_production)}</span>` : ''}
                        </td>
                        <td class="px-5 py-3">${statusPill(p.status)}</td>
                    </tr>`).join('');
            }

            // Mobile cards
            if (cards) {
                cards.innerHTML = products.map(p => `
                    <div class="p-4 flex flex-col gap-3 hover:bg-pink-50/20 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-xs font-black text-slate-400 font-heading shrink-0">#${p.sold_30d > 0 ? Number(p.rank) : '—'}</span>
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 border border-slate-300 shadow-2xs" style="background:${safeColor(p.image_color)}"></span>
                                <h3 class="font-bold text-slate-800 text-sm font-heading truncate">${escapeHtml(p.name)}</h3>
                                ${p.is_top_seller ? '<i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-300 shrink-0" title="Top ventas"></i>' : ''}
                            </div>
                            <div class="shrink-0">
                                ${statusPill(p.status)}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-slate-50/90 p-2.5 rounded-xl border border-slate-200/70 text-center">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Stock</span>
                                <span class="font-black text-slate-800 text-sm mt-0.5">${Number(p.stock)}</span>
                            </div>
                            <div class="flex flex-col border-x border-slate-200/60 px-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Cobertura</span>
                                <span class="font-black text-slate-700 text-sm mt-0.5">${days(p.coverage_days)}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Ventas 7d / 30d</span>
                                <span class="font-bold text-slate-700 text-xs mt-0.5">${Number(p.sold_7d)} <span class="text-slate-400 font-normal">/</span> ${Number(p.sold_30d)}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-0.5 text-xs">
                            <span class="text-slate-600 text-xs font-medium">
                                Elaborables: <strong class="text-slate-800 font-bold">${p.can_make === null ? 'Sin receta' : Number(p.can_make)}</strong>
                            </span>
                            ${p.suggested_production > 0
                                ? `<a href="produccion.php?produce=${Number(p.id)}&qty=${Number(p.suggested_production)}"
                                      class="btn-sweet-accent text-white px-3 py-1.5 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-xs">
                                      <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar ${Number(p.suggested_production)}
                                   </a>`
                                : '<span class="text-xs text-slate-400 font-semibold bg-slate-100/80 px-2 py-0.5 rounded-lg">Sin faltante</span>'
                            }
                        </div>
                        ${p.need > p.suggested_production && p.has_recipe
                            ? `<div class="bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5">
                                  <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-500 shrink-0"></i>
                                  <span>Faltan insumos para elaborar ${Number(p.need - p.suggested_production)} unidad(es)</span>
                               </div>`
                            : ''
                        }
                    </div>
                `).join('');
            }
        }

        function renderMaterials(materials) {
            const table = document.getElementById('materials-table');
            const cards = document.getElementById('materials-cards');
            const countEl = document.getElementById('materials-count');

            const atRisk = (materials || [])
                .filter(m => m.status !== 'ok' || (m.blocks && m.blocks.length))
                .sort((a, b) => ((b.blocks && b.blocks.length) ? 1 : 0) - ((a.blocks && a.blocks.length) ? 1 : 0)
                             || (a.coverage_days ?? Infinity) - (b.coverage_days ?? Infinity));

            if (countEl) countEl.textContent = atRisk.length ? `${atRisk.length} en riesgo` : '0 en riesgo';

            if (!atRisk.length) {
                const emptyMsg = '<div class="text-center py-6 text-emerald-700 font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 bg-emerald-50/40"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0"></i> Ningún insumo en riesgo de agotarse.</div>';
                if (cards) cards.innerHTML = emptyMsg;
                if (table) table.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-emerald-700 font-semibold bg-emerald-50/40">Ningún insumo en riesgo de agotarse.</td></tr>';
                return;
            }

            // Desktop table
            if (table) {
                table.innerHTML = atRisk.map(m => `
                    <tr class="hover:bg-pink-50/30 transition-colors">
                        <td class="px-5 py-3 font-bold text-slate-800 font-heading">${escapeHtml(m.name)}
                            ${m.used_by === 0 ? '<span class="block text-[10px] text-slate-400 font-medium">Sin recetas</span>' : ''}</td>
                        <td class="px-5 py-3 text-right font-bold">${num(m.stock)} <span class="text-[10px] text-slate-400">${escapeHtml(m.unit)}</span></td>
                        <td class="px-5 py-3 text-right text-slate-500">${num(m.min_stock)}</td>
                        <td class="px-5 py-3 text-right">${num(m.daily_consumption, 3)}</td>
                        <td class="px-5 py-3 text-right">${days(m.coverage_days)}</td>
                        <td class="px-5 py-3 text-xs">${m.blocks && m.blocks.length
                            ? m.blocks.map(b => `<span class="inline-block mr-1 mb-0.5 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-semibold">#${Number(b.rank)} ${escapeHtml(b.name)}</span>`).join('')
                            : '<span class="text-slate-300">—</span>'}</td>
                        <td class="px-5 py-3">${statusPill(m.status)}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="materiales.php?purchase=${Number(m.id)}&qty=${Number(m.suggested_purchase)}" title="Registrar compra"
                               class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-800 px-2.5 py-1.5 rounded-xl hover:bg-emerald-50 transition-colors">
                                <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> ${m.suggested_purchase > 0 ? num(m.suggested_purchase) : 'Comprar'}
                            </a>
                        </td>
                    </tr>`).join('');
            }

            // Mobile cards
            if (cards) {
                cards.innerHTML = atRisk.map(m => `
                    <div class="p-4 flex flex-col gap-3 hover:bg-pink-50/20 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-800 text-sm font-heading truncate">${escapeHtml(m.name)}</h3>
                                ${m.used_by === 0 ? '<span class="text-[10px] text-slate-400 font-medium">Sin recetas vinculadas</span>' : ''}
                            </div>
                            <div class="shrink-0">
                                ${statusPill(m.status)}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-slate-50/90 p-2.5 rounded-xl border border-slate-200/70 text-center">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Stock Actual</span>
                                <span class="font-black text-slate-800 text-sm mt-0.5">${num(m.stock)} <span class="text-[10px] font-normal text-slate-500">${escapeHtml(m.unit)}</span></span>
                            </div>
                            <div class="flex flex-col border-x border-slate-200/60 px-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Mínimo</span>
                                <span class="font-bold text-slate-700 text-xs mt-0.5">${num(m.min_stock)}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Cobertura</span>
                                <span class="font-black text-slate-700 text-sm mt-0.5">${days(m.coverage_days)}</span>
                            </div>
                        </div>

                        ${m.blocks && m.blocks.length ? `
                            <div class="text-xs text-slate-600 bg-rose-50/60 p-2 rounded-xl border border-rose-100">
                                <span class="text-rose-800 font-bold block mb-1 text-[11px]">Bloquea elaboración de:</span>
                                <div class="flex flex-wrap gap-1">
                                    ${m.blocks.map(b => `<span class="px-2 py-0.5 rounded-full bg-white text-rose-700 border border-rose-200 font-bold text-[10px]">#${Number(b.rank)} ${escapeHtml(b.name)}</span>`).join('')}
                                </div>
                            </div>
                        ` : ''}

                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <span class="text-xs text-slate-500 font-medium">
                                Consumo: <strong class="text-slate-700 font-bold">${num(m.daily_consumption, 3)}/d</strong>
                            </span>
                            <a href="materiales.php?purchase=${Number(m.id)}&qty=${Number(m.suggested_purchase)}"
                               class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-xs transition-colors">
                                <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> ${m.suggested_purchase > 0 ? 'Comprar ' + num(m.suggested_purchase) : 'Comprar'}
                            </a>
                        </div>
                    </div>
                `).join('');
            }
        }
    </script>
</body>
</html>
