/* HayFixr CRM — front-end behaviour for the Laravel views.
   Pages are server-rendered; this file handles drawers/modals (loaded as
   server partials), the ⌘K palette, toasts and small UI toggles. */
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------- toasts ---------- */
  function toast(msg, type = 'ok') {
    const c = { ok: 'border-brand-400 text-brand-600', wa: 'border-brand-400 text-brand-600', warn: 'border-amber-500 text-amber', err: 'border-rose-400 text-rose-600' }[type] || '';
    const ic = { ok: '✓', wa: '💬', warn: '!', err: '✕' }[type] || '✓';
    const t = document.createElement('div');
    t.className = `bg-white border-l-4 ${c} border border-slate2 rounded-xl shadow-soft px-4 py-3 text-[13px] text-ink flex items-start gap-2.5 opacity-0 translate-y-1 transition`;
    t.innerHTML = `<span class="mt-px">${ic}</span><span class="flex-1">${esc(msg)}</span>`;
    $('#toasts').appendChild(t);
    requestAnimationFrame(() => { t.style.opacity = 1; t.style.transform = 'none'; });
    setTimeout(() => { t.style.opacity = 0; setTimeout(() => t.remove(), 300); }, 3400);
  }
  window.toast = toast;

  /* ---------- drawer / modal panels ---------- */
  const closeDrawer = () => $('#drawerWrap').classList.add('hidden');
  const closeModal = () => $('#modalWrap').classList.add('hidden');

  async function openPanel(url) {
    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
      if (!res.ok) throw new Error(res.status === 403 ? 'You do not have access to that.' : 'Could not load (' + res.status + ')');
      const html = await res.text();
      const tpl = document.createElement('template');
      tpl.innerHTML = html.trim();
      const root = tpl.content.firstElementChild;
      if (root?.dataset.panel === 'modal') {
        $('#modal').replaceChildren(tpl.content);
        $('#modalWrap').classList.remove('hidden');
        $('#modal [autofocus]')?.focus();
      } else {
        closeModal();
        $('#drawer').replaceChildren(tpl.content);
        $('#drawerWrap').classList.remove('hidden');
        // restart slide-in animation
        const d = $('#drawer'); d.classList.remove('drawer-enter'); void d.offsetWidth; d.classList.add('drawer-enter');
      }
    } catch (e) {
      toast(e.message, 'err');
    }
  }
  window.openPanel = openPanel;

  /* ---------- command palette ---------- */
  let cmdResults = [];
  let cmdTimer;
  const openCmd = () => { $('#cmdWrap').classList.remove('hidden'); $('#cmdInput').value = ''; $('#cmdInput').focus(); cmdSearch(''); };
  const closeCmd = () => $('#cmdWrap').classList.add('hidden');

  async function cmdSearch(q) {
    const res = await fetch(CRM.searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
    cmdResults = res.ok ? await res.json() : [];
    $('#cmdResults').innerHTML = cmdResults.length
      ? cmdResults.map((r, i) => `
        <button data-cmd="${i}" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-paper text-left">
          <div class="w-7 h-7 rounded-lg bg-brand-50 text-brand-600 grid place-items-center text-[12px] font-semibold">${esc(r.t[0])}</div>
          <div><div class="text-[13px] font-medium text-ink">${esc(r.t)}</div><div class="text-[11.5px] text-slate-400">${esc(r.s)}</div></div>
        </button>`).join('')
      : `<div class="px-3 py-8 text-center text-slate-400 text-[13px]">No matches for "${esc(q)}"</div>`;
  }
  $('#cmdInput').addEventListener('input', (e) => { clearTimeout(cmdTimer); cmdTimer = setTimeout(() => cmdSearch(e.target.value), 150); });
  $('#cmdInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') $('#cmdResults [data-cmd]')?.click(); });

  /* ---------- sidebar ---------- */
  const toggleSidebar = (open) => {
    $('#sidebar').classList.toggle('-translate-x-full', !open);
    $('#scrim').classList.toggle('hidden', !open);
  };

  /* ---------- global click handling ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target;

    const panel = t.closest('[data-panel-url]');
    if (panel) { e.preventDefault(); openPanel(panel.dataset.panelUrl); return; }

    if (t.closest('[data-close-drawer]')) { closeDrawer(); return; }
    if (t.closest('[data-close-modal]')) { closeModal(); return; }
    if (t.closest('[data-close-cmd]')) { closeCmd(); return; }

    const cmd = t.closest('[data-cmd]');
    if (cmd) {
      const r = cmdResults[+cmd.dataset.cmd]; closeCmd();
      if (r.drawer || r.modal) openPanel(r.drawer || r.modal); else location.href = r.url;
      return;
    }

    if (t.closest('#searchOpen')) { openCmd(); return; }
    if (t.closest('#menuBtn')) { toggleSidebar($('#sidebar').classList.contains('-translate-x-full')); return; }
    if (t.closest('#scrim')) { toggleSidebar(false); return; }

    if (t.closest('#bellBtn')) { $('#bellPanel').classList.toggle('hidden'); return; }
    if (t.closest('#clearBell')) { $('#bellDot')?.classList.add('hidden'); $('#bellPanel').classList.add('hidden'); return; }
    if (!t.closest('#bellPanel')) $('#bellPanel').classList.add('hidden');

    if (t.closest('#userChip')) { $('#userPanel').classList.toggle('hidden'); return; }
    if (!t.closest('#userPanel')) $('#userPanel').classList.add('hidden');

    const fake = t.closest('[data-toast]');
    if (fake) { toast(fake.dataset.toast, fake.dataset.toastType || 'ok'); }
  });

  // Confirm destructive form submissions.
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) { e.preventDefault(); return; }
    // Deferred so the clicked button's name/value is still included in the submission.
    setTimeout(() => e.target.querySelectorAll('button[type=submit],button:not([type])').forEach((b) => { b.disabled = true; b.classList.add('opacity-60'); }), 0);
  });

  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openCmd(); }
    if (e.key === 'Escape') { closeCmd(); closeModal(); closeDrawer(); }
  });

  /* ---------- init ---------- */
  (CRM.toasts || []).forEach(([m, type]) => toast(m, type));
  if (CRM.reopen) openPanel(CRM.reopen);
  const msgArea = $('#msgArea');
  if (msgArea) msgArea.scrollTop = msgArea.scrollHeight;
})();
