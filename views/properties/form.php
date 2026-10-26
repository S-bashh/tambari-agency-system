<?php $isEdit = (bool)$p; ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit property' : 'Add property' ?></h1><div class="sub"><?= $isEdit ? e($p['ref']) : 'New properties start as Available. Photos and documents are added on the next page.' ?></div></div>
  <a class="btn btn-light border" href="<?= e($isEdit ? url('properties/view', ['id' => $p['id']]) : url('properties')) ?>">Cancel</a></div>
<form method="post" action="<?= e(url('properties/save')) ?>" class="needs-validation" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
  <div class="card mb-3"><div class="card-body">
    <div class="form-section"><div class="section-title">Basic information</div><div class="row g-3">
      <?php f_input('title', 'Property title', ['required' => true, 'maxlength' => 180, 'col' => 'col-md-8', 'placeholder' => 'e.g. 3 bedroom flat, Sharada Phase II', 'autofocus' => !$isEdit]); ?>
      <?php f_select('property_type', 'Property type', list_property_types(), ['required' => true, 'blank' => 'Select…', 'col' => 'col-md-4']); ?>
      <?php f_select('category', 'Category', list_categories(), ['required' => true, 'col' => 'col-md-4']); ?>
      <?php f_select('listing_for', 'Listed for', list_listing_for(), ['required' => true, 'col' => 'col-md-4']); ?>
      <?php f_input('land_size', 'Land size (if applicable)', ['maxlength' => 60, 'col' => 'col-md-4', 'placeholder' => 'e.g. 50 x 100 ft']); ?>
      <?php f_input('address', 'Street address', ['required' => true, 'maxlength' => 255, 'col' => 'col-md-6']); ?>
      <?php f_input('area', 'Area / neighbourhood', ['required' => true, 'maxlength' => 100, 'col' => 'col-md-3', 'placeholder' => 'e.g. Sharada']); ?>
      <?php f_input('lga', 'Local govt. area', ['maxlength' => 80, 'col' => 'col-md-3']); ?>
      <?php f_select('state', 'State', list_nigerian_states(), ['required' => true, 'col' => 'col-md-4']); ?>
      <?php f_textarea('description', 'Description', ['rows' => 4, 'maxlength' => 5000]); ?>
    </div></div>

    <div class="form-section"><div class="section-title">Pricing &amp; fees (₦)</div><div class="row g-3">
      <?php f_input('rent_price', 'Rent', ['inputmode' => 'decimal', 'col' => 'col-md-3', 'placeholder' => '0.00']); ?>
      <?php f_select('rent_period', 'Rent period', list_rent_periods(), ['col' => 'col-md-3']); ?>
      <?php f_input('sale_price', 'Sale price', ['inputmode' => 'decimal', 'col' => 'col-md-3', 'placeholder' => '0.00']); ?>
      <?php f_input('deposit', 'Deposit / caution fee', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('agency_fee', 'Agency fee', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('legal_fee', 'Legal fee', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('service_charge', 'Service charge', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('other_fees', 'Other fees', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('other_fees_note', 'What are the other fees for?', ['maxlength' => 150, 'col' => 'col-md-6']); ?>
    </div><div class="form-text mt-2">Rent is required for rental listings and sale price for sale listings. Amounts are in Naira (<?= e(setting('currency_code', 'NGN')) ?>).</div></div>

    <div class="form-section"><div class="section-title">Ownership &amp; management</div><div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="landlord_search">Owner (landlord) <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('landlord_id') ? ' is-invalid' : '' ?>" id="landlord_search" data-lookup="<?= e(url('customers/lookup', ['role' => 'landlord'])) ?>" data-target="#landlord_id" value="<?= e(fv('landlord_name')) ?>" placeholder="Search landlords by name or phone…" autocomplete="off" required>
        <input type="hidden" name="landlord_id" id="landlord_id" value="<?= e(fv('landlord_id')) ?>">
        <?php if (fe('landlord_id')): ?><div class="invalid-feedback d-block"><?= e(fe('landlord_id')) ?></div><?php endif; ?>
        <div class="form-text">Not listed? <a href="<?= e(url('customers/create', ['role' => 'landlord'])) ?>" target="_blank" rel="noopener">Add the landlord</a> first, then search again.</div></div>
      <?php if ($canAssign): ?>
        <div class="col-md-6"><label class="form-label" for="f_agent_id">Responsible agent</label><select class="form-select" id="f_agent_id" name="agent_id"><option value="">Unassigned</option>
          <?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>"<?= selected(fv('agent_id'), $a['id']) ?>><?= e($a['full_name']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <?php f_input('ownership_info', 'Ownership information', ['maxlength' => 255, 'col' => 'col-12', 'placeholder' => 'e.g. Certificate of Occupancy No., family property, power of attorney…']); ?>
    </div></div>

    <div class="form-section"><div class="section-title">Features</div><div class="row g-3">
      <?php f_input('bedrooms', 'Bedrooms', ['type' => 'number', 'min' => 0, 'max' => 100, 'col' => 'col-6 col-md-3']); ?>
      <?php f_input('bathrooms', 'Bathrooms', ['type' => 'number', 'min' => 0, 'max' => 100, 'col' => 'col-6 col-md-3']); ?>
      <?php f_input('toilets', 'Toilets', ['type' => 'number', 'min' => 0, 'max' => 100, 'col' => 'col-6 col-md-3']); ?>
      <?php f_input('parking_spaces', 'Parking spaces', ['type' => 'number', 'min' => 0, 'max' => 100, 'col' => 'col-6 col-md-3']); ?>
      <?php f_select('furnishing', 'Furnishing', list_furnishing(), ['col' => 'col-md-3']); ?>
      <?php f_select('water_supply', 'Water supply', list_water(), ['blank' => 'Not stated', 'col' => 'col-md-3']); ?>
      <?php f_select('electricity', 'Electricity', list_electricity(), ['blank' => 'Not stated', 'col' => 'col-md-3']); ?>
      <?php f_input('security_features', 'Security', ['maxlength' => 150, 'col' => 'col-md-3', 'placeholder' => 'Gated, guard, CCTV…']); ?>
      <?php f_textarea('amenities', 'Other amenities', ['rows' => 2, 'maxlength' => 2000]); ?>
    </div></div>
  </div></div>
  <div class="d-flex gap-2"><button class="btn btn-primary btn-lg px-4"><?= $isEdit ? 'Save changes' : 'Add property' ?></button><a class="btn btn-light btn-lg border" href="<?= e($isEdit ? url('properties/view', ['id' => $p['id']]) : url('properties')) ?>">Cancel</a></div>
</form>
