<?php $hasFilter = array_filter($f) !== []; $types = list_property_types(); ?>
<div class="page-head">
  <div><h1><?= $archived ? 'Archived properties' : 'Properties' ?></h1><div class="sub"><?= (int)$pg['total'] ?> record<?= $pg['total'] === 1 ? '' : 's' ?><?= $hasFilter ? ' match your filters' : '' ?></div></div>
  <div class="d-flex gap-2">
    <?php if (can('properties.delete')): ?><a class="btn btn-light border" href="<?= e(url('properties', $archived ? [] : ['archived' => 1])) ?>"><i class="bi bi-archive me-1"></i><?= $archived ? 'Active properties' : 'Archived' ?></a><?php endif; ?>
    <?php if (can('properties.create')): ?><a class="btn btn-primary" href="<?= e(url('properties/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add property</a><?php endif; ?>
  </div>
</div>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get"><input type="hidden" name="r" value="properties"><?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <div class="col-lg-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Search reference, title, address, area or owner"></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="type"><option value="">All types</option><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (list_property_statuses() as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="listing"><option value="">Rent or sale</option><option value="rent"<?= selected($f['listing'], 'rent') ?>>For rent</option><option value="sale"<?= selected($f['listing'], 'sale') ?>>For sale</option></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="area"><option value="">Any area</option><?php foreach ($areas as $a): ?><option<?= selected($f['area'], $a) ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><input class="form-control" name="min" inputmode="decimal" value="<?= e($f['min']) ?>" placeholder="Min price"></div>
    <div class="col-6 col-lg-2"><input class="form-control" name="max" inputmode="decimal" value="<?= e($f['max']) ?>" placeholder="Max price"></div>
    <div class="col-12 col-lg-8 d-flex gap-2 justify-content-lg-end"><button class="btn btn-primary px-4">Filter</button><?php if ($hasFilter): ?><a class="btn btn-light border" href="<?= e(url('properties', $archived ? ['archived' => 1] : [])) ?>">Reset</a><?php endif; ?></div>
    <?php if ($f['media'] === 'none'): ?><input type="hidden" name="media" value="none"><?php endif; ?><?php if ($f['docs'] === 'none'): ?><input type="hidden" name="docs" value="none"><?php endif; ?>
  </form>
  <?php if ($f['media'] === 'none' || $f['docs'] === 'none'): ?><div class="small text-muted mt-2"><i class="bi bi-funnel me-1"></i>Showing only properties with no <?= $f['media'] === 'none' ? 'photos' : 'documents' ?>.</div><?php endif; ?>
</div></div>

<?php if (!$rows): ?>
  <div class="card"><div class="empty"><i class="bi bi-buildings"></i><?= $hasFilter ? 'No properties match these filters.' : 'No properties yet. Add your first listing.' ?></div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($rows as $r):
    $img = $r['thumb'] ? url('media/image', ['f' => $r['thumb'], 'size' => 'thumb']) : null; ?>
  <div class="col-sm-6 col-xl-4">
    <a class="card prop-card" href="<?= e(url('properties/view', ['id' => $r['id']])) ?>">
      <div class="prop-img" <?= $img ? 'style="background-image:url(\'' . e($img) . '\')"' : '' ?>><?= $img ? '' : '<i class="bi bi-house-door"></i>' ?>
        <span class="ref"><?= e($r['ref']) ?></span><span class="position-absolute top-0 start-0 m-2"><?= status_badge($r['status']) ?></span></div>
      <div class="card-body">
        <div class="fw-semibold text-truncate"><?= e($r['title']) ?></div>
        <div class="small text-muted text-truncate mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($r['area']) ?><?= $r['lga'] ? ', ' . e($r['lga']) : '' ?></div>
        <div class="d-flex justify-content-between align-items-end">
          <div><?php if ($r['rent_price'] !== null): ?><div class="price"><?= e(money($r['rent_price'])) ?><span class="small fw-normal text-muted"> / <?= $r['rent_period'] === 'monthly' ? 'month' : 'year' ?></span></div><?php endif; ?>
            <?php if ($r['sale_price'] !== null): ?><div class="price"><?= e(money($r['sale_price'])) ?> <span class="small fw-normal text-muted">sale</span></div><?php endif; ?></div>
          <div class="small text-muted text-end"><?= e($types[$r['property_type']] ?? $r['property_type']) ?><?php if ($r['bedrooms'] !== null): ?><br><?= (int)$r['bedrooms'] ?> bed<?php endif; ?></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<div class="card mt-3"><?php View::partial('pager', ['pg' => $pg]); ?></div>
<?php endif; ?>
