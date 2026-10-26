<?php $types = PaymentsController::types(); $methods = PaymentsController::methods(); ?>
<div class="page-head"><div><h1>Record payment</h1><div class="sub">A receipt number is issued automatically when you save.</div></div>
  <a class="btn btn-light border" href="<?= e($t ? url('tenancies/view', ['id' => $t['id']]) : ($sale ? url('sales/view', ['id' => $sale['id']]) : url('payments'))) ?>">Cancel</a></div>
<div class="row g-3"><div class="col-lg-8">
<form method="post" action="<?= e(url('payments/save')) ?>" class="needs-validation" novalidate>
  <?= csrf_field() ?>
  <div class="card mb-3"><div class="card-body"><div class="row g-3">
    <?php if ($sale): ?>
      <input type="hidden" name="sale_id" value="<?= (int)$sale['id'] ?>">
      <div class="col-12"><div class="alert alert-light border mb-0"><div class="small text-muted">Paying for sale</div><a class="fw-semibold" href="<?= e(url('sales/view', ['id' => $sale['id']])) ?>"><?= e($sale['ref']) ?></a> · <?= e($sale['buyer_name']) ?> · <?= e($sale['asset_title']) ?>
        <div class="small mt-1">Price <?= e(money($sale['sale_price'])) ?> · paid <?= e(money($sale['amount_paid'])) ?> · outstanding: <strong><?= e(money(Sale::outstanding($sale))) ?></strong><?= $sched['next_due'] ? ' · next instalment ' . e(fmt_date($sched['next_due'])) . ' (' . e(money($sched['next_amount'])) . ')' : '' ?><?= Money::kobo($sched['overdue']) > 0 ? ' · <span class="text-danger">overdue ' . e(money($sched['overdue'])) . '</span>' : '' ?></div></div></div>
    <?php elseif ($t): ?>
      <input type="hidden" name="tenancy_id" value="<?= (int)$t['id'] ?>">
      <div class="col-12"><div class="alert alert-light border mb-0"><div class="small text-muted">Paying for tenancy</div><a class="fw-semibold" href="<?= e(url('tenancies/view', ['id' => $t['id']])) ?>"><?= e($t['ref']) ?></a> · <?= e($t['tenant_name']) ?> · <?= e($t['property_title']) ?>
        <div class="small mt-1">Outstanding: <strong><?= e(money(Tenancy::outstanding($t))) ?></strong><?= $sched['next_due'] ? ' · next due ' . e(fmt_date($sched['next_due'])) . ' (' . e(money($sched['next_amount'])) . ')' : '' ?><?= Money::kobo($sched['overdue']) > 0 ? ' · <span class="text-danger">overdue ' . e(money($sched['overdue'])) . '</span>' : '' ?></div></div></div>
    <?php else: ?>
      <div class="col-md-6"><label class="form-label" for="payer_search">Received from <span class="text-danger">*</span></label>
        <input class="form-control<?= fe('customer_id') ? ' is-invalid' : '' ?>" id="payer_search" data-lookup="<?= e(url('customers/lookup')) ?>" data-target="#customer_id" value="<?= e(fv('customer_name')) ?>" placeholder="Search by name or phone…" autocomplete="off" required>
        <input type="hidden" name="customer_id" id="customer_id" value="<?= e(fv('customer_id')) ?>"><?php if (fe('customer_id')): ?><div class="invalid-feedback d-block"><?= e(fe('customer_id')) ?></div><?php endif; ?></div>
      <div class="col-md-6"><label class="form-label" for="prop_search">Property <span class="text-muted">(optional)</span></label>
        <input class="form-control" id="prop_search" data-lookup="<?= e(url('properties/lookup')) ?>" data-target="#property_id" value="<?= e(fv('property_name')) ?>" placeholder="Search properties…" autocomplete="off">
        <input type="hidden" name="property_id" id="property_id" value="<?= e(fv('property_id')) ?>"><?php if (fe('property_id')): ?><div class="invalid-feedback d-block"><?= e(fe('property_id')) ?></div><?php endif; ?></div>
      <div class="col-12 form-text mt-0">Rent payments are recorded from the tenancy page so the balance updates. Use this form for other fees such as inspection or agency fees.</div>
    <?php endif; ?>
    <?php if ($sale): ?><div class="col-md-4"><label class="form-label">Payment type</label><input class="form-control" value="<?= e($types[$sale['sale_type'] === 'plot' ? 'land' : 'sale']) ?>" disabled></div>
    <?php else: f_select('payment_type', 'Payment type', $t ? array_diff_key($types, ['sale' => 1, 'land' => 1]) : array_diff_key($types, ['rent' => 1, 'sale' => 1, 'land' => 1]), ['required' => true, 'col' => 'col-md-4']); endif; ?>
    <?php f_input('amount', 'Amount (₦)', ['required' => true, 'inputmode' => 'decimal', 'col' => 'col-md-4', 'autofocus' => true]); ?>
    <?php f_input('payment_date', 'Payment date', ['type' => 'date', 'required' => true, 'max' => today(), 'col' => 'col-md-4']); ?>
    <?php f_select('method', 'Payment method', $methods, ['required' => true, 'col' => 'col-md-4']); ?>
    <?php f_input('reference', 'Reference (transfer / POS / cheque no.)', ['maxlength' => 80, 'col' => 'col-md-4']); ?>
    <?php f_input('received_by', 'Received by', ['required' => true, 'maxlength' => 120, 'col' => 'col-md-4']); ?>
    <?php f_input('purpose', 'Being payment for', ['maxlength' => 255, 'col' => 'col-12', 'help' => 'Optional. Filled in automatically if left empty.']); ?>
    <?php f_input('notes', 'Notes', ['maxlength' => 500, 'col' => 'col-12']); ?>
  </div></div></div>
  <button class="btn btn-primary btn-lg px-4"><i class="bi bi-receipt me-1"></i>Save payment &amp; issue receipt</button>
</form></div>
<div class="col-lg-4"><div class="card"><div class="card-body small text-muted"><div class="fw-semibold text-dark mb-2">Good to know</div>
  <ul class="ps-3 mb-0"><li>Payments cannot be edited. If something was entered wrongly, void it (with a reason) and record it again.</li><li>A payment cannot be larger than the tenancy's outstanding balance.</li><li>The payment date cannot be in the future.</li></ul></div></div></div></div>
