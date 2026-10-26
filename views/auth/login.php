<form method="post" action="<?= e(url('auth/login')) ?>" class="needs-validation" novalidate>
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="alert alert-danger py-2 small" role="alert"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
  <div class="mb-3"><label class="form-label" for="username">Username or email</label>
    <input class="form-control form-control-lg" id="username" name="username" value="<?= e($login) ?>" required autofocus autocomplete="username"></div>
  <div class="mb-3"><label class="form-label" for="password">Password</label>
    <input class="form-control form-control-lg" id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary btn-lg w-100 mt-2">Sign in</button>
  <p class="text-center text-muted small mt-3 mb-0">Authorised staff only. Activity is recorded.</p>
</form>
