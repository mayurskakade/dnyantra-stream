<?php use function App\Support\e; ?>
<h1>Users</h1>
<p><a class="btn" href="/admin/users/create">Create User</a></p>
<div class="card"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Action</th></tr></thead><tbody>
<?php foreach (($items ?? []) as $item): ?><tr><td><?= e((string)$item['id']) ?></td><td><?= e((string)$item['name']) ?></td><td><?= e((string)$item['email']) ?></td><td><?= e((string)$item['role']) ?></td><td><?= ((int)($item['is_active'] ?? 0)) === 1 ? 'yes' : 'no' ?></td><td><a class="btn" href="/admin/users/<?= e((string)$item['id']) ?>/edit">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
