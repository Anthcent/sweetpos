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
                    <p class="text-xs sm:text-sm text-slate-500">Cierres de caja, ventas e inventario</p>
                </div>
            </div>
            <div class="hidden sm:block text-right">
                <div class="text-sm font-bold"><?= htmlspecialchars(currentUser(), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500"><?= htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-5 pb-16">
            <section class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_minmax(220px,2fr)_auto] gap-4 items-end">
                    <label class="text-sm font-bold text-slate-700" for="report-type">Tipo de reporte
                        <select id="report-type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 text-sm focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100">
                            <option value="cierre_diario">Cierre de caja diario</option>
                            <option value="cierre_mensual">Cierre de caja mensual</option>
                            <option value="mas_vendidos">Productos más vendidos</option>
                            <option value="reponer">Mercancía e insumos por reponer</option>
                        </select>
                    </label>
                    <div id="report-filters" class="grid grid-cols-2 gap-3"></div>
                    <button id="generate-report" type="button" class="rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-extrabold px-5 py-3 flex items-center justify-center gap-2 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i><span>Generar</span>
                    </button>
                </div>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs text-slate-500">Los informes usan la zona horaria configurada para el negocio.</p>
                    <div class="flex gap-2">
                        <button id="export-excel" type="button" disabled class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-40 flex items-center gap-2">
                            <i data-lucide="sheet" class="w-4 h-4"></i>Excel
                        </button>
                        <button id="export-pdf" type="button" disabled class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-800 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40 flex items-center gap-2">
                            <i data-lucide="file-down" class="w-4 h-4"></i>PDF
                        </button>
                    </div>
                </div>
            </section>

            <div id="report-status" class="hidden rounded-xl border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
            <section id="report-result" class="space-y-5" aria-live="polite">
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 p-10 text-center text-sm text-slate-500">
                    Selecciona un reporte y presiona <strong>Generar</strong> para consultar los datos.
                </div>
            </section>
        </div>
    </main>

    <script>
    (() => {
        const today = <?= json_encode($today) ?>;
        const currency = <?= json_encode((string)($business['currency'] ?? '$'), JSON_UNESCAPED_UNICODE) ?>;
        const typeSelect = document.getElementById('report-type');
        const filters = document.getElementById('report-filters');
        const result = document.getElementById('report-result');
        const statusBox = document.getElementById('report-status');
        const excelButton = document.getElementById('export-excel');
        const pdfButton = document.getElementById('export-pdf');
        let lastReport = null;

        function field(label, id, type, value, min, max) {
            const wrap = document.createElement('label');
            wrap.className = 'text-sm font-bold text-slate-700';
            wrap.htmlFor = id;
            wrap.append(document.createTextNode(label));
            const input = document.createElement('input');
            input.id = id;
            input.type = type;
            input.value = value;
            if (min !== undefined) input.min = min;
            if (max !== undefined) input.max = max;
            input.required = true;
            input.className = 'mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 text-sm focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100';
            wrap.append(input);
            filters.append(wrap);
        }

        function drawFilters() {
            filters.replaceChildren();
            switch (typeSelect.value) {
                case 'cierre_diario': field('Fecha', 'filter-date', 'date', today); break;
                case 'cierre_mensual': field('Mes', 'filter-month', 'month', today.slice(0, 7)); break;
                case 'mas_vendidos':
                    field('Desde', 'filter-from', 'date', today.slice(0, 8) + '01');
                    field('Hasta', 'filter-to', 'date', today);
                    field('Productos a mostrar', 'filter-limit', 'number', '20', 5, 100);
                    break;
                case 'reponer': field('Días de cobertura objetivo', 'filter-target-days', 'number', '7', 1, 60); break;
            }
            if (window.lucide) lucide.createIcons();
        }

        function params() {
            const query = new URLSearchParams({ type: typeSelect.value });
            for (const input of filters.querySelectorAll('input')) {
                if (input.value) query.set(input.id.replace('filter-', '').replace(/-/g, '_'), input.value);
            }
            return query;
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
                const scroll = document.createElement('div');
                scroll.className = 'overflow-x-auto';
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
                if (!table.rows.length) {
                    const emptyRow = document.createElement('tr');
                    const emptyCell = document.createElement('td');
                    emptyCell.colSpan = table.columns.length;
                    emptyCell.className = 'px-4 py-8 text-center text-slate-500';
                    emptyCell.textContent = 'Sin registros para este período.';
                    emptyRow.append(emptyCell);
                    tbody.append(emptyRow);
                }
                for (const row of table.rows) {
                    const tr = document.createElement('tr');
                    for (let index = 0; index < table.columns.length; index++) {
                        const td = document.createElement('td');
                        td.className = 'px-4 py-3 text-slate-700 whitespace-nowrap';
                        td.textContent = display(row[index], table.columns[index].format);
                        tr.append(td);
                    }
                    tbody.append(tr);
                }
                htmlTable.append(thead, tbody);
                scroll.append(htmlTable);
                section.append(tableTitle, scroll);
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
            const button = document.getElementById('generate-report');
            button.disabled = true;
            excelButton.disabled = true;
            pdfButton.disabled = true;
            showStatus('Consultando datos…', 'loading');
            try {
                const response = await fetch('api/reports.php?' + query.toString(), { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'No se pudo generar el reporte.');
                lastReport = query;
                render(data);
                excelButton.disabled = false;
                pdfButton.disabled = false;
                statusBox.classList.add('hidden');
            } catch (error) {
                lastReport = null;
                result.replaceChildren();
                showStatus(error.message || 'No se pudo consultar el reporte.', 'error');
            } finally {
                button.disabled = false;
                if (window.lucide) lucide.createIcons();
            }
        }

        function exportReport(format) {
            if (!lastReport) return;
            const query = new URLSearchParams(lastReport);
            query.set('format', format);
            window.location.href = 'api/report_export.php?' + query.toString();
        }

        typeSelect.addEventListener('change', () => {
            lastReport = null;
            excelButton.disabled = true;
            pdfButton.disabled = true;
            statusBox.classList.add('hidden');
            drawFilters();
        });
        filters.addEventListener('input', () => {
            lastReport = null;
            excelButton.disabled = true;
            pdfButton.disabled = true;
        });
        document.getElementById('generate-report').addEventListener('click', loadReport);
        excelButton.addEventListener('click', () => exportReport('excel'));
        pdfButton.addEventListener('click', () => exportReport('pdf'));
        drawFilters();
        if (window.lucide) lucide.createIcons();
    })();
    </script>
</body>
</html>
