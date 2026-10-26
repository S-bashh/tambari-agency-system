/* Tenancy form helpers: auto-fill from the chosen property, load the tenant's guarantors, live preview of totals. */
(function () {
  'use strict';
  const $ = s => document.querySelector(s);
  const f = $('#tenancyForm'); if (!f) return;
  const val = n => f.elements[n];
  const kobo = v => Math.round((parseFloat(String(v || '0').replace(/[, ₦]/g, '')) || 0) * 100);
  const money = k => '₦' + (k / 100).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const PERIOD = { monthly: 1, quarterly: 3, biannual: 6, annual: 12 };

  function addMonths(iso, m) {
    const [y, mo, d] = iso.split('-').map(Number);
    const t = mo - 1 + m, ny = y + Math.floor(t / 12), nm = t % 12;
    const last = new Date(ny, nm + 1, 0).getDate();
    return new Date(Date.UTC(ny, nm, Math.min(d, last)));
  }
  function preview() {
    const start = val('start_date').value, dur = parseInt(val('duration_months').value, 10), freq = val('rent_frequency').value;
    const step = PERIOD[freq]; const periods = dur && dur % step === 0 ? dur / step : 0;
    let end = '–';
    if (/^\d{4}-\d{2}-\d{2}$/.test(start) && dur > 0) { const e = addMonths(start, dur); e.setUTCDate(e.getUTCDate() - 1); end = e.toISOString().slice(0, 10).split('-').reverse().join('/'); }
    const rent = kobo(val('rent_amount').value) * periods;
    const fees = ['security_deposit', 'agency_fee', 'legal_fee', 'service_charge', 'other_fees'].reduce((a, n) => a + kobo(val(n).value), 0);
    $('#pvEnd').textContent = end; $('#pvPeriods').textContent = periods || 'invalid duration';
    $('#pvRent').textContent = money(rent); $('#pvTotal').textContent = money(rent + fees);
  }
  f.addEventListener('input', preview); f.addEventListener('change', preview); preview();

  const prop = $('#property_search');
  if (prop) prop.addEventListener('lookup:selected', e => {
    const d = e.detail && e.detail.data; if (!d) return;
    const set = (n, v) => { if (val(n) && v !== null && v !== undefined) val(n).value = v; };
    set('rent_amount', d.rent); set('rent_frequency', d.period === 'monthly' ? 'monthly' : 'annual');
    set('security_deposit', d.deposit); set('agency_fee', d.agency); set('legal_fee', d.legal); set('service_charge', d.service); set('other_fees', d.other); set('other_fees_note', d.other_note);
    preview();
  });

  const tenant = $('#tenant_search'), gsel = $('#guarantor_id');
  if (tenant && gsel) tenant.addEventListener('lookup:selected', e => {
    fetch(gsel.dataset.url + '&tenant=' + encodeURIComponent(e.detail.id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(list => {
        gsel.innerHTML = '<option value="">No guarantor</option>';
        list.forEach(g => { const o = document.createElement('option'); o.value = g.id; o.textContent = g.name; gsel.appendChild(o); });
        if (list.length === 1) gsel.value = list[0].id;
      }).catch(() => { });
  });
})();
