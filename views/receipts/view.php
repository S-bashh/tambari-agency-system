<div class="page-head"><div><h1>Receipt <?= e($r['receipt_no']) ?></h1><div class="sub"><?= e($r['customer_name']) ?> · <?= e(money($r['amount'])) ?><?= $r['status'] === 'void' ? ' · <span class="text-danger fw-semibold">VOID</span>' : '' ?></div></div>
  <div class="d-flex flex-wrap gap-2 no-print">
    <a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e(url('receipts/raw', ['id' => $r['receipt_id'], 'print' => 1])) ?>"><i class="bi bi-printer me-1"></i><?= $r['print_count'] > 0 ? 'Reprint' : 'Print' ?></a>
    <a class="btn btn-light border" href="<?= e(url('receipts/pdf', ['id' => $r['receipt_id']])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
    <button class="btn btn-light border" type="button" data-share-pdf="<?= e(url('receipts/pdf', ['id' => $r['receipt_id']])) ?>" data-share-name="Receipt-<?= e($r['receipt_no']) ?>.pdf" data-share-title="Receipt <?= e($r['receipt_no']) ?>"><i class="bi bi-share me-1"></i>Share</button>
    <a class="btn btn-light border" href="<?= e(url('payments/view', ['id' => $r['payment_id']])) ?>">Payment details</a>
    <?php if ($r['tenancy_id']): ?><a class="btn btn-light border" href="<?= e(url('tenancies/view', ['id' => $r['tenancy_id']])) ?>">Tenancy</a><?php endif; ?></div></div>
<div class="card"><div class="card-body p-2"><iframe src="<?= e(url('receipts/raw', ['id' => $r['receipt_id']])) ?>" title="Receipt preview" style="width:100%;height:560px;border:0;background:#fff"></iframe></div></div>
<div class="small text-muted mt-2">Printed or downloaded <?= (int)$r['print_count'] ?> time<?= $r['print_count'] == 1 ? '' : 's' ?>. Every reprint is recorded in the audit log.</div>
