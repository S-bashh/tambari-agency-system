<div class="page-head"><div><h1><?= $tpl['id'] ? 'Edit template' : 'New template' ?></h1><div class="sub">Write the agreement as simple HTML. Put placeholders in double curly braces; they are filled from the tenancy when an agreement is generated.</div></div><a class="btn btn-light border" href="<?= e(url('agreements/templates')) ?>">Back</a></div>
<div class="row g-3"><div class="col-xl-8">
<form method="post" action="<?= e(url('agreements/template-save')) ?>" class="needs-validation" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
  <div class="card mb-3"><div class="card-body"><div class="row g-3">
    <?php f_input('name', 'Template name', ['required' => true, 'maxlength' => 120, 'col' => 'col-md-6']); ?>
    <div class="col-md-6 d-flex align-items-end gap-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="isdef" <?= checked(!empty(fv('is_default'))) ?>><label class="form-check-label" for="isdef">Default template</label></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="isact" <?= checked(!isset($tpl['is_active']) || !empty(fv('is_active', 1))) ?>><label class="form-check-label" for="isact">Active</label></div></div>
    <div class="col-12"><label class="form-label" for="f_body_html">Agreement text <span class="text-danger">*</span></label>
      <textarea class="form-control font-monospace small<?= fe('body_html') ? ' is-invalid' : '' ?>" id="f_body_html" name="body_html" rows="26" required spellcheck="false"><?= e(fv('body_html')) ?></textarea><?php if (fe('body_html')): ?><div class="invalid-feedback"><?= e(fe('body_html')) ?></div><?php endif; ?>
      <div class="form-text">Allowed tags: p, h1 to h4, strong, em, u, ol, ul, li, br, table. Allowed classes: center, right, small. Scripts and styles are removed automatically.</div></div>
  </div></div></div><button class="btn btn-primary btn-lg px-4">Save template</button></form></div>
<div class="col-xl-4"><div class="card"><div class="card-header">Placeholders</div><div class="card-body small"><p class="text-muted">Click to copy, then paste into the text.</p>
  <?php foreach ($placeholders as $k => $d): ?><div class="mb-2"><button type="button" class="btn btn-sm btn-light border font-monospace" data-copy="{{<?= e($k) ?>}}">{{<?= e($k) ?>}}</button><div class="text-muted"><?= e($d) ?></div></div><?php endforeach; ?></div></div></div></div>
