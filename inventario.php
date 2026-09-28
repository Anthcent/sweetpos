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
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium line-clamp-1 sm:line-clamp-none">Catálogo de helados, postres, precios & existencias</p>
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
            <div class="flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
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
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div class="bg-white rounded-3xl shadow-card border border-slate-200/90 overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="w-full min-w-[580px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Producto</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Categoría</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Precio</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold">Stock</th>
                                <th class="px-5 sm:px-6 py-3.5 sm:py-4 font-bold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-table" class="divide-y divide-slate-100/80 text-sm">
                            <tr><td colspan="5" class="text-center py-12 text-slate-400 font-medium">Cargando inventario...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Producto (compartido) -->
    <?php require '_product_modal.php'; ?>

    <!-- Modal Movimiento de Stock (entrada de reventa o ajuste auditado) -->
    <div id="stock-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-[slideIn_0.2s_ease-out] border border-slate-100 max-h-[92dvh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="package-plus" class="w-4 h-4 stroke-[2.2]"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading">
                        Stock: <span id="stock-product-name" class="text-pink-600 font-bold"></span>
                    </h3>
                </div>
                <button type="button" onclick="closeStockModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form id="stock-form" class="p-6 flex flex-col gap-4">
                <input type="hidden" id="s-product-id" value="">
                <p id="stock-hint" class="text-xs text-slate-500"></p>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Tipo de movimiento</label>
                    <select id="s-reason" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-medium">
                        <option value="purchase">Entrada por compra (suma unidades)</option>
                        <option value="adjustment">Ajuste (suma o resta unidades)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cantidad (unidades)</label>
                    <input type="number" id="s-quantity" step="1" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-base font-bold">
                    <p class="text-[11px] text-slate-400 mt-1">En un ajuste, usa un número negativo para restar (ej. -2 por merma).</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nota</label>
                    <input type="text" id="s-note" maxlength="200" placeholder="Ej. Factura 123, conteo físico, producto dañado" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm">
                </div>
                <div id="s-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></div>
                <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="check" class="w-4 h-4 stroke-[2.2]"></i> Registrar Movimiento
                </button>
            </form>
        </div>
    </div>

    <script>
        const CAN_DELETE = <?= canAccess('inventario_delete') ? 'true' : 'false' ?>;
        const IS_ADMIN = <?= currentRole() === 'admin' ? 'true' : 'false' ?>;
        let allProductsCache = [];
        let currentInvFilter = 'all';

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
        });
        document.addEventListener('product:created', loadInventory);

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
                const res = await fetch('api/products.php');
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error');
                // PDO/SQLite puede devolver numeros como texto: normalizar para comparar
                allProductsCache = data.map(p => ({ ...p, id: Number(p.id), stock: Number(p.stock), recipe_items: Number(p.recipe_items) || 0 }));
                updateInvCounts(allProductsCache);
                filterInventory();
            } catch (e) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-rose-500 font-medium">Error al cargar productos.</td></tr>';
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

        function filterInventory() {
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

        function renderInventoryTable(products) {
            const table = document.getElementById('inventory-table');
            table.innerHTML = '';
            if(products.length === 0) {
                table.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-slate-400 font-medium">No se encontraron productos coincidentes.</td></tr>';
                return;
            }

            products.forEach(p => {
                const iconName = getProductIcon(p.name, p.category);
                const isLowStock = p.stock <= 5;
                const isOutOfStock = p.stock <= 0;
                
                let catClass = 'bg-pink-50 text-pink-700 border-pink-200';
                if (p.category === 'Postres') catClass = 'bg-purple-50 text-purple-700 border-purple-200';
                if (p.category === 'Varios') catClass = 'bg-amber-50 text-amber-800 border-amber-200';

                table.innerHTML += `
                    <tr class="hover:bg-pink-50/30 transition-colors group">
                        <td class="px-5 sm:px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 border border-white shadow-xs" style="background-color: ${safeColor(p.image_color)}; color: #9e2d58;">
                                    <i data-lucide="${iconName}" class="w-5 h-5 stroke-[1.8]"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 font-heading block leading-tight group-hover:text-pink-600 transition-colors">${escapeHtml(p.name)}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">ID #${p.id}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5">
                            <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${catClass}">${escapeHtml(p.category)}</span>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5 font-black text-slate-900 font-heading">$${parseFloat(p.price).toFixed(2)}</td>
                        <td class="px-5 sm:px-6 py-3.5">
                            ${isOutOfStock 
                                ? '<span class="stock-pill out-stock">Agotado</span>' 
                                : (isLowStock 
                                    ? `<span class="stock-pill low-stock">● ${p.stock} uni</span>` 
                                    : `<span class="stock-pill in-stock">● ${p.stock} uni</span>`)}
                        </td>
                        <td class="px-5 sm:px-6 py-3.5 text-right">
                            ${(p.recipe_items === 0 || IS_ADMIN) ? `
                            <button onclick="openStockModal(${p.id})" title="${p.recipe_items > 0 ? 'Ajustar stock (admin)' : 'Registrar entrada de stock'}" class="text-slate-400 hover:text-emerald-600 p-2 rounded-xl hover:bg-emerald-50 transition-colors">
                                <i data-lucide="package-plus" class="w-4 h-4"></i>
                            </button>` : ''}
                            ${CAN_DELETE ? `
                            <button onclick="deleteProduct(${p.id})" title="Eliminar Producto" class="text-slate-400 hover:text-rose-600 p-2 rounded-xl hover:bg-rose-50 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>` : ''}
                        </td>
                    </tr>
                `;
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
        // Productos sin receta (reventa): entradas por compra y ajustes.
        // Productos con receta: el stock sube solo por Elaboración; admin puede ajustar con nota.

        function openStockModal(id) {
            const p = allProductsCache.find(x => x.id === Number(id));
            if (!p) return;
            const hasRecipe = p.recipe_items > 0;
            const reasonSel = document.getElementById('s-reason');
            reasonSel.querySelector('option[value="purchase"]').disabled = hasRecipe;
            reasonSel.value = hasRecipe ? 'adjustment' : 'purchase';

            document.getElementById('s-product-id').value = p.id;
            document.getElementById('stock-product-name').innerText = p.name;
            document.getElementById('stock-hint').innerText = hasRecipe
                ? 'Este producto tiene receta: para aumentar su stock usa Elaboración. Aquí solo puedes registrar un ajuste de administrador con nota obligatoria.'
                : `Producto de reventa. Stock actual: ${p.stock} unidades.`;
            document.getElementById('s-quantity').value = '';
            document.getElementById('s-note').value = '';
            document.getElementById('s-error').classList.add('hidden');
            document.getElementById('stock-modal').classList.remove('hidden');
        }

        function closeStockModal() {
            document.getElementById('stock-modal').classList.add('hidden');
        }

        document.getElementById('stock-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const errDiv = document.getElementById('s-error');
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Registrando...';
            btn.disabled = true;
            errDiv.classList.add('hidden');

            try {
                const res = await fetch('api/products.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'stock_entry',
                        id: parseInt(document.getElementById('s-product-id').value),
                        reason: document.getElementById('s-reason').value,
                        quantity: parseInt(document.getElementById('s-quantity').value),
                        note: document.getElementById('s-note').value.trim()
                    })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    closeStockModal();
                    await loadInventory();
                } else {
                    errDiv.innerText = data.error || 'No se pudo registrar el movimiento.';
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
