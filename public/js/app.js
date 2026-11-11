/* Bayanihan Connect - UI helpers and offline capture with sync-on-reconnect (FR07). */
(function () {
    'use strict';

    const QUEUE_KEY = 'bc-offline-queue';
    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
    const userId = meta('user-id');
    const base = new URL(document.querySelector('link[rel="manifest"]')?.href || '/manifest.webmanifest', location.href);
    const appBase = base.href.replace(/manifest\.webmanifest$/, '');

    /* ---------- Mobile navigation ---------- */
    document.getElementById('menu-btn')?.addEventListener('click', () => document.body.classList.toggle('nav-open'));
    document.getElementById('sidebar-backdrop')?.addEventListener('click', () => document.body.classList.remove('nav-open'));

    /* ---------- Small helpers ---------- */
    function uuid() {
        if (window.crypto?.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }
    function readQueue() {
        try { return JSON.parse(localStorage.getItem(QUEUE_KEY)) || []; } catch { return []; }
    }
    function writeQueue(items) {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(items)); } catch { /* storage unavailable */ }
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // Each offline-capable form gets a unique id so a replayed entry is never saved twice.
    document.querySelectorAll('input[data-client-uuid]').forEach((el) => { if (!el.value) el.value = uuid(); });

    /* ---------- Connection status ---------- */
    const statusEl = document.getElementById('sync-status');
    const bannerEl = document.getElementById('offline-banner');
    const panelEl = document.getElementById('pending-panel');
    let syncing = false;

    function myQueue() { return readQueue().filter((i) => i.userId === userId); }

    function render() {
        const online = navigator.onLine;
        const pending = myQueue();
        if (bannerEl) bannerEl.hidden = online;
        if (statusEl) {
            statusEl.dataset.state = syncing ? 'syncing' : online ? 'online' : 'offline';
            const label = syncing ? 'Syncing…' : online ? 'Online' : 'Offline';
            statusEl.querySelector('.sync-text').textContent = pending.length ? `${label} · ${pending.length} pending` : label;
        }
        if (!panelEl) return;
        panelEl.hidden = pending.length === 0;
        if (!pending.length) return;
        panelEl.innerHTML = `<strong>${pending.length} entr${pending.length === 1 ? 'y' : 'ies'} saved on this device, waiting to sync.</strong>`
            + (online ? ' <button type="button" class="btn btn-sm btn-outline" data-sync-now>Sync now</button>' : '')
            + '<ul>' + pending.map((i) => `<li>${escapeHtml(i.label)} <small>(${new Date(i.createdAt).toLocaleString()})</small>`
                + (i.error ? ` <span class="err">— ${escapeHtml(i.error)}</span> <button type="button" class="btn btn-sm btn-danger" data-discard="${i.id}">Discard</button>` : '')
                + '</li>').join('') + '</ul>';
    }

    panelEl?.addEventListener('click', (e) => {
        if (e.target.matches('[data-sync-now]')) flush(true);
        const discard = e.target.getAttribute('data-discard');
        if (discard && confirm('Discard this unsynced entry? It will not be saved.')) {
            writeQueue(readQueue().filter((i) => i.id !== discard));
            render();
        }
    });

    /* ---------- Capture while offline ---------- */
    document.querySelectorAll('form[data-offline]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (navigator.onLine) return; // normal submission
            e.preventDefault();

            const fields = [];
            new FormData(form).forEach((value, key) => { if (key !== '_token') fields.push([key, String(value)]); });
            const labelSource = form.dataset.offlineLabel ? form.querySelector(form.dataset.offlineLabel) : null;
            const label = form.dataset.offline + (labelSource?.value ? `: ${labelSource.value}` : '');

            const queue = readQueue();
            queue.push({ id: uuid(), userId, url: form.action, fields, label, createdAt: Date.now(), error: null });
            writeQueue(queue);

            form.reset();
            form.querySelectorAll('input[data-client-uuid]').forEach((el) => { el.value = uuid(); });
            form.dispatchEvent(new Event('offline-saved'));
            const note = form.querySelector('[data-offline-note]') || form.insertAdjacentElement('afterbegin', Object.assign(document.createElement('div'), {}));
            note.setAttribute('data-offline-note', '');
            note.className = 'alert alert-warning';
            note.textContent = 'Saved on this device. It will be sent automatically when you are back online.';
            note.scrollIntoView({ behavior: 'smooth', block: 'center' });
            render();
        });
    });

    /* ---------- Sync on reconnect ---------- */
    async function flush(includeFailed = false) {
        if (syncing || !navigator.onLine) return;
        const items = myQueue().filter((i) => includeFailed || !i.error);
        if (!items.length) return;
        syncing = true;
        render();
        let synced = 0;

        for (const item of items) {
            try {
                const res = await fetch(item.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token') },
                    body: new URLSearchParams(item.fields),
                });
                if (res.ok) {
                    writeQueue(readQueue().filter((i) => i.id !== item.id));
                    synced++;
                } else if (res.status === 422) {
                    const body = await res.json().catch(() => ({}));
                    const first = body.errors ? Object.values(body.errors)[0][0] : body.message;
                    writeQueue(readQueue().map((i) => (i.id === item.id ? { ...i, error: first || 'Rejected by the server.' } : i)));
                } else if (res.status === 401 || res.status === 419) {
                    break; // signed out or session expired: keep everything until the user signs in again
                } else {
                    writeQueue(readQueue().map((i) => (i.id === item.id ? { ...i, error: `Server error (${res.status}). Will retry.` } : i)));
                }
            } catch {
                break; // still offline
            }
        }

        syncing = false;
        render();
        if (synced) {
            const toast = Object.assign(document.createElement('div'), {
                className: 'alert alert-success',
                textContent: `${synced} offline entr${synced === 1 ? 'y was' : 'ies were'} synced to Bayanihan Connect.`,
            });
            document.querySelector('.content')?.prepend(toast);
        }
    }

    window.addEventListener('online', () => { render(); flush(); });
    window.addEventListener('offline', render);
    render();
    if (userId) flush();

    /* ---------- Service worker ---------- */
    if ('serviceWorker' in navigator && userId) {
        navigator.serviceWorker.register(appBase + 'sw.js', { scope: appBase }).then(() => navigator.serviceWorker.ready).then((reg) => {
            const urls = JSON.parse(meta('offline-urls') || '[]');
            reg.active?.postMessage({ type: 'precache', urls });
        }).catch(() => { /* offline support unavailable (e.g. non-secure origin) */ });
    }
    document.querySelectorAll('form[action$="/logout"]').forEach((form) => form.addEventListener('submit', () => {
        navigator.serviceWorker?.controller?.postMessage({ type: 'clear-pages' });
    }));

    /* ---------- Confirm dangerous actions ---------- */
    document.querySelectorAll('[data-confirm]').forEach((el) => el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    }));
})();
