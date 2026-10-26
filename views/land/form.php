<?php $isEdit = (bool)$p; ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit plot' : 'Add plot' ?></h1><div class="sub"><?= $isEdit ? e($p['ref']) : 'Record one plot of land. To add many plots to an estate at once, use the estate page.' ?></div></div><a class="btn btn-light border" href="<?= e($isEdit ? url('land/view', ['id' => $p['id']]) : url('land')) ?>">Cancel</a></div>
<form method="post" action="<?= e(url('land/save')) ?>" class="needs-validation" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
  <div class="card mb-3"><div class="card-body">
    <div class="form-section"><div class="section-title">Identification</div><div class="row g-3">
      <?php f_select('estate_id', 'Estate / project', array_column($estates, 'name', 'id'), ['blank' => 'None (stand-alone plot)', 'col' => 'col-md-4']); ?>
      <?php f_input('block_number', 'Block', ['maxlength' => 30, 'col' => 'col-md-3']); ?>
      <?php f_input('plot_number', 'Plot number', ['required' => true, 'maxlength' => 30, 'col' => 'col-md-3']); ?>
      <?php f_input('size_text', 'Size', ['maxlength' => 60, 'col' => 'col-md-4', 'placeholder' => 'e.g. 50 x 100 ft']); ?>
      <?php f_input('size_sqm', 'Size (square metres)', ['inputmode' => 'decimal', 'col' => 'col-md-4']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Location</div><div class="row g-3">
      <?php f_input('location', 'Address / description of location', ['maxlength' => 255, 'col' => 'col-md-6']); ?>
      <?php f_input('area', 'Area', ['required' => true, 'maxlength' => 100, 'col' => 'col-md-3']); ?>
      <?php f_input('lga', 'Local govt. area', ['maxlength' => 80, 'col' => 'col-md-3']); ?>
      <?php f_select('state', 'State', list_nigerian_states(), ['required' => true, 'col' => 'col-md-4']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Title &amp; price</div><div class="row g-3">
      <?php f_select('title_status', 'Title / document status', list_title_status(), ['required' => true, 'col' => 'col-md-6']); ?>
      <?php f_input('list_price', 'List price (₦)', ['inputmode' => 'decimal', 'col' => 'col-md-6']); ?>
      <div class="col-md-6"><label class="form-label" for="seller_search">Seller / owner <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('seller_id') ? ' is-invalid' : '' ?>" id="seller_search" data-lookup="<?= e(url('customers/lookup')) ?>" data-target="#seller_id" value="<?= e(fv('seller_name')) ?>" placeholder="Search customers…" autocomplete="off" required>
        <input type="hidden" name="seller_id" id="seller_id" value="<?= e(fv('seller_id')) ?>"><?php if (fe('seller_id')): ?><div class="invalid-feedback d-block"><?= e(fe('seller_id')) ?></div><?php endif; ?>
        <div class="form-text">Not listed? <a href="<?= e(url('customers/create', ['role' => 'seller'])) ?>" target="_blank" rel="noopener">Add the person</a> first.</div></div>
      <?php f_textarea('description', 'Description / notes', ['rows' => 3]); ?>
    </div></div>
  </div></div>
  <button class="btn btn-primary btn-lg px-4"><?= $isEdit ? 'Save changes' : 'Add plot' ?></button>
</form>
