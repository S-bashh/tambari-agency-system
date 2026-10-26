<?php $hasF = array_filter($f) !== []; $types = PaymentsController::types(); $methods = PaymentsController::methods(); ?>
<div class="page-head"><div><h1>Payments &amp; receipts</h1><div class="sub">Every payment has a transaction ID and a numbered receipt. Payments are never edited or deleted, only voided with a reason.</div></div>
  <?php if (can('payments.create')): ?><a class="btn btn-primary" href="<?= e(url('payments/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Record payment</a><?php endif; ?></div>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="payments">
    <div class="col-lg-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Receipt no, transaction ID, name, reference"></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="type"><option value="">All types</option><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="method"><option value="">All methods</option><?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['method'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">Any status</option><option value="valid"<?= selected($f['status'], 'valid') ?>>Valid</option><option value="void"<?= selected($f['status'], 'void') ?>>Void</option></select></div>
    <div class="col-6 col-lg-2"><button class="btn btn-primary w-100">Filter</button></div>
    <div class="col-6 col-lg-3"><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" title="From date"></div>
    <div class="col-6 col-lg-3"><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" title="To date"></div>
    <?php if ($hasF): ?><div class="col-12 col-lg-2"><a class="btn btn-light border w-100" href="<?= e(url('payments')) ?>">Reset</a></div><?php endif; ?></form></div>
  <div class="card-body py-2 border-bottom small text-muted d-flex justify-content-between flex-wrap gap-2"><span><?= (int)$pg['total'] ?> payment<?= $pg['total'] == 1 ? '' : 's' ?></span><span>Valid total for this filter: <strong class="text-dark"><?= e(money($validSum)) ?></strong></span></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-cash-coin"></i><?= $hasF ? 'No payments match.' : 'No payments recorded yet.' ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Date</th><th>Receipt</th><th>Payer</th><th>For</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $void = $r['status'] === 'void'; ?>
    <tr class="row-link <?= $void ? 'opacity-50' : '' ?>" data-href="<?= e(url('payments/view', ['id' => $r['id']])) ?>">
      <td class="cell-main"><?= e(fmt_date($r['payment_date'])) ?></td>
      <td data-label="Receipt"><a class="fw-semibold text-decoration-none" href="<?= e(url('payments/view', ['id' => $r['id']])) ?>"><?= e($r['receipt_no'] ?: $r['txn_id']) ?></a><?= $void ? ' <span class="badge badge-soft bg-danger">Void</span>' : '' ?><div class="small text-muted"><?= e($r['txn_id']) ?></div></td>
      <td data-label="Payer"><?= e($r['customer_name']) ?></td>
      <td data-label="For"><?= e($types[$r['payment_type']] ?? $r['payment_type']) ?><div class="small text-muted"><?= e($r['tenancy_ref'] ?: ($r['sale_ref'] ?: str_limit((string)$r['property_title'], 28))) ?></div></td>
      <td data-label="Method"><?= e($methods[$r['method']] ?? $r['method']) ?></td>
      <td data-label="Amount" class="text-end fw-semibold <?= $void ? 'text-decoration-line-through' : '' ?>"><?= e(money($r['amount'])) ?></td>
      <td class="cell-actions text-end text-nowrap"><?php if ($r['receipt_id']): ?><a class="btn btn-sm btn-light" href="<?= e(url('receipts/view', ['id' => $r['receipt_id']])) ?>" title="Receipt"><i class="bi bi-receipt"></i></a><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
