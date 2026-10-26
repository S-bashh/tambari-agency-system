<?php
$types = list_property_types(); $feat = $images[0] ?? null;
$imgUrl = fn($n, $s = 'full') => url('media/image', ['f' => $n, 'size' => $s]);
$kv = function (string $label, ?string $val) { if ($val === null || $val === '') return; echo '<dt>' . e($label) . '</dt><dd>' . nl2br(e($val)) . '</dd>'; };
$m = fn($v) => $v === null ? null : money($v);
?>
<div class="page-head">
  <div><div class="d-flex flex-wrap gap-2 align-items-center mb-1"><span class="text-muted small"><?= e($p['ref']) ?></span><?= status_badge($p['status']) ?><?php if ($p['deleted_at']): ?><span class="badge badge-soft bg-danger">Archived</span><?php endif; ?></div>
    <h1><?= e($p['title']) ?></h1><div class="sub"><i class="bi bi-geo-alt me-1"></i><?= e($p['address']) ?>, <?= e($p['area']) ?><?= $p['lga'] ? ', ' . e($p['lga']) : '' ?>, <?= e($p['state']) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($p['deleted_at'] && can('properties.delete')): ?>
      <form method="post" action="<?= e(url('properties/restore')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></form>
    <?php else: ?>
      <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('properties/edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
      <?php if (can('properties.delete')): ?><form method="post" action="<?= e(url('properties/archive')) ?>" data-confirm="Archive <?= e($p['ref']) ?>? It disappears from lists but the record, photos and documents are kept." data-confirm-title="Archive property" data-confirm-button="Archive"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-light border text-danger"><i class="bi bi-archive me-1"></i>Archive</button></form><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-body">
      <?php if ($feat): ?>
        <div class="gallery-main" id="galleryMain" data-full="<?= e($imgUrl($feat['stored_name'])) ?>" style="background-image:url('<?= e($imgUrl($feat['stored_name'])) ?>')"></div>
        <?php if (count($images) > 1): ?><div class="gallery-thumbs"><?php foreach ($images as $k => $i): ?><img class="<?= $k === 0 ? 'on' : '' ?>" src="<?= e($imgUrl($i['stored_name'], 'thumb')) ?>" data-full="<?= e($imgUrl($i['stored_name'])) ?>" alt="Photo <?= $k + 1 ?>"><?php endforeach; ?></div><?php endif; ?>
      <?php else: ?><div class="empty"><i class="bi bi-image"></i>No photos yet.</div><?php endif; ?>
    </div></div>

    <div class="card mb-3"><div class="card-header">Description &amp; features</div><div class="card-body">
      <?php if ($p['description']): ?><p><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
      <dl class="kv">
        <?php $kv('Type', ($types[$p['property_type']] ?? $p['property_type']) . ' · ' . (list_categories()[$p['category']] ?? '')); $kv('Listed for', list_listing_for()[$p['listing_for']] ?? null);
        $kv('Bedrooms', $p['bedrooms'] !== null ? (string)$p['bedrooms'] : null); $kv('Bathrooms', $p['bathrooms'] !== null ? (string)$p['bathrooms'] : null); $kv('Toilets', $p['toilets'] !== null ? (string)$p['toilets'] : null);
        $kv('Parking', $p['parking_spaces'] !== null ? (string)$p['parking_spaces'] : null); $kv('Furnishing', list_furnishing()[$p['furnishing']] ?? null); $kv('Water', $p['water_supply']); $kv('Electricity', $p['electricity']);
        $kv('Security', $p['security_features']); $kv('Land size', $p['land_size']); $kv('Other amenities', $p['amenities']); ?>
      </dl>
    </div></div>

    <?php if (can('documents.view')): ?><div class="mb-3"><?php View::partial('documents', ['ownerType' => 'property', 'ownerId' => (int)$p['id'], 'docs' => $docs, 'canUpload' => can('documents.upload') && !$p['deleted_at'], 'canDelete' => can('documents.delete'), 'withContext' => false]); ?></div><?php endif; ?>

    <?php if ($canEdit && !$p['deleted_at']): ?>
    <div class="card"><div class="card-header"><span><i class="bi bi-images me-2"></i>Manage photos</span></div><div class="card-body">
      <form method="post" action="<?= e(url('properties/upload-images')) ?>" enctype="multipart/form-data" class="row g-2 mb-3 needs-validation" novalidate>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div class="col-md-9"><input class="form-control" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required><div class="form-text">JPG, PNG or WebP, up to <?= (int)cfg('max_upload_mb', 8) ?> MB each. Photos are resized automatically.</div></div>
        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button></div>
      </form>
      <?php if ($images): ?><div class="row g-2">
        <?php foreach ($images as $k => $i): ?>
        <div class="col-6 col-md-4"><div class="img-tile">
          <img src="<?= e($imgUrl($i['stored_name'], 'thumb')) ?>" alt="">
          <?php if ($i['is_featured']): ?><span class="badge text-bg-primary feat">Featured</span><?php endif; ?>
          <div class="tools">
            <?php foreach ([['move-image', 'up', 'bi-arrow-left', 'Move earlier', $k > 0], ['move-image', 'down', 'bi-arrow-right', 'Move later', $k < count($images) - 1]] as [$act, $dir, $ic, $tt, $show]): if ($show): ?>
              <form method="post" action="<?= e(url('properties/' . $act)) ?>"><?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="dir" value="<?= $dir ?>"><button class="btn btn-sm btn-light" title="<?= e($tt) ?>"><i class="bi <?= $ic ?>"></i></button></form>
            <?php endif; endforeach; ?>
            <?php if (!$i['is_featured']): ?><form method="post" action="<?= e(url('properties/feature-image')) ?>"><?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-light" title="Set as featured"><i class="bi bi-star"></i></button></form><?php endif; ?>
            <form method="post" action="<?= e(url('properties/delete-image')) ?>" data-confirm="Delete this photo permanently?" data-confirm-title="Delete photo" data-confirm-button="Delete"><?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
          </div></div></div>
        <?php endforeach; ?></div><?php endif; ?>
    </div></div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">Pricing</div><div class="card-body"><dl class="kv">
      <?php if ($p['rent_price'] !== null) { echo '<dt>Rent</dt><dd class="price fs-5">' . e(money($p['rent_price'])) . '<span class="small fw-normal text-muted"> / ' . ($p['rent_period'] === 'monthly' ? 'month' : 'year') . '</span></dd>'; }
      if ($p['sale_price'] !== null) { echo '<dt>Sale price</dt><dd class="price fs-5">' . e(money($p['sale_price'])) . '</dd>'; }
      $kv('Deposit', $m($p['deposit'])); $kv('Agency fee', $m($p['agency_fee'])); $kv('Legal fee', $m($p['legal_fee'])); $kv('Service charge', $m($p['service_charge']));
      $kv($p['other_fees_note'] ?: 'Other fees', $m($p['other_fees'])); ?></dl></div></div>

    <div class="card mb-3"><div class="card-header">Ownership</div><div class="card-body"><dl class="kv">
      <dt>Owner</dt><dd><?php if (can('customers.view')): ?><a href="<?= e(url('customers/view', ['id' => $landlord['id']])) ?>"><?= e($landlord['full_name']) ?></a><?php else: ?><?= e($landlord['full_name']) ?><?php endif; ?><?= $landlord['phone'] ? '<div class="small text-muted">' . e($landlord['phone']) . '</div>' : '' ?></dd>
      <?php $kv('Ownership info', $p['ownership_info']); $kv('Agent', $agent); ?></dl></div></div>

    <?php if (can('tenancies.view')): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-house-check me-2"></i>Tenancies</span><?php if (can('tenancies.create') && in_array($p['status'], ['available', 'reserved'], true) && !$p['deleted_at'] && $p['listing_for'] !== 'sale'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('tenancies/create', ['property' => $p['id']])) ?>">Let this property</a><?php endif; ?></div>
      <?php if (!$tenancies): ?><div class="empty py-4"><i class="bi bi-people"></i>Never let through the system.</div><?php else: ?>
      <ul class="list-group list-group-flush"><?php foreach ($tenancies as $tn): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none" href="<?= e(url('tenancies/view', ['id' => $tn['id']])) ?>"><span class="fw-semibold"><?= e($tn['tenant_name']) ?></span><span class="d-block small text-muted"><?= e($tn['ref']) ?> · to <?= e(fmt_date($tn['end_date'])) ?></span></a><?= Tenancy::badge($tn['eff']) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php endif; ?>

    <?php if (can('sales.view')): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-tag me-2"></i>Sales</span><?php if (can('sales.create') && $p['status'] === 'available' && !$p['deleted_at'] && $p['listing_for'] !== 'rent'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('sales/create', ['type' => 'property', 'property' => $p['id']])) ?>">Sell this property</a><?php endif; ?></div>
      <?php if (!$sales): ?><div class="empty py-4"><i class="bi bi-tag"></i>No sale recorded.</div><?php else: ?>
      <ul class="list-group list-group-flush"><?php foreach ($sales as $sl): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none" href="<?= e(url('sales/view', ['id' => $sl['id']])) ?>"><span class="fw-semibold"><?= e($sl['buyer_name']) ?></span><span class="d-block small text-muted"><?= e($sl['ref']) ?> · <?= e(money($sl['sale_price'])) ?> · paid <?= e(money($sl['amount_paid'])) ?></span></a><?= Sale::badge($sl['status']) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php endif; ?>

    <div class="card mb-3"><div class="card-header">Status workflow</div><div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3"><span class="text-muted small">Current:</span><?= status_badge($p['status']) ?></div>
      <?php if (can('properties.status') && !$p['deleted_at'] && $next): ?>
      <form method="post" action="<?= e(url('properties/status')) ?>" class="row g-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div class="col-12"><select class="form-select" name="status" required><option value="">Change status to…</option><?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e(list_property_statuses()[$s][0]) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><input class="form-control" name="note" maxlength="255" placeholder="Reason or note (optional)"></div>
        <div class="col-12"><button class="btn btn-primary btn-sm">Update status</button></div></form>
      <?php elseif (!$next && !$p['deleted_at']): ?><div class="small text-muted"><?= $p['status'] === 'sold' ? 'Sold properties are historical records and their status is final.' : 'This status changes automatically when a tenancy or sale changes. Use the Tenancies or Sales panel.' ?></div><?php endif; ?>
      <?php if ($history): ?><div class="section-title mt-4">History</div><ul class="timeline"><?php foreach ($history as $h): ?>
        <li><span class="dot"><i class="bi bi-arrow-right-short"></i></span><div><div class="small"><?= $h['from_status'] ? e(list_property_statuses()[$h['from_status']][0] ?? $h['from_status']) . ' → ' : '' ?><strong><?= e(list_property_statuses()[$h['to_status']][0] ?? $h['to_status']) ?></strong><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div>
        <div class="text-muted" style="font-size:.72rem"><?= e($h['full_name'] ?? 'System') ?> · <?= e(fmt_datetime($h['changed_at'])) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div></div>
  </div>
</div>

<div class="modal fade" id="lightbox" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content bg-transparent border-0"><div class="text-end"><button type="button" class="btn-close btn-close-white mb-2" data-bs-dismiss="modal"></button></div><img id="lightboxImg" class="img-fluid rounded" alt=""></div></div></div>
