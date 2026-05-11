<?php use function App\Support\e; ?>
<?php if (($totalPages ?? 1) > 1): ?>
  <div class="actions" style="margin-top:10px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a class="btn" href="<?= e($basePath . '?page=' . $i) ?>"><?= e((string)$i) ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
