<?php
use function App\Support\csrf_field;
use function App\Support\e;
$item = $item ?? [];
$isEdit = !empty($item['id']);
$action = $isEdit ? '/admin/genres/' . (int)$item['id'] . '/update' : '/admin/genres';
?>
<h1><?= $isEdit ? 'Edit Genre' : 'Create Genre' ?></h1>
<div class="card"><form method="post" action="<?= e($action) ?>"><?= csrf_field() ?>
<label>Name <input name="name" required value="<?= e((string)($item['name'] ?? '')) ?>"></label>
<label>Slug <input name="slug" value="<?= e((string)($item['slug'] ?? '')) ?>"></label>
<label>Sort Order <input type="number" name="sort_order" value="<?= e((string)($item['sort_order'] ?? '0')) ?>"></label>
<label><input type="checkbox" name="is_active" value="1" <?= ((int)($item['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label><br><br>
<button class="btn" type="submit">Save</button></form>
<?php if ($isEdit): ?><form method="post" action="/admin/genres/<?= e((string)$item['id']) ?>/delete" style="margin-top:10px;"><?= csrf_field() ?><button class="danger" type="submit">Delete</button></form><?php endif; ?></div>
