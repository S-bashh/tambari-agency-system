<div class="page-head">
  <div><h1><?= $archived ? 'Archived customers' : 'Customers' ?></h1><div class="sub">One record per person. A person can be a tenant, landlord, buyer, seller and guarantor at the same time.</div></div>
  <div class="d-flex gap-2">
    <?php if (can('customers.delete')): ?><a class="btn btn-light border" href="<?= e(url('customers', $archived ? [] : ['status' => 'archived'])) ?>"><i class="bi bi-archive me-1"></i><?= $archived ? 'Active customers' : 'Archived' ?></a><?php endif; ?>
    <?php if (can('customers.create')): ?><a class="btn btn-primary" href="<?= e(url('customers/create')) ?>"><i class="bi bi-person-plus me-1"></i>Add person</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-body border-bottom">
    <form class="row g-2" method="get">
      <input type="hidden" name="r" value="customers"><?php if ($archived): ?><input type="hidden" name="status" value="archived"><?php endif; ?>
      <div class="col-md-6"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email or reference"></div>
      <div class="col-md-3"><select class="form-select" name="role" data-autosubmit><option value="">All roles</option><?php foreach (list_roles() as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($role, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Search</button><?php if ($q !== '' || $role !== ''): ?><a class="btn btn-light border" href="<?= e(url('customers', $archived ? ['status' => 'archived'] : [])) ?>">Reset</a><?php endif; ?></div>
    </form>
  </div>
  <?php if (!$rows): ?>
    <div class="empty"><i class="bi bi-people"></i><?= ($q !== '' || $role !== '') ? 'No one matches your search.' : ($archived ? 'No archived customers.' : 'No customers yet. Add your first person to get started.') ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack">
    <thead><tr><th>Name</th><th>Roles</th><th>Phone</th><th>Email</th><th>Occupation</th><th>Added</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr class="row-link" data-href="<?= e(url('customers/view', ['id' => $r['id']])) ?>">
        <td class="cell-main"><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(initials($r['full_name'])) ?></span><span><a class="fw-semibold text-decoration-none" href="<?= e(url('customers/view', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a><span class="d-block small text-muted"><?= e($r['ref']) ?></span></span></div></td>
        <td data-label="Roles"><?= role_badges($r['roles']) ?></td>
        <td data-label="Phone"><?= e($r['phone'] ?: '—') ?></td>
        <td data-label="Email"><?= e($r['email'] ?: '—') ?></td>
        <td data-label="Occupation"><?= e($r['occupation'] ?: '—') ?></td>
        <td data-label="Added"><?= e(fmt_date($r['created_at'])) ?></td>
      </tr><?php endforeach; ?></tbody></table></div>
    <?php View::partial('pager', ['pg' => $pg]); ?>
  <?php endif; ?>
</div>
