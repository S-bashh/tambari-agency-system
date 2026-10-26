<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle ? $pageTitle . ' · ' : '') ?><?= e(setting('company_name', 'TEAMS')) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-wrap"><div class="auth-card">
  <div class="text-center mb-3">
    <?= brand_mark() ?>
    <h1 class="h5 mb-0"><?= e(setting('company_name', 'Tambari Estate Agents')) ?></h1>
    <div class="text-muted small">Estate Agents Management System</div>
  </div>
  <?= $content ?>
</div></div>
<div class="toast-wrap" id="toasts" data-flash="<?= e(json_encode(flashes(), JSON_UNESCAPED_UNICODE)) ?>"></div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
