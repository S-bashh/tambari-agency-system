<div class="empty" style="max-width:520px;margin:3rem auto">
  <i class="bi <?= $code === 403 ? 'bi-shield-lock' : ($code === 404 ? 'bi-search' : 'bi-exclamation-triangle') ?>"></i>
  <h2 class="h4"><?= e($title) ?></h2>
  <p><?= e($message ?: 'Something unexpected happened.') ?></p>
  <a class="btn btn-primary" href="<?= e(Auth::check() ? url('dashboard') : url('auth/login')) ?>">Go back home</a>
</div>
