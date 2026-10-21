<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Sweet POS</title>
    <script src="assets/tailwindcss.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/lucide.min.js"></script>
    <script src="assets/product-form.js"></script>
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

    <!-- Área Principal (Inventario) -->
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <div class="absolute top-0 right-0 w-[26rem] h-[26rem] bg-pink-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="package" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Gestión de Inventario</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-2 sm:line-clamp-none">Catálogo de helados, postres, precios & existencias</p>
                </div>
            </div>

            <!-- Acciones: Buscador en vivo y Botón Nuevo Producto -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <!-- Buscador de inventario -->
                <div class="search-bar-sweet w-full sm:w-80">
                    <div class="search-icon-badge">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <input 
                        type="text" 
                        id="inventory-search" 
                        placeholder="Buscar producto o ID..." 
                        autocomplete="off">
                    <span id="inventory-search-count" class="hidden text-[10px] font-extrabold text-pink-700 bg-pink-50 px-2 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                    <button 
                        type="button" 
                        id="inventory-search-clear" 
                        onclick="clearInventorySearch()" 
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
                <button onclick="openProductModal()" class="btn-sweet-accent text-white px-4 py-2 sm:py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-md shrink-0">
                    <i data-lucide="plus" class="w-4 h-4 stroke-[2.2]"></i>
                    <span>Nuevo Producto</span>
                </button>
            </div>
        </header>

        <!-- BARRA DEDICADA DE FILTROS DE INVENTARIO (100% VISIBLE Y DINÁMICA) -->
        <div class="px-4 sm:px-6 lg:px-8 pt-3.5 pb-1 shrink-0 z-10">
            <div class="filter-scroll-fade flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
                <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1.5 mr-1 shrink-0 font-heading">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Filtrar:</span>
                </span>

                <button type="button" data-filter="all" class="inv-filter-btn filter-pill-sweet active">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span>Todos</span>
                    <span class="pill-badge" id="inv-count-all">0</span>
                </button>

                <button type="button" data-filter="Helados" class="inv-filter-btn filter-pill-sweet">
                    <i data-lucide="ice-cream-cone" class="w-3.5 h-3.5"></i>
                    <span>Helados</span>
                    <span class="pill-badge" id="inv-count-helados">0</span>
                </button>

                <button type="button" data-filter="Postres" class="inv-filter-btn filter-pill-sweet">
                    <i data-lucide="cake-slice" class="w-3.5 h-3.5"></i>
                    <span>Postres</span>
                    <span class="pill-badge" id="inv-count-postres">0</span>
                </button>

                <button type="button" data-filter="Varios" class="inv-filter-btn filter-pill-sweet">
                    <i data-lucide="coffee" class="w-3.5 h-3.5"></i>
                    <span>Café & Más</span>
                    <span class="pill-badge" id="inv-count-varios">0</span>
                </button>

                <button type="button" data-filter="low_stock" class="inv-filter-btn filter-pill-sweet hover:border-amber-300">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-500"></i>
                    <span class="text-amber-800">Bajo Stock</span>
                    <span class="pill-badge !bg-amber-100 !text-amber-800" id="inv-count-low">0</span>
                </button>

                <button type="button" data-filter="out_of_stock" class="inv-filter-btn filter-pill-sweet hover:border-rose-300">
                    <i data-lucide="x-circle" class="w-3.5 h-3.5 text-rose-500"></i>
                    <span class="text-rose-800">Agotados</span>
                    <span class="pill-badge !bg-rose-100 !text-rose-800" id="inv-count-out">0</span>
                </button>

                <?php if (in_array(currentRole(), ['admin', 'gerente'], true)): ?>
                <label class="filter-pill-sweet cursor-pointer shrink-0 select-none" title="Incluir productos desactivados en la lista">
                    <input type="checkbox" id="inv-show-inactive" class="w-3.5 h-3.5 accent-pink-500 cursor-pointer">
                    <i data-lucide="eye-off" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Mostrar inactivos</span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div id="inventory-list" class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div id="inventory-cards" class="mobile-data-list md:hidden">
                    <div class="mobile-data-empty">Cargando inventario...</div>
                </div>
                <div class="hidden md:block overflow-x-auto w-full">
                    <table class="w-full min-w-[700px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Producto</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Categoría</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Precio</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Stock</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold" title="Unidades que alcanzan con los insumos disponibles">Puedes elaborar</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="6" class="text-center py-12 text-slate-400 font-medium">Cargando inventario...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="inventory-pager" class="responsive-pager hidden"></div>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Producto (compartido) -->
    <?php require '_product_modal.php'; ?>

    <!-- Modal Movimiento de Stock: elaborar (con receta), entrada por compra (reventa),
         disminuir con motivo, o ajuste de administrador -->
    <div id="stock-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[94dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100 flex justify-between items-start gap-3 shrink-0">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center shrink-0">
                        <i data-lucide="package" class="w-5 h-5 stroke-[2.2]"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="stock-product-name" class="font-extrabold text-slate-900 font-heading text-base sm:text-lg leading-tight truncate"></h3>
                        <p id="stock-subtitle" class="text-xs text-slate-500 mt-0.5">Movimiento de stock</p>
                    </div>
                </div>
                <button type="button" onclick="closeStockModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 p-2 rounded-lg shrink-0" title="Cerrar">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Summary: current stock and what the recipe allows right now -->
            <div class="grid grid-cols-3 border-b border-slate-100 bg-slate-50/80 shrink-0 divide-x divide-slate-200/70">
                <div class="px-3 sm:px-5 py-3">
                    <span class="block text-[11px] font-bold text-slate-500">Stock actual</span>
                    <strong id="s-sum-stock" class="block text-xl font-black font-heading text-slate-800 leading-tight">—</strong>
                </div>
                <div class="px-3 sm:px-5 py-3">
                    <span class="block text-[11px] font-bold text-slate-500">Puedes elaborar</span>
                    <strong id="s-sum-max" class="block text-xl font-black font-heading text-slate-400 leading-tight">—</strong>
                </div>
                <div class="px-3 sm:px-5 py-3 min-w-0">
                    <span class="block text-[11px] font-bold text-slate-500">Insumo que limita</span>
                    <strong id="s-sum-limit" class="block text-sm font-extrabold text-slate-400 leading-tight mt-1 truncate">—</strong>
                </div>
            </div>

            <form id="stock-form" class="flex flex-col min-h-0 flex-1" novalidate>
                <input type="hidden" id="s-product-id" value="">
                <div class="p-4 sm:p-5 flex flex-col gap-4 overflow-y-auto min-h-0 flex-1">
                    <!-- Action chooser -->
                    <div id="s-actions" role="tablist" aria-label="Tipo de movimiento" class="grid gap-1.5 p-1 bg-slate-100 rounded-xl">
                        <button type="button" role="tab" data-action="produce" class="s-action-btn flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-xs font-bold text-slate-600 transition-colors">
                            <i data-lucide="chef-hat" class="w-4 h-4"></i><span>Elaborar</span>
                        </button>
                        <button type="button" role="tab" data-action="purchase" class="s-action-btn flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-xs font-bold text-slate-600 transition-colors">
                            <i data-lucide="package-plus" class="w-4 h-4"></i><span>Entrada por compra</span>
                        </button>
                        <button type="button" role="tab" data-action="decrease" class="s-action-btn flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-xs font-bold text-slate-600 transition-colors">
                            <i data-lucide="package-minus" class="w-4 h-4"></i><span>Disminuir</span>
                        </button>
                        <button type="button" role="tab" data-action="adjust" class="s-action-btn flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-xs font-bold text-slate-600 transition-colors">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i><span>Ajuste</span>
                        </button>
                    </div>
                    <p id="s-recipe-hint" class="hidden text-[11px] text-slate-500 -mt-2 flex items-start gap-1.5">
                        <i data-lucide="info" class="w-3.5 h-3.5 mt-px shrink-0 text-pink-500"></i>
                        <span>Este producto tiene receta: su stock aumenta solo mediante <strong class="text-slate-700">Elaborar</strong>, que descuenta los insumos.</span>
                    </p>

                    <!-- Elaborar -->
                    <div id="s-panel-produce" class="s-panel hidden flex flex-col gap-3">
                        <div>
                            <label for="s-produce-qty" class="block text-xs font-bold text-slate-700 mb-1.5">Unidades a elaborar</label>
                            <input type="number" id="s-produce-qty" min="1" step="1" inputmode="numeric" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 font-heading text-base font-bold">
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-700 mb-1.5">Insumos necesarios</span>
                            <div id="s-produce-lines" class="flex flex-col gap-1.5"></div>
                        </div>
                        <div id="s-produce-alert" class="hidden text-xs font-medium text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 flex items-start gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-amber-500"></i>
                            <span id="s-produce-alert-text"></span>
                        </div>
                    </div>

                    <!-- Entrada por compra -->
                    <div id="s-panel-purchase" class="s-panel hidden flex flex-col gap-3">
                        <div>
                            <label for="s-purchase-qty" class="block text-xs font-bold text-slate-700 mb-1.5">Unidades recibidas</label>
                            <input type="number" id="s-purchase-qty" min="1" step="1" inputmode="numeric" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 font-heading text-base font-bold">
                        </div>
                        <div>
                            <label for="s-purchase-note" class="block text-xs font-bold text-slate-700 mb-1.5">Nota <span class="text-slate-400 font-semibold">(opcional)</span></label>
                            <input type="text" id="s-purchase-note" maxlength="200" placeholder="Ej. Factura 123, proveedor" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 text-sm">
                        </div>
                    </div>

                    <!-- Disminuir -->
                    <div id="s-panel-decrease" class="s-panel hidden flex flex-col gap-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="s-decrease-qty" class="block text-xs font-bold text-slate-700 mb-1.5">Unidades a retirar</label>
                                <input type="number" id="s-decrease-qty" min="1" step="1" inputmode="numeric" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 font-heading text-base font-bold">
                            </div>
                            <div>
                                <label for="s-decrease-reason" class="block text-xs font-bold text-slate-700 mb-1.5">Motivo</label>
                                <select id="s-decrease-reason" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 text-sm font-medium">
                                    <option value="">Selecciona un motivo</option>
                                    <option value="Merma">Merma</option>
                                    <option value="Vencido">Vencido</option>
                                    <option value="Dañado">Dañado</option>
                                    <option value="Consumo interno">Consumo interno</option>
                                    <option value="Corrección de conteo">Corrección de conteo</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="s-decrease-detail" class="block text-xs font-bold text-slate-700 mb-1.5">Detalle <span id="s-decrease-detail-tag" class="text-slate-400 font-semibold">(opcional)</span></label>
                            <input type="text" id="s-decrease-detail" maxlength="160" placeholder="Ej. Se cayó la bandeja, lote del 12/10" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 text-sm">
                        </div>
                        <p id="s-decrease-hint" class="hidden text-[11px] text-slate-500 flex items-start gap-1.5">
                            <i data-lucide="info" class="w-3.5 h-3.5 mt-px shrink-0 text-slate-400"></i>
                            <span>Los insumos ya usados no se devuelven: solo se descuentan las unidades del producto.</span>
                        </p>
                    </div>

                    <!-- Ajuste (admin) -->
                    <div id="s-panel-adjust" class="s-panel hidden flex flex-col gap-3">
                        <div>
                            <label for="s-adjust-qty" class="block text-xs font-bold text-slate-700 mb-1.5">Cantidad (unidades)</label>
                            <input type="number" id="s-adjust-qty" step="1" inputmode="numeric" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 font-heading text-base font-bold">
                            <p class="text-[11px] text-slate-400 mt-1">Usa un número negativo para restar. Solo para correcciones de administrador; no descuenta insumos.</p>
                        </div>
                        <div>
                            <label for="s-adjust-note" class="block text-xs font-bold text-slate-700 mb-1.5">Nota <span class="text-rose-500 font-semibold">(obligatoria)</span></label>
                            <input type="text" id="s-adjust-note" maxlength="200" placeholder="Ej. Conteo físico del cierre" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300 text-sm">
                        </div>
                    </div>

                    <div id="s-error" class="hidden text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                </div>

                <div class="px-4 sm:px-5 py-3.5 border-t border-slate-100 flex gap-2 shrink-0 bg-white">
                    <button type="button" onclick="closeStockModal()" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                    <button type="submit" id="s-submit" class="btn-sweet-accent flex-1 text-white font-extrabold py-3 rounded-xl transition-all flex justify-center items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="check" class="w-4 h-4 stroke-[2.2]"></i> <span id="s-submit-label">Registrar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CAN_DELETE = <?= canAccess('inventario_delete') ? 'true' : 'false' ?>;
        const IS_ADMIN = <?= currentRole() === 'admin' ? 'true' : 'false' ?>;
        const CAN_EDIT = <?= in_array(currentRole(), ['admin', 'gerente'], true) ? 'true' : 'false' ?>;
        let showInactive = false;
        let allProductsCache = [];
        let currentInvFilter = 'all';
        let invPage = 1;
        let invFiltered = [];

        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            loadInventory();
            
            const sInput = document.getElementById('inventory-search');
            if (sInput) {
                sInput.addEventListener('input', filterInventory);
                sInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        clearInventorySearch();
                        sInput.blur();
                    }
                });
            }

            // Keyboard shortcut '/' to search
            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== sInput) {
                    const hasModal = document.querySelector('.fixed:not(.hidden) input');
                    if (!hasModal && sInput) {
                        e.preventDefault();
                        sInput.focus();
                        sInput.select();
                    }
                }
            });

            // Filter pills click listeners
            document.querySelectorAll('.inv-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.inv-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentInvFilter = btn.dataset.filter || 'all';
                    filterInventory();
                });
            });

            document.getElementById('inv-show-inactive')?.addEventListener('change', (e) => {
                showInactive = e.target.checked;
                loadInventory();
            });

            SweetPagination.onBreakpointChange(() => {
                invPage = 1;
                renderInventoryTable(invFiltered);
            });
        });
        document.addEventListener('product:created', loadInventory);
        document.addEventListener('product:updated', loadInventory);

        function getProductIcon(name, category) {
            name = (name || '').toLowerCase();
            category = (category || '').toLowerCase();
            if (name.includes('vainilla') || name.includes('fresa') || category.includes('helad')) return 'ice-cream-cone';
            if (name.includes('chocolate') || name.includes('cacao')) return 'ice-cream-2';
            if (name.includes('cheesecake') || name.includes('torta') || category.includes('postre')) return 'cake-slice';
            if (name.includes('brownie') || name.includes('cookie') || name.includes('galleta')) return 'cookie';
            if (name.includes('café') || name.includes('cafe')) return 'coffee';
            if (name.includes('malteada') || name.includes('soda')) return 'cup-soda';
            return 'sparkles';
        }

        async function loadInventory() {
            const table = document.getElementById('inventory-table');
            try {
                const res = await fetch('api/products.php' + (showInactive ? '?include_inactive=1' : ''));
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                // PDO/SQLite puede devolver numeros como texto: normalizar para comparar
                allProductsCache = data.map(p => ({
                    ...p,
                    id: Number(p.id),
                    stock: Number(p.stock),
                    recipe_items: Number(p.recipe_items) || 0,
                    can_make: p.can_make === null || p.can_make === undefined ? null : Number(p.can_make),
                    is_active: Number(p.is_active ?? 1) === 1
                }));
                updateInvCounts(allProductsCache);
                filterInventory();
            } catch (e) {
                table.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-rose-500 font-medium">Error al cargar productos.</td></tr>';
                const cards = document.getElementById('inventory-cards');
                if (cards) cards.innerHTML = '<div class="mobile-data-empty text-rose-500">Error al cargar productos.</div>';
            }
        }

        function updateInvCounts(products) {
            const total = products.length;
            const helados = products.filter(p => (p.category || '').toLowerCase().includes('helad')).length;
            const postres = products.filter(p => (p.category || '').toLowerCase().includes('postre')).length;
            const varios = products.filter(p => {
                const c = (p.category || '').toLowerCase();
                return !c.includes('helad') && !c.includes('postre');
            }).length;
            const lowStock = products.filter(p => p.stock > 0 && p.stock <= 5).length;
            const outStock = products.filter(p => p.stock <= 0).length;

            const elAll = document.getElementById('inv-count-all');
            const elHelados = document.getElementById('inv-count-helados');
            const elPostres = document.getElementById('inv-count-postres');
            const elVarios = document.getElementById('inv-count-varios');
            const elLow = document.getElementById('inv-count-low');
            const elOut = document.getElementById('inv-count-out');

            if (elAll) elAll.textContent = total;
            if (elHelados) elHelados.textContent = helados;
            if (elPostres) elPostres.textContent = postres;
            if (elVarios) elVarios.textContent = varios;
            if (elLow) elLow.textContent = lowStock;
            if (elOut) elOut.textContent = outStock;
        }

        function filterInventory(resetPage = true) {
            const q = (document.getElementById('inventory-search')?.value || '').trim().toLowerCase();
            const clearBtn = document.getElementById('inventory-search-clear');
            const countEl = document.getElementById('inventory-search-count');
            if (clearBtn) {
                if (q.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }

            let filtered = allProductsCache;

            // Filtro por píldora seleccionada
            if (currentInvFilter === 'low_stock') {
                filtered = filtered.filter(p => p.stock > 0 && p.stock <= 5);
            } else if (currentInvFilter === 'out_of_stock') {
                filtered = filtered.filter(p => p.stock <= 0);
            } else if (currentInvFilter === 'Helados') {
                filtered = filtered.filter(p => (p.category || '').toLowerCase().includes('helad'));
            } else if (currentInvFilter === 'Postres') {
                filtered = filtered.filter(p => (p.category || '').toLowerCase().includes('postre'));
            } else if (currentInvFilter === 'Varios') {
                filtered = filtered.filter(p => {
                    const c = (p.category || '').toLowerCase();
                    return !c.includes('helad') && !c.includes('postre');
                });
            }

            // Filtro por texto de búsqueda
            if (q) {
                filtered = filtered.filter(p => 
                    (p.name || '').toLowerCase().includes(q) || 
                    (p.category || '').toLowerCase().includes(q) || 
                    String(p.id).includes(q)
                );
            }

            if (countEl) {
                if (q.length > 0) {
                    countEl.classList.remove('hidden');
                    countEl.textContent = `${filtered.length} ${filtered.length === 1 ? 'prod.' : 'prods.'}`;
                } else {
                    countEl.classList.add('hidden');
                }
            }

            invFiltered = filtered;
            if (resetPage) invPage = 1;
            renderInventoryTable(filtered);
        }

        function clearInventorySearch() {
            const input = document.getElementById('inventory-search');
            const clearBtn = document.getElementById('inventory-search-clear');
            const countEl = document.getElementById('inventory-search-count');
            if (input) {
                input.value = '';
                if (clearBtn) clearBtn.classList.add('hidden');
                if (countEl) countEl.classList.add('hidden');
                input.focus();
                filterInventory();
            }
        }

        function inventoryStockHtml(p) {
            if (p.stock <= 0) return '<span class="stock-pill out-stock">Agotado</span>';
            if (p.stock <= 5) return `<span class="stock-pill low-stock">● ${p.stock} uni</span>`;
            return `<span class="stock-pill in-stock">● ${p.stock} uni</span>`;
        }

        // Units makeable now with the available materials (can_make comes from the API).
        function canMakeHtml(p) {
            if (p.recipe_items === 0) {
                return '<span class="text-[11px] font-semibold text-slate-400" title="Producto sin receta">Reventa</span>';
            }
            const units = p.can_make === null ? 0 : p.can_make;
            const limit = p.limiting_material ? escapeHtml(p.limiting_material) : '';
            if (units > 0) {
                return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200" title="${limit ? `Insumo que limita: ${limit}` : ''}">
                    <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i>${units} uni</span>`;
            }
            return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200" title="${limit ? `Falta: ${limit}` : ''}">
                <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>Sin insumos</span>`;
        }

        function inactiveBadgeHtml(p) {
            return p.is_active ? '' : '<span class="ml-1.5 align-middle text-[10px] font-bold px-2 py-0.5 rounded-full border bg-slate-100 text-slate-500 border-slate-200">Inactivo</span>';
        }

        function inventoryCardHtml(p) {
            const iconName = getProductIcon(p.name, p.category);
            const actions = [];
            if (CAN_EDIT) {
                actions.push(`
                    <button onclick="openProductModal(${p.id})" class="mobile-data-action">
                        <i data-lucide="pencil" class="w-4 h-4"></i><span>Editar</span>
                    </button>`);
            }
            actions.push(`
                <button onclick="openStockModal(${p.id})" class="mobile-data-action">
                    <i data-lucide="arrow-up-down" class="w-4 h-4"></i><span>Stock</span>
                </button>`);
            actions.push(`
                <a href="produccion.php?recipe=${p.id}" class="mobile-data-action">
                    <i data-lucide="book-open" class="w-4 h-4"></i><span>Receta</span>
                </a>`);
            if (CAN_DELETE) {
                actions.push(`
                    <button onclick="deleteProduct(${p.id})" class="mobile-data-action mobile-data-action-danger">
                        <i data-lucide="trash-2" class="w-4 h-4"></i><span>Eliminar</span>
                    </button>`);
            }
            return `
                <article class="mobile-data-card ${p.is_active ? '' : 'opacity-60'}">
                    <div class="mobile-data-card-top">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 border border-white shadow-xs ${p.is_active ? '' : 'grayscale'}" style="background-color: ${safeColor(p.image_color)}; color: #9e2d58;">
                                <i data-lucide="${iconName}" class="w-5 h-5 stroke-[1.8]"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="mobile-data-card-title">${escapeHtml(p.name)}${inactiveBadgeHtml(p)}</h3>
                                <p class="mobile-data-card-sub">ID #${p.id} · ${escapeHtml(p.category)}</p>
                            </div>
                        </div>
                        <div class="mobile-data-card-side">
                            <span class="mobile-data-card-price">$${parseFloat(p.price).toFixed(2)}</span>
                            ${inventoryStockHtml(p)}
                            ${canMakeHtml(p)}
                        </div>
                    </div>
                    ${actions.length ? `<div class="mobile-data-actions">${actions.join('')}</div>` : ''}
                </article>`;
        }

        function renderInventoryTable(products) {
            const table = document.getElementById('inventory-table');
            const cards = document.getElementById('inventory-cards');
            const pager = document.getElementById('inventory-pager');
            const state = SweetPagination.paginate(products, invPage, SweetPagination.pageSize(6, 12));
            invPage = state.page;

            if (products.length === 0) {
                table.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-slate-400 font-medium">No se encontraron productos coincidentes.</td></tr>';
                if (cards) cards.innerHTML = '<div class="mobile-data-empty">No se encontraron productos coincidentes.</div>';
                SweetPagination.render(pager, { page: 1, pages: 1, total: 0, perPage: state.perPage, onChange: () => {} });
                return;
            }

            table.innerHTML = state.items.map(p => {
                const iconName = getProductIcon(p.name, p.category);
                let catClass = 'bg-pink-50 text-pink-700 border-pink-200';
                if (p.category === 'Postres') catClass = 'bg-purple-50 text-purple-700 border-purple-200';
                if (p.category === 'Varios') catClass = 'bg-amber-50 text-amber-800 border-amber-200';

                return `
                    <tr class="hover:bg-pink-50/30 transition-colors group ${p.is_active ? '' : 'opacity-60 bg-slate-50/60'}">
                        <td class="px-5 sm:px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 border border-white shadow-xs ${p.is_active ? '' : 'grayscale'}" style="background-color: ${safeColor(p.image_color)}; color: #9e2d58;">
                                    <i data-lucide="${iconName}" class="w-5 h-5 stroke-[1.8]"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 font-heading block leading-tight group-hover:text-pink-600 transition-colors">${escapeHtml(p.name)}${inactiveBadgeHtml(p)}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">ID #${p.id}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5">
                            <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${catClass}">${escapeHtml(p.category)}</span>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5 font-black text-slate-900 font-heading">$${parseFloat(p.price).toFixed(2)}</td>
                        <td class="px-5 sm:px-6 py-3.5">${inventoryStockHtml(p)}</td>
                        <td class="px-5 sm:px-6 py-3.5">${canMakeHtml(p)}</td>
                        <td class="px-5 sm:px-6 py-3.5 text-right">
                            ${CAN_EDIT ? `
                            <button onclick="openProductModal(${p.id})" title="Editar producto" class="text-slate-400 hover:text-pink-600 p-2 rounded-xl hover:bg-pink-50 transition-colors">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>` : ''}
                            <button onclick="openStockModal(${p.id})" title="${p.recipe_items > 0 ? 'Elaborar o disminuir stock' : 'Entrada o disminución de stock'}" class="text-slate-400 hover:text-emerald-600 p-2 rounded-xl hover:bg-emerald-50 transition-colors">
                                <i data-lucide="arrow-up-down" class="w-4 h-4"></i>
                            </button>
                            <a href="produccion.php?recipe=${p.id}" title="Editar receta" class="inline-flex text-slate-400 hover:text-purple-600 p-2 rounded-xl hover:bg-purple-50 transition-colors">
                                <i data-lucide="book-open" class="w-4 h-4"></i>
                            </a>
                            ${CAN_DELETE ? `
                            <button onclick="deleteProduct(${p.id})" title="Eliminar Producto" class="text-slate-400 hover:text-rose-600 p-2 rounded-xl hover:bg-rose-50 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>` : ''}
                        </td>
                    </tr>`;
            }).join('');

            if (cards) cards.innerHTML = state.items.map(inventoryCardHtml).join('');

            SweetPagination.render(pager, {
                page: state.page,
                pages: state.pages,
                total: state.total,
                perPage: state.perPage,
                label: 'productos',
                onChange: (next) => {
                    invPage = next;
                    renderInventoryTable(products);
                    document.getElementById('inventory-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
            });
            lucide.createIcons();
        }

        async function deleteProduct(id) {
            if(confirm('¿Seguro que deseas eliminar este producto del menú?')) {
                const res = await fetch('api/products.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id})
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.deactivated) alert(data.error || data.message);
                loadInventory();
            }
        }

        // ---------- Movimientos de stock ----------
        // Recipe products: stock grows only through "Elaborar" (api/production.php,
        // which deducts materials). Resale products: "Entrada por compra".
        // Both: "Disminuir" with a required reason (negative adjustment; materials
        // are not returned). Admin only: free "Ajuste" (+/-) with a required note.

        const STOCK_SUBMIT_LABELS = {
            produce: 'Elaborar',
            purchase: 'Registrar entrada',
            decrease: 'Disminuir stock',
            adjust: 'Registrar ajuste',
        };
        let stockProduct = null;      // product being edited (from allProductsCache)
        let stockAction = null;       // 'produce' | 'purchase' | 'decrease' | 'adjust'
        let stockRecipeLines = [];    // [{name, unit, material_stock, quantity_required}]
        let stockRecipeLoading = false;

        function formatQty(value) {
            return Number(value || 0).toLocaleString('es-VE', { maximumFractionDigits: 3 });
        }

        function stockActionsFor(p) {
            const hasRecipe = p.recipe_items > 0;
            const actions = hasRecipe ? ['produce'] : ['purchase'];
            actions.push('decrease');
            if (IS_ADMIN) actions.push('adjust');
            return actions;
        }

        function stockMaxMakeable() {
            return stockProduct && stockProduct.can_make !== null ? Math.max(0, Number(stockProduct.can_make)) : 0;
        }

        function renderStockSummary() {
            const p = stockProduct;
            const hasRecipe = p.recipe_items > 0;
            document.getElementById('s-sum-stock').textContent = `${p.stock} uni`;
            document.getElementById('s-decrease-qty').max = String(Math.max(0, p.stock));
            const maxEl = document.getElementById('s-sum-max');
            const limitEl = document.getElementById('s-sum-limit');
            if (!hasRecipe) {
                maxEl.textContent = 'Reventa';
                maxEl.className = 'block text-base font-extrabold font-heading text-slate-400 leading-tight mt-1';
                limitEl.textContent = 'Sin receta';
                limitEl.className = 'block text-sm font-extrabold text-slate-400 leading-tight mt-1 truncate';
                limitEl.title = '';
                return;
            }
            const max = stockMaxMakeable();
            maxEl.textContent = `${max} uni`;
            maxEl.className = `block text-xl font-black font-heading leading-tight ${max > 0 ? 'text-emerald-600' : 'text-rose-600'}`;
            limitEl.textContent = p.limiting_material || '—';
            limitEl.title = p.limiting_material || '';
            limitEl.className = `block text-sm font-extrabold leading-tight mt-1 truncate ${max > 0 ? 'text-slate-800' : 'text-rose-600'}`;
        }

        async function openStockModal(id) {
            const p = allProductsCache.find(x => x.id === Number(id));
            if (!p) return;
            stockProduct = p;
            stockRecipeLines = [];
            const hasRecipe = p.recipe_items > 0;

            document.getElementById('s-product-id').value = p.id;
            document.getElementById('stock-product-name').textContent = p.name;
            document.getElementById('stock-subtitle').textContent = hasRecipe
                ? 'Producto con receta · movimiento de stock'
                : 'Producto de reventa · movimiento de stock';
            document.getElementById('s-recipe-hint').classList.toggle('hidden', !hasRecipe);
            document.getElementById('s-decrease-hint').classList.toggle('hidden', !hasRecipe);

            ['s-produce-qty', 's-purchase-qty', 's-purchase-note', 's-decrease-qty', 's-decrease-detail',
             's-adjust-qty', 's-adjust-note'].forEach(fid => { document.getElementById(fid).value = ''; });
            document.getElementById('s-decrease-reason').value = '';
            updateDecreaseDetailTag();

            const actions = stockActionsFor(p);
            document.querySelectorAll('#s-actions .s-action-btn').forEach(btn => {
                btn.classList.toggle('hidden', !actions.includes(btn.dataset.action));
            });
            document.getElementById('s-actions').style.gridTemplateColumns = `repeat(${actions.length}, minmax(0, 1fr))`;

            renderStockSummary();
            setStockError('');
            setStockAction(actions[0]);
            document.getElementById('stock-modal').classList.remove('hidden');
            lucide.createIcons({ root: document.getElementById('stock-modal') });

            if (hasRecipe) await loadStockRecipe(p.id);
        }

        // Fresh recipe lines (current material stock) for the live preview, and a
        // fresh inventory so can_make / limiting material match those lines.
        async function loadStockRecipe(productId) {
            stockRecipeLoading = true;
            renderProducePreview();
            try {
                const [res] = await Promise.all([
                    fetch('api/production.php?product_id=' + encodeURIComponent(productId)),
                    loadInventory(),
                ]);
                const data = await res.json().catch(() => []);
                if (!res.ok) throw new Error((data && data.error) || 'Error');
                if (!stockProduct || stockProduct.id !== Number(productId)) return;
                const fresh = allProductsCache.find(x => x.id === Number(productId));
                if (fresh) {
                    stockProduct = fresh;
                    renderStockSummary();
                }
                stockRecipeLines = data.map(l => ({
                    name: l.name,
                    unit: l.unit,
                    material_stock: Math.max(0, Number(l.material_stock) || 0),
                    quantity_required: Number(l.quantity_required) || 0,
                }));
            } catch (e) {
                stockRecipeLines = [];
                setStockError('No se pudo cargar la receta del producto.');
            } finally {
                stockRecipeLoading = false;
                renderProducePreview();
            }
        }

        function closeStockModal() {
            document.getElementById('stock-modal').classList.add('hidden');
            stockProduct = null;
        }

        function setStockError(message) {
            const errDiv = document.getElementById('s-error');
            errDiv.textContent = message;
            errDiv.classList.toggle('hidden', !message);
        }

        function setStockAction(action) {
            stockAction = action;
            document.querySelectorAll('#s-actions .s-action-btn').forEach(btn => {
                const active = btn.dataset.action === action;
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
                btn.classList.toggle('bg-white', active);
                btn.classList.toggle('text-pink-700', active);
                btn.classList.toggle('shadow-sm', active);
                btn.classList.toggle('text-slate-600', !active);
            });
            document.querySelectorAll('#stock-form .s-panel').forEach(panel => {
                panel.classList.toggle('hidden', panel.id !== `s-panel-${action}`);
            });
            document.getElementById('s-submit-label').textContent = STOCK_SUBMIT_LABELS[action];
            setStockError('');
            refreshStockSubmitState();
        }

        function produceQuantity() {
            const raw = document.getElementById('s-produce-qty').value;
            const qty = Number(raw);
            return raw !== '' && Number.isInteger(qty) && qty > 0 ? qty : 0;
        }

        // Needed vs available per material for the typed quantity. Mirrors the
        // backend check (needed = quantity_required * qty, rounded to 6 decimals).
        function produceShortages(qty) {
            return stockRecipeLines.map(l => {
                const needed = Math.round(l.quantity_required * qty * 1e6) / 1e6;
                const missing = Math.round((needed - l.material_stock) * 1e6) / 1e6;
                return { ...l, needed, missing: missing > 0 ? missing : 0 };
            });
        }

        function renderProducePreview() {
            const linesEl = document.getElementById('s-produce-lines');
            const alertEl = document.getElementById('s-produce-alert');
            const alertText = document.getElementById('s-produce-alert-text');
            if (!stockProduct || stockProduct.recipe_items === 0) return;

            if (stockRecipeLoading) {
                linesEl.innerHTML = '<div class="text-xs text-slate-400 py-2">Cargando insumos...</div>';
                alertEl.classList.add('hidden');
                refreshStockSubmitState();
                return;
            }

            const qty = produceQuantity();
            const max = stockMaxMakeable();
            const lines = produceShortages(Math.max(qty, 1));
            linesEl.innerHTML = lines.length ? lines.map(l => {
                const ok = qty === 0 || l.missing === 0;
                return `
                    <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl border ${ok ? 'border-slate-200 bg-white' : 'border-rose-200 bg-rose-50/60'}">
                        <div class="min-w-0">
                            <span class="block text-sm font-bold text-slate-800 truncate">${escapeHtml(l.name)}</span>
                            <span class="block text-[11px] text-slate-500">
                                Necesitas <strong class="text-slate-700">${formatQty(qty ? l.needed : 0)} ${escapeHtml(l.unit)}</strong>
                                · disponible ${formatQty(l.material_stock)} ${escapeHtml(l.unit)}
                            </span>
                        </div>
                        ${qty === 0 ? '' : ok
                            ? '<span class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700"><i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>OK</span>'
                            : `<span class="shrink-0 text-[11px] font-bold text-rose-700 text-right">Falta ${formatQty(l.missing)} ${escapeHtml(l.unit)}</span>`}
                    </div>`;
            }).join('') : '<div class="text-xs text-slate-400 py-2">La receta no tiene insumos.</div>';

            let message = '';
            if (lines.length && max === 0) {
                const shortList = produceShortages(1).filter(l => l.missing > 0);
                message = 'No alcanzan los insumos ni para 1 unidad' + (shortList.length
                    ? ': falta ' + shortList.map(l => `${formatQty(l.missing)} ${l.unit} de ${l.name}`).join(', ') + '.'
                    : '.');
            } else if (qty > 0) {
                const shortList = lines.filter(l => l.missing > 0);
                if (shortList.length) {
                    message = 'No alcanzan los insumos: falta ' +
                        shortList.map(l => `${formatQty(l.missing)} ${l.unit} de ${l.name}`).join(', ') +
                        `. Con el stock actual puedes elaborar hasta ${max} unidad(es).`;
                }
            }
            alertText.textContent = message;
            alertEl.classList.toggle('hidden', !message);
            lucide.createIcons({ root: linesEl });
            refreshStockSubmitState();
        }

        function updateDecreaseDetailTag() {
            const isOther = document.getElementById('s-decrease-reason').value === 'Otro';
            const tag = document.getElementById('s-decrease-detail-tag');
            tag.textContent = isOther ? '(obligatorio)' : '(opcional)';
            tag.className = isOther ? 'text-rose-500 font-semibold' : 'text-slate-400 font-semibold';
        }

        // Returns an error message for the current action, or '' when it can be sent.
        function stockValidationError() {
            const p = stockProduct;
            if (!p) return 'Producto no seleccionado.';
            if (stockAction === 'produce') {
                if (stockRecipeLoading) return 'Cargando insumos...';
                const qty = produceQuantity();
                if (!qty) return 'Indica cuántas unidades vas a elaborar (número entero mayor a 0).';
                if (!stockRecipeLines.length) return 'La receta no tiene insumos.';
                if (produceShortages(qty).some(l => l.missing > 0)) return 'No alcanzan los insumos para esa cantidad.';
                return '';
            }
            if (stockAction === 'purchase') {
                const qty = Number(document.getElementById('s-purchase-qty').value);
                if (!Number.isInteger(qty) || qty <= 0) return 'Indica las unidades recibidas (número entero mayor a 0).';
                return '';
            }
            if (stockAction === 'decrease') {
                const qty = Number(document.getElementById('s-decrease-qty').value);
                if (!Number.isInteger(qty) || qty <= 0) return 'Indica las unidades a retirar (número entero mayor a 0).';
                if (qty > p.stock) return `No puedes retirar más de lo que hay en stock (${p.stock} uni).`;
                const reason = document.getElementById('s-decrease-reason').value;
                if (!reason) return 'Selecciona el motivo de la disminución.';
                if (reason === 'Otro' && !document.getElementById('s-decrease-detail').value.trim()) {
                    return 'Describe el motivo en el detalle.';
                }
                return '';
            }
            if (stockAction === 'adjust') {
                const qty = Number(document.getElementById('s-adjust-qty').value);
                if (!Number.isInteger(qty) || qty === 0) return 'Indica una cantidad entera distinta de 0.';
                if (qty < 0 && -qty > p.stock) return `No puedes restar más de lo que hay en stock (${p.stock} uni).`;
                if (!document.getElementById('s-adjust-note').value.trim()) return 'El ajuste requiere una nota.';
                return '';
            }
            return 'Selecciona un tipo de movimiento.';
        }

        function refreshStockSubmitState() {
            const btn = document.getElementById('s-submit');
            if (!btn || btn.dataset.busy === '1') return;
            // Only "Elaborar" is hard-disabled (live material check); the other
            // actions show their validation message on submit.
            btn.disabled = stockAction === 'produce' && stockValidationError() !== '';
        }

        function stockRequest() {
            const id = stockProduct.id;
            if (stockAction === 'produce') {
                return { url: 'api/production.php', body: { product_id: id, quantity: produceQuantity() } };
            }
            if (stockAction === 'purchase') {
                return { url: 'api/products.php', body: {
                    action: 'stock_entry', id, reason: 'purchase',
                    quantity: Number(document.getElementById('s-purchase-qty').value),
                    note: document.getElementById('s-purchase-note').value.trim(),
                } };
            }
            if (stockAction === 'decrease') {
                const reason = document.getElementById('s-decrease-reason').value;
                const detail = document.getElementById('s-decrease-detail').value.trim();
                return { url: 'api/products.php', body: {
                    action: 'stock_entry', id, reason: 'adjustment',
                    quantity: -Number(document.getElementById('s-decrease-qty').value),
                    note: detail ? `${reason}: ${detail}` : reason,
                } };
            }
            return { url: 'api/products.php', body: {
                action: 'stock_entry', id, reason: 'adjustment',
                quantity: Number(document.getElementById('s-adjust-qty').value),
                note: document.getElementById('s-adjust-note').value.trim(),
            } };
        }

        function stockErrorMessage(data) {
            let msg = data.error || 'No se pudo registrar el movimiento.';
            if (Array.isArray(data.missing) && data.missing.length) {
                msg += ' Falta: ' + data.missing.map(m => {
                    const short = Math.max(0, Number(m.needed) - Number(m.available));
                    return `${formatQty(short)} ${m.unit || ''} de ${m.name}`;
                }).join(', ') + '.';
            }
            return msg;
        }

        document.querySelectorAll('#s-actions .s-action-btn').forEach(btn => {
            btn.addEventListener('click', () => setStockAction(btn.dataset.action));
        });
        document.getElementById('s-produce-qty').addEventListener('input', renderProducePreview);
        document.getElementById('s-decrease-reason').addEventListener('change', updateDecreaseDetailTag);
        document.getElementById('stock-modal').addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeStockModal();
        });

        document.getElementById('stock-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const validation = stockValidationError();
            if (validation) {
                setStockError(validation);
                return;
            }
            const btn = document.getElementById('s-submit');
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Registrando...';
            btn.disabled = true;
            btn.dataset.busy = '1';
            setStockError('');

            const { url, body } = stockRequest();
            const productId = stockProduct.id;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    closeStockModal();
                    await loadInventory();
                } else {
                    // Someone else may have changed materials or stock: refresh the data.
                    if (stockProduct && stockProduct.recipe_items > 0) {
                        await loadStockRecipe(productId);
                    } else {
                        await loadInventory();
                        const fresh = allProductsCache.find(x => x.id === productId);
                        if (fresh && stockProduct && stockProduct.id === productId) {
                            stockProduct = fresh;
                            renderStockSummary();
                        }
                    }
                    setStockError(stockErrorMessage(data));
                }
            } catch (err) {
                setStockError('Error de conexión.');
            } finally {
                btn.innerHTML = origHTML;
                delete btn.dataset.busy;
                btn.disabled = false;
                refreshStockSubmitState();
            }
        });
    </script>
</body>
</html>
