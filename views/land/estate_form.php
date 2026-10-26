<div class="page-head"><div><h1><?= !empty($e['id']) ? 'Edit estate' : 'Add estate' ?></h1><div class="sub">An estate or project groups many plots. The seller is used for plots created from the estate page.</div></div><a class="btn btn-light border" href="<?= e(!empty($e['id']) ? url('land/estate-view', ['id' => $e['id']]) : url('land/estates')) ?>">Cancel</a></div>
<div class="card" style="max-width:820px"><div class="card-body"><form method="post" action="<?= e(url('land/estate-save')) ?>" class="row g-3 needs-validation" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
  <?php f_input('name', 'Estate / project name', ['required' => true, 'maxlength' => 150, 'col' => 'col-md-8']); ?>
  <?php f_select('title_status', 'Title / document status', list_title_status(), ['blank' => 'Not stated', 'col' => 'col-md-4']); ?>
  <?php f_input('location', 'Location / area', ['maxlength' => 255, 'col' => 'col-md-6']); ?>
  <?php f_input('lga', 'Local govt. area', ['maxlength' => 80, 'col' => 'col-md-3']); ?>
  <?php f_select('state', 'State', list_nigerian_states(), ['required' => true, 'col' => 'col-md-3']); ?>
  <div class="col-md-6"><label class="form-label" for="seller_search">Seller / owner</label>
    <input class="form-control<?= fe('seller_id') ? ' is-invalid' : '' ?>" id="seller_search" data-lookup="<?= e(url('customers/lookup')) ?>" data-target="#seller_id" value="<?= e(fv('seller_name')) ?>" placeholder="Search customers…" autocomplete="off">
    <input type="hidden" name="seller_id" id="seller_id" value="<?= e(fv('seller_id')) ?>"><?php if (fe('seller_id')): ?><div class="invalid-feedback d-block"><?= e(fe('seller_id')) ?></div><?php endif; ?></div>
  <?php f_textarea('description', 'Description', ['rows' => 3]); ?>
  <div class="col-12"><button class="btn btn-primary">Save estate</button></div></form></div></div>
