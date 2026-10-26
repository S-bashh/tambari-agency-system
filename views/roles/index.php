<?php $byModule = []; foreach ($perms as $p) $byModule[$p['module']][] = $p; ?>
<div class="page-head"><div><h1>Roles &amp; permissions</h1><div class="sub">Controls what each role can do. Enforced on the server for every request, not just hidden in the menu.</div></div></div>
<form method="post" action="<?= e(url('roles/save')) ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table perm-grid align-middle">
  <thead><tr><th style="min-width:260px">Permission</th><?php foreach ($roles as $r): ?><th><?= e($r['name']) ?><div class="fw-normal text-muted text-lowercase" style="font-size:.68rem;letter-spacing:0"><?= (int)($counts[$r['id']] ?? 0) ?> active user<?= ($counts[$r['id']] ?? 0) == 1 ? '' : 's' ?></div></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($byModule as $mod => $list): ?>
    <tr class="table-light"><td colspan="<?= count($roles) + 1 ?>" class="fw-semibold small text-uppercase text-muted"><?= e($mod) ?></td></tr>
    <?php foreach ($list as $p): ?><tr><td><?= e($p['label']) ?><div class="small text-muted"><?= e($p['perm_key']) ?></div></td>
      <?php foreach ($roles as $r): $isAdmin = $r['slug'] === 'admin'; ?>
        <td><input class="form-check-input" type="checkbox" <?= $isAdmin ? 'checked disabled title="Administrators always have every permission"' : 'name="perm[' . (int)$r['id'] . '][]" value="' . (int)$p['id'] . '"' . checked(!empty($grants[$r['id']][$p['id']])) ?>></td>
      <?php endforeach; ?></tr><?php endforeach; ?>
  <?php endforeach; ?></tbody></table></div></div>
<div class="mt-3"><button class="btn btn-primary btn-lg px-4">Save permissions</button></div></form>
