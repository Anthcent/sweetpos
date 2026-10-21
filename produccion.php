<?php require 'auth.php'; requireRole(['admin', 'gerente']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elaboración de Productos - Sweet POS</title>
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

    <!-- Área Principal (Elaboracion) -->
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <div class="absolute top-0 right-0 w-[26rem] h-[26rem] bg-pink-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

        <header class="min-h-[4.5rem] py-2.5 px-4 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                    <i data-lucide="chef-hat" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Elaboración & Recetas</h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-2 sm:line-clamp-none">Convierte materias primas en postres y helados listos</p>
                </div>
            </div>

            <!-- Acciones: Buscador en vivo y Botón Nuevo Producto -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <!-- Buscador de elaboración -->
                <div class="search-bar-sweet w-full sm:w-80">
                    <div class="search-icon-badge">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <input 
                        type="text" 
                        id="production-search" 
                        placeholder="Buscar producto o receta..." 
                        autocomplete="off">
                    <span id="production-search-count" class="hidden text-[10px] font-extrabold text-pink-700 bg-pink-50 px-2 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                    <button 
                        type="button" 
                        id="production-search-clear" 
                        onclick="clearProductionSearch()" 
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

        <!-- BARRA DEDICADA DE FILTROS DE PRODUCCIÓN (100% VISIBLE Y DINÁMICA) -->
        <div class="px-4 sm:px-6 lg:px-8 pt-3.5 pb-1 shrink-0 z-10">
            <div class="filter-scroll-fade flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
                <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline-flex items-center gap-1.5 mr-1 shrink-0 font-heading">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Filtrar:</span>
                </span>

                <button type="button" data-filter="all" class="prod-filter-btn filter-pill-sweet active">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span>Todos</span>
                    <span class="pill-badge" id="prod-count-all">0</span>
                </button>

                <button type="button" data-filter="has_recipe" class="prod-filter-btn filter-pill-sweet">
                    <i data-lucide="book-open" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Con Receta</span>
                    <span class="pill-badge" id="prod-count-recipe">0</span>
                </button>

                <button type="button" data-filter="ready" class="prod-filter-btn filter-pill-sweet">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-emerald-500"></i>
                    <span class="text-emerald-800">Listos para Producir</span>
                    <span class="pill-badge !bg-emerald-100 !text-emerald-800" id="prod-count-ready">0</span>
                </button>

                <button type="button" data-filter="no_recipe" class="prod-filter-btn filter-pill-sweet">
                    <i data-lucide="file-question" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Sin Receta</span>
                    <span class="pill-badge" id="prod-count-norecipe">0</span>
                </button>

                <button type="button" data-filter="out_of_stock" class="prod-filter-btn filter-pill-sweet hover:border-amber-300">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-500"></i>
                    <span class="text-amber-800">Falta Insumos</span>
                    <span class="pill-badge !bg-amber-100 !text-amber-800" id="prod-count-missing">0</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div id="production-list" class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div id="production-cards" class="mobile-data-list md:hidden">
                    <div class="mobile-data-empty">Cargando recetas y stock...</div>
                </div>
                <div class="hidden md:block overflow-x-auto w-full">
                    <table class="w-full min-w-[620px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Producto</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Stock Terminado</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Receta</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Capacidad</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="production-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="5" class="text-center py-12 text-slate-400 font-medium">Cargando recetas y stock...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="production-pager" class="responsive-pager hidden"></div>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Producto (compartido) -->
    <?php require '_product_modal.php'; ?>

    <!-- Modal Editar Receta -->
    <div id="recipe-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[94dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100 flex justify-between items-start gap-3 shrink-0">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center shrink-0">
                        <i data-lucide="chef-hat" class="w-5 h-5 stroke-[2.2]"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="recipe-product-name" class="font-extrabold text-slate-900 font-heading text-base sm:text-lg leading-tight truncate"></h3>
                        <p class="text-xs text-slate-500 mt-0.5">Cantidad de cada insumo para elaborar <strong class="text-slate-700">1 unidad</strong></p>
                    </div>
                </div>
                <button type="button" onclick="closeRecipeModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 p-2 rounded-lg shrink-0" title="Cerrar">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Live summary: what this recipe allows right now -->
            <div id="recipe-summary" class="grid grid-cols-3 border-b border-slate-100 bg-slate-50/80 shrink-0 divide-x divide-slate-200/70">
                <div class="px-3 sm:px-5 py-3">
                    <span class="block text-[11px] font-bold text-slate-500">Puedes elaborar</span>
                    <strong id="recipe-max" class="block text-xl font-black font-heading text-slate-400 leading-tight">—</strong>
                </div>
                <div class="px-3 sm:px-5 py-3">
                    <span class="block text-[11px] font-bold text-slate-500">Costo por unidad</span>
                    <strong id="recipe-cost" class="block text-xl font-black font-heading text-slate-800 leading-tight">$0.00</strong>
                </div>
                <div class="px-3 sm:px-5 py-3 min-w-0">
                    <span class="block text-[11px] font-bold text-slate-500">Insumo que limita</span>
                    <strong id="recipe-limit" class="block text-sm font-extrabold text-slate-400 leading-tight mt-1 truncate">—</strong>
                </div>
            </div>

            <form id="recipe-form" class="flex flex-col min-h-0 flex-1">
                <input type="hidden" id="recipe-product-id" value="">
                <div class="p-4 sm:p-5 flex flex-col gap-4 overflow-y-auto min-h-0 flex-1">
                    <!-- Material picker -->
                    <div>
                        <label for="recipe-search" class="block text-xs font-bold text-slate-700 mb-1.5">Agregar insumo</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input type="search" id="recipe-search" autocomplete="off" placeholder="Buscar por nombre: harina, azúcar, leche..." class="w-full pl-9 pr-3 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300">
                        </div>
                        <div id="recipe-picker" class="mt-2 flex flex-wrap gap-1.5 max-h-28 overflow-y-auto"></div>
                    </div>

                    <!-- Ingredients -->
                    <div>
                        <div class="flex items-baseline justify-between mb-1.5">
                            <span class="text-xs font-bold text-slate-700">Ingredientes <span id="recipe-count" class="text-slate-400 font-semibold">(0)</span></span>
                        </div>
                        <div id="recipe-rows" class="flex flex-col gap-2"></div>
                    </div>

                    <div id="recipe-error" class="hidden text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                </div>

                <div class="px-4 sm:px-5 py-3.5 border-t border-slate-100 flex gap-2 shrink-0 bg-white">
                    <button type="button" onclick="closeRecipeModal()" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                    <button type="submit" class="btn-sweet-accent flex-1 text-white font-extrabold py-3 rounded-xl transition-all flex justify-center items-center gap-2 shadow-md">
                        <i data-lucide="save" class="w-4 h-4 stroke-[2.2]"></i> Guardar receta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Producir -->
    <div id="produce-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="chef-hat" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading">
                        Elaborar: <span id="produce-product-name" class="text-pink-600 font-bold"></span>
                    </h3>
                </div>
                <button type="button" onclick="document.getElementById('produce-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="p-6 flex flex-col gap-4">
                <input type="hidden" id="produce-product-id" value="">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cantidad a Elaborar (Unidades)</label>
                    <input type="number" id="produce-quantity" value="1" min="1" step="1" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-lg font-bold">
                </div>
                <div id="produce-requirements" class="flex flex-col gap-1.5 text-xs bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80"></div>
                <div id="produce-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                <button id="produce-submit-btn" onclick="submitProduction()" class="btn-sweet-accent w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="check" class="w-4 h-4 stroke-[2.2]"></i> Confirmar Elaboración
                </button>
            </div>
        </div>
    </div>

    <script>
        let productsCache  = [];
        let materialsCache = [];
        let currentRecipe  = [];

        let currentProdFilter = 'all';
        let prodPage = 1;
        let prodFiltered = [];

        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', async () => {
            const searchInput = document.getElementById('production-search');
            if (searchInput) {
                searchInput.addEventListener('input', filterProduction);
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        clearProductionSearch();
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
            document.querySelectorAll('.prod-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.prod-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentProdFilter = btn.dataset.filter || 'all';
                    filterProduction();
                });
            });

            SweetPagination.onBreakpointChange(() => {
                prodPage = 1;
                renderProductionTable(prodFiltered);
            });

            await loadMaterials();
            await loadProduction();

            // Deep links: recipe=<product_id> opens the recipe editor.
            const params = new URLSearchParams(location.search);
            const recipeId = Number(params.get('recipe'));
            if (recipeId && productsCache.some(p => p.id === recipeId)) {
                await openRecipeModal(recipeId);
                history.replaceState(null, '', location.pathname);
            }

            // Deep link from panel.php: produccion.php?produce=<product_id>&qty=<units>
            const produceId = Number(params.get('produce'));
            if (produceId && productsCache.some(p => p.id === produceId)) {
                await openProduceModal(produceId);
                const qty = parseInt(params.get('qty'), 10);
                if (qty > 0) {
                    document.getElementById('produce-quantity').value = qty;
                    updateProduceRequirements();
                }
            }
            if (params.has('produce')) history.replaceState(null, '', location.pathname);
        });
        const refreshAfterProductSave = async () => {
            await loadMaterials();
            await loadProduction();
        };
        document.addEventListener('product:created', refreshAfterProductSave);
        document.addEventListener('product:updated', refreshAfterProductSave);

        async function loadMaterials() {
            try {
                const res = await fetch('api/materials.php');
                const data = await res.json();
                materialsCache = res.ok && Array.isArray(data) ? data : [];
            } catch (e) {
                materialsCache = [];
            }
        }

        function getProductIcon(name) {
            name = (name || '').toLowerCase();
            if (name.includes('vainilla') || name.includes('fresa')) return 'ice-cream-cone';
            if (name.includes('chocolate') || name.includes('cacao')) return 'ice-cream-2';
            if (name.includes('cheesecake') || name.includes('torta') || name.includes('pastel')) return 'cake-slice';
            if (name.includes('brownie') || name.includes('cookie') || name.includes('galleta')) return 'cookie';
            if (name.includes('café') || name.includes('cafe')) return 'coffee';
            return 'sparkles';
        }

        async function loadProduction() {
            const table = document.getElementById('production-table');
            try {
                const res = await fetch('api/production.php');
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                // PDO/SQLite puede devolver numeros como texto: normalizar para comparar
                productsCache = data.map(p => ({
                    ...p,
                    id: Number(p.id),
                    stock: Number(p.stock),
                    recipe_items: Number(p.recipe_items) || 0,
                    max_producible: p.max_producible === null ? 0 : Number(p.max_producible)
                }));
                updateProdCounts(productsCache);
                filterProduction();
            } catch (e) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-rose-500 font-medium">Error al cargar recetas.</td></tr>';
                const cards = document.getElementById('production-cards');
                if (cards) cards.innerHTML = '<div class="mobile-data-empty text-rose-500">Error al cargar recetas.</div>';
            }
        }

        function updateProdCounts(products) {
            const total = products.length;
            const hasRecipe = products.filter(p => p.recipe_items > 0).length;
            const noRecipe = products.filter(p => !p.recipe_items || p.recipe_items === 0).length;
            const ready = products.filter(p => p.recipe_items > 0 && p.max_producible > 0).length;
            const missing = products.filter(p => p.recipe_items > 0 && p.max_producible === 0).length;

            const elAll = document.getElementById('prod-count-all');
            const elRecipe = document.getElementById('prod-count-recipe');
            const elNoRecipe = document.getElementById('prod-count-norecipe');
            const elReady = document.getElementById('prod-count-ready');
            const elMissing = document.getElementById('prod-count-missing');

            if (elAll) elAll.textContent = total;
            if (elRecipe) elRecipe.textContent = hasRecipe;
            if (elNoRecipe) elNoRecipe.textContent = noRecipe;
            if (elReady) elReady.textContent = ready;
            if (elMissing) elMissing.textContent = missing;
        }

        function filterProduction(resetPage = true) {
            const q = (document.getElementById('production-search')?.value || '').trim().toLowerCase();
            const clearBtn = document.getElementById('production-search-clear');
            const countEl = document.getElementById('production-search-count');
            if (clearBtn) {
                if (q.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }

            let filtered = productsCache;

            // Filtro por píldora
            if (currentProdFilter === 'has_recipe') {
                filtered = filtered.filter(p => p.recipe_items > 0);
            } else if (currentProdFilter === 'no_recipe') {
                filtered = filtered.filter(p => !p.recipe_items || p.recipe_items === 0);
            } else if (currentProdFilter === 'ready') {
                filtered = filtered.filter(p => p.recipe_items > 0 && p.max_producible > 0);
            } else if (currentProdFilter === 'out_of_stock') {
                filtered = filtered.filter(p => p.recipe_items > 0 && p.max_producible === 0);
            }

            // Filtro por búsqueda de texto
            if (q) {
                filtered = filtered.filter(p => {
                    const name = (p.name || '').toLowerCase();
                    const idStr = String(p.id);
                    return name.includes(q) || idStr.includes(q);
                });
            }

            if (countEl) {
                if (q.length > 0) {
                    countEl.classList.remove('hidden');
                    countEl.textContent = `${filtered.length} ${filtered.length === 1 ? 'receta' : 'recetas'}`;
                } else {
                    countEl.classList.add('hidden');
                }
            }

            prodFiltered = filtered;
            if (resetPage) prodPage = 1;
            renderProductionTable(filtered);
        }

        function clearProductionSearch() {
            const input = document.getElementById('production-search');
            const clearBtn = document.getElementById('production-search-clear');
            const countEl = document.getElementById('production-search-count');
            if (input) {
                input.value = '';
                if (clearBtn) clearBtn.classList.add('hidden');
                if (countEl) countEl.classList.add('hidden');
                input.focus();
                filterProduction();
            }
        }

        function productionAvailability(p) {
            const hasRecipe = p.recipe_items > 0;
            if (!hasRecipe) {
                return `<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Sin receta</span>`;
            }
            if (p.max_producible <= 0) {
                return `<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">Falta material</span>`;
            }
            return `<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">● ${p.max_producible} unid. posibles</span>`;
        }

        function productionCardHtml(p) {
            const hasRecipe = p.recipe_items > 0;
            const maxProd = p.max_producible;
            const iconName = getProductIcon(p.name);
            return `
                <article class="mobile-data-card">
                    <div class="mobile-data-card-top">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 border border-white shadow-xs" style="background-color: ${safeColor(p.image_color)}; color: #9e2d58;">
                                <i data-lucide="${iconName}" class="w-5 h-5 stroke-[1.8]"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="mobile-data-card-title">${escapeHtml(p.name)}</h3>
                                <p class="mobile-data-card-sub">ID #${p.id}</p>
                            </div>
                        </div>
                        <div class="mobile-data-card-side">${productionAvailability(p)}</div>
                    </div>
                    <div class="mobile-data-grid">
                        <div class="mobile-data-field">
                            <span class="mobile-data-label">Stock terminado</span>
                            <span class="mobile-data-value">${p.stock} uni</span>
                        </div>
                        <div class="mobile-data-field">
                            <span class="mobile-data-label">Receta</span>
                            <span class="mobile-data-value">${hasRecipe ? `${p.recipe_items} ingredientes` : 'Sin receta'}</span>
                        </div>
                    </div>
                    <div class="mobile-data-actions">
                        <button onclick="openRecipeModal(${p.id})" class="mobile-data-action">
                            <i data-lucide="book-open" class="w-4 h-4"></i><span>Receta</span>
                        </button>
                        <button onclick="openProduceModal(${p.id})" ${!hasRecipe || maxProd <= 0 ? 'disabled' : ''} class="mobile-data-action btn-sweet-accent disabled:opacity-30 disabled:pointer-events-none">
                            <i data-lucide="chef-hat" class="w-4 h-4"></i><span>Elaborar</span>
                        </button>
                    </div>
                </article>`;
        }

        function renderProductionTable(products) {
            const table = document.getElementById('production-table');
            const cards = document.getElementById('production-cards');
            const pager = document.getElementById('production-pager');
            const state = SweetPagination.paginate(products, prodPage, SweetPagination.pageSize(6, 12));
            prodPage = state.page;

            if (products.length === 0) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-slate-400 font-medium">No se encontraron productos o recetas coincidentes.</td></tr>';
                if (cards) cards.innerHTML = '<div class="mobile-data-empty">No se encontraron productos o recetas coincidentes.</div>';
                SweetPagination.render(pager, { page: 1, pages: 1, total: 0, perPage: state.perPage, onChange: () => {} });
                return;
            }

            table.innerHTML = state.items.map(p => {
                const hasRecipe = p.recipe_items > 0;
                const maxProd = p.max_producible;
                const iconName = getProductIcon(p.name);
                return `
                    <tr class="hover:bg-pink-50/30 transition-colors group">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 border border-white shadow-xs" style="background-color: ${safeColor(p.image_color)}; color: #9e2d58;">
                                    <i data-lucide="${iconName}" class="w-5 h-5 stroke-[1.8]"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 font-heading block leading-tight">${escapeHtml(p.name)}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">ID #${p.id}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="font-extrabold text-slate-800 font-heading">${p.stock}</span> <span class="text-xs text-slate-400">uni</span>
                        </td>
                        <td class="px-6 py-3.5 text-xs text-slate-500 font-medium">
                            ${hasRecipe ? `<span class="px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-semibold">${p.recipe_items} ingredientes</span>` : '<span class="text-slate-400">—</span>'}
                        </td>
                        <td class="px-6 py-3.5">${productionAvailability(p)}</td>
                        <td class="px-6 py-3.5 text-right flex items-center justify-end gap-2">
                            <button onclick="openRecipeModal(${p.id})" class="text-xs font-bold text-slate-600 hover:text-pink-600 border border-slate-200 hover:border-pink-300 rounded-xl px-3 py-1.5 transition-all bg-white shadow-xs">
                                Receta
                            </button>
                            <button onclick="openProduceModal(${p.id})" ${!hasRecipe || maxProd <= 0 ? 'disabled' : ''} class="btn-sweet-accent text-xs font-bold text-white disabled:opacity-30 disabled:pointer-events-none rounded-xl px-3.5 py-1.5 transition-all shadow-xs">
                                Elaborar
                            </button>
                        </td>
                    </tr>`;
            }).join('');

            if (cards) cards.innerHTML = state.items.map(productionCardHtml).join('');

            SweetPagination.render(pager, {
                page: state.page,
                pages: state.pages,
                total: state.total,
                perPage: state.perPage,
                label: 'productos',
                onChange: (next) => {
                    prodPage = next;
                    renderProductionTable(products);
                    document.getElementById('production-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
            });
            lucide.createIcons();
        }

        // ---------- Receta ----------
        // Each ingredient row carries its material id in data-material-id; the picker
        // never adds the same material twice, so rows map 1:1 to recipe lines.

        let recipeOriginalCount = 0;

        function formatQty(value) {
            return Number(value || 0).toLocaleString('es-VE', { maximumFractionDigits: 3 });
        }

        function findMaterial(id) {
            return materialsCache.find(m => String(m.id) === String(id));
        }

        function recipeRowIds() {
            return [...document.querySelectorAll('#recipe-rows .recipe-row')].map(r => r.dataset.materialId);
        }

        function renderRecipePicker() {
            const picker = document.getElementById('recipe-picker');
            if (!picker) return;
            if (materialsCache.length === 0) {
                picker.innerHTML = '<p class="w-full rounded-xl border border-dashed border-amber-300 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-800">No hay insumos registrados. Créalos primero en el módulo Insumos.</p>';
                return;
            }
            const term = (document.getElementById('recipe-search')?.value || '').trim().toLowerCase();
            const used = new Set(recipeRowIds());
            const matches = materialsCache.filter(m => !used.has(String(m.id)) && (!term || String(m.name).toLowerCase().includes(term)));
            if (matches.length === 0) {
                picker.innerHTML = `<p class="text-xs text-slate-500 px-1">${term ? 'Ningún insumo coincide con la búsqueda.' : 'Todos los insumos ya están en la receta.'}</p>`;
                return;
            }
            picker.innerHTML = matches.map(m => {
                const empty = (parseFloat(m.stock) || 0) <= 0;
                return `<button type="button" data-material-id="${escapeHtml(m.id)}" class="recipe-pick inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors ${empty ? 'border-slate-200 bg-slate-50 text-slate-500 hover:border-slate-300' : 'border-pink-200 bg-pink-50/60 text-pink-800 hover:bg-pink-100'}">
                    <i data-lucide="plus" class="w-3 h-3 stroke-[2.5]"></i>${escapeHtml(m.name)}
                    <span class="font-medium ${empty ? 'text-rose-600' : 'text-slate-500'}">${empty ? 'sin stock' : formatQty(m.stock) + ' ' + escapeHtml(m.unit)}</span>
                </button>`;
            }).join('');
            picker.querySelectorAll('.recipe-pick').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row = addRecipeRow(btn.dataset.materialId, '');
                    row?.querySelector('.recipe-quantity')?.focus();
                });
            });
            lucide.createIcons({ root: picker });
        }

        function renderRecipeEmptyState() {
            const container = document.getElementById('recipe-rows');
            if (!container || container.querySelector('.recipe-row')) {
                document.getElementById('recipe-empty')?.remove();
                return;
            }
            if (document.getElementById('recipe-empty')) return;
            container.innerHTML = '<div id="recipe-empty" class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center"><p class="text-sm font-bold text-slate-700">Esta receta no tiene ingredientes</p><p class="text-xs text-slate-500 mt-1">Busca un insumo arriba y tócalo para agregarlo.</p></div>';
        }

        function updateRecipeSummary() {
            const rows = [...document.querySelectorAll('#recipe-rows .recipe-row')];
            const lines = [];
            let cost = 0;
            rows.forEach(row => {
                const material = findMaterial(row.dataset.materialId);
                const quantity = parseFloat(row.querySelector('.recipe-quantity').value);
                if (!material || !quantity || quantity <= 0) {
                    lines.push({ row, material, possible: null });
                    return;
                }
                const lineCost = (parseFloat(material.cost_per_unit) || 0) * quantity;
                cost += lineCost;
                lines.push({ row, material, quantity, lineCost, possible: Math.floor((parseFloat(material.stock) || 0) / quantity) });
            });

            const valid = lines.filter(l => l.possible !== null);
            const max = valid.length ? Math.min(...valid.map(l => l.possible)) : null;
            const best = valid.length ? Math.max(...valid.map(l => l.possible), 1) : 1;
            const limiting = valid.length ? valid.reduce((a, b) => (b.possible < a.possible ? b : a)) : null;

            lines.forEach(({ row, material, quantity, lineCost, possible }) => {
                const bar = row.querySelector('.recipe-bar');
                const status = row.querySelector('.recipe-row-status');
                const costEl = row.querySelector('.recipe-row-cost');
                if (possible === null) {
                    bar.style.width = '0%';
                    status.textContent = 'Indica la cantidad por unidad';
                    status.className = 'recipe-row-status text-[11px] font-semibold text-amber-700';
                    costEl.textContent = '';
                    row.classList.remove('border-rose-300', 'bg-rose-50/40');
                    return;
                }
                const isLimit = limiting && limiting.row === row && valid.length > 1;
                const tone = possible <= 0 ? 'rose' : isLimit ? 'amber' : 'emerald';
                bar.style.width = possible <= 0 ? '100%' : Math.max(6, Math.round((possible / best) * 100)) + '%';
                bar.className = `recipe-bar h-full rounded-full transition-all duration-300 ${tone === 'rose' ? 'bg-rose-400' : tone === 'amber' ? 'bg-amber-400' : 'bg-emerald-400'}`;
                status.textContent = possible <= 0
                    ? `Falta: hay ${formatQty(material.stock)} ${material.unit}, necesitas ${formatQty(quantity)}`
                    : `Alcanza para ${possible} ${possible === 1 ? 'unidad' : 'unidades'}${isLimit ? ' · limita la producción' : ''}`;
                status.className = `recipe-row-status text-[11px] font-semibold ${tone === 'rose' ? 'text-rose-700' : tone === 'amber' ? 'text-amber-700' : 'text-emerald-700'}`;
                costEl.textContent = lineCost > 0 ? '$' + lineCost.toFixed(2) : '';
                row.classList.toggle('border-rose-300', possible <= 0);
                row.classList.toggle('bg-rose-50/40', possible <= 0);
            });

            document.getElementById('recipe-count').textContent = `(${rows.length})`;
            document.getElementById('recipe-cost').textContent = '$' + cost.toFixed(2);
            const maxEl = document.getElementById('recipe-max');
            maxEl.textContent = max === null ? '—' : `${Math.max(0, max)} ${max === 1 ? 'unidad' : 'unid.'}`;
            maxEl.className = 'block text-xl font-black font-heading leading-tight ' + (max === null ? 'text-slate-400' : max <= 0 ? 'text-rose-600' : 'text-emerald-700');
            const limitEl = document.getElementById('recipe-limit');
            limitEl.textContent = limiting ? limiting.material.name : '—';
            limitEl.title = limiting ? limiting.material.name : '';
            limitEl.className = 'block text-sm font-extrabold leading-tight mt-1 truncate ' + (!limiting ? 'text-slate-400' : limiting.possible <= 0 ? 'text-rose-600' : 'text-slate-800');
        }

        function addRecipeRow(materialId, quantity = '') {
            const material = findMaterial(materialId);
            const container = document.getElementById('recipe-rows');
            if (!material || !container) return null;
            const existing = container.querySelector(`.recipe-row[data-material-id="${CSS.escape(String(material.id))}"]`);
            if (existing) return existing;
            document.getElementById('recipe-empty')?.remove();

            const row = document.createElement('div');
            row.className = 'recipe-row rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition-colors';
            row.dataset.materialId = String(material.id);
            row.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2">
                            <span class="font-bold text-sm text-slate-900 truncate">${escapeHtml(material.name)}</span>
                            <span class="recipe-row-cost text-[11px] font-semibold text-slate-500 shrink-0"></span>
                        </div>
                        <span class="block text-[11px] text-slate-500">Disponible: ${formatQty(material.stock)} ${escapeHtml(material.unit)}</span>
                    </div>
                    <label class="relative shrink-0">
                        <span class="sr-only">Cantidad de ${escapeHtml(material.name)} por unidad</span>
                        <input type="number" class="recipe-quantity w-28 pl-3 pr-10 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm font-bold text-right focus:outline-none focus:ring-2 focus:ring-pink-200 focus:border-pink-300" placeholder="0" min="0.001" step="any" inputmode="decimal" value="${escapeHtml(quantity)}">
                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-slate-500 pointer-events-none">${escapeHtml(material.unit)}</span>
                    </label>
                    <button type="button" class="recipe-remove text-slate-400 hover:text-rose-600 p-2 rounded-lg hover:bg-rose-50 shrink-0" title="Quitar ${escapeHtml(material.name)}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
                <div class="mt-2 flex items-center gap-2.5">
                    <div class="h-1.5 flex-1 rounded-full bg-slate-100 overflow-hidden"><div class="recipe-bar h-full rounded-full bg-slate-300" style="width:0%"></div></div>
                </div>
                <span class="recipe-row-status block mt-1 text-[11px] font-semibold"></span>
            `;
            container.appendChild(row);
            lucide.createIcons({ root: row });
            row.querySelector('.recipe-remove').addEventListener('click', () => {
                row.remove();
                renderRecipeEmptyState();
                renderRecipePicker();
                updateRecipeSummary();
            });
            row.querySelector('.recipe-quantity').addEventListener('input', updateRecipeSummary);
            renderRecipePicker();
            updateRecipeSummary();
            return row;
        }

        function closeRecipeModal() {
            document.getElementById('recipe-modal').classList.add('hidden');
        }

        async function openRecipeModal(productId) {
            const product = productsCache.find(p => p.id === productId);
            document.getElementById('recipe-product-id').value = productId;
            document.getElementById('recipe-product-name').innerText = product ? product.name : '';
            document.getElementById('recipe-rows').innerHTML = '';
            document.getElementById('recipe-error').classList.add('hidden');
            document.getElementById('recipe-search').value = '';
            recipeOriginalCount = 0;

            if (materialsCache.length > 0) {
                const res = await fetch(`api/production.php?product_id=${productId}`);
                const recipe = res.ok ? await res.json() : [];
                recipeOriginalCount = Array.isArray(recipe) ? recipe.length : 0;
                (Array.isArray(recipe) ? recipe : []).forEach(item => addRecipeRow(item.material_id, item.quantity_required));
            }

            renderRecipeEmptyState();
            renderRecipePicker();
            updateRecipeSummary();
            document.getElementById('recipe-modal').classList.remove('hidden');
            lucide.createIcons({ root: document.getElementById('recipe-modal') });
        }

        document.getElementById('recipe-search').addEventListener('input', renderRecipePicker);
        document.getElementById('recipe-search').addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.querySelector('#recipe-picker .recipe-pick')?.click();
        });

        document.getElementById('recipe-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const errDiv = document.getElementById('recipe-error');
            errDiv.classList.add('hidden');

            const rows = [...document.querySelectorAll('#recipe-rows .recipe-row')];
            const incomplete = rows.filter(row => !(parseFloat(row.querySelector('.recipe-quantity').value) > 0));
            if (incomplete.length > 0) {
                const names = incomplete.map(row => findMaterial(row.dataset.materialId)?.name).filter(Boolean).join(', ');
                errDiv.innerText = `Indica la cantidad por unidad de: ${names}. O quita esos insumos de la receta.`;
                errDiv.classList.remove('hidden');
                incomplete[0].querySelector('.recipe-quantity').focus();
                return;
            }
            const items = rows.map(row => ({
                material_id: parseInt(row.dataset.materialId),
                quantity_required: parseFloat(row.querySelector('.recipe-quantity').value)
            }));
            if (items.length === 0 && recipeOriginalCount > 0
                && !confirm('La receta quedará sin ingredientes y el producto dejará de poder elaborarse. ¿Guardar de todas formas?')) {
                return;
            }

            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Guardando...';
            btn.disabled = true;
            try {
                const res = await fetch('api/production.php', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        product_id: parseInt(document.getElementById('recipe-product-id').value),
                        items: items
                    })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    closeRecipeModal();
                    await loadProduction();
                } else {
                    errDiv.innerText = data.error || 'No se pudo guardar la receta.';
                    errDiv.classList.remove('hidden');
                }
            } catch (err) {
                errDiv.innerText = 'Error de conexión al guardar la receta.';
                errDiv.classList.remove('hidden');
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });


        // ---------- Elaborar ----------

        async function openProduceModal(productId) {
            const product = productsCache.find(p => p.id === Number(productId));
            const errDiv  = document.getElementById('produce-error');
            document.getElementById('produce-product-id').value = productId;
            document.getElementById('produce-product-name').innerText = product ? product.name : '';
            document.getElementById('produce-quantity').value = 1;
            errDiv.classList.add('hidden');

            currentRecipe = [];
            try {
                const res = await fetch(`api/production.php?product_id=${encodeURIComponent(productId)}`);
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'No se pudo cargar la receta.');
                currentRecipe = data;
            } catch (e) {
                errDiv.innerText = e.message || 'No se pudo cargar la receta.';
                errDiv.classList.remove('hidden');
            }

            updateProduceRequirements();
            document.getElementById('produce-modal').classList.remove('hidden');
        }

        function updateProduceRequirements() {
            const qty = parseInt(document.getElementById('produce-quantity').value, 10) || 0;
            const container = document.getElementById('produce-requirements');
            let canProduce = currentRecipe.length > 0;

            // La API devuelve name / material_stock / quantity_required por insumo
            container.innerHTML = currentRecipe.map(item => {
                const neededNum    = parseFloat(item.quantity_required) * qty;
                const availableNum = parseFloat(item.material_stock) || 0;
                const ok           = availableNum + 1e-9 >= neededNum;
                if (!ok) canProduce = false;
                return `
                    <div class="flex justify-between items-center py-1 border-b border-slate-100 last:border-0">
                        <span class="text-slate-600 font-medium">${escapeHtml(item.name)}</span>
                        <span class="${ok ? 'text-emerald-700 font-bold' : 'text-rose-600 font-bold'}">
                            ${neededNum.toFixed(2)} / ${availableNum.toFixed(2)} ${escapeHtml(item.unit)} ${ok ? '✓' : '✗'}
                        </span>
                    </div>
                `;
            }).join('') || '<p class="text-slate-400 text-center py-1">Este producto no tiene receta.</p>';

            const btn = document.getElementById('produce-submit-btn');
            btn.disabled = !canProduce || qty <= 0;
        }

        document.getElementById('produce-quantity').addEventListener('input', updateProduceRequirements);

        async function submitProduction() {
            const productId = parseInt(document.getElementById('produce-product-id').value);
            const quantity  = parseInt(document.getElementById('produce-quantity').value);
            const errDiv    = document.getElementById('produce-error');
            const btn       = document.getElementById('produce-submit-btn');
            const origHTML  = btn.innerHTML;

            errDiv.classList.add('hidden');
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Elaborando...';
            btn.disabled  = true;

            try {
                const res = await fetch('api/production.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ product_id: productId, quantity })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    document.getElementById('produce-modal').classList.add('hidden');
                    await loadMaterials();
                    await loadProduction();
                } else {
                    let msg = data.error || 'Error al elaborar producto.';
                    if (Array.isArray(data.missing) && data.missing.length) {
                        msg += ' Falta: ' + data.missing.map(m =>
                            `${m.name} (necesario ${parseFloat(m.needed).toFixed(2)}, disponible ${parseFloat(m.available).toFixed(2)} ${m.unit})`
                        ).join('; ');
                    }
                    errDiv.innerText = msg;
                    errDiv.classList.remove('hidden');
                    // Refrescar disponibilidad: otro usuario pudo consumir insumos
                    await refreshCurrentRecipe(productId);
                }
            } catch (e) {
                errDiv.innerText = 'Error de conexión.';
                errDiv.classList.remove('hidden');
            } finally {
                btn.innerHTML = origHTML;
                updateProduceRequirements();
            }
        }

        async function refreshCurrentRecipe(productId) {
            try {
                const res = await fetch(`api/production.php?product_id=${encodeURIComponent(productId)}`);
                if (res.ok) currentRecipe = await res.json();
            } catch (e) { /* se mantiene la receta anterior */ }
        }
    </script>
</body>
</html>
