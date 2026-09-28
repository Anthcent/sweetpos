<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos & Materiales - Sweet POS</title>
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

    <!-- Sidebar de Navegacion (dinamico por rol) -->
    <?php require '_sidebar.php'; ?>

    <!-- Área Principal (Materiales) -->
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <div class="absolute top-0 right-0 w-[26rem] h-[26rem] bg-pink-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center shadow-xs border border-amber-200/70 shrink-0">
                    <i data-lucide="wheat" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Insumos & Materias Primas</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-1 sm:line-clamp-none">Control de stock de ingredientes para la elaboración</p>
                </div>
            </div>

            <!-- Acciones: Buscador en vivo y Botón Nuevo Insumo -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <!-- Buscador de insumos -->
                <div class="search-bar-sweet w-full sm:w-80">
                    <div class="search-icon-badge">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <input 
                        type="text" 
                        id="materials-search" 
                        placeholder="Buscar insumo o ID..." 
                        autocomplete="off">
                    <span id="materials-search-count" class="hidden text-[10px] font-extrabold text-pink-700 bg-pink-50 px-2 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                    <button 
                        type="button" 
                        id="materials-search-clear" 
                        onclick="clearMaterialsSearch()" 
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
                <button onclick="openModal()" class="btn-sweet-accent text-white px-4 py-2 sm:py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-md shrink-0">
                    <i data-lucide="plus" class="w-4 h-4 stroke-[2.2]"></i>
                    <span>Nuevo Insumo</span>
                </button>
            </div>
        </header>

        <!-- BARRA DEDICADA DE FILTROS DE INSUMOS (100% VISIBLE Y DINÁMICA) -->
        <div class="px-4 sm:px-6 lg:px-8 pt-3.5 pb-1 shrink-0 z-10">
            <div class="flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
                <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1.5 mr-1 shrink-0 font-heading">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Filtrar:</span>
                </span>

                <button type="button" data-filter="all" class="mat-filter-btn filter-pill-sweet active">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span>Todos</span>
                    <span class="pill-badge" id="mat-count-all">0</span>
                </button>

                <button type="button" data-filter="in_stock" class="mat-filter-btn filter-pill-sweet">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-500"></i>
                    <span class="text-emerald-800">Stock Óptimo</span>
                    <span class="pill-badge !bg-emerald-100 !text-emerald-800" id="mat-count-ok">0</span>
                </button>

                <button type="button" data-filter="low_stock" class="mat-filter-btn filter-pill-sweet hover:border-amber-300">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-500"></i>
                    <span class="text-amber-800">Bajo Mínimo</span>
                    <span class="pill-badge !bg-amber-100 !text-amber-800" id="mat-count-low">0</span>
                </button>

                <button type="button" data-filter="out_of_stock" class="mat-filter-btn filter-pill-sweet hover:border-rose-300">
                    <i data-lucide="x-circle" class="w-3.5 h-3.5 text-rose-500"></i>
                    <span class="text-rose-800">Agotados</span>
                    <span class="pill-badge !bg-rose-100 !text-rose-800" id="mat-count-out">0</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="w-full min-w-[560px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Insumo</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Unidad</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Stock Actual</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Stock Mínimo</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="materials-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="5" class="text-center py-12 text-slate-400 font-medium">Cargando insumos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo/Editar Material -->
    <div id="modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                        <i data-lucide="wheat" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading" id="modal-title">Agregar Insumo</h3>
                </div>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="material-form" class="p-6 flex flex-col gap-4">
                <input type="hidden" id="m-id" value="">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nombre del Insumo</label>
                    <input type="text" id="m-name" required placeholder="Ej. Leche entera, Cacao en polvo" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-semibold">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Unidad de Medida</label>
                        <select id="m-unit" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-medium">
                            <option value="unidades">unidades</option>
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                            <option value="L">L</option>
                            <option value="ml">ml</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Actual</label>
                        <input type="number" id="m-stock" value="0" min="0" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-base font-bold">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Mínimo (Alerta de reposición)</label>
                    <input type="number" id="m-min-stock" value="0" min="0" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-base font-bold">
                </div>
                <div id="m-note-container" class="hidden">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Motivo del ajuste de stock (opcional)</label>
                    <input type="text" id="m-note" maxlength="200" placeholder="Ej. Conteo físico, merma, vencimiento" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm">
                    <p class="text-[11px] text-slate-400 mt-1">Si cambias el stock actual se registra como ajuste en el historial de movimientos. Para ingresos de mercancía usa "Registrar compra".</p>
                </div>
                <div id="m-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="save" class="w-4 h-4 stroke-[2.2]"></i> Guardar Insumo
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Registrar Compra de Insumo -->
    <div id="purchase-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="shopping-cart" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading">
                        Compra: <span id="purchase-material-name" class="text-pink-600 font-bold"></span>
                    </h3>
                </div>
                <button type="button" onclick="closePurchaseModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="purchase-form" class="p-6 flex flex-col gap-4">
                <input type="hidden" id="p-material-id" value="">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cantidad (<span id="purchase-unit"></span>)</label>
                        <input type="number" id="p-quantity" min="0.01" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-base font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Costo total ($, opcional)</label>
                        <input type="number" id="p-total-cost" min="0" step="0.01" placeholder="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-base font-bold">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nota (opcional)</label>
                    <input type="text" id="p-note" maxlength="200" placeholder="Ej. Proveedor, número de factura" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm">
                </div>
                <div id="p-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="check" class="w-4 h-4 stroke-[2.2]"></i> Registrar Compra
                </button>
            </form>
        </div>
    </div>

    <script>
        const CAN_DELETE = <?= canAccess('inventario_delete') ? 'true' : 'false' ?>;
        let materialsCache = [];
        let currentMatFilter = 'all';

        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('materials-search');
            if (searchInput) {
                searchInput.addEventListener('input', filterMaterials);
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        clearMaterialsSearch();
                        searchInput.blur();
                    }
                });
            }

            // Keyboard shortcut '/' to search
            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== searchInput) {
                    const hasModal = document.querySelector('.fixed:not(.hidden) input');
                    if (!hasModal && searchInput) {
                        e.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            });

            // Filter pills click listeners
            document.querySelectorAll('.mat-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.mat-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentMatFilter = btn.dataset.filter || 'all';
                    filterMaterials();
                });
            });

            loadMaterials().then(openPurchaseFromQuery);
        });

        // Deep link from panel.php: materiales.php?purchase=<material_id>&qty=<quantity>
        function openPurchaseFromQuery() {
            const params = new URLSearchParams(location.search);
            if (!params.has('purchase')) return;
            const id = Number(params.get('purchase'));
            if (id && materialsCache.some(m => m.id === id)) {
                openPurchaseModal(id);
                const qty = parseFloat(params.get('qty'));
                if (qty > 0) document.getElementById('p-quantity').value = qty.toFixed(2);
            }
            history.replaceState(null, '', location.pathname);
        }

        async function loadMaterials() {
            const table = document.getElementById('materials-table');
            try {
                const res = await fetch('api/materials.php');
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                materialsCache = data.map(m => ({ ...m, id: Number(m.id) }));
                updateMatCounts(materialsCache);
                filterMaterials();
            } catch (e) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-rose-500 font-medium">Error al cargar insumos.</td></tr>';
            }
        }

        function updateMatCounts(materials) {
            const total = materials.length;
            const ok = materials.filter(m => parseFloat(m.stock) > parseFloat(m.min_stock)).length;
            const low = materials.filter(m => parseFloat(m.stock) <= parseFloat(m.min_stock) && parseFloat(m.stock) > 0).length;
            const out = materials.filter(m => parseFloat(m.stock) <= 0).length;

            const elAll = document.getElementById('mat-count-all');
            const elOk = document.getElementById('mat-count-ok');
            const elLow = document.getElementById('mat-count-low');
            const elOut = document.getElementById('mat-count-out');

            if (elAll) elAll.textContent = total;
            if (elOk) elOk.textContent = ok;
            if (elLow) elLow.textContent = low;
            if (elOut) elOut.textContent = out;
        }

        function filterMaterials() {
            const q = (document.getElementById('materials-search')?.value || '').trim().toLowerCase();
            const clearBtn = document.getElementById('materials-search-clear');
            const countEl = document.getElementById('materials-search-count');
            if (clearBtn) {
                if (q.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }

            let filtered = materialsCache;

            // Filtro por píldora
            if (currentMatFilter === 'in_stock') {
                filtered = filtered.filter(m => parseFloat(m.stock) > parseFloat(m.min_stock));
            } else if (currentMatFilter === 'low_stock') {
                filtered = filtered.filter(m => parseFloat(m.stock) <= parseFloat(m.min_stock) && parseFloat(m.stock) > 0);
            } else if (currentMatFilter === 'out_of_stock') {
                filtered = filtered.filter(m => parseFloat(m.stock) <= 0);
            }

            // Filtro por texto de búsqueda
            if (q) {
                filtered = filtered.filter(m => 
                    (m.name || '').toLowerCase().includes(q) || 
                    (m.unit || '').toLowerCase().includes(q) || 
                    String(m.id).includes(q)
                );
            }

            if (countEl) {
                if (q.length > 0) {
                    countEl.classList.remove('hidden');
                    countEl.textContent = `${filtered.length} ${filtered.length === 1 ? 'insumo' : 'insumos'}`;
                } else {
                    countEl.classList.add('hidden');
                }
            }

            renderMaterialsTable(filtered);
        }

        function clearMaterialsSearch() {
            const input = document.getElementById('materials-search');
            const clearBtn = document.getElementById('materials-search-clear');
            const countEl = document.getElementById('materials-search-count');
            if (input) {
                input.value = '';
                if (clearBtn) clearBtn.classList.add('hidden');
                if (countEl) countEl.classList.add('hidden');
                input.focus();
                filterMaterials();
            }
        }

        function renderMaterialsTable(materials) {
            const table = document.getElementById('materials-table');
            table.innerHTML = '';
            if (materials.length === 0) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-slate-400 font-medium">No se encontraron insumos coincidentes.</td></tr>';
                return;
            }

            materials.forEach(m => {
                const low = parseFloat(m.stock) <= parseFloat(m.min_stock);
                table.innerHTML += `
                    <tr class="hover:bg-pink-50/30 transition-colors group">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 border border-amber-100 shadow-xs">
                                    <i data-lucide="wheat" class="w-5 h-5 stroke-[1.8]"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 font-heading block leading-tight cursor-pointer hover:text-pink-600 transition-colors" onclick="editMaterial(${Number(m.id)})">${escapeHtml(m.name)}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">ID #${m.id}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">${escapeHtml(m.unit)}</span>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold ${low ? 'stock-pill low-stock' : 'stock-pill in-stock'}">
                                ● ${parseFloat(m.stock).toFixed(2)} ${escapeHtml(m.unit)}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-xs text-slate-500 font-bold font-heading">
                            ${parseFloat(m.min_stock).toFixed(2)} ${escapeHtml(m.unit)}
                        </td>
                        <td class="px-6 py-3.5 text-right flex items-center justify-end gap-1.5">
                            <button onclick="openPurchaseModal(${Number(m.id)})" title="Registrar compra" class="text-slate-400 hover:text-emerald-600 p-2 rounded-xl hover:bg-emerald-50 transition-colors">
                                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                            </button>
                            <button onclick="editMaterial(${Number(m.id)})" title="Editar Insumo" class="text-slate-400 hover:text-pink-600 p-2 rounded-xl hover:bg-pink-50 transition-colors">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            ${CAN_DELETE ? `
                            <button onclick="deleteMaterial(${Number(m.id)})" title="Eliminar Insumo" class="text-slate-400 hover:text-rose-600 p-2 rounded-xl hover:bg-rose-50 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>` : ''}
                        </td>
                    </tr>
                `;
            });
            lucide.createIcons();
        }

        function openModal() {
            document.getElementById('modal-title').innerText = 'Nuevo Insumo';
            document.getElementById('m-id').value = '';
            document.getElementById('m-name').value = '';
            document.getElementById('m-unit').value = 'kg';
            document.getElementById('m-stock').value = '0';
            document.getElementById('m-min-stock').value = '0';
            document.getElementById('m-note').value = '';
            document.getElementById('m-note-container').classList.add('hidden');
            document.getElementById('m-error').classList.add('hidden');
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function editMaterial(id) {
            const m = materialsCache.find(x => x.id === Number(id));
            if (!m) return;
            document.getElementById('modal-title').innerText = 'Editar Insumo';
            document.getElementById('m-id').value = m.id;
            document.getElementById('m-name').value = m.name;
            document.getElementById('m-unit').value = m.unit;
            document.getElementById('m-stock').value = m.stock;
            document.getElementById('m-min-stock').value = m.min_stock;
            document.getElementById('m-note').value = '';
            document.getElementById('m-note-container').classList.remove('hidden');
            document.getElementById('m-error').classList.add('hidden');
            document.getElementById('modal').classList.remove('hidden');
        }

        document.getElementById('material-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Guardando...';

            const id = document.getElementById('m-id').value;
            const payload = {
                id: id ? parseInt(id) : undefined,
                name: document.getElementById('m-name').value.trim(),
                unit: document.getElementById('m-unit').value,
                stock: parseFloat(document.getElementById('m-stock').value),
                min_stock: parseFloat(document.getElementById('m-min-stock').value),
                note: document.getElementById('m-note').value.trim() || undefined
            };
            const errDiv = document.getElementById('m-error');
            errDiv.classList.add('hidden');

            const method = id ? 'PUT' : 'POST';
            try {
                const res = await fetch('api/materials.php', {
                    method,
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    closeModal();
                    await loadMaterials();
                } else {
                    errDiv.innerText = data.error || 'No se pudo guardar el insumo.';
                    errDiv.classList.remove('hidden');
                }
            } catch (err) {
                errDiv.innerText = 'Error de conexión.';
                errDiv.classList.remove('hidden');
            } finally {
                btn.innerHTML = origHTML;
            }
        });

        async function deleteMaterial(id) {
            if (!confirm('¿Seguro que deseas eliminar este insumo?')) return;
            const res = await fetch('api/materials.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id })
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                alert(data.error || 'No se pudo eliminar el insumo.');
            }
            await loadMaterials();
        }

        // ---------- Compra de insumos (suma stock y queda registrada como movimiento) ----------

        function openPurchaseModal(id) {
            const m = materialsCache.find(x => x.id === Number(id));
            if (!m) return;
            document.getElementById('p-material-id').value = m.id;
            document.getElementById('purchase-material-name').innerText = m.name;
            document.getElementById('purchase-unit').innerText = m.unit;
            document.getElementById('p-quantity').value = '';
            document.getElementById('p-total-cost').value = '';
            document.getElementById('p-note').value = '';
            document.getElementById('p-error').classList.add('hidden');
            document.getElementById('purchase-modal').classList.remove('hidden');
            document.getElementById('p-quantity').focus();
        }

        function closePurchaseModal() {
            document.getElementById('purchase-modal').classList.add('hidden');
        }

        document.getElementById('purchase-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const errDiv = document.getElementById('p-error');
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Registrando...';
            btn.disabled = true;
            errDiv.classList.add('hidden');

            const totalCostRaw = document.getElementById('p-total-cost').value;
            const payload = {
                action: 'purchase',
                id: parseInt(document.getElementById('p-material-id').value),
                quantity: parseFloat(document.getElementById('p-quantity').value),
                total_cost: totalCostRaw === '' ? null : parseFloat(totalCostRaw),
                note: document.getElementById('p-note').value.trim() || null
            };

            try {
                const res = await fetch('api/materials.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    closePurchaseModal();
                    await loadMaterials();
                } else {
                    errDiv.innerText = data.error || 'No se pudo registrar la compra.';
                    errDiv.classList.remove('hidden');
                }
            } catch (err) {
                errDiv.innerText = 'Error de conexión.';
                errDiv.classList.remove('hidden');
            } finally {
                btn.innerHTML = origHTML;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
