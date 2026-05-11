<?php
use function App\Support\csrf_field;
use function App\Support\e;
$item = $item ?? [];
$isEdit = !empty($item['id']);
$action = $isEdit ? '/admin/users/' . (int)$item['id'] . '/update' : '/admin/users';
?>
<h1><?= $isEdit ? 'Edit User' : 'Create User' ?></h1>
<div class="card">
<form method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <label>Name <input name="name" required value="<?= e((string)($item['name'] ?? '')) ?>"></label>
  <label>Email <input type="email" name="email" required value="<?= e((string)($item['email'] ?? '')) ?>"></label>
  <label>Password <?= $isEdit ? '(optional)' : '' ?> <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>></label>
  <label>Role
    <select name="role">
      <?php foreach (['admin','viewer'] as $v): ?><option value="<?= e($v) ?>" <?= (($item['role'] ?? 'viewer') === $v) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><input type="checkbox" name="is_active" value="1" <?= ((int)($item['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label><br><br>
  <button class="btn" type="submit">Save</button>
</form>
<?php if ($isEdit): ?><form method="post" action="/admin/users/<?= e((string)$item['id']) ?>/delete" style="margin-top:10px;"><?= csrf_field() ?><button class="danger" type="submit">Delete</button></form><?php endif; ?>
</div>
