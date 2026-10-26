<?php $isEdit = (bool)$t; $renew = (bool)$renewOf; ?>
<div class="page-head"><div><h1><?= $renew ? 'Renew tenancy' : ($isEdit ? 'Edit draft tenancy' : 'New tenancy') ?></h1>
  <div class="sub"><?= $renew ? 'Renewing ' . e($renewOf['ref']) . '. The new term starts the day after the old one ends.' : 'Saved as a draft. Record the first payment, then activate it.' ?></div></div>
  <a class="btn btn-light border" href="<?= e($isEdit ? url('tenancies/view', ['id' => $t['id']]) : ($renew ? url('tenancies/view', ['id' => $renewOf['id']]) : url('tenancies'))) ?>">Cancel</a></div>
<form method="post" action="<?= e(url('tenancies/save')) ?>" class="needs-validation" novalidate id="tenancyForm">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>"><?php if ($renew): ?><input type="hidden" name="renewed_from_id" value="<?= (int)$renewOf['id'] ?>"><?php endif; ?>
  <div class="card mb-3"><div class="card-body">
    <div class="form-section"><div class="section-title">Parties &amp; property</div><div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="tenant_search">Tenant <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('tenant_id') ? ' is-invalid' : '' ?>" id="tenant_search" data-lookup="<?= e(url('customers/lookup', ['role' => 'tenant'])) ?>" data-target="#tenant_id" value="<?= e(fv('tenant_name')) ?>" placeholder="Search tenants by name or phone…" autocomplete="off" <?= $renew ? 'readonly' : 'required' ?>>
        <input type="hidden" name="tenant_id" id="tenant_id" value="<?= e(fv('tenant_id')) ?>">
        <?php if (fe('tenant_id')): ?><div class="invalid-feedback d-block"><?= e(fe('tenant_id')) ?></div><?php endif; ?>
        <?php if (!$renew): ?><div class="form-text">Not listed? <a href="<?= e(url('customers/create', ['role' => 'tenant'])) ?>" target="_blank" rel="noopener">Add the tenant</a> first.</div><?php endif; ?></div>
      <div class="col-md-6"><label class="form-label" for="property_search">Property <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('property_id') ? ' is-invalid' : '' ?>" id="property_search" data-lookup="<?= e(url('properties/lookup')) ?>" data-target="#property_id" value="<?= e(fv('property_name')) ?>" placeholder="Search available properties…" autocomplete="off" <?= $renew ? 'readonly' : 'required' ?>>
        <input type="hidden" name="property_id" id="property_id" value="<?= e(fv('property_id')) ?>">
        <?php if (fe('property_id')): ?><div class="invalid-feedback d-block"><?= e(fe('property_id')) ?></div><?php endif; ?>
        <div class="form-text"><?= $renew ? 'Same property as the tenancy being renewed.' : 'Only properties listed for rent that are Available or Reserved. Rent and fees are filled in from the property.' ?></div></div>
      <div class="col-md-6"><label class="form-label" for="guarantor_id">Guarantor</label>
        <select class="form-select<?= fe('guarantor_id') ? ' is-invalid' : '' ?>" id="guarantor_id" name="guarantor_id" data-url="<?= e(url('tenancies/guarantors')) ?>"><option value="">No guarantor</option>
          <?php foreach ($guarantors as $g): ?><option value="<?= (int)$g['id'] ?>"<?= selected(fv('guarantor_id'), $g['id']) ?>><?= e($g['full_name']) ?></option><?php endforeach; ?></select>
        <?php if (fe('guarantor_id')): ?><div class="invalid-feedback d-block"><?= e(fe('guarantor_id')) ?></div><?php endif; ?>
        <div class="form-text">Shows the guarantors linked to the chosen tenant.</div></div>
    </div></div>
    <div class="form-section"><div class="section-title">Term &amp; rent</div><div class="row g-3">
      <?php f_input('start_date', 'Start date', ['type' => 'date', 'required' => true, 'col' => 'col-md-3']); ?>
      <?php f_input('duration_months', 'Duration (months)', ['type' => 'number', 'min' => 1, 'max' => 120, 'required' => true, 'col' => 'col-md-3', 'help' => 'Usually 12']); ?>
      <?php f_select('rent_frequency', 'Rent is paid', Tenancy::FREQ_LABEL, ['required' => true, 'col' => 'col-md-3']); ?>
      <?php f_input('rent_amount', 'Rent per payment (₦)', ['required' => true, 'inputmode' => 'decimal', 'col' => 'col-md-3', 'help' => 'Amount for one payment period']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Other charges (₦)</div><div class="row g-3">
      <?php f_input('security_deposit', 'Security / caution deposit', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('agency_fee', 'Agency fee', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('legal_fee', 'Legal fee', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('service_charge', 'Service charge', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('other_fees', 'Other fees', ['inputmode' => 'decimal', 'col' => 'col-md-3']); ?>
      <?php f_input('other_fees_note', 'What are the other fees for?', ['maxlength' => 150, 'col' => 'col-md-9']); ?>
      <?php f_textarea('notes', 'Notes', ['rows' => 2]); ?>
    </div></div>
    <div class="alert alert-info mt-4 mb-0" id="calcBox"><div class="fw-semibold mb-1"><i class="bi bi-calculator me-1"></i>Preview</div>
      <div class="row g-2 small"><div class="col-6 col-md-3">Ends on<br><strong id="pvEnd">–</strong></div><div class="col-6 col-md-3">Rent payments<br><strong id="pvPeriods">–</strong></div>
        <div class="col-6 col-md-3">Total rent<br><strong id="pvRent">–</strong></div><div class="col-6 col-md-3">Total payable<br><strong id="pvTotal">–</strong></div></div>
      <div class="small mt-1 text-muted">Preview only. The system recalculates every figure on the server when you save.</div></div>
  </div></div>
  <div class="d-flex gap-2"><button class="btn btn-primary btn-lg px-4"><?= $isEdit ? 'Save draft' : ($renew ? 'Create renewal draft' : 'Create draft tenancy') ?></button></div>
</form>
