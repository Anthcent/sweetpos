<?php require 'auth.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zonas de Mesa - YILMANU POS</title>
    <script src="assets/tailwindcss.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/lucide.min.js"></script>
    <script>
        tailwind.config = {
            theme: { 
                extend: { 
                    colors: { 
                        primary: '#f5b8d0', 
                        background: '#faf7f4' 
                    } 
                } 
            }
        }
    </script>
</head>
<body class="h-screen overflow-hidden flex text-slate-800">

    <!-- Sidebar de Navegacion (dinamico por rol) -->
    <?php require '_sidebar.php'; ?>

    <!-- Área Principal (Mesas) -->
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <div class="absolute top-0 left-0 w-[26rem] h-[26rem] bg-pink-100/40 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 right-10 w-[24rem] h-[24rem] bg-purple-100/30 rounded-full mix-blend-multiply filter blur-3xl translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="coffee" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Zonas de Mesa</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-2 sm:line-clamp-none">Comandas activas & pedidos pendientes de cobro</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <!-- Buscador de mesas -->
                <div class="search-bar-sweet w-full sm:w-80">
                    <div class="search-icon-badge">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <input 
                        type="text" 
                        id="tables-search" 
                        placeholder="Buscar mesa o cliente..." 
                        autocomplete="off">
                    <span id="tables-search-count" class="hidden text-[10px] font-extrabold text-pink-700 bg-pink-50 px-2 sm:px-2.5 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                    <button 
                        type="button" 
                        id="tables-search-clear" 
                        onclick="clearTablesSearch()" 
                        class="hidden search-clear-btn" 
                        title="Limpiar búsqueda">
                        <i data-lucide="x" class="w-3.5 h-3.5 stroke-[2.4]"></i>
                    </button>
                    <span class="hidden lg:inline-flex text-[10px] font-bold text-slate-400 bg-pink-50/90 text-pink-600 px-2 py-0.5 rounded-lg border border-pink-200/80 font-mono shrink-0 select-none">
                        ESC
                    </span>
                </div>

                <div class="hidden xl:flex flex-col items-end pl-2 border-l border-slate-200/80">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
                <button onclick="loadPendingTables()" title="Actualizar mesas" class="text-slate-600 hover:text-pink-600 transition-colors p-2 sm:p-2.5 rounded-2xl bg-white border border-slate-200 shadow-xs hover:border-pink-200 flex items-center justify-center gap-1.5 font-bold text-xs shrink-0">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span class="sm:hidden">Actualizar</span>
                </button>
            </div>
        </header>

        <!-- BARRA DEDICADA DE FILTROS DE MESAS (100% VISIBLE Y DINÁMICA) -->
        <div class="px-4 sm:px-6 lg:px-8 pt-3.5 pb-1 shrink-0 z-10">
            <div class="filter-wrap-mobile flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
                <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1.5 mr-1 shrink-0 font-heading">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Filtrar:</span>
                </span>

                <button type="button" data-filter="all" class="tbl-filter-btn filter-pill-sweet active">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span>Todas</span>
                    <span class="pill-badge" id="tbl-count-all">0</span>
                </button>

                <button type="button" data-filter="salon" class="tbl-filter-btn filter-pill-sweet">
                    <i data-lucide="coffee" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>En Salón</span>
                    <span class="pill-badge" id="tbl-count-salon">0</span>
                </button>

                <button type="button" data-filter="takeout" class="tbl-filter-btn filter-pill-sweet">
                    <i data-lucide="shopping-bag" class="w-3.5 h-3.5 text-purple-500"></i>
                    <span>Para Llevar</span>
                    <span class="pill-badge" id="tbl-count-takeout">0</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div id="tables-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6 pb-28 md:pb-20">
                <!-- Mesas inyectadas por JS -->
            </div>
        </div>
    </main>

    <!-- Modal Pago Mesa -->
    <div id="payment-modal" role="dialog" aria-modal="true" aria-label="Cobrar mesa" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="checkout-layout flex flex-col md:flex-row items-stretch md:items-start justify-center gap-3 sm:gap-4 w-full max-w-md md:max-w-[47rem] max-h-[92dvh] overflow-y-auto no-scrollbar">
        <div class="order-2 md:order-1 bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[90vh] flex flex-col shrink-0">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="credit-card" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-base text-slate-900 font-heading">Cobrar Mesa</h3>
                </div>
                <button onclick="closePaymentModal()" aria-label="Cerrar cobro" class="text-slate-600 hover:text-slate-900 bg-slate-200/50 hover:bg-slate-200 rounded-full p-1.5 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="p-4 sm:p-6 flex flex-col gap-3.5 sm:gap-4 overflow-y-auto">
                <input type="hidden" id="pay-sale-id">
                
                <div class="flex justify-between items-center bg-gradient-to-r from-pink-50 to-purple-50 p-3.5 sm:p-4 rounded-2xl border border-pink-100">
                    <div>
                        <span class="text-pink-800 font-bold uppercase tracking-wider text-xs block">Total a Cobrar</span>
                        <span class="text-[11px] text-slate-600 font-medium">Monto final de la comanda</span>
                    </div>
                    <span id="pay-modal-total" class="text-2xl sm:text-3xl font-black text-pink-700 font-heading">$0.00</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Método de Pago</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="cash" class="peer sr-only" checked onchange="toggleReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-600 peer-checked:text-pink-800 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="banknote" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Efectivo</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="card" class="peer sr-only" onchange="toggleReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-600 peer-checked:text-pink-800 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Tarjeta</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="transfer" class="peer sr-only" onchange="toggleReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-600 peer-checked:text-pink-800 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Transf.</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="pagomovil" class="peer sr-only" onchange="toggleReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-600 peer-checked:text-pink-800 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="smartphone" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Pago Móv.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="reference-field-container" class="hidden">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nº de Referencia (Últimos dígitos)</label>
                    <input type="text" id="payment-reference" placeholder="Ej. 4589" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-mono text-base tracking-widest text-slate-800 font-bold">
                </div>
            </div>
            
            <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 shrink-0">
                <button onclick="submitPayment()" class="btn-sweet-accent w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex items-center justify-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5 stroke-[2.2]"></i>
                    <span>Confirmar Pago de Mesa</span>
                </button>
            </div>
        </div>

        <!-- Burbuja de resumen de la comanda (solo lectura: la comanda ya descontó stock) -->
        <aside id="pay-summary" class="order-summary-bubble is-collapsed order-1 md:order-2 w-full max-w-md md:w-72 md:max-w-none md:shrink-0" aria-label="Resumen de la comanda">
            <button type="button" id="pay-summary-toggle" class="order-summary-toggle" aria-expanded="false" aria-controls="pay-summary-list">
                <span class="flex items-center gap-2 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-white text-pink-700 flex items-center justify-center shrink-0 border border-pink-200">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                    </span>
                    <span class="font-extrabold text-sm font-heading">Resumen de la comanda</span>
                </span>
                <span class="flex items-center gap-2 shrink-0">
                    <span id="pay-summary-count" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-white text-pink-800 border border-pink-200">0 artículos</span>
                    <i data-lucide="chevron-down" class="chevron w-4 h-4"></i>
                </span>
            </button>
            <div id="pay-summary-list" class="order-summary-list"></div>
            <div class="order-summary-footer">
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Total</span>
                <span id="pay-summary-total" class="text-xl font-black text-pink-700 font-heading">$0.00</span>
            </div>
        </aside>
        </div>
    </div>

    <!-- Modal Agregar productos a una comanda pendiente -->
    <div id="add-items-modal" role="dialog" aria-modal="true" aria-labelledby="add-items-title" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0 gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-700 flex items-center justify-center shrink-0">
                        <i data-lucide="plus-circle" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="add-items-title" class="font-extrabold text-base text-slate-900 font-heading leading-tight">Agregar productos</h3>
                        <p id="add-items-subtitle" class="text-xs text-slate-600 font-semibold truncate"></p>
                    </div>
                </div>
                <button type="button" onclick="closeAddItemsModal()" aria-label="Cerrar agregar productos" class="text-slate-600 hover:text-slate-900 bg-slate-200/50 hover:bg-slate-200 rounded-full p-1.5 transition-colors shrink-0">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="px-4 sm:px-6 pt-4 pb-2 shrink-0">
                <label for="add-items-search" class="sr-only">Buscar producto</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    <input type="text" id="add-items-search" autocomplete="off" maxlength="60" placeholder="Buscar producto o categoría..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-semibold text-slate-800">
                </div>
            </div>

            <div id="add-items-list" class="px-4 sm:px-6 py-2 overflow-y-auto flex-1 min-h-[10rem]" aria-live="polite"></div>

            <div class="px-4 sm:px-6 py-3.5 bg-slate-50/80 border-t border-slate-100 shrink-0 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p id="add-items-count" class="text-xs font-bold text-slate-600">0 artículos</p>
                    <p id="add-items-total" class="text-xl font-black text-pink-700 font-heading leading-tight">$0.00</p>
                </div>
                <button type="button" id="add-items-submit" onclick="submitAddItems()" disabled class="btn-sweet-accent disabled:opacity-40 disabled:pointer-events-none text-white font-extrabold py-3 px-4 sm:px-5 rounded-2xl transition-all flex items-center justify-center gap-2 text-sm shrink-0">
                    <i data-lucide="check-circle-2" class="w-4 h-4 stroke-[2.2]"></i>
                    <span>Agregar a la comanda</span>
                </button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>
    <script src="assets/receipt.js"></script>

    <script>
        // Anular ordenes (con devolucion de stock) queda reservado a admin/gerente
        const CAN_VOID = <?= in_array(currentRole(), ['admin', 'gerente'], true) ? 'true' : 'false' ?>;
        let allTablesCache = [];

        async function voidSale(id) {
            const reason = prompt('¿Anular esta comanda? El stock de sus productos será restituido.\n\nMotivo (opcional):');
            if (reason === null) return;
            try {
                const res = await fetch('api/sales.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'void', id, reason: reason.trim() })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    showToast('Comanda anulada y stock restituido');
                    loadPendingTables();
                } else {
                    showToast(data.error || 'No se pudo anular la comanda', 'error');
                }
            } catch (e) {
                showToast('Error de conexión', 'error');
            }
        }
        let currentTableFilter = 'all';

        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('tables-search');
            if (searchInput) {
                searchInput.addEventListener('input', filterTables);
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        clearTablesSearch();
                        searchInput.blur();
                    }
                });
            }

            // Keyboard shortcut '/' to search
            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== searchInput) {
                    const hasModal = document.querySelector('.fixed:not(.hidden) input, #payment-modal:not(.hidden)');
                    if (!hasModal && searchInput) {
                        e.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            });

            // Filter pills click listeners
            document.querySelectorAll('.tbl-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.tbl-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentTableFilter = btn.dataset.filter || 'all';
                    filterTables();
                });
            });

            loadPendingTables();
        });

        function showToast(message, type = 'success') {
            const tc = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bg = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
            const icon = type === 'success' ? 'check-circle-2' : 'alert-circle';
            toast.className = `toast px-4 py-3 rounded-2xl text-white shadow-xl flex items-center gap-2.5 font-medium text-sm ${bg}`;
            toast.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5 stroke-[2.2]"></i><span>${escapeHtml(message)}</span>`;
            tc.appendChild(toast);
            if(window.lucide) window.lucide.createIcons({ root: toast });
            setTimeout(() => { 
                toast.classList.add('hide'); 
                setTimeout(() => toast.remove(), 250); 
            }, 3000);
        }

        async function loadPendingTables() {
            const grid = document.getElementById('tables-grid');
            try {
                grid.innerHTML = '<div class="col-span-full flex flex-col items-center justify-center py-16"><div class="loader mb-3"></div><p class="text-xs font-semibold text-slate-400">Verificando mesas...</p></div>';
                const res = await fetch('api/sales.php');
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                allTablesCache = data;
                updateTableCounts(allTablesCache);
                filterTables();
            } catch(e) {
                grid.innerHTML = '<p class="col-span-full text-center text-rose-500 py-10 font-medium">Error al cargar mesas activas.</p>';
            }
        }

        function updateTableCounts(tables) {
            const total = tables.length;
            const salon = tables.filter(s => parseInt(s.table_number, 10) !== 999).length;
            const takeout = tables.filter(s => parseInt(s.table_number, 10) === 999).length;

            const elAll = document.getElementById('tbl-count-all');
            const elSalon = document.getElementById('tbl-count-salon');
            const elTakeout = document.getElementById('tbl-count-takeout');

            if (elAll) elAll.textContent = total;
            if (elSalon) elSalon.textContent = salon;
            if (elTakeout) elTakeout.textContent = takeout;
        }

        function filterTables() {
            const q = (document.getElementById('tables-search')?.value || '').trim().toLowerCase();
            const clearBtn = document.getElementById('tables-search-clear');
            const countEl = document.getElementById('tables-search-count');
            if (clearBtn) {
                if (q.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }

            let filtered = allTablesCache;

            // Filtro por píldora
            if (currentTableFilter === 'salon') {
                filtered = filtered.filter(s => parseInt(s.table_number, 10) !== 999);
            } else if (currentTableFilter === 'takeout') {
                filtered = filtered.filter(s => parseInt(s.table_number, 10) === 999);
            }

            // Filtro por texto
            if (q) {
                filtered = filtered.filter(sale => {
                    let tNumberStr = String(sale.table_number);
                    if (tNumberStr === '999') tNumberStr = 'llevar';
                    const mesaLabel = 'mesa ' + tNumberStr;
                    const clientName = (sale.client_name || '').toLowerCase();
                    const hasItemMatch = (sale.items || []).some(item => (item.name || '').toLowerCase().includes(q));

                    return tNumberStr.includes(q) || mesaLabel.includes(q) || clientName.includes(q) || hasItemMatch;
                });
            }

            if (countEl) {
                if (q.length > 0) {
                    countEl.classList.remove('hidden');
                    countEl.textContent = `${filtered.length} ${filtered.length === 1 ? 'comanda' : 'comandas'}`;
                } else {
                    countEl.classList.add('hidden');
                }
            }

            renderTables(filtered);
        }

        function clearTablesSearch() {
            const input = document.getElementById('tables-search');
            const clearBtn = document.getElementById('tables-search-clear');
            const countEl = document.getElementById('tables-search-count');
            if (input) {
                input.value = '';
                if (clearBtn) clearBtn.classList.add('hidden');
                if (countEl) countEl.classList.add('hidden');
                input.focus();
                filterTables();
            }
        }

        function renderTables(sales) {
            const grid = document.getElementById('tables-grid');
            grid.innerHTML = '';
            
            if(allTablesCache.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full flex flex-col items-center justify-center py-20 text-slate-400">
                        <div class="w-20 h-20 rounded-3xl bg-pink-50 text-pink-500 flex items-center justify-center mb-4 shadow-sm border border-pink-100">
                            <i data-lucide="coffee" class="w-10 h-10 stroke-[1.75]"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 font-heading mb-1">Todas las mesas están libres</h3>
                        <p class="text-xs text-slate-400">No hay comandas activas pendientes de cobro.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            if(sales.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-400">
                        <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                            <i data-lucide="search-x" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-700 font-heading mb-1">Sin coincidencias</h3>
                        <p class="text-xs text-slate-400">No se encontraron mesas activas para tu búsqueda.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            sales.forEach(sale => {
                let itemsHtml = '';
                sale.items.forEach(item => {
                    itemsHtml += `
                        <div class="flex justify-between items-center text-xs py-1.5 border-b border-slate-100/80 last:border-0">
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <span class="w-5 h-5 rounded-md bg-pink-50 text-pink-700 font-extrabold text-[10px] flex items-center justify-center font-heading shrink-0">${item.quantity}</span>
                                <span class="text-slate-700 font-medium truncate">${escapeHtml(item.name)}</span>
                            </div>
                            <span class="font-bold text-slate-900 font-heading shrink-0">$${parseFloat(item.subtotal).toFixed(2)}</span>
                        </div>
                    `;
                });

                let tNumberStr = sale.table_number;
                if(tNumberStr === 999 || tNumberStr === '999') tNumberStr = 'Llevar';
                const isTakeaway = tNumberStr === 'Llevar';

                grid.innerHTML += `
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col overflow-hidden group">
                        <!-- Card Header -->
                        <div class="bg-gradient-to-r ${isTakeaway ? 'from-purple-50/80 to-slate-50' : 'from-pink-50/80 to-amber-50/50'} border-b border-slate-100 px-5 py-4 flex justify-between items-center">
                            <div class="flex items-center gap-3 text-slate-800">
                                <div class="w-10 h-10 rounded-2xl bg-white text-pink-600 flex items-center justify-center shadow-xs border border-white/80 group-hover:scale-105 transition-transform">
                                    <i data-lucide="${isTakeaway ? 'shopping-bag' : 'armchair'}" class="w-5 h-5 stroke-[2]"></i>
                                </div>
                                <div>
                                    <span class="font-extrabold text-base text-slate-900 font-heading block leading-tight">${isTakeaway ? 'Para Llevar' : 'Mesa ' + escapeHtml(tNumberStr)}</span>
                                    <span class="text-[10px] text-slate-400 font-semibold">Comanda activa</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-extrabold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Pendiente</span>
                            </div>
                        </div>

                        ${sale.client_name ? `
                        <div class="px-5 pt-3 pb-1 border-b border-slate-50 bg-slate-50/30">
                            <p class="text-xs text-slate-600 font-semibold flex items-center gap-1.5">
                                <i data-lucide="user" class="w-3.5 h-3.5 text-pink-500"></i> 
                                <span>${escapeHtml(sale.client_name)}</span>
                            </p>
                        </div>` : ''}

                        <!-- Card Items -->
                        <div class="p-5 flex-1 flex flex-col gap-1 overflow-y-auto max-h-48">
                            ${itemsHtml}
                        </div>

                        <div class="px-5 pb-3">
                            <button type="button" onclick="openAddItemsModal(${Number(sale.id)})" class="btn-soft-pink w-full py-2.5 px-3 rounded-2xl text-xs font-extrabold flex items-center justify-center gap-1.5 transition-colors" aria-label="Agregar productos a ${isTakeaway ? 'la orden para llevar' : 'la mesa ' + escapeHtml(tNumberStr)}">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                <span>Agregar productos</span>
                            </button>
                        </div>

                        <!-- Card Footer -->
                        <div class="bg-slate-50/70 px-5 py-3.5 border-t border-slate-100 flex justify-between items-center">
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Total Mesa</p>
                                <p class="font-black text-xl text-slate-900 font-heading leading-tight">$${parseFloat(sale.total_amount).toFixed(2)}</p>
                            </div>
                            ${CAN_VOID ? `
                            <button onclick="voidSale(${Number(sale.id)})" title="Anular orden" class="ml-auto mr-2 text-slate-400 hover:text-rose-600 p-2 rounded-xl hover:bg-rose-50 transition-colors">
                                <i data-lucide="ban" class="w-4 h-4"></i>
                            </button>` : ''}
                            <button onclick="openPaymentModal(${Number(sale.id)}, ${Number(sale.total_amount)})" class="btn-sweet-accent text-white px-4 py-2.5 rounded-2xl text-xs font-bold shadow-md transition-all flex items-center gap-1.5">
                                <span>Cobrar</span>
                                <i data-lucide="arrow-right" class="w-4 h-4 stroke-[2.2]"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            lucide.createIcons();
        }

        /* ---------- Agregar productos a una comanda pendiente ---------- */
        // quantities: product id -> units to append. Stock is checked again on the
        // server, which deducts it exactly like a new ticket.
        const addItemsState = { saleId: null, products: [], quantities: new Map(), busy: false };

        function tableLabel(sale) {
            const n = String(sale?.table_number ?? '');
            return n === '999' ? 'Para Llevar' : 'Mesa ' + n;
        }

        async function openAddItemsModal(saleId) {
            const sale = allTablesCache.find(s => Number(s.id) === Number(saleId));
            if (!sale) return;
            Object.assign(addItemsState, { saleId: Number(saleId), products: [], quantities: new Map(), busy: false });
            document.getElementById('add-items-subtitle').textContent = `${tableLabel(sale)} · Total actual $${parseFloat(sale.total_amount).toFixed(2)}`;
            document.getElementById('add-items-search').value = '';
            document.getElementById('add-items-list').innerHTML = '<div class="flex flex-col items-center justify-center py-10"><div class="loader mb-3"></div><p class="text-xs font-semibold text-slate-600">Cargando productos...</p></div>';
            updateAddItemsFooter();
            document.getElementById('add-items-modal').classList.remove('hidden');
            setTimeout(() => document.getElementById('add-items-search').focus(), 50);
            await loadAddItemsProducts();
        }

        async function loadAddItemsProducts() {
            try {
                const res = await fetch('api/products.php');
                const data = await res.json();
                if (!res.ok || !Array.isArray(data)) throw new Error((data && data.error) || 'Error');
                addItemsState.products = data.map(p => ({
                    id: Number(p.id), name: p.name, category: p.category || '',
                    price: parseFloat(p.price) || 0, stock: parseInt(p.stock, 10) || 0
                }));
                // Drop selections that exceed the refreshed stock
                addItemsState.quantities.forEach((qty, id) => {
                    const product = addItemsState.products.find(p => p.id === id);
                    if (!product || product.stock <= 0) addItemsState.quantities.delete(id);
                    else if (qty > product.stock) addItemsState.quantities.set(id, product.stock);
                });
                renderAddItemsList();
            } catch (e) {
                console.error('Could not load products', e);
                document.getElementById('add-items-list').innerHTML = '<p class="text-center text-rose-700 py-8 text-sm font-semibold">No se pudieron cargar los productos.</p>';
            }
        }

        function closeAddItemsModal() {
            document.getElementById('add-items-modal').classList.add('hidden');
            addItemsState.saleId = null;
        }

        function renderAddItemsList() {
            const list = document.getElementById('add-items-list');
            const q = document.getElementById('add-items-search').value.trim().toLowerCase();
            const items = addItemsState.products.filter(p => !q || p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q));
            if (items.length === 0) {
                list.innerHTML = '<p class="text-center text-slate-600 py-8 text-sm font-semibold">No hay productos que coincidan.</p>';
                updateAddItemsFooter();
                return;
            }
            list.innerHTML = items.map(p => {
                const qty = addItemsState.quantities.get(p.id) || 0;
                const name = escapeHtml(p.name);
                const out = p.stock <= 0;
                return `
                    <div class="flex items-center gap-3 py-2.5 border-b border-slate-100 last:border-0 ${out ? 'opacity-60' : ''}">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate" title="${name}">${name}</p>
                            <p class="text-xs text-slate-600 font-semibold">$${p.price.toFixed(2)} · ${out ? 'Agotado' : p.stock + ' disp.'}</p>
                        </div>
                        <div class="cart-stepper shrink-0">
                            <button type="button" data-action="dec" data-id="${p.id}" aria-label="Quitar una unidad de ${name}" ${qty === 0 ? 'disabled' : ''} class="text-slate-600 hover:text-pink-700 disabled:opacity-40">
                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                            </button>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" value="${qty}" data-id="${p.id}" aria-label="Cantidad de ${name}" ${out ? 'disabled' : ''} class="w-9 text-center text-sm font-black text-slate-800 bg-transparent focus:outline-none focus:ring-2 focus:ring-pink-300 rounded-md">
                            <button type="button" data-action="inc" data-id="${p.id}" aria-label="Agregar una unidad de ${name}" ${qty >= p.stock ? 'disabled' : ''} class="text-slate-600 hover:text-pink-700 disabled:opacity-40">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>`;
            }).join('');
            lucide.createIcons({ root: list });
            updateAddItemsFooter();
        }

        // Whole units between 0 and the available stock.
        function setAddItemQuantity(productId, qty) {
            const product = addItemsState.products.find(p => p.id === productId);
            if (!product) return;
            const clean = Math.max(0, Math.min(product.stock, Math.floor(Number(qty) || 0)));
            if (clean === 0) addItemsState.quantities.delete(productId);
            else addItemsState.quantities.set(productId, clean);
            if (Number(qty) > product.stock) showToast(`Solo hay ${product.stock} disponibles de ${product.name}`, 'error');
        }

        function updateAddItemsFooter() {
            let units = 0, total = 0;
            addItemsState.quantities.forEach((qty, id) => {
                const product = addItemsState.products.find(p => p.id === id);
                if (!product) return;
                units += qty;
                total += qty * product.price;
            });
            document.getElementById('add-items-count').textContent = `${units} ${units === 1 ? 'artículo' : 'artículos'} a agregar`;
            document.getElementById('add-items-total').textContent = '+$' + total.toFixed(2);
            document.getElementById('add-items-submit').disabled = units === 0 || addItemsState.busy;
        }

        async function submitAddItems() {
            if (addItemsState.busy || !addItemsState.saleId) return;
            const items = Array.from(addItemsState.quantities, ([id, quantity]) => ({ id, quantity }));
            if (items.length === 0) return;
            addItemsState.busy = true;
            updateAddItemsFooter();
            try {
                const res = await fetch('api/sales.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add_items', id: addItemsState.saleId, items })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    showToast('Productos agregados a la comanda');
                    closeAddItemsModal();
                    loadPendingTables();
                } else {
                    showToast(data.error || 'No se pudieron agregar los productos', 'error');
                    if (res.status === 409) {
                        // Stock changed or the ticket was paid/voided meanwhile
                        loadPendingTables();
                        if (data.product_id) await loadAddItemsProducts();
                        else closeAddItemsModal();
                    }
                }
            } catch (e) {
                console.error('Add items failed', e);
                showToast('Error de conexión', 'error');
            } finally {
                addItemsState.busy = false;
                updateAddItemsFooter();
            }
        }

        document.getElementById('add-items-search').addEventListener('input', renderAddItemsList);
        document.getElementById('add-items-list').addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const id = Number(btn.dataset.id);
            const current = addItemsState.quantities.get(id) || 0;
            setAddItemQuantity(id, current + (btn.dataset.action === 'inc' ? 1 : -1));
            renderAddItemsList();
        });
        document.getElementById('add-items-list').addEventListener('change', (e) => {
            const input = e.target.closest('input[data-id]');
            if (!input) return;
            setAddItemQuantity(Number(input.dataset.id), input.value.replace(/\D/g, ''));
            renderAddItemsList();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('add-items-modal').classList.contains('hidden')) closeAddItemsModal();
        });

        function openPaymentModal(saleId, total) {
            document.getElementById('pay-sale-id').value = saleId;
            document.getElementById('pay-modal-total').innerText = '$' + parseFloat(total).toFixed(2);
            renderPaymentSummary(allTablesCache.find(s => Number(s.id) === Number(saleId)), total);
            document.getElementById('payment-modal').classList.remove('hidden');
        }

        // Read-only list of the ticket lines. A pending ticket already discounted
        // stock, so removing lines would need a server-side partial void.
        function renderPaymentSummary(sale, total) {
            const summary = document.getElementById('pay-summary');
            const list = document.getElementById('pay-summary-list');
            const items = (sale && sale.items) || [];
            const units = items.reduce((acc, item) => acc + Number(item.quantity || 0), 0);
            const totalText = '$' + parseFloat(total).toFixed(2);
            list.innerHTML = items.length === 0
                ? '<p class="text-xs text-slate-600 py-2">No hay productos en esta comanda.</p>'
                : items.map(item => {
                    const qty = Number(item.quantity) || 0;
                    const subtotal = parseFloat(item.subtotal) || 0;
                    const unit = qty > 0 ? subtotal / qty : 0;
                    return `
                        <div class="order-summary-line">
                            <span class="text-sm font-bold text-slate-800 leading-snug min-w-0 truncate">${escapeHtml(item.name)}</span>
                            <span class="text-sm font-black text-slate-900 font-heading text-right">$${subtotal.toFixed(2)}</span>
                            <span class="text-xs text-slate-600 font-semibold col-span-2">${qty} × $${unit.toFixed(2)}</span>
                        </div>`;
                }).join('');
            document.getElementById('pay-summary-total').textContent = totalText;
            // Total repeated only on mobile (collapsed bubble); desktop shows it in the footer
            document.getElementById('pay-summary-count').innerHTML = `${units} ${units === 1 ? 'artículo' : 'artículos'}<span class="md:hidden"> · ${escapeHtml(totalText)}</span>`;
            summary.classList.add('is-collapsed');
            document.getElementById('pay-summary-toggle').setAttribute('aria-expanded', 'false');
        }

        document.getElementById('pay-summary-toggle').addEventListener('click', () => {
            const collapsed = document.getElementById('pay-summary').classList.toggle('is-collapsed');
            document.getElementById('pay-summary-toggle').setAttribute('aria-expanded', String(!collapsed));
        });

        function closePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
            document.getElementById('payment-reference').value = '';
        }

        function toggleReferenceField() {
            const pmEl = document.querySelector('input[name="payment_method"]:checked');
            const refContainer = document.getElementById('reference-field-container');
            if(pmEl && (pmEl.value === 'transfer' || pmEl.value === 'pagomovil')) {
                refContainer.classList.remove('hidden');
            } else {
                refContainer.classList.add('hidden');
            }
        }

        async function submitPayment() {
            const saleId = document.getElementById('pay-sale-id').value;
            const pmEl = document.querySelector('input[name="payment_method"]:checked');
            const paymentMethod = pmEl ? pmEl.value : 'cash';
            const reference = document.getElementById('payment-reference').value.trim();

            if((paymentMethod === 'transfer' || paymentMethod === 'pagomovil') && !reference) {
                showToast('Debes ingresar el número de referencia', 'error');
                return;
            }
            
            try {
                const res = await fetch('api/sales.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: saleId,
                        status: 'paid',
                        payment_method: paymentMethod,
                        reference_number: reference || null
                    })
                });
                
                if(res.ok) {
                    showToast('Pago registrado correctamente');
                    closePaymentModal();
                    loadPendingTables();
                    if (window.SweetReceipt) SweetReceipt.show(saleId, { autoClose: true });
                } else {
                    const data = await res.json().catch(() => ({}));
                    showToast(data.error || 'Error al registrar pago', 'error');
                }
            } catch(e) {
                showToast('Error de conexión', 'error');
            }
        }
    </script>
</body>
</html>
