// assets/app.js — Sweet POS Application Logic with Enhanced Pastel Aesthetics

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

// DOM Clientes (Floating Panel)
const checkoutCedula = document.getElementById('checkout-cedula');
const activeClientId = document.getElementById('active-client-id');
const floatingClientPanel = document.getElementById('floating-client-panel');
const fastClientBtn = document.getElementById('fast-client-btn');
const registerFloatingBtn = document.getElementById('register-floating-btn');
const clientFoundCard = document.getElementById('client-found-card');
const clientNameDisplay = document.getElementById('client-name-display');
const clientCedulaDisplay = document.getElementById('client-cedula-display');
const clearClientBtn = document.getElementById('clear-client-btn');

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
        
        // Polling de mesas activas
        if(activeTablesBar) {
            fetchActiveTables();
            activeTablesInterval = setInterval(fetchActiveTables, 10000);
        }
        
        checkoutTriggerBtn?.addEventListener('click', openCheckoutModal);
        processPaymentBtn?.addEventListener('click', () => processCheckout('paid'));
        sendToTableBtn?.addEventListener('click', () => processCheckout('pending'));
        
        // Búsqueda automática de clientes con Debounce
        checkoutCedula?.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();
            if(val === '') {
                resetFloatingPanelState();
                return;
            }
            if(val.toLowerCase() === 'cliente rápido' || val.toLowerCase() === 'cliente rapido') {
                hideFloatingPanel();
                activeClientId.value = '';
                return;
            }
            
            const icon = checkoutCedula.parentElement.querySelector('i');
            if(icon) {
                icon.setAttribute('data-lucide', 'loader-2');
                icon.classList.add('animate-spin');
                if(window.lucide) window.lucide.createIcons({ root: checkoutCedula.parentElement });
            }
            
            searchTimeout = setTimeout(() => searchClient(val), 550);
        });
        
        clearClientBtn?.addEventListener('click', clearClient);
        
        fastClientBtn?.addEventListener('click', () => {
            checkoutCedula.value = 'Cliente Rápido';
            activeClientId.value = '';
            hideFloatingPanel();
        });
        
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
async function fetchProducts() {
    try {
        productsGrid.innerHTML = '<div class="col-span-full flex flex-col items-center justify-center py-16"><div class="loader mb-3"></div><p class="text-xs font-semibold text-slate-400">Cargando delicias...</p></div>';
        const response = await fetch('api/products.php');
        const rawProducts = await response.json();
        products = rawProducts.map(p => ({ 
            ...p, 
            id: parseInt(p.id, 10), 
            price: parseFloat(p.price), 
            stock: parseInt(p.stock, 10) 
        }));
        updateCategoryCounts();
        applyProductFilters();
    } catch (error) {
        showToast('Error cargando productos', 'error');
        productsGrid.innerHTML = '<p class="text-slate-500 col-span-full text-center py-10 font-medium">Error al cargar productos del inventario.</p>';
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
        card.className = `product-card p-3.5 cursor-pointer flex flex-col justify-between group ${isOutOfStock ? 'opacity-40 grayscale cursor-not-allowed' : ''}`;
        
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
                <div class="text-[11px] font-bold text-slate-400 group-hover:text-pink-500 transition-colors flex items-center gap-0.5">
                    <span>Añadir</span>
                    <i data-lucide="plus" class="w-3 h-3"></i>
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
        }
        productsGrid.appendChild(card);
    });

    if(window.lucide) window.lucide.createIcons({ root: productsGrid });
}

// Mesas Rápidas (Macaron Pills)
function renderQuickTables() {
    if(!quickTablesContainer) return;
    quickTablesContainer.innerHTML = '';
    const tables = [1, 2, 3, 4, 5, 6, 7, 8, 'L'];
    
    tables.forEach(t => {
        const btn = document.createElement('button');
        const isTakeaway = t === 'L';
        btn.type = 'button';
        btn.className = 'macaron-table-btn';
        
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
}

function selectQuickTable(t, btnElement) {
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
        const originalProduct = products.find(p => p.id === id);
        
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
}

function closeMobileCart() {
    document.body.classList.remove('cart-drawer-open');
}

function toggleMobileCart() {
    document.body.classList.toggle('cart-drawer-open');
}

window.openMobileCart = openMobileCart;
window.closeMobileCart = closeMobileCart;
window.toggleMobileCart = toggleMobileCart;

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
}

// Lógica de Floating Panel
function showFloatingPanel() {
    floatingClientPanel.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
    floatingClientPanel.classList.add('opacity-100', 'scale-100');
}
function hideFloatingPanel() {
    floatingClientPanel.classList.remove('opacity-100', 'scale-100');
    floatingClientPanel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
}

function resetFloatingPanelState() {
    const icon = checkoutCedula.parentElement.querySelector('i');
    if(icon) {
        icon.setAttribute('data-lucide', 'search');
        icon.classList.remove('animate-spin');
        if(window.lucide) window.lucide.createIcons({ root: checkoutCedula.parentElement });
    }
    
    if(checkoutCedula.value.trim() === '') {
        fastClientBtn.classList.remove('hidden');
        registerFloatingBtn.classList.add('hidden');
        clientFoundCard.classList.add('hidden');
        showFloatingPanel();
    }
    activeClientId.value = '';
}

async function searchClient(cedula) {
    try {
        const res = await fetch(`api/clients.php?cedula=${encodeURIComponent(cedula)}`);
        const client = await res.json();
        
        if(res.ok) {
            fastClientBtn.classList.add('hidden');
            registerFloatingBtn.classList.add('hidden');
            clientFoundCard.classList.remove('hidden');
            
            clientNameDisplay.innerText = client.name;
            clientCedulaDisplay.innerText = `V-${client.cedula}`;
            activeClientId.value = client.id;
        } else {
            fastClientBtn.classList.remove('hidden');
            registerFloatingBtn.classList.remove('hidden');
            clientFoundCard.classList.add('hidden');
            activeClientId.value = '';
        }
        showFloatingPanel();
    } catch(e) {
        showToast('Error al buscar cliente', 'error');
    } finally {
        const icon = checkoutCedula.parentElement.querySelector('i');
        if(icon) {
            icon.setAttribute('data-lucide', 'search');
            icon.classList.remove('animate-spin');
            if(window.lucide) window.lucide.createIcons({ root: checkoutCedula.parentElement });
        }
    }
}

function clearClient() {
    checkoutCedula.value = '';
    resetFloatingPanelState();
    checkoutCedula.focus();
}

async function registerClient(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="loader inline-block border-2 mr-2"></div> Guardando...';
    
    const payload = {
        cedula: document.getElementById('c-cedula').value.trim(),
        name: document.getElementById('c-name').value.trim(),
        phone: document.getElementById('c-phone').value.trim()
    };
    
    try {
        const res = await fetch('api/clients.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        
        if(res.ok) {
            showToast('Cliente registrado con éxito');
            document.getElementById('client-modal').classList.add('hidden');
            e.target.reset();
            if(!checkoutModal.classList.contains('hidden')) {
                checkoutCedula.value = payload.cedula;
                searchClient(payload.cedula);
            }
        } else {
            showToast(result.error || 'Error', 'error');
        }
    } catch(err) {
        showToast('Error de red', 'error');
    } finally {
        btn.innerHTML = originalText;
    }
}

// Modal Checkout Logic
function openCheckoutModal() {
    if(cart.length === 0) return;
    closeMobileCart();
    
    let total = cart.reduce((acc, item) => acc + item.subtotal, 0);
    modalTotal.innerText = '$' + total.toFixed(2);
    
    const tableNum = cartTableInput ? cartTableInput.value.trim() : currentTable;
    if(tableNum) {
        modalTableNumDisplay.innerText = tableNum;
        modalTableInfo.classList.remove('hidden');
        sendToTableBtn.classList.remove('hidden');
    } else {
        modalTableInfo.classList.add('hidden');
        sendToTableBtn.classList.add('hidden');
    }
    
    checkoutModal.classList.remove('hidden');
    
    setTimeout(() => {
        resetFloatingPanelState();
        checkoutCedula.focus();
    }, 100);
}

function closeCheckoutModal() {
    checkoutModal.classList.add('hidden');
    hideFloatingPanel();
    setTimeout(() => {
        checkoutCedula.value = '';
        activeClientId.value = '';
    }, 300);
}

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
        } else {
            showToast(result.error || 'Error al procesar orden', 'error');
            // El stock pudo cambiar (otra caja vendio el producto): refrescar disponibilidad
            if (response.status === 409) fetchProducts();
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
    if(!activeTablesBar) return;
    try {
        const res = await fetch('api/sales.php');
        const pendingSales = await res.json();
        
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
        } else {
            const data = await res.json();
            showToast(data.error || 'Error al registrar pago', 'error');
        }
    } catch(e) {
        showToast('Error de conexión', 'error');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
