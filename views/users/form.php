<?php $isEdit = (bool)$u; ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit ' . e($u['full_name']) : 'Add user' ?></h1><div class="sub"><?= $isEdit ? e($u['username']) : 'They will be asked to choose their own password at first sign-in.' ?></div></div><a class="btn btn-light border" href="<?= e(url('users')) ?>">Back</a></div>
<div class="row g-3"><div class="col-lg-7">
<div class="card"><div class="card-body"><form method="post" action="<?= e(url('users/save')) ?>" class="row g-3 needs-validation" novalidate autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($u['id'] ?? 0) ?>">
  <?php f_input('full_name', 'Full name', ['required' => true, 'maxlength' => 120, 'col' => 'col-md-6']); ?>
  <?php if ($isEdit): ?><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" value="<?= e($u['username']) ?>" disabled></div>
  <?php else: f_input('username', 'Username', ['required' => true, 'maxlength' => 40, 'col' => 'col-md-6', 'autocomplete' => 'off']); endif; ?>
  <?php f_input('email', 'Email', ['type' => 'email', 'required' => true, 'maxlength' => 150, 'col' => 'col-md-6']); ?>
  <?php f_input('phone', 'Phone', ['type' => 'tel', 'maxlength' => 30, 'col' => 'col-md-6']); ?>
  <div class="col-md-6"><label class="form-label" for="f_role_id">Role <span class="text-danger">*</span></label>
    <select class="form-select<?= fe('role_id') ? ' is-invalid' : '' ?>" id="f_role_id" name="role_id" required><?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"<?= selected(fv('role_id'), $r['id']) ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
    <?php if (fe('role_id')): ?><div class="invalid-feedback"><?= e(fe('role_id')) ?></div><?php endif; ?></div>
  <?php if (!$isEdit): ?><?php f_input('password', 'Temporary password', ['type' => 'password', 'required' => true, 'minlength' => 8, 'col' => 'col-md-6', 'autocomplete' => 'new-password', 'help' => '8+ characters with a letter and a number. Share it privately.']); ?><?php endif; ?>
  <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= checked($isEdit ? (bool)$u['is_active'] : true) ?>><label class="form-check-label" for="is_active">Account is active (can sign in)</label></div></div>
  <div class="col-12"><button class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create user' ?></button></div>
</form></div></div></div>
<?php if ($isEdit): ?><div class="col-lg-5"><div class="card"><div class="card-header">Reset password</div><div class="card-body">
  <p class="small text-muted">Sets a temporary password. The user must replace it at their next sign-in. This also clears any lockout.</p>
  <form method="post" action="<?= e(url('users/reset-password')) ?>" class="needs-validation" novalidate autocomplete="off" data-confirm="Set a new temporary password for <?= e($u['full_name']) ?>? Their current password will stop working." data-confirm-title="Reset password" data-confirm-button="Reset" data-confirm-class="btn-primary">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    <input class="form-control mb-2" type="password" name="password" placeholder="New temporary password" required minlength="8" autocomplete="new-password"><button class="btn btn-light border">Reset password</button></form>
</div></div></div><?php endif; ?></div>
