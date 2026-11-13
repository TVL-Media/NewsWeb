/* Newsroom Admin — small behaviours on top of Alpine.js. No browser alert/confirm dialogs are used. */
(function () {
  const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

  // ---------- Toasts ----------
  window.toast = function (msg, type = 'ok') {
    let box = document.querySelector('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
    const t = document.createElement('div');
    t.className = 'toast' + (type === 'err' ? ' err' : '');
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => t.remove(), 4000);
  };

  // ---------- Confirm dialog for destructive forms ----------
  // <form data-confirm="Archive this item?" data-confirm-detail="..." data-confirm-word="DELETE" data-confirm-password data-confirm-reason="optional|required">
  function buildConfirm(form) {
    const ov = document.createElement('div');
    ov.className = 'overlay';
    const word = form.dataset.confirmWord;
    const needPw = form.hasAttribute('data-confirm-password');
    const reason = form.dataset.confirmReason;
    const danger = form.hasAttribute('data-confirm-danger');
    ov.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-h"><h2></h2></div>
        <div class="modal-b">
          <p class="detail muted"></p>
          ${reason ? `<div class="field"><label>Reason ${reason === 'required' ? '<span class="req">*</span>' : '<span class="muted">(optional)</span>'}</label><textarea rows="3" data-f="reason"></textarea></div>` : ''}
          ${word ? `<div class="field"><label>Type <code>${word}</code> to confirm</label><input type="text" data-f="word" autocomplete="off"></div>` : ''}
          ${needPw ? `<div class="field"><label>Your password</label><input type="password" data-f="pw" autocomplete="current-password"><div class="help">Sensitive action: password confirmation is required (BR-12).</div></div>` : ''}
          <div class="err" data-f="err"></div>
        </div>
        <div class="modal-f"><button type="button" class="btn" data-a="cancel">Cancel</button><button type="button" class="btn ${danger ? 'danger' : 'primary'}" data-a="ok">Confirm</button></div>
      </div>`;
    ov.querySelector('h2').textContent = form.dataset.confirm;
    ov.querySelector('.detail').textContent = form.dataset.confirmDetail || '';
    const close = () => ov.remove();
    ov.addEventListener('click', e => { if (e.target === ov) close(); });
    ov.querySelector('[data-a=cancel]').onclick = close;
    ov.querySelector('[data-a=ok]').onclick = () => {
      const err = ov.querySelector('[data-f=err]');
      const r = ov.querySelector('[data-f=reason]');
      const w = ov.querySelector('[data-f=word]');
      const p = ov.querySelector('[data-f=pw]');
      if (reason === 'required' && r && !r.value.trim()) { err.textContent = 'Please give a reason.'; return; }
      if (word && w && w.value.trim() !== word) { err.textContent = `Type ${word} exactly.`; return; }
      if (needPw && p && !p.value) { err.textContent = 'Enter your password.'; return; }
      const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i); };
      if (r) add('reason', r.value);
      if (w) add('confirm_word', w.value);
      if (p) add('confirm_password', p.value);
      form.dataset.confirmed = '1';
      close();
      form.requestSubmit ? form.requestSubmit() : form.submit();
    };
    document.body.appendChild(ov);
    (ov.querySelector('textarea,input') || ov.querySelector('[data-a=ok]')).focus();
    ov.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  }
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.matches('form[data-confirm]') && f.dataset.confirmed !== '1') { e.preventDefault(); buildConfirm(f); }
  }, true);

  // ---------- Character / word counters ----------
  function bindCounter(el) {
    const max = +el.dataset.count; const words = el.dataset.countMode === 'words';
    const out = document.createElement('div'); out.className = 'counter'; el.after(out);
    const upd = () => {
      const n = words ? (el.value.trim() ? el.value.trim().split(/\s+/).length : 0) : el.value.length;
      out.textContent = `${n} / ${max}${words ? ' words' : ''}`; out.classList.toggle('over', n > max);
    };
    el.addEventListener('input', upd); upd();
  }

  // ---------- Sortable lists ----------
  function bindSortable(el) {
    if (!window.Sortable) return;
    Sortable.create(el, {
      handle: el.dataset.handle || '.handle', animation: 150, ghostClass: 'sortable-ghost',
      onEnd() {
        const ids = [...el.children].map(c => c.dataset.id).filter(Boolean);
        el.querySelectorAll('[data-order]').forEach((inp, i) => inp.value = i);
        if (el.dataset.sortUrl) {
          fetch(el.dataset.sortUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }, body: JSON.stringify({ ids }) })
            .then(r => r.ok ? toast('Order saved') : toast('Could not save order', 'err'));
        }
        el.dispatchEvent(new CustomEvent('sorted', { detail: ids }));
      }
    });
  }

  // ---------- Charts ----------
  const RED = '#C8102E', GREY = '#BDB7B0', INK = '#3B3F47';
  function bindChart(el) {
    if (!window.Chart) return;
    Chart.defaults.font.family = "'IBM Plex Sans', system-ui, sans-serif";
    Chart.defaults.color = '#6B7079';
    const cfg = JSON.parse(el.dataset.chart);
    const palette = [RED, '#6B7079', '#245BA8', '#A86400', '#1E7B4E', '#9E0B23', '#BDB7B0', '#16181D'];
    const ds = cfg.datasets.map((d, i) => Object.assign({
      borderColor: d.color || (i === 0 ? RED : GREY), backgroundColor: d.fill || d.color || (i === 0 ? RED : GREY),
      borderWidth: cfg.type === 'line' ? 2 : 0, pointRadius: 0, pointHoverRadius: 4, tension: .25, borderRadius: 2, maxBarThickness: 28,
    }, d));
    if (cfg.type === 'doughnut') ds.forEach(d => { d.backgroundColor = palette; d.borderWidth = 1; d.borderColor = '#fff'; });
    const canvas = document.createElement('canvas'); el.appendChild(canvas);
    new Chart(canvas, {
      type: cfg.type, data: { labels: cfg.labels, datasets: ds },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false, indexAxis: cfg.horizontal ? 'y' : 'x',
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: !!cfg.legend, position: 'bottom', labels: { boxWidth: 10, color: INK, font: { family: 'IBM Plex Sans', size: 12 } } },
          tooltip: { backgroundColor: '#16181D', titleFont: { family: 'IBM Plex Sans' }, bodyFont: { family: 'IBM Plex Sans' }, padding: 8, cornerRadius: 3 } },
        scales: cfg.type === 'doughnut' ? {} : {
          x: { grid: { display: false }, ticks: { color: '#6B7079', font: { family: 'IBM Plex Sans', size: 11 }, maxRotation: 0, autoSkipPadding: 12 }, border: { color: '#E4E2DF' }, stacked: !!cfg.stacked },
          y: { beginAtZero: true, grid: { color: '#EFEDEA' }, ticks: { color: '#6B7079', font: { family: 'IBM Plex Sans', size: 11 }, precision: 0 }, border: { display: false }, stacked: !!cfg.stacked },
        },
      },
    });
  }

  // ---------- Rich text (Quill) ----------
  function bindQuill(el) {
    if (!window.Quill) return;
    const input = document.getElementById(el.dataset.quill);
    const q = new Quill(el, {
      theme: 'snow', placeholder: el.dataset.placeholder || 'Write the story…',
      modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], ['blockquote', { list: 'ordered' }, { list: 'bullet' }], ['image', 'video'], ['clean']] },
    });
    q.root.innerHTML = input.value || '';
    q.on('text-change', () => { input.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML; input.dispatchEvent(new Event('input', { bubbles: true })); });
    // Image button opens the media library instead of base64 embedding.
    q.getModule('toolbar').addHandler('image', () => {
      window.mediaPicker.open({ kind: 'image' }, m => { const r = q.getSelection(true); q.insertEmbed(r.index, 'image', m.url, 'user'); });
    });
  }

  // ---------- Media picker (modal) ----------
  window.mediaPicker = {
    open(opts, cb) {
      const kind = opts.kind || 'image';
      const ov = document.createElement('div'); ov.className = 'overlay';
      ov.innerHTML = `<div class="modal lg"><div class="modal-h"><h2>Select ${kind}</h2><button class="icon-btn" data-a="x" aria-label="Close">✕</button></div>
        <div class="modal-b" style="padding:0">
          <div class="filters"><div class="search"><input type="search" placeholder="Search by name, caption or credit" data-f="q"></div>
            <label class="btn sm primary" style="cursor:pointer">Upload<input type="file" hidden data-f="file" accept="${kind === 'image' ? 'image/*' : kind === 'video' ? 'video/mp4,video/*' : kind === 'audio' ? 'audio/*' : kind === 'caption' ? '.vtt,.srt' : '*/*'}" multiple></label>
            <span class="small muted" data-f="status"></span></div>
          <div class="media-grid" data-f="grid" style="min-height:280px"><div class="empty">Loading…</div></div>
        </div></div>`;
      document.body.appendChild(ov);
      const grid = ov.querySelector('[data-f=grid]');
      const close = () => ov.remove();
      ov.querySelector('[data-a=x]').onclick = close;
      ov.addEventListener('click', e => { if (e.target === ov) close(); });
      const load = (q = '') => fetch(`/admin/media/picker?kind=${kind}&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(items => {
        grid.innerHTML = items.length ? '' : '<div class="empty"><div class="t">Nothing here yet</div>Upload a file to get started.</div>';
        items.forEach(m => {
          const c = document.createElement('div'); c.className = 'mcard';
          c.innerHTML = `<div class="img" style="${m.thumb ? `background-image:url('${m.thumb}')` : ''}">${m.thumb ? '' : m.kind}</div><div class="b"><div class="n trunc"></div><div class="muted">${m.meta}</div></div>`;
          c.querySelector('.n').textContent = m.name;
          c.onclick = () => { cb(m); close(); };
          grid.appendChild(c);
        });
      });
      let t; ov.querySelector('[data-f=q]').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => load(e.target.value), 250); });
      ov.querySelector('[data-f=file]').addEventListener('change', async e => {
        const st = ov.querySelector('[data-f=status]');
        for (const f of e.target.files) {
          st.textContent = `Uploading ${f.name}…`;
          const fd = new FormData(); fd.append('file', f); fd.append('kind', kind);
          const r = await fetch('/admin/media', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: fd });
          if (!r.ok) { const j = await r.json().catch(() => ({})); st.textContent = ''; toast(j.message || 'Upload failed', 'err'); continue; }
        }
        st.textContent = ''; load();
      });
      load();
    }
  };

  // Alpine helper used by media fields:  x-data="mediaField({id:.., url:.., kind:'image'})"
  window.mediaField = (init) => ({
    id: init.id || '', url: init.url || '', name: init.name || '', kind: init.kind || 'image',
    pick() { window.mediaPicker.open({ kind: this.kind }, m => { this.id = m.id; this.url = m.thumb || ''; this.name = m.name; }); },
    clear() { this.id = ''; this.url = ''; this.name = ''; },
  });

  // ---------- Command palette (Ctrl/Cmd+K) ----------
  window.cmdk = () => ({
    open: false, q: '', items: [], i: 0,
    toggle() { this.open = !this.open; if (this.open) { this.q = ''; this.search(); setTimeout(() => this.$refs.q.focus(), 60); } },
    search() {
      fetch('/admin/search?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => { this.items = j; this.i = 0; });
    },
    go() { if (this.items[this.i]) window.location = this.items[this.i].url; },
  });

  function init(root = document) {
    root.querySelectorAll('[data-count]').forEach(bindCounter);
    root.querySelectorAll('[data-sortable]').forEach(bindSortable);
    (document.fonts ? document.fonts.load("12px 'IBM Plex Sans'").then(() => document.fonts.ready) : Promise.resolve()).then(() => root.querySelectorAll('[data-chart]').forEach(bindChart));
    root.querySelectorAll('[data-quill]').forEach(bindQuill);
    document.querySelectorAll('[data-flash]').forEach(el => { toast(el.dataset.flash, el.dataset.type); el.remove(); });
  }
  window.adminInit = init;
  document.addEventListener('DOMContentLoaded', () => init());

  // Unsaved-changes guard for editors: <form data-dirty-guard>
  document.addEventListener('DOMContentLoaded', () => {
    const f = document.querySelector('form[data-dirty-guard]');
    if (!f) return;
    let dirty = false;
    f.addEventListener('input', () => dirty = true);
    f.addEventListener('submit', () => dirty = false);
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  // Keyboard: "/" focuses search, Ctrl/Cmd+S saves editor.
  document.addEventListener('keydown', e => {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
      const s = document.querySelector('.filters .search input, .top-search input'); if (s) { e.preventDefault(); s.focus(); }
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      const b = document.querySelector('[data-save-shortcut]'); if (b) { e.preventDefault(); b.click(); }
    }
  });
})();
