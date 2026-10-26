<?php $isEdit = (bool)$s; $type = fv('sale_type', 'property'); $locked = $isEdit && Money::kobo($s['amount_paid']) > 0; ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit sale ' . e($s['ref']) : 'New sale' ?></h1><div class="sub"><?= $isEdit ? ($locked ? 'Money has been received, so the buyer and ' . ($type === 'plot' ? 'plot' : 'property') . ' are fixed.' : 'Change the terms of this sale.') : 'Property or plot, buyer, price, deposit and instalments.' ?></div></div>
  <a class="btn btn-light border" href="<?= e($isEdit ? url('sales/view', ['id' => $s['id']]) : url('sales')) ?>">Cancel</a></div>
<form method="post" action="<?= e(url('sales/save')) ?>" class="needs-validation" novalidate id="saleForm">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($s['id'] ?? 0) ?>">
  <div class="card mb-3"><div class="card-body">
    <div class="form-section"><div class="section-title">What is being sold</div><div class="row g-3">
      <?php if (!$isEdit): ?><div class="col-12"><div class="btn-group" role="group"><input type="radio" class="btn-check" name="sale_type" id="st_property" value="property" <?= checked($type === 'property') ?>><label class="btn btn-outline-primary" for="st_property"><i class="bi bi-house-door me-1"></i>House / property</label>
        <input type="radio" class="btn-check" name="sale_type" id="st_plot" value="plot" <?= checked($type === 'plot') ?>><label class="btn btn-outline-primary" for="st_plot"><i class="bi bi-map me-1"></i>Land plot</label></div></div>
      <?php else: ?><input type="hidden" name="sale_type" value="<?= e($type) ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label" for="asset_search"><span id="assetLabel"><?= $type === 'plot' ? 'Plot' : 'Property' ?></span> <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('asset_id') ? ' is-invalid' : '' ?>" id="asset_search" data-lookup="<?= e($type === 'plot' ? url('land/lookup') : url('properties/lookup', ['for' => 'sale'])) ?>" data-lookup-property="<?= e(url('properties/lookup', ['for' => 'sale'])) ?>" data-lookup-plot="<?= e(url('land/lookup')) ?>" data-target="#asset_id"
          value="<?= e(fv('asset_name')) ?>" placeholder="Search available…" autocomplete="off" <?= $locked ? 'readonly' : 'required' ?>>
        <input type="hidden" name="asset_id" id="asset_id" value="<?= e($type === 'plot' ? fv('plot_id') : fv('property_id')) ?>">
        <?php if (fe('asset_id')): ?><div class="invalid-feedback d-block"><?= e(fe('asset_id')) ?></div><?php endif; ?>
        <div class="form-text" id="assetHelp">Only properties listed for sale (or rent or sale) that are Available.</div></div>
      <div class="col-md-6"><label class="form-label" for="buyer_search">Buyer <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('buyer_id') ? ' is-invalid' : '' ?>" id="buyer_search" data-lookup="<?= e(url('customers/lookup')) ?>" data-target="#buyer_id" value="<?= e(fv('buyer_name')) ?>" placeholder="Search customers by name or phone…" autocomplete="off" <?= $locked ? 'readonly' : 'required' ?>>
        <input type="hidden" name="buyer_id" id="buyer_id" value="<?= e(fv('buyer_id')) ?>"><?php if (fe('buyer_id')): ?><div class="invalid-feedback d-block"><?= e(fe('buyer_id')) ?></div><?php endif; ?>
        <div class="form-text">Any customer can be a buyer. <a href="<?= e(url('customers/create', ['role' => 'buyer'])) ?>" target="_blank" rel="noopener">Add a new person</a> first if needed. The seller is the owner of the <span id="sellerWhat"><?= $type === 'plot' ? 'plot' : 'property' ?></span>.</div></div>
    </div></div>
    <div class="form-section"><div class="section-title">Price &amp; terms (₦)</div><div class="row g-3">
      <?php f_input('sale_price', 'Sale price', ['required' => true, 'inputmode' => 'decimal', 'col' => 'col-md-4']); ?>
      <?php f_input('deposit', 'Agreed deposit', ['inputmode' => 'decimal', 'col' => 'col-md-4', 'help' => 'The initial amount the buyer commits to paying']); ?>
      <?php f_input('sale_date', 'Sale / agreement date', ['type' => 'date', 'col' => 'col-md-4', 'help' => 'Optional. Required when you complete the sale']); ?>
      <?php if (!$isEdit): ?><?php f_select('status', 'Start as', ['negotiation' => 'Negotiation (nothing held)', 'reserved' => 'Reserved (hold it for this buyer now)'], ['col' => 'col-md-4']); ?><?php endif; ?>
      <?php f_select('agent_id', 'Responsible agent', array_column($agents, 'full_name', 'id'), ['blank' => 'Unassigned', 'col' => 'col-md-4']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Agent commission</div><div class="row g-3">
      <?php f_input('commission_rate', 'Rate (%)', ['inputmode' => 'decimal', 'col' => 'col-md-4', 'help' => 'If you enter a rate, the amount is calculated for you']); ?>
      <?php f_input('commission_amount', 'or fixed amount (₦)', ['inputmode' => 'decimal', 'col' => 'col-md-4', 'help' => 'Used only when no rate is entered']); ?>
      <div class="col-md-4 d-flex align-items-end"><div class="small text-muted">Commission: <strong id="pvComm">₦0.00</strong></div></div>
    </div></div>
    <div class="form-section"><div class="section-title">Payment schedule (optional)</div>
      <?php if (fe('installments')): ?><div class="alert alert-danger py-2"><?= e(fe('installments')) ?></div><?php endif; ?>
      <div class="small text-muted mb-2">Add the agreed instalments. If you add any, they must add up to exactly the sale price. Leave empty to take payments as they come.</div>
      <div id="instRows"><?php foreach ($inst as $i): ?>
        <div class="row g-2 mb-2 inst-row"><div class="col-5 col-md-3"><input class="form-control" type="date" name="inst_date[]" value="<?= e($i['due_date']) ?>"></div><div class="col-7 col-md-3"><input class="form-control inst-amt" name="inst_amount[]" inputmode="decimal" placeholder="Amount" value="<?= e($i['amount']) ?>"></div>
          <div class="col-9 col-md-5"><input class="form-control" name="inst_label[]" maxlength="80" placeholder="Label (e.g. Second instalment)" value="<?= e($i['label'] ?? '') ?>"></div><div class="col-3 col-md-1"><button type="button" class="btn btn-light border w-100" data-remove-row title="Remove"><i class="bi bi-x-lg"></i></button></div></div>
      <?php endforeach; ?></div>
      <button type="button" class="btn btn-light border btn-sm" id="addInst"><i class="bi bi-plus-lg me-1"></i>Add instalment</button>
      <span class="small ms-3">Total: <strong id="instTotal">₦0.00</strong> of <strong id="instPrice">₦0.00</strong></span>
      <template id="instTpl"><div class="row g-2 mb-2 inst-row"><div class="col-5 col-md-3"><input class="form-control" type="date" name="inst_date[]"></div><div class="col-7 col-md-3"><input class="form-control inst-amt" name="inst_amount[]" inputmode="decimal" placeholder="Amount"></div>
        <div class="col-9 col-md-5"><input class="form-control" name="inst_label[]" maxlength="80" placeholder="Label (e.g. Second instalment)"></div><div class="col-3 col-md-1"><button type="button" class="btn btn-light border w-100" data-remove-row title="Remove"><i class="bi bi-x-lg"></i></button></div></div></template>
    </div>
    <div class="form-section"><div class="row g-3"><?php f_textarea('notes', 'Notes', ['rows' => 2]); ?></div></div>
  </div></div>
  <button class="btn btn-primary btn-lg px-4"><?= $isEdit ? 'Save changes' : 'Create sale' ?></button>
</form>
