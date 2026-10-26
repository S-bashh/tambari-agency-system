<?php $hasF = array_filter($f) !== []; ?>
<div class="page-head"><div><h1>Sales</h1><div class="sub">House and land sales with payment tracking, instalments and commission.</div></div>
  <?php if (can('sales.create')): ?><div class="d-flex gap-2"><a class="btn btn-primary" href="<?= e(url('sales/create', ['type' => 'property'])) ?>"><i class="bi bi-house-door me-1"></i>Sell a house</a><a class="btn btn-light border" href="<?= e(url('sales/create', ['type' => 'plot'])) ?>"><i class="bi bi-map me-1"></i>Sell a plot</a></div><?php endif; ?></div>
<div class="row g-3 mb-3">
  <div class="col-4"><div class="card stat"><span><span class="num d-block fs-5"><?= e(money($tot['value'])) ?></span><span class="lbl">Sales value (excl. cancelled)</span></span></div></div>
  <div class="col-4"><div class="card stat"><span><span class="num d-block fs-5 text-success"><?= e(money($tot['paid'])) ?></span><span class="lbl">Collected</span></span></div></div>
  <div class="col-4"><div class="card stat"><span><span class="num d-block fs-5 text-danger"><?= e(money(Money::fromKobo(max(0, Money::kobo($tot['value']) - Money::kobo($tot['paid']))))) ?></span><span class="lbl">Still to collect</span></span></div></div>
</div>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="sales">
    <div class="col-lg-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Reference, buyer, seller, property or plot"></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="type"><option value="">Houses and land</option><option value="property"<?= selected($f['type'], 'property') ?>>Houses</option><option value="plot"<?= selected($f['type'], 'plot') ?>>Land plots</option></select></div>
    <div class="col-6 col-lg-3"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (Sale::STATUS as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-lg-3"><select class="form-select" name="agent"><option value="">Any agent</option><?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>"<?= selected($f['agent'], $a['id']) ?>><?= e($a['full_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-3"><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" title="Created from"></div>
    <div class="col-6 col-lg-3"><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" title="Created to"></div>
    <div class="col-12 col-lg-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><?php if ($hasF): ?><a class="btn btn-light border" href="<?= e(url('sales')) ?>">Reset</a><?php endif; ?></div></form></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-tag"></i><?= $hasF ? 'No sales match.' : 'No sales yet.' ?></div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Sale</th><th>Buyer</th><th>Property / plot</th><th class="text-end">Price</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $out = Sale::outstanding($r); $label = $r['sale_type'] === 'property' ? $r['property_title'] : Sale::plotLabel($r); ?>
    <tr class="row-link" data-href="<?= e(url('sales/view', ['id' => $r['id']])) ?>">
      <td class="cell-main"><a class="fw-semibold text-decoration-none" href="<?= e(url('sales/view', ['id' => $r['id']])) ?>"><?= e($r['ref']) ?></a><div class="small text-muted"><?= $r['sale_type'] === 'plot' ? 'Land' : 'House' ?></div></td>
      <td data-label="Buyer"><?= e($r['buyer_name']) ?></td><td data-label="For"><?= e(str_limit($label, 40)) ?></td>
      <td data-label="Price" class="text-end"><?= e(money($r['sale_price'])) ?></td>
      <td data-label="Balance" class="text-end"><?= $r['status'] === 'cancelled' ? '<span class="text-muted">n/a</span>' : (Money::kobo($out) > 0 ? '<span class="fw-semibold">' . e(money($out)) . '</span>' : '<span class="text-success">Settled</span>') ?></td>
      <td data-label="Status"><?= Sale::badge($r['status']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
