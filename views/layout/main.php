<?php
$u = Auth::user();
$nav = [
    ['Overview', [['dashboard', 'dashboard.view', 'dashboard', 'Dashboard', 'bi-speedometer2']]],
    ['People', [
        ['customers', 'customers.view', 'customers', 'Customers', 'bi-people'],
        ['tenants', 'tenants.view', 'tenants', 'Tenants', 'bi-person-badge'],
        ['landlords', 'landlords.view', 'landlords', 'Landlords', 'bi-person-vcard'],
        ['guarantors', 'guarantors.view', 'guarantors', 'Guarantors', 'bi-shield-check'],
    ]],
    ['Rentals', [
        ['tenancies', 'tenancies.view', 'tenancies', 'Tenancies', 'bi-house-check'],
        ['payments', 'payments.view', 'payments', 'Payments & receipts', 'bi-cash-coin'],
    ]],
    ['Sales & land', [
        ['sales', 'sales.view', 'sales', 'Sales', 'bi-tag'],
        ['land', 'land.view', 'land', 'Land & plots', 'bi-map'],
    ]],
    ['Portfolio', [
        ['properties', 'properties.view', 'properties', 'Properties', 'bi-buildings'],
        ['documents', 'documents.view', 'documents', 'Documents', 'bi-folder2-open'],
    ]],
    ['Insight', [['reports', 'reports.view', 'reports', 'Reports', 'bi-bar-chart-line']]],
    ['Administration', [
        ['users', 'users.manage', 'users', 'Users', 'bi-person-gear'],
        ['roles', 'roles.manage', 'roles', 'Roles & permissions', 'bi-key'],
        ['agreements/templates', 'templates.manage', 'templates', 'Agreement templates', 'bi-file-earmark-text'],
        ['backups', 'backups.manage', 'backups', 'Backups', 'bi-cloud-arrow-down'],
        ['audit', 'audit.view', 'audit', 'Audit log', 'bi-clipboard-data'],
        ['settings', 'settings.manage', 'settings', 'Settings', 'bi-sliders'],
    ]],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle ? $pageTitle . ' · ' : '') ?><?= e(setting('company_name', 'TEAMS')) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url('dashboard')) ?>">
    <?= brand_mark() ?>
    <span><span class="brand-name d-block"><?= e(setting('company_name', 'Tambari Estate Agents')) ?></span><span class="brand-sub">Management System</span></span>
  </a>
  <nav>
    <?php foreach ($nav as [$group, $items]):
        $visible = array_filter($items, fn($i) => can($i[1]));
        if (!$visible) continue; ?>
      <div class="nav-group"><?= e($group) ?></div>
      <?php foreach ($visible as [$route, , $key, $label, $icon]): ?>
        <a class="nav-link <?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= e(url($route)) ?>"><i class="bi <?= e($icon) ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">TEAMS v<?= e(TEAMS_VERSION) ?> · <?= e(setting('company_reg')) ?></div>
</aside>
<div class="sidebar-backdrop" id="backdrop"></div>

<div class="main">
  <header class="topbar">
    <button class="btn btn-light d-lg-none" id="navToggle" type="button" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
    <form class="search" action="<?= e(url()) ?>" method="get" role="search">
      <input type="hidden" name="r" value="search">
      <i class="bi bi-search"></i>
      <input class="form-control" id="globalSearch" type="search" name="q" placeholder="Search people, properties, documents…" autocomplete="off" minlength="2" value="<?= e(($active ?? '') === 'search' ? ($_GET['q'] ?? '') : '') ?>">
      <kbd>Ctrl K</kbd>
    </form>
    <div class="ms-auto dropdown">
      <button class="btn btn-light d-flex align-items-center gap-2 border" data-bs-toggle="dropdown" type="button">
        <span class="avatar"><?= e(initials($u['full_name'])) ?></span>
        <span class="d-none d-md-block text-start lh-sm"><span class="d-block fw-semibold small"><?= e($u['full_name']) ?></span><span class="text-muted" style="font-size:.7rem"><?= e($u['role_name']) ?></span></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="<?= e(url('account/profile')) ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
        <li><a class="dropdown-item" href="<?= e(url('account/password')) ?>"><i class="bi bi-lock me-2"></i>Change password</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><form method="post" action="<?= e(url('auth/logout')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
      </ul>
    </div>
  </header>
  <main class="content"><?= $content ?></main>
</div>

<div class="toast-wrap" id="toasts" data-flash="<?= e(json_encode(flashes(), JSON_UNESCAPED_UNICODE)) ?>"></div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title" id="confirmTitle">Are you sure?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body pt-1" id="confirmBody"></div>
    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmOk">Confirm</button></div>
  </div></div>
</div>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?php if (!empty($charts)): ?><script src="<?= e(asset('vendor/chartjs/chart.umd.js')) ?>"></script><?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $sc): ?><script src="<?= e(asset($sc)) ?>"></script><?php endforeach; ?>
</body>
</html>
