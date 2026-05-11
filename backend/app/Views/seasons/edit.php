<?php
use function App\Support\csrf_field;
use function App\Support\e;
$item = $item ?? [];
$isEdit = !empty($item['id']);
$action = $isEdit ? '/admin/seasons/' . (int)$item['id'] . '/update' : '/admin/seasons';
?>
<h1><?= $isEdit ? 'Edit Season' : 'Create Season' ?></h1>
<div class="card">
<form method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <label>Series ID <input type="number" min="1" required name="series_id" value="<?= e((string)($item['series_id'] ?? '')) ?>"></label>
  <label>Season Number <input type="number" min="1" required name="season_number" value="<?= e((string)($item['season_number'] ?? '1')) ?>"></label>
  <label>Title <input name="title" value="<?= e((string)($item['title'] ?? '')) ?>"></label>
  <button class="btn" type="submit">Save</button>
</form>
<?php if ($isEdit): ?><form method="post" action="/admin/seasons/<?= e((string)$item['id']) ?>/delete" style="margin-top:10px;"><?= csrf_field() ?><button class="danger" type="submit">Delete</button></form><?php endif; ?>
</div>
