// assets/live.js — Near real-time refresh between cashiers without long-lived
// connections (works behind Apache prefork and serverless). One poller per page
// asks api/changes.php for cheap per-topic fingerprints and notifies the
// subscribers of a topic only when its fingerprint changes.
//
//   SweetLive.subscribe('sales', () => reload());            // returns unsubscribe()
//   SweetLive.subscribe(['sales', 'stock'], fn, { minIntervalMs: 10000 });
//   SweetLive.poke();  // after an own mutation: check right away
(function (global) {
    'use strict';

    const ENDPOINT = 'api/changes.php';
    const POLL_INTERVAL_MS = 4000;
    const MAX_BACKOFF_MS = 30000;
    const REQUEST_TIMEOUT_MS = 10000;
    // focus + visibilitychange usually fire together; one request is enough.
    const EVENT_POLL_MIN_GAP_MS = 1000;
    // Consecutive failures before warning the cashier that data may be stale.
    const FAILURES_BEFORE_WARNING = 2;

    const subscribers = [];
    let versions = null;        // last fingerprints seen; null until the first answer
    let timer = null;
    let inFlight = false;
    let pokeQueued = false;
    let failures = 0;
    let stopped = false;
    let listening = false;
    let lastPollAt = 0;
    let banner = null;

    function subscribe(topics, callback, options) {
        if (typeof callback !== 'function') throw new TypeError('SweetLive.subscribe needs a callback');
        const sub = {
            topics: (Array.isArray(topics) ? topics : [topics]).map(String),
            callback,
            minIntervalMs: Math.max(0, Number(options && options.minIntervalMs) || 0),
            lastRunAt: 0,
            deferred: null,
            pending: new Set(),
        };
        subscribers.push(sub);
        start();
        return function unsubscribe() {
            const index = subscribers.indexOf(sub);
            if (index > -1) subscribers.splice(index, 1);
            if (sub.deferred) clearTimeout(sub.deferred);
        };
    }

    function poke() {
        if (stopped || subscribers.length === 0) return;
        if (inFlight) {
            // The running request may predate the mutation: ask again when it ends.
            pokeQueued = true;
            return;
        }
        poll();
    }

    function start() {
        if (!listening) {
            listening = true;
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) clearTimer();
                else pollFromEvent();
            });
            global.addEventListener('focus', pollFromEvent);
            global.addEventListener('online', pollFromEvent);
        }
        if (!inFlight && !timer) poll();
    }

    function pollFromEvent() {
        if (inFlight || Date.now() - lastPollAt < EVENT_POLL_MIN_GAP_MS) return;
        poll();
    }

    function clearTimer() {
        if (timer) clearTimeout(timer);
        timer = null;
    }

    function schedule() {
        clearTimer();
        if (stopped || subscribers.length === 0 || document.hidden) return;
        let delay = POLL_INTERVAL_MS;
        if (pokeQueued) delay = 0;
        else if (failures > 0) delay = Math.min(POLL_INTERVAL_MS * Math.pow(2, failures), MAX_BACKOFF_MS);
        pokeQueued = false;
        timer = setTimeout(poll, delay);
    }

    async function poll() {
        clearTimer();
        if (stopped || inFlight || subscribers.length === 0) return;
        // Hidden tabs do not poll; visibilitychange resumes with an immediate check.
        if (document.hidden) return;

        inFlight = true;
        lastPollAt = Date.now();
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timeout = controller ? setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS) : null;
        try {
            const res = await fetch(ENDPOINT, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller ? controller.signal : undefined,
            });
            if (res.status === 401) {
                // Session expired: stop and tell the cashier to sign in again.
                stopped = true;
                showBanner('Tu sesión expiró: los datos ya no se actualizan en vivo.', true);
                return;
            }
            const data = await res.json();
            if (!res.ok || !data || typeof data !== 'object') throw new Error('Unexpected changes response');
            applyVersions(data);
            failures = 0;
            hideBanner();
        } catch (error) {
            failures++;
            if (failures >= FAILURES_BEFORE_WARNING) {
                showBanner('Sin conexión con el servidor: los datos pueden estar desactualizados. Reintentando…', false);
            }
        } finally {
            if (timeout) clearTimeout(timeout);
            inFlight = false;
            schedule();
        }
    }

    function applyVersions(data) {
        const next = {};
        Object.keys(data).forEach((key) => {
            if (key !== 'server_time' && typeof data[key] === 'string') next[key] = data[key];
        });
        // The first answer is only the baseline: the page just loaded fresh data.
        if (versions === null) {
            versions = next;
            return;
        }
        const changed = Object.keys(next).filter((topic) => versions[topic] !== next[topic]);
        versions = next;
        if (changed.length === 0) return;
        subscribers.slice().forEach((sub) => {
            const hits = sub.topics.filter((topic) => changed.includes(topic));
            if (hits.length) deliver(sub, hits);
        });
    }

    // Runs the callback, at most once per minIntervalMs (trailing call kept).
    function deliver(sub, topics) {
        topics.forEach((topic) => sub.pending.add(topic));
        if (sub.deferred) return;
        const wait = sub.minIntervalMs - (Date.now() - sub.lastRunAt);
        if (wait > 0) {
            sub.deferred = setTimeout(() => {
                sub.deferred = null;
                run(sub);
            }, wait);
            return;
        }
        run(sub);
    }

    function run(sub) {
        if (!subscribers.includes(sub)) return;
        const topics = Array.from(sub.pending);
        sub.pending.clear();
        sub.lastRunAt = Date.now();
        try {
            sub.callback(topics);
        } catch (error) {
            console.error('SweetLive subscriber failed', error);
        }
    }

    // Visible notice while live sync is degraded, so nobody works on stale data unaware.
    function showBanner(message, offerReload) {
        if (!banner) {
            banner = document.createElement('div');
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');
            banner.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:60;'
                + 'max-width:calc(100vw - 32px);display:flex;align-items:center;gap:12px;padding:10px 14px;'
                + 'border-radius:14px;background:#fffbeb;border:1px solid #f59e0b;color:#78350f;'
                + 'font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(15,23,42,.15);';
            document.body.appendChild(banner);
        }
        banner.replaceChildren();
        const text = document.createElement('span');
        text.textContent = message;
        banner.appendChild(text);
        if (offerReload) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Recargar';
            button.style.cssText = 'flex-shrink:0;min-height:36px;padding:0 12px;border-radius:10px;'
                + 'background:#b45309;color:#fff;font-weight:800;border:0;cursor:pointer;';
            button.addEventListener('click', () => global.location.reload());
            banner.appendChild(button);
        }
        banner.style.display = 'flex';
    }

    function hideBanner() {
        if (banner) banner.style.display = 'none';
    }

    global.SweetLive = { subscribe, poke };
})(window);
