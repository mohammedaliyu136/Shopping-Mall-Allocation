/*
 * Ward view client: opens action dialogs (one tap), switches type-selector
 * sections, lazy-loads the patient list and tab content, and keeps the ward
 * config in a versioned client cache (Phase 3).
 */
(function () {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  async function fetchHtml(url) {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error(res.status === 403 ? 'You do not have access to this.' : 'Could not load (' + res.status + ').');
    return res.text();
  }

  /* ---------- Phase 3: versioned client cache of ward config ---------- */
  const WardConfigCache = {
    key: (wardId) => 'ward-config:' + wardId,
    read(wardId) {
      try { return JSON.parse(localStorage.getItem(this.key(wardId)) || 'null'); } catch (e) { return null; }
    },
    write(wardId, etag, data) {
      try { localStorage.setItem(this.key(wardId), JSON.stringify({ etag, data, version: data.version })); } catch (e) { /* storage full or disabled */ }
    },
    /* Returns the cached config, refreshing it only when the server version moved. */
    async load(wardId, url, serverVersion) {
      const cached = this.read(wardId);
      if (cached && cached.version === serverVersion) return cached.data;
      const headers = { Accept: 'application/json' };
      if (cached?.etag) headers['If-None-Match'] = cached.etag;
      const res = await fetch(url, { headers, credentials: 'same-origin' });
      if (res.status === 304 && cached) return cached.data;
      if (!res.ok) return cached?.data ?? null;
      const data = await res.json();
      this.write(wardId, res.headers.get('ETag'), data);
      return data;
    },
  };
  window.WardConfigCache = WardConfigCache;

  /* ---------- type selectors inside forms ---------- */
  function syncTypeSections(form) {
    const checked = form.querySelector('input[name="type"]:checked') || form.querySelector('select[name="type"]');
    const type = checked ? checked.value : null;
    form.querySelectorAll('[data-types]').forEach((section) => {
      const show = type && section.dataset.types.split(/\s+/).includes(type);
      section.hidden = !show;
      // Hidden inputs are disabled so they are not submitted or validated.
      section.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !show; });
    });
    form.querySelectorAll('[data-show-when]').forEach((el) => {
      const [name, value] = el.dataset.showWhen.split('=');
      const ctrl = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('select[name="' + name + '"]');
      const show = ctrl && ctrl.value === value;
      el.hidden = !show;
      el.querySelectorAll('input, select, textarea').forEach((i) => { i.disabled = !show; });
    });
  }

  function wireForm(root) {
    root.querySelectorAll('form[data-typed]').forEach((form) => {
      syncTypeSections(form);
      form.addEventListener('change', (e) => {
        syncTypeSections(form);
      });
    });
    root.querySelectorAll('[data-autofocus]').forEach((el) => el.focus());
  }

  /* ---------- action dialog ---------- */
  const dialog = document.getElementById('action-dialog');

  async function openAction(url) {
    if (!dialog) return;
    dialog.innerHTML = '<div class="dlg-body"><div class="loading">Opening…</div></div>';
    if (!dialog.open) dialog.showModal();
    try {
      dialog.innerHTML = await fetchHtml(url);
      wireForm(dialog);
    } catch (err) {
      dialog.innerHTML = '<div class="dlg-body"><p class="flash err">' + err.message + '</p><button class="btn secondary" data-close>Close</button></div>';
    }
  }

  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-action-url]');
    if (opener) { e.preventDefault(); openAction(opener.dataset.actionUrl); return; }

    const local = e.target.closest('[data-open-dialog]');
    if (local) {
      e.preventDefault();
      const d = document.getElementById(local.dataset.openDialog);
      if (d) { d.showModal(); wireForm(d); }
      return;
    }

    if (e.target.closest('[data-close]')) {
      e.preventDefault();
      e.target.closest('dialog')?.close();
    }
  });

  document.querySelectorAll('dialog').forEach((d) => {
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
  });

  /* ---------- async regions (patient list, tabs) ---------- */
  async function loadRegion(el) {
    try {
      el.innerHTML = await fetchHtml(el.dataset.src);
      el.dataset.loaded = '1';
      wireForm(el);
    } catch (err) {
      el.innerHTML = '<p class="flash err">' + err.message + ' <button class="link" data-retry>Retry</button></p>';
    }
  }

  document.querySelectorAll('[data-src]').forEach((el) => {
    loadRegion(el);
    const every = parseInt(el.dataset.refresh || '0', 10);
    if (every > 0) setInterval(() => { if (!document.hidden && !document.querySelector('dialog[open]')) loadRegion(el); }, every * 1000);
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-retry]')) loadRegion(e.target.closest('[data-src]'));
  });

  /* Tabs: switch without a page load; content loads on first open. */
  const tabStrip = document.querySelector('[data-tabs]');
  if (tabStrip) {
    const body = document.getElementById('tab-body');
    tabStrip.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-tab]');
      if (!a) return;
      e.preventDefault();
      tabStrip.querySelectorAll('a').forEach((x) => x.classList.toggle('active', x === a));
      body.dataset.src = a.dataset.tabSrc;
      body.innerHTML = '<div class="skeleton"></div><div class="skeleton"></div>';
      history.replaceState(null, '', a.href);
      loadRegion(body);
    });
    // Filter chips inside the history tab load in place.
    body.addEventListener('click', (e) => {
      const chip = e.target.closest('a[data-inplace]');
      if (!chip) return;
      e.preventDefault();
      body.dataset.src = chip.href;
      loadRegion(body);
    });
  }

  /* Ward screen: cache this ward's config once on selection. */
  const shell = document.querySelector('[data-ward-config]');
  if (shell) {
    WardConfigCache.load(shell.dataset.wardId, shell.dataset.wardConfig, parseInt(shell.dataset.configVersion, 10))
      .then((cfg) => { if (cfg) shell.dataset.cachedVersion = cfg.version; });
  }

  /* Admin form: changing ward type offers that type's buttons and tabs. */
  const setup = document.querySelector('form[data-ward-setup]');
  if (setup) {
    const catalogue = JSON.parse(setup.dataset.catalogue);
    const typeSel = setup.querySelector('select[name="ward_type"]');
    const original = typeSel.value;
    const apply = setup.querySelector('input[name="apply_defaults"]');
    const refresh = () => {
      const allowed = catalogue[typeSel.value];
      setup.querySelectorAll('[data-option-group]').forEach((input) => {
        const ok = allowed[input.dataset.optionGroup].includes(input.value);
        input.disabled = !ok;
        input.closest('label').hidden = !ok;
      });
      // Tick "apply defaults" for a type change; undo our own tick if they switch back.
      if (apply && typeSel.value !== original) { apply.checked = true; autoTicked = true; }
      else if (apply && autoTicked) { apply.checked = false; autoTicked = false; }
    };
    let autoTicked = false;
    typeSel.addEventListener('change', refresh);
    refresh();
  }

  /* Re-open a form that failed validation. */
  const reopen = document.querySelector('dialog[data-open-on-load]');
  if (reopen) { reopen.showModal(); wireForm(reopen); }

  wireForm(document);
})();
