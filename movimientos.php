<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de stock - Sweet POS</title>
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
        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="arrow-left-right" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Movimientos de stock</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Cada entrada y salida de productos e insumos, con su motivo</p>
                </div>
            </div>
            <div class="hidden xl:flex flex-col items-end">
                <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
            </div>
        </header>

        <!-- Filtros -->
        <form id="filters" class="px-4 sm:px-6 lg:px-8 pt-4 flex flex-wrap items-end gap-3 shrink-0 z-10">
            <div>
                <label for="f-type" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tipo</label>
                <select id="f-type" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
                    <option value="">Todos</option>
                    <option value="product">Productos</option>
                    <option value="material">Insumos</option>
                </select>
            </div>
            <div>
                <label for="f-item" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Item</label>
                <select id="f-item" disabled class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm min-w-[12rem] focus:outline-none focus:ring-2 focus:ring-pink-300 disabled:opacity-50">
                    <option value="">Todos</option>
                </select>
            </div>
            <div>
                <label for="f-reason" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Motivo</label>
                <select id="f-reason" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
                    <option value="">Todos</option>
                    <option value="purchase">Compra</option>
                    <option value="production">Elaboración</option>
                    <option value="sale">Venta</option>
                    <option value="adjustment">Ajuste</option>
                    <option value="void">Anulación</option>
                </select>
            </div>
            <div>
                <label for="f-from" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Desde</label>
                <input type="date" id="f-from" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
            </div>
            <div>
                <label for="f-to" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Hasta</label>
                <input type="date" id="f-to" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
            </div>
            <button type="button" id="f-clear" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:text-pink-600 hover:bg-pink-50 transition-colors">Limpiar</button>
        </form>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="w-full min-w-[760px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 py-3 font-bold">Fecha</th>
                                <th class="px-5 py-3 font-bold">Item</th>
                                <th class="px-5 py-3 font-bold">Motivo</th>
                                <th class="px-5 py-3 font-bold text-right">Cantidad</th>
                                <th class="px-5 py-3 font-bold text-right">Costo unit.</th>
                                <th class="px-5 py-3 font-bold">Usuario</th>
                                <th class="px-5 py-3 font-bold">Nota / Ref.</th>
                            </tr>
                        </thead>
                        <tbody id="movements-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="7" class="text-center py-10 text-slate-400 font-medium">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span id="page-info" class="text-slate-500 font-medium"></span>
                    <div class="flex gap-2">
                        <button type="button" id="prev-page" class="px-3 py-1.5 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-pink-50 disabled:opacity-40 disabled:hover:bg-transparent">Anterior</button>
                        <button type="button" id="next-page" class="px-3 py-1.5 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-pink-50 disabled:opacity-40 disabled:hover:bg-transparent">Siguiente</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const REASON_LABELS = {
            purchase:   ['Compra', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            production: ['Elaboración', 'bg-pink-50 text-pink-700 border-pink-200'],
            sale:       ['Venta', 'bg-sky-50 text-sky-700 border-sky-200'],
            adjustment: ['Ajuste', 'bg-amber-50 text-amber-800 border-amber-200'],
            void:       ['Anulación', 'bg-slate-100 text-slate-600 border-slate-200'],
        };
        const PER_PAGE = 50;
        let options = { products: [], materials: [] };
        let page = 1;

        lucide.createIcons();

        // created_at is stored in UTC by SQLite CURRENT_TIMESTAMP.
        function formatDate(value) {
            const d = new Date(String(value).replace(' ', 'T') + 'Z');
            return isNaN(d) ? escapeHtml(value) : d.toLocaleString('es-VE', { dateStyle: 'short', timeStyle: 'short' });
        }

        function fillItemSelect(selected = '') {
            const type = document.getElementById('f-type').value;
            const select = document.getElementById('f-item');
            const list = type === 'product' ? options.products : type === 'material' ? options.materials : [];
            select.disabled = !type;
            select.innerHTML = '<option value="">Todos</option>' +
                list.map(i => `<option value="${Number(i.id)}">${escapeHtml(i.name)}</option>`).join('');
            select.value = String(selected);
        }

        function currentFilters() {
            const params = new URLSearchParams();
            const type = document.getElementById('f-type').value;
            const item = document.getElementById('f-item').value;
            const reason = document.getElementById('f-reason').value;
            const from = document.getElementById('f-from').value;
            const to = document.getElementById('f-to').value;
            if (type) params.set('item_type', type);
            if (type && item) params.set('item_id', item);
            if (reason) params.set('reason', reason);
            if (from) params.set('date_from', from);
            if (to) params.set('date_to', to);
            return params;
        }

        async function loadMovements() {
            const table = document.getElementById('movements-table');
            const params = currentFilters();
            params.set('page', page);
            params.set('per_page', PER_PAGE);
            // Keep filters in the URL so a view can be shared or reloaded.
            history.replaceState(null, '', location.pathname + '?' + params.toString());
            try {
                const res = await fetch('api/movements.php?' + params.toString());
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                renderMovements(data);
            } catch (e) {
                table.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-500 font-medium">${escapeHtml(e.message || 'Error al cargar movimientos.')}</td></tr>`;
            }
        }

        function renderMovements(data) {
            const table = document.getElementById('movements-table');
            page = data.page;
            document.getElementById('page-info').textContent =
                `${data.total} movimiento(s) · página ${data.page} de ${data.pages}`;
            document.getElementById('prev-page').disabled = data.page <= 1;
            document.getElementById('next-page').disabled = data.page >= data.pages;

            if (!data.items.length) {
                table.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-slate-400 font-medium">No hay movimientos con estos filtros.</td></tr>';
                return;
            }
            table.innerHTML = data.items.map(m => {
                const [label, cls] = REASON_LABELS[m.reason] || [m.reason, 'bg-slate-100 text-slate-600 border-slate-200'];
                const qty = Number(m.qty_delta);
                const ref = m.ref_id ? `#${Number(m.ref_id)}` : '';
                return `
                    <tr class="hover:bg-pink-50/30 transition-colors">
                        <td class="px-5 py-2.5 text-xs text-slate-500 whitespace-nowrap">${formatDate(m.created_at)}</td>
                        <td class="px-5 py-2.5">
                            <span class="font-bold text-slate-800 font-heading">${escapeHtml(m.item_name || `(eliminado #${Number(m.item_id)})`)}</span>
                            <span class="block text-[10px] text-slate-400">${m.item_type === 'product' ? 'Producto' : 'Insumo'}</span>
                        </td>
                        <td class="px-5 py-2.5"><span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${cls}">${escapeHtml(label)}</span></td>
                        <td class="px-5 py-2.5 text-right font-extrabold font-heading ${qty < 0 ? 'text-rose-600' : 'text-emerald-600'} whitespace-nowrap">
                            ${qty > 0 ? '+' : ''}${qty.toLocaleString('es-VE', { maximumFractionDigits: 3 })}
                            <span class="text-[10px] text-slate-400 font-medium">${escapeHtml(m.unit || '')}</span>
                        </td>
                        <td class="px-5 py-2.5 text-right text-xs text-slate-500">${m.unit_cost !== null ? '$' + Number(m.unit_cost).toFixed(2) : '—'}</td>
                        <td class="px-5 py-2.5 text-xs text-slate-600">${escapeHtml(m.username || '—')}</td>
                        <td class="px-5 py-2.5 text-xs text-slate-500">${escapeHtml([ref, m.note || ''].filter(Boolean).join(' · ') || '—')}</td>
                    </tr>`;
            }).join('');
        }

        document.addEventListener('DOMContentLoaded', async () => {
            // Restore filters from the URL (e.g. movimientos.php?item_type=material&item_id=3)
            const q = new URLSearchParams(location.search);
            document.getElementById('f-type').value = ['product', 'material'].includes(q.get('item_type')) ? q.get('item_type') : '';
            document.getElementById('f-reason').value = REASON_LABELS[q.get('reason')] ? q.get('reason') : '';
            document.getElementById('f-from').value = q.get('date_from') || '';
            document.getElementById('f-to').value = q.get('date_to') || '';
            page = Math.max(1, parseInt(q.get('page'), 10) || 1);

            try {
                const res = await fetch('api/movements.php?options=1');
                if (res.ok) options = await res.json();
            } catch (e) {}
            fillItemSelect(q.get('item_id') || '');

            document.getElementById('f-type').addEventListener('change', () => { fillItemSelect(); page = 1; loadMovements(); });
            ['f-item', 'f-reason', 'f-from', 'f-to'].forEach(id =>
                document.getElementById(id).addEventListener('change', () => { page = 1; loadMovements(); }));
            document.getElementById('f-clear').addEventListener('click', () => {
                document.getElementById('filters').reset();
                fillItemSelect();
                page = 1;
                loadMovements();
            });
            document.getElementById('prev-page').addEventListener('click', () => { if (page > 1) { page--; loadMovements(); } });
            document.getElementById('next-page').addEventListener('click', () => { page++; loadMovements(); });

            loadMovements();
        });
    </script>
</body>
</html>
