<?php
/** @var string $ownerType @var int $ownerId @var array $docs @var bool $canUpload @var bool $canDelete @var bool $withContext */
$cats = list_doc_categories($ownerType);
?>
<div class="card" id="documents">
  <div class="card-header"><span><i class="bi bi-folder2-open me-2"></i>Documents <span class="text-muted fw-normal">(<?= count($docs) ?>)</span></span>
    <?php if ($canUpload): ?><button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#docUpload"><i class="bi bi-upload me-1"></i>Upload</button><?php endif; ?>
  </div>
  <?php if ($canUpload): ?>
  <div class="collapse border-bottom" id="docUpload"><div class="card-body bg-light">
    <form method="post" action="<?= e(url('documents/upload')) ?>" enctype="multipart/form-data" class="row g-2 needs-validation" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="owner_type" value="<?= e($ownerType) ?>"><input type="hidden" name="owner_id" value="<?= (int)$ownerId ?>">
      <div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category" required><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
      <?php if (!empty($withContext)): ?><div class="col-md-4"><label class="form-label">Belongs to role</label><select class="form-select" name="context"><?php foreach (list_customer_contexts() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-md-4"><label class="form-label">Title <span class="text-muted">(optional)</span></label><input class="form-control" name="title" maxlength="150"></div>
      <div class="col-md-8"><label class="form-label">Files <span class="text-muted">(PDF, JPG, PNG, DOC, DOCX, XLS, XLSX · max <?= (int)cfg('max_upload_mb', 8) ?> MB each)</span></label><input class="form-control" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
      <div class="col-md-4"><label class="form-label">Note <span class="text-muted">(optional)</span></label><input class="form-control" name="notes" maxlength="255"></div>
      <div class="col-12"><button class="btn btn-primary btn-sm">Upload documents</button></div>
    </form>
  </div></div>
  <?php endif; ?>
  <?php if (!$docs): ?>
    <div class="empty"><i class="bi bi-file-earmark"></i>No documents uploaded yet.</div>
  <?php else: ?>
    <div class="table-responsive"><table class="table table-hover table-stack align-middle">
      <thead><tr><th>Document</th><th>Category</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): $isImg = str_starts_with($d['mime_type'], 'image/'); $isPdf = $d['mime_type'] === 'application/pdf'; ?>
        <tr>
          <td class="cell-main"><i class="bi <?= $isPdf ? 'bi-file-earmark-pdf text-danger' : ($isImg ? 'bi-file-earmark-image text-primary' : 'bi-file-earmark-text text-secondary') ?> me-2"></i><?= e($d['title']) ?>
            <?php if ($d['context']): ?><span class="badge badge-soft bg-secondary ms-1"><?= e(ucfirst($d['context'])) ?></span><?php endif; ?>
            <div class="small text-muted"><?= e($d['original_name']) ?></div></td>
          <td data-label="Category"><?= e($d['category']) ?></td>
          <td data-label="Size"><?= e(fmt_bytes((int)$d['file_size'])) ?></td>
          <td data-label="Uploaded"><?= e(fmt_date($d['created_at'])) ?><div class="small text-muted"><?= e($d['uploader'] ?? '') ?></div></td>
          <td class="cell-actions text-end text-nowrap">
            <?php if ($isPdf || $isImg): ?><a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= e(url('documents/download', ['id' => $d['id'], 'inline' => 1])) ?>" title="View"><i class="bi bi-eye"></i></a><?php endif; ?>
            <a class="btn btn-sm btn-light" href="<?= e(url('documents/download', ['id' => $d['id']])) ?>" title="Download"><i class="bi bi-download"></i></a>
            <?php if ($canDelete): ?>
            <form method="post" action="<?= e(url('documents/archive')) ?>" class="d-inline" data-confirm="Archive &quot;<?= e($d['title']) ?>&quot;? It will be hidden from this record but kept on file." data-confirm-title="Archive document">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Archive"><i class="bi bi-archive"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endif; ?>
</div>
