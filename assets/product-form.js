// assets/product-form.js
// Logica compartida del modal "Nuevo Producto" (inventario.php y produccion.php).
// La creacion de stock inicial reutiliza la validacion de api/production.php
// para que, si el producto tiene receta, nunca se pueda cargar mas stock del
// que el material disponible permite elaborar.

// The same modal is reused to edit an existing product: openProductModal(id).

let pfMaterialsCache = [];
// null = create mode; { id, hadRecipe } = editing that product.
let pfEditState = null;

const PF_RECIPE_EMPTY_TEXT = 'Aún no hay ingredientes. Agrega el primero para convertir este producto en elaborable.';

function pfRecipeEmptyElement() {
    const p = document.createElement('p');
    p.id = 'np-recipe-empty';
    p.className = 'rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-3 py-3 text-center text-[11px] font-semibold text-slate-400';
    p.textContent = PF_RECIPE_EMPTY_TEXT;
    return p;
}

async function pfLoadMaterials() {
    try {
        const res = await fetch('api/materials.php');
        pfMaterialsCache = await res.json();
    } catch (e) {
        pfMaterialsCache = [];
    }
}

function pfMaterialOptionsHtml(selectedId) {
    if (pfMaterialsCache.length === 0) {
        return '<option value="">No hay materiales registrados</option>';
    }
    return pfMaterialsCache.map(m =>
        `<option value="${m.id}" ${String(m.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(m.name)} — ${escapeHtml(m.stock)} ${escapeHtml(m.unit)} disponibles</option>`
    ).join('');
}

function pfUpdateRecipeRowMeta(row) {
    const select = row.querySelector('.np-recipe-material');
    const stockEl = row.querySelector('.np-row-stock');
    const unitEl = row.querySelector('.np-row-unit');
    const material = pfMaterialsCache.find(m => String(m.id) === String(select?.value));
    if (!material) {
        if (stockEl) stockEl.textContent = 'Selecciona un insumo';
        if (unitEl) unitEl.textContent = '';
        return;
    }
    const stock = parseFloat(material.stock) || 0;
    if (stockEl) stockEl.textContent = `Disponible: ${stock.toLocaleString('es-VE', { maximumFractionDigits: 3 })} ${material.unit}`;
    if (unitEl) unitEl.textContent = material.unit;
}

function pfAddRecipeRow(materialId = '', quantity = '') {
    const container = document.getElementById('np-recipe-rows');
    if (!container) return;
    const empty = document.getElementById('np-recipe-empty');
    if (empty) empty.remove();

    const row = document.createElement('div');
    row.className = 'np-recipe-row flex flex-wrap sm:flex-nowrap items-center gap-2 p-2 bg-slate-50/80 rounded-xl border border-slate-100';
    row.innerHTML = `
        <div class="flex-1 min-w-[150px]">
            <select class="np-recipe-material w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-pink-200">
                ${pfMaterialOptionsHtml(materialId)}
            </select>
            <div class="np-row-stock text-[10px] font-semibold text-slate-400 mt-1"></div>
        </div>
        <div class="relative shrink-0">
            <input type="number" class="np-recipe-quantity w-24 px-3 pr-9 py-2 bg-white border border-slate-200 rounded-lg text-sm font-bold focus:outline-none focus:ring-2 focus:ring-pink-200" placeholder="Cant." min="0.01" step="0.01" value="${quantity}">
            <span class="np-row-unit absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400"></span>
        </div>
        <button type="button" class="text-slate-400 hover:text-red-500 p-2 rounded-lg hover:bg-red-50 transition-colors shrink-0" title="Eliminar ingrediente">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
        <span class="np-row-status text-[10px] font-semibold w-full sm:w-24 text-left sm:text-right shrink-0"></span>
    `;
    container.appendChild(row);
    if (window.lucide) lucide.createIcons({ root: row });

    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        if (!container.querySelector('.np-recipe-row')) {
            container.appendChild(pfRecipeEmptyElement());
        }
        pfUpdateStockCap();
    });
    row.querySelector('.np-recipe-material').addEventListener('change', () => {
        pfUpdateRecipeRowMeta(row);
        pfUpdateStockCap();
    });
    row.querySelector('.np-recipe-quantity').addEventListener('input', pfUpdateStockCap);
    pfUpdateRecipeRowMeta(row);
    pfUpdateStockCap();
}

// Calcula cuanto se puede producir con el material disponible segun las filas
// cargadas, y refleja disponibilidad por fila. Devuelve null si no hay receta
// (sin limite), o un numero >= 0 si hay receta.
function pfUpdateStockCap() {
    const rows = [...document.querySelectorAll('#np-recipe-rows .np-recipe-row')];
    const stockInput = document.getElementById('np-stock');
    const hint = document.getElementById('np-stock-hint');
    const summary = document.getElementById('np-recipe-summary');
    const countEl = document.getElementById('np-recipe-count');
    const costEl = document.getElementById('np-recipe-cost');
    const maxEl = document.getElementById('np-recipe-max');
    if (!stockInput || !hint) return null;

    const totals = new Map();
    const seen = new Set();
    rows.forEach(row => {
        const materialId = row.querySelector('.np-recipe-material').value;
        const qtyRequired = parseFloat(row.querySelector('.np-recipe-quantity').value);
        const material = pfMaterialsCache.find(m => String(m.id) === String(materialId));
        const statusEl = row.querySelector('.np-row-status');
        pfUpdateRecipeRowMeta(row);

        if (!materialId || !qtyRequired || qtyRequired <= 0 || !material) {
            if (statusEl) {
                statusEl.innerText = '';
                statusEl.className = 'np-row-status text-[10px] font-semibold w-full sm:w-24 text-left sm:text-right shrink-0';
            }
            return;
        }
        totals.set(String(materialId), (totals.get(String(materialId)) || 0) + qtyRequired);
        if (seen.has(String(materialId))) {
            statusEl.innerText = 'Repetido: se suma';
            statusEl.className = 'np-row-status text-[10px] font-semibold text-amber-600 w-full sm:w-24 text-left sm:text-right shrink-0';
        }
        seen.add(String(materialId));
    });

    let max = null;
    let cost = 0;
    let ingredientCount = 0;
    totals.forEach((qtyRequired, materialId) => {
        const material = pfMaterialsCache.find(m => String(m.id) === String(materialId));
        if (!material || qtyRequired <= 0) return;
        ingredientCount++;
        const available = parseFloat(material.stock) || 0;
        const possible = Math.floor(available / qtyRequired);
        cost += (parseFloat(material.cost_per_unit) || 0) * qtyRequired;
        if (max === null || possible < max) max = possible;
    });

    // Update each row status with the aggregated quantity for that material.
    rows.forEach(row => {
        const materialId = row.querySelector('.np-recipe-material').value;
        const material = pfMaterialsCache.find(m => String(m.id) === String(materialId));
        const qty = totals.get(String(materialId));
        const statusEl = row.querySelector('.np-row-status');
        if (!material || !qty) {
            if (statusEl && !seen.has(String(materialId))) statusEl.innerText = '';
            return;
        }
        const possible = Math.floor((parseFloat(material.stock) || 0) / qty);
        if (statusEl && !statusEl.innerText.startsWith('Repetido')) {
            if (possible <= 0) {
                statusEl.innerText = 'Sin stock suficiente';
                statusEl.className = 'np-row-status text-[10px] font-semibold text-red-500 w-full sm:w-24 text-left sm:text-right shrink-0';
            } else {
                statusEl.innerText = `Alcanza p/ ${possible}`;
                statusEl.className = 'np-row-status text-[10px] font-semibold text-emerald-600 w-full sm:w-24 text-left sm:text-right shrink-0';
            }
        }
    });

    if (summary) summary.classList.toggle('hidden', ingredientCount === 0);
    if (countEl) countEl.innerText = String(ingredientCount);
    if (costEl) costEl.innerText = '$' + cost.toFixed(2);
    if (maxEl) {
        maxEl.innerText = max === null ? '—' : String(Math.max(0, max));
        maxEl.className = 'block text-sm font-black mt-0.5 ' + (max !== null && max <= 0 ? 'text-rose-600' : 'text-emerald-700');
    }

    const editing = pfEditState !== null;
    const label = document.getElementById('np-stock-label');
    if (label) label.innerText = editing ? (max === null ? 'Stock' : 'Elaborar ahora (opcional)') : 'Stock Inicial';
    // On edit, products without recipe never send stock: it changes via the stock-movement action.
    stockInput.classList.toggle('hidden', editing && max === null);

    if (max === null) {
        stockInput.removeAttribute('max');
        if (editing) {
            stockInput.value = 0;
            stockInput.disabled = true;
            hint.innerText = 'Sin receta: el stock de este producto se modifica desde la acción "Movimiento de stock" del inventario.';
        } else {
            stockInput.disabled = false;
            hint.innerText = 'Sin receta asignada: puedes ingresar el stock inicial libremente.';
        }
        hint.className = 'text-xs text-slate-400 mt-1';
    } else if (max <= 0) {
        stockInput.value = 0;
        stockInput.setAttribute('max', 0);
        stockInput.disabled = true;
        hint.innerText = editing
            ? 'No hay material suficiente para elaborar unidades de este producto ahora.'
            : 'No hay material suficiente para elaborar este producto todavía — se va a guardar con 0 en stock.';
        hint.className = 'text-xs text-red-600 font-medium mt-1';
    } else {
        stockInput.disabled = false;
        stockInput.setAttribute('max', max);
        if (parseInt(stockInput.value) > max) stockInput.value = max;
        hint.innerText = `Con el material disponible puedes elaborar hasta ${max} unidades ahora mismo.`;
        hint.className = 'text-xs text-emerald-600 font-medium mt-1';
    }

    return max;
}

// Switches title, icon, submit label and edit-only fields between create and edit.
function pfApplyModeUi() {
    const editing = pfEditState !== null;
    const title = document.getElementById('np-title');
    const submitLabel = document.getElementById('np-submit-label');
    if (title) title.innerText = editing ? 'Editar producto' : 'Nuevo Producto del Menú';
    if (submitLabel) submitLabel.innerText = editing ? 'Guardar cambios' : 'Guardar Producto';
    // lucide replaces the <i> with an <svg> that keeps the id: swap it for a fresh <i>.
    const icon = document.getElementById('np-title-icon');
    if (icon) {
        const fresh = document.createElement('i');
        fresh.id = 'np-title-icon';
        fresh.setAttribute('data-lucide', editing ? 'pencil' : 'package-plus');
        fresh.className = 'w-4 h-4 stroke-[2.2]';
        icon.replaceWith(fresh);
    }
    document.getElementById('np-active-wrap')?.classList.toggle('hidden', !editing);
}

async function pfFetchProduct(id) {
    const res = await fetch('api/products.php?id=' + encodeURIComponent(id));
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'No se pudo cargar el producto.');
    return data;
}

// openProductModal()   -> create a new product
// openProductModal(id) -> edit product `id` (name, category, price, color, min stock, active, recipe)
async function openProductModal(productId) {
    const editId = productId !== undefined && productId !== null && productId !== '' ? Number(productId) : null;
    let product = null;
    try {
        [, product] = await Promise.all([
            pfLoadMaterials(),
            editId !== null ? pfFetchProduct(editId) : Promise.resolve(null)
        ]);
    } catch (err) {
        alert(err.message || 'No se pudo cargar el producto.');
        return;
    }

    // Always start from a clean create-mode form so edit state never leaks.
    pfEditState = null;
    document.getElementById('new-product-form').reset();
    const rowsContainer = document.getElementById('np-recipe-rows');
    rowsContainer.innerHTML = '';
    rowsContainer.appendChild(pfRecipeEmptyElement());
    document.getElementById('np-recipe-summary')?.classList.add('hidden');
    document.getElementById('np-error').classList.add('hidden');
    const stockInput = document.getElementById('np-stock');
    stockInput.value = 0;
    stockInput.disabled = false;
    stockInput.classList.remove('hidden');
    document.getElementById('np-min-stock').value = 0;
    document.getElementById('np-active').checked = true;
    document.querySelectorAll('#np-cat option[data-legacy]').forEach(o => o.remove());

    if (product) {
        const recipe = Array.isArray(product.recipe) ? product.recipe : [];
        pfEditState = { id: Number(product.id), hadRecipe: recipe.length > 0 };
        document.getElementById('np-name').value = product.name || '';
        const catSelect = document.getElementById('np-cat');
        const category = product.category || '';
        if (category && ![...catSelect.options].some(o => o.value === category)) {
            // Keep a legacy category selectable instead of silently changing it.
            const opt = document.createElement('option');
            opt.value = category;
            opt.textContent = category;
            opt.dataset.legacy = '1';
            catSelect.appendChild(opt);
        }
        if (category) catSelect.value = category;
        document.getElementById('np-price').value = product.price ?? '';
        document.getElementById('np-color').value = /^#[0-9a-fA-F]{6}$/.test(product.image_color || '') ? product.image_color : '#fbcfe8';
        document.getElementById('np-min-stock').value = Math.max(0, parseInt(product.min_stock, 10) || 0);
        document.getElementById('np-active').checked = Number(product.is_active ?? 1) === 1;
        recipe.forEach(item => pfAddRecipeRow(item.material_id, item.quantity_required));
    }

    pfApplyModeUi();
    pfUpdateStockCap();
    document.getElementById('new-product-modal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeProductModal() {
    document.getElementById('new-product-modal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('new-product-form');
    if (!form) return;

    const stockInput = document.getElementById('np-stock');
    stockInput.addEventListener('input', () => {
        const max = pfUpdateStockCap();
        if (max !== null && parseInt(stockInput.value) > max) stockInput.value = max;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = 'Guardando...';
        btn.disabled = true;

        const errorBox = document.getElementById('np-error');
        errorBox.classList.add('hidden');

        const recipeTotals = new Map();
        [...document.querySelectorAll('#np-recipe-rows .np-recipe-row')].forEach(row => {
            const materialId = parseInt(row.querySelector('.np-recipe-material').value);
            const quantity = parseFloat(row.querySelector('.np-recipe-quantity').value);
            if (materialId && quantity > 0) {
                recipeTotals.set(materialId, (recipeTotals.get(materialId) || 0) + quantity);
            }
        });
        const rows = [...recipeTotals].map(([material_id, quantity_required]) => ({ material_id, quantity_required }));

        const editing        = pfEditState !== null;
        const hasRecipe      = rows.length > 0;
        const stockEl        = document.getElementById('np-stock');
        const requestedStock = stockEl.disabled ? 0 : (parseInt(stockEl.value) || 0);
        const minStock       = Math.max(0, parseInt(document.getElementById('np-min-stock').value, 10) || 0);

        if (editing && pfEditState.hadRecipe && !hasRecipe
            && !confirm('Este producto quedará sin receta: se eliminará la receta actual. ¿Deseas continuar?')) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            return;
        }

        // save_full creates/updates the product, its recipe and the first stock
        // entry in a single transaction: if any step fails nothing is persisted.
        const payload = {
            action: 'save_full',
            name: document.getElementById('np-name').value,
            category: document.getElementById('np-cat').value,
            price: document.getElementById('np-price').value,
            image_color: document.getElementById('np-color').value,
            min_stock: minStock
        };
        if (editing) {
            // Edit: always send the full recipe ([] clears it); stock only grows via production.
            payload.id = pfEditState.id;
            payload.is_active = document.getElementById('np-active').checked ? 1 : 0;
            payload.recipe = rows;
            payload.produce_quantity = hasRecipe ? requestedStock : 0;
        } else {
            payload.recipe = hasRecipe ? rows : null;
            payload.initial_stock = hasRecipe ? 0 : requestedStock;
            payload.produce_quantity = hasRecipe ? requestedStock : 0;
        }

        try {
            const res = await fetch('api/products.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const result = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(result.error || (editing ? 'Error al actualizar el producto' : 'Error al crear el producto'));

            closeProductModal();
            document.dispatchEvent(new CustomEvent(editing ? 'product:updated' : 'product:created', { detail: { id: result.id ?? null } }));
        } catch (err) {
            errorBox.innerText = err.message || 'Error al guardar el producto.';
            errorBox.classList.remove('hidden');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
});
