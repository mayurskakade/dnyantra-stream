<?php
use function App\Support\csrf_field;
use function App\Support\e;
$item = $item ?? [];
$isEdit = !empty($item['id']);
$action = $isEdit ? '/admin/movies/' . (int)$item['id'] . '/update' : '/admin/movies';
?>
<h1><?= $isEdit ? 'Edit Movie' : 'Create Movie' ?></h1>
<div class="card">
<form method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <label>Title <input name="title" required value="<?= e($item['title'] ?? '') ?>"></label>
  <label>Slug <input name="slug" value="<?= e($item['slug'] ?? '') ?>"></label>
  <label>Synopsis <textarea name="synopsis"><?= e($item['synopsis'] ?? '') ?></textarea></label>
  <div class="row">
    <label>Duration Seconds <input name="duration_seconds" type="number" min="0" value="<?= e((string)($item['duration_seconds'] ?? '')) ?>"></label>
    <label>Media Asset ID <input name="media_asset_id" type="number" min="1" value="<?= e((string)($item['media_asset_id'] ?? '')) ?>"></label>
  </div>
  <div class="row">
    <label>Poster URL <input name="poster_url" value="<?= e($item['poster_url'] ?? '') ?>"></label>
    <label>Banner URL <input name="banner_url" value="<?= e($item['banner_url'] ?? '') ?>"></label>
  </div>
  <div class="row">
    <label>Release Year <input name="release_year" type="number" min="1900" max="3000" value="<?= e((string)($item['release_year'] ?? '')) ?>"></label>
    <label>Status
      <select name="status">
        <?php foreach (['draft','published','archived'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['status'] ?? 'draft') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="row">
    <label>Visibility
      <select name="visibility">
        <?php foreach (['private','authenticated','unlisted','public'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['visibility'] ?? 'private') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Rights Status
      <select name="rights_status">
        <?php foreach (['personal_only','owned_by_me','licensed_private','licensed_public','public_domain','creative_commons'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['rights_status'] ?? 'personal_only') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="row">
    <label><input type="checkbox" name="public_streaming_enabled" value="1" <?= ((int)($item['public_streaming_enabled'] ?? 0) === 1) ? 'checked' : '' ?>> Public Streaming Enabled</label>
    <label><input type="checkbox" name="is_listed_publicly" value="1" <?= ((int)($item['is_listed_publicly'] ?? 0) === 1) ? 'checked' : '' ?>> Listed Publicly</label>
  </div>
  <div class="row">
    <label>Public From (UTC datetime) <input name="public_from" value="<?= e((string)($item['public_from'] ?? '')) ?>"></label>
    <label>Public Until (UTC datetime) <input name="public_until" value="<?= e((string)($item['public_until'] ?? '')) ?>"></label>
  </div>
  <button class="btn" type="submit">Save</button>
</form>
<?php if ($isEdit): ?>
<form method="post" action="/admin/movies/<?= e((string)$item['id']) ?>/delete" style="margin-top:10px;">
  <?= csrf_field() ?>
  <button class="danger" type="submit">Delete</button>
</form>
<?php endif; ?>
</div>
