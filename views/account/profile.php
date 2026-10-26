<div class="page-head"><div><h1>My profile</h1><div class="sub">Signed in as <strong><?= e($u['username']) ?></strong> · <?= e($u['role_name']) ?></div></div>
  <a class="btn btn-light border" href="<?= e(url('account/password')) ?>"><i class="bi bi-lock me-1"></i>Change password</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
  <form method="post" class="row g-3 needs-validation" novalidate>
    <?= csrf_field() ?>
    <?php f_input('full_name', 'Full name', ['required' => true, 'maxlength' => 120, 'col' => 'col-12']); ?>
    <?php f_input('email', 'Email', ['type' => 'email', 'required' => true, 'maxlength' => 150]); ?>
    <?php f_input('phone', 'Phone', ['maxlength' => 30, 'inputmode' => 'tel']); ?>
    <div class="col-12"><button class="btn btn-primary">Save changes</button></div>
  </form>
</div></div>
