<div class="page-head"><div><h1>Expiry &amp; overdue tracker</h1><div class="sub">Worked out from tenancy dates and payment schedules every time you open this page. No SMS or internet needed.</div></div>
  <a class="btn btn-light border" href="<?= e(url('tenancies')) ?>">All tenancies</a></div>
<div class="row g-3 mb-3">
<?php foreach ($data as $k => $b): ?>
  <div class="col-6 col-lg"><a class="card stat" href="#b-<?= e($k) ?>"><span class="ico ico-<?= ['danger' => 'rose', 'warning' => 'amber', 'primary' => 'blue'][$b['color']] ?>"><i class="bi bi-calendar-event"></i></span><span><span class="num d-block"><?= count($b['rows']) ?></span><span class="lbl"><?= e($b['label']) ?></span></span></a></div>
<?php endforeach; ?>
</div>
<?php foreach ($data as $k => $b): if (!$b['rows']) continue; ?>
<div class="card mb-3" id="b-<?= e($k) ?>"><div class="card-header"><span><span class="badge badge-soft bg-<?= e($b['color']) ?> me-2"><?= count($b['rows']) ?></span><?= e($b['label']) ?></span></div>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Tenant</th><th>Property</th><th>Ends</th><th class="text-end">Unpaid</th><th></th></tr></thead><tbody>
  <?php foreach ($b['rows'] as $r): ?><tr>
    <td class="cell-main"><a class="fw-semibold text-decoration-none" href="<?= e(url('tenancies/view', ['id' => $r['id']])) ?>"><?= e($r['tenant_name']) ?></a><div class="small text-muted"><?= e($r['ref']) ?><?= $r['tenant_phone'] ? ' · ' . e($r['tenant_phone']) : '' ?></div></td>
    <td data-label="Property"><?= e(str_limit($r['property_title'], 36)) ?></td>
    <td data-label="Ends"><?= e(fmt_date($r['end_date'])) ?><div class="small <?= $r['days_left'] < 0 ? 'text-danger' : 'text-muted' ?>"><?= $r['days_left'] < 0 ? abs((int)$r['days_left']) . ' days ago' : (int)$r['days_left'] . ' days left' ?></div></td>
    <td data-label="Unpaid" class="text-end"><?= e(money(Tenancy::outstanding($r))) ?></td>
    <td class="cell-actions text-end"><?php if ($r['has_renewal']): ?><span class="badge badge-soft bg-info">Renewal drafted</span><?php elseif (can('tenancies.create')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('tenancies/renew', ['id' => $r['id']])) ?>">Renew</a><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endforeach; ?>
<?php if (!array_filter(array_column($data, 'rows'))): ?><div class="card mb-3"><div class="empty"><i class="bi bi-check2-circle"></i>No active tenancies are expiring within 90 days.</div></div><?php endif; ?>

<div class="card" id="overdue"><div class="card-header"><span><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Overdue rent <span class="text-muted fw-normal">(<?= count($overdue) ?>)</span></span></div>
<?php if (!$overdue): ?><div class="empty"><i class="bi bi-emoji-smile"></i>No active tenancy is behind on its payment schedule.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Tenant</th><th>Property</th><th>Overdue since</th><th class="text-end">Overdue amount</th><th></th></tr></thead><tbody>
  <?php foreach ($overdue as $r): ?><tr>
    <td class="cell-main"><a class="fw-semibold text-decoration-none" href="<?= e(url('tenancies/view', ['id' => $r['id']])) ?>"><?= e($r['tenant_name']) ?></a><div class="small text-muted"><?= e($r['ref']) ?></div></td>
    <td data-label="Property"><?= e(str_limit($r['property_title'], 36)) ?></td>
    <td data-label="Since"><?= e(fmt_date($r['overdue_since'])) ?><div class="small text-danger"><?= (int)((time() - strtotime($r['overdue_since'])) / 86400) ?> days</div></td>
    <td data-label="Overdue" class="text-end fw-semibold text-danger"><?= e(money($r['overdue'])) ?></td>
    <td class="cell-actions text-end"><?php if (can('payments.create')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('payments/create', ['tenancy' => $r['id']])) ?>">Record payment</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></div>
