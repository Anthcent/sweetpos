// assets/product-form.js
// Logica compartida del modal "Nuevo Producto" (inventario.php y produccion.php).
// La creacion de stock inicial reutiliza la validacion de api/production.php
// para que, si el producto tiene receta, nunca se pueda cargar mas stock del
// que el material disponible permite elaborar.

let pfMaterialsCache = [];

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

function pfAddRecipeRow(materialId = '', quantity = '') {
    const container = document.getElementById('np-recipe-rows');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-1.5 sm:gap-2 flex-wrap sm:flex-nowrap p-1.5 sm:p-0 bg-slate-50/80 sm:bg-transparent rounded-xl border sm:border-0 border-slate-100';
    row.innerHTML = `
        <select class="np-recipe-material min-w-[130px] flex-1 px-3 py-2 bg-white sm:bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-pink-200">
            ${pfMaterialOptionsHtml(materialId)}
        </select>
        <input type="number" class="np-recipe-quantity w-20 sm:w-24 px-2.5 sm:px-3 py-2 bg-white sm:bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-pink-200" placeholder="Cant/uni" min="0.01" step="0.01" value="${quantity}">
        <span class="np-row-status text-[10px] font-semibold shrink-0 w-20 sm:w-24 text-right"></span>
        <button type="button" class="text-slate-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Eliminar fila"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
    `;
    container.appendChild(row);
    if (window.lucide) lucide.createIcons({ root: row });

    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        pfUpdateStockCap();
    });
    row.querySelector('.np-recipe-material').addEventListener('change', pfUpdateStockCap);
    row.querySelector('.np-recipe-quantity').addEventListener('input', pfUpdateStockCap);
    pfUpdateStockCap();
}

// Calcula cuanto se puede producir con el material disponible segun las filas
// cargadas, y refleja disponibilidad por fila. Devuelve null si no hay receta
// (sin limite), o un numero >= 0 si hay receta.
function pfUpdateStockCap() {
    const rows = [...document.querySelectorAll('#np-recipe-rows > div')];
    const stockInput = document.getElementById('np-stock');
    const hint = document.getElementById('np-stock-hint');
    if (!stockInput || !hint) return null;

    let max = null;
    rows.forEach(row => {
        const materialId   = row.querySelector('.np-recipe-material').value;
        const qtyRequired  = parseFloat(row.querySelector('.np-recipe-quantity').value);
        const statusEl     = row.querySelector('.np-row-status');
        const material      = pfMaterialsCache.find(m => String(m.id) === String(materialId));

        if (!materialId || !qtyRequired || qtyRequired <= 0 || !material) {
            if (statusEl) statusEl.innerText = '';
            return;
        }

        const available = parseFloat(material.stock);
        const possible  = Math.floor(available / qtyRequired);

        if (statusEl) {
            if (possible <= 0) {
                statusEl.innerText = 'Sin stock suficiente';
                statusEl.className = 'np-row-status text-[10px] font-semibold text-red-500 shrink-0 w-24 text-right';
            } else {
                statusEl.innerText = `Alcanza p/ ${possible}`;
                statusEl.className = 'np-row-status text-[10px] font-semibold text-emerald-600 shrink-0 w-24 text-right';
            }
        }

        if (max === null || possible < max) max = possible;
    });

    if (max === null) {
        stockInput.disabled = false;
        stockInput.removeAttribute('max');
        hint.innerText = 'Sin receta asignada: podés ingresar el stock inicial libremente.';
        hint.className = 'text-xs text-slate-400 mt-1';
    } else if (max <= 0) {
        stockInput.value = 0;
        stockInput.setAttribute('max', 0);
        stockInput.disabled = true;
        hint.innerText = 'No hay material suficiente para elaborar este producto todavía — se va a guardar con 0 en stock.';
        hint.className = 'text-xs text-red-600 font-medium mt-1';
    } else {
        stockInput.disabled = false;
        stockInput.setAttribute('max', max);
        if (parseInt(stockInput.value) > max) stockInput.value = max;
        hint.innerText = `Con el material disponible podés elaborar hasta ${max} unidades ahora mismo.`;
        hint.className = 'text-xs text-emerald-600 font-medium mt-1';
    }

    return max;
}

async function openProductModal() {
    await pfLoadMaterials();
    document.getElementById('new-product-form').reset();
    document.getElementById('np-recipe-rows').innerHTML = '';
    document.getElementById('np-error').classList.add('hidden');
    document.getElementById('np-stock').value = 0;
    document.getElementById('np-stock').disabled = false;
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

        const rows = [...document.querySelectorAll('#np-recipe-rows > div')]
            .map(row => ({
                material_id: parseInt(row.querySelector('.np-recipe-material').value),
                quantity_required: parseFloat(row.querySelector('.np-recipe-quantity').value)
            }))
            .filter(i => i.material_id && i.quantity_required > 0);

        const hasRecipe      = rows.length > 0;
        const requestedStock = parseInt(document.getElementById('np-stock').value) || 0;

        const payload = {
            name: document.getElementById('np-name').value,
            category: document.getElementById('np-cat').value,
            price: document.getElementById('np-price').value,
            image_color: document.getElementById('np-color').value,
            stock: hasRecipe ? 0 : requestedStock
        };

        try {
            const createRes = await fetch('api/products.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const created = await createRes.json();
            if (!createRes.ok) throw new Error(created.error || 'Error al crear el producto');
            const productId = created.id;

            if (hasRecipe) {
                const recipeRes = await fetch('api/production.php', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ product_id: productId, items: rows })
                });
                if (!recipeRes.ok) {
                    const recipeResult = await recipeRes.json().catch(() => ({}));
                    throw new Error('El producto se creó, pero falló al guardar la receta: ' + (recipeResult.error || ''));
                }

                if (requestedStock > 0) {
                    const prodRes = await fetch('api/production.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ product_id: productId, quantity: requestedStock })
                    });
                    const prodResult = await prodRes.json();
                    if (!prodRes.ok) throw new Error('El producto y la receta se guardaron, pero no se pudo cargar el stock inicial: ' + (prodResult.error || ''));
                }
            }

            closeProductModal();
            document.dispatchEvent(new CustomEvent('product:created'));
        } catch (err) {
            errorBox.innerText = err.message || 'Error al guardar el producto.';
            errorBox.classList.remove('hidden');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
});
