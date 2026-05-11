<?php
use function App\Support\csrf_field;
use function App\Support\e;
$item = $item ?? [];
$isEdit = !empty($item['id']);
$action = $isEdit ? '/admin/episodes/' . (int)$item['id'] . '/update' : '/admin/episodes';
?>
<h1><?= $isEdit ? 'Edit Episode' : 'Create Episode' ?></h1>
<div class="card">
<form method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <div class="row">
    <label>Series ID <input type="number" min="1" name="series_id" required value="<?= e((string)($item['series_id'] ?? '')) ?>"></label>
    <label>Season ID <input type="number" min="1" name="season_id" required value="<?= e((string)($item['season_id'] ?? '')) ?>"></label>
  </div>
  <div class="row">
    <label>Episode Number <input type="number" min="1" name="episode_number" required value="<?= e((string)($item['episode_number'] ?? '1')) ?>"></label>
    <label>Media Asset ID <input type="number" min="1" name="media_asset_id" value="<?= e((string)($item['media_asset_id'] ?? '')) ?>"></label>
  </div>
  <label>Title <input name="title" required value="<?= e((string)($item['title'] ?? '')) ?>"></label>
  <label>Synopsis <textarea name="synopsis"><?= e((string)($item['synopsis'] ?? '')) ?></textarea></label>
  <div class="row">
    <label>Duration Seconds <input type="number" min="0" name="duration_seconds" value="<?= e((string)($item['duration_seconds'] ?? '')) ?>"></label>
    <label>Status <select name="status"><?php foreach (['draft','published','archived'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['status'] ?? 'draft') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  </div>
  <label>Visibility <select name="visibility"><?php foreach (['private','authenticated','unlisted','public'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['visibility'] ?? 'private') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <button class="btn" type="submit">Save</button>
</form>
<?php if ($isEdit): ?><form method="post" action="/admin/episodes/<?= e((string)$item['id']) ?>/delete" style="margin-top:10px;"><?= csrf_field() ?><button class="danger" type="submit">Delete</button></form><?php endif; ?>
</div>
