<?php $hasF = array_filter($f) !== []; ?>
<div class="page-head"><div><h1><?= $archived ? 'Archived plots' : 'Land & plots' ?></h1><div class="sub"><?= (int)$pg['total'] ?> plot<?= $pg['total'] == 1 ? '' : 's' ?><?= $hasF ? ' match your filters' : '' ?></div></div>
  <div class="d-flex gap-2 flex-wrap"><a class="btn btn-light border" href="<?= e(url('land/estates')) ?>"><i class="bi bi-diagram-3 me-1"></i>Estates &amp; projects</a>
    <?php if (can('land.delete')): ?><a class="btn btn-light border" href="<?= e(url('land', $archived ? [] : ['archived' => 1])) ?>"><i class="bi bi-archive me-1"></i><?= $archived ? 'Active plots' : 'Archived' ?></a><?php endif; ?>
    <?php if (can('land.create')): ?><a class="btn btn-primary" href="<?= e(url('land/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add plot</a><?php endif; ?></div></div>
<div class="card">
  <div class="card-body border-bottom"><form class="row g-2" method="get"><input type="hidden" name="r" value="land"><?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <div class="col-lg-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Reference, plot, block, area, estate or seller"></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="estate"><option value="">All estates</option><?php foreach ($estates as $e): ?><option value="<?= (int)$e['id'] ?>"<?= selected($f['estate'], $e['id']) ?>><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (list_plot_statuses() as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-lg-2"><select class="form-select" name="title"><option value="">Any title status</option><?php foreach (list_title_status() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['title'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><?php if ($hasF): ?><a class="btn btn-light border" href="<?= e(url('land', $archived ? ['archived' => 1] : [])) ?>">Reset</a><?php endif; ?></div></form></div>
  <?php if (!$rows): ?><div class="empty"><i class="bi bi-map"></i><?= $hasF ? 'No plots match.' : 'No plots yet. Add a plot, or create an estate and generate its plots in one go.' ?></div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Plot</th><th>Estate / area</th><th>Size</th><th>Title</th><th class="text-end">Price</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr class="row-link" data-href="<?= e(url('land/view', ['id' => $r['id']])) ?>">
    <td class="cell-main"><a class="fw-semibold text-decoration-none" href="<?= e(url('land/view', ['id' => $r['id']])) ?>"><?= e(($r['block_number'] !== '' ? 'Block ' . $r['block_number'] . ', ' : '') . 'Plot ' . $r['plot_number']) ?></a><div class="small text-muted"><?= e($r['ref']) ?></div></td>
    <td data-label="Estate"><?= e($r['estate_name'] ?: $r['area']) ?></td><td data-label="Size"><?= e($r['size_text'] ?: '–') ?></td><td data-label="Title"><?= e(str_limit($r['title_status'], 26)) ?></td>
    <td data-label="Price" class="text-end"><?= e(money($r['list_price'])) ?></td><td data-label="Status"><?= plot_badge($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php View::partial('pager', ['pg' => $pg]); endif; ?>
</div>
