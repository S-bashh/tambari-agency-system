<?php
$pct = Money::kobo($t['total_payable']) > 0 ? min(100, (int)round(Money::kobo($t['amount_paid']) / Money::kobo($t['total_payable']) * 100)) : 0;
$totalDays = max(1, (int)((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
$elapsed = max(0, min($totalDays, (int)((time() - strtotime($t['start_date'])) / 86400)));
$canPay = can('payments.create') && in_array($t['status'], ['draft', 'active'], true) && Money::kobo($outstanding) > 0;
$kv = function (string $l, ?string $v) { if ($v !== null && $v !== '') echo '<dt>' . e($l) . '</dt><dd>' . $v . '</dd>'; };
?>
<div class="page-head">
  <div><div class="d-flex flex-wrap gap-2 align-items-center mb-1"><span class="text-muted small"><?= e($t['ref']) ?></span><?= Tenancy::badge($eff) ?>
      <?php if ($from): ?><a class="small" href="<?= e(url('tenancies/view', ['id' => $from['id']])) ?>">renews <?= e($from['ref']) ?></a><?php endif; ?>
      <?php if ($to): ?><a class="small" href="<?= e(url('tenancies/view', ['id' => $to['id']])) ?>">renewed by <?= e($to['ref']) ?></a><?php endif; ?></div>
    <h1><?= e($t['tenant_name']) ?></h1><div class="sub"><a href="<?= e(url('properties/view', ['id' => $t['property_id']])) ?>"><?= e($t['property_title']) ?></a> · <?= e($t['property_ref']) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canPay): ?><a class="btn btn-primary" href="<?= e(url('payments/create', ['tenancy' => $t['id']])) ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</a><?php endif; ?>
    <?php if ($t['status'] === 'draft' && can('tenancies.activate')): ?>
      <form method="post" action="<?= e(url('tenancies/activate')) ?>" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <?php if (Money::kobo($t['amount_paid']) <= 0): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="allow_unpaid" value="1" id="aunp"><label class="form-check-label" for="aunp">Activate without payment</label></div><?php endif; ?>
        <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Activate</button></form>
    <?php endif; ?>
    <?php if ($t['status'] === 'active' && can('tenancies.create') && !$to): ?><a class="btn btn-light border" href="<?= e(url('tenancies/renew', ['id' => $t['id']])) ?>"><i class="bi bi-arrow-repeat me-1"></i>Renew</a><?php endif; ?>
    <?php if (can('agreements.generate') && $t['status'] !== 'terminated'): ?><a class="btn btn-light border" href="<?= e(url('agreements/generate', ['tenancy' => $t['id']])) ?>"><i class="bi bi-file-earmark-text me-1"></i>Agreement</a><?php endif; ?>
    <?php if ($t['status'] === 'draft' && can('tenancies.edit')): ?><a class="btn btn-light border" href="<?= e(url('tenancies/edit', ['id' => $t['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
      <form method="post" action="<?= e(url('tenancies/cancel-draft')) ?>" data-confirm="Cancel this draft tenancy? Nothing is lost from the audit log." data-confirm-title="Cancel draft" data-confirm-button="Cancel draft"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-light border text-danger">Cancel draft</button></form><?php endif; ?>
    <?php if ($t['status'] === 'active' && can('tenancies.terminate')): ?><button class="btn btn-light border text-danger" data-bs-toggle="collapse" data-bs-target="#termBox"><i class="bi bi-x-octagon me-1"></i>Terminate</button><?php endif; ?>
  </div>
</div>

<?php if ($t['status'] === 'active' && can('tenancies.terminate')): ?>
<div class="collapse mb-3" id="termBox"><div class="card border-danger"><div class="card-body">
  <form method="post" action="<?= e(url('tenancies/terminate')) ?>" class="row g-2 needs-validation" novalidate data-confirm="Terminate <?= e($t['ref']) ?>? The property becomes Available again. This cannot be undone." data-confirm-title="Terminate tenancy" data-confirm-button="Terminate">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <div class="col-md-9"><label class="form-label">Reason for terminating or closing this tenancy</label><input class="form-control" name="reason" required minlength="5" maxlength="255" placeholder="e.g. Tenant vacated, term ended and not renewed"></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100">Terminate tenancy</button></div></form>
</div></div></div>
<?php endif; ?>
<?php if ($eff === 'expired'): ?><div class="alert alert-danger py-2"><i class="bi bi-calendar-x me-1"></i>This tenancy ended <?= abs($days) ?> days ago. Renew it, or terminate it to free the property.</div>
<?php elseif ($eff === 'expiring'): ?><div class="alert alert-warning py-2"><i class="bi bi-alarm me-1"></i>Expires in <?= (int)$days ?> days (<?= e(fmt_date($t['end_date'])) ?>).</div><?php endif; ?>
<?php if (Money::kobo($sched['overdue']) > 0 && $t['status'] === 'active'): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-1"></i>Overdue: <strong><?= e(money($sched['overdue'])) ?></strong> since <?= e(fmt_date($sched['overdue_since'])) ?>.</div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Financial summary</div><div class="card-body">
      <div class="fin3 mb-3">
        <div><div class="text-muted small">Total payable</div><div class="fin-num"><?= e(money($t['total_payable'])) ?></div></div>
        <div><div class="text-muted small">Paid</div><div class="fin-num text-success"><?= e(money($t['amount_paid'])) ?></div></div>
        <div><div class="text-muted small">Outstanding</div><div class="fin-num <?= Money::kobo($outstanding) > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($outstanding)) ?></div></div></div>
      <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
      <dl class="kv">
        <?php $kv('Rent', e(money($t['rent_amount'])) . ' ' . e(Tenancy::FREQ_TEXT[$t['rent_frequency']]) . ' (' . Tenancy::periods((int)$t['duration_months'], $t['rent_frequency']) . ' payment' . (Tenancy::periods((int)$t['duration_months'], $t['rent_frequency']) > 1 ? 's' : '') . ')');
        foreach (['security_deposit' => 'Security deposit', 'agency_fee' => 'Agency fee', 'legal_fee' => 'Legal fee', 'service_charge' => 'Service charge'] as $k => $l) if (Money::kobo($t[$k]) > 0) $kv($l, e(money($t[$k])));
        if (Money::kobo($t['other_fees']) > 0) $kv($t['other_fees_note'] ?: 'Other fees', e(money($t['other_fees']))); ?>
      </dl></div></div>

    <div class="card mb-3"><div class="card-header">Payment schedule</div><div class="table-responsive"><table class="table table-stack align-middle mb-0"><thead><tr><th>Due</th><th>Covers</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($sched['rows'] as $i => $r): $col = ['paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', 'upcoming' => 'secondary'][$r['status']]; ?>
        <tr><td class="cell-main"><?= e(fmt_date($r['due'])) ?></td><td data-label="Covers"><?= e($r['label']) ?> #<?= $i + 1 ?></td><td data-label="Amount" class="text-end"><?= e(money($r['amount'])) ?></td>
          <td data-label="Paid" class="text-end"><?= e(money($r['paid'])) ?></td><td data-label="Status"><span class="badge badge-soft bg-<?= $col ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr>
      <?php endforeach; ?></tbody></table></div>
      <div class="card-body border-top small text-muted">Payments are applied to the oldest unpaid instalment first. Next payment due: <strong><?= $sched['next_due'] ? e(fmt_date($sched['next_due'])) . ' (' . e(money($sched['next_amount'])) . ')' : 'none, fully paid' ?></strong></div></div>

    <?php if (can('payments.view')): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-cash-coin me-2"></i>Payments &amp; receipts</span><?php if ($canPay): ?><a class="btn btn-sm btn-primary" href="<?= e(url('payments/create', ['tenancy' => $t['id']])) ?>">Record payment</a><?php endif; ?></div>
      <?php if (!$payments): ?><div class="empty"><i class="bi bi-receipt"></i>No payments recorded yet.</div><?php else: ?>
      <div class="table-responsive"><table class="table table-hover table-stack align-middle mb-0"><thead><tr><th>Date</th><th>Receipt</th><th>For</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
      <?php foreach ($payments as $p): $void = $p['status'] === 'void'; ?>
        <tr class="<?= $void ? 'opacity-50' : '' ?>"><td class="cell-main"><?= e(fmt_date($p['payment_date'])) ?></td>
          <td data-label="Receipt"><a href="<?= e(url('payments/view', ['id' => $p['id']])) ?>"><?= e($p['receipt_no'] ?: $p['txn_id']) ?></a><?= $void ? ' <span class="badge badge-soft bg-danger">Void</span>' : '' ?></td>
          <td data-label="For"><?= e(PaymentsController::types()[$p['payment_type']] ?? $p['payment_type']) ?><div class="small text-muted"><?= e(PaymentsController::methods()[$p['method']] ?? '') ?></div></td>
          <td data-label="Amount" class="text-end <?= $void ? 'text-decoration-line-through' : '' ?>"><?= e(money($p['amount'])) ?></td>
          <td class="cell-actions text-end text-nowrap"><?php if ($p['receipt_id']): ?><a class="btn btn-sm btn-light" href="<?= e(url('receipts/view', ['id' => $p['receipt_id']])) ?>" title="Receipt"><i class="bi bi-receipt"></i></a><a class="btn btn-sm btn-light" href="<?= e(url('receipts/pdf', ['id' => $p['receipt_id']])) ?>" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <?php endif; ?>

    <?php if (can('documents.view')): ?><div class="mb-3"><?php View::partial('documents', ['ownerType' => 'tenancy', 'ownerId' => (int)$t['id'], 'docs' => $docs, 'canUpload' => can('documents.upload') && $t['status'] !== 'terminated', 'canDelete' => can('documents.delete'), 'withContext' => false]); ?></div><?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">Term</div><div class="card-body">
      <dl class="kv mb-3"><dt>Starts</dt><dd><?= e(fmt_date($t['start_date'])) ?></dd><dt>Ends</dt><dd><?= e(fmt_date($t['end_date'])) ?></dd><dt>Duration</dt><dd><?= e(Agreement::durationText((int)$t['duration_months'])) ?></dd>
        <?php if (in_array($t['status'], ['active'], true)): ?><dt>Days remaining</dt><dd class="<?= $days < 0 ? 'text-danger' : ($days <= 30 ? 'text-warning fw-semibold' : '') ?>"><?= $days < 0 ? 'Expired ' . abs($days) . ' days ago' : (int)$days ?></dd><?php endif; ?>
        <?php if ($t['terminated_at']): ?><dt>Terminated</dt><dd><?= e(fmt_date($t['terminated_at'])) ?><div class="small text-muted"><?= e($t['termination_reason']) ?></div></dd><?php endif; ?></dl>
      <?php if ($t['status'] === 'active'): ?><div class="progress" style="height:6px"><div class="progress-bar <?= $days < 0 ? 'bg-danger' : ($days <= 30 ? 'bg-warning' : 'bg-primary') ?>" style="width:<?= (int)round($elapsed / $totalDays * 100) ?>%"></div></div><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">Parties</div><div class="card-body"><dl class="kv">
      <dt>Tenant</dt><dd><a href="<?= e(url('customers/view', ['id' => $t['tenant_id']])) ?>"><?= e($t['tenant_name']) ?></a><?= $t['tenant_phone'] ? '<div class="small text-muted">' . e($t['tenant_phone']) . '</div>' : '' ?></dd>
      <dt>Landlord</dt><dd><a href="<?= e(url('customers/view', ['id' => $t['landlord_id']])) ?>"><?= e($t['landlord_name']) ?></a></dd>
      <dt>Guarantor</dt><dd><?= $t['guarantor_id'] ? '<a href="' . e(url('customers/view', ['id' => $t['guarantor_id']])) . '">' . e($t['guarantor_name']) . '</a>' : '<span class="text-muted">None</span>' ?></dd>
      <?php $kv('Notes', $t['notes'] ? nl2br(e($t['notes'])) : null); ?></dl></div></div>

    <?php if (can('agreements.view')): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-file-earmark-text me-2"></i>Agreements</span><?php if (can('agreements.generate') && $t['status'] !== 'terminated'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('agreements/generate', ['tenancy' => $t['id']])) ?>">Generate</a><?php endif; ?></div>
      <?php if (!$agreements): ?><div class="empty py-4"><i class="bi bi-file-earmark"></i>No agreement generated yet.</div><?php else: ?>
      <ul class="list-group list-group-flush"><?php foreach ($agreements as $a): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2">
        <a class="text-decoration-none" href="<?= e(url('agreements/view', ['id' => $a['id']])) ?>"><span class="fw-semibold"><?= e($a['ref']) ?></span><span class="d-block small text-muted"><?= e(fmt_date($a['generated_at'])) ?> · <?= e($a['gen_by'] ?? '') ?></span></a>
        <span class="badge badge-soft bg-<?= $a['status'] === 'current' ? 'success' : 'secondary' ?>"><?= e(ucfirst($a['status'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php endif; ?>

    <?php if ($activity): ?><div class="card"><div class="card-header">Recent activity</div><div class="card-body py-2"><ul class="timeline"><?php foreach ($activity as $a): ?>
      <li><span class="dot"><i class="bi bi-clock-history"></i></span><div><div class="small"><?= e($a['description']) ?></div><div class="text-muted" style="font-size:.72rem"><?= e($a['username']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div></div></li><?php endforeach; ?></ul></div></div><?php endif; ?>
  </div>
</div>
