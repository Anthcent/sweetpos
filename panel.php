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

        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="gauge" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Panel de decisiones</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Qué elaborar y qué comprar, según las ventas de los últimos 30 días</p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <label for="target-days" class="text-xs font-bold text-slate-500">Cubrir</label>
                <select id="target-days" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-pink-300">
                    <option value="1">1 día</option>
                    <option value="2">2 días</option>
                    <option value="3" selected>3 días</option>
                    <option value="5">5 días</option>
                    <option value="7">7 días</option>
                    <option value="14">14 días</option>
                </select>
                <button type="button" onclick="loadInsights()" title="Actualizar" class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-pink-600 hover:border-pink-200 transition-colors">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
                <div class="hidden xl:flex flex-col items-end pl-2 border-l border-slate-200/80">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative flex flex-col gap-5">
            <div id="panel-error" class="hidden text-sm font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3"></div>

            <!-- KPI tiles -->
            <section id="kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4" aria-label="Indicadores">
                <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 p-4 h-24 animate-pulse"></div>
                <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 p-4 h-24 animate-pulse"></div>
                <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 p-4 h-24 animate-pulse"></div>
                <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 p-4 h-24 animate-pulse"></div>
            </section>

            <!-- Alerts -->
            <section class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-5 sm:px-6 py-3.5 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-extrabold text-slate-800 font-heading flex items-center gap-2">
                        <i data-lucide="bell-ring" class="w-4 h-4 text-pink-500"></i> Alertas priorizadas
                    </h2>
                    <span id="alerts-count" class="text-[11px] font-bold text-slate-400"></span>
                </div>
                <ul id="alerts-list" class="divide-y divide-slate-100/80">
                    <li class="px-6 py-8 text-center text-slate-400 text-sm font-medium">Calculando...</li>
                </ul>
            </section>

            <!-- Products -->
            <section class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-5 sm:px-6 py-3.5 border-b border-slate-100">
                    <h2 class="font-extrabold text-slate-800 font-heading flex items-center gap-2">
                        <i data-lucide="ice-cream-cone" class="w-4 h-4 text-pink-500"></i> Productos por ventas
                    </h2>
                </div>
                <div class="overflow-x-auto w-full">
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
            <section class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="px-5 sm:px-6 py-3.5 border-b border-slate-100">
                    <h2 class="font-extrabold text-slate-800 font-heading flex items-center gap-2">
                        <i data-lucide="wheat" class="w-4 h-4 text-amber-600"></i> Insumos en riesgo
                    </h2>
                </div>
                <div class="overflow-x-auto w-full">
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

            <p id="panel-footnote" class="text-[11px] text-slate-400 font-medium pb-2"></p>
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
            return `<span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${cls}">${label}</span>`;
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
                renderAlerts(data.alerts, data);
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
            return `<svg viewBox="0 0 ${w} ${h}" class="w-full h-8 mt-1" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="${coords.join(' ')}" fill="none" stroke="#ec4899" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>`;
        }

        function kpiTile(icon, label, value, sub, extra = '', tone = 'text-slate-900') {
            return `
                <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 p-4 flex flex-col">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="${icon}" class="w-3.5 h-3.5 text-pink-500"></i> ${label}
                    </span>
                    <span class="text-2xl font-extrabold font-heading mt-1 ${tone}">${value}</span>
                    <span class="text-[11px] text-slate-500 font-medium">${sub}</span>
                    ${extra}
                </div>`;
        }

        function renderKpis(data) {
            const k = data.kpis;
            const toProduce = data.products.filter(p => p.suggested_production > 0).length;
            document.getElementById('kpis').innerHTML =
                kpiTile('wallet', 'Ventas de hoy', money(k.sales_today), `${k.orders_today} orden(es)`) +
                kpiTile('trending-up', 'Ventas 7 días', money(k.sales_7d), 'Últimos 14 días:', sparkline(k.sparkline)) +
                kpiTile('siren', 'Alertas críticas', k.critical_alerts, `${k.total_alerts} alerta(s) en total`, '',
                        k.critical_alerts > 0 ? 'text-rose-600' : 'text-emerald-600') +
                kpiTile('chef-hat', 'Por elaborar', toProduce, `producto(s) para cubrir ${data.target_days} día(s)`);
        }

        function alertAction(action) {
            if (!action) return '';
            if (action.type === 'produce') {
                return `<a href="produccion.php?produce=${Number(action.product_id)}&qty=${Number(action.quantity)}"
                           class="btn-sweet-accent text-white px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm shrink-0">
                            <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar ${Number(action.quantity)}
                        </a>`;
            }
            if (action.type === 'purchase') {
                return `<a href="materiales.php?purchase=${Number(action.material_id)}&qty=${Number(action.quantity)}"
                           class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm shrink-0 transition-colors">
                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Registrar compra
                        </a>`;
            }
            return `<a href="inventario.php"
                       class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors">
                        <i data-lucide="package" class="w-3.5 h-3.5"></i> Ver inventario
                    </a>`;
        }

        function renderAlerts(alerts) {
            const list = document.getElementById('alerts-list');
            document.getElementById('alerts-count').textContent = alerts.length ? `${alerts.length} alerta(s)` : '';
            if (!alerts.length) {
                list.innerHTML = `<li class="px-6 py-8 text-center text-emerald-600 text-sm font-semibold flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Todo en orden: no hay alertas para el objetivo elegido.</li>`;
                return;
            }
            list.innerHTML = alerts.map(a => {
                const [label, dot, text] = SEVERITY_STYLES[a.severity] || SEVERITY_STYLES.medio;
                return `
                    <li class="px-5 sm:px-6 py-3 flex items-center gap-3">
                        <span class="w-2.5 h-2.5 rounded-full ${dot} shrink-0" aria-hidden="true"></span>
                        <div class="flex-1 min-w-0">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider ${text}">${label}</span>
                            <p class="text-sm text-slate-700 font-medium leading-snug">${escapeHtml(a.message)}</p>
                        </div>
                        ${alertAction(a.action)}
                    </li>`;
            }).join('');
        }

        function renderProducts(products) {
            const table = document.getElementById('products-table');
            if (!products.length) {
                table.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-slate-400 font-medium">No hay productos activos.</td></tr>';
                return;
            }
            table.innerHTML = products.map(p => `
                <tr class="hover:bg-pink-50/30 transition-colors">
                    <td class="px-5 py-3 text-xs font-extrabold text-slate-400 font-heading">${p.sold_30d > 0 ? Number(p.rank) : '—'}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full shrink-0 border border-slate-200" style="background:${safeColor(p.image_color)}"></span>
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

        function renderMaterials(materials) {
            const table = document.getElementById('materials-table');
            const atRisk = materials
                .filter(m => m.status !== 'ok' || m.blocks.length)
                .sort((a, b) => (b.blocks.length ? 1 : 0) - (a.blocks.length ? 1 : 0)
                             || (a.coverage_days ?? Infinity) - (b.coverage_days ?? Infinity));
            if (!atRisk.length) {
                table.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-emerald-600 font-semibold">Ningún insumo en riesgo.</td></tr>';
                return;
            }
            table.innerHTML = atRisk.map(m => `
                <tr class="hover:bg-pink-50/30 transition-colors">
                    <td class="px-5 py-3 font-bold text-slate-800 font-heading">${escapeHtml(m.name)}
                        ${m.used_by === 0 ? '<span class="block text-[10px] text-slate-400 font-medium">Sin recetas</span>' : ''}</td>
                    <td class="px-5 py-3 text-right font-bold">${num(m.stock)} <span class="text-[10px] text-slate-400">${escapeHtml(m.unit)}</span></td>
                    <td class="px-5 py-3 text-right text-slate-500">${num(m.min_stock)}</td>
                    <td class="px-5 py-3 text-right">${num(m.daily_consumption, 3)}</td>
                    <td class="px-5 py-3 text-right">${days(m.coverage_days)}</td>
                    <td class="px-5 py-3 text-xs">${m.blocks.length
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
    </script>
</body>
</html>
