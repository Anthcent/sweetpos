// assets/app.js — YILMANU POS Application Logic with Enhanced Pastel Aesthetics

// Estado global
let products = [];
let cart = [];
let currentTable = null;
let searchTimeout = null;
let activeTablesInterval = null;
let currentCategory = 'all';
let currentSearchQuery = '';

// DOM Elements POS
const productsGrid = document.getElementById('products-grid');
const cartItemsContainer = document.getElementById('cart-items');
const cartTotalEl = document.getElementById('cart-total');
const checkoutTriggerBtn = document.getElementById('checkout-trigger-btn');
const toastContainer = document.getElementById('toast-container');
const categoryFilters = document.querySelectorAll('.category-filter-card, .category-filter');
const quickTablesContainer = document.getElementById('quick-tables');
const cartTableInput = document.getElementById('cart-table-number');

// DOM Active Tables Bar (POS)
const activeTablesBar = document.getElementById('active-tables-bar');

// DOM Modal Checkout (POS)
const checkoutModal = document.getElementById('checkout-modal');
const modalTableInfo = document.getElementById('modal-table-info');
const modalTableNumDisplay = document.getElementById('modal-table-num-display');
const modalTotal = document.getElementById('modal-total');
const processPaymentBtn = document.getElementById('process-payment-btn');
const sendToTableBtn = document.getElementById('send-to-table-btn');

// DOM POS Payment Modal (For pending tables)
const posPaymentModal = document.getElementById('pos-payment-modal');
const posPaySaleId = document.getElementById('pos-pay-sale-id');
const posPayModalTotal = document.getElementById('pos-pay-modal-total');
const posPayTableName = document.getElementById('pos-pay-table-name');
const posReferenceFieldContainer = document.getElementById('pos-reference-field-container');
const posPaymentReference = document.getElementById('pos-payment-reference');

// DOM Clientes (busqueda rapida en el cobro)
const checkoutCedula = document.getElementById('checkout-cedula');
const activeClientId = document.getElementById('active-client-id');
const clientSearchBlock = document.getElementById('checkout-client-search');
const clientResultsBox = document.getElementById('client-results');
const fastClientBtn = document.getElementById('fast-client-btn');
const registerClientBtn = document.getElementById('register-client-btn');
const clientSelectedChip = document.getElementById('client-selected');
const clientNameDisplay = document.getElementById('client-name-display');
const clientCedulaDisplay = document.getElementById('client-cedula-display');
const clearClientBtn = document.getElementById('clear-client-btn');
const clientModal = document.getElementById('client-modal');

// DOM Resumen del pedido (burbuja del cobro)
const checkoutSummary = document.getElementById('checkout-summary');
const checkoutSummaryToggle = document.getElementById('checkout-summary-toggle');
const checkoutSummaryList = document.getElementById('checkout-summary-list');
const checkoutSummaryCount = document.getElementById('checkout-summary-count');
const checkoutSummaryTotal = document.getElementById('checkout-summary-total');

const CLIENT_SEARCH_DEBOUNCE_MS = 200;
const clientSearchState = { results: [], activeIndex: -1, term: '', controller: null, open: false };

const referenceFieldContainer = document.getElementById('reference-field-container');
const paymentReference = document.getElementById('payment-reference');

// DOM Client Modal
const clientForm = document.getElementById('client-form');

// Helper: Visual theme & minimalist icon per product
function getProductTheme(product) {
    const name = (product.name || '').toLowerCase();
    const cat = (product.category || '').toLowerCase();
    
    let icon = 'ice-cream-cone';
    let iconColor = '#d9487f';
    let bgGradient = 'linear-gradient(135deg, #fdf2f6 0%, #fbd5e5 100%)';
    let chipClass = 'bg-pink-50 text-pink-700 border-pink-200';
    
    if (name.includes('vainilla') || name.includes('crema')) {
        icon = 'ice-cream-cone';
        iconColor = '#d97706';
        bgGradient = 'linear-gradient(135deg, #fef9ee 0%, #fdedc6 100%)';
        chipClass = 'bg-amber-50 text-amber-800 border-amber-200';
    } else if (name.includes('chocolate') || name.includes('cacao') || name.includes('choc')) {
        icon = 'ice-cream-2';
        iconColor = '#9a3412';
        bgGradient = 'linear-gradient(135deg, #fff3eb 0%, #fedcc7 100%)';
        chipClass = 'bg-orange-50 text-orange-800 border-orange-200';
    } else if (name.includes('fresa') || name.includes('berry') || name.includes('frutilla')) {
        icon = 'ice-cream-cone';
        iconColor = '#d9487f';
        bgGradient = 'linear-gradient(135deg, #fdf2f6 0%, #fcd4e2 100%)';
        chipClass = 'bg-pink-50 text-pink-800 border-pink-200';
    } else if (cat.includes('helad')) {
        icon = 'ice-cream-cone';
        iconColor = '#d9487f';
        bgGradient = 'linear-gradient(135deg, #fce8f0 0%, #fbd5e5 100%)';
        chipClass = 'bg-pink-50 text-pink-800 border-pink-200';
    } else if (name.includes('cheesecake') || name.includes('torta') || name.includes('pastel') || name.includes('pie') || name.includes('tarta')) {
        icon = 'cake-slice';
        iconColor = '#7c3aed';
        bgGradient = 'linear-gradient(135deg, #f4edfd 0%, #e8d7fa 100%)';
        chipClass = 'bg-purple-50 text-purple-800 border-purple-200';
    } else if (name.includes('brownie') || name.includes('cookie') || name.includes('galleta') || name.includes('macaron')) {
        icon = 'cookie';
        iconColor = '#854d0e';
        bgGradient = 'linear-gradient(135deg, #fdf8ed 0%, #fbedcf 100%)';
        chipClass = 'bg-amber-50 text-amber-900 border-amber-200';
    } else if (cat.includes('postre')) {
        icon = 'cake-slice';
        iconColor = '#9333ea';
        bgGradient = 'linear-gradient(135deg, #f5effe 0%, #eadcfd 100%)';
        chipClass = 'bg-purple-50 text-purple-800 border-purple-200';
    } else if (name.includes('café') || name.includes('cafe') || name.includes('espresso') || name.includes('cappuccino') || name.includes('americano')) {
        icon = 'coffee';
        iconColor = '#78350f';
        bgGradient = 'linear-gradient(135deg, #f9f5f1 0%, #eee3d5 100%)';
        chipClass = 'bg-stone-100 text-stone-800 border-stone-200';
    } else if (name.includes('malteada') || name.includes('batido') || name.includes('soda') || name.includes('jugo') || name.includes('bebida')) {
        icon = 'cup-soda';
        iconColor = '#059669';
        bgGradient = 'linear-gradient(135deg, #e8f7f0 0%, #d1f2e2 100%)';
        chipClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
    } else {
        icon = 'sparkles';
        iconColor = '#d9487f';
        bgGradient = 'linear-gradient(135deg, #fdf2f6 0%, #fbd5e5 100%)';
        chipClass = 'bg-pink-50 text-pink-800 border-pink-200';
    }
    
    // Si viene un color personalizado en la base de datos
    if (/^#[0-9a-fA-F]{6}$/.test(product.image_color || '') && product.image_color !== '#fbcfe8') {
        bgGradient = `linear-gradient(135deg, ${product.image_color}33 0%, ${product.image_color}bb 100%)`;
    }
    
    return { icon, iconColor, bgGradient, chipClass };
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    if(productsGrid) {
        fetchProducts();
        setupCategoryFilters();
        renderQuickTables();
        
        // Mesas activas (tambien mantiene la ocupacion de mesas al dia)
        fetchActiveTables();
        if (window.SweetLive) {
            // Other cashiers' tickets/payments and stock changes, within seconds
            SweetLive.subscribe('sales', () => fetchActiveTables());
            SweetLive.subscribe('stock', () => refreshProductsQuietly());
        } else {
            activeTablesInterval = setInterval(fetchActiveTables, 10000);
        }
        
        checkoutTriggerBtn?.addEventListener('click', openCheckoutModal);
        processPaymentBtn?.addEventListener('click', () => processCheckout('paid'));
        sendToTableBtn?.addEventListener('click', () => processCheckout('pending'));
        
        setupClientSearch();
        setupCheckoutSummary();
        
        if(clientForm) {
            clientForm.addEventListener('submit', registerClient);
        }
    }
});

// Toast Notificaciones
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
    const icon = type === 'success' ? 'check-circle-2' : 'alert-circle';
    toast.className = `toast px-4 py-3 rounded-2xl text-white shadow-xl flex items-center gap-2.5 font-medium text-sm ${bgColor}`;
    toast.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5 shrink-0 stroke-[2.2]"></i><span>${escapeHtml(message)}</span>`;
    if(toastContainer) {
        toastContainer.appendChild(toast);
        if(window.lucide) window.lucide.createIcons({ root: toast });
        setTimeout(() => { 
            toast.classList.add('hide'); 
            setTimeout(() => toast.remove(), 250); 
        }, 3000);
    }
}

// Productos y Grid
// Bumped on every load so a slower, older response never overwrites a newer one.
let productsLoadSeq = 0;

async function loadProductList() {
    const response = await fetch('api/products.php');
    const rawProducts = await response.json();
    if (!response.ok || !Array.isArray(rawProducts)) throw new Error('Could not load products');
    return rawProducts.map(p => ({
        ...p,
        id: parseInt(p.id, 10),
        price: parseFloat(p.price),
        stock: parseInt(p.stock, 10)
    }));
}

async function fetchProducts() {
    const seq = ++productsLoadSeq;
    try {
        productsGrid.innerHTML = '<div class="col-span-full flex flex-col items-center justify-center py-16"><div class="loader mb-3"></div><p class="text-xs font-semibold text-slate-400">Cargando delicias...</p></div>';
        const list = await loadProductList();
        if (seq !== productsLoadSeq) return;
        products = list;
        updateCategoryCounts();
        applyProductFilters();
    } catch (error) {
        if (seq !== productsLoadSeq) return;
        showToast('Error cargando productos', 'error');
        productsGrid.innerHTML = '<p class="text-slate-500 col-span-full text-center py-10 font-medium">Error al cargar productos del inventario.</p>';
    }
}

// Live stock refresh: no loader, keeps cart, filters, search and scroll position.
async function refreshProductsQuietly() {
    if (!productsGrid) return;
    const seq = ++productsLoadSeq;
    try {
        const list = await loadProductList();
        if (seq !== productsLoadSeq) return;
        const scroller = productsGrid.parentElement;
        const scrollTop = scroller ? scroller.scrollTop : 0;
        products = list;
        updateCategoryCounts();
        applyProductFilters();
        if (scroller) scroller.scrollTop = scrollTop;
    } catch (error) {
        console.error('Live product refresh failed', error);
    }
}

function updateCategoryCounts() {
    const total = products.length;
    const helados = products.filter(p => (p.category || '').toLowerCase().includes('helad')).length;
    const postres = products.filter(p => (p.category || '').toLowerCase().includes('postre')).length;
    const varios = products.filter(p => {
        const c = (p.category || '').toLowerCase();
        return !c.includes('helad') && !c.includes('postre');
    }).length;

    const elAll = document.getElementById('count-all');
    const elHelados = document.getElementById('count-helados');
    const elPostres = document.getElementById('count-postres');
    const elVarios = document.getElementById('count-varios');

    if (elAll) elAll.textContent = `${total} items`;
    if (elHelados) elHelados.textContent = `${helados} items`;
    if (elPostres) elPostres.textContent = `${postres} items`;
    if (elVarios) elVarios.textContent = `${varios} items`;
}

function applyProductFilters() {
    let filtered = products;
    if (currentCategory !== 'all') {
        const catKey = currentCategory.toLowerCase();
        if (catKey === 'varios') {
            filtered = filtered.filter(p => {
                const c = (p.category || '').toLowerCase();
                return !c.includes('helad') && !c.includes('postre');
            });
        } else if (catKey.includes('helad')) {
            filtered = filtered.filter(p => (p.category || '').toLowerCase().includes('helad'));
        } else if (catKey.includes('postre')) {
            filtered = filtered.filter(p => (p.category || '').toLowerCase().includes('postre'));
        } else {
            filtered = filtered.filter(p => (p.category || '').toLowerCase() === catKey);
        }
    }
    if (currentSearchQuery.trim() !== '') {
        const q = currentSearchQuery.toLowerCase();
        filtered = filtered.filter(p => (p.name || '').toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q));
    }

    const countEl = document.getElementById('pos-search-count');
    if (countEl) {
        if (currentSearchQuery.trim() !== '') {
            countEl.classList.remove('hidden');
            const total = filtered.length;
            countEl.textContent = `${total} ${total === 1 ? 'postre' : 'postres'}`;
            if (total === 0) {
                countEl.className = 'text-[10px] sm:text-[11px] font-extrabold text-rose-600 bg-rose-50 px-2 sm:px-2.5 py-0.5 rounded-full border border-rose-200 shrink-0 font-heading animate-[pulse_1s_ease-in-out_1]';
            } else {
                countEl.className = 'text-[10px] sm:text-[11px] font-extrabold text-pink-700 bg-pink-50 px-2 sm:px-2.5 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading';
            }
        } else {
            countEl.classList.add('hidden');
        }
    }

    renderProducts(filtered);
}

function setupCategoryFilters() {
    const filterBtns = document.querySelectorAll('.category-filter-card, .category-filter');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            filterBtns.forEach(b => {
                b.classList.remove('active-filter', 'bg-pink-200');
            });
            const target = e.currentTarget;
            target.classList.add('active-filter');
            currentCategory = target.dataset.cat || 'all';
            applyProductFilters();
        });
    });

    const searchInput = document.getElementById('pos-search-input');
    const clearBtn = document.getElementById('pos-search-clear');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim();
            if (clearBtn) {
                if (currentSearchQuery.length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
            applyProductFilters();
        });

        // Limpiar con ESC dentro del input
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                clearPosSearch();
                searchInput.blur();
            }
        });
    }

    // Atajo de teclado: presionar '/' enfoca el buscador si no hay un modal abierto
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== searchInput) {
            const hasOpenModal = document.querySelector('.fixed:not(.hidden) input, #checkout-modal:not(.hidden)');
            if (!hasOpenModal && searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        }
    });
}

function clearPosSearch() {
    const searchInput = document.getElementById('pos-search-input');
    const clearBtn = document.getElementById('pos-search-clear');
    const countEl = document.getElementById('pos-search-count');
    if (searchInput) {
        searchInput.value = '';
        currentSearchQuery = '';
        if (clearBtn) clearBtn.classList.add('hidden');
        if (countEl) countEl.classList.add('hidden');
        searchInput.focus();
        applyProductFilters();
    }
}
window.clearPosSearch = clearPosSearch;

function renderProducts(items) {
    if(!productsGrid) return;
    productsGrid.innerHTML = '';
    
    if(items.length === 0) {
        productsGrid.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-400">
                <div class="w-16 h-16 rounded-3xl bg-pink-50 text-pink-400 flex items-center justify-center mb-3">
                    <i data-lucide="sparkles" class="w-8 h-8"></i>
                </div>
                <p class="font-heading font-bold text-slate-700 text-base mb-1">No hay delicias en esta categoría</p>
                <p class="text-xs text-slate-400">Prueba seleccionando "Todos" o busca otro sabor.</p>
            </div>
        `;
        if(window.lucide) window.lucide.createIcons({ root: productsGrid });
        return;
    }

    items.forEach(product => {
        const isOutOfStock = product.stock <= 0;
        const theme = getProductTheme(product);

        const card = document.createElement('div');
        card.className = `product-card p-3.5 cursor-pointer flex flex-col justify-between group ${isOutOfStock ? 'opacity-70' : ''}`;
        
        card.innerHTML = `
            <div>
                <!-- Visual Capsule -->
                <div class="product-visual-capsule border border-white/70 shadow-inner" style="background: ${theme.bgGradient};">
                    <div class="absolute top-2.5 right-2.5 text-white/60 opacity-80 pointer-events-none">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    </div>
                    <div class="product-visual-icon w-14 h-14 rounded-2xl bg-white/85 backdrop-blur-sm shadow-sm flex items-center justify-center border border-white/90" style="color: ${theme.iconColor};">
                        <i data-lucide="${theme.icon}" class="w-7 h-7 stroke-[1.85]"></i>
                    </div>
                    ${!isOutOfStock ? `
                    <div class="product-add-badge">
                        <i data-lucide="plus" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>` : ''}
                </div>

                <!-- Info Header -->
                <div class="flex items-center justify-between gap-1 mb-1.5">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${theme.chipClass}">${escapeHtml(product.category)}</span>
                    ${isOutOfStock 
                        ? '<span class="stock-pill out-stock">Agotado</span>' 
                        : (product.stock <= 5 
                            ? `<span class="stock-pill low-stock">● ${product.stock} disp</span>` 
                            : `<span class="stock-pill in-stock">● ${product.stock} disp</span>`)}
                </div>
                <h3 class="font-bold text-slate-800 text-sm leading-snug font-heading group-hover:text-pink-600 transition-colors line-clamp-1">${escapeHtml(product.name)}</h3>
            </div>

            <!-- Price Footer -->
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex justify-between items-center">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Precio</span>
                    <div class="font-extrabold text-base text-pink-600 font-heading">$${product.price.toFixed(2)}</div>
                </div>
                <div class="text-[11px] font-bold ${isOutOfStock ? 'text-emerald-600 group-hover:text-emerald-700' : 'text-slate-400 group-hover:text-pink-500'} transition-colors flex items-center gap-0.5">
                    <span>${isOutOfStock ? 'Reponer' : 'Añadir'}</span>
                    <i data-lucide="${isOutOfStock ? 'package-plus' : 'plus'}" class="w-3 h-3"></i>
                </div>
            </div>
        `;
        
        if(!isOutOfStock) {
            card.addEventListener('click', () => {
                addToCart(product);
                // Micro-bounce
                card.style.transform = 'scale(0.96)';
                setTimeout(() => { card.style.transform = ''; }, 150);
            });
        } else {
            card.addEventListener('click', () => {
                if (typeof window.openRestockModal === 'function') {
                    window.openRestockModal(product.id);
                } else {
                    showToast('Producto agotado. Pide reposición al encargado.', 'error');
                }
            });
        }
        productsGrid.appendChild(card);
    });

    if(window.lucide) window.lucide.createIcons({ root: productsGrid });
}

// Mesas Rápidas (Macaron Pills)
// Dine-in tables with a pending ticket (table_number as string). Take-away
// orders (999) never occupy a table: several can be pending at once.
const TAKEOUT_TABLE_NUMBER = '999';
const OCCUPANCY_REFRESH_MIN_MS = 3000;
let occupiedTables = new Set();
let occupancyFetchedAt = 0;

function isTableOccupied(t) {
    return t !== 'L' && occupiedTables.has(String(t));
}

function updateTableOccupancy(pendingSales) {
    occupiedTables = new Set(
        (Array.isArray(pendingSales) ? pendingSales : [])
            .map(sale => String(sale.table_number ?? ''))
            .filter(n => n !== '' && n !== TAKEOUT_TABLE_NUMBER)
    );
    occupancyFetchedAt = Date.now();
    applyTableOccupancy();
}

async function fetchPendingSales() {
    const res = await fetch('api/sales.php');
    const data = await res.json();
    if(!res.ok || !Array.isArray(data)) throw new Error((data && data.error) || 'Error al consultar mesas');
    updateTableOccupancy(data);
    return data;
}

// Fresh occupancy when the cashier is about to pick a table (throttled).
async function refreshTableOccupancy(force = false) {
    if(!force && Date.now() - occupancyFetchedAt < OCCUPANCY_REFRESH_MIN_MS) return;
    try {
        await fetchPendingSales();
    } catch (e) {
        console.error('Could not refresh table occupancy', e);
    }
}

function applyTableOccupancy() {
    if(!quickTablesContainer) return;
    quickTablesContainer.querySelectorAll('[data-table]').forEach(btn => {
        const t = btn.dataset.table;
        if(t === 'L') return;
        const occupied = isTableOccupied(t);
        btn.classList.toggle('is-occupied', occupied);
        btn.setAttribute('aria-disabled', String(occupied));
        btn.title = occupied ? `Mesa ${t}: Ocupada` : `Mesa ${t}`;
        btn.setAttribute('aria-label', occupied ? `Mesa ${t}, ocupada` : `Mesa ${t}`);
    });
    // Another cashier took the selected table: drop the selection
    if(currentTable !== null && isTableOccupied(currentTable)) {
        const taken = currentTable;
        clearSelectedTable();
        showToast(`La mesa ${taken} acaba de ser ocupada. Elige otra mesa.`, 'error');
    }
}

function clearSelectedTable() {
    currentTable = null;
    if(cartTableInput) cartTableInput.value = '';
    quickTablesContainer?.querySelectorAll('.macaron-table-btn').forEach(b => b.classList.remove('active'));
}

function renderQuickTables() {
    if(!quickTablesContainer) return;
    quickTablesContainer.innerHTML = '';
    const tables = [1, 2, 3, 4, 5, 6, 7, 8, 'L'];

    tables.forEach(t => {
        const btn = document.createElement('button');
        const isTakeaway = t === 'L';
        btn.type = 'button';
        btn.className = 'macaron-table-btn';
        btn.dataset.table = String(t);

        if (isTakeaway) {
            btn.innerHTML = '<span class="text-xs font-black tracking-tight">Llevar</span>';
            btn.title = 'Para Llevar';
        } else {
            btn.innerText = t;
            btn.title = `Mesa ${t}`;
        }

        btn.addEventListener('click', () => selectQuickTable(t, btn));
        quickTablesContainer.appendChild(btn);
    });
    applyTableOccupancy();

    // Re-check occupancy as soon as the cashier reaches for the table picker
    if(!quickTablesContainer.dataset.occupancyHooked) {
        quickTablesContainer.dataset.occupancyHooked = '1';
        quickTablesContainer.addEventListener('pointerenter', () => refreshTableOccupancy());
        quickTablesContainer.addEventListener('focusin', () => refreshTableOccupancy());
    }
}

function selectQuickTable(t, btnElement) {
    if(isTableOccupied(t)) {
        showToast(`La mesa ${t} está ocupada. Agrega productos desde Zonas de Mesa.`, 'error');
        return;
    }
    Array.from(quickTablesContainer.children).forEach(b => {
        b.classList.remove('active');
    });
    
    if(currentTable === t) {
        currentTable = null;
        if(cartTableInput) cartTableInput.value = '';
    } else {
        btnElement.classList.add('active');
        currentTable = t;
        if(cartTableInput) cartTableInput.value = t === 'L' ? 'Llevar' : t;
    }
}

// Carrito
function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    if(existing) {
        if(existing.quantity < product.stock) {
            existing.quantity++;
            existing.subtotal = existing.quantity * product.price;
        } else {
            showToast('Stock máximo alcanzado', 'error');
            return;
        }
    } else {
        cart.push({ ...product, quantity: 1, subtotal: product.price });
    }
    renderCart();
}

function updateQuantity(id, change) {
    const itemIndex = cart.findIndex(i => i.id === id);
    if(itemIndex > -1) {
        const item = cart[itemIndex];
        const newQty = item.quantity + change;
        // A live refresh may have dropped the product (deactivated elsewhere):
        // fall back to the cart line so it can still be reduced or removed.
        const originalProduct = products.find(p => p.id === id) || { ...item, stock: Math.min(item.stock, item.quantity) };

        if(newQty > 0 && newQty <= originalProduct.stock) {
            item.quantity = newQty;
            item.subtotal = item.quantity * originalProduct.price;
        } else if (newQty === 0) {
            cart.splice(itemIndex, 1);
        } else if (newQty > originalProduct.stock) {
            showToast('Stock insuficiente', 'error');
        }
        renderCart();
    }
}

// Control del Drawer de Carrito (Tablet y Móvil < 1024px)
function openMobileCart() {
    document.body.classList.add('cart-drawer-open');
    refreshTableOccupancy();
    const floatingBtn = document.getElementById('mobile-cart-floating-btn');
    if (floatingBtn) floatingBtn.setAttribute('aria-expanded', 'true');
}

function closeMobileCart() {
    document.body.classList.remove('cart-drawer-open');
    const floatingBtn = document.getElementById('mobile-cart-floating-btn');
    if (floatingBtn) floatingBtn.setAttribute('aria-expanded', 'false');
}

function toggleMobileCart() {
    document.body.classList.toggle('cart-drawer-open');
}

window.openMobileCart = openMobileCart;
window.closeMobileCart = closeMobileCart;
window.toggleMobileCart = toggleMobileCart;

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('cart-drawer-open')) {
        closeMobileCart();
    }
});

function renderCart() {
    if(!cartItemsContainer) return;
    cartItemsContainer.innerHTML = '';
    let total = 0;
    let itemCount = 0;
    
    const countBadge = document.getElementById('cart-count-badge');
    const mobileHeaderCount = document.getElementById('mobile-header-cart-count');
    const floatingCartBar = document.getElementById('mobile-cart-floating-bar');
    const floatingCartCount = document.getElementById('floating-cart-count');
    const floatingCartTotal = document.getElementById('floating-cart-total');
    
    if(cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-slate-400 py-12 px-4 text-center">
                <div class="w-16 h-16 rounded-3xl bg-pink-50 text-pink-400 flex items-center justify-center mb-3 shadow-inner border border-pink-100">
                    <i data-lucide="ice-cream-cone" class="w-8 h-8 stroke-[1.75]"></i>
                </div>
                <h4 class="font-heading font-bold text-slate-700 text-sm mb-1">Tu orden está vacía</h4>
                <p class="text-xs text-slate-400 max-w-[190px]">Selecciona helados o postres del menú para agregarlos aquí</p>
            </div>
        `;
        if(window.lucide) window.lucide.createIcons({ root: cartItemsContainer });
        checkoutTriggerBtn.disabled = true;
        if(countBadge) countBadge.innerText = '0';
        if(mobileHeaderCount) mobileHeaderCount.innerText = '0';
        if(floatingCartBar) {
            floatingCartBar.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
            floatingCartBar.classList.remove('translate-y-0', 'opacity-100');
        }
    } else {
        checkoutTriggerBtn.disabled = false;
        cart.forEach(item => {
            total += item.subtotal;
            itemCount += item.quantity;
            const theme = getProductTheme(item);
            
            const el = document.createElement('div');
            el.className = 'flex items-center gap-2.5 p-3 bg-white rounded-2xl border border-slate-100 shadow-sm hover:border-pink-200 transition-colors group';
            el.innerHTML = `
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-white/80 shadow-xs" style="background: ${theme.bgGradient}; color: ${theme.iconColor};">
                    <i data-lucide="${theme.icon}" class="w-5 h-5 stroke-[1.85]"></i>
                </div>
                <div class="flex-1 min-w-0 pr-1">
                    <h4 class="text-xs font-bold text-slate-800 truncate font-heading group-hover:text-pink-600 transition-colors">${escapeHtml(item.name)}</h4>
                    <div class="text-[11px] text-pink-600 font-extrabold font-heading">$${item.price.toFixed(2)} <span class="text-slate-400 font-normal text-[10px]">c/u</span></div>
                </div>
                <div class="cart-stepper shrink-0">
                    <button type="button" onclick="updateQuantity(${item.id}, -1)" title="Restar" class="text-slate-500 hover:text-pink-600">
                        <i data-lucide="minus" class="w-3 h-3"></i>
                    </button>
                    <span class="text-xs font-black w-5 text-center text-slate-800 font-heading">${item.quantity}</span>
                    <button type="button" onclick="updateQuantity(${item.id}, 1)" title="Sumar" class="text-slate-500 hover:text-pink-600">
                        <i data-lucide="plus" class="w-3 h-3"></i>
                    </button>
                </div>
                <div class="text-right pl-1 shrink-0">
                    <span class="text-xs font-black text-slate-800 font-heading">$${item.subtotal.toFixed(2)}</span>
                </div>
            `;
            cartItemsContainer.appendChild(el);
        });
        if(window.lucide) window.lucide.createIcons({ root: cartItemsContainer });
        if(countBadge) countBadge.innerText = String(itemCount);
        if(mobileHeaderCount) mobileHeaderCount.innerText = String(itemCount);
        if(floatingCartBar) {
            floatingCartBar.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
            floatingCartBar.classList.add('translate-y-0', 'opacity-100');
            if(floatingCartCount) floatingCartCount.innerText = `${itemCount} ${itemCount === 1 ? 'postre' : 'postres'}`;
            if(floatingCartTotal) floatingCartTotal.innerText = '$' + total.toFixed(2);
        }
    }
    cartTotalEl.innerText = '$' + total.toFixed(2);
    renderCheckoutSummary();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

/* =========================================================
   CLIENTE EN EL COBRO: busqueda por cedula o nombre + registro rapido
   ========================================================= */
function setupClientSearch() {
    if(!checkoutCedula) return;

    checkoutCedula.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const term = checkoutCedula.value.trim();
        if(term === '') {
            clientSearchState.controller?.abort();
            closeClientResults();
            registerClientBtn?.classList.add('hidden');
            return;
        }
        searchTimeout = setTimeout(() => runClientSearch(term), CLIENT_SEARCH_DEBOUNCE_MS);
    });

    checkoutCedula.addEventListener('keydown', handleClientSearchKeys);
    checkoutCedula.addEventListener('blur', () => setTimeout(closeClientResults, 150));
    checkoutCedula.addEventListener('focus', () => {
        if(checkoutCedula.value.trim() !== '' && clientSearchState.term === checkoutCedula.value.trim()) {
            openClientResults();
        }
    });

    clientResultsBox?.addEventListener('mousedown', (e) => e.preventDefault()); // keep focus on the input
    clientResultsBox?.addEventListener('click', (e) => {
        const option = e.target.closest('[data-index]');
        if(option) pickClientOption(Number(option.dataset.index));
    });

    fastClientBtn?.addEventListener('click', useAnonymousClient);
    registerClientBtn?.addEventListener('click', () => openClientRegister(checkoutCedula.value));
    clearClientBtn?.addEventListener('click', () => clearClient(true));
}

// Parses a fetch response as JSON. A non-JSON body (PHP warning, proxy error
// page, empty body) is logged with a short excerpt and returns null.
async function readJsonResponse(res) {
    const text = await res.text();
    try {
        return text === '' ? null : JSON.parse(text);
    } catch (parseError) {
        console.error(`Non-JSON response from ${res.url} (HTTP ${res.status}):`, text.slice(0, 500));
        return null;
    }
}

function setClientSearchIcon(loading) {
    const icon = checkoutCedula?.parentElement.querySelector('[data-lucide], svg');
    if(!icon) return;
    const fresh = document.createElement('i');
    fresh.setAttribute('data-lucide', loading ? 'loader-2' : 'search');
    fresh.className = 'w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none' + (loading ? ' animate-spin' : '');
    icon.replaceWith(fresh);
    if(window.lucide) window.lucide.createIcons({ root: checkoutCedula.parentElement });
}

async function runClientSearch(term) {
    clientSearchState.controller?.abort();
    const controller = new AbortController();
    clientSearchState.controller = controller;
    setClientSearchIcon(true);
    try {
        const res = await fetch(`api/clients.php?q=${encodeURIComponent(term)}`, { signal: controller.signal });
        const data = await readJsonResponse(res);
        if(!res.ok || !Array.isArray(data)) {
            const error = new Error((data && data.error) || `Respuesta inesperada del servidor (HTTP ${res.status}).`);
            error.userMessage = data && data.error;
            throw error;
        }
        // Ignore stale answers: the user kept typing
        if(checkoutCedula.value.trim() !== term) return;
        clientSearchState.term = term;
        clientSearchState.results = Array.isArray(data) ? data : [];
        clientSearchState.activeIndex = clientSearchState.results.length > 0 ? 0 : -1;
        renderClientResults();
    } catch(e) {
        if(e.name !== 'AbortError') {
            console.error('Client search failed:', e);
            showToast(e.userMessage || 'Error al buscar cliente', 'error');
        }
    } finally {
        if(clientSearchState.controller === controller) {
            clientSearchState.controller = null;
            setClientSearchIcon(false);
        }
    }
}

function renderClientResults() {
    if(!clientResultsBox) return;
    const { results, activeIndex, term } = clientSearchState;
    const hasResults = results.length > 0;
    registerClientBtn?.classList.toggle('hidden', hasResults);

    if(!hasResults) {
        clientResultsBox.innerHTML = `
            <p class="client-result-empty">Sin coincidencias para «${escapeHtml(term)}».</p>
            <div role="option" id="client-opt-new" data-index="0" aria-selected="true" class="client-result-option">
                <span class="flex items-center gap-2 text-sm font-bold text-pink-800">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Registrar nuevo cliente
                </span>
                <span class="text-[10px] font-bold text-slate-600 uppercase">Enter</span>
            </div>`;
        checkoutCedula.setAttribute('aria-activedescendant', 'client-opt-new');
    } else {
        clientResultsBox.innerHTML = results.map((client, index) => `
            <div role="option" id="client-opt-${index}" data-index="${index}" aria-selected="${index === activeIndex}" class="client-result-option">
                <span class="min-w-0">
                    <span class="block text-sm font-bold truncate">${escapeHtml(client.name)}</span>
                    <span class="block text-xs font-mono text-slate-600">${escapeHtml(client.cedula_display || (window.FieldRules ? FieldRules.formatCedula(client.cedula) : client.cedula))}</span>
                </span>
                <i data-lucide="corner-down-left" class="w-4 h-4 text-slate-500 shrink-0"></i>
            </div>`).join('');
        checkoutCedula.setAttribute('aria-activedescendant', `client-opt-${activeIndex}`);
    }
    if(window.lucide) window.lucide.createIcons({ root: clientResultsBox });
    openClientResults();
}

function openClientResults() {
    if(!clientResultsBox || clientResultsBox.innerHTML.trim() === '') return;
    clientResultsBox.classList.remove('hidden');
    checkoutCedula.setAttribute('aria-expanded', 'true');
    clientSearchState.open = true;
}

function closeClientResults() {
    if(!clientResultsBox) return;
    clientResultsBox.classList.add('hidden');
    checkoutCedula?.setAttribute('aria-expanded', 'false');
    checkoutCedula?.removeAttribute('aria-activedescendant');
    clientSearchState.open = false;
}

function moveClientHighlight(step) {
    const count = clientSearchState.results.length;
    if(count === 0) return;
    clientSearchState.activeIndex = (clientSearchState.activeIndex + step + count) % count;
    clientResultsBox.querySelectorAll('[role="option"]').forEach((option, index) => {
        option.setAttribute('aria-selected', String(index === clientSearchState.activeIndex));
        if(index === clientSearchState.activeIndex) option.scrollIntoView({ block: 'nearest' });
    });
    checkoutCedula.setAttribute('aria-activedescendant', `client-opt-${clientSearchState.activeIndex}`);
}

function handleClientSearchKeys(e) {
    const term = checkoutCedula.value.trim();
    if(e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if(!clientSearchState.open) openClientResults();
        moveClientHighlight(e.key === 'ArrowDown' ? 1 : -1);
    } else if(e.key === 'Enter') {
        e.preventDefault();
        if(term === '') return;
        // Results may still be loading: search now and decide with fresh data
        if(clientSearchState.term !== term || clientSearchState.controller) {
            clearTimeout(searchTimeout);
            runClientSearch(term).then(() => {
                if(clientSearchState.term === term) pickClientOption(clientSearchState.activeIndex);
            });
            return;
        }
        pickClientOption(clientSearchState.activeIndex);
    } else if(e.key === 'Escape' && clientSearchState.open) {
        e.preventDefault();
        e.stopPropagation();
        closeClientResults();
    }
}

function pickClientOption(index) {
    const { results, term } = clientSearchState;
    if(results.length === 0) {
        openClientRegister(term);
    } else if(results[index]) {
        selectClient(results[index]);
    }
}

function showClientChip(name, detail, anonymous) {
    clientNameDisplay.textContent = name;
    clientCedulaDisplay.textContent = detail;
    clientSelectedChip.classList.toggle('is-anonymous', anonymous);
    const icon = clientSelectedChip.querySelector('[data-lucide], svg');
    if(icon) {
        const fresh = document.createElement('i');
        fresh.setAttribute('data-lucide', anonymous ? 'zap' : 'user-check');
        fresh.className = 'w-5 h-5';
        icon.replaceWith(fresh);
        if(window.lucide) window.lucide.createIcons({ root: clientSelectedChip });
    }
    clientSearchBlock.classList.add('hidden');
    clientSelectedChip.classList.remove('hidden');
    closeClientResults();
}

function selectClient(client) {
    activeClientId.value = client.id;
    showClientChip(client.name, client.cedula_display || FieldRules.formatCedula(client.cedula), false);
    processPaymentBtn?.focus();
}

function useAnonymousClient() {
    activeClientId.value = '';
    showClientChip('Cliente rápido', 'Venta sin registro de cliente', true);
    processPaymentBtn?.focus();
}

// Back to the search step. focus=true when the cashier asked to change the client.
function clearClient(focus = false) {
    clearTimeout(searchTimeout);
    clientSearchState.controller?.abort();
    Object.assign(clientSearchState, { results: [], activeIndex: -1, term: '', controller: null });
    activeClientId.value = '';
    checkoutCedula.value = '';
    if(clientResultsBox) clientResultsBox.innerHTML = '';
    closeClientResults();
    registerClientBtn?.classList.add('hidden');
    clientSelectedChip.classList.add('hidden');
    clientSearchBlock.classList.remove('hidden');
    if(focus) checkoutCedula.focus();
}

// Opens the quick-register form pre-filled with what the cashier typed.
function openClientRegister(term = '') {
    if(!clientModal || !clientForm) return;
    clientForm.reset();
    FieldRules.reset(clientForm);
    const cedulaInput = document.getElementById('c-cedula');
    const nameInput = document.getElementById('c-name');
    const clean = String(term).trim();
    let focusTarget = cedulaInput;
    if(clean !== '' && FieldRules.looksLikeCedula(clean)) {
        cedulaInput.value = FieldRules.RULES.cedula.filter(clean);
        focusTarget = nameInput;
    } else if(clean !== '') {
        nameInput.value = FieldRules.RULES.name.filter(clean).trim();
        focusTarget = cedulaInput;
    }
    closeClientResults();
    clientModal.classList.remove('hidden');
    setTimeout(() => focusTarget.focus(), 30);
}

function closeClientModal() {
    if(!clientModal) return;
    clientModal.classList.add('hidden');
    if(!checkoutModal.classList.contains('hidden') && !clientSearchBlock.classList.contains('hidden')) {
        checkoutCedula.focus();
    }
}
window.openClientRegister = openClientRegister;
window.closeClientModal = closeClientModal;

async function registerClient(e) {
    e.preventDefault();
    if(!FieldRules.validateForm(clientForm)) return;

    const btn = clientForm.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Guardando...';
    btn.disabled = true;

    const payload = {
        cedula: document.getElementById('c-cedula').value.trim(),
        name: document.getElementById('c-name').value.trim(),
        phone: document.getElementById('c-phone').value.trim()
    };
    const inCheckout = !checkoutModal.classList.contains('hidden');

    try {
        const res = await fetch('api/clients.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if(res.ok) {
            showToast('Cliente registrado con éxito');
            clientModal.classList.add('hidden');
            clientForm.reset();
            if(inCheckout && result.client) selectClient(result.client);
        } else if(res.status === 409 && result.client && inCheckout) {
            // Already registered: use the existing client instead of blocking the sale
            showToast(`${result.error} Se seleccionó para esta orden.`);
            clientModal.classList.add('hidden');
            selectClient(result.client);
        } else {
            showToast(result.error || 'Error al registrar el cliente', 'error');
        }
    } catch(err) {
        showToast('Error de red', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/* =========================================================
   RESUMEN DEL PEDIDO EN EL COBRO (burbuja)
   ========================================================= */
function setupCheckoutSummary() {
    checkoutSummaryToggle?.addEventListener('click', () => {
        const collapsed = checkoutSummary.classList.toggle('is-collapsed');
        checkoutSummaryToggle.setAttribute('aria-expanded', String(!collapsed));
    });

    checkoutSummaryList?.addEventListener('click', (e) => {
        const button = e.target.closest('button[data-action]');
        if(!button) return;
        const id = Number(button.dataset.id);
        const action = button.dataset.action;
        if(action === 'dec') updateQuantity(id, -1);
        else if(action === 'inc') updateQuantity(id, 1);
        else if(action === 'remove') removeFromCart(id);

        if(cart.length === 0) {
            closeCheckoutModal();
            showToast('La orden quedó vacía. Agrega productos para cobrar.');
            if(window.matchMedia('(max-width: 1023px)').matches) openMobileCart();
        }
    });
}

function isCheckoutOpen() {
    return checkoutModal && !checkoutModal.classList.contains('hidden');
}

// Re-renders the order bubble and every total shown in the checkout from `cart`.
function renderCheckoutSummary() {
    if(!checkoutSummaryList || !isCheckoutOpen()) return;
    const total = cart.reduce((acc, item) => acc + item.subtotal, 0);
    const units = cart.reduce((acc, item) => acc + item.quantity, 0);

    checkoutSummaryList.innerHTML = cart.map(item => {
        const product = products.find(p => p.id === item.id);
        const atMax = product ? item.quantity >= product.stock : true;
        const name = escapeHtml(item.name);
        return `
            <div class="order-summary-line">
                <span class="text-sm font-bold text-slate-800 leading-snug min-w-0 truncate" title="${name}">${name}</span>
                <span class="text-sm font-black text-slate-900 font-heading text-right">$${item.subtotal.toFixed(2)}</span>
                <span class="text-xs text-slate-600 font-semibold">${item.quantity} × $${item.price.toFixed(2)}</span>
                <span class="order-summary-actions justify-end">
                    <button type="button" data-action="dec" data-id="${item.id}" aria-label="Quitar una unidad de ${name}" title="Quitar una unidad"><i data-lucide="minus" class="w-3.5 h-3.5"></i></button>
                    <button type="button" data-action="inc" data-id="${item.id}" aria-label="Agregar una unidad de ${name}" title="Agregar una unidad" ${atMax ? 'disabled' : ''}><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                    <button type="button" data-action="remove" data-id="${item.id}" class="danger" aria-label="Eliminar ${name} de la orden" title="Eliminar del pedido"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </span>
            </div>`;
    }).join('');
    if(window.lucide) window.lucide.createIcons({ root: checkoutSummaryList });

    const totalText = '$' + total.toFixed(2);
    checkoutSummaryTotal.textContent = totalText;
    // The total is repeated only while the bubble is collapsed (mobile); on desktop
    // the footer already shows it and the long pill wrapped the title.
    checkoutSummaryCount.innerHTML = `${units} ${units === 1 ? 'artículo' : 'artículos'}<span class="md:hidden"> · ${escapeHtml(totalText)}</span>`;
    modalTotal.innerText = totalText;
}

// Modal Checkout Logic
async function openCheckoutModal() {
    if(cart.length === 0) return;
    // Re-check the selected table right before checkout (another cashier may
    // have taken it); applyTableOccupancy() clears it and warns if so.
    if(currentTable !== null && currentTable !== 'L') {
        await refreshTableOccupancy(true);
        if(currentTable === null) return;
    }
    closeMobileCart();

    const tableNum = cartTableInput ? cartTableInput.value.trim() : currentTable;
    if(tableNum) {
        modalTableNumDisplay.innerText = tableNum;
        modalTableInfo.classList.remove('hidden');
        sendToTableBtn.classList.remove('hidden');
    } else {
        modalTableInfo.classList.add('hidden');
        sendToTableBtn.classList.add('hidden');
    }
    // Without "Servir a Mesa" the pay button spans the whole two-column footer
    processPaymentBtn.classList.toggle('col-span-2', !tableNum);

    clearClient();
    checkoutSummary?.classList.add('is-collapsed');
    checkoutSummaryToggle?.setAttribute('aria-expanded', 'false');
    checkoutModal.classList.remove('hidden');
    renderCheckoutSummary();
    setTimeout(() => checkoutCedula.focus(), 50);
}

function closeCheckoutModal() {
    checkoutModal.classList.add('hidden');
    clientModal?.classList.add('hidden');
    clearClient();
}
window.closeCheckoutModal = closeCheckoutModal;

document.addEventListener('keydown', (event) => {
    if(event.key !== 'Escape') return;
    if(clientModal && !clientModal.classList.contains('hidden')) {
        closeClientModal();
    } else if(isCheckoutOpen() && !clientSearchState.open) {
        closeCheckoutModal();
    }
});

// Referencias
window.toggleReferenceField = function() {
    const pmEl = document.querySelector('input[name="payment_method"]:checked');
    if(pmEl && (pmEl.value === 'transfer' || pmEl.value === 'pagomovil')) {
        referenceFieldContainer.classList.remove('hidden');
    } else {
        referenceFieldContainer.classList.add('hidden');
        paymentReference.value = '';
    }
}

// Receipt card after a collected sale (assets/receipt.js); optional on this page.
function showSaleReceipt(saleId) {
    if (window.SweetReceipt) window.SweetReceipt.show(saleId, { autoClose: true });
}

// Procesar Venta (Desde Carrito POS)
async function processCheckout(status) {
    if(cart.length === 0) return;
    
    const paymentMethodEl = document.querySelector('input[name="payment_method"]:checked');
    const paymentMethod = status === 'paid' && paymentMethodEl ? paymentMethodEl.value : null;
    
    const refVal = paymentReference.value.trim();
    if(status === 'paid' && (paymentMethod === 'transfer' || paymentMethod === 'pagomovil') && !refVal) {
        showToast('Debes ingresar el número de referencia', 'error');
        paymentReference.focus();
        return;
    }
    
    const originalText = processPaymentBtn.innerHTML;
    processPaymentBtn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Procesando...';
    processPaymentBtn.disabled = true;
    if(sendToTableBtn) sendToTableBtn.disabled = true;
    
    const tableNumStr = currentTable ? String(currentTable) : (cartTableInput ? cartTableInput.value.trim() : null);
    
    const payload = {
        items: cart,
        status: status,
        table_number: tableNumStr === 'Llevar' || tableNumStr === 'L' ? 999 : tableNumStr,
        payment_method: paymentMethod,
        client_id: activeClientId.value || null,
        reference_number: refVal || null
    };
    
    try {
        const response = await fetch('api/sales.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if(response.ok) {
            showToast(status === 'paid' ? '¡Pago completado con éxito!' : '¡Orden enviada a mesa!');
            cart = [];
            currentTable = null;
            if(cartTableInput) cartTableInput.value = '';
            paymentReference.value = '';
            renderQuickTables();
            renderCart();
            fetchProducts();
            closeCheckoutModal();
            fetchActiveTables();
            window.SweetLive?.poke();
            if (status === 'paid' && result.sale_id) showSaleReceipt(result.sale_id);
        } else {
            showToast(result.error || 'Error al procesar orden', 'error');
            // El stock pudo cambiar (otra caja vendio el producto): refrescar disponibilidad
            if (response.status === 409) {
                // Mesa ocupada por otra caja: refrescar ocupacion; si no, el stock cambio
                if (result.table_number) refreshTableOccupancy(true);
                else fetchProducts();
            }
        }
    } catch (error) {
        showToast('Error de red al procesar orden', 'error');
    } finally {
        processPaymentBtn.innerHTML = originalText;
        processPaymentBtn.disabled = false;
        if(sendToTableBtn) sendToTableBtn.disabled = false;
    }
}

/* =========================================================
   BARRA DE MESAS ACTIVAS (POS)
   ========================================================= */
async function fetchActiveTables() {
    try {
        const pendingSales = await fetchPendingSales();
        syncPosPaymentModal(pendingSales);
        if(!activeTablesBar) return;
        
        if(pendingSales.length === 0) {
            activeTablesBar.classList.add('hidden');
            activeTablesBar.innerHTML = '';
            return;
        }

        activeTablesBar.classList.remove('hidden');
        activeTablesBar.innerHTML = '';

        // Título discreto
        const titleBadge = document.createElement('div');
        titleBadge.className = 'flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0 pr-2 border-r border-slate-200/80';
        titleBadge.innerHTML = '<i data-lucide="clock" class="w-3.5 h-3.5 text-pink-500"></i><span>Mesas Activas</span>';
        activeTablesBar.appendChild(titleBadge);

        pendingSales.forEach(sale => {
            let tNumberStr = sale.table_number;
            if(tNumberStr === 999 || tNumberStr === '999') tNumberStr = 'Llevar';
            
            const btn = document.createElement('button');
            btn.className = 'flex items-center gap-2.5 bg-white/95 hover:bg-pink-50/70 border border-pink-200 text-pink-900 px-3.5 py-2 rounded-2xl transition-all shadow-sm shrink-0 group transform hover:-translate-y-1 hover:shadow-md';
            btn.onclick = () => openPosPaymentModal(sale.id, sale.total_amount, tNumberStr);
            btn.innerHTML = `
                <div class="w-8 h-8 rounded-xl bg-pink-100/80 text-pink-600 flex items-center justify-center shadow-xs group-hover:scale-105 transition-transform">
                    <i data-lucide="${tNumberStr === 'Llevar' ? 'shopping-bag' : 'armchair'}" class="w-4 h-4"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-pink-600 leading-tight">${tNumberStr === 'Llevar' ? 'Para Llevar' : 'Mesa ' + escapeHtml(tNumberStr)}</span>
                    <span class="font-black text-xs text-slate-800 leading-tight font-heading">$${parseFloat(sale.total_amount).toFixed(2)}</span>
                </div>
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse ml-1"></span>
            `;
            activeTablesBar.appendChild(btn);
        });
        
        if(window.lucide) window.lucide.createIcons({ root: activeTablesBar });

    } catch(e) {
        console.error('Error fetching active tables', e);
    }
}

// True while this cashier's own payment request is running: the ticket may
// already be gone from the pending list, and that is not someone else's doing.
let posPaymentBusy = false;

// Keeps the open "cobrar mesa" modal in line with what other cashiers did.
function syncPosPaymentModal(pendingSales) {
    if(!posPaymentModal || posPaymentModal.classList.contains('hidden') || posPaymentBusy) return;
    const sale = pendingSales.find(s => String(s.id) === String(posPaySaleId.value));
    if(!sale) {
        closePosPaymentModal();
        showToast('Esta comanda ya fue cobrada o anulada desde otra caja.', 'error');
        return;
    }
    posPayModalTotal.innerText = '$' + parseFloat(sale.total_amount).toFixed(2);
}

// Pos Payment Modal Logic
window.togglePosReferenceField = function() {
    const pmEl = document.querySelector('input[name="pos_payment_method"]:checked');
    if(pmEl && (pmEl.value === 'transfer' || pmEl.value === 'pagomovil')) {
        posReferenceFieldContainer.classList.remove('hidden');
    } else {
        posReferenceFieldContainer.classList.add('hidden');
        posPaymentReference.value = '';
    }
}

function openPosPaymentModal(saleId, total, tableName) {
    if(!posPaymentModal) return;
    posPaySaleId.value = saleId;
    posPayModalTotal.innerText = '$' + parseFloat(total).toFixed(2);
    posPayTableName.innerText = tableName === 'Llevar' ? 'Para Llevar' : 'Mesa ' + tableName;
    
    document.querySelector('input[name="pos_payment_method"][value="cash"]').checked = true;
    togglePosReferenceField();
    posPaymentReference.value = '';
    
    posPaymentModal.classList.remove('hidden');
}

function closePosPaymentModal() {
    if(posPaymentModal) {
        posPaymentModal.classList.add('hidden');
    }
}

async function submitPosTablePayment() {
    const saleId = posPaySaleId.value;
    const pmEl = document.querySelector('input[name="pos_payment_method"]:checked');
    const paymentMethod = pmEl ? pmEl.value : 'cash';
    const reference = posPaymentReference.value.trim();

    if((paymentMethod === 'transfer' || paymentMethod === 'pagomovil') && !reference) {
        showToast('Debes ingresar el número de referencia', 'error');
        posPaymentReference.focus();
        return;
    }
    
    const btn = document.getElementById('pos-pay-confirm-btn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Procesando...';
    btn.disabled = true;
    posPaymentBusy = true;

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
            closePosPaymentModal();
            fetchActiveTables();
            window.SweetLive?.poke();
            showSaleReceipt(saleId);
        } else {
            const data = await res.json();
            showToast(data.error || 'Error al registrar pago', 'error');
        }
    } catch(e) {
        showToast('Error de conexión', 'error');
    } finally {
        posPaymentBusy = false;
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
