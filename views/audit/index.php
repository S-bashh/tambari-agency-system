<?php $hasF = array_filter($f) !== []; ?>
<div class="page-head"><div><h1>Audit log</h1><div class="sub">A permanent record of who did what. Entries cannot be edited or deleted from the application.</div></div></div>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="audit">
    <div class="col-md-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Search description, user or record ID"></div>
    <div class="col-6 col-md-2"><select class="form-select" name="module"><option value="">All modules</option><?php foreach ($modules as $m): ?><option<?= selected($f['module'], $m) ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="action"><option value="">All actions</option><?php foreach ($actions as $a): ?><option<?= selected($f['action'], $a) ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-md-4"><select class="form-select" name="user"><option value="">All users</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= selected($f['user'], $u['id']) ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-3"><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" title="From"></div>
    <div class="col-6 col-md-3"><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" title="To"></div>
    <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><?php if ($hasF): ?><a class="btn btn-light border" href="<?= e(url('audit')) ?>">Reset</a><?php endif; ?></div></form></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-clipboard-data"></i>No matching entries.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $a): $det = $a['details'] ? json_decode($a['details'], true) : null; ?>
    <tr><td data-label="When" class="text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td><td data-label="User"><?= e($a['username']) ?></td>
      <td data-label="Action"><span class="badge badge-soft bg-<?= in_array($a['action'], ['login_failed', 'archive', 'delete', 'void'], true) ? 'danger' : (in_array($a['action'], ['create', 'upload'], true) ? 'success' : 'primary') ?>"><?= e($a['action']) ?></span></td>
      <td data-label="Module"><?= e($a['module']) ?><?= $a['record_id'] ? ' <span class="text-muted">#' . e($a['record_id']) . '</span>' : '' ?></td>
      <td><?= e($a['description']) ?>
        <?php if (is_array($det)): ?><details class="small text-muted mt-1"><summary>Details</summary><?php foreach ($det as $k => $v): ?><div><strong><?= e($k) ?>:</strong> <?= is_array($v) ? e(implode(' → ', array_map(fn($x) => $x === null || $x === '' ? '(empty)' : (is_array($x) ? implode(', ', $x) : (string)$x), $v))) : e((string)$v) ?></div><?php endforeach; ?></details><?php endif; ?></td>
      <td data-label="IP" class="small text-muted"><?= e($a['ip_address']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
