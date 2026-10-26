<div class="page-head"><div><h1>Settings</h1><div class="sub">Company details appear on the sign-in page and on printed documents.</div></div></div>
<form method="post" action="<?= e(url('settings/save')) ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
<?= csrf_field() ?>
<div class="card mb-3"><div class="card-header">Company information</div><div class="card-body"><div class="row g-3">
  <?php f_input('company_name', 'Company name', ['required' => true, 'maxlength' => 120]); ?>
  <?php f_input('company_reg', 'Registration number', ['maxlength' => 60]); ?>
  <?php f_input('company_tagline', 'Tagline / business description', ['maxlength' => 200, 'col' => 'col-12']); ?>
  <?php f_input('company_address', 'Address', ['required' => true, 'maxlength' => 255, 'col' => 'col-12']); ?>
  <?php f_input('company_phone', 'Phone number(s)', ['required' => true, 'maxlength' => 120, 'help' => 'Separate several numbers with commas.']); ?>
  <?php f_input('company_email', 'Email', ['type' => 'email', 'maxlength' => 150]); ?>
  <?php f_input('company_website', 'Website', ['maxlength' => 150]); ?>
  <div class="col-md-6"><label class="form-label" for="logo">Logo</label>
    <div class="d-flex align-items-center gap-3"><?= brand_mark() ?><input class="form-control<?= fe('logo') ? ' is-invalid' : '' ?>" id="logo" type="file" name="logo" accept="image/png,image/jpeg"></div>
    <?php if (fe('logo')): ?><div class="invalid-feedback d-block"><?= e(fe('logo')) ?></div><?php endif; ?><div class="form-text">PNG or JPG. Leave empty to keep the current logo.</div></div>
</div></div></div>
<div class="card mb-3"><div class="card-header">System</div><div class="card-body"><div class="row g-3">
  <?php f_input('currency_symbol', 'Currency symbol', ['required' => true, 'maxlength' => 4, 'col' => 'col-md-3']); ?>
  <?php f_input('currency_code', 'Currency code', ['required' => true, 'maxlength' => 3, 'col' => 'col-md-3']); ?>
  <?php f_select('date_format', 'Date format', ['d/m/Y' => 'DD/MM/YYYY (' . date('d/m/Y') . ')', 'd-m-Y' => 'DD-MM-YYYY (' . date('d-m-Y') . ')', 'j M Y' => 'D Mon YYYY (' . date('j M Y') . ')', 'Y-m-d' => 'YYYY-MM-DD (' . date('Y-m-d') . ')'], ['required' => true, 'col' => 'col-md-3']); ?>
  <?php f_input('page_size', 'Rows per page', ['type' => 'number', 'min' => 5, 'max' => 100, 'required' => true, 'col' => 'col-md-3']); ?>
  <?php f_input('session_timeout', 'Sign out after (minutes idle)', ['type' => 'number', 'min' => 5, 'max' => 480, 'required' => true, 'col' => 'col-md-4']); ?>
  <?php f_input('prefix_customer', 'Customer reference prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-4', 'help' => 'Applies to new records only. Example: TEA-C-00001']); ?>
  <?php f_input('prefix_property', 'Property reference prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-4', 'help' => 'Applies to new records only. Example: TEA-P-00001']); ?>
</div></div></div>
<div class="card mb-3"><div class="card-header">Numbering &amp; rental rules</div><div class="card-body"><div class="row g-3">
  <?php f_input('prefix_sale', 'Sale reference prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-3', 'help' => 'e.g. TEA-S-00001']); ?>
  <?php f_input('prefix_plot', 'Plot reference prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-3', 'help' => 'e.g. TEA-L-00001']); ?>
  <?php f_input('backup_reminder_days', 'Remind me to back up after (days)', ['type' => 'number', 'min' => 1, 'max' => 90, 'required' => true, 'col' => 'col-md-3']); ?>
  <?php f_input('prefix_tenancy', 'Tenancy reference prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-3', 'help' => 'e.g. TEA-T-00001']); ?>
  <?php f_input('receipt_prefix', 'Receipt number prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-3', 'help' => 'Prints as TEA-2026-000001. The counter restarts each year.']); ?>
  <?php f_input('agreement_prefix', 'Agreement prefix', ['required' => true, 'maxlength' => 12, 'col' => 'col-md-3', 'help' => 'e.g. TEA-AGR-2026-00001']); ?>
  <?php f_input('expiry_alert_days', 'Mark a tenancy "Expiring" within (days)', ['type' => 'number', 'min' => 7, 'max' => 365, 'required' => true, 'col' => 'col-md-3']); ?>
</div></div></div>
<div class="card mb-3"><div class="card-header">Receipts &amp; agreements</div><div class="card-body"><div class="row g-3">
  <?php f_input('receipt_title', 'Receipt heading', ['required' => true, 'maxlength' => 60]); ?>
  <?php f_textarea('receipt_footer', 'Receipt footer (printed at the bottom of every receipt)', ['rows' => 2, 'maxlength' => 500]); ?>
  <?php f_textarea('legal_disclaimer', 'Legal disclaimer (printed at the end of every agreement)', ['rows' => 3, 'maxlength' => 1500]); ?>
  <?php f_textarea('agreement_footer', 'Extra agreement footer (optional)', ['rows' => 2, 'maxlength' => 500]); ?>
  <?php f_textarea('agreement_guarantor_clause', 'Guarantor clause (used when the tenancy has a guarantor)', ['rows' => 3, 'maxlength' => 1500]); ?>
  <div class="col-12 form-text">Guarantor clause placeholders: {{guarantor_name}}, {{guarantor_address}}, {{guarantor_occupation}}. The full agreement wording is edited under Agreement templates.</div>
</div></div></div>
<button class="btn btn-primary btn-lg px-4">Save settings</button>
</form>
<div class="card mt-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2"><div><div class="fw-semibold">Demo data</div><div class="small text-muted">Load or remove clearly marked sample records for trying the system out.</div></div><a class="btn btn-light border" href="<?= e(url('demo')) ?>">Open demo data</a></div></div>
