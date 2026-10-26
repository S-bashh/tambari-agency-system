<?php
$stats = [
    ['Total properties', $total, 'bi-buildings', 'blue', url('properties')],
    ['Available', (int)($byStatus['available'] ?? 0), 'bi-door-open', 'green', url('properties', ['status' => 'available'])],
    ['Rented', (int)($byStatus['rented'] ?? 0), 'bi-key', 'amber', url('properties', ['status' => 'rented'])],
    ['Sold', (int)($byStatus['sold'] ?? 0), 'bi-patch-check', 'slate', url('properties', ['status' => 'sold'])],
    ['Available for rent', $forRent, 'bi-house-door', 'blue', url('properties', ['status' => 'available', 'listing' => 'rent'])],
    ['Available for sale', $forSale, 'bi-tag', 'rose', url('properties', ['status' => 'available', 'listing' => 'sale'])],
    ['Customers', $customers, 'bi-people', 'blue', url('customers')],
    ['Tenants', (int)($roleCount['tenant'] ?? 0), 'bi-person-badge', 'green', url('tenants')],
    ['Landlords', (int)($roleCount['landlord'] ?? 0), 'bi-person-vcard', 'amber', url('landlords')],
    ['Guarantors', (int)($roleCount['guarantor'] ?? 0), 'bi-shield-check', 'slate', url('guarantors')],
];
?>
<div class="page-head"><div><h1>Dashboard</h1><div class="sub"><?= e(date('l, j F Y')) ?> · live figures from your database</div></div>
  <div class="d-flex gap-2">
    <?php if (can('properties.create')): ?><a class="btn btn-primary" href="<?= e(url('properties/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add property</a><?php endif; ?>
    <?php if (can('customers.create')): ?><a class="btn btn-light border" href="<?= e(url('customers/create')) ?>"><i class="bi bi-person-plus me-1"></i>Add person</a><?php endif; ?>
  </div></div>

<div class="row g-3 mb-3">
<?php foreach ($stats as [$lbl, $num, $ico, $col, $href]): ?>
  <div class="col-6 col-md-4 col-xl"><a class="card stat" href="<?= e($href) ?>"><span class="ico ico-<?= $col ?>"><i class="bi <?= $ico ?>"></i></span><span><span class="num d-block"><?= (int)$num ?></span><span class="lbl"><?= e($lbl) ?></span></span></a></div>
<?php endforeach; ?>
</div>

<?php if ($rental || $money): ?>
<div class="row g-3 mb-3">
<?php if ($money): ?>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('payments')) ?>"><span class="ico ico-green"><i class="bi bi-cash-coin"></i></span><span><span class="num d-block fs-5"><?= e(money($money['month_all'])) ?></span><span class="lbl">Collected this month</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('payments', ['type' => 'rent'])) ?>"><span class="ico ico-blue"><i class="bi bi-house-check"></i></span><span><span class="num d-block fs-5"><?= e(money($money['total_rent'])) ?></span><span class="lbl">Rent collected (all time)</span></span></a></div>
<?php endif; ?>
<?php if ($rental): $c = $rental['counts']; ?>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('tenancies', ['status' => 'active'])) ?>"><span class="ico ico-green"><i class="bi bi-people"></i></span><span><span class="num d-block"><?= (int)(($c['active'] ?? 0) + ($c['expiring'] ?? 0)) ?></span><span class="lbl">Active tenancies</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('tenancies/expiry')) ?>"><span class="ico ico-amber"><i class="bi bi-calendar-event"></i></span><span><span class="num d-block"><?= (int)(($c['expiring'] ?? 0)) ?></span><span class="lbl">Expiring within <?= (int)setting('expiry_alert_days', '90') ?> days</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('tenancies', ['status' => 'expired'])) ?>"><span class="ico ico-rose"><i class="bi bi-calendar-x"></i></span><span><span class="num d-block"><?= (int)($c['expired'] ?? 0) ?></span><span class="lbl">Expired tenancies</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('tenancies/expiry')) ?>#overdue"><span class="ico ico-rose"><i class="bi bi-exclamation-octagon"></i></span><span><span class="num d-block fs-5"><?= e(money($rental['overdue_sum'])) ?></span><span class="lbl">Overdue rent (<?= (int)$rental['overdue_n'] ?> tenanc<?= $rental['overdue_n'] == 1 ? 'y' : 'ies' ?>)</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('tenancies')) ?>"><span class="ico ico-slate"><i class="bi bi-hourglass-split"></i></span><span><span class="num d-block fs-5"><?= e(money($rental['pending'])) ?></span><span class="lbl">Pending (unpaid on tenancies)</span></span></a></div>
<?php endif; ?>
</div>
<?php if ($revMonthChart): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-header">Payments collected per month</div><div class="card-body"><?php if (array_sum($revMonthChart['values'])): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($revMonthChart)) ?>"></canvas></div><?php else: ?><div class="empty"><i class="bi bi-graph-up"></i>No payments recorded in the last 6 months.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Income by type (this year)</div><div class="card-body"><?php if ($revTypeChart['values']): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($revTypeChart)) ?>"></canvas></div><?php else: ?><div class="empty"><i class="bi bi-pie-chart"></i>No payments this year.</div><?php endif; ?></div></div></div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($salesStat): ?>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('sales')) ?>"><span class="ico ico-blue"><i class="bi bi-tag"></i></span><span><span class="num d-block fs-5"><?= e(money($salesStat['value'])) ?></span><span class="lbl">Sales value (<?= (int)$salesStat['open'] ?> open)</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('sales')) ?>"><span class="ico ico-green"><i class="bi bi-cash-stack"></i></span><span><span class="num d-block fs-5"><?= e(money($salesStat['paid'])) ?></span><span class="lbl">Collected on sales</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('sales', ['status' => 'completed', 'type' => 'property'])) ?>"><span class="ico ico-slate"><i class="bi bi-house-check"></i></span><span><span class="num d-block"><?= (int)$salesStat['houses'] ?></span><span class="lbl">Houses sold</span></span></a></div>
  <div class="col-6 col-lg-3"><a class="card stat" href="<?= e(url('sales', ['status' => 'completed', 'type' => 'plot'])) ?>"><span class="ico ico-amber"><i class="bi bi-map"></i></span><span><span class="num d-block"><?= (int)$salesStat['plots'] ?></span><span class="lbl">Land plots sold<?= $salesStat['plots_available'] !== null ? ' · ' . (int)$salesStat['plots_available'] . ' available' : '' ?></span></span></a></div>
</div>
<?php if ($activityChart): ?><div class="row g-3 mb-3"><div class="col-12"><div class="card"><div class="card-header">Rental vs sales activity (new tenancies and new sales, 6 months)</div><div class="card-body"><?php if (array_sum($activityChart['values'])): ?><div class="chart-box" style="height:220px"><canvas data-chart="<?= e(json_encode(['type' => 'stacked', 'labels' => $activityChart['labels'], 'datasets' => [['label' => 'New tenancies', 'data' => $activityChart['tenancies']], ['label' => 'New sales', 'data' => $activityChart['sales']]]])) ?>"></canvas></div><?php else: ?><div class="empty"><i class="bi bi-graph-up"></i>Nothing created in the last 6 months.</div><?php endif; ?></div></div></div></div><?php endif; ?>
<?php endif; ?>

<?php if ($alerts): ?>
<div class="card mb-3"><div class="card-header"><span><i class="bi bi-bell me-2"></i>Needs attention</span></div><div class="card-body pb-2">
  <?php foreach ($alerts as [$ico, $txt, $href]): ?>
    <a class="alert-row" href="<?= e($href) ?>"><i class="bi <?= e($ico) ?> fs-5"></i><span><?= e($txt) ?></span><i class="bi bi-chevron-right ms-auto"></i></a>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Property status</div><div class="card-body">
    <?php if ($total): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($statusChart)) ?>"></canvas></div>
    <?php else: ?><div class="empty"><i class="bi bi-pie-chart"></i>Add properties to see the breakdown.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Properties by type</div><div class="card-body">
    <?php if ($total): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($typeChart)) ?>"></canvas></div>
    <?php else: ?><div class="empty"><i class="bi bi-bar-chart"></i>No data yet.</div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Listings added (6 months)</div><div class="card-body">
    <?php if ($total): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($monthChart)) ?>"></canvas></div>
    <?php else: ?><div class="empty"><i class="bi bi-graph-up"></i>No data yet.</div><?php endif; ?></div></div></div>
</div>

<div class="row g-3">
  <?php if ($recentProps || can('properties.view')): ?>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Recent properties <a class="small fw-normal" href="<?= e(url('properties')) ?>">View all</a></div>
    <?php if (!$recentProps): ?><div class="empty"><i class="bi bi-buildings"></i>No properties yet.</div><?php else: ?>
    <ul class="list-group list-group-flush"><?php foreach ($recentProps as $p): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none" href="<?= e(url('properties/view', ['id' => $p['id']])) ?>"><span class="fw-semibold d-block"><?= e(str_limit($p['title'], 34)) ?></span><span class="small text-muted"><?= e($p['ref']) ?> · <?= e($p['area']) ?></span></a><?= status_badge($p['status']) ?></li>
    <?php endforeach; ?></ul><?php endif; ?></div></div>
  <?php endif; ?>
  <?php if (can('customers.view')): ?>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Recently added people <a class="small fw-normal" href="<?= e(url('customers')) ?>">View all</a></div>
    <?php if (!$recentPeople): ?><div class="empty"><i class="bi bi-people"></i>No customers yet.</div><?php else: ?>
    <ul class="list-group list-group-flush"><?php foreach ($recentPeople as $c): ?>
      <li class="list-group-item d-flex align-items-center gap-2"><span class="avatar"><?= e(initials($c['full_name'])) ?></span><a class="text-decoration-none flex-grow-1" href="<?= e(url('customers/view', ['id' => $c['id']])) ?>"><span class="fw-semibold d-block"><?= e($c['full_name']) ?></span><span class="small text-muted"><?= e($c['phone'] ?: $c['ref']) ?></span></a><span><?= role_badges($c['roles']) ?></span></li>
    <?php endforeach; ?></ul><?php endif; ?></div></div></div>
  <?php endif; ?>
  <?php if (can('audit.view')): ?>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Recent activity <a class="small fw-normal" href="<?= e(url('audit')) ?>">Audit log</a></div>
    <?php if (!$activity): ?><div class="empty"><i class="bi bi-activity"></i>No activity recorded yet.</div><?php else: ?>
    <div class="card-body py-2"><ul class="timeline"><?php foreach ($activity as $a): ?>
      <li><span class="dot"><i class="bi bi-lightning-charge"></i></span><div><div class="small"><?= e($a['description']) ?></div><div class="text-muted" style="font-size:.72rem"><?= e($a['username']) ?> · <?= e(time_ago($a['created_at'])) ?></div></div></li>
    <?php endforeach; ?></ul></div><?php endif; ?></div></div>
  <?php endif; ?>
</div>
