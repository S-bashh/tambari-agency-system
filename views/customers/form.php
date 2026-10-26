<?php $isEdit = (bool)$c; $rolesList = list_roles(); ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit ' . e($c['full_name']) : 'Add person' ?></h1>
  <div class="sub"><?= $isEdit ? e($c['ref']) : 'This single record can later be used as tenant, landlord, buyer, seller or guarantor.' ?></div></div>
  <a class="btn btn-light border" href="<?= e($isEdit ? url('customers/view', ['id' => $c['id']]) : url('customers')) ?>">Cancel</a></div>

<?php if ($forTenant): ?><div class="alert alert-info py-2"><i class="bi bi-link-45deg me-1"></i>This person will be added as a guarantor for <strong><?= e($forTenantName) ?></strong>.</div><?php endif; ?>
<?php if ($dups): ?>
<div class="alert alert-warning" role="alert">
  <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>This person may already exist</div>
  <ul class="mb-2 ps-3"><?php foreach ($dups as $d): ?><li><a target="_blank" rel="noopener" href="<?= e(url('customers/view', ['id' => $d['id']])) ?>"><?= e($d['full_name']) ?></a> · <?= e($d['ref']) ?> · <?= e($d['phone'] ?: 'no phone') ?><?= $d['email'] ? ' · ' . e($d['email']) : '' ?></li><?php endforeach; ?></ul>
  <div class="small mb-2">Using the existing record keeps their history in one place. You can open it and add a role there instead of creating a second record.</div>
  <button class="btn btn-warning btn-sm" form="personForm" name="confirm_dup" value="1">It is a different person. Save anyway</button>
</div>
<?php endif; ?>

<form id="personForm" method="post" action="<?= e(url('customers/save')) ?>" class="needs-validation" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>"><?php if ($forTenant): ?><input type="hidden" name="for_tenant" value="<?= (int)$forTenant ?>"><?php endif; ?>
  <div class="card mb-3"><div class="card-body">
    <div class="form-section"><div class="section-title">Identity</div><div class="row g-3">
      <?php f_input('full_name', 'Full name', ['required' => true, 'maxlength' => 150, 'col' => 'col-md-6', 'autofocus' => !$isEdit]); ?>
      <?php f_select('gender', 'Gender', list_genders(), ['blank' => 'Select…', 'col' => 'col-md-3']); ?>
      <?php f_input('date_of_birth', 'Date of birth', ['type' => 'date', 'max' => today(), 'col' => 'col-md-3']); ?>
      <?php f_select('marital_status', 'Marital status', list_marital(), ['blank' => 'Select…', 'col' => 'col-md-3']); ?>
      <?php f_input('nationality', 'Nationality', ['maxlength' => 60, 'col' => 'col-md-3']); ?>
      <?php f_input('state_of_origin', 'State of origin', ['maxlength' => 60, 'col' => 'col-md-3']); ?>
      <?php f_input('lga', 'Local govt. area', ['maxlength' => 80, 'col' => 'col-md-3']); ?>
      <?php f_input('religion', 'Religion', ['maxlength' => 40, 'col' => 'col-md-3', 'help' => 'Optional']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Contact</div><div class="row g-3">
      <?php f_input('phone', 'Phone number', ['required' => true, 'type' => 'tel', 'maxlength' => 30, 'inputmode' => 'tel', 'col' => 'col-md-4']); ?>
      <?php f_input('phone_alt', 'Alternative phone', ['type' => 'tel', 'maxlength' => 30, 'inputmode' => 'tel', 'col' => 'col-md-4']); ?>
      <?php f_input('email', 'Email', ['type' => 'email', 'maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_textarea('address', 'Residential address', ['rows' => 2, 'maxlength' => 1000, 'col' => 'col-md-8']); ?>
      <?php f_input('town', 'Town', ['maxlength' => 80, 'col' => 'col-md-4']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Work &amp; identification</div><div class="row g-3">
      <?php f_input('occupation', 'Occupation', ['maxlength' => 100, 'col' => 'col-md-4']); ?>
      <?php f_input('employer_name', 'Employer / business name', ['maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_input('employer_address', 'Employer / business address', ['maxlength' => 255, 'col' => 'col-md-4']); ?>
      <?php f_select('id_type', 'ID type', list_id_types(), ['blank' => 'Select…', 'col' => 'col-md-4']); ?>
      <?php f_input('id_number', 'ID number', ['maxlength' => 60, 'col' => 'col-md-4']); ?>
    </div></div>
    <div class="form-section"><div class="section-title">Emergency contact</div><div class="row g-3">
      <?php f_input('emergency_name', 'Name', ['maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_input('emergency_phone', 'Phone', ['type' => 'tel', 'maxlength' => 30, 'col' => 'col-md-4']); ?>
      <?php f_input('emergency_relationship', 'Relationship', ['maxlength' => 60, 'col' => 'col-md-4']); ?>
    </div></div>
  </div></div>

  <div class="card mb-3"><div class="card-header">Roles <?php if (fe('roles')): ?><span class="text-danger small fw-normal"><?= e(fe('roles')) ?></span><?php endif; ?></div><div class="card-body">
    <div class="row g-2">
      <?php foreach ($rolesList as $k => [$l, $col]): $on = in_array($k, $selRoles, true); ?>
        <div class="col-6 col-md"><label class="role-box d-flex align-items-center gap-2 w-100 <?= $manage[$k] ? '' : 'opacity-50' ?>">
          <input class="form-check-input mt-0" type="checkbox" name="roles[]" value="<?= e($k) ?>" <?= checked($on) ?> <?= $manage[$k] ? '' : 'disabled' ?> <?= in_array($k, ['tenant', 'landlord', 'guarantor'], true) ? 'data-toggle-target="#extra_' . e($k) . '"' : '' ?>>
          <span class="fw-medium"><?= e($l) ?></span></label></div>
      <?php endforeach; ?>
    </div>
    <div class="form-text mt-2">Tick every role that applies. Nothing is duplicated: the same record serves all of them.</div>

    <div id="extra_tenant" class="role-extra"><div class="section-title">Tenant details</div><div class="row g-3">
      <?php f_input('t_spouse_name', 'Spouse name', ['maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_input('t_spouse_employer', 'Spouse employer', ['maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_input('t_office_phone', 'Office phone', ['type' => 'tel', 'maxlength' => 30, 'col' => 'col-md-4']); ?>
      <?php f_input('t_number_of_kids', 'Number of children', ['type' => 'number', 'min' => 0, 'max' => 40, 'col' => 'col-md-4']); ?>
      <?php f_input('t_wedding_anniversary', 'Wedding anniversary', ['type' => 'date', 'max' => today(), 'col' => 'col-md-4']); ?>
      <?php f_textarea('t_notes', 'Tenant notes', ['rows' => 2, 'col' => 'col-12']); ?>
    </div></div>
    <div id="extra_landlord" class="role-extra"><div class="section-title">Landlord payment details</div><div class="row g-3">
      <?php f_input('l_bank_name', 'Bank', ['maxlength' => 100, 'col' => 'col-md-4']); ?>
      <?php f_input('l_account_name', 'Account name', ['maxlength' => 150, 'col' => 'col-md-4']); ?>
      <?php f_input('l_account_number', 'Account number', ['maxlength' => 10, 'inputmode' => 'numeric', 'pattern' => '\d{10}', 'col' => 'col-md-4', 'help' => '10 digits']); ?>
      <?php f_textarea('l_notes', 'Landlord notes', ['rows' => 2, 'col' => 'col-12']); ?>
    </div></div>
    <div id="extra_guarantor" class="role-extra"><div class="section-title">Guarantor details</div><div class="row g-3">
      <?php if ($forTenant): ?>
        <?php f_input('link_relationship', 'Relationship with tenant', ['maxlength' => 80, 'col' => 'col-md-4']); ?>
        <?php f_input('link_years_known', 'How long has the guarantor known the tenant?', ['maxlength' => 40, 'col' => 'col-md-4']); ?>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="link_consented" value="1" id="link_consented"><label class="form-check-label" for="link_consented">Consents to be the tenant's guarantor</label></div></div>
      <?php endif; ?>
      <?php f_textarea('g_notes', 'Guarantor notes', ['rows' => 2, 'col' => 'col-12']); ?>
    </div></div>
  </div></div>

  <div class="card mb-3"><div class="card-body"><div class="row g-3"><?php f_textarea('notes', 'General notes', ['rows' => 3, 'maxlength' => 3000]); ?></div></div></div>
  <div class="d-flex gap-2"><button class="btn btn-primary btn-lg px-4"><?= $isEdit ? 'Save changes' : 'Save person' ?></button><a class="btn btn-light btn-lg border" href="<?= e($isEdit ? url('customers/view', ['id' => $c['id']]) : url('customers')) ?>">Cancel</a></div>
</form>
