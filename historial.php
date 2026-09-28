<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - Sweet POS</title>
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
<body class="h-screen overflow-hidden flex text-slate-800 bg-background relative">

    <!-- Sidebar Navegación -->
    <?php require '_sidebar.php'; ?>

    <!-- Fondo desenfocado decorativo -->
    <div class="absolute top-0 right-0 w-[28rem] h-[28rem] bg-pink-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative z-10">
        
        <!-- Header -->
        <!-- Header -->
        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-200/60 glass-panel shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="history" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Historial de Ventas</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-1 sm:line-clamp-none">Auditoría, filtros & comanda de transacciones</p>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto justify-end">
                <div class="hidden sm:flex flex-col items-end">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
                <button onclick="fetchHistory()" class="bg-white border border-slate-200 text-slate-700 hover:text-pink-600 hover:border-pink-300 px-3.5 py-2 rounded-2xl text-xs font-bold shadow-xs transition-all flex items-center justify-center gap-1.5 w-full sm:w-auto">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Refrescar</span>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 flex flex-col gap-4 sm:gap-6 pb-28 md:pb-20">
            
            <!-- KPI Cards con gradientes pastel sutiles -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-5 shrink-0">
                <!-- Ingresos -->
                <div class="bg-gradient-to-br from-emerald-50/70 via-teal-50/40 to-white rounded-3xl p-4 sm:p-5 border border-emerald-100 shadow-card flex items-center gap-3.5 sm:gap-4 relative overflow-hidden group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white text-emerald-600 flex items-center justify-center shrink-0 shadow-xs border border-emerald-100 group-hover:scale-105 transition-transform">
                        <i data-lucide="dollar-sign" class="w-6 h-6 sm:w-7 sm:h-7 stroke-[2.2]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-widest mb-0.5">Ingresos Totales</p>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 font-heading" id="kpi-ingresos">$0.00</h3>
                    </div>
                    <i data-lucide="trending-up" class="w-16 h-16 sm:w-20 sm:h-20 absolute -right-3 -bottom-3 text-emerald-100 opacity-40"></i>
                </div>
                
                <!-- Pagadas -->
                <div class="bg-gradient-to-br from-pink-50/70 via-rose-50/40 to-white rounded-3xl p-4 sm:p-5 border border-pink-100 shadow-card flex items-center gap-3.5 sm:gap-4 relative overflow-hidden group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white text-pink-600 flex items-center justify-center shrink-0 shadow-xs border border-pink-100 group-hover:scale-105 transition-transform">
                        <i data-lucide="shopping-bag" class="w-6 h-6 sm:w-7 sm:h-7 stroke-[2.2]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-pink-700 uppercase tracking-widest mb-0.5">Órdenes Pagadas</p>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 font-heading" id="kpi-pagadas">0</h3>
                    </div>
                    <i data-lucide="check-circle-2" class="w-16 h-16 sm:w-20 sm:h-20 absolute -right-3 -bottom-3 text-pink-100 opacity-40"></i>
                </div>
                
                <!-- Pendientes -->
                <div class="bg-gradient-to-br from-amber-50/70 via-orange-50/40 to-white rounded-3xl p-4 sm:p-5 border border-amber-100 shadow-card flex items-center gap-3.5 sm:gap-4 relative overflow-hidden group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white text-amber-600 flex items-center justify-center shrink-0 shadow-xs border border-amber-100 group-hover:scale-105 transition-transform">
                        <i data-lucide="clock" class="w-6 h-6 sm:w-7 sm:h-7 stroke-[2.2]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-amber-700 uppercase tracking-widest mb-0.5">Mesas Pendientes</p>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 font-heading" id="kpi-pendientes">0</h3>
                    </div>
                    <i data-lucide="hourglass" class="w-16 h-16 sm:w-20 sm:h-20 absolute -right-3 -bottom-3 text-amber-100 opacity-40"></i>
                </div>
            </div>

            <!-- Panel de Filtros Sweet & Visible -->
            <div class="bg-white rounded-3xl p-3.5 sm:p-4 border border-slate-200/90 shadow-card flex flex-col gap-3 shrink-0 z-20">
                <!-- Buscador Prominente -->
                <div class="w-full">
                    <div class="search-bar-sweet w-full">
                        <div class="search-icon-badge">
                            <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                        </div>
                        <input 
                            type="text" 
                            id="filter-search" 
                            placeholder="Buscar por cliente, cédula, orden # o número de referencia..." 
                            autocomplete="off">
                        <span id="history-search-count" class="hidden text-[10px] sm:text-[11px] font-extrabold text-pink-700 bg-pink-50 px-2 sm:px-2.5 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                        <button 
                            type="button" 
                            id="filter-search-clear" 
                            onclick="clearFilterSearch()" 
                            class="hidden search-clear-btn" 
                            title="Limpiar búsqueda">
                            <i data-lucide="x" class="w-3.5 h-3.5 stroke-[2.4]"></i>
                        </button>
                        <span class="hidden lg:inline-flex text-[10px] font-bold text-slate-400 bg-pink-50/90 text-pink-600 px-2 py-0.5 rounded-lg border border-pink-200/80 font-mono shrink-0 select-none">
                            ESC
                        </span>
                    </div>
                </div>

                <!-- Hidden inputs to synchronize values -->
                <input type="hidden" id="filter-status" value="all">
                <input type="hidden" id="filter-payment" value="all">

                <!-- Píldoras de Filtros Visibles -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5 pt-2 border-t border-slate-100/90">
                    <!-- Filtro por Estado -->
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
                        <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1 mr-1 shrink-0 font-heading">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-pink-500"></i>
                            <span>Estado:</span>
                        </span>
                        <button type="button" data-status="all" class="history-status-pill filter-pill-sweet active">
                            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                            <span>Todas</span>
                            <span class="pill-badge" id="status-count-all">0</span>
                        </button>
                        <button type="button" data-status="paid" class="history-status-pill filter-pill-sweet">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-emerald-500"></i>
                            <span class="text-emerald-800">Pagadas</span>
                            <span class="pill-badge !bg-emerald-100 !text-emerald-800" id="status-count-paid">0</span>
                        </button>
                        <button type="button" data-status="pending" class="history-status-pill filter-pill-sweet">
                            <i data-lucide="hourglass" class="w-3.5 h-3.5 text-amber-500"></i>
                            <span class="text-amber-800">Pendientes</span>
                            <span class="pill-badge !bg-amber-100 !text-amber-800" id="status-count-pending">0</span>
                        </button>
                        <button type="button" data-status="voided" class="history-status-pill filter-pill-sweet">
                            <i data-lucide="ban" class="w-3.5 h-3.5 text-rose-500"></i>
                            <span class="text-rose-800">Anuladas</span>
                            <span class="pill-badge !bg-rose-100 !text-rose-800" id="status-count-voided">0</span>
                        </button>
                    </div>

                    <!-- Filtro por Método de Pago -->
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
                        <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1 mr-1 shrink-0 font-heading">
                            <i data-lucide="credit-card" class="w-3.5 h-3.5 text-pink-500"></i>
                            <span>Pago:</span>
                        </span>
                        <button type="button" data-payment="all" class="history-payment-pill filter-pill-sweet active">
                            <span>Todos</span>
                        </button>
                        <button type="button" data-payment="cash" class="history-payment-pill filter-pill-sweet">
                            <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                            <span>Efectivo</span>
                        </button>
                        <button type="button" data-payment="card" class="history-payment-pill filter-pill-sweet">
                            <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                            <span>Tarjeta</span>
                        </button>
                        <button type="button" data-payment="transfer" class="history-payment-pill filter-pill-sweet">
                            <i data-lucide="arrow-left-right" class="w-3.5 h-3.5"></i>
                            <span>Transf.</span>
                        </button>
                        <button type="button" data-payment="pagomovil" class="history-payment-pill filter-pill-sweet">
                            <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                            <span>Pago Móvil</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lista de Historial -->
            <div class="flex-1 flex flex-col gap-4 pb-20" id="history-container">
                <!-- Inyectado por JS -->
            </div>

        </div>
    </main>

    <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2"></div>

    <script>
        lucide.createIcons();

        let allSales = [];

        const historyContainer = document.getElementById('history-container');
        const filterSearch = document.getElementById('filter-search');
        const filterStatus = document.getElementById('filter-status');
        const filterPayment = document.getElementById('filter-payment');

        const kpiIngresos = document.getElementById('kpi-ingresos');
        const kpiPagadas = document.getElementById('kpi-pagadas');
        const kpiPendientes = document.getElementById('kpi-pendientes');

        document.addEventListener('DOMContentLoaded', () => {
            fetchHistory();
            filterSearch.addEventListener('input', applyFilters);

            // Teclado '/' para enfocar buscador
            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== filterSearch) {
                    const hasModal = document.querySelector('.fixed:not(.hidden) input');
                    if (!hasModal && filterSearch) {
                        e.preventDefault();
                        filterSearch.focus();
                        filterSearch.select();
                    }
                }
            });

            // Píldoras de Estado
            document.querySelectorAll('.history-status-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.history-status-pill').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    filterStatus.value = btn.dataset.status || 'all';
                    applyFilters();
                });
            });

            // Píldoras de Método de Pago
            document.querySelectorAll('.history-payment-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.history-payment-pill').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    filterPayment.value = btn.dataset.payment || 'all';
                    applyFilters();
                });
            });
        });

        async function fetchHistory() {
            historyContainer.innerHTML = '<div class="w-full flex justify-center py-20"><div class="loader"></div></div>';
            try {
                const response = await fetch('api/history.php');
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Error');
                allSales = data;
                updateStatusPillCounts(allSales);
                applyFilters();
            } catch (error) {
                historyContainer.innerHTML = '<p class="text-center text-rose-500 font-medium py-20">Error de conexión con el servidor.</p>';
            }
        }

        function updateStatusPillCounts(sales) {
            const total = sales.length;
            const paid = sales.filter(s => s.status === 'paid').length;
            const pending = sales.filter(s => s.status === 'pending').length;
            const voided = sales.filter(s => s.status === 'voided').length;

            const elAll = document.getElementById('status-count-all');
            const elPaid = document.getElementById('status-count-paid');
            const elPending = document.getElementById('status-count-pending');

            if (elAll) elAll.textContent = total;
            if (elPaid) elPaid.textContent = paid;
            if (elPending) elPending.textContent = pending;
            const elVoided = document.getElementById('status-count-voided');
            if (elVoided) elVoided.textContent = voided;
        }

        function applyFilters() {
            const rawQuery = filterSearch.value.trim();
            const query = rawQuery.toLowerCase();
            const status = filterStatus.value;
            const payment = filterPayment.value;

            const clearBtn = document.getElementById('filter-search-clear');
            const countEl = document.getElementById('history-search-count');
            if (clearBtn) {
                if (rawQuery.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }

            const filteredSales = allSales.filter(sale => {
                if(status !== 'all' && sale.status !== status) return false;
                if(payment !== 'all' && sale.payment_method !== payment) return false;
                
                if(query) {
                    const clientName = (sale.client_name || '').toLowerCase();
                    const clientCedula = (sale.client_cedula || '').toLowerCase();
                    const ref = (sale.reference_number || '').toLowerCase();
                    const idStr = String(sale.id);
                    
                    if(!clientName.includes(query) && !clientCedula.includes(query) && !ref.includes(query) && !idStr.includes(query)) {
                        return false;
                    }
                }
                return true;
            });

            if (countEl) {
                if (rawQuery.length > 0) {
                    countEl.classList.remove('hidden');
                    countEl.textContent = `${filteredSales.length} ${filteredSales.length === 1 ? 'venta' : 'ventas'}`;
                } else {
                    countEl.classList.add('hidden');
                }
            }

            updateKPIs(filteredSales);
            renderSales(filteredSales);
        }

        function clearFilterSearch() {
            filterSearch.value = '';
            const clearBtn = document.getElementById('filter-search-clear');
            const countEl = document.getElementById('history-search-count');
            if (clearBtn) clearBtn.classList.add('hidden');
            if (countEl) countEl.classList.add('hidden');
            filterSearch.focus();
            applyFilters();
        }

        filterSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                clearFilterSearch();
                filterSearch.blur();
            }
        });

        function updateKPIs(sales) {
            let ingresos = 0;
            let pagadas = 0;
            let pendientes = 0;

            sales.forEach(sale => {
                if(sale.status === 'paid') {
                    pagadas++;
                    ingresos += parseFloat(sale.total_amount);
                } else if(sale.status === 'pending') {
                    pendientes++;
                }
            });

            kpiIngresos.innerText = '$' + ingresos.toFixed(2);
            kpiPagadas.innerText = pagadas;
            kpiPendientes.innerText = pendientes;
        }

        function getPaymentLabel(method) {
            const map = {
                'cash': { icon: 'banknote', label: 'Efectivo', color: 'text-emerald-700 bg-emerald-50 border-emerald-200' },
                'card': { icon: 'credit-card', label: 'Tarjeta', color: 'text-indigo-700 bg-indigo-50 border-indigo-200' },
                'transfer': { icon: 'arrow-left-right', label: 'Transferencia', color: 'text-blue-700 bg-blue-50 border-blue-200' },
                'pagomovil': { icon: 'smartphone', label: 'Pago Móvil', color: 'text-purple-700 bg-purple-50 border-purple-200' },
                'null': { icon: 'clock', label: 'Pendiente', color: 'text-amber-700 bg-amber-50 border-amber-200' }
            };
            return map[method] || map['null'];
        }

        // Anula la orden y devuelve el stock de sus productos (solo admin/gerente)
        async function voidSale(id) {
            const reason = prompt(`¿Anular la orden #${String(id).padStart(4, '0')}? El stock de sus productos será restituido.\n\nMotivo (opcional):`);
            if (reason === null) return;
            try {
                const res = await fetch('api/sales.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'void', id, reason: reason.trim() })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.error || 'No se pudo anular la orden.');
                    return;
                }
                await fetchHistory();
            } catch (e) {
                alert('Error de conexión al anular la orden.');
            }
        }

        function toggleDetails(id) {
            const el = document.getElementById(`details-${id}`);
            const icon = document.getElementById(`icon-${id}`);
            if(el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
            } else {
                el.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        function renderSales(sales) {
            historyContainer.innerHTML = '';
            
            if(sales.length === 0) {
                historyContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                        <div class="w-16 h-16 rounded-3xl bg-pink-50 text-pink-400 flex items-center justify-center mb-3">
                            <i data-lucide="inbox" class="w-8 h-8 stroke-[1.8]"></i>
                        </div>
                        <p class="font-heading font-bold text-slate-700 text-base mb-1">No se encontraron ventas</p>
                        <p class="text-xs text-slate-400">Prueba cambiando los criterios de filtro.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            sales.forEach(sale => {
                const dateObj = new Date(sale.created_at);
                const dateStr = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                const timeStr = dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                
                const pm = getPaymentLabel(sale.payment_method);
                const isPaid = sale.status === 'paid';
                const isVoided = sale.status === 'voided';
                const statusBadge = isVoided
                    ? `<span class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-full text-[10px] font-bold uppercase tracking-wider border border-rose-200 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Anulada</span>`
                    : isPaid
                    ? `<span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 rounded-full text-[10px] font-bold uppercase tracking-wider border border-emerald-200 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Pagada</span>`
                    : `<span class="px-2.5 py-1 bg-amber-50 text-amber-800 rounded-full text-[10px] font-bold uppercase tracking-wider border border-amber-200 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Pendiente</span>`;

                let tableStr = sale.table_number;
                if(tableStr === 999 || tableStr === '999') tableStr = 'Llevar';

                let itemsHtml = '';
                sale.items.forEach(item => {
                    itemsHtml += `
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-pink-50 text-pink-700 font-extrabold text-[10px] flex items-center justify-center font-heading">${item.quantity}</span>
                                <span class="text-xs text-slate-700 font-medium">${escapeHtml(item.name)}</span>
                            </div>
                            <span class="text-xs font-bold text-slate-900 font-heading">$${parseFloat(item.subtotal).toFixed(2)}</span>
                        </div>
                    `;
                });

                const card = document.createElement('div');
                card.className = 'bg-white rounded-3xl border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all overflow-hidden group';
                card.innerHTML = `
                    <div class="p-5 cursor-pointer" onclick="toggleDetails(${sale.id})">
                        <!-- Cabecera Card -->
                        <div class="flex items-center justify-between mb-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="bg-pink-50 text-pink-700 font-black px-3 py-1 rounded-xl text-xs font-heading border border-pink-100">#${String(sale.id).padStart(4, '0')}</div>
                                <div class="text-xs font-medium text-slate-400 flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> ${dateStr} · ${timeStr}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                ${statusBadge}
                                <i data-lucide="chevron-down" id="icon-${sale.id}" class="w-4 h-4 text-slate-400 transition-transform duration-300"></i>
                            </div>
                        </div>

                        <!-- Info Principal -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4 items-start lg:items-center">
                            <div class="sm:col-span-2 flex flex-col">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Cliente & Destino</span>
                                <div class="flex items-center gap-2 flex-wrap">
                                    ${sale.client_name ? `<span class="font-bold text-slate-800 truncate text-sm font-heading">${escapeHtml(sale.client_name)}</span> <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full font-bold">V-${escapeHtml(sale.client_cedula)}</span>` : `<span class="font-bold text-slate-500 italic text-sm">Cliente Rápido</span>`}
                                </div>
                                <div class="text-xs text-pink-600 font-bold mt-1 flex items-center gap-1">
                                    <i data-lucide="${tableStr === 'Llevar' ? 'shopping-bag' : 'armchair'}" class="w-3.5 h-3.5"></i> 
                                    <span>${tableStr === 'Llevar' ? 'Para Llevar' : 'Mesa ' + escapeHtml(tableStr)}</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-col lg:border-l lg:border-slate-100 lg:pl-4">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Método de Pago</span>
                                <div class="flex items-center gap-1.5 ${pm.color} px-2.5 py-1 rounded-full w-max border">
                                    <i data-lucide="${pm.icon}" class="w-3.5 h-3.5"></i>
                                    <span class="text-[11px] font-bold">${pm.label}</span>
                                </div>
                                ${sale.reference_number ? `<span class="text-[10px] font-mono font-bold text-slate-400 mt-1">Ref: ${escapeHtml(sale.reference_number)}</span>` : ''}
                            </div>

                            <div class="flex flex-col items-start lg:items-end justify-center lg:border-l lg:border-slate-100 lg:pl-4 pt-1 lg:pt-0">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-0.5">Total Orden</span>
                                <span class="text-2xl font-black text-slate-900 font-heading leading-none">$${parseFloat(sale.total_amount).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Detalles Expandibles (Comanda) -->
                    <div id="details-${sale.id}" class="hidden bg-slate-50/70 border-t border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-1.5 mb-2.5">
                            <i data-lucide="receipt" class="w-3.5 h-3.5 text-pink-500"></i>
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Detalle de Comanda</span>
                        </div>
                        <div class="bg-white border border-slate-200/80 rounded-2xl px-4 py-2 shadow-xs">
                            ${itemsHtml}
                        </div>
                        ${isVoided ? `
                        <p class="mt-3 text-[11px] text-rose-700 font-semibold">Anulada por ${escapeHtml(sale.voided_by || '—')}${sale.void_reason ? ': ' + escapeHtml(sale.void_reason) : ''}</p>` : `
                        <div class="mt-3 flex justify-end">
                            <button type="button" onclick="voidSale(${Number(sale.id)})" class="text-xs font-bold text-rose-600 hover:text-white hover:bg-rose-600 border border-rose-200 rounded-xl px-3 py-1.5 transition-all bg-white flex items-center gap-1.5">
                                <i data-lucide="ban" class="w-3.5 h-3.5"></i> Anular orden
                            </button>
                        </div>`}
                    </div>
                `;
                historyContainer.appendChild(card);
            });
            lucide.createIcons();
        }
    </script>
</body>
</html>
