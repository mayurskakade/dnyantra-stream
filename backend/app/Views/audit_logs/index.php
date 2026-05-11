<?php
use function App\Support\e;
$filters = $filters ?? [];
?>
<h1>Audit Logs</h1>
<div class="card">
  <form method="get" action="/admin/audit-logs">
    <div class="row">
      <label>Admin User ID <input type="number" name="admin_user_id" value="<?= e((string)($filters['admin_user_id'] ?? '')) ?>"></label>
      <label>Entity Type <input name="entity_type" value="<?= e((string)($filters['entity_type'] ?? '')) ?>"></label>
    </div>
    <div class="row">
      <label>Action <input name="action" value="<?= e((string)($filters['action'] ?? '')) ?>"></label>
      <label>Date From (YYYY-MM-DD) <input name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"></label>
    </div>
    <div class="row">
      <label>Date To (YYYY-MM-DD) <input name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"></label>
      <label>Per Page <input type="number" min="1" max="100" name="per_page" value="<?= e((string)($per_page ?? 20)) ?>"></label>
    </div>
    <button class="btn" type="submit">Filter</button>
  </form>
</div>

<div class="card" style="margin-top:14px;">
  <p class="muted">Page <?= e((string)($page ?? 1)) ?> of <?= e((string)($total_pages ?? 1)) ?> (total <?= e((string)($total ?? 0)) ?>)</p>
  <table>
    <thead><tr><th>ID</th><th>Admin</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>IP</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $item): ?>
      <tr>
        <td><?= e((string)$item['id']) ?></td>
        <td><?= e((string)($item['admin_user_id'] ?? '')) ?></td>
        <td><?= e((string)($item['action'] ?? '')) ?></td>
        <td><?= e((string)($item['entity_type'] ?? '')) ?></td>
        <td><?= e((string)($item['entity_id'] ?? '')) ?></td>
        <td><?= e((string)($item['ip_address'] ?? '')) ?></td>
        <td><?= e((string)($item['created_at'] ?? '')) ?></td>
      </tr>
      <tr><td colspan="7" class="muted">before: <?= e((string)($item['before_state'] ?? '')) ?><br>after: <?= e((string)($item['after_state'] ?? '')) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php
  $totalPages = $total_pages ?? 1;
  $basePath = '/admin/audit-logs';
  include __DIR__ . '/../partials/pagination.php';
  ?>
</div>
