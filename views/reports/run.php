<?php
$q = fn(array $extra = []) => url('reports/run', ['report' => $key] + array_filter($f, fn($v) => $v !== '' && $v !== 0) + $extra);
$hasFilters = (bool)$filters;
$shown = $ran ? array_slice($result['rows'], 0, 500) : [];
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><div class="sub"><?= e($desc) ?></div></div>
  <div class="d-flex gap-2 flex-wrap"><a class="btn btn-light border" href="<?= e(url('reports')) ?>"><i class="bi bi-arrow-left me-1"></i>All reports</a>
  <?php if ($ran): ?><a class="btn btn-light border" href="<?= e($q(['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv me-1"></i>CSV / Excel</a><a class="btn btn-primary" href="<?= e($q(['export' => 'pdf'])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?></div></div>
<?php if ($hasFilters): ?>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="get"><input type="hidden" name="r" value="reports/run"><input type="hidden" name="report" value="<?= e($key) ?>"><input type="hidden" name="run" value="1">
  <?php if (in_array('date', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label"><?= e($dateLabel ?? 'Date') ?>: from</label><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>"></div><div class="col-6 col-lg-2"><label class="form-label">to</label><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>"></div><?php endif; ?>
  <?php if (in_array('status', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Property status</label><select class="form-select" name="status"><option value="">Any</option><?php foreach (list_property_statuses() as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('type', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Property type</label><select class="form-select" name="type"><option value="">Any</option><?php foreach (list_property_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('area', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Area contains</label><input class="form-control" name="area" value="<?= e($f['area']) ?>"></div><?php endif; ?>
  <?php if (in_array('tstatus', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Tenancy status</label><select class="form-select" name="tstatus"><option value="">Any</option><?php foreach (Tenancy::statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['tstatus'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('sstatus', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Sale status</label><select class="form-select" name="sstatus"><option value="">Any</option><?php foreach (Sale::STATUS as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($f['sstatus'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('ptype', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Payment type</label><select class="form-select" name="ptype"><option value="">Any</option><?php foreach (PaymentsController::types() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['ptype'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('method', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Method</label><select class="form-select" name="method"><option value="">Any</option><?php foreach (PaymentsController::methods() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['method'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('pstatus', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Payment status</label><select class="form-select" name="pstatus"><option value="">Any</option><option value="valid"<?= selected($f['pstatus'], 'valid') ?>>Valid</option><option value="void"<?= selected($f['pstatus'], 'void') ?>>Void</option></select></div><?php endif; ?>
  <?php if (in_array('agent', $filters, true)): ?><div class="col-6 col-lg-2"><label class="form-label">Agent / staff</label><select class="form-select" name="agent"><option value="">Anyone</option><?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>"<?= selected($f['agent'], $a['id']) ?>><?= e($a['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (in_array('property', $filters, true)): ?><div class="col-6 col-lg-3"><label class="form-label">Property contains</label><input class="form-control" name="property" value="<?= e($f['property']) ?>" placeholder="ref, title or address"></div><?php endif; ?>
  <?php if (in_array('customer', $filters, true)): ?><div class="col-6 col-lg-3"><label class="form-label">Customer contains</label><input class="form-control" name="customer" value="<?= e($f['customer']) ?>" placeholder="name or phone"></div><?php endif; ?>
  <div class="col-12 col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Run report</button></div></form></div></div>
<?php endif; ?>
<?php if (!$ran): ?><div class="card"><div class="empty"><i class="bi bi-funnel"></i>Choose any filters you need and press Run report.</div></div>
<?php else: ?>
<div class="card">
  <div class="card-body py-2 border-bottom small text-muted d-flex justify-content-between flex-wrap gap-1"><span><?= count($result['rows']) ?> row<?= count($result['rows']) == 1 ? '' : 's' ?><?= count($result['rows']) > 500 ? ' (first 500 shown, export for all)' : '' ?></span><span>Generated <?= e(date('d/m/Y H:i')) ?></span></div>
  <?php if (!$result['rows']): ?><div class="empty"><i class="bi bi-inbox"></i>No records match these filters.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><?php foreach ($result['cols'] as $c): ?><th class="<?= $c['kind'] === 'text' ? '' : 'text-end' ?>"><?= e($c['label']) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($shown as $r): ?><tr><?php foreach ($result['cols'] as $c): ?><td class="<?= $c['kind'] === 'text' ? '' : 'text-end' ?>"><?= e(Reports::cell($r[$c['key']] ?? '', $c['kind'])) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    <?php if (!empty($result['totals'])): ?><tr class="table-light fw-semibold"><?php foreach ($result['cols'] as $c): ?><td class="<?= $c['kind'] === 'text' ? '' : 'text-end' ?>"><?= isset($result['totals'][$c['key']]) ? e(Reports::cell($result['totals'][$c['key']], $c['kind'])) : '' ?></td><?php endforeach; ?></tr><?php endif; ?>
  </tbody></table></div><?php endif; ?>
  <?php if (!empty($result['note'])): ?><div class="card-body border-top small text-muted"><?= e($result['note']) ?></div><?php endif; ?>
</div>
<?php endif; ?>
