<?php
require 'auth.php';
requireRole(['admin', 'gerente']);
$business = require __DIR__ . '/config/business.php';
try { $today = (new DateTimeImmutable('now', new DateTimeZone($business['timezone'] ?? 'America/Caracas')))->format('Y-m-d'); }
catch (Throwable $e) { $today = date('Y-m-d'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sweet POS</title>
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
    </style>
</head>
<body class="h-screen overflow-hidden flex text-slate-800 bg-background">
    <?php require '_sidebar.php'; ?>
    <main class="flex-1 min-w-0 h-full overflow-y-auto">
        <header class="px-5 sm:px-8 py-4 sm:py-5 bg-white border-b border-slate-200/80 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-pink-100 text-pink-600 flex items-center justify-center border border-pink-200">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold">Reportes</h1>
                    <p class="text-xs sm:text-sm text-slate-500">Caja, ventas, producción e inventario</p>
                </div>
            </div>
            <div class="hidden sm:block text-right">
                <div class="text-sm font-bold"><?= htmlspecialchars(currentUser(), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500"><?= htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-5 pb-16">
            <!-- Report picker: one button per report, grouped by area -->
            <section class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5" aria-labelledby="picker-title">
                <h2 id="picker-title" class="sr-only">Elige un reporte</h2>
                <div id="report-picker" class="grid grid-cols-1 lg:grid-cols-4 gap-4 lg:gap-5"></div>
            </section>

            <!-- Filters for the selected report -->
            <section class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5" aria-label="Filtros del reporte">
                <div class="flex flex-col xl:flex-row xl:items-end gap-4">
                    <div id="report-filters" class="flex-1 min-w-0 flex flex-col gap-3"></div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button id="generate-report" type="button" class="rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-extrabold px-4 py-2.5 text-sm flex items-center justify-center gap-2 transition-colors disabled:opacity-60" title="Volver a consultar los datos">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i><span>Actualizar</span>
                        </button>
                        <button id="export-excel" type="button" disabled class="rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-40 flex items-center gap-2 transition-colors">
                            <i data-lucide="sheet" class="w-4 h-4"></i>Excel
                        </button>
                        <button id="export-pdf" type="button" disabled class="rounded-xl bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-800 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40 flex items-center gap-2 transition-colors">
                            <i data-lucide="file-down" class="w-4 h-4"></i>PDF
                        </button>
                    </div>
                </div>
                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">El reporte se actualiza al elegirlo o al cambiar un filtro. Los informes usan la zona horaria configurada para el negocio.</p>
            </section>

            <div id="report-status" class="hidden rounded-xl border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
            <section id="report-result" class="space-y-5" aria-live="polite">
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 p-10 text-center text-sm text-slate-500">
                    Elige un reporte para consultar los datos.
                </div>
            </section>
        </div>
    </main>

    <script>
    (() => {
        const today = <?= json_encode($today) ?>;
        const currency = <?= json_encode((string)($business['currency'] ?? '$'), JSON_UNESCAPED_UNICODE) ?>;
        const picker = document.getElementById('report-picker');
        const filters = document.getElementById('report-filters');
        const result = document.getElementById('report-result');
        const statusBox = document.getElementById('report-status');
        const generateButton = document.getElementById('generate-report');
        const excelButton = document.getElementById('export-excel');
        const pdfButton = document.getElementById('export-pdf');
        let lastReport = null;
        let lastReportData = null;
        let requestSeq = 0;

        // filter: range (period chips), day, month, coverage or none. top adds the "Mostrar" chips.
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
        const TOP_OPTIONS = [10, 20, 50, 100];
        const COVERAGE_OPTIONS = [3, 7, 15, 30];

        // Filter state is shared between reports so switching keeps the chosen period.
        const state = {
            report: 'cierre_diario',
            period: 'month', from: today.slice(0, 8) + '01', to: today,
            day: 'today', date: today,
            month: 'current', monthValue: today.slice(0, 7),
            limit: 20, targetDays: 7,
        };

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

        function drawPicker() {
            picker.replaceChildren();
            for (const group of GROUPS) {
                const wrap = document.createElement('div');
                wrap.className = 'min-w-0';
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
                    button.setAttribute('aria-pressed', String(report.id === state.report));
                    button.className = 'report-option snap-start shrink-0 w-56 lg:w-full flex items-start gap-3 rounded-xl bg-slate-50 hover:bg-pink-50 px-3 py-2.5 text-left text-slate-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400';
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
                button.className = 'report-chip shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400';
                button.textContent = option.label;
                button.setAttribute('aria-pressed', String(option.id === active));
                button.addEventListener('click', () => onPick(option.id));
                list.append(button);
            }
            row.append(title, list);
            filters.append(row);
            return row;
        }

        function inputField(label, id, type, value, onChange) {
            const wrap = document.createElement('label');
            wrap.className = 'text-xs font-bold text-slate-700 flex flex-col gap-1';
            wrap.htmlFor = id;
            wrap.append(document.createTextNode(label));
            const input = document.createElement('input');
            input.id = id;
            input.type = type;
            input.value = value;
            input.max = type === 'month' ? today.slice(0, 7) : today;
            input.required = true;
            input.className = 'w-full sm:w-44 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100';
            input.addEventListener('change', () => { if (input.value) onChange(input.value); });
            wrap.append(input);
            return wrap;
        }

        function customRow(fields) {
            const row = document.createElement('div');
            row.className = 'flex flex-wrap gap-3 sm:pl-[7.75rem]';
            row.append(...fields);
            filters.append(row);
        }

        function rangeLabel() {
            const [from, to] = periodRange(state.period);
            const format = (day) => day.split('-').reverse().join('/');
            return from === to ? format(from) : format(from) + ' al ' + format(to);
        }

        function drawFilters() {
            filters.replaceChildren();
            const report = currentReport();
            if (report.filter === 'range') {
                chipGroup('Período', PERIODS, state.period, (id) => { state.period = id; drawFilters(); loadReport(); });
                if (state.period === 'custom') {
                    customRow([
                        inputField('Desde', 'filter-from', 'date', state.from, (value) => { state.from = value; loadReport(); }),
                        inputField('Hasta', 'filter-to', 'date', state.to, (value) => { state.to = value; loadReport(); }),
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
            } else if (report.filter === 'day') {
                chipGroup('Fecha', [{ id: 'today', label: 'Hoy' }, { id: 'yesterday', label: 'Ayer' }, { id: 'custom', label: 'Otra fecha' }],
                    state.day, (id) => { state.day = id; drawFilters(); loadReport(); });
                if (state.day === 'custom') {
                    customRow([inputField('Fecha', 'filter-date', 'date', state.date, (value) => { state.date = value; loadReport(); })]);
                }
            } else if (report.filter === 'month') {
                chipGroup('Mes', [{ id: 'current', label: 'Este mes' }, { id: 'previous', label: 'Mes anterior' }, { id: 'custom', label: 'Otro mes' }],
                    state.month, (id) => { state.month = id; drawFilters(); loadReport(); });
                if (state.month === 'custom') {
                    customRow([inputField('Mes', 'filter-month', 'month', state.monthValue, (value) => { state.monthValue = value; loadReport(); })]);
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
            if (window.lucide) lucide.createIcons({ root: filters });
        }

        function params() {
            const report = currentReport();
            const query = new URLSearchParams({ type: report.id });
            if (report.filter === 'range') {
                const [from, to] = periodRange(state.period);
                query.set('from', from);
                query.set('to', to);
                if (report.top) query.set('limit', String(state.limit));
            } else if (report.filter === 'day') {
                query.set('date', state.day === 'today' ? today : state.day === 'yesterday' ? addDays(today, -1) : state.date);
            } else if (report.filter === 'month') {
                const month = today.slice(0, 7);
                query.set('month', state.month === 'current' ? month : state.month === 'previous' ? shiftMonth(month, -1) : state.monthValue);
            } else if (report.filter === 'coverage') {
                query.set('target_days', String(state.targetDays));
            }
            return query;
        }

        function selectReport(id) {
            if (!REPORTS.some((report) => report.id === id)) return;
            state.report = id;
            for (const button of picker.querySelectorAll('[data-report]')) {
                button.setAttribute('aria-pressed', String(button.dataset.report === id));
            }
            try { localStorage.setItem('sweetpos.reports.last', id); } catch (e) { /* storage unavailable */ }
            try { history.replaceState(null, '', '#' + id); } catch (e) { /* ignore */ }
            drawFilters();
            loadReport();
        }

        function money(value) {
            const amount = Number(value) || 0;
            return currency + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
        }

        function display(value, format) {
            if (value === null || value === undefined || value === '') return '—';
            const number = Number(value);
            switch (format) {
                case 'money': return money(number);
                case 'integer': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 }).format(number);
                case 'quantity': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 2 }).format(number);
                case 'days': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }).format(number) + ' d';
                case 'percent': return new Intl.NumberFormat('es-VE', { maximumFractionDigits: 2 }).format(number) + '%';
                default: return String(value);
            }
        }

        function showStatus(message, kind) {
            statusBox.textContent = message;
            statusBox.className = 'rounded-xl border px-4 py-3 text-sm ' + (kind === 'error'
                ? 'border-rose-200 bg-rose-50 text-rose-800'
                : 'border-slate-200 bg-slate-50 text-slate-600');
            statusBox.classList.remove('hidden');
        }

        function render(report) {
            result.replaceChildren();
            const heading = document.createElement('div');
            heading.className = 'flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1';
            const titleBlock = document.createElement('div');
            const title = document.createElement('h2');
            title.className = 'text-xl font-extrabold text-slate-900';
            title.textContent = report.title;
            const period = document.createElement('p');
            period.className = 'text-sm text-slate-500';
            period.textContent = report.period_label;
            titleBlock.append(title, period);
            const generated = document.createElement('p');
            generated.className = 'text-xs text-slate-400';
            generated.textContent = 'Generado: ' + report.generated_at;
            heading.append(titleBlock, generated);
            result.append(heading);

            const metricGrid = document.createElement('div');
            metricGrid.className = 'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3';
            for (const metric of report.metrics) {
                const card = document.createElement('article');
                card.className = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm';
                const label = document.createElement('div');
                label.className = 'text-xs font-bold uppercase tracking-wide text-slate-500';
                label.textContent = metric.label;
                const value = document.createElement('div');
                value.className = 'mt-2 text-xl font-extrabold text-slate-900';
                value.textContent = display(metric.value, metric.format);
                card.append(label, value);
                metricGrid.append(card);
            }
            result.append(metricGrid);

            for (const table of report.tables) {
                const section = document.createElement('section');
                section.className = 'rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden';
                const tableTitle = document.createElement('h3');
                tableTitle.className = 'px-4 sm:px-5 py-3 font-extrabold text-slate-800 border-b border-slate-100';
                tableTitle.textContent = table.title;

                const cards = document.createElement('div');
                cards.className = 'mobile-data-list md:hidden';

                const scroll = document.createElement('div');
                scroll.className = 'hidden md:block overflow-x-auto';
                const htmlTable = document.createElement('table');
                htmlTable.className = 'min-w-full text-sm';
                const thead = document.createElement('thead');
                thead.className = 'bg-pink-50 text-pink-900';
                const headRow = document.createElement('tr');
                for (const column of table.columns) {
                    const th = document.createElement('th');
                    th.className = 'px-4 py-3 text-left text-xs font-extrabold whitespace-nowrap';
                    th.textContent = column.label;
                    headRow.append(th);
                }
                thead.append(headRow);
                const tbody = document.createElement('tbody');
                tbody.className = 'divide-y divide-slate-100';
                htmlTable.append(thead, tbody);
                scroll.append(htmlTable);

                const pager = document.createElement('div');
                pager.className = 'responsive-pager hidden';
                const pagerState = { page: 1 };

                const drawTable = () => {
                    const state = SweetPagination.paginate(table.rows, pagerState.page, SweetPagination.pageSize(6, 15));
                    pagerState.page = state.page;
                    tbody.innerHTML = '';
                    cards.innerHTML = '';

                    if (!table.rows.length) {
                        const emptyRow = document.createElement('tr');
                        const emptyCell = document.createElement('td');
                        emptyCell.colSpan = table.columns.length;
                        emptyCell.className = 'px-4 py-8 text-center text-slate-500';
                        emptyCell.textContent = 'Sin registros para este período.';
                        emptyRow.append(emptyCell);
                        tbody.append(emptyRow);
                        cards.innerHTML = '<div class="mobile-data-empty">Sin registros para este período.</div>';
                        SweetPagination.render(pager, { page: 1, pages: 1, total: 0, perPage: state.perPage, onChange: () => {} });
                        return;
                    }

                    for (const row of state.items) {
                        const tr = document.createElement('tr');
                        for (let index = 0; index < table.columns.length; index++) {
                            const td = document.createElement('td');
                            td.className = 'px-4 py-3 text-slate-700 whitespace-nowrap';
                            td.textContent = display(row[index], table.columns[index].format);
                            tr.append(td);
                        }
                        tbody.append(tr);

                        const card = document.createElement('article');
                        card.className = 'mobile-data-card';
                        const firstLabel = String(table.columns[0]?.label || '').trim();
                        const titleIndex = /^(#|n[°º]?\.?|no\.?|ranking)$/i.test(firstLabel) && table.columns.length > 1 ? 1 : 0;
                        const titleValue = display(row[titleIndex], table.columns[titleIndex]?.format);
                        const subtitle = titleIndex === 1
                            ? `<p class="mobile-data-card-sub">${escapeHtml(firstLabel)} ${escapeHtml(display(row[0], table.columns[0].format))}</p>`
                            : '';
                        const otherFields = table.columns.map((column, index) => {
                            if (index === titleIndex || (titleIndex === 1 && index === 0)) return '';
                            return `
                                <div class="mobile-data-field">
                                    <span class="mobile-data-label">${escapeHtml(column.label)}</span>
                                    <span class="mobile-data-value">${escapeHtml(display(row[index], column.format))}</span>
                                </div>`;
                        }).join('');
                        card.innerHTML = `
                            <div class="min-w-0">
                                <h4 class="mobile-data-card-title">${escapeHtml(titleValue)}</h4>
                                ${subtitle}
                            </div>
                            ${otherFields ? `<div class="mobile-data-grid">${otherFields}</div>` : ''}`;
                        cards.append(card);
                    }

                    SweetPagination.render(pager, {
                        page: state.page,
                        pages: state.pages,
                        total: state.total,
                        perPage: state.perPage,
                        label: 'filas',
                        onChange: (next) => {
                            pagerState.page = next;
                            drawTable();
                            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        },
                    });
                    if (window.lucide) lucide.createIcons({ root: section });
                };

                section.append(tableTitle, cards, scroll, pager);
                drawTable();
                result.append(section);
            }

            if (report.notes && report.notes.length) {
                const notes = document.createElement('aside');
                notes.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 space-y-1';
                for (const noteText of report.notes) {
                    const note = document.createElement('p');
                    note.textContent = noteText;
                    notes.append(note);
                }
                result.append(notes);
            }
        }

        async function loadReport() {
            const query = params();
            const seq = ++requestSeq;
            lastReport = null;
            generateButton.disabled = true;
            excelButton.disabled = true;
            pdfButton.disabled = true;
            showStatus('Consultando datos…', 'loading');
            try {
                const response = await fetch('api/reports.php?' + query.toString(), { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (seq !== requestSeq) return; // a newer selection already replaced this request
                if (!response.ok) throw new Error(data.error || 'No se pudo generar el reporte.');
                lastReport = query;
                lastReportData = data;
                render(data);
                excelButton.disabled = false;
                pdfButton.disabled = false;
                statusBox.classList.add('hidden');
            } catch (error) {
                if (seq !== requestSeq) return;
                lastReport = null;
                lastReportData = null;
                result.replaceChildren();
                showStatus(error.message || 'No se pudo consultar el reporte.', 'error');
            } finally {
                if (seq === requestSeq) {
                    generateButton.disabled = false;
                    if (window.lucide) lucide.createIcons();
                }
            }
        }

        function exportReport(format) {
            if (!lastReport) return;
            const query = new URLSearchParams(lastReport);
            query.set('format', format);
            window.location.href = 'api/report_export.php?' + query.toString();
        }

        generateButton.addEventListener('click', loadReport);
        excelButton.addEventListener('click', () => exportReport('excel'));
        pdfButton.addEventListener('click', () => exportReport('pdf'));
        SweetPagination.onBreakpointChange(() => {
            if (lastReportData) render(lastReportData);
        });

        let initial = location.hash.slice(1);
        if (!REPORTS.some((report) => report.id === initial)) {
            try { initial = localStorage.getItem('sweetpos.reports.last') || ''; } catch (e) { initial = ''; }
        }
        if (REPORTS.some((report) => report.id === initial)) state.report = initial;
        drawPicker();
        drawFilters();
        if (window.lucide) lucide.createIcons();
        const activeOption = picker.querySelector('[aria-pressed="true"]');
        if (activeOption && activeOption.parentElement.scrollWidth > activeOption.parentElement.clientWidth) {
            activeOption.parentElement.scrollLeft = activeOption.offsetLeft - activeOption.parentElement.offsetLeft - 8;
        }
        loadReport();
    })();
    </script>
</body>
</html>
