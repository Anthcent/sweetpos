// assets/restock.js — Quick-restock modal shared by POS and backoffice.
// Reuses api/restock.php, api/materials.php, api/products.php and api/production.php.
(function () {
  const state = {
    open: false,
    loading: false,
    tab: 'missing',
    targetDays: 7,
    data: null,
    purchaseRows: [],
    buyNote: '',
    focusProductId: null,
    lastFocus: null,
  };

  const $ = (id) => document.getElementById(id);

  function money(value) {
    return '$' + Number(value || 0).toFixed(2);
  }

  function number(value, digits = 2) {
    return Number(value || 0).toLocaleString('es-VE', { maximumFractionDigits: digits });
  }

  function statusPill(status) {
    const map = {
      agotado: ['Agotado', 'bg-rose-100 text-rose-700 border-rose-200'],
      critico: ['Crítico', 'bg-orange-100 text-orange-700 border-orange-200'],
      bajo: ['Bajo', 'bg-amber-100 text-amber-800 border-amber-200'],
      ok: ['OK', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    };
    const [label, cls] = map[status] || map.ok;
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-bold ${cls}">${label}</span>`;
  }

  function setStatus(message, kind = 'info') {
    const box = $('restock-status');
    if (!box) return;
    const styles = {
      info: 'border-slate-200 bg-slate-50 text-slate-600',
      success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
      error: 'border-rose-200 bg-rose-50 text-rose-800',
    };
    box.className = `mx-4 sm:mx-6 mt-3 rounded-xl border px-3.5 py-2.5 text-xs font-semibold ${styles[kind] || styles.info}`;
    box.textContent = message;
  }

  function clearStatus() {
    const box = $('restock-status');
    if (box) {
      box.classList.add('hidden');
      box.textContent = '';
    }
  }

  function icons(root) {
    if (window.lucide) window.lucide.createIcons(root ? { root } : undefined);
  }

  function openRestockModal(productId = null) {
    const modal = $('restock-modal');
    if (!modal) return;
    state.lastFocus = document.activeElement;
    state.open = true;
    state.focusProductId = productId ? Number(productId) : null;
    state.tab = 'missing';
    try {
      state.targetDays = Number(localStorage.getItem('sweetpos.restock.targetDays')) || 7;
    } catch (error) {
      state.targetDays = 7;
    }
    const select = $('restock-target-days');
    if (select) select.value = String(state.targetDays);
    modal.inert = false;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('restock-open');
    const close = modal.querySelector('[aria-label="Cerrar reposición rápida"]');
    if (close) close.focus({ preventScroll: true });
    loadData();
  }

  function closeRestockModal() {
    const modal = $('restock-modal');
    if (!modal) return;
    state.open = false;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.inert = true;
    document.body.classList.remove('restock-open');
    if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
      state.lastFocus.focus({ preventScroll: true });
    }
  }

  async function loadData() {
    if (state.loading) return;
    state.loading = true;
    clearStatus();
    const content = $('restock-content');
    if (content) {
      content.innerHTML = `
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <div class="loader mb-3"></div>
          <p class="text-xs font-semibold">Calculando faltantes…</p>
        </div>`;
    }
    try {
      const response = await fetch(`api/restock.php?target_days=${encodeURIComponent(state.targetDays)}`, {
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudo calcular la reposición.');
      state.data = data;
      state.buyNote = '';
      state.purchaseRows = (data.purchase || []).map((material) => defaultPurchaseRow(material));
      render();
      if (state.focusProductId) {
        const hasCard = document.querySelector(`[data-restock-product="${state.focusProductId}"]`);
        if (hasCard) {
          state.tab = 'missing';
          render();
          setTimeout(() => {
            document.querySelector(`[data-restock-product="${state.focusProductId}"]`)
              ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 80);
        }
      }
    } catch (error) {
      if (content) {
        content.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm font-semibold text-rose-700">${escapeHtml(error.message || 'No se pudo calcular la reposición.')}</div>`;
      }
      setStatus(error.message || 'No se pudo calcular la reposición.', 'error');
    } finally {
      state.loading = false;
    }
  }

  function defaultPurchaseRow(material) {
    const presentationQty = Number(material.presentation_qty) || 0;
    const suggested = Number(material.suggested_purchase) || 0;
    const amount = presentationQty > 0
      ? Math.max(1, Number(material.suggested_packages) || Math.ceil((suggested || presentationQty) / presentationQty))
      : Math.max(0.01, suggested || 1);
    return {
      id: Number(material.id),
      selected: true,
      amount: Number(amount) || 1,
      cost: '',
    };
  }

  function presentationInfo(material) {
    const qty = Number(material.presentation_qty) || 0;
    if (qty <= 0) return null;
    return {
      label: material.presentation_label || 'Presentación',
      qty,
    };
  }

  function baseQuantity(row) {
    const material = state.data?.materials.find((m) => Number(m.id) === Number(row.id));
    if (!material) return Number(row.amount) || 0;
    const presentation = presentationInfo(material);
    return (Number(row.amount) || 0) * (presentation ? presentation.qty : 1);
  }

  function rowCost(row) {
    const value = Number(row.cost);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function render() {
    if (!state.data) return;
    renderSummary();
    renderTabs();
    if (state.tab === 'buy') renderBuy();
    else if (state.tab === 'produce') renderProduce();
    else renderMissing();
    icons($('restock-modal'));
  }

  function renderSummary() {
    const summary = state.data.summary || {};
    const box = $('restock-summary');
    if (!box) return;
    const cards = [
      ['missing', 'Por reponer', Number(summary.products_low || 0), 'bg-rose-50 text-rose-700 border-rose-100'],
      ['buy', 'Insumos por comprar', Number(summary.purchase_count || 0), 'bg-amber-50 text-amber-800 border-amber-100'],
      ['buy', 'Faltan insumos', Number(summary.missing_materials || 0), 'bg-orange-50 text-orange-700 border-orange-100'],
      ['produce', 'Se pueden elaborar', Number(summary.production_count || 0), 'bg-emerald-50 text-emerald-700 border-emerald-100'],
    ];
    box.innerHTML = cards.map(([tab, label, value, cls]) => `
      <button type="button" onclick="restockSetTab('${tab}')" class="text-left rounded-2xl border p-3 ${cls} hover:shadow-sm transition-all">
        <span class="block text-[10px] font-extrabold uppercase tracking-wide opacity-80">${label}</span>
        <span class="block text-xl font-black font-heading mt-0.5">${value}</span>
      </button>`).join('');
  }

  function renderTabs() {
    document.querySelectorAll('.restock-tab').forEach((button) => {
      button.classList.toggle('active', button.dataset.restockTab === state.tab);
    });
  }

  function renderMissing() {
    const content = $('restock-content');
    const products = (state.data.products || []).filter((p) => p.restock_needed || p.missing_count > 0);
    const materials = (state.data.purchase || []).filter((m) => m.status !== 'ok' || (m.blocks && m.blocks.length));
    if (!products.length && !materials.length) {
      content.innerHTML = emptyState('check-circle-2', 'Todo en orden', 'No hay productos ni insumos que reponer con el objetivo actual.');
      return;
    }
    content.innerHTML = `
      ${products.length ? `<section class="mb-6">
        <div class="flex items-center justify-between gap-2 mb-2.5">
          <h3 class="text-sm font-extrabold text-slate-800 font-heading">Productos que necesitan reposición</h3>
          <span class="text-[10px] font-bold text-slate-400">${products.length}</span>
        </div>
        <div class="grid grid-cols-1 gap-2.5">${products.map(productCard).join('')}</div>
      </section>` : ''}
      ${materials.length ? `<section>
        <div class="flex items-center justify-between gap-2 mb-2.5">
          <h3 class="text-sm font-extrabold text-slate-800 font-heading">Insumos bajos o bloqueando recetas</h3>
          <span class="text-[10px] font-bold text-slate-400">${materials.length}</span>
        </div>
        <div class="grid grid-cols-1 gap-2.5">${materials.map(materialCard).join('')}</div>
      </section>` : ''}`;
  }

  function productCard(product) {
    const hasRecipe = !!product.has_recipe;
    const canMake = product.can_make === null ? null : Number(product.can_make);
    const missing = product.missing_materials || [];
    const suggested = Math.max(1, Number(product.suggested_units) || 1);
    let actions = '';
    if (hasRecipe && canMake !== null && canMake > 0) {
      const maxQty = Math.max(1, Math.min(suggested || 1, canMake));
      actions += `
        <div class="flex items-center gap-1.5">
          <input type="number" min="1" max="${canMake}" step="1" value="${maxQty}" data-restock-qty="${product.id}" class="w-20 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-pink-200" aria-label="Cantidad a elaborar">
          <button type="button" onclick="restockProduce(${product.id}, this)" class="rounded-xl bg-pink-500 hover:bg-pink-600 text-white px-3 py-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
            <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar
          </button>
        </div>`;
    }
    if (hasRecipe && missing.length) {
      actions += `<button type="button" onclick="restockBuyMissing(${product.id}, this)" class="rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-800 px-3 py-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Comprar lo que falta
      </button>`;
    }
    if (!hasRecipe) {
      actions += `
        <div class="flex items-center gap-1.5">
          <input type="number" min="1" step="1" value="${suggested}" data-restock-stock="${product.id}" class="w-20 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-pink-200" aria-label="Cantidad a cargar">
          <button type="button" onclick="restockStock(${product.id}, this)" class="rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 px-3 py-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
            <i data-lucide="package-plus" class="w-3.5 h-3.5"></i> Cargar stock
          </button>
        </div>`;
    }

    return `
      <article data-restock-product="${product.id}" class="rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-start gap-2.5 min-w-0">
            <span class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 mt-0.5" style="background:${safeColor(product.image_color)}"></span>
            <div class="min-w-0">
              <h4 class="text-sm font-extrabold text-slate-800 font-heading truncate">${escapeHtml(product.name)}</h4>
              <p class="text-[10px] text-slate-400 font-medium">Stock: ${number(product.stock)} · ${hasRecipe ? (canMake === null ? 'Con receta' : `Puedes elaborar ${Math.max(0, canMake)}`) : 'Producto de reventa'}</p>
            </div>
          </div>
          <div class="shrink-0">${statusPill(product.status)}</div>
        </div>
        ${missing.length ? `<div class="mt-2.5 rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2">
          <p class="text-[10px] font-extrabold uppercase tracking-wide text-amber-800 mb-1">Falta para elaborar</p>
          <div class="flex flex-wrap gap-1.5">
            ${missing.map((item) => `<span class="rounded-full bg-white border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-800">${escapeHtml(item.name)}: ${number(item.missing, 3)} ${escapeHtml(item.unit)}</span>`).join('')}
          </div>
        </div>` : ''}
        ${actions ? `<div class="mt-2.5 flex flex-wrap items-center gap-2">${actions}</div>` : ''}
      </article>`;
  }

  function materialCard(material) {
    const presentation = presentationInfo(material);
    const suggested = Number(material.suggested_purchase) || 0;
    const suggestedText = presentation
      ? `${Math.max(1, Number(material.suggested_packages) || 1)} × ${escapeHtml(presentation.label)}`
      : `${number(suggested, 3)} ${escapeHtml(material.unit)}`;
    return `
      <article class="rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h4 class="text-sm font-extrabold text-slate-800 font-heading truncate">${escapeHtml(material.name)}</h4>
            <p class="text-[10px] text-slate-400 font-medium">Stock: ${number(material.stock, 3)} ${escapeHtml(material.unit)} · Mínimo: ${number(material.min_stock, 3)}</p>
            ${material.blocks && material.blocks.length ? `<p class="text-[10px] font-bold text-rose-600 mt-0.5 truncate">Bloquea: ${material.blocks.map((block) => escapeHtml(block.name)).join(', ')}</p>` : ''}
          </div>
          <div class="shrink-0">${statusPill(material.status)}</div>
        </div>
        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2">
          <span class="text-[11px] font-bold text-slate-600">Sugerido: <span class="text-pink-600">${suggestedText}</span></span>
          <button type="button" onclick="restockFocusMaterial(${material.id})" class="rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 px-3 py-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Comprar
          </button>
        </div>
      </article>`;
  }

  function renderBuy() {
    const content = $('restock-content');
    const allMaterials = state.data.materials || [];
    const missingOptions = allMaterials.filter((material) => !state.purchaseRows.some((row) => Number(row.id) === Number(material.id)));
    if (!state.purchaseRows.length) {
      content.innerHTML = `
        ${emptyState('shopping-cart', 'Sin compras sugeridas', 'Puedes agregar cualquier insumo manualmente para cargar mercancía.')}
        <div class="mt-4 max-w-sm mx-auto">
          ${addMaterialSelect(missingOptions)}
        </div>`;
      return;
    }

    const selected = state.purchaseRows.filter((row) => row.selected);
    const totalCost = selected.reduce((sum, row) => sum + rowCost(row), 0);
    content.innerHTML = `
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-600">
          <input type="checkbox" id="restock-select-all" onchange="restockSelectAll(this.checked)" ${selected.length === state.purchaseRows.length ? 'checked' : ''} class="w-4 h-4 accent-pink-600">
          Seleccionar todo
        </label>
        ${addMaterialSelect(missingOptions)}
      </div>
      <div class="grid grid-cols-1 gap-2.5">
        ${state.purchaseRows.map(purchaseRow).join('')}
      </div>
      <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 flex flex-col sm:flex-row sm:items-end gap-3">
        <label class="flex-1 text-xs font-bold text-slate-600">Nota de la compra
          <input id="restock-buy-note" maxlength="200" value="${escapeHtml(state.buyNote)}" oninput="restockSetBuyNote(this.value)" placeholder="Ej. Proveedor, factura" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-pink-200">
        </label>
        <div class="flex items-center justify-between sm:justify-end gap-3">
          <span class="text-xs font-bold text-slate-500">${selected.length} seleccionado(s)${totalCost > 0 ? ' · ' + money(totalCost) : ''}</span>
          <button type="button" onclick="restockSubmitBuy(this)" ${selected.length ? '' : 'disabled'} class="rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:pointer-events-none text-white px-4 py-2.5 text-xs font-extrabold inline-flex items-center gap-1.5 transition-colors">
            <i data-lucide="check" class="w-4 h-4"></i> Registrar compras
          </button>
        </div>
      </div>`;
  }

  function addMaterialSelect(options) {
    if (!options.length) return '';
    return `<label class="text-[11px] font-bold text-slate-500">Agregar otro insumo
      <select onchange="restockAddMaterial(this.value); this.value=''" class="mt-1 block w-full sm:w-64 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-pink-200">
        <option value="">Elegir insumo…</option>
        ${options.map((material) => `<option value="${material.id}">${escapeHtml(material.name)} (${escapeHtml(material.unit)})</option>`).join('')}
      </select>
    </label>`;
  }

  function purchaseRow(row) {
    const material = state.data.materials.find((item) => Number(item.id) === Number(row.id));
    if (!material) return '';
    const presentation = presentationInfo(material);
    const base = baseQuantity(row);
    const suggested = Number(material.suggested_purchase) || 0;
    const suggestion = presentation
      ? `${Math.max(1, Number(material.suggested_packages) || 1)} × ${escapeHtml(presentation.label)}`
      : `${number(suggested, 3)} ${escapeHtml(material.unit)}`;
    return `
      <article class="rounded-2xl border border-slate-200 bg-white p-3.5 ${row.selected ? 'ring-1 ring-pink-100' : 'opacity-70'}">
        <div class="flex items-start gap-2.5">
          <input type="checkbox" ${row.selected ? 'checked' : ''} onchange="restockToggleBuy(${material.id}, this.checked)" class="mt-1 w-4 h-4 accent-pink-600 shrink-0" aria-label="Incluir ${escapeHtml(material.name)}">
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <h4 class="text-sm font-extrabold text-slate-800 font-heading truncate">${escapeHtml(material.name)}</h4>
                <p class="text-[10px] text-slate-400 font-medium">Stock: ${number(material.stock, 3)} ${escapeHtml(material.unit)} · Mínimo: ${number(material.min_stock, 3)}</p>
                <p class="text-[10px] font-bold text-emerald-700 mt-0.5">Sugerido: ${suggestion}</p>
              </div>
              ${statusPill(material.status)}
            </div>
            <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
              <label class="text-[10px] font-extrabold uppercase tracking-wide text-slate-500">${presentation ? 'Paquetes' : 'Cantidad (' + escapeHtml(material.unit) + ')'}
                <input type="number" min="0.01" step="0.01" value="${row.amount}" data-buy-id="${material.id}" onchange="restockSetBuyAmount(${material.id}, this.value)" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-pink-200">
              </label>
              <div class="text-[11px] font-semibold text-slate-500">Carga real
                <div class="mt-1 rounded-xl bg-slate-100 px-3 py-2 text-sm font-extrabold text-slate-800">${number(base, 3)} ${escapeHtml(material.unit)}</div>
                ${presentation ? `<span class="block text-[9px] mt-0.5">${row.amount} × ${escapeHtml(presentation.label)}</span>` : ''}
              </div>
              <label class="text-[10px] font-extrabold uppercase tracking-wide text-slate-500">Costo total ($)
                <input type="number" min="0" step="0.01" value="${row.cost}" placeholder="Opcional" onchange="restockSetBuyCost(${material.id}, this.value)" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-pink-200">
              </label>
            </div>
          </div>
        </div>
      </article>`;
  }

  function renderProduce() {
    const content = $('restock-content');
    const products = state.data.production || [];
    if (!products.length) {
      content.innerHTML = emptyState('check-circle-2', 'Nada que elaborar', 'No hay productos con receta que necesiten producción con el objetivo actual.');
      return;
    }
    content.innerHTML = `
      <div class="mb-2.5 flex items-center justify-between gap-2">
        <h3 class="text-sm font-extrabold text-slate-800 font-heading">Elaborar productos</h3>
        <span class="text-[10px] font-bold text-slate-400">${products.length}</span>
      </div>
      <div class="grid grid-cols-1 gap-2.5">
        ${products.map((product) => {
          const max = Math.max(1, Number(product.can_make) || 1);
          const suggested = Math.max(1, Math.min(Number(product.suggested_units) || 1, max));
          return `<article class="rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2.5 min-w-0">
                <span class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 mt-0.5" style="background:${safeColor(product.image_color)}"></span>
                <div class="min-w-0">
                  <h4 class="text-sm font-extrabold text-slate-800 font-heading truncate">${escapeHtml(product.name)}</h4>
                  <p class="text-[10px] text-slate-400 font-medium">Stock: ${number(product.stock)} · Máximo ahora: ${max}</p>
                </div>
              </div>
              ${statusPill(product.status)}
            </div>
            <div class="mt-2.5 flex items-center gap-1.5">
              <input type="number" min="1" max="${max}" step="1" value="${suggested}" data-restock-produce-qty="${product.id}" class="w-20 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-pink-200" aria-label="Cantidad a elaborar">
              <button type="button" onclick="restockProduce(${product.id}, this)" class="rounded-xl bg-pink-500 hover:bg-pink-600 text-white px-3.5 py-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
                <i data-lucide="chef-hat" class="w-3.5 h-3.5"></i> Elaborar
              </button>
            </div>
          </article>`;
        }).join('')}
      </div>`;
  }

  function emptyState(icon, title, text) {
    return `<div class="flex flex-col items-center justify-center py-14 text-center text-slate-400">
      <div class="w-14 h-14 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center mb-3"><i data-lucide="${icon}" class="w-7 h-7"></i></div>
      <p class="font-extrabold text-slate-700 text-sm mb-1">${escapeHtml(title)}</p>
      <p class="text-xs max-w-xs">${escapeHtml(text)}</p>
    </div>`;
  }

  function productById(id) {
    return (state.data?.products || []).find((product) => Number(product.id) === Number(id));
  }

  function materialById(id) {
    return (state.data?.materials || []).find((material) => Number(material.id) === Number(id));
  }

  function setTab(tab) {
    state.tab = tab;
    render();
  }

  function focusMaterial(id) {
    let row = state.purchaseRows.find((item) => Number(item.id) === Number(id));
    if (!row) {
      const material = materialById(id);
      if (!material) return;
      row = defaultPurchaseRow(material);
      state.purchaseRows.push(row);
    }
    row.selected = true;
    state.tab = 'buy';
    render();
    setTimeout(() => {
      const input = document.querySelector(`[data-buy-id="${id}"]`);
      if (input) input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 40);
  }

  async function produce(id, button) {
    const product = productById(id);
    const input = document.querySelector(`[data-restock-produce-qty="${id}"], [data-restock-qty="${id}"]`);
    const quantity = Math.max(1, parseInt(input?.value, 10) || 1);
    if (product && quantity > Number(product.can_make)) {
      setStatus(`Solo puedes elaborar ${product.can_make} unidad(es) con el stock actual.`, 'error');
      return;
    }
    await runAction(button, 'api/production.php', { product_id: Number(id), quantity }, `Elaboradas ${quantity} unidad(es) de ${product ? product.name : 'producto'}.`);
  }

  async function stockProduct(id, button) {
    const product = productById(id);
    const input = document.querySelector(`[data-restock-stock="${id}"]`);
    const quantity = Math.max(1, parseInt(input?.value, 10) || 1);
    await runAction(button, 'api/products.php', {
      action: 'stock_entry', id: Number(id), reason: 'purchase', quantity,
      note: 'Reposición rápida',
    }, `Cargadas ${quantity} unidad(es) de ${product ? product.name : 'producto'}.`);
  }

  async function runAction(button, url, payload, successMessage) {
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<div class="loader inline-block border-2 mr-1"></div> Procesando…';
    try {
      const response = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || 'No se pudo completar la operación.');
      refreshUnderlyingData();
      await loadData();
      setStatus(successMessage, 'success');
    } catch (error) {
      setStatus(error.message || 'No se pudo completar la operación.', 'error');
    } finally {
      if (button.isConnected) {
        button.disabled = false;
        button.innerHTML = original;
        icons(button);
      }
    }
  }

  async function submitPurchases(button) {
    const selected = state.purchaseRows.filter((row) => row.selected && baseQuantity(row) > 0);
    if (!selected.length) return;
    const note = (state.buyNote || '').trim() || null;
    const items = selected.map((row) => ({
      id: row.id,
      quantity: baseQuantity(row),
      total_cost: rowCost(row) > 0 ? rowCost(row) : null,
    }));
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<div class="loader inline-block border-2 mr-1"></div> Registrando…';
    try {
      const response = await fetch('api/materials.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'purchase_batch', items, note }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || 'No se pudieron registrar las compras.');
      refreshUnderlyingData();
      await loadData();
      setStatus(`Compras registradas: ${items.length} insumo(s).`, 'success');
    } catch (error) {
      setStatus(error.message || 'No se pudieron registrar las compras.', 'error');
    } finally {
      if (button.isConnected) {
        button.disabled = false;
        button.innerHTML = original;
        icons(button);
      }
    }
  }

  function refreshUnderlyingData() {
    ['fetchProducts', 'loadInventory', 'loadMaterials', 'loadProduction', 'loadInsights'].forEach((name) => {
      if (typeof window[name] === 'function') {
        try { window[name](); } catch (error) { /* optional page refresh */ }
      }
    });
  }

  function buyMissing(productId) {
    const product = productById(productId);
    if (!product) return;
    (product.missing_materials || []).forEach((missing) => {
      let row = state.purchaseRows.find((item) => Number(item.id) === Number(missing.id));
      if (!row) {
        const material = materialById(missing.id);
        if (material) {
          row = defaultPurchaseRow(material);
          state.purchaseRows.push(row);
        }
      }
      if (row) row.selected = true;
    });
    state.tab = 'buy';
    render();
  }

  function addMaterial(id) {
    if (!id) return;
    if (state.purchaseRows.some((row) => Number(row.id) === Number(id))) return;
    const material = materialById(id);
    if (!material) return;
    state.purchaseRows.push(defaultPurchaseRow(material));
    state.tab = 'buy';
    render();
  }

  function setBuyAmount(id, value) {
    const row = state.purchaseRows.find((item) => Number(item.id) === Number(id));
    if (row) row.amount = Math.max(0.01, Number(value) || 0);
    renderBuy();
    icons($('restock-modal'));
  }

  function setBuyNote(value) {
    state.buyNote = String(value || '');
  }

  function setBuyCost(id, value) {
    const row = state.purchaseRows.find((item) => Number(item.id) === Number(id));
    if (row) row.cost = value;
    renderBuy();
    icons($('restock-modal'));
  }

  function toggleBuy(id, checked) {
    const row = state.purchaseRows.find((item) => Number(item.id) === Number(id));
    if (row) row.selected = !!checked;
    renderBuy();
    icons($('restock-modal'));
  }

  function selectAll(checked) {
    state.purchaseRows.forEach((row) => { row.selected = !!checked; });
    renderBuy();
    icons($('restock-modal'));
  }

  // Public API used by inline handlers and other pages.
  window.openRestockModal = openRestockModal;
  window.closeRestockModal = closeRestockModal;
  window.restockSetTab = setTab;
  window.restockFocusMaterial = focusMaterial;
  window.restockProduce = produce;
  window.restockStock = stockProduct;
  window.restockSubmitBuy = submitPurchases;
  window.restockBuyMissing = buyMissing;
  window.restockAddMaterial = addMaterial;
  window.restockSetBuyAmount = setBuyAmount;
  window.restockSetBuyCost = setBuyCost;
  window.restockSetBuyNote = setBuyNote;
  window.restockToggleBuy = toggleBuy;
  window.restockSelectAll = selectAll;

  function init() {
    const modal = $('restock-modal');
    if (!modal) return;
    document.querySelectorAll('.restock-tab').forEach((button) => {
      button.addEventListener('click', () => setTab(button.dataset.restockTab));
    });
    $('restock-target-days')?.addEventListener('change', (event) => {
      state.targetDays = Number(event.target.value) || 7;
      try { localStorage.setItem('sweetpos.restock.targetDays', String(state.targetDays)); } catch (error) {}
      loadData();
    });
    $('restock-refresh')?.addEventListener('click', loadData);
    $('restock-download')?.addEventListener('click', () => {
      window.open(`api/report_export.php?type=reponer&target_days=${encodeURIComponent(state.targetDays)}&format=pdf`, '_blank');
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && state.open) closeRestockModal();
    });
    icons(modal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
