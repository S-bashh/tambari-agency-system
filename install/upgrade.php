<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file(TEAMS_ROOT . '/config/config.php')) { header('Location: index.php'); exit; }
Auth::startSession();
header('Cache-Control: no-store');
function uh(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$user = null;
try { $user = Auth::user(); } catch (Throwable) { }
$isAdmin = $user && $user['role_slug'] === 'admin';
$applied = null; $error = null;

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(Csrf::token(), (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Session expired. Reload and try again.';
    } else {
        try {
            $applied = Migrator::run(db());
            Migrator::syncCatalogue(db());
            Audit::log('upgrade', 'system', null, 'Ran database upgrade' . ($applied ? ': ' . implode(', ', $applied) : ' (already up to date)'));
        } catch (Throwable $e) {
            app_log('Upgrade: ' . $e->getMessage());
            $error = 'The upgrade failed (see storage/logs). Your existing data was not deleted. Restore your latest backup if the application misbehaves.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Upgrade · TEAMS</title>
<link rel="stylesheet" href="<?= uh(asset('vendor/bootstrap/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= uh(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><div class="auth-card" style="max-width:520px">
<h1 class="h5">Upgrade TEAMS</h1>
<?php if (!$isAdmin): ?>
  <p class="text-muted small">Only a signed-in administrator can run upgrades. <a href="<?= uh(base_path() . '/index.php') ?>">Sign in</a>, then return to this page.</p>
<?php else: ?>
  <p class="small text-muted">Files version <strong><?= uh(TEAMS_VERSION) ?></strong>. Make a backup first (Administration, Backups). This applies any new database changes shipped with this version. It never deletes existing records.</p>
  <?php if ($error): ?><div class="alert alert-danger small"><?= uh($error) ?></div><?php endif; ?>
  <?php if ($applied !== null): ?><div class="alert alert-success small"><?= $applied ? 'Applied: ' . uh(implode(', ', $applied)) : 'Already up to date.' ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="_csrf" value="<?= uh(Csrf::token()) ?>"><button class="btn btn-primary w-100">Run upgrade</button></form>
  <a class="btn btn-link w-100 mt-2" href="<?= uh(base_path() . '/index.php') ?>">Back to the system</a>
<?php endif; ?>
</div></div></body></html>
