<div class="row justify-content-center"><div class="col-lg-5">
  <div class="page-head"><div><h1>Change password</h1><div class="sub"><?= $forced ? 'You must set a new password before continuing.' : 'Use a strong password you do not use anywhere else.' ?></div></div></div>
  <div class="card"><div class="card-body">
    <form method="post" class="row g-3 needs-validation" novalidate autocomplete="off">
      <?= csrf_field() ?>
      <?php f_input('current_password', 'Current password', ['type' => 'password', 'required' => true, 'col' => 'col-12', 'autocomplete' => 'current-password']); ?>
      <?php f_input('new_password', 'New password', ['type' => 'password', 'required' => true, 'col' => 'col-12', 'minlength' => 8, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with a letter and a number.']); ?>
      <?php f_input('confirm_password', 'Confirm new password', ['type' => 'password', 'required' => true, 'col' => 'col-12', 'autocomplete' => 'new-password']); ?>
      <div class="col-12"><button class="btn btn-primary">Update password</button></div>
    </form>
  </div></div>
</div></div>
