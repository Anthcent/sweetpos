<?php
// Shared quick-restock modal. Included from _sidebar.php on every page.
if (!canAccess('materiales')) return;
?>
<div id="restock-modal" class="hidden fixed inset-0 z-[90] flex items-center justify-center p-2 sm:p-4" aria-hidden="true" inert>
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-md" onclick="closeRestockModal()"></div>

    <section class="relative bg-white w-full max-w-4xl max-h-[94dvh] rounded-3xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden"
             role="dialog" aria-modal="true" aria-labelledby="restock-title">
        <header class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 bg-slate-50/70 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 shrink-0">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0">
                    <i data-lucide="package-plus" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <h2 id="restock-title" class="text-lg sm:text-xl font-extrabold text-slate-900 font-heading leading-tight">Reposición rápida</h2>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium mt-0.5">Detecta faltantes, compra insumos y elabora sin salir de la pantalla.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
                <select id="restock-target-days" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-pink-200" title="Cobertura objetivo">
                    <option value="1">1 día</option>
                    <option value="3">3 días</option>
                    <option value="7" selected>7 días</option>
                    <option value="14">14 días</option>
                    <option value="30">30 días</option>
                </select>
                <button type="button" id="restock-refresh" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-pink-600 hover:border-pink-200 flex items-center justify-center transition-colors" title="Actualizar">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
                <button type="button" onclick="closeRestockModal()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition-colors" title="Cerrar" aria-label="Cerrar reposición rápida">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </header>

        <div class="px-4 sm:px-6 pt-3 pb-0 shrink-0">
            <div id="restock-summary" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
            <div class="mt-3 flex items-center gap-1.5 overflow-x-auto no-scrollbar border-b border-slate-100">
                <button type="button" class="restock-tab active" data-restock-tab="missing">
                    <i data-lucide="triangle-alert" class="w-3.5 h-3.5"></i> Faltantes
                </button>
                <button type="button" class="restock-tab" data-restock-tab="buy">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Comprar
                </button>
                <button type="button" class="restock-tab" data-restock-tab="produce">
                    <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar
                </button>
            </div>
        </div>

        <div id="restock-status" class="hidden mx-4 sm:mx-6 mt-3 rounded-xl border px-3.5 py-2.5 text-xs font-semibold" role="status" aria-live="polite"></div>

        <div id="restock-content" class="flex-1 overflow-y-auto p-4 sm:p-6">
            <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                <div class="loader mb-3"></div>
                <p class="text-xs font-semibold">Calculando faltantes…</p>
            </div>
        </div>

        <footer class="px-4 sm:px-6 py-3 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
            <p class="text-[10px] sm:text-[11px] text-slate-400 font-medium">Las compras y elaboraciones quedan registradas en Movimientos.</p>
            <div class="flex items-center gap-2">
                <button type="button" id="restock-download" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:text-pink-600 hover:border-pink-200 flex items-center gap-1.5 transition-colors">
                    <i data-lucide="file-down" class="w-4 h-4"></i> Lista PDF
                </button>
                <button type="button" onclick="closeRestockModal()" class="rounded-xl bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 text-xs font-bold transition-colors">Cerrar</button>
            </div>
        </footer>
    </section>
</div>
<script src="assets/restock.js"></script>
