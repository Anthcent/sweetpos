<?php
require 'auth.php';
requireRole(['admin', 'gerente']);
require_once __DIR__ . '/api/reports_lib.php';
$business = require __DIR__ . '/config/business.php';
try { $today = (new DateTimeImmutable('now', new DateTimeZone($business['timezone'] ?? 'America/Caracas')))->format('Y-m-d'); }
catch (Throwable $e) { $today = date('Y-m-d'); }
// Values for the report builder selects (sellers, payment methods, statuses).
try { $filterOptions = reportFilterOptions($pdo); }
catch (Throwable $e) {
    error_log('SweetPOS report options failed: ' . $e->getMessage());
    $filterOptions = ['sellers' => [], 'payment_methods' => [], 'statuses' => []];
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - YILMANU POS</title>
    <script src="assets/tailwindcss.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/lucide.min.js"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#f5b8d0', background: '#faf7f4' } } } };</script>
    <style>
        .report-scroll { scrollbar-width: thin; }
        .report-option[aria-pressed="true"] { background: #fce7f3; color: #831843; box-shadow: inset 0 0 0 1.5px #f9a8d4; }
        .report-option[aria-pressed="true"] .report-option-icon { background: #ec4899; color: #fff; }
        .report-option[aria-pressed="true"] .report-option-desc { color: #9d174d; }
        .report-chip[aria-pressed="true"] { background: #fff; color: #be185d; box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }
        .report-step { width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: #fce7f3; color: #be185d; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; flex-shrink: 0; }
        .report-sort-button { display: inline-flex; align-items: center; gap: .25rem; min-height: 32px; border-radius: .5rem; font-weight: 800; color: inherit; }
        .report-sort-button:hover { color: #9d174d; }
        .report-sort-button:focus-visible { outline: 2px solid #f472b6; outline-offset: 2px; }
        .report-sort-button .report-sort-icon { opacity: .45; }
        th[aria-sort="ascending"] .report-sort-icon, th[aria-sort="descending"] .report-sort-icon { opacity: 1; }
        .report-result-busy { opacity: .55; pointer-events: none; transition: opacity 150ms ease; }
        .report-skeleton { background: linear-gradient(90deg, #f1f5f9 0%, #f8fafc 50%, #f1f5f9 100%); background-size: 200% 100%; animation: report-shimmer 1.2s ease-in-out infinite; border-radius: 1rem; }
        @keyframes report-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
        @media (prefers-reduced-motion: reduce) { .report-skeleton { animation: none; } }
        /* Phones: the filter rows fold into a panel opened from the "Filtros" button. */
        @media (max-width: 767px) {
            .report-filters-body[data-open="false"] { display: none; }
        }
    </style>
</head>
<body class="h-screen overflow-hidden flex text-slate-800 bg-background">
    <?php require '_sidebar.php'; ?>
    <main id="report-main" class="flex-1 min-w-0 h-full overflow-y-auto overflow-x-hidden">
        <header class="px-4 sm:px-8 py-4 sm:py-5 bg-white border-b border-slate-200/80 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-2xl bg-pink-100 text-pink-600 flex items-center justify-center border border-pink-200 shrink-0">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-extrabold">Reportes</h1>
                    <p class="text-xs sm:text-sm text-slate-500">Elige, filtra, revisa la vista previa y exporta</p>
                </div>
            </div>
            <div class="hidden sm:block text-right">
                <div class="text-sm font-bold"><?= htmlspecialchars(currentUser(), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500"><?= htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-3 py-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-5 pb-28 md:pb-16">
            <!-- Step 1: report picker with a catalog search -->
            <section class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5" aria-labelledby="picker-title">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <h2 id="picker-title" class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                        <span class="report-step" aria-hidden="true">1</span>Elige qué reportar
                    </h2>
                    <div class="relative w-full sm:w-72">
                        <label for="catalog-search" class="sr-only">Buscar un reporte</label>
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input id="catalog-search" type="text" autocomplete="off" enterkeyhint="go" maxlength="60"
                            placeholder="Buscar reporte (ventas, mermas, caja…)"
                            class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 min-h-[44px] text-sm font-medium focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100">
                    </div>
                </div>
                <div id="report-picker" class="grid grid-cols-1 lg:grid-cols-4 gap-4 lg:gap-5"></div>
                <p id="catalog-empty" class="hidden rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                    Ningún reporte coincide con la búsqueda.
                </p>
            </section>

            <!-- Step 2: search and filters for the selected report -->
            <section class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 space-y-3" aria-labelledby="filters-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="filters-title" class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                        <span class="report-step" aria-hidden="true">2</span>Busca y filtra
                    </h2>
                    <button id="reset-filters" type="button" class="min-h-[40px] px-3 rounded-xl text-xs font-bold text-slate-500 hover:text-pink-700 hover:bg-pink-50 flex items-center gap-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>Limpiar filtros
                    </button>
                </div>
                <div class="search-bar-sweet w-full">
                    <div class="search-icon-badge" aria-hidden="true">
                        <i data-lucide="search" class="w-4 h-4 stroke-[2.4]"></i>
                    </div>
                    <label for="report-search" class="sr-only">Buscar en el reporte</label>
                    <input id="report-search" type="text" autocomplete="off" inputmode="search" enterkeyhint="search" maxlength="80"
                        placeholder="Buscar en el reporte…" aria-describedby="search-hint">
                    <button id="report-search-clear" type="button" class="hidden search-clear-btn" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                        <i data-lucide="x" class="w-3.5 h-3.5 stroke-[2.4]"></i>
                    </button>
                </div>
                <p id="search-hint" class="text-xs text-slate-500"></p>

                <button id="filters-toggle" type="button" aria-expanded="false" aria-controls="filters-body"
                    class="md:hidden w-full min-h-[44px] flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4 text-pink-600 shrink-0"></i>
                    <span>Filtros</span>
                    <span id="filters-summary" class="flex-1 min-w-0 truncate text-right text-xs font-semibold text-slate-500"></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 shrink-0 transition-transform" id="filters-toggle-icon"></i>
                </button>
                <div id="filters-body" class="report-filters-body" data-open="false">
                    <div id="report-filters" class="flex flex-col gap-3 pt-1"></div>
                </div>
            </section>

            <!-- Step 3: preview, then export with the same filters -->
            <section id="report-preview" class="space-y-4" aria-labelledby="preview-heading">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="preview-heading" class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span class="report-step" aria-hidden="true">3</span>Vista previa
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">Los archivos se generan con los mismos filtros que ves aquí.</p>
                    </div>
                    <div class="grid grid-cols-[auto_1fr_1fr] sm:flex sm:flex-wrap items-center gap-2 shrink-0">
                        <button id="generate-report" type="button" title="Volver a consultar los datos" aria-label="Actualizar vista previa"
                            class="min-h-[44px] rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50 flex items-center justify-center gap-2 transition-colors disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i><span class="hidden sm:inline">Actualizar</span>
                        </button>
                        <button id="export-excel" type="button" disabled
                            class="min-h-[44px] rounded-xl bg-emerald-50 px-4 text-sm font-bold text-emerald-800 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-40 flex items-center justify-center gap-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            <i data-lucide="sheet" class="w-4 h-4 shrink-0"></i><span class="sm:hidden">Excel</span><span class="hidden sm:inline">Exportar Excel</span>
                        </button>
                        <button id="export-pdf" type="button" disabled
                            class="min-h-[44px] rounded-xl bg-pink-500 px-4 text-sm font-extrabold text-white hover:bg-pink-600 disabled:cursor-not-allowed disabled:opacity-40 flex items-center justify-center gap-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400">
                            <i data-lucide="file-down" class="w-4 h-4 shrink-0"></i><span class="sm:hidden">PDF</span><span class="hidden sm:inline">Generar PDF</span>
                        </button>
                    </div>
                </div>

                <div id="report-status" class="hidden rounded-xl border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
                <div id="report-result" class="space-y-4 sm:space-y-5" aria-live="polite"></div>
            </section>
        </div>
    </main>

    <script>
    (() => {
        const today = <?= json_encode($today) ?>;
        const currency = <?= json_encode((string)($business['currency'] ?? '$'), $jsonFlags) ?>;
        const FILTER_OPTIONS = <?= json_encode($filterOptions, $jsonFlags) ?>;
        const mainScroller = document.getElementById('report-main');
        const picker = document.getElementById('report-picker');
        const catalogSearch = document.getElementById('catalog-search');
        const catalogEmpty = document.getElementById('catalog-empty');
        const filters = document.getElementById('report-filters');
        const filtersBody = document.getElementById('filters-body');
        const filtersToggle = document.getElementById('filters-toggle');
        const filtersToggleIcon = document.getElementById('filters-toggle-icon');
        const filtersSummary = document.getElementById('filters-summary');
        const searchInput = document.getElementById('report-search');
        const searchClear = document.getElementById('report-search-clear');
        const searchHint = document.getElementById('search-hint');
        const resetButton = document.getElementById('reset-filters');
        const result = document.getElementById('report-result');
        const statusBox = document.getElementById('report-status');
        const generateButton = document.getElementById('generate-report');
        const excelButton = document.getElementById('export-excel');
        const pdfButton = document.getElementById('export-pdf');

        const SEARCH_DEBOUNCE_MS = 350;
        const SEARCH_MAX_LENGTH = 80;
        let lastQuery = null;      // API params of the preview on screen; exports reuse them
        let lastReportData = null;
        let activeController = null;
        let searchTimer = null;
        let exporting = false;

        // filter: range (period chips), day, month, coverage or none. top adds the "Mostrar" chips;
        // builder adds status / payment method / seller filters.
        const GROUPS = [
            { name: 'Caja', icon: 'banknote' },
            { name: 'Ventas', icon: 'shopping-bag' },
            { name: 'Producción', icon: 'chef-hat' },
            { name: 'Inventario', icon: 'boxes' },
        ];
        const REPORTS = [
            { id: 'cierre_diario', group: 'Caja', icon: 'calculator', name: 'Cierre diario', desc: 'Cobros del día por método y vendedor', filter: 'day' },
            { id: 'cierre_mensual', group: 'Caja', icon: 'calendar-range', name: 'Cierre mensual', desc: 'Cobros del mes, día por día', filter: 'month' },
            { id: 'metodos_pago', group: 'Caja', icon: 'wallet', name: 'Métodos de pago', desc: 'Efectivo, tarjeta, transferencia y pago móvil', filter: 'range' },
            { id: 'anulaciones', group: 'Caja', icon: 'ban', name: 'Anulaciones', desc: 'Órdenes anuladas, quién y por qué', filter: 'range' },
            { id: 'ventas_detalle', group: 'Ventas', icon: 'receipt-text', name: 'Detalle de ventas', desc: 'Órdenes por cliente, producto, vendedor o método', filter: 'range', builder: true },
            { id: 'ventas_periodo', group: 'Ventas', icon: 'trending-up', name: 'Ventas por período', desc: 'Total, tickets y ticket promedio por día', filter: 'range' },
            { id: 'ventas_categoria', group: 'Ventas', icon: 'layout-grid', name: 'Por categoría', desc: 'Lo que aporta cada categoría', filter: 'range' },
            { id: 'ventas_vendedor', group: 'Ventas', icon: 'users', name: 'Por vendedor', desc: 'Ventas y ticket promedio por usuario', filter: 'range' },
            { id: 'horas_pico', group: 'Ventas', icon: 'clock', name: 'Horas pico', desc: 'Ventas por hora y día de la semana', filter: 'range' },
            { id: 'mas_vendidos', group: 'Ventas', icon: 'trophy', name: 'Más vendidos', desc: 'Ranking de productos por unidades', filter: 'range', top: true },
            { id: 'rentabilidad', group: 'Ventas', icon: 'piggy-bank', name: 'Rentabilidad', desc: 'Precio, costo, margen y ganancia', filter: 'range' },
            { id: 'elaboracion', group: 'Producción', icon: 'chef-hat', name: 'Elaboración', desc: 'Unidades elaboradas y costo de insumos', filter: 'range' },
            { id: 'consumo_insumos', group: 'Producción', icon: 'flask-conical', name: 'Consumo de insumos', desc: 'Cantidad y costo usado por insumo', filter: 'range' },
            { id: 'reponer', group: 'Inventario', icon: 'package-search', name: 'Por reponer', desc: 'Qué comprar o elaborar', filter: 'coverage' },
            { id: 'mermas', group: 'Inventario', icon: 'package-minus', name: 'Mermas', desc: 'Disminuciones agrupadas por motivo', filter: 'range' },
            { id: 'valorizacion', group: 'Inventario', icon: 'warehouse', name: 'Valorización', desc: 'Valor del stock al costo y a la venta', filter: 'none' },
        ];
        const PERIODS = [
            { id: 'today', label: 'Hoy' }, { id: 'yesterday', label: 'Ayer' }, { id: 'last7', label: 'Últimos 7 días' },
            { id: 'month', label: 'Este mes' }, { id: 'prev_month', label: 'Mes anterior' }, { id: 'custom', label: 'Personalizado' },
        ];
        const DAY_OPTIONS = [{ id: 'today', label: 'Hoy' }, { id: 'yesterday', label: 'Ayer' }, { id: 'custom', label: 'Otra fecha' }];
        const MONTH_OPTIONS = [{ id: 'current', label: 'Este mes' }, { id: 'previous', label: 'Mes anterior' }, { id: 'custom', label: 'Otro mes' }];
        const TOP_OPTIONS = [10, 20, 50, 100];
        const COVERAGE_OPTIONS = [3, 7, 15, 30];
        const STATUS_OPTIONS = [...(FILTER_OPTIONS.statuses || []), { id: 'all', label: 'Todas' }];
        const METHOD_OPTIONS = FILTER_OPTIONS.payment_methods || [];
        const SELLERS = FILTER_OPTIONS.sellers || [];
        const SEARCH_HINTS = {
            ventas_detalle: 'Busca por cliente, cédula, producto, categoría, vendedor, referencia u orden #. Los indicadores se recalculan con las órdenes encontradas.',
            default: 'Filtra las filas de las tablas (producto, categoría, vendedor, motivo…). Los indicadores muestran el período completo.',
        };
        const DEFAULT_FILTERS = {
            period: 'month', from: today.slice(0, 8) + '01', to: today,
            day: 'today', date: today,
            month: 'current', monthValue: today.slice(0, 7),
            limit: 20, targetDays: 7,
            q: '', status: 'paid', method: '', seller: '', sort: {},
        };

        // Filter state is shared between reports so switching keeps the chosen period.
        const state = { report: 'ventas_detalle', ...structuredCloneSafe(DEFAULT_FILTERS) };

        function structuredCloneSafe(value) {
            return JSON.parse(JSON.stringify(value));
        }

        function fold(value) {
            return String(value ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        }

        function addDays(day, amount) {
            const [y, m, d] = day.split('-').map(Number);
            const date = new Date(Date.UTC(y, m - 1, d + amount));
            return date.toISOString().slice(0, 10);
        }
        function shiftMonth(month, amount) {
            const [y, m] = month.split('-').map(Number);
            const date = new Date(Date.UTC(y, m - 1 + amount, 1));
            return date.toISOString().slice(0, 7);
        }
        function lastDayOfMonth(month) {
            return addDays(shiftMonth(month, 1) + '-01', -1);
        }
        function isDate(value) {
            return /^\d{4}-\d{2}-\d{2}$/.test(value || '');
        }
        function isMonth(value) {
            return /^\d{4}-\d{2}$/.test(value || '');
        }
        function periodRange(period) {
            switch (period) {
                case 'today': return [today, today];
                case 'yesterday': { const day = addDays(today, -1); return [day, day]; }
                case 'last7': return [addDays(today, -6), today];
                case 'month': return [today.slice(0, 8) + '01', today];
                case 'prev_month': { const month = shiftMonth(today.slice(0, 7), -1); return [month + '-01', lastDayOfMonth(month)]; }
                default: return [state.from, state.to];
            }
        }
        function currentReport() {
            return REPORTS.find((report) => report.id === state.report) || REPORTS[0];
        }

        function icon(name, className) {
            const element = document.createElement('i');
            element.setAttribute('data-lucide', name);
            element.className = className;
            element.setAttribute('aria-hidden', 'true');
            return element;
        }

        function refreshIcons(root) {
            if (window.lucide) lucide.createIcons(root ? { root } : undefined);
        }

        // ------------------------------------------------------------------
        // Step 1: report picker
        // ------------------------------------------------------------------

        function drawPicker() {
            picker.replaceChildren();
            for (const group of GROUPS) {
                const wrap = document.createElement('div');
                wrap.className = 'min-w-0';
                wrap.dataset.group = group.name;
                const headingId = 'group-' + group.name.normalize('NFD').replace(/[^a-z]/gi, '').toLowerCase();
                const heading = document.createElement('h3');
                heading.id = headingId;
                heading.className = 'flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 mb-2';
                heading.append(icon(group.icon, 'w-3.5 h-3.5'), document.createTextNode(group.name));
                const list = document.createElement('div');
                list.setAttribute('role', 'group');
                list.setAttribute('aria-labelledby', headingId);
                list.className = 'report-scroll flex lg:flex-col gap-2 overflow-x-auto lg:overflow-visible snap-x pb-1 lg:pb-0 -mx-1 px-1';
                for (const report of REPORTS.filter((item) => item.group === group.name)) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.report = report.id;
                    button.dataset.search = fold([report.name, report.desc, group.name].join(' '));
                    button.setAttribute('aria-pressed', String(report.id === state.report));
                    button.className = 'report-option snap-start shrink-0 w-56 lg:w-full min-h-[48px] flex items-start gap-3 rounded-xl bg-slate-50 hover:bg-pink-50 px-3 py-2.5 text-left text-slate-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400';
                    const iconBox = document.createElement('span');
                    iconBox.className = 'report-option-icon w-8 h-8 rounded-lg bg-white text-pink-600 flex items-center justify-center shrink-0 transition-colors';
                    iconBox.append(icon(report.icon, 'w-4 h-4'));
                    const text = document.createElement('span');
                    text.className = 'min-w-0';
                    const name = document.createElement('span');
                    name.className = 'block text-sm font-extrabold leading-tight';
                    name.textContent = report.name;
                    const desc = document.createElement('span');
                    desc.className = 'report-option-desc block text-xs text-slate-500 leading-snug mt-0.5';
                    desc.textContent = report.desc;
                    text.append(name, desc);
                    button.append(iconBox, text);
                    button.addEventListener('click', () => selectReport(report.id));
                    list.append(button);
                }
                wrap.append(heading, list);
                picker.append(wrap);
            }
        }

        function filterCatalog() {
            const words = fold(catalogSearch.value).split(/\s+/).filter(Boolean);
            let visible = 0;
            for (const wrap of picker.querySelectorAll('[data-group]')) {
                let groupVisible = 0;
                for (const button of wrap.querySelectorAll('[data-report]')) {
                    const match = words.every((word) => button.dataset.search.includes(word));
                    button.hidden = !match;
                    if (match) groupVisible++;
                }
                wrap.hidden = groupVisible === 0;
                visible += groupVisible;
            }
            catalogEmpty.classList.toggle('hidden', visible > 0);
        }

        function scrollActiveOptionIntoView() {
            const activeOption = picker.querySelector('[aria-pressed="true"]');
            if (activeOption && activeOption.parentElement.scrollWidth > activeOption.parentElement.clientWidth) {
                activeOption.parentElement.scrollLeft = activeOption.offsetLeft - activeOption.parentElement.offsetLeft - 8;
            }
        }

        // ------------------------------------------------------------------
        // Step 2: filters
        // ------------------------------------------------------------------

        // Segmented control built from buttons with aria-pressed.
        function chipGroup(label, options, active, onPick) {
            const row = document.createElement('div');
            row.className = 'flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-3 min-w-0';
            const title = document.createElement('span');
            title.className = 'text-xs font-bold text-slate-700 sm:w-28 shrink-0';
            title.textContent = label;
            const list = document.createElement('div');
            list.setAttribute('role', 'group');
            list.setAttribute('aria-label', label);
            list.className = 'report-scroll flex gap-1 p-1 bg-slate-100 rounded-xl overflow-x-auto max-w-full w-fit';
            for (const option of options) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'report-chip shrink-0 whitespace-nowrap min-h-[40px] px-3 rounded-lg text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400';
                button.textContent = option.label;
                button.setAttribute('aria-pressed', String(option.id === active));
                button.addEventListener('click', () => onPick(option.id));
                list.append(button);
            }
            row.append(title, list);
            filters.append(row);
            return row;
        }

        const FIELD_CLASS = 'w-full sm:w-48 min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100';

        function inputField(label, id, type, value, onChange) {
            const wrap = document.createElement('label');
            wrap.className = 'text-xs font-bold text-slate-700 flex flex-col gap-1 min-w-0';
            wrap.htmlFor = id;
            wrap.append(document.createTextNode(label));
            const input = document.createElement('input');
            input.id = id;
            input.type = type;
            input.value = value;
            input.max = type === 'month' ? today.slice(0, 7) : today;
            input.required = true;
            input.className = FIELD_CLASS;
            input.addEventListener('change', () => { if (input.value) onChange(input.value); });
            wrap.append(input);
            return wrap;
        }

        function selectField(label, id, options, value, onChange) {
            const wrap = document.createElement('label');
            wrap.className = 'text-xs font-bold text-slate-700 flex flex-col gap-1 min-w-0';
            wrap.htmlFor = id;
            wrap.append(document.createTextNode(label));
            const select = document.createElement('select');
            select.id = id;
            select.className = FIELD_CLASS;
            for (const option of options) {
                const element = document.createElement('option');
                element.value = option.id;
                element.textContent = option.label;
                element.selected = option.id === value;
                select.append(element);
            }
            select.addEventListener('change', () => onChange(select.value));
            wrap.append(select);
            return wrap;
        }

        function customRow(fields) {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 min-[420px]:grid-cols-2 sm:flex sm:flex-wrap gap-3 sm:pl-[7.75rem]';
            row.append(...fields);
            filters.append(row);
        }

        function formatDay(day) {
            return day.split('-').reverse().join('/');
        }

        function rangeLabel() {
            const [from, to] = periodRange(state.period);
            return from === to ? formatDay(from) : formatDay(from) + ' al ' + formatDay(to);
        }

        // Short text for the collapsed "Filtros" button on phones.
        function filtersSummaryText() {
            const report = currentReport();
            const parts = [];
            if (report.filter === 'range') parts.push(rangeLabel());
            else if (report.filter === 'day') parts.push(formatDay(dayValue()));
            else if (report.filter === 'month') parts.push(monthValue().split('-').reverse().join('/'));
            else if (report.filter === 'coverage') parts.push(state.targetDays + ' días');
            if (report.builder) {
                const status = STATUS_OPTIONS.find((option) => option.id === state.status);
                if (status) parts.push(status.label);
                if (state.method) parts.push((METHOD_OPTIONS.find((option) => option.id === state.method) || {}).label || state.method);
                if (state.seller) parts.push(state.seller);
            }
            return parts.join(' · ');
        }

        function setFiltersOpen(open) {
            filtersBody.dataset.open = String(open);
            filtersToggle.setAttribute('aria-expanded', String(open));
            filtersToggleIcon.style.transform = open ? 'rotate(180deg)' : '';
        }

        function drawFilters() {
            filters.replaceChildren();
            const report = currentReport();
            if (report.filter === 'range') {
                chipGroup('Período', PERIODS, state.period, (id) => { state.period = id; drawFilters(); loadReport(); });
                if (state.period === 'custom') {
                    customRow([
                        inputField('Desde', 'filter-from', 'date', state.from, (value) => { state.from = value; drawFilters(); loadReport(); }),
                        inputField('Hasta', 'filter-to', 'date', state.to, (value) => { state.to = value; drawFilters(); loadReport(); }),
                    ]);
                } else {
                    const hint = document.createElement('p');
                    hint.className = 'text-xs text-slate-500 sm:pl-[7.75rem]';
                    hint.textContent = rangeLabel();
                    filters.append(hint);
                }
                if (report.top) {
                    chipGroup('Mostrar', TOP_OPTIONS.map((n) => ({ id: n, label: 'Top ' + n })), state.limit,
                        (n) => { state.limit = n; drawFilters(); loadReport(); });
                }
                if (report.builder) {
                    chipGroup('Estado', STATUS_OPTIONS, state.status, (id) => { state.status = id; drawFilters(); loadReport(); });
                    customRow([
                        selectField('Método de pago', 'filter-method', [{ id: '', label: 'Todos los métodos' }, ...METHOD_OPTIONS], state.method,
                            (value) => { state.method = value; updateFiltersSummary(); loadReport(); }),
                        selectField('Vendedor', 'filter-seller', [{ id: '', label: 'Todos los vendedores' }, ...SELLERS.map((name) => ({ id: name, label: name }))], state.seller,
                            (value) => { state.seller = value; updateFiltersSummary(); loadReport(); }),
                    ]);
                }
            } else if (report.filter === 'day') {
                chipGroup('Fecha', DAY_OPTIONS, state.day, (id) => { state.day = id; drawFilters(); loadReport(); });
                if (state.day === 'custom') {
                    customRow([inputField('Fecha', 'filter-date', 'date', state.date, (value) => { state.date = value; drawFilters(); loadReport(); })]);
                }
            } else if (report.filter === 'month') {
                chipGroup('Mes', MONTH_OPTIONS, state.month, (id) => { state.month = id; drawFilters(); loadReport(); });
                if (state.month === 'custom') {
                    customRow([inputField('Mes', 'filter-month', 'month', state.monthValue, (value) => { state.monthValue = value; drawFilters(); loadReport(); })]);
                }
            } else if (report.filter === 'coverage') {
                chipGroup('Cobertura', COVERAGE_OPTIONS.map((n) => ({ id: n, label: n + ' días' })), state.targetDays,
                    (n) => { state.targetDays = n; drawFilters(); loadReport(); });
            } else {
                const note = document.createElement('p');
                note.className = 'text-sm text-slate-600 flex items-center gap-2';
                note.append(icon('info', 'w-4 h-4 text-pink-500 shrink-0'), document.createTextNode('Muestra las existencias actuales; no usa filtro de fechas.'));
                filters.append(note);
            }
            searchInput.placeholder = report.builder ? 'Cliente, cédula, producto, categoría u orden #' : 'Buscar en las filas del reporte…';
            searchHint.textContent = SEARCH_HINTS[report.builder ? 'ventas_detalle' : 'default'];
            updateFiltersSummary();
            refreshIcons(filters);
        }

        function updateFiltersSummary() {
            filtersSummary.textContent = filtersSummaryText();
        }

        function dayValue() {
            return state.day === 'today' ? today : state.day === 'yesterday' ? addDays(today, -1) : state.date;
        }

        function monthValue() {
            const month = today.slice(0, 7);
            return state.month === 'current' ? month : state.month === 'previous' ? shiftMonth(month, -1) : state.monthValue;
        }

        function serializeSort() {
            return Object.entries(state.sort).map(([table, [column, dir]]) => `${table}:${column}:${dir}`).join(',');
        }

        // Search, builder filters and sort, shared by the API and the page URL.
        function appendViewParams(query, report) {
            const term = state.q.trim();
            if (term) query.set('q', term);
            if (report.builder) {
                query.set('status', state.status);
                if (state.method) query.set('payment_method', state.method);
                if (state.seller) query.set('seller', state.seller);
            }
            const sort = serializeSort();
            if (sort) query.set('sort', sort);
            return query;
        }

        // Parameters understood by api/reports.php and api/report_export.php.
        function apiParams() {
            const report = currentReport();
            const query = new URLSearchParams({ type: report.id });
            if (report.filter === 'range') {
                const [from, to] = periodRange(state.period);
                query.set('from', from);
                query.set('to', to);
                if (report.top) query.set('limit', String(state.limit));
            } else if (report.filter === 'day') {
                query.set('date', dayValue());
            } else if (report.filter === 'month') {
                query.set('month', monthValue());
            } else if (report.filter === 'coverage') {
                query.set('target_days', String(state.targetDays));
            }
            return appendViewParams(query, report);
        }

        // Page URL: keeps relative presets ("Este mes") so a saved link stays relative.
        function pageParams() {
            const report = currentReport();
            const query = new URLSearchParams({ type: report.id });
            if (report.filter === 'range') {
                query.set('period', state.period);
                if (state.period === 'custom') { query.set('from', state.from); query.set('to', state.to); }
                if (report.top) query.set('limit', String(state.limit));
            } else if (report.filter === 'day') {
                query.set('date', state.day === 'custom' ? state.date : state.day);
            } else if (report.filter === 'month') {
                query.set('month', state.month === 'custom' ? state.monthValue : state.month);
            } else if (report.filter === 'coverage') {
                query.set('target_days', String(state.targetDays));
            }
            return appendViewParams(query, report);
        }

        function syncUrl() {
            try { history.replaceState(null, '', location.pathname + '?' + pageParams().toString()); } catch (e) { /* ignore */ }
        }

        function readUrl() {
            const params = new URLSearchParams(location.search);
            let initial = params.get('type') || location.hash.slice(1);
            if (!REPORTS.some((report) => report.id === initial)) {
                try { initial = localStorage.getItem('sweetpos.reports.last') || ''; } catch (e) { initial = ''; }
            }
            if (REPORTS.some((report) => report.id === initial)) state.report = initial;

            const period = params.get('period');
            if (PERIODS.some((option) => option.id === period)) state.period = period;
            if (isDate(params.get('from')) && isDate(params.get('to'))) { state.from = params.get('from'); state.to = params.get('to'); }
            const date = params.get('date');
            if (date === 'today' || date === 'yesterday') state.day = date;
            else if (isDate(date)) { state.day = 'custom'; state.date = date; }
            const month = params.get('month');
            if (month === 'current' || month === 'previous') state.month = month;
            else if (isMonth(month)) { state.month = 'custom'; state.monthValue = month; }
            const limit = Number(params.get('limit'));
            if (TOP_OPTIONS.includes(limit)) state.limit = limit;
            const targetDays = Number(params.get('target_days'));
            if (COVERAGE_OPTIONS.includes(targetDays)) state.targetDays = targetDays;
            state.q = (params.get('q') || '').slice(0, SEARCH_MAX_LENGTH);
            const status = params.get('status');
            if (STATUS_OPTIONS.some((option) => option.id === status)) state.status = status;
            const method = params.get('payment_method');
            if (METHOD_OPTIONS.some((option) => option.id === method)) state.method = method;
            const seller = params.get('seller');
            if (seller) state.seller = seller.slice(0, 100);
            for (const part of (params.get('sort') || '').split(',')) {
                const match = /^(\d{1,2}):(\d{1,2}):(asc|desc)$/.exec(part);
                if (match) state.sort[match[1]] = [Number(match[2]), match[3]];
            }
        }

        function selectReport(id) {
            if (!REPORTS.some((report) => report.id === id)) return;
            if (id !== state.report) state.sort = {};
            state.report = id;
            for (const button of picker.querySelectorAll('[data-report]')) {
                button.setAttribute('aria-pressed', String(button.dataset.report === id));
            }
            try { localStorage.setItem('sweetpos.reports.last', id); } catch (e) { /* storage unavailable */ }
            drawFilters();
            loadReport();
        }

        function resetFilters() {
            const report = state.report;
            Object.assign(state, structuredCloneSafe(DEFAULT_FILTERS), { report });
            searchInput.value = '';
            updateSearchClear();
            drawFilters();
            loadReport();
        }

        // ------------------------------------------------------------------
        // Step 3: preview rendering
        // ------------------------------------------------------------------

        const NUMBER_FORMATS = ['money', 'integer', 'quantity', 'days', 'percent'];

        function money(value) {
            const amount = Number(value) || 0;
            return currency + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
        }

        function formatInteger(value) {
            return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 }).format(Number(value) || 0);
        }

        function display(value, format) {
            if (value === null || value === undefined || value === '') return '—';
            const number = Number(value);
            // Labels such as "Total" can sit on numeric columns of the totals row.
            if (NUMBER_FORMATS.includes(format) && !Number.isFinite(number)) return String(value);
            switch (format) {
                case 'money': return money(number);
                case 'integer': return formatInteger(number);
                case 'quantity': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 2 }).format(number);
                case 'days': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }).format(number) + ' d';
                case 'percent': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 2 }).format(number) + '%';
                default: return String(value);
            }
        }

        function hideStatus() {
            statusBox.classList.add('hidden');
            statusBox.replaceChildren();
        }

        function showStatus(message, kind, onRetry) {
            statusBox.replaceChildren();
            statusBox.setAttribute('role', kind === 'error' ? 'alert' : 'status');
            statusBox.className = 'rounded-xl border px-4 py-3 text-sm flex flex-col sm:flex-row sm:items-center gap-3 ' + (kind === 'error'
                ? 'border-rose-200 bg-rose-50 text-rose-800'
                : 'border-slate-200 bg-slate-50 text-slate-600');
            const text = document.createElement('p');
            text.className = 'flex-1 flex items-start gap-2';
            text.append(icon(kind === 'error' ? 'triangle-alert' : 'loader-circle', 'w-4 h-4 mt-0.5 shrink-0' + (kind === 'error' ? '' : ' animate-spin')));
            const span = document.createElement('span');
            span.textContent = message;
            text.append(span);
            statusBox.append(text);
            if (onRetry) {
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'min-h-[40px] rounded-xl bg-white border border-rose-200 px-4 text-sm font-bold text-rose-800 hover:bg-rose-100 transition-colors';
                retry.textContent = 'Reintentar';
                retry.addEventListener('click', onRetry);
                statusBox.append(retry);
            }
            refreshIcons(statusBox);
        }

        function drawSkeleton() {
            result.replaceChildren();
            const wrap = document.createElement('div');
            wrap.className = 'space-y-4';
            wrap.setAttribute('aria-hidden', 'true');
            wrap.innerHTML = `
                <div class="report-skeleton h-16"></div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="report-skeleton h-20"></div><div class="report-skeleton h-20"></div>
                    <div class="report-skeleton h-20"></div><div class="report-skeleton h-20"></div>
                </div>
                <div class="report-skeleton h-64"></div>`;
            result.append(wrap);
        }

        function rowsInfo(table) {
            const shown = table.rows.length;
            const total = Number(table.row_count ?? shown);
            if (table.truncated) {
                return `Vista previa de las primeras ${formatInteger(shown)} de ${formatInteger(total)} filas. La exportación incluye todas.`;
            }
            if (table.unfiltered_count !== undefined) {
                return `${formatInteger(total)} de ${formatInteger(table.unfiltered_count)} filas coinciden con la búsqueda`;
            }
            return `${formatInteger(total)} ${total === 1 ? 'fila' : 'filas'}`;
        }

        function emptyMessage(table) {
            if (state.q.trim() && (table.unfiltered_count === undefined || table.unfiltered_count > 0)) {
                return `Ninguna fila coincide con «${state.q.trim()}».`;
            }
            return 'Sin registros para los filtros seleccionados.';
        }

        // Header click cycles: first direction -> opposite -> report's natural order.
        function toggleSort(tableIndex, columnIndex, format) {
            const current = state.sort[tableIndex];
            const first = NUMBER_FORMATS.includes(format) ? 'desc' : 'asc';
            if (!current || current[0] !== columnIndex) state.sort[tableIndex] = [columnIndex, first];
            else if (current[1] === first) state.sort[tableIndex] = [columnIndex, first === 'asc' ? 'desc' : 'asc'];
            else delete state.sort[tableIndex];
            loadReport({ keepScroll: true });
        }

        function setSort(tableIndex, value) {
            if (!value) delete state.sort[tableIndex];
            else {
                const [column, dir] = value.split(':');
                state.sort[tableIndex] = [Number(column), dir];
            }
            loadReport({ keepScroll: true });
        }

        function renderHeading(report) {
            const heading = document.createElement('div');
            heading.className = 'flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1 px-1';
            const titleBlock = document.createElement('div');
            titleBlock.className = 'min-w-0';
            const title = document.createElement('h3');
            title.className = 'text-lg sm:text-xl font-extrabold text-slate-900';
            title.textContent = report.title;
            const period = document.createElement('p');
            period.className = 'text-sm text-slate-500';
            period.textContent = report.period_label;
            titleBlock.append(title, period);
            if (report.filters_label) {
                const applied = document.createElement('p');
                applied.className = 'mt-1 text-xs font-semibold text-pink-800 break-words';
                applied.textContent = report.filters_label;
                titleBlock.append(applied);
            }
            const generated = document.createElement('p');
            generated.className = 'text-xs text-slate-400 shrink-0';
            generated.textContent = 'Generado: ' + report.generated_at;
            heading.append(titleBlock, generated);
            return heading;
        }

        function renderMetrics(metrics) {
            const metricGrid = document.createElement('div');
            metricGrid.className = 'grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3';
            for (const metric of metrics) {
                const card = document.createElement('article');
                card.className = 'min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-4';
                const label = document.createElement('div');
                label.className = 'text-[11px] sm:text-xs font-bold uppercase tracking-wide text-slate-500 leading-tight';
                label.textContent = metric.label;
                const value = document.createElement('div');
                value.className = 'mt-1.5 sm:mt-2 text-base sm:text-xl font-extrabold text-slate-900 break-words tabular-nums';
                value.textContent = display(metric.value, metric.format);
                card.append(label, value);
                metricGrid.append(card);
            }
            return metricGrid;
        }

        function renderTable(table, tableIndex) {
            const section = document.createElement('section');
            section.className = 'rounded-2xl border border-slate-200 bg-white overflow-hidden';
            const header = document.createElement('div');
            header.className = 'px-4 sm:px-5 py-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1';
            const tableTitle = document.createElement('h4');
            tableTitle.className = 'font-extrabold text-slate-800';
            tableTitle.textContent = table.title;
            const info = document.createElement('p');
            info.className = 'text-xs ' + (table.truncated ? 'font-semibold text-amber-700' : 'text-slate-500');
            info.textContent = rowsInfo(table);
            header.append(tableTitle, info);
            section.append(header);

            const sort = state.sort[tableIndex];

            // Phones: sort select above the cards.
            if (table.rows.length > 1) {
                const sortWrap = document.createElement('div');
                sortWrap.className = 'md:hidden px-4 pt-3';
                const sortOptions = [{ id: '', label: 'Orden del reporte' }];
                table.columns.forEach((column, index) => {
                    const numeric = NUMBER_FORMATS.includes(column.format);
                    sortOptions.push({ id: `${index}:asc`, label: `${column.label} (${numeric ? 'menor a mayor' : 'A-Z'})` });
                    sortOptions.push({ id: `${index}:desc`, label: `${column.label} (${numeric ? 'mayor a menor' : 'Z-A'})` });
                });
                const field = selectField('Ordenar por', `sort-${tableIndex}`, sortOptions, sort ? `${sort[0]}:${sort[1]}` : '', (value) => setSort(tableIndex, value));
                field.querySelector('select').classList.remove('sm:w-48');
                sortWrap.append(field);
                section.append(sortWrap);
            }

            const cards = document.createElement('div');
            cards.className = 'mobile-data-list md:hidden';

            const scroll = document.createElement('div');
            scroll.className = 'hidden md:block overflow-x-auto';
            const htmlTable = document.createElement('table');
            htmlTable.className = 'min-w-full text-sm';
            const thead = document.createElement('thead');
            thead.className = 'bg-pink-50 text-pink-900';
            const headRow = document.createElement('tr');
            table.columns.forEach((column, index) => {
                const th = document.createElement('th');
                th.scope = 'col';
                th.className = 'px-4 py-2 text-xs font-extrabold whitespace-nowrap ' + (column.align === 'R' ? 'text-right' : 'text-left');
                const active = sort && sort[0] === index ? sort[1] : null;
                th.setAttribute('aria-sort', active === 'asc' ? 'ascending' : active === 'desc' ? 'descending' : 'none');
                if (table.rows.length > 1) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'report-sort-button' + (column.align === 'R' ? ' flex-row-reverse' : '');
                    button.title = 'Ordenar por ' + column.label;
                    const label = document.createElement('span');
                    label.textContent = column.label;
                    button.append(label, icon(active === 'asc' ? 'arrow-up' : active === 'desc' ? 'arrow-down' : 'chevrons-up-down', 'report-sort-icon w-3.5 h-3.5 shrink-0'));
                    button.addEventListener('click', () => toggleSort(tableIndex, index, column.format));
                    th.append(button);
                } else {
                    th.textContent = column.label;
                }
                headRow.append(th);
            });
            thead.append(headRow);
            const tbody = document.createElement('tbody');
            tbody.className = 'divide-y divide-slate-100';
            htmlTable.append(thead, tbody);

            if (table.totals && table.rows.length) {
                const tfoot = document.createElement('tfoot');
                tfoot.className = 'bg-pink-50/70 border-t-2 border-pink-100';
                const tr = document.createElement('tr');
                table.columns.forEach((column, index) => {
                    const td = document.createElement('td');
                    td.className = 'px-4 py-3 font-extrabold text-slate-900 whitespace-nowrap tabular-nums ' + (column.align === 'R' ? 'text-right' : 'text-left');
                    const value = table.totals[index];
                    td.textContent = value === null || value === undefined ? '' : display(value, column.format);
                    tr.append(td);
                });
                tfoot.append(tr);
                htmlTable.append(tfoot);
            }
            scroll.append(htmlTable);

            const pager = document.createElement('div');
            pager.className = 'responsive-pager hidden';
            const pagerState = { page: 1 };

            const drawRows = () => {
                const page = SweetPagination.paginate(table.rows, pagerState.page, SweetPagination.pageSize(6, 15));
                pagerState.page = page.page;
                tbody.replaceChildren();
                cards.replaceChildren();

                if (!table.rows.length) {
                    const message = emptyMessage(table);
                    const emptyRow = document.createElement('tr');
                    const emptyCell = document.createElement('td');
                    emptyCell.colSpan = table.columns.length;
                    emptyCell.className = 'px-4 py-8 text-center text-slate-500';
                    emptyCell.textContent = message;
                    emptyRow.append(emptyCell);
                    tbody.append(emptyRow);
                    cards.innerHTML = `<div class="mobile-data-empty">${escapeHtml(message)}</div>`;
                    SweetPagination.render(pager, { page: 1, pages: 1, total: 0, perPage: page.perPage, onChange: () => {} });
                    return;
                }

                for (const row of page.items) {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50/70';
                    table.columns.forEach((column, index) => {
                        const td = document.createElement('td');
                        td.className = 'px-4 py-3 text-slate-700 whitespace-nowrap ' + (column.align === 'R' ? 'text-right tabular-nums' : 'text-left');
                        const text = display(row[index], column.format);
                        td.textContent = text;
                        // Long free-text cells (product lists, reasons) wrap instead of widening the table.
                        if (column.format === 'text' && text.length > 40) td.className = td.className.replace('whitespace-nowrap', 'min-w-[16rem] max-w-md whitespace-normal');
                        tr.append(td);
                    });
                    tbody.append(tr);

                    const card = document.createElement('article');
                    card.className = 'mobile-data-card';
                    const firstLabel = String(table.columns[0]?.label || '').trim();
                    const titleIndex = /^(#|n[°º]?\.?|no\.?|ranking|orden)$/i.test(firstLabel) && table.columns.length > 1 ? 1 : 0;
                    const titleValue = display(row[titleIndex], table.columns[titleIndex]?.format);
                    const subtitle = titleIndex === 1
                        ? `<p class="mobile-data-card-sub">${escapeHtml(firstLabel)} ${escapeHtml(display(row[0], table.columns[0].format))}</p>`
                        : '';
                    const otherFields = table.columns.map((column, index) => {
                        if (index === titleIndex || (titleIndex === 1 && index === 0)) return '';
                        const value = display(row[index], column.format);
                        const wide = column.format === 'text' && value.length > 24 ? ' mobile-data-field-wide' : '';
                        return `
                            <div class="mobile-data-field${wide}">
                                <span class="mobile-data-label">${escapeHtml(column.label)}</span>
                                <span class="mobile-data-value">${escapeHtml(value)}</span>
                            </div>`;
                    }).join('');
                    card.innerHTML = `
                        <div class="min-w-0">
                            <h5 class="mobile-data-card-title">${escapeHtml(titleValue)}</h5>
                            ${subtitle}
                        </div>
                        ${otherFields ? `<div class="mobile-data-grid">${otherFields}</div>` : ''}`;
                    cards.append(card);
                }

                SweetPagination.render(pager, {
                    page: page.page,
                    pages: page.pages,
                    total: page.total,
                    perPage: page.perPage,
                    label: 'filas',
                    onChange: (next) => {
                        pagerState.page = next;
                        drawRows();
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                });
            };

            section.append(cards, scroll, pager);

            // Phones: totals card under the list (outside the pagination).
            if (table.totals && table.rows.length) {
                const fields = table.columns.map((column, index) => {
                    const value = table.totals[index];
                    if (value === null || value === undefined || value === 'Total') return '';
                    return `
                        <div class="mobile-data-field">
                            <span class="mobile-data-label">${escapeHtml(column.label)}</span>
                            <span class="mobile-data-value">${escapeHtml(display(value, column.format))}</span>
                        </div>`;
                }).join('');
                const totals = document.createElement('div');
                totals.className = 'md:hidden border-t-2 border-pink-100 bg-pink-50/70 px-4 py-3';
                totals.innerHTML = `
                    <h5 class="text-xs font-extrabold uppercase tracking-wide text-pink-900">Totales${table.truncated || table.rows.length < (table.row_count ?? 0) ? ' (todas las filas)' : ''}</h5>
                    <div class="mobile-data-grid">${fields}</div>`;
                section.append(totals);
            }

            drawRows();
            return section;
        }

        function render(report) {
            result.replaceChildren();
            const card = document.createElement('div');
            card.className = 'space-y-4 sm:space-y-5';
            card.append(renderHeading(report), renderMetrics(report.metrics || []));
            (report.tables || []).forEach((table, index) => card.append(renderTable(table, index)));

            if (report.notes && report.notes.length) {
                const notes = document.createElement('aside');
                notes.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 space-y-1';
                for (const noteText of report.notes) {
                    const note = document.createElement('p');
                    note.textContent = noteText;
                    notes.append(note);
                }
                card.append(notes);
            }
            result.append(card);
            refreshIcons(result);
        }

        // ------------------------------------------------------------------
        // Data loading and export
        // ------------------------------------------------------------------

        function updateExportButtons() {
            const ready = Boolean(lastQuery) && !activeController && !searchTimer && !exporting;
            excelButton.disabled = !ready;
            pdfButton.disabled = !ready;
        }

        async function loadReport(options = {}) {
            clearTimeout(searchTimer);
            searchTimer = null;
            if (activeController) activeController.abort(); // drop the stale request
            const controller = new AbortController();
            activeController = controller;
            const query = apiParams();
            const scrollTop = mainScroller.scrollTop;
            syncUrl();
            updateFiltersSummary();
            lastQuery = null;
            updateExportButtons();
            generateButton.disabled = true;
            if (lastReportData) {
                result.classList.add('report-result-busy');
                result.setAttribute('aria-busy', 'true');
            } else {
                drawSkeleton();
            }
            showStatus('Consultando datos…', 'loading');
            try {
                const previewQuery = new URLSearchParams(query);
                previewQuery.set('preview', '1');
                const response = await fetch('api/reports.php?' + previewQuery.toString(), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                let data;
                try { data = await response.json(); }
                catch (e) { throw new Error('El servidor respondió con un formato inesperado.'); }
                if (!response.ok) throw new Error(data.error || 'No se pudo generar el reporte.');
                lastQuery = query;
                lastReportData = data;
                render(data);
                hideStatus();
                if (options.keepScroll) mainScroller.scrollTop = scrollTop;
            } catch (error) {
                if (error.name === 'AbortError') return;
                lastQuery = null;
                lastReportData = null;
                result.replaceChildren();
                showStatus(error.message || 'No se pudo consultar el reporte.', 'error', () => loadReport());
            } finally {
                if (activeController === controller) {
                    activeController = null;
                    generateButton.disabled = false;
                    result.classList.remove('report-result-busy');
                    result.removeAttribute('aria-busy');
                    updateExportButtons();
                }
            }
        }

        function downloadName(response, fallback) {
            const header = response.headers.get('Content-Disposition') || '';
            const match = /filename="?([^";]+)"?/i.exec(header);
            return match ? match[1] : fallback;
        }

        // Exports reuse the exact parameters of the preview on screen.
        async function exportReport(format) {
            if (!lastQuery || exporting) return;
            const query = new URLSearchParams(lastQuery);
            query.set('format', format);
            const button = format === 'pdf' ? pdfButton : excelButton;
            exporting = true;
            button.setAttribute('aria-busy', 'true');
            updateExportButtons();
            showStatus(format === 'pdf' ? 'Generando el PDF…' : 'Generando el archivo de Excel…', 'loading');
            try {
                const response = await fetch('api/report_export.php?' + query.toString());
                if (!response.ok) {
                    const message = (await response.text()).trim();
                    let parsed = null;
                    try { parsed = JSON.parse(message); } catch (e) { /* plain text */ }
                    throw new Error((parsed && parsed.error) || message || 'No se pudo generar el archivo.');
                }
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = downloadName(response, 'reporte.' + (format === 'pdf' ? 'pdf' : 'xlsx'));
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
                hideStatus();
            } catch (error) {
                showStatus(error.message || 'No se pudo generar el archivo.', 'error');
            } finally {
                exporting = false;
                button.removeAttribute('aria-busy');
                updateExportButtons();
            }
        }

        function updateSearchClear() {
            searchClear.classList.toggle('hidden', searchInput.value === '');
        }

        function scheduleSearch() {
            const value = searchInput.value.slice(0, SEARCH_MAX_LENGTH);
            updateSearchClear();
            if (value.trim() === state.q.trim()) return;
            state.q = value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { searchTimer = null; loadReport(); }, SEARCH_DEBOUNCE_MS);
            updateExportButtons();
        }

        // ------------------------------------------------------------------
        // Wiring
        // ------------------------------------------------------------------

        catalogSearch.addEventListener('input', filterCatalog);
        catalogSearch.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                const first = picker.querySelector('[data-report]:not([hidden])');
                if (first) { event.preventDefault(); first.click(); first.focus(); }
            } else if (event.key === 'Escape') {
                catalogSearch.value = '';
                filterCatalog();
            }
        });
        searchInput.addEventListener('input', scheduleSearch);
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                state.q = searchInput.value.slice(0, SEARCH_MAX_LENGTH);
                loadReport();
            } else if (event.key === 'Escape' && searchInput.value) {
                searchInput.value = '';
                scheduleSearch();
            }
        });
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            scheduleSearch();
            searchInput.focus();
        });
        filtersToggle.addEventListener('click', () => setFiltersOpen(filtersBody.dataset.open !== 'true'));
        resetButton.addEventListener('click', resetFilters);
        generateButton.addEventListener('click', () => loadReport());
        excelButton.addEventListener('click', () => exportReport('excel'));
        pdfButton.addEventListener('click', () => exportReport('pdf'));
        SweetPagination.onBreakpointChange(() => {
            if (lastReportData) render(lastReportData);
        });

        readUrl();
        searchInput.value = state.q;
        updateSearchClear();
        drawPicker();
        drawFilters();
        refreshIcons();
        scrollActiveOptionIntoView();
        loadReport();
    })();
    </script>
</body>
</html>
