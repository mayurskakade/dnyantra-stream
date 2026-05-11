<?php
use function App\Support\csrf_field;
use function App\Support\e;
?>
<h1>Access Management</h1>
<div class="card">
  <h2>Assign Access</h2>
  <form method="post" action="/admin/access/assign">
    <?= csrf_field() ?>
    <div class="row">
      <label>User ID <input type="number" min="1" name="user_id" required></label>
      <label>Playable ID <input type="number" min="1" name="playable_id" required></label>
    </div>
    <div class="row">
      <label>Playable Type <select name="playable_type"><?php foreach (['movie','series','episode'] as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label>Access Type <select name="access_type"><?php foreach (['owner','granted','temporary'] as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Expires At (UTC datetime) <input name="expires_at"></label>
    <button class="btn" type="submit">Assign</button>
  </form>
</div>

<div class="card" style="margin-top:14px;">
  <h2>Create Share Link</h2>
  <form method="post" action="/admin/share-links/create">
    <?= csrf_field() ?>
    <div class="row">
      <label>Playable ID <input type="number" min="1" name="playable_id" required></label>
      <label>Playable Type <select name="playable_type"><?php foreach (['movie','series','episode'] as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="row">
      <label>Max Uses <input type="number" min="1" name="max_uses"></label>
      <label>Expires At (UTC datetime) <input name="expires_at"></label>
    </div>
    <button class="btn" type="submit">Create Share Link (JSON)</button>
  </form>
</div>

<div class="card" style="margin-top:14px;">
  <h2>Existing Access Rows</h2>
  <table><thead><tr><th>ID</th><th>User</th><th>Movie</th><th>Series</th><th>Episode</th><th>Type</th><th>Expires</th><th>Action</th></tr></thead><tbody>
  <?php foreach (($items ?? []) as $item): ?>
    <tr>
      <td><?= e((string)$item['id']) ?></td><td><?= e((string)$item['user_id']) ?></td><td><?= e((string)($item['movie_id'] ?? '')) ?></td><td><?= e((string)($item['series_id'] ?? '')) ?></td><td><?= e((string)($item['episode_id'] ?? '')) ?></td><td><?= e((string)$item['access_type']) ?></td><td><?= e((string)($item['expires_at'] ?? '')) ?></td>
      <td>
        <form method="post" action="/admin/access/revoke"><?= csrf_field() ?><input type="hidden" name="access_id" value="<?= e((string)$item['id']) ?>"><button class="danger" type="submit">Revoke</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>

<div class="card" style="margin-top:14px;">
  <h2>Share Links</h2>
  <table><thead><tr><th>ID</th><th>Movie</th><th>Series</th><th>Episode</th><th>Active</th><th>Uses</th><th>Action</th></tr></thead><tbody>
  <?php foreach (($share_links ?? []) as $row): ?>
    <tr>
      <td><?= e((string)$row['id']) ?></td><td><?= e((string)($row['movie_id'] ?? '')) ?></td><td><?= e((string)($row['series_id'] ?? '')) ?></td><td><?= e((string)($row['episode_id'] ?? '')) ?></td><td><?= ((int)($row['is_active'] ?? 0)) === 1 ? 'yes' : 'no' ?></td><td><?= e((string)($row['used_count'] ?? 0)) ?>/<?= e((string)($row['max_uses'] ?? '∞')) ?></td>
      <td><form method="post" action="/admin/share-links/revoke"><?= csrf_field() ?><input type="hidden" name="share_link_id" value="<?= e((string)$row['id']) ?>"><button class="danger" type="submit">Revoke</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
