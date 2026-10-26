<div class="page-head"><div><h1>Search</h1><div class="sub"><?= $q === '' ? 'Find people, properties and documents by name, phone, reference or address.' : 'Results for “' . e($q) . '”' ?></div></div></div>
<?php if (mb_strlen($q) < 2): ?>
  <div class="card"><div class="empty"><i class="bi bi-search"></i>Type at least 2 characters in the search box above.</div></div>
<?php else:
  $total = array_sum(array_map(fn($g) => $g[5], $groups));
  if (!$total): ?><div class="card"><div class="empty"><i class="bi bi-emoji-neutral"></i>Nothing found for “<?= e($q) ?>”.</div></div><?php endif;
  foreach ($groups as [$label, $icon, $allUrl, $rows, $route, $count]): if (!$rows) continue; ?>
  <div class="card mb-3"><div class="card-header"><span><i class="bi <?= e($icon) ?> me-2"></i><?= e($label) ?> <span class="text-muted fw-normal">(<?= (int)$count ?>)</span></span><?php if ($count > count($rows)): ?><a class="small fw-normal" href="<?= e($allUrl) ?>">See all <?= (int)$count ?></a><?php endif; ?></div>
    <ul class="list-group list-group-flush"><?php foreach ($rows as $r): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none flex-grow-1" href="<?= e(isset($r['owner_type']) ? url(['property' => 'properties/view', 'tenancy' => 'tenancies/view', 'sale' => 'sales/view', 'plot' => 'land/view'][$r['owner_type']] ?? 'customers/view', ['id' => $r['id']]) . '#documents' : url($route, ['id' => $r['id']])) ?>"><span class="fw-semibold d-block"><?= e($r['title']) ?></span><span class="small text-muted"><?= e($r['sub']) ?></span></a><?= $r['roles'] ? role_badges($r['roles']) : '' ?></li>
    <?php endforeach; ?></ul></div>
<?php endforeach; endif; ?>
