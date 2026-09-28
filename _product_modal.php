<!-- _product_modal.php — Modal compartido "Nuevo Producto" (usado en inventario.php y produccion.php) -->
<div id="new-product-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-4 transition-opacity">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-[slideIn_0.2s_ease-out] max-h-[92dvh] flex flex-col border border-slate-100">
        <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                    <i data-lucide="package-plus" class="w-4 h-4 stroke-[2.2]"></i>
                </div>
                <h3 class="font-extrabold text-slate-800 font-heading text-sm sm:text-base">Nuevo Producto del Menú</h3>
            </div>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 hover:bg-slate-200 rounded-full p-1.5 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="new-product-form" class="p-4 sm:p-6 flex flex-col gap-3.5 sm:gap-4 overflow-y-auto">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nombre del Postre o Helado</label>
                <input type="text" id="np-name" required placeholder="Ej. Helado de Pistacho Artesanal" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-semibold">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Categoría</label>
                    <select id="np-cat" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-medium">
                        <option>Helados</option>
                        <option>Postres</option>
                        <option>Varios</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Tono Pastel (Hex)</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="np-color" value="#fbcfe8" class="w-12 h-10 p-1 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                        <span class="text-xs text-slate-400 font-mono">Fondo del card</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Precio de Venta ($)</label>
                <input type="number" id="np-price" step="0.01" required placeholder="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 font-heading text-lg font-bold">
            </div>

            <div class="border-t border-slate-100 pt-4">
                <label class="block text-xs font-extrabold text-slate-600 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                    <i data-lucide="wheat" class="w-3.5 h-3.5 text-pink-500"></i>
                    <span>Receta / Ingredientes (Opcional)</span>
                </label>
                <p class="text-[11px] text-slate-400 mb-2.5">Asigna los materiales necesarios para elaborar cada unidad de este postre.</p>
                <div id="np-recipe-rows" class="flex flex-col gap-2 max-h-40 overflow-y-auto pr-1"></div>
                <button type="button" onclick="pfAddRecipeRow()" class="mt-2 w-full border border-dashed border-pink-200 text-pink-600 hover:bg-pink-50/50 rounded-xl py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="plus" class="w-3.5 h-3.5 stroke-[2.2]"></i> Agregar insumo a la receta
                </button>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Stock Inicial</label>
                <input type="number" id="np-stock" value="0" min="0" step="1" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-300 text-sm font-semibold disabled:bg-slate-100 disabled:text-slate-400">
                <p id="np-stock-hint" class="text-[11px] text-slate-400 mt-1">Sin receta asignada: puedes ingresar el stock inicial libremente.</p>
            </div>

            <div id="np-error" class="hidden text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2"></div>

            <button type="submit" class="btn-sweet-accent mt-2 w-full text-white font-extrabold py-3.5 rounded-2xl transition-all flex justify-center items-center gap-2 shadow-md">
                <i data-lucide="save" class="w-4 h-4 stroke-[2.2]"></i> Guardar Producto
            </button>
        </form>
    </div>
</div>
