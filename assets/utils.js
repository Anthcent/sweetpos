// assets/utils.js — Shared helpers loaded on every authenticated page (via _sidebar.php).

// Escapes a value before interpolating it into innerHTML / template strings.
// Use it for every user-controlled string (product, material and client names,
// references, table labels, server error messages...).
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
window.escapeHtml = escapeHtml;

// Only allows #rgb / #rrggbb colors inside style attributes.
function safeColor(value, fallback = '#fce8f0') {
    return /^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(value || '') ? value : fallback;
}
window.safeColor = safeColor;

// ---------------------------------------------------------------------------
// Responsive pagination shared by the data modules.
// Usage:
//   const state = SweetPagination.paginate(items, page, SweetPagination.pageSize(6, 12));
//   SweetPagination.render(el, { page, pages, total, perPage, label, onChange });
// ---------------------------------------------------------------------------
const SweetPagination = (() => {
  const mobileQuery = window.matchMedia('(max-width: 767px)');

  function isMobile() {
    return mobileQuery.matches;
  }

  function pageSize(mobile = 6, desktop = 12) {
    return isMobile() ? mobile : desktop;
  }

  function normalizePage(page, total, perPage) {
    const pages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
    return Math.min(Math.max(1, Number(page) || 1), pages);
  }

  function paginate(items, page, perPage) {
    const list = Array.isArray(items) ? items : [];
    const safePerPage = Math.max(1, Number(perPage) || 10);
    const pages = Math.max(1, Math.ceil(list.length / safePerPage));
    const current = Math.min(Math.max(1, Number(page) || 1), pages);
    const start = (current - 1) * safePerPage;
    return {
      items: list.slice(start, start + safePerPage),
      page: current,
      pages,
      total: list.length,
      perPage: safePerPage,
    };
  }

  function render(container, options) {
    if (!container) return;
    const total = Number(options.total) || 0;
    const perPage = Math.max(1, Number(options.perPage) || 10);
    if (total <= perPage) {
      container.innerHTML = '';
      container.classList.add('hidden');
      return;
    }

    const page = Math.min(Math.max(1, Number(options.page) || 1), Math.max(1, Number(options.pages) || 1));
    const pages = Math.max(1, Number(options.pages) || 1);
    const from = (page - 1) * perPage + 1;
    const to = Math.min(total, page * perPage);
    const label = options.label || 'registros';

    container.classList.remove('hidden');
    container.innerHTML = `
      <div class="responsive-pager-info" aria-live="polite">
        Mostrando <strong>${from}–${to}</strong> de <strong>${total}</strong> ${label}
      </div>
      <div class="responsive-pager-controls">
        <button type="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''} aria-label="Página anterior">
          <i data-lucide="chevron-left" class="w-4 h-4"></i><span>Anterior</span>
        </button>
        <span class="responsive-pager-current">Página <strong>${page}</strong> de ${pages}</span>
        <button type="button" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''} aria-label="Página siguiente">
          <span>Siguiente</span><i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>
      </div>`;

    container.querySelectorAll('button[data-page]').forEach(button => {
      button.addEventListener('click', () => {
        const next = Number(button.dataset.page);
        if (next >= 1 && next <= pages && next !== page) {
          options.onChange(next);
        }
      });
    });
    if (window.lucide) window.lucide.createIcons({ root: container });
  }

  function onBreakpointChange(callback) {
    if (typeof callback !== 'function') return;
    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', callback);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(callback);
    }
  }

  return { isMobile, pageSize, normalizePage, paginate, render, onBreakpointChange };
})();

window.SweetPagination = SweetPagination;
