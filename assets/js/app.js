/* TEAMS front-end behaviour. No inline scripts are used anywhere (strict CSP). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Mobile navigation
  const toggle = $('#navToggle'), backdrop = $('#backdrop');
  if (toggle) toggle.addEventListener('click', () => document.body.classList.toggle('nav-open'));
  if (backdrop) backdrop.addEventListener('click', () => document.body.classList.remove('nav-open'));

  // Ctrl/Cmd+K focuses search
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { const s = $('#globalSearch'); if (s) { e.preventDefault(); s.focus(); s.select(); } }
  });

  // Toasts from server flash messages
  const wrap = $('#toasts');
  function toast(type, msg) {
    if (!wrap) return;
    const cls = { success: 'text-bg-success', danger: 'text-bg-danger', error: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-primary' }[type] || 'text-bg-primary';
    const el = document.createElement('div');
    el.className = 'toast align-items-center border-0 ' + cls;
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    wrap.appendChild(el);
    new bootstrap.Toast(el, { delay: type === 'danger' || type === 'error' ? 8000 : 4500 }).show();
  }
  window.teamsToast = toast;
  if (wrap) { try { JSON.parse(wrap.dataset.flash || '[]').forEach(f => toast(f[0], f[1])); } catch (e) { } }

  // Confirmation dialogs for any <form data-confirm="...">
  const modalEl = $('#confirmModal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    let pending = null;
    document.addEventListener('submit', e => {
      const f = e.target;
      if (f.dataset && f.dataset.confirm && !f.dataset.confirmed) {
        e.preventDefault();
        pending = f;
        $('#confirmTitle').textContent = f.dataset.confirmTitle || 'Are you sure?';
        $('#confirmBody').textContent = f.dataset.confirm;
        const ok = $('#confirmOk');
        ok.textContent = f.dataset.confirmButton || 'Confirm';
        ok.className = 'btn ' + (f.dataset.confirmClass || 'btn-danger');
        modal.show();
      }
    }, true);
    $('#confirmOk').addEventListener('click', () => {
      if (pending) { pending.dataset.confirmed = '1'; modal.hide(); pending.requestSubmit ? pending.requestSubmit() : pending.submit(); }
    });
  }

  // Client-side validation + loading state on submit
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.classList && f.classList.contains('needs-validation') && !f.checkValidity()) {
      e.preventDefault(); e.stopPropagation(); f.classList.add('was-validated');
      const bad = $(':invalid', f); if (bad) bad.focus();
      return;
    }
    if (e.defaultPrevented) return;
    const btn = e.submitter || $('button[type=submit],button:not([type])', f);
    if (btn && !f.dataset.noLoading) {
      setTimeout(() => { btn.disabled = true; btn.dataset.orig = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Working…'; }, 0);
    }
  });
  window.addEventListener('pageshow', e => { if (e.persisted) $$('button[data-orig]').forEach(b => { b.disabled = false; b.innerHTML = b.dataset.orig; }); });

  // Clickable table rows
  document.addEventListener('click', e => {
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button,form,input,select,label')) window.location = tr.dataset.href;
  });

  // Auto-submit filter selects
  $$('select[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

  // Show/hide blocks controlled by checkboxes: <input data-toggle-target="#id">
  $$('[data-toggle-target]').forEach(cb => {
    const t = $(cb.dataset.toggleTarget); if (!t) return;
    const sync = () => { t.classList.toggle('d-none', !cb.checked); $$('input,select,textarea', t).forEach(i => i.disabled = !cb.checked); };
    cb.addEventListener('change', sync); sync();
  });

  // Lookup (autocomplete) fields: <input data-lookup="url" data-target="#hidden">
  $$('input[data-lookup]').forEach(inp => {
    const target = $(inp.dataset.target);
    const box = document.createElement('div'); box.className = 'lookup-list d-none';
    inp.parentElement.style.position = 'relative'; inp.parentElement.appendChild(box);
    let timer = null, seq = 0, items = {};
    const run = () => {
      const q = inp.value.trim();
      if (q.length < 2) { box.classList.add('d-none'); return; }
      const my = ++seq;
      fetch(inp.dataset.lookup + (inp.dataset.lookup.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json()).then(list => {
          if (my !== seq) return;
          items = {}; list.forEach(i => { items[i.id] = i; });
          box.innerHTML = list.length ? list.map(i => '<button type="button" data-id="' + i.id + '" data-text="' + esc(i.name) + '"><strong>' + esc(i.name) + '</strong><span class="d-block small text-muted">' + esc(i.meta) + '</span></button>').join('') : '<div class="p-3 text-muted small">No matches found.</div>';
          box.classList.remove('d-none');
        }).catch(() => { });
    };
    inp.addEventListener('input', () => { if (target) target.value = ''; clearTimeout(timer); timer = setTimeout(run, 220); });
    box.addEventListener('click', e => {
      const b = e.target.closest('button[data-id]'); if (!b) return;
      if (target) target.value = b.dataset.id; inp.value = b.dataset.text; box.classList.add('d-none');
      inp.dispatchEvent(new CustomEvent('lookup:selected', { detail: items[b.dataset.id] || { id: b.dataset.id } }));
    });
    document.addEventListener('click', e => { if (!box.contains(e.target) && e.target !== inp) box.classList.add('d-none'); });
    inp.form && inp.form.addEventListener('submit', e => {
      if (inp.required && target && !target.value) { e.preventDefault(); inp.classList.add('is-invalid'); inp.focus(); }
    });
  });

  // Property gallery
  const main = $('#galleryMain');
  if (main) {
    $$('.gallery-thumbs img').forEach(t => t.addEventListener('click', () => {
      main.style.backgroundImage = 'url("' + t.dataset.full + '")'; main.dataset.full = t.dataset.full;
      $$('.gallery-thumbs img').forEach(x => x.classList.remove('on')); t.classList.add('on');
    }));
    main.addEventListener('click', () => {
      const img = $('#lightboxImg'); if (img && main.dataset.full) { img.src = main.dataset.full; new bootstrap.Modal($('#lightbox')).show(); }
    });
  }

  // Charts: <canvas data-chart='{"type":"doughnut","labels":[...],"values":[...]}'>
  if (window.Chart) {
    const palette = ['#1f5fbf', '#1b8a57', '#d99a1f', '#c0245a', '#6b7a8d', '#5b8def', '#12263f'];
    $$('canvas[data-chart]').forEach(c => {
      let cfg; try { cfg = JSON.parse(c.dataset.chart); } catch (e) { return; }
      if (cfg.type === 'stacked') {
        new Chart(c, { type: 'bar', data: { labels: cfg.labels, datasets: cfg.datasets.map((d, i) => ({ label: d.label, data: d.data, backgroundColor: ['#1f5fbf', '#c0245a'][i % 2], borderRadius: 4, maxBarThickness: 34 })) },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f6' } } } } });
        return;
      }
      const isPie = cfg.type === 'doughnut';
      new Chart(c, {
        type: cfg.type,
        data: { labels: cfg.labels, datasets: [{ data: cfg.values, label: cfg.label || '', backgroundColor: isPie ? palette : '#1f5fbf', borderRadius: isPie ? 0 : 6, borderWidth: isPie ? 2 : 0, borderColor: '#fff', maxBarThickness: 38 }] },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: isPie ? '64%' : undefined,
          plugins: { legend: { display: isPie, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
          scales: isPie ? {} : { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f6' } }, x: { grid: { display: false } } }
        }
      });
    });
  }

  // Copy-to-clipboard buttons: <button data-copy="text">
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-copy]'); if (!b) return;
    navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => toast('success', 'Copied to clipboard'));
  });

  // Share a PDF using the device share sheet when available, otherwise download it
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-share-pdf]'); if (!b) return;
    const url = b.dataset.sharePdf, name = b.dataset.shareName || 'document.pdf';
    b.disabled = true;
    fetch(url, { credentials: 'same-origin' }).then(r => { if (!r.ok) throw new Error('bad'); return r.blob(); }).then(blob => {
      const file = new File([blob], name, { type: 'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) return navigator.share({ files: [file], title: b.dataset.shareTitle || name });
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove();
      toast('info', 'Sharing is not available on this device, so the PDF was downloaded instead.');
    }).catch(err => { if (!err || err.name !== 'AbortError') toast('danger', 'The PDF could not be shared. Try Download PDF.'); }).finally(() => { b.disabled = false; });
  });
})();
