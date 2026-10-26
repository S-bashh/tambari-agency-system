<?php $labels = Tenancy::statuses(); ?>
<div class="page-head"><div><h1>Tenancies</h1><div class="sub">Customer, property, rent and term in one place. Expiry and balances are calculated live.</div></div>
  <div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('tenancies/expiry')) ?>"><i class="bi bi-calendar-event me-1"></i>Expiry &amp; overdue</a>
  <?php if (can('tenancies.create')): ?><a class="btn btn-primary" href="<?= e(url('tenancies/create')) ?>"><i class="bi bi-plus-lg me-1"></i>New tenancy</a><?php endif; ?></div></div>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm <?= $st === '' ? 'btn-primary' : 'btn-light border' ?>" href="<?= e(url('tenancies', $q !== '' ? ['q' => $q] : [])) ?>">All <span class="opacity-75">(<?= (int)array_sum($counts) ?>)</span></a>
  <?php foreach ($labels as $k => $l): ?><a class="btn btn-sm <?= $st === $k ? 'btn-primary' : 'btn-light border' ?>" href="<?= e(url('tenancies', ['status' => $k] + ($q !== '' ? ['q' => $q] : []))) ?>"><?= e($l) ?> <span class="opacity-75">(<?= (int)($counts[$k] ?? 0) ?>)</span></a><?php endforeach; ?>
</div>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="tenancies"><?php if ($st !== ''): ?><input type="hidden" name="status" value="<?= e($st) ?>"><?php endif; ?>
    <div class="col-md-9"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search reference, tenant, phone, property"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Search</button><?php if ($q !== ''): ?><a class="btn btn-light border" href="<?= e(url('tenancies', $st !== '' ? ['status' => $st] : [])) ?>">Reset</a><?php endif; ?></div></form></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-house-check"></i><?= ($q !== '' || $st !== '') ? 'No tenancies match.' : 'No tenancies yet. Create one from a tenant and an available property.' ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Tenancy</th><th>Tenant</th><th>Property</th><th>Term</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $out = Tenancy::outstanding($r); ?>
    <tr class="row-link" data-href="<?= e(url('tenancies/view', ['id' => $r['id']])) ?>">
      <td class="cell-main"><a class="fw-semibold text-decoration-none" href="<?= e(url('tenancies/view', ['id' => $r['id']])) ?>"><?= e($r['ref']) ?></a></td>
      <td data-label="Tenant"><?= e($r['tenant_name']) ?></td>
      <td data-label="Property"><?= e(str_limit($r['property_title'], 36)) ?><div class="small text-muted"><?= e($r['property_ref']) ?></div></td>
      <td data-label="Term"><?= e(fmt_date($r['start_date'])) ?> to <?= e(fmt_date($r['end_date'])) ?>
        <?php if (in_array($r['eff'], ['active', 'expiring'], true)): ?><div class="small <?= $r['days_left'] <= 30 ? 'text-danger' : 'text-muted' ?>"><?= (int)$r['days_left'] ?> days left</div><?php elseif ($r['eff'] === 'expired'): ?><div class="small text-danger"><?= abs((int)$r['days_left']) ?> days ago</div><?php endif; ?></td>
      <td data-label="Balance" class="text-end"><?= Money::kobo($out) > 0 ? '<span class="fw-semibold">' . e(money($out)) . '</span>' : '<span class="text-success">Settled</span>' ?><div class="small text-muted">of <?= e(money($r['total_payable'])) ?></div></td>
      <td data-label="Status"><?= Tenancy::badge($r['eff']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
