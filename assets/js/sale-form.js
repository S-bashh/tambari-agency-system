/* Sale form: switch between house / plot lookup, live commission and instalment totals. */
(function () {
  'use strict';
  const f = document.getElementById('saleForm'); if (!f) return;
  const $ = s => f.querySelector(s);
  const kobo = v => Math.round((parseFloat(String(v || '0').replace(/[, ₦]/g, '')) || 0) * 100);
  const money = k => '₦' + (k / 100).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const asset = $('#asset_search'), assetId = $('#asset_id');

  function setType(t) {
    asset.dataset.lookup = t === 'plot' ? asset.dataset.lookupPlot : asset.dataset.lookupProperty;
    $('#assetLabel').textContent = t === 'plot' ? 'Plot' : 'Property';
    $('#sellerWhat').textContent = t === 'plot' ? 'plot' : 'property';
    $('#assetHelp').textContent = t === 'plot' ? 'Only plots that are Available.' : 'Only properties listed for sale (or rent or sale) that are Available.';
  }
  f.querySelectorAll('input[name=sale_type]').forEach(r => r.addEventListener('change', () => { if (!r.checked) return; asset.value = ''; assetId.value = ''; setType(r.value); }));
  const checkedType = f.querySelector('input[name=sale_type]:checked'); if (checkedType) setType(checkedType.value);

  asset.addEventListener('lookup:selected', e => { const d = e.detail && e.detail.data; if (d && d.price && !f.elements.sale_price.value) { f.elements.sale_price.value = d.price; update(); } });

  function update() {
    const price = kobo(f.elements.sale_price.value);
    const rate = parseFloat(f.elements.commission_rate.value);
    const comm = f.elements.commission_rate.value.trim() !== '' && !isNaN(rate) ? Math.round(price * rate / 100) : kobo(f.elements.commission_amount.value);
    $('#pvComm').textContent = money(comm);
    f.elements.commission_amount.disabled = f.elements.commission_rate.value.trim() !== '';
    let sum = 0; f.querySelectorAll('.inst-amt').forEach(i => { sum += kobo(i.value); });
    $('#instTotal').textContent = money(sum); $('#instPrice').textContent = money(price);
    $('#instTotal').className = sum === 0 || sum === price ? '' : 'text-danger';
  }
  f.addEventListener('input', update); update();

  $('#addInst').addEventListener('click', () => { $('#instRows').appendChild(document.getElementById('instTpl').content.cloneNode(true)); });
  f.addEventListener('click', e => { const b = e.target.closest('[data-remove-row]'); if (b) { b.closest('.inst-row').remove(); update(); } });
})();
