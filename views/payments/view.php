<?php $void = $p['status'] === 'void'; ?>
<div class="page-head"><div><div class="d-flex gap-2 align-items-center mb-1"><span class="text-muted small"><?= e($p['txn_id']) ?></span><?= $void ? '<span class="badge badge-soft bg-danger">Void</span>' : '<span class="badge badge-soft bg-success">Valid</span>' ?></div>
  <h1><?= e(money($p['amount'])) ?></h1><div class="sub"><?= e($types[$p['payment_type']] ?? $p['payment_type']) ?> from <a href="<?= e(url('customers/view', ['id' => $p['customer_id']])) ?>"><?= e($p['customer_name']) ?></a></div></div>
  <div class="d-flex gap-2 flex-wrap"><?php if ($p['receipt_id']): ?><a class="btn btn-primary" href="<?= e(url('receipts/view', ['id' => $p['receipt_id']])) ?>"><i class="bi bi-receipt me-1"></i>Receipt <?= e($p['receipt_no']) ?></a><?php endif; ?>
  <?php if (!$void && can('payments.void')): ?><button class="btn btn-light border text-danger" data-bs-toggle="collapse" data-bs-target="#voidBox"><i class="bi bi-slash-circle me-1"></i>Void</button><?php endif; ?></div></div>
<?php if (!$void && can('payments.void')): ?><div class="collapse mb-3" id="voidBox"><div class="card border-danger"><div class="card-body">
  <form method="post" action="<?= e(url('payments/void')) ?>" class="row g-2 needs-validation" novalidate data-confirm="Void this payment and its receipt? The records are kept but the amount no longer counts towards any balance." data-confirm-title="Void payment" data-confirm-button="Void payment">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <div class="col-md-9"><label class="form-label">Reason for voiding</label><input class="form-control" name="reason" required minlength="5" maxlength="255" placeholder="e.g. Wrong amount entered, cheque bounced"></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100">Void payment</button></div></form></div></div></div><?php endif; ?>
<?php if ($void): ?><div class="alert alert-danger"><strong>Voided</strong> on <?= e(fmt_datetime($p['voided_at'])) ?><?= $p['voider'] ? ' by ' . e($p['voider']) : '' ?>. Reason: <?= e($p['void_reason']) ?></div><?php endif; ?>
<div class="row g-3"><div class="col-lg-7"><div class="card"><div class="card-header">Details</div><div class="card-body"><dl class="kv">
  <dt>Amount in words</dt><dd><?= e(Money::words($p['amount'])) ?></dd>
  <dt>Date</dt><dd><?= e(fmt_date($p['payment_date'])) ?></dd><dt>Method</dt><dd><?= e($methods[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></dd>
  <dt>Being payment for</dt><dd><?= e($p['purpose']) ?></dd>
  <?php if ($p['tenancy_ref']): ?><dt>Tenancy</dt><dd><a href="<?= e(url('tenancies/view', ['id' => $p['tenancy_id']])) ?>"><?= e($p['tenancy_ref']) ?></a></dd><?php endif; ?>
  <?php if ($p['sale_ref']): ?><dt>Sale</dt><dd><a href="<?= e(url('sales/view', ['id' => $p['sale_id']])) ?>"><?= e($p['sale_ref']) ?></a></dd><?php endif; ?>
  <?php if ($p['property_title']): ?><dt>Property</dt><dd><a href="<?= e(url('properties/view', ['id' => $p['property_id']])) ?>"><?= e($p['property_title']) ?></a> (<?= e($p['property_ref']) ?>)</dd><?php endif; ?>
  <dt>Received by</dt><dd><?= e($p['received_by']) ?></dd><dt>Recorded by</dt><dd><?= e($p['recorder'] ?? '–') ?> on <?= e(fmt_datetime($p['created_at'])) ?></dd>
  <?php if ($p['notes']): ?><dt>Notes</dt><dd><?= e($p['notes']) ?></dd><?php endif; ?></dl></div></div></div>
<?php if ($activity): ?><div class="col-lg-5"><div class="card"><div class="card-header">History</div><div class="card-body py-2"><ul class="timeline"><?php foreach ($activity as $a): ?>
  <li><span class="dot"><i class="bi bi-clock-history"></i></span><div><div class="small"><?= e($a['description']) ?></div><div class="text-muted" style="font-size:.72rem"><?= e($a['username']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div></div></li><?php endforeach; ?></ul></div></div></div><?php endif; ?></div>
