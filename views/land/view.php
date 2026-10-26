<?php $kv = function (string $l, ?string $v) { if ($v !== null && $v !== '') echo '<dt>' . e($l) . '</dt><dd>' . $v . '</dd>'; }; $label = Sale::plotLabel($p); ?>
<div class="page-head"><div><div class="d-flex flex-wrap gap-2 align-items-center mb-1"><span class="text-muted small"><?= e($p['ref']) ?></span><?= plot_badge($p['status']) ?><?php if ($p['deleted_at']): ?><span class="badge badge-soft bg-danger">Archived</span><?php endif; ?></div>
  <h1><?= e($label) ?></h1><div class="sub"><i class="bi bi-geo-alt me-1"></i><?= e(trim(($p['location'] ? $p['location'] . ', ' : '') . $p['area'] . ', ' . $p['state'])) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($p['deleted_at'] && can('land.delete')): ?><form method="post" action="<?= e(url('land/restore')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-success">Restore</button></form>
    <?php else: ?>
      <?php if ($p['status'] === 'available' && can('sales.create')): ?><a class="btn btn-primary" href="<?= e(url('sales/create', ['type' => 'plot', 'plot' => $p['id']])) ?>"><i class="bi bi-tag me-1"></i>Sell this plot</a><?php endif; ?>
      <?php if (can('land.create')): ?><a class="btn btn-light border" href="<?= e(url('land/edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
      <?php if (in_array($p['status'], ['available', 'inactive'], true) && can('land.create')): ?><form method="post" action="<?= e(url('land/status')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="status" value="<?= $p['status'] === 'available' ? 'inactive' : 'available' ?>"><button class="btn btn-light border"><?= $p['status'] === 'available' ? 'Mark inactive' : 'Mark available' ?></button></form><?php endif; ?>
      <?php if (can('land.delete') && in_array($p['status'], ['available', 'inactive'], true)): ?><form method="post" action="<?= e(url('land/archive')) ?>" data-confirm="Archive this plot? The record and its documents are kept." data-confirm-title="Archive plot" data-confirm-button="Archive"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-light border text-danger"><i class="bi bi-archive"></i></button></form><?php endif; ?>
    <?php endif; ?></div></div>
<div class="row g-3"><div class="col-lg-7">
  <div class="card mb-3"><div class="card-header">Details</div><div class="card-body"><dl class="kv">
    <?php $kv('Estate', $p['estate_name'] ? ($p['estate_id'] ? '<a href="' . e(url('land/estate-view', ['id' => $p['estate_id']])) . '">' . e($p['estate_name']) . '</a>' : e($p['estate_name'])) : null);
    $kv('Size', $p['size_text'] ? e($p['size_text']) . ($p['size_sqm'] ? ' (' . e(number_format((float)$p['size_sqm'], 2)) . ' sqm)' : '') : ($p['size_sqm'] ? e(number_format((float)$p['size_sqm'], 2)) . ' sqm' : null));
    $kv('Title / documents', e($p['title_status'])); $kv('List price', $p['list_price'] !== null ? '<span class="price fs-5">' . e(money($p['list_price'])) . '</span>' : null);
    $kv('Seller', '<a href="' . e(url('customers/view', ['id' => $seller['id']])) . '">' . e($seller['full_name']) . '</a>'); $kv('Description', $p['description'] ? nl2br(e($p['description'])) : null); ?></dl></div></div>
  <?php if (can('documents.view')): ?><div class="mb-3"><?php View::partial('documents', ['ownerType' => 'plot', 'ownerId' => (int)$p['id'], 'docs' => $docs, 'canUpload' => can('documents.upload') && !$p['deleted_at'], 'canDelete' => can('documents.delete'), 'withContext' => false]); ?></div><?php endif; ?>
</div><div class="col-lg-5">
  <?php if (can('sales.view')): ?><div class="card"><div class="card-header"><i class="bi bi-tag me-2"></i>Sales</div>
    <?php if (!$sales): ?><div class="empty py-4"><i class="bi bi-tag"></i>No sale recorded.</div><?php else: ?>
    <ul class="list-group list-group-flush"><?php foreach ($sales as $sl): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none" href="<?= e(url('sales/view', ['id' => $sl['id']])) ?>"><span class="fw-semibold"><?= e($sl['buyer_name']) ?></span><span class="d-block small text-muted"><?= e($sl['ref']) ?> · <?= e(money($sl['sale_price'])) ?> · paid <?= e(money($sl['amount_paid'])) ?></span></a><?= Sale::badge($sl['status']) ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>
</div></div>
