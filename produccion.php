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
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-1 sm:line-clamp-none">Convierte materias primas en postres y helados listos</p>
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
            <div class="flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
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
            <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="overflow-x-auto w-full">
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
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Producto (compartido) -->
    <?php require '_product_modal.php'; ?>

    <!-- Modal Editar Receta -->
    <div id="recipe-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="chef-hat" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading text-sm sm:text-base">
                        Receta: <span id="recipe-product-name" class="text-pink-600 font-bold"></span>
                    </h3>
                </div>
                <button type="button" onclick="document.getElementById('recipe-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="recipe-form" class="p-4 sm:p-6 flex flex-col gap-3 sm:gap-3.5 overflow-y-auto">
                <input type="hidden" id="recipe-product-id" value="">
                <p class="text-xs text-slate-400">Insumos requeridos para elaborar <strong>1 unidad</strong> de este producto.</p>
                <div id="recipe-rows" class="flex flex-col gap-2 max-h-64 overflow-y-auto pr-1"></div>
                <button type="button" onclick="addRecipeRow()" class="w-full border border-dashed border-pink-200 text-pink-600 hover:bg-pink-50/50 rounded-xl py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="plus" class="w-3.5 h-3.5 stroke-[2.2]"></i> Agregar insumo
                </button>
                <div id="recipe-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="save" class="w-4 h-4 stroke-[2.2]"></i> Guardar Receta
                </button>
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

            await loadMaterials();
            await loadProduction();

            // Deep link from panel.php: produccion.php?produce=<product_id>&qty=<units>
            const params = new URLSearchParams(location.search);
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
        document.addEventListener('product:created', async () => {
            await loadMaterials();
            await loadProduction();
        });

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

        function filterProduction() {
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

        function renderProductionTable(products) {
            const table = document.getElementById('production-table');
            table.innerHTML = '';
            if (products.length === 0) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-slate-400 font-medium">No se encontraron productos o recetas coincidentes.</td></tr>';
                return;
            }

            products.forEach(p => {
                const hasRecipe = p.recipe_items > 0;
                const maxProd = p.max_producible;
                let availabilityBadge;
                if (!hasRecipe) {
                    availabilityBadge = `<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Sin receta</span>`;
                } else if (maxProd <= 0) {
                    availabilityBadge = `<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">Falta material</span>`;
                } else {
                    availabilityBadge = `<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">● ${maxProd} unid. posibles</span>`;
                }

                const iconName = getProductIcon(p.name);

                table.innerHTML += `
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
                        <td class="px-6 py-3.5">${availabilityBadge}</td>
                        <td class="px-6 py-3.5 text-right flex items-center justify-end gap-2">
                            <button onclick="openRecipeModal(${p.id})" class="text-xs font-bold text-slate-600 hover:text-pink-600 border border-slate-200 hover:border-pink-300 rounded-xl px-3 py-1.5 transition-all bg-white shadow-xs">
                                Receta
                            </button>
                            <button onclick="openProduceModal(${p.id})" ${!hasRecipe || maxProd <= 0 ? 'disabled' : ''} class="btn-sweet-accent text-xs font-bold text-white disabled:opacity-30 disabled:pointer-events-none rounded-xl px-3.5 py-1.5 transition-all shadow-xs">
                                Elaborar
                            </button>
                        </td>
                    </tr>
                `;
            });
            lucide.createIcons();
        }

        // ---------- Receta ----------

        function materialOptionsHtml(selectedId) {
            return materialsCache.map(m =>
                `<option value="${escapeHtml(m.id)}" ${String(m.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(m.name)} (${escapeHtml(m.unit)})</option>`
            ).join('');
        }

        function addRecipeRow(materialId = '', quantity = '') {
            const container = document.getElementById('recipe-rows');
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            row.innerHTML = `
                <select class="recipe-material flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300 font-medium">
                    ${materialOptionsHtml(materialId)}
                </select>
                <input type="number" class="recipe-quantity w-28 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pink-300 font-bold" placeholder="Cant." min="0.01" step="0.01" value="${escapeHtml(quantity)}">
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500 p-2 rounded-xl hover:bg-rose-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            `;
            container.appendChild(row);
            lucide.createIcons({ root: row });
        }

        async function openRecipeModal(productId) {
            const product = productsCache.find(p => p.id === productId);
            document.getElementById('recipe-product-id').value = productId;
            document.getElementById('recipe-product-name').innerText = product ? product.name : '';
            document.getElementById('recipe-rows').innerHTML = '';
            document.getElementById('recipe-error').classList.add('hidden');

            if (materialsCache.length === 0) {
                document.getElementById('recipe-rows').innerHTML = '<p class="text-xs text-slate-400 text-center py-3">Registra insumos en el módulo "Materiales" antes de crear una receta.</p>';
            } else {
                const res = await fetch(`api/production.php?product_id=${productId}`);
                const recipe = res.ok ? await res.json() : [];
                if (recipe.length === 0) {
                    addRecipeRow();
                } else {
                    recipe.forEach(item => addRecipeRow(item.material_id, item.quantity_required));
                }
            }

            document.getElementById('recipe-modal').classList.remove('hidden');
        }

        document.getElementById('recipe-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const errDiv = document.getElementById('recipe-error');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Guardando...';
            btn.disabled = true;
            errDiv.classList.add('hidden');

            const items = [...document.querySelectorAll('#recipe-rows > div')].map(row => ({
                material_id: parseInt(row.querySelector('.recipe-material').value),
                quantity_required: parseFloat(row.querySelector('.recipe-quantity').value)
            })).filter(i => i.material_id && i.quantity_required > 0);

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
                    document.getElementById('recipe-modal').classList.add('hidden');
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
