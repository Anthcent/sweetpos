<?php require 'auth.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Sweet POS</title>
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

    <!-- Backdrop para Drawer de Carrito en Móvil y Tablet -->
    <div id="cart-backdrop" onclick="closeMobileCart()"></div>

    <!-- Área Principal -->
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden relative">
        <!-- Ambient Soft Pastels (Glows) -->
        <div class="absolute top-0 right-0 w-[28rem] h-[28rem] bg-pink-200/30 rounded-full mix-blend-multiply filter blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 left-10 w-[24rem] h-[24rem] bg-purple-200/20 rounded-full mix-blend-multiply filter blur-3xl translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

        <!-- Header Principal POS -->
        <header class="py-2.5 sm:py-3 px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between border-b border-slate-200/60 glass-panel z-10 shrink-0 gap-3">
            
            <!-- Fila superior: Logo/Título y Controles Móviles (En Desktop: Columna Izquierda) -->
            <div class="flex items-center justify-between gap-3 w-full sm:w-auto shrink-0">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-pink-100/90 text-pink-600 flex items-center justify-center shadow-xs border border-pink-200/70 shrink-0">
                        <i data-lucide="sparkles" class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2]"></i>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-900 font-heading leading-tight">Punto de Venta</h1>
                        <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Helados, postres & cafetería artesanal</p>
                    </div>
                </div>

                <!-- Botón de Carrito para Móviles/Tablets (<1024px) -->
                <div class="flex items-center gap-2 lg:hidden">
                    <button type="button" onclick="openMobileCart()" class="flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-pink-50 hover:bg-pink-100 border border-pink-200 text-pink-700 font-extrabold text-xs shadow-xs transition-all active:scale-95">
                        <i data-lucide="shopping-bag" class="w-4 h-4 text-pink-600 stroke-[2.2]"></i>
                        <span class="hidden sm:inline font-heading">Orden</span>
                        <span id="mobile-header-cart-count" class="min-w-[18px] h-[18px] px-1 rounded-full bg-pink-600 text-white text-[10px] font-black flex items-center justify-center">0</span>
                    </button>
                    <!-- Avatar mini en móvil -->
                    <div class="w-8 h-8 rounded-full bg-pink-100 text-pink-700 font-extrabold text-xs flex items-center justify-center font-heading border border-pink-200 shrink-0">
                        <?= strtoupper(substr(currentUser(), 0, 1)) ?>
                    </div>
                </div>
            </div>

            <!-- Buscador Principal Prominente -->
            <div class="w-full sm:flex-1 sm:max-w-md lg:max-w-lg xl:max-w-xl">
                <div class="search-bar-sweet w-full">
                    <div class="search-icon-badge">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <input 
                        type="text" 
                        id="pos-search-input" 
                        placeholder="Buscar sabor, helado, torta, café..." 
                        autocomplete="off">
                    <span id="pos-search-count" class="hidden text-[10px] sm:text-[11px] font-extrabold text-pink-700 bg-pink-50 px-2 sm:px-2.5 py-0.5 rounded-full border border-pink-200 shrink-0 font-heading"></span>
                    <button 
                        type="button" 
                        id="pos-search-clear" 
                        onclick="clearPosSearch()" 
                        class="hidden search-clear-btn" 
                        title="Limpiar búsqueda">
                        <i data-lucide="x" class="w-3.5 h-3.5 stroke-[2.4]"></i>
                    </button>
                    <span class="hidden xl:inline-flex text-[10px] font-bold text-slate-400 bg-pink-50/90 text-pink-600 px-2 py-0.5 rounded-lg border border-pink-200/80 font-mono shrink-0 select-none">
                        ESC
                    </span>
                </div>
            </div>

            <!-- Perfil Usuario Desktop -->
            <div class="hidden lg:flex items-center gap-2 pl-3 border-l border-slate-200/80 shrink-0">
                <div class="w-9 h-9 rounded-full bg-pink-100 text-pink-700 font-extrabold text-xs flex items-center justify-center font-heading border border-pink-200 shadow-xs">
                    <?= strtoupper(substr(currentUser(), 0, 1)) ?>
                </div>
                <div class="flex flex-col items-start leading-none">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars(currentUser()) ?></span>
                    <span class="text-[10px] px-1.5 py-0.5 mt-0.5 rounded-full font-semibold <?= getRoleBadgeClass() ?>"><?= getRoleLabel() ?></span>
                </div>
            </div>
        </header>

        <!-- BARRA DEDICADA DE FILTROS DE BÚSQUEDA Y CATEGORÍAS (100% VISIBLE Y DESTACADA) -->
        <div class="px-4 sm:px-6 lg:px-8 pt-3.5 pb-1 shrink-0 z-10">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 w-full">
                    <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider hidden md:inline-flex items-center gap-1.5 mr-1 shrink-0 font-heading">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-pink-500"></i>
                        <span>Filtrar:</span>
                    </span>

                    <button type="button" data-cat="all" class="category-filter-card active-filter shrink-0">
                        <div class="cat-icon-badge">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        </div>
                        <div class="cat-info">
                            <span class="cat-label">Todos</span>
                            <span class="cat-count" id="count-all">...</span>
                        </div>
                    </button>

                    <button type="button" data-cat="Helados" class="category-filter-card shrink-0">
                        <div class="cat-icon-badge">
                            <i data-lucide="ice-cream-cone" class="w-4 h-4"></i>
                        </div>
                        <div class="cat-info">
                            <span class="cat-label">Helados</span>
                            <span class="cat-count" id="count-helados">...</span>
                        </div>
                    </button>

                    <button type="button" data-cat="Postres" class="category-filter-card shrink-0">
                        <div class="cat-icon-badge">
                            <i data-lucide="cake-slice" class="w-4 h-4"></i>
                        </div>
                        <div class="cat-info">
                            <span class="cat-label">Postres</span>
                            <span class="cat-count" id="count-postres">...</span>
                        </div>
                    </button>

                    <button type="button" data-cat="Varios" class="category-filter-card shrink-0">
                        <div class="cat-icon-badge">
                            <i data-lucide="coffee" class="w-4 h-4"></i>
                        </div>
                        <div class="cat-info">
                            <span class="cat-label">Café & Más</span>
                            <span class="cat-count" id="count-varios">...</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Grilla de Productos -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 z-10 relative">
            <div id="products-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-5 pb-24"></div>
        </div>

        <!-- Botón Flotante de Carrito para Móviles / Tablets (<1024px) -->
        <div id="mobile-cart-floating-bar" class="lg:hidden fixed bottom-20 md:bottom-6 right-4 left-4 md:left-auto md:w-80 z-35 transition-all duration-300 transform translate-y-24 opacity-0 pointer-events-none">
            <button type="button" onclick="openMobileCart()" class="btn-sweet-accent w-full py-3 px-4 rounded-2xl shadow-xl flex items-center justify-between text-white font-heading font-extrabold text-sm cart-pill-pulse">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center">
                        <i data-lucide="shopping-bag" class="w-4 h-4 stroke-[2.2]"></i>
                    </span>
                    <div class="text-left leading-tight">
                        <span id="floating-cart-count" class="block text-xs font-black">0 postres</span>
                        <span class="text-[10px] text-pink-100 font-normal">Toca para revisar orden</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span id="floating-cart-total" class="text-base font-black font-heading">$0.00</span>
                    <i data-lucide="arrow-right" class="w-4 h-4 stroke-[2.2]"></i>
                </div>
            </button>
        </div>

        <!-- Barra Inferior de Mesas Activas (Flotante) -->
        <div id="active-tables-bar" class="absolute bottom-0 left-0 w-full bg-white/95 backdrop-blur-md border-t border-slate-200/80 px-4 sm:px-6 py-2.5 sm:py-3 z-20 flex items-center gap-3 overflow-x-auto shadow-[0_-8px_25px_rgba(91,58,77,0.06)] hidden">
            <!-- JS Inyectará las mesas -->
        </div>
    </main>

    <!-- Panel Lateral de Carrito / Comanda (Drawer en Tablet/Móvil) -->
    <aside id="pos-cart-aside" class="w-96 bg-white border-l border-slate-200/80 flex flex-col h-full shadow-2xl relative z-20 shrink-0">
        <!-- Header del Carrito -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col gap-3 bg-slate-50/50 shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2 font-heading">
                    <span class="w-7 h-7 rounded-lg bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="shopping-bag" class="w-4 h-4 stroke-[2.2]"></i>
                    </span>
                    <span>Orden Actual</span>
                    <span id="cart-count-badge" class="ml-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-pink-100 text-pink-700">0</span>
                </h2>
                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="cart=[]; renderCart();" class="text-xs font-semibold text-slate-400 hover:text-rose-600 flex items-center gap-1 transition-colors px-2 py-1 rounded-lg hover:bg-rose-50" title="Vaciar orden">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Vaciar</span>
                    </button>
                    <!-- Botón Cerrar Drawer para Tablet y Móvil -->
                    <button type="button" onclick="closeMobileCart()" class="lg:hidden text-slate-400 hover:text-slate-700 bg-slate-200/60 hover:bg-slate-200 rounded-full p-1.5 transition-colors" title="Cerrar orden">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Selector de Mesas Rápidas (Macarons) -->
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 mb-1.5 uppercase tracking-widest flex items-center gap-1">
                    <i data-lucide="armchair" class="w-3 h-3 text-pink-500"></i> Ubicación / Mesa
                </p>
                <div class="flex flex-wrap gap-1.5 sm:gap-2" id="quick-tables">
                    <!-- JS inyectará botones 1-8 y L -->
                </div>
                <input type="hidden" id="cart-table-number" value="">
            </div>
        </div>

        <!-- Lista de Items en Orden -->
        <div id="cart-items" class="flex-1 overflow-y-auto p-3.5 sm:p-4 flex flex-col gap-2.5"></div>

        <!-- Footer / Total y Botón de Pago -->
        <div class="p-4 sm:p-5 bg-gradient-to-b from-white to-slate-50/80 border-t border-slate-200/80 shadow-[0_-8px_20px_-4px_rgba(91,58,77,0.04)] shrink-0">
            <div class="flex justify-between items-baseline mb-3.5">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total a Pagar</span>
                    <span class="text-[11px] text-slate-400 font-medium">Impuestos incluidos</span>
                </div>
                <span id="cart-total" class="text-2xl sm:text-3xl font-black text-pink-600 font-heading">$0.00</span>
            </div>
            
            <button id="checkout-trigger-btn" disabled class="btn-sweet-accent w-full disabled:opacity-40 disabled:pointer-events-none disabled:shadow-none text-white font-extrabold text-base py-3.5 rounded-2xl transition-all flex items-center justify-center gap-2 group">
                <span>Proceder al Pago</span>
                <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform stroke-[2.2]"></i>
            </button>
        </div>
    </aside>

    <!-- Modal de Checkout & Floating Panel Wrapper -->
    <div id="checkout-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
        
        <div class="flex flex-col md:flex-row items-center md:items-start gap-3 sm:gap-4 w-full max-w-lg md:max-w-3xl max-h-[92dvh] overflow-y-auto no-scrollbar">
            <!-- Modal Principal de Cobro -->
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md md:w-[480px] overflow-hidden flex flex-col max-h-[90vh] border border-slate-100 shrink-0">
                <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                            <i data-lucide="credit-card" class="w-4 h-4 stroke-[2.2]"></i>
                        </div>
                        <h3 class="font-extrabold text-lg text-slate-900 font-heading">Cobrar Pedido</h3>
                    </div>
                    <button type="button" onclick="closeCheckoutModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 hover:bg-slate-200 rounded-full p-1.5 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                
                <div class="p-4 sm:p-6 flex flex-col gap-3.5 sm:gap-4 overflow-y-auto">
                    <!-- Buscador de Cliente Automático -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cliente (Cédula o Identificación)</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            <input type="text" id="checkout-cedula" placeholder="Buscar cédula automáticamente..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm tracking-wider font-semibold text-slate-800">
                        </div>
                        <input type="hidden" id="active-client-id" value="">
                    </div>

                    <!-- Info Mesa -->
                    <div id="modal-table-info" class="hidden bg-pink-50/70 border border-pink-200/80 rounded-2xl p-3 sm:p-3.5 flex items-center gap-3 text-pink-900">
                        <div class="w-9 h-9 rounded-xl bg-white text-pink-600 flex items-center justify-center shadow-xs shrink-0"><i data-lucide="armchair" class="w-5 h-5"></i></div>
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-pink-600">Destino del Pedido</p>
                            <p class="font-black text-sm">Servir en <span id="modal-table-num-display">--</span></p>
                        </div>
                    </div>

                    <!-- Total destacado -->
                    <div class="flex justify-between items-center bg-gradient-to-r from-pink-50/70 via-purple-50/40 to-slate-50 p-3.5 sm:p-4 rounded-2xl border border-pink-100">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total de la Venta</span>
                            <span class="text-[11px] text-pink-600 font-semibold">Listo para facturar</span>
                        </div>
                        <span id="modal-total" class="text-2xl sm:text-3xl font-black text-pink-600 font-heading">$0.00</span>
                    </div>

                    <!-- Método de Pago (Cards elegantes con íconos minimalistas) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Método de Pago</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="cash" class="peer sr-only" checked onchange="toggleReferenceField()">
                                <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                    <i data-lucide="banknote" class="w-5 h-5"></i>
                                    <span class="font-extrabold text-[10px] uppercase">Efectivo</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="card" class="peer sr-only" onchange="toggleReferenceField()">
                                <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                                    <span class="font-extrabold text-[10px] uppercase">Tarjeta</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="transfer" class="peer sr-only" onchange="toggleReferenceField()">
                                <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                    <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                                    <span class="font-extrabold text-[10px] uppercase">Transf.</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="pagomovil" class="peer sr-only" onchange="toggleReferenceField()">
                                <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                                    <span class="font-extrabold text-[10px] uppercase">Pago Móv.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Campo Referencia -->
                    <div id="reference-field-container" class="hidden">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nº de Referencia (Últimos dígitos)</label>
                        <input type="text" id="payment-reference" placeholder="Ej. 4589" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-mono text-base tracking-widest text-slate-800 font-bold">
                    </div>
                </div>

                <!-- Botones Accion Modal -->
                <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 grid grid-cols-2 gap-3 shrink-0">
                    <button type="button" id="send-to-table-btn" class="w-full bg-white hover:bg-slate-100 text-slate-700 font-bold py-3 rounded-xl border border-slate-200 transition-all flex items-center justify-center gap-2 text-sm shadow-xs">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
                        <span>Servir a Mesa</span>
                    </button>
                    <button type="button" id="process-payment-btn" class="btn-sweet-accent w-full text-white font-extrabold py-3 rounded-xl transition-all flex items-center justify-center gap-2 text-sm">
                        <i data-lucide="check-circle-2" class="w-4 h-4 stroke-[2.2]"></i>
                        <span>Confirmar Pago</span>
                    </button>
                </div>
            </div>

            <!-- Panel Flotante Animado (Estados del Cliente) -->
            <div id="floating-client-panel" class="w-56 flex flex-col gap-3 transition-all duration-300 translate-x-4 opacity-0 scale-95 pointer-events-none mt-20">
                
                <!-- Botón Cliente Rápido -->
                <button type="button" id="fast-client-btn" class="w-full bg-white shadow-xl hover:bg-slate-50 text-slate-700 py-4 px-4 rounded-3xl font-extrabold flex flex-col items-center justify-center gap-2 border border-slate-100 transition-all transform hover:scale-105 active:scale-95 group font-heading">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center group-hover:bg-amber-100 transition-colors">
                        <i data-lucide="zap" class="w-6 h-6 stroke-[2.2]"></i>
                    </div>
                    <span class="text-xs">Cliente Rápido</span>
                </button>

                <!-- Botón Registrar Cliente -->
                <button type="button" id="register-floating-btn" onclick="document.getElementById('client-modal').classList.remove('hidden')" class="hidden w-full bg-pink-500 shadow-xl hover:bg-pink-600 text-white py-4 px-4 rounded-3xl font-extrabold flex-col items-center justify-center gap-2 transition-all transform hover:scale-105 active:scale-95 font-heading">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center">
                        <i data-lucide="user-plus" class="w-6 h-6"></i>
                    </div>
                    <span class="text-xs">Registrar Cliente</span>
                </button>

                <!-- Info Cliente Encontrado -->
                <div id="client-found-card" class="hidden w-full bg-gradient-to-br from-emerald-500 to-teal-600 shadow-xl text-white p-5 rounded-3xl flex flex-col gap-2 relative overflow-hidden">
                    <div class="absolute -right-3 -top-3 opacity-15"><i data-lucide="check-circle" class="w-20 h-20"></i></div>
                    <div class="relative z-10 flex flex-col">
                        <span class="text-emerald-100 text-[10px] font-extrabold uppercase tracking-widest mb-1">Cliente Registrado</span>
                        <span id="client-name-display" class="font-extrabold text-base leading-tight font-heading">Juan Perez</span>
                        <span id="client-cedula-display" class="text-xs font-mono text-emerald-100 mt-0.5">V-12345678</span>
                    </div>
                    <button type="button" id="clear-client-btn" class="relative z-10 mt-2 bg-black/20 hover:bg-black/30 text-white text-[11px] font-bold py-1.5 px-3 rounded-xl transition-colors">
                        Cambiar
                    </button>
                </div>

            </div>

        </div>
    </div>

    <!-- Modal Registrar Cliente -->
    <div id="client-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-[60] flex items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 font-heading">Nuevo Cliente</h3>
                </div>
                <button type="button" onclick="document.getElementById('client-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="client-form" class="p-6 flex flex-col gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Cédula / Documento</label>
                    <input type="text" id="c-cedula" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-mono text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre Completo</label>
                    <input type="text" id="c-name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Teléfono (Opcional)</label>
                    <input type="text" id="c-phone" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-medium">
                </div>
                <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-bold py-3 rounded-xl transition-all flex justify-center items-center gap-2 shadow-md">
                    <i data-lucide="save" class="w-4 h-4"></i> Guardar Cliente
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Pago de Mesa desde POS -->
    <div id="pos-payment-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-[70] flex items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-extrabold text-base text-slate-900 font-heading">Cobrar <span id="pos-pay-table-name" class="text-pink-600"></span></h3>
                </div>
                <button type="button" onclick="closePosPaymentModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 flex flex-col gap-4">
                <input type="hidden" id="pos-pay-sale-id">
                <div class="flex justify-between items-center bg-gradient-to-r from-pink-50 to-purple-50 p-4 rounded-2xl border border-pink-100">
                    <span class="text-pink-700 font-extrabold uppercase tracking-wider text-xs">Total Pendiente</span>
                    <span id="pos-pay-modal-total" class="text-3xl font-black text-pink-600 font-heading">$0.00</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Método de Pago</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="pos_payment_method" value="cash" class="peer sr-only" checked onchange="togglePosReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="banknote" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Efectivo</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="pos_payment_method" value="card" class="peer sr-only" onchange="togglePosReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Tarjeta</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="pos_payment_method" value="transfer" class="peer sr-only" onchange="togglePosReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Transf.</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="pos_payment_method" value="pagomovil" class="peer sr-only" onchange="togglePosReferenceField()">
                            <div class="flex flex-col items-center gap-1 p-2 sm:p-2.5 rounded-2xl border-2 border-slate-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 text-slate-500 peer-checked:text-pink-700 transition-all text-center hover:border-pink-200 hover:bg-slate-50">
                                <i data-lucide="smartphone" class="w-5 h-5"></i>
                                <span class="font-bold text-[10px] uppercase">Pago Móv.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="pos-reference-field-container" class="hidden">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nº de Referencia</label>
                    <input type="text" id="pos-payment-reference" placeholder="Ej. 4589" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-mono text-base tracking-widest text-slate-800 font-bold">
                </div>
            </div>
            
            <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 shrink-0">
                <button type="button" onclick="submitPosTablePayment()" id="pos-pay-confirm-btn" class="btn-sweet-accent w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex items-center justify-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5 stroke-[2.2]"></i>
                    <span>Confirmar Pago de Mesa</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Contenedor Toasts -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2"></div>
    
    <script src="assets/app.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
