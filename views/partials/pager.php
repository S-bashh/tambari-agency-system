<?php /** @var array $pg */
if ($pg['total'] === 0) return;
$from = $pg['offset'] + 1; $to = min($pg['total'], $pg['offset'] + $pg['per']);
$p = $pg['page']; $n = $pg['pages'];
$win = array_values(array_unique(array_filter([1, $p - 1, $p, $p + 1, $n], fn($x) => $x >= 1 && $x <= $n)));
sort($win);
?>
<div class="pager">
  <div>Showing <strong><?= $from ?>–<?= $to ?></strong> of <strong><?= (int)$pg['total'] ?></strong></div>
  <?php if ($n > 1): ?>
  <ul class="pagination pagination-sm">
    <li class="page-item <?= $p <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(page_url($p - 1)) ?>">‹</a></li>
    <?php $last = 0; foreach ($win as $x): if ($x - $last > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <li class="page-item <?= $x === $p ? 'active' : '' ?>"><a class="page-link" href="<?= e(page_url($x)) ?>"><?= $x ?></a></li>
    <?php $last = $x; endforeach; ?>
    <li class="page-item <?= $p >= $n ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(page_url($p + 1)) ?>">›</a></li>
  </ul>
  <?php endif; ?>
</div>
