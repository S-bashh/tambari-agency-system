<?php
$pct = Money::kobo($s['sale_price']) > 0 ? min(100, (int)round(Money::kobo($s['amount_paid']) / Money::kobo($s['sale_price']) * 100)) : 0;
$open = in_array($s['status'], Sale::OPEN, true);
$canPay = can('payments.create') && in_array($s['status'], ['negotiation', 'reserved', 'deposit_paid', 'partially_paid'], true);
$kv = function (string $l, ?string $v) { if ($v !== null && $v !== '') echo '<dt>' . e($l) . '</dt><dd>' . $v . '</dd>'; };
?>
<div class="page-head">
  <div><div class="d-flex flex-wrap gap-2 align-items-center mb-1"><span class="text-muted small"><?= e($s['ref']) ?></span><?= Sale::badge($s['status']) ?><span class="badge badge-soft bg-secondary"><?= $s['sale_type'] === 'plot' ? 'Land' : 'House' ?></span></div>
    <h1><?= e($s['asset_label']) ?></h1><div class="sub">Buyer: <a href="<?= e(url('customers/view', ['id' => $s['buyer_id']])) ?>"><?= e($s['buyer_name']) ?></a> · Seller: <a href="<?= e(url('customers/view', ['id' => $s['seller_id']])) ?>"><?= e($s['seller_name']) ?></a></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canPay): ?><a class="btn btn-primary" href="<?= e(url('payments/create', ['sale' => $s['id']])) ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</a><?php endif; ?>
    <?php if ($s['status'] === 'negotiation' && can('sales.create')): ?><form method="post" action="<?= e(url('sales/reserve')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-warning"><i class="bi bi-lock me-1"></i>Reserve</button></form><?php endif; ?>
    <?php if ($open && can('sales.create')): ?><a class="btn btn-light border" href="<?= e(url('sales/edit', ['id' => $s['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
    <?php if ($s['status'] === 'fully_paid' && can('sales.complete')): ?><button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#compBox"><i class="bi bi-check2-circle me-1"></i>Complete sale</button><?php endif; ?>
    <?php if ($open && can('sales.cancel')): ?><button class="btn btn-light border text-danger" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="bi bi-x-octagon me-1"></i>Cancel</button><?php endif; ?>
  </div>
</div>
<?php if ($s['status'] === 'fully_paid' && can('sales.complete')): ?><div class="collapse mb-3" id="compBox"><div class="card border-success"><div class="card-body">
  <form method="post" action="<?= e(url('sales/complete')) ?>" class="row g-2 needs-validation" novalidate data-confirm="Complete this sale? The <?= $s['sale_type'] === 'plot' ? 'plot' : 'property' ?> will be marked Sold and the sale locked. This cannot be undone." data-confirm-title="Complete sale" data-confirm-button="Complete sale" data-confirm-class="btn-success">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <div class="col-md-5"><label class="form-label">Date the sale was completed</label><input class="form-control" type="date" name="sale_date" required max="<?= e(today()) ?>" value="<?= e($s['sale_date'] ?: today()) ?>"></div>
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-success w-100">Complete sale</button></div></form></div></div></div><?php endif; ?>
<?php if ($open && can('sales.cancel')): ?><div class="collapse mb-3" id="cancelBox"><div class="card border-danger"><div class="card-body">
  <form method="post" action="<?= e(url('sales/cancel')) ?>" class="row g-2 needs-validation" novalidate data-confirm="Cancel this sale? The <?= $s['sale_type'] === 'plot' ? 'plot' : 'property' ?> becomes available again." data-confirm-title="Cancel sale" data-confirm-button="Cancel sale">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <div class="col-md-9"><label class="form-label">Reason</label><input class="form-control" name="reason" required minlength="5" maxlength="255" placeholder="e.g. Buyer withdrew"></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100">Cancel sale</button></div></form>
  <?php if (Money::kobo($s['amount_paid']) > 0): ?><div class="small text-muted mt-2">This sale has payments. Void them first before it can be cancelled.</div><?php endif; ?></div></div></div><?php endif; ?>
<?php if ($s['status'] === 'cancelled'): ?><div class="alert alert-danger py-2">Cancelled <?= e(fmt_date($s['cancelled_at'])) ?>: <?= e($s['cancel_reason']) ?></div><?php endif; ?>
<?php if (Money::kobo($sched['overdue']) > 0 && $open): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-1"></i>Overdue instalments: <strong><?= e(money($sched['overdue'])) ?></strong></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Financial summary</div><div class="card-body">
      <div class="fin3 mb-3">
        <div><div class="text-muted small">Sale price</div><div class="fin-num"><?= e(money($s['sale_price'])) ?></div></div>
        <div><div class="text-muted small">Paid</div><div class="fin-num text-success"><?= e(money($s['amount_paid'])) ?></div></div>
        <div><div class="text-muted small">Balance</div><div class="fin-num <?= Money::kobo($outstanding) > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($outstanding)) ?></div></div></div>
      <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
      <dl class="kv"><?php $kv('Agreed deposit', Money::kobo($s['deposit']) > 0 ? e(money($s['deposit'])) : null); $kv('Sale date', $s['sale_date'] ? e(fmt_date($s['sale_date'])) : null);
        $kv('Commission', Money::kobo($s['commission_amount']) > 0 ? e(money($s['commission_amount'])) . ($s['commission_rate'] !== null ? ' (' . e(rtrim(rtrim($s['commission_rate'], '0'), '.')) . '%)' : '') : null); $kv('Agent', $s['agent_name'] ? e($s['agent_name']) : null); ?></dl></div></div>

    <div class="card mb-3"><div class="card-header">Payment schedule</div>
      <?php if (!$sched['rows']): ?><div class="empty py-4"><i class="bi bi-calendar3"></i>No instalment schedule was agreed. Payments are taken as they arrive until the balance is cleared.</div>
      <?php else: ?><div class="table-responsive"><table class="table table-stack align-middle mb-0"><thead><tr><th>Due</th><th>For</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($sched['rows'] as $r): $col = ['paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', 'upcoming' => 'secondary'][$r['status']]; ?>
        <tr><td class="cell-main"><?= e(fmt_date($r['due'])) ?></td><td data-label="For"><?= e($r['label'] ?: 'Instalment') ?></td><td data-label="Amount" class="text-end"><?= e(money($r['amount'])) ?></td><td data-label="Paid" class="text-end"><?= e(money($r['paid'])) ?></td><td data-label="Status"><span class="badge badge-soft bg-<?= $col ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>

    <?php if (can('payments.view')): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-cash-coin me-2"></i>Payments &amp; receipts</span><?php if ($canPay): ?><a class="btn btn-sm btn-primary" href="<?= e(url('payments/create', ['sale' => $s['id']])) ?>">Record payment</a><?php endif; ?></div>
      <?php if (!$payments): ?><div class="empty"><i class="bi bi-receipt"></i>No payments yet.</div><?php else: ?>
      <div class="table-responsive"><table class="table table-hover table-stack align-middle mb-0"><thead><tr><th>Date</th><th>Receipt</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
      <?php foreach ($payments as $p): $void = $p['status'] === 'void'; ?><tr class="<?= $void ? 'opacity-50' : '' ?>"><td class="cell-main"><?= e(fmt_date($p['payment_date'])) ?></td>
        <td data-label="Receipt"><a href="<?= e(url('payments/view', ['id' => $p['id']])) ?>"><?= e($p['receipt_no'] ?: $p['txn_id']) ?></a><?= $void ? ' <span class="badge badge-soft bg-danger">Void</span>' : '' ?></td>
        <td data-label="Method"><?= e(PaymentsController::methods()[$p['method']] ?? '') ?></td><td data-label="Amount" class="text-end <?= $void ? 'text-decoration-line-through' : '' ?>"><?= e(money($p['amount'])) ?></td>
        <td class="cell-actions text-end text-nowrap"><?php if ($p['receipt_id']): ?><a class="btn btn-sm btn-light" href="<?= e(url('receipts/view', ['id' => $p['receipt_id']])) ?>"><i class="bi bi-receipt"></i></a><a class="btn btn-sm btn-light" href="<?= e(url('receipts/pdf', ['id' => $p['receipt_id']])) ?>"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <?php endif; ?>
    <?php if (can('documents.view')): ?><div class="mb-3"><?php View::partial('documents', ['ownerType' => 'sale', 'ownerId' => (int)$s['id'], 'docs' => $docs, 'canUpload' => can('documents.upload') && $s['status'] !== 'cancelled', 'canDelete' => can('documents.delete'), 'withContext' => false]); ?></div><?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header"><?= $s['sale_type'] === 'plot' ? 'Plot' : 'Property' ?></div><div class="card-body"><dl class="kv">
      <?php if ($s['sale_type'] === 'property'): ?><dt>Property</dt><dd><a href="<?= e(url('properties/view', ['id' => $s['property_id']])) ?>"><?= e($s['property_title']) ?></a><div class="small text-muted"><?= e($s['property_ref']) ?> · <?= e($s['property_address']) ?></div></dd>
      <?php else: ?><dt>Plot</dt><dd><a href="<?= e(url('land/view', ['id' => $s['plot_id']])) ?>"><?= e($s['plot_label']) ?></a><div class="small text-muted"><?= e($s['plot_ref']) ?></div></dd>
        <?php $kv('Size', $s['size_text'] ? e($s['size_text']) : null); $kv('Title status', e($s['title_status'])); ?><?php endif; ?></dl></div></div>
    <?php if ($s['notes']): ?><div class="card mb-3"><div class="card-header">Notes</div><div class="card-body"><?= nl2br(e($s['notes'])) ?></div></div><?php endif; ?>
    <?php if ($activity): ?><div class="card"><div class="card-header">Recent activity</div><div class="card-body py-2"><ul class="timeline"><?php foreach ($activity as $a): ?>
      <li><span class="dot"><i class="bi bi-clock-history"></i></span><div><div class="small"><?= e($a['description']) ?></div><div class="text-muted" style="font-size:.72rem"><?= e($a['username']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div></div></li><?php endforeach; ?></ul></div></div><?php endif; ?>
  </div>
</div>
