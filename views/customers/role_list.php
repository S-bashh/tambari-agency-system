<?php [$label, $color] = $meta; $extraLabel = ['tenant' => 'Guarantors', 'landlord' => 'Properties', 'guarantor' => 'Tenants guaranteed'][$role]; ?>
<div class="page-head">
  <div><h1><?= e($label) ?>s</h1><div class="sub">Everyone in the customer database who holds the <?= e(strtolower($label)) ?> role.</div></div>
  <?php if (can('customers.create') && can($route . '.manage')): ?><a class="btn btn-primary" href="<?= e(url('customers/create', ['role' => $role])) ?>"><i class="bi bi-person-plus me-1"></i>Add <?= e(strtolower($label)) ?></a><?php endif; ?>
</div>
<?php if ($guarantorFilter): ?><div class="alert alert-warning py-2 d-flex justify-content-between align-items-center">Showing tenants with no guarantor on file.<a href="<?= e(url($route)) ?>">Clear filter</a></div><?php endif; ?>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="<?= e($route) ?>">
    <div class="col-md-9"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email or reference"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Search</button><?php if ($q !== ''): ?><a class="btn btn-light border" href="<?= e(url($route)) ?>">Reset</a><?php endif; ?></div></form></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-person-badge"></i><?= $q !== '' ? 'No matches.' : 'No ' . e(strtolower($label)) . 's yet.' ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack">
    <thead><tr><th>Name</th><th>Phone</th><th>Occupation / employer</th><th><?= e($extraLabel) ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr class="row-link" data-href="<?= e(url('customers/view', ['id' => $r['id']])) ?>">
        <td class="cell-main"><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(initials($r['full_name'])) ?></span><span><a class="fw-semibold text-decoration-none" href="<?= e(url('customers/view', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a><span class="d-block small text-muted"><?= e($r['ref']) ?></span></span></div></td>
        <td data-label="Phone"><?= e($r['phone'] ?: '—') ?></td>
        <td data-label="Occupation"><?= e(trim(($r['occupation'] ?? '') . ($r['employer_name'] ? ' · ' . $r['employer_name'] : ''), ' ·') ?: '—') ?></td>
        <td data-label="<?= e($extraLabel) ?>"><?php if ($role === 'tenant' && (int)$r['n'] === 0): ?><span class="badge badge-soft bg-warning">None on file</span><?php else: ?><?= (int)$r['n'] ?><?php endif; ?></td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
