<div class="page-head"><div><h1>Agreement templates</h1><div class="sub">The agreement wording lives here, not in the program. Changes affect new agreements only.</div></div>
  <a class="btn btn-primary" href="<?= e(url('agreements/template-edit')) ?>"><i class="bi bi-plus-lg me-1"></i>New template</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Template</th><th>Status</th><th>Used</th><th>Last edited</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="cell-main fw-semibold"><?= e($r['name']) ?></td>
  <td data-label="Status"><?= $r['is_default'] ? '<span class="badge badge-soft bg-primary">Default</span> ' : '' ?><?= $r['is_active'] ? '<span class="badge badge-soft bg-success">Active</span>' : '<span class="badge badge-soft bg-secondary">Inactive</span>' ?></td>
  <td data-label="Used"><?= (int)$r['used'] ?> agreement<?= $r['used'] == 1 ? '' : 's' ?></td><td data-label="Edited"><?= e(fmt_date($r['updated_at'])) ?><div class="small text-muted"><?= e($r['editor'] ?? '') ?></div></td>
  <td class="cell-actions text-end text-nowrap"><a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= e(url('agreements/template-preview', ['id' => $r['id']])) ?>"><i class="bi bi-eye me-1"></i>Preview</a> <a class="btn btn-sm btn-light" href="<?= e(url('agreements/template-edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a></td></tr>
<?php endforeach; ?></tbody></table></div></div>
