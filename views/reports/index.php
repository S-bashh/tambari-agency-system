<div class="page-head"><div><h1>Reports</h1><div class="sub">Live reports from your records. Every report can be exported as CSV (opens in Excel) or PDF.</div></div></div>
<?php if (!$groups): ?><div class="card"><div class="empty"><i class="bi bi-bar-chart-line"></i>You do not have access to any report data.</div></div><?php endif; ?>
<?php foreach ($groups as $g => $items): ?>
<h2 class="h6 text-uppercase text-muted mt-4 mb-2" style="letter-spacing:.08em"><?= e($g) ?></h2>
<div class="row g-3"><?php foreach ($items as $it): ?>
  <div class="col-md-6 col-xl-4"><a class="card h-100 text-decoration-none text-reset prop-card" href="<?= e(url('reports/run', ['report' => $it['key']])) ?>"><div class="card-body"><div class="d-flex justify-content-between"><span class="fw-semibold"><?= e($it['title']) ?></span><i class="bi bi-chevron-right text-muted"></i></div><div class="small text-muted mt-1"><?= e($it['desc']) ?></div></div></a></div>
<?php endforeach; ?></div>
<?php endforeach; ?>
