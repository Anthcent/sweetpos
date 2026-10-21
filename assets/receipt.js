// assets/receipt.js — On-screen receipt card ("nota de entrega") shown after a
// sale is collected, and reopened from Historial.
//
//   SweetReceipt.show(saleId, { autoClose: true })   // closes itself after 6 s
//   SweetReceipt.show(saleId, { autoClose: false })  // stays until closed
//   SweetReceipt.close()
//
// Data comes from api/receipt.php (same NE-xxxxxx number as the PDF). The
// countdown pauses while the pointer is over the card, while a control inside it
// has focus, after a touch on it (until the next touch outside), while the tab
// is hidden and while printing. Printing uses window.print() with the @media
// print rules in assets/style.css, which print only the receipt at 80 mm.
(function () {
    'use strict';

    const AUTO_CLOSE_MS = 6000;
    const LEAVE_MS = 180;
    const TICK_MS = 40;

    let current = null;   // state of the open card
    let requestSeq = 0;   // discards responses of superseded show() calls

    const reducedMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : { matches: false };

    function esc(value) {
        if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function money(amount, currency) {
        const n = Number(amount) || 0;
        return (currency || '$') + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function qty(value) {
        const n = Number(value) || 0;
        return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
    }

    function icons(root) {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ root });
        }
    }

    // Uses the page's toast when it has one (POS, Mesas); otherwise draws one.
    function notify(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        const bg = type === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
        const icon = type === 'error' ? 'alert-circle' : 'check-circle-2';
        toast.className = `toast px-4 py-3 rounded-2xl text-white shadow-xl flex items-center gap-2.5 font-medium text-sm ${bg}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5 shrink-0 stroke-[2.2]"></i><span>${esc(message)}</span>`;
        container.appendChild(toast);
        icons(toast);
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 250);
        }, 3500);
    }

    async function fetchReceipt(saleId) {
        let response;
        try {
            response = await fetch('api/receipt.php?sale_id=' + encodeURIComponent(saleId), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
        } catch (e) {
            throw new Error('No se pudo cargar el comprobante: error de conexión.');
        }
        const type = response.headers.get('Content-Type') || '';
        let data = null;
        if (type.includes('application/json')) {
            try { data = await response.json(); } catch (e) { data = null; }
        }
        if (!response.ok || !data || typeof data !== 'object' || !data.note) {
            const message = data && typeof data.error === 'string' ? data.error : 'No se pudo cargar el comprobante.';
            throw new Error(message);
        }
        return data;
    }

    // ------------------------------------------------------------------
    // Markup
    // ------------------------------------------------------------------

    function row(label, value) {
        if (value === null || value === undefined || value === '') return '';
        return `<div class="sr-row"><span>${esc(label)}</span><span>${esc(value)}</span></div>`;
    }

    function paperHtml(r) {
        const cur = r.business.currency;
        const sale = r.sale || {};
        const items = Array.isArray(r.items) ? r.items : [];
        const itemsHtml = items.length
            ? items.map(item => `
                <li class="sr-item">
                    <span class="sr-item-name">${esc(item.name)}</span>
                    <span class="sr-item-calc">${esc(qty(item.quantity))} x ${esc(money(item.unit_price, cur))}</span>
                    <span class="sr-item-sub">${esc(money(item.subtotal, cur))}</span>
                </li>`).join('')
            : '<li class="sr-item sr-item-empty">La orden no tiene productos.</li>';

        const client = r.client
            ? row('Cliente', r.client.name) + row('Cédula', r.client.cedula)
            : row('Cliente', 'Cliente rápido');

        const voided = r.voided
            ? `<div class="sr-stamp" aria-hidden="true">ANULADA</div>
               <p class="sr-void-note">Anulada${r.voided.at ? ' el ' + esc(r.voided.at) : ''}${r.voided.by ? ' por ' + esc(r.voided.by) : ''}${r.voided.reason ? '. Motivo: ' + esc(r.voided.reason) : ''}</p>`
            : '';

        return `
            <div class="sr-paper">
                ${voided}
                <header class="sr-business">
                    <p class="sr-business-name">${esc(r.business.name)}</p>
                    ${r.business.rif ? `<p>RIF ${esc(r.business.rif)}</p>` : ''}
                    ${r.business.address ? `<p>${esc(r.business.address)}</p>` : ''}
                    ${r.business.phone ? `<p>Tel. ${esc(r.business.phone)}</p>` : ''}
                </header>
                <div class="sr-cut"></div>
                <div class="sr-doc">
                    <p class="sr-doc-label">Nota de entrega</p>
                    <p class="sr-doc-number">${esc(r.note.number)}</p>
                </div>
                <div class="sr-rows">
                    ${row('Fecha', sale.paid_at || sale.created_at)}
                    ${row('Orden', '#' + String(sale.id).padStart(4, '0'))}
                    ${row('Vendedor', sale.seller)}
                    ${row('Destino', sale.table_label)}
                    ${client}
                </div>
                <div class="sr-cut"></div>
                <ul class="sr-items">${itemsHtml}</ul>
                <div class="sr-cut"></div>
                <div class="sr-total"><span>Total</span><span>${esc(money(r.total, cur))}</span></div>
                <div class="sr-rows">
                    ${row('Pago', r.payment.label)}
                    ${row('Referencia', r.payment.reference)}
                </div>
                <div class="sr-cut"></div>
                <p class="sr-footer">Documento no fiscal</p>
                <p class="sr-footer sr-footer-soft">Gracias por su compra</p>
            </div>`;
    }

    function shellHtml(autoClose) {
        return `
            <div class="sr-backdrop" data-sr-close></div>
            <div class="sr-card" role="dialog" aria-modal="true" aria-label="Comprobante de venta" tabindex="-1">
                <div class="sr-toolbar">
                    <span class="sr-chip ${autoClose ? 'sr-chip-ok' : ''}">
                        <i data-lucide="${autoClose ? 'check-circle-2' : 'receipt'}" class="w-4 h-4"></i>
                        <span>${autoClose ? 'Pago registrado' : 'Comprobante'}</span>
                    </span>
                    <button type="button" class="sr-icon-btn" data-sr-close aria-label="Cerrar comprobante">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="sr-body">
                    <div class="sr-loading" role="status">
                        <div class="loader"></div>
                        <span>Cargando comprobante...</span>
                    </div>
                </div>
                <div class="sr-actions" hidden>
                    <button type="button" class="sr-print btn-sweet-accent" data-sr-print>
                        <i data-lucide="printer" class="w-4 h-4"></i><span>Imprimir</span>
                    </button>
                    <a class="sr-pdf" data-sr-pdf target="_blank" rel="noopener">
                        <i data-lucide="file-text" class="w-4 h-4"></i><span>PDF</span>
                    </a>
                </div>
                ${autoClose ? '<div class="sr-progress" aria-hidden="true"><span></span></div>' : ''}
            </div>`;
    }

    // ------------------------------------------------------------------
    // Countdown
    // ------------------------------------------------------------------

    function setPaused(state, reason, on) {
        if (!state.autoClose) return;
        if (on) state.pauses.add(reason); else state.pauses.delete(reason);
        state.overlay.classList.toggle('sr-paused', state.pauses.size > 0);
    }

    // A short interval (not requestAnimationFrame) keeps time even when the
    // browser skips frames; elapsed time only grows while nothing pauses it.
    function tick() {
        const state = current;
        if (!state || !state.autoClose || !state.running) return;
        const now = performance.now();
        const delta = state.last === null ? 0 : now - state.last;
        state.last = now;
        if (state.pauses.size === 0) state.elapsed += delta;

        const left = Math.max(0, 1 - state.elapsed / AUTO_CLOSE_MS);
        // Reduced motion: the bar moves in one-second steps instead of gliding.
        const shown = reducedMotion.matches ? Math.ceil(left * (AUTO_CLOSE_MS / 1000)) / (AUTO_CLOSE_MS / 1000) : left;
        if (state.bar) state.bar.style.transform = `scaleX(${shown})`;

        if (state.elapsed >= AUTO_CLOSE_MS) close();
    }

    function startCountdown(state) {
        if (!state.autoClose) return;
        state.running = true;
        state.last = null;
        state.timer = setInterval(tick, TICK_MS);
    }

    // ------------------------------------------------------------------
    // Events
    // ------------------------------------------------------------------

    function focusables(card) {
        return Array.from(card.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])'))
            .filter(el => !el.disabled && !el.closest('[hidden]'));
    }

    function bind(state) {
        const { overlay, card } = state;
        const on = (target, type, fn, opts) => {
            target.addEventListener(type, fn, opts);
            state.unbind.push(() => target.removeEventListener(type, fn, opts));
        };

        on(overlay, 'click', (e) => {
            if (e.target.closest('[data-sr-close]')) close();
            else if (e.target.closest('[data-sr-print]')) print();
            else if (e.target.closest('[data-sr-pdf]')) setPaused(state, 'pdf', true); // engaged: keep it open
        });

        on(card, 'pointerenter', (e) => { if (e.pointerType !== 'touch') setPaused(state, 'hover', true); });
        on(card, 'pointerleave', (e) => { if (e.pointerType !== 'touch') setPaused(state, 'hover', false); });
        on(document, 'pointerdown', (e) => {
            if (e.pointerType !== 'touch') return;
            setPaused(state, 'touch', card.contains(e.target));
        }, true);

        // The card itself receives programmatic focus on open; only focus on a
        // control (keyboard users tabbing to Imprimir) pauses the countdown.
        on(card, 'focusin', (e) => setPaused(state, 'focus', e.target !== card));
        on(card, 'focusout', (e) => {
            if (!e.relatedTarget || !card.contains(e.relatedTarget) || e.relatedTarget === card) {
                setPaused(state, 'focus', false);
            }
        });

        on(document, 'visibilitychange', () => setPaused(state, 'hidden', document.hidden));

        on(document, 'keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                close();
                return;
            }
            if (e.key === 'Tab') {
                const list = focusables(card);
                if (!list.length) { e.preventDefault(); card.focus(); return; }
                const first = list[0];
                const last = list[list.length - 1];
                if (!card.contains(document.activeElement)) {
                    e.preventDefault();
                    first.focus();
                } else if (e.shiftKey && (document.activeElement === first || document.activeElement === card)) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }, true);
    }

    // ------------------------------------------------------------------
    // Print
    // ------------------------------------------------------------------

    function print() {
        const state = current;
        if (!state || !state.data) return;
        setPaused(state, 'print', true);
        let finished = false;
        const finish = () => {
            if (finished) return;
            finished = true;
            window.removeEventListener('afterprint', finish);
            document.body.classList.remove('sr-printing');
            if (current === state) close();
        };
        window.addEventListener('afterprint', finish);
        document.body.classList.add('sr-printing');
        try {
            window.print();
        } catch (e) {
            finished = true;
            window.removeEventListener('afterprint', finish);
            document.body.classList.remove('sr-printing');
            setPaused(state, 'print', false);
            notify('No se pudo abrir la impresión.', 'error');
            return;
        }
        // Browsers without afterprint: print() has returned, so the dialog is done.
        if (!('onafterprint' in window)) finish();
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    function close(immediate) {
        const state = current;
        if (!state) return;
        current = null;
        state.running = false;
        if (state.timer) clearInterval(state.timer);
        state.unbind.forEach(fn => fn());
        document.body.classList.remove('sr-printing');

        const remove = () => state.overlay.remove();
        if (immediate || reducedMotion.matches) remove();
        else {
            state.overlay.classList.add('sr-leaving');
            setTimeout(remove, LEAVE_MS);
        }

        const back = state.returnFocus;
        if (back && document.contains(back) && typeof back.focus === 'function' && back.offsetParent !== null) {
            back.focus({ preventScroll: true });
        }
    }

    async function show(saleId, options) {
        const id = parseInt(saleId, 10);
        if (!Number.isInteger(id) || id <= 0) return;
        const autoClose = !options || options.autoClose !== false;

        const returnFocus = current ? current.returnFocus : document.activeElement;
        close(true);
        const seq = ++requestSeq;

        const overlay = document.createElement('div');
        overlay.className = 'sr-overlay';
        overlay.innerHTML = shellHtml(autoClose);
        document.body.appendChild(overlay);
        icons(overlay);

        const card = overlay.querySelector('.sr-card');
        const state = {
            overlay, card, autoClose, returnFocus,
            bar: overlay.querySelector('.sr-progress span'),
            pauses: new Set(), elapsed: 0, last: null, timer: 0, running: false,
            unbind: [], data: null,
        };
        current = state;
        bind(state);
        if (document.hidden) setPaused(state, 'hidden', true);
        card.focus({ preventScroll: true });

        let data;
        try {
            data = await fetchReceipt(id);
        } catch (e) {
            if (seq === requestSeq && current === state) {
                close(true);
                notify(e.message, 'error');
            }
            return;
        }
        if (seq !== requestSeq || current !== state) return;

        state.data = data;
        card.setAttribute('aria-label', 'Comprobante ' + data.note.number);
        overlay.querySelector('.sr-body').innerHTML = paperHtml(data);
        const actions = overlay.querySelector('.sr-actions');
        const pdf = actions.querySelector('[data-sr-pdf]');
        pdf.href = 'api/pdf.php?type=nota&sale_id=' + encodeURIComponent(data.sale.id) + '&inline=1';
        pdf.setAttribute('aria-label', 'Abrir PDF de la nota ' + data.note.number + ' en una pestaña nueva');
        actions.hidden = false;
        overlay.classList.add('sr-ready');
        icons(overlay);
        startCountdown(state);
    }

    window.SweetReceipt = { show, close };
})();
