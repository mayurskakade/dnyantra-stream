<?php use function App\Support\e; ?>
<h1>Series</h1>
<p><a class="btn" href="/admin/series/create">Create Series</a></p>
<div class="card"><table><thead><tr><th>ID</th><th>Title</th><th>Slug</th><th>Status</th><th>Visibility</th><th>Rights</th><th>Public</th><th>Action</th></tr></thead><tbody>
<?php foreach (($items ?? []) as $item): ?>
<tr>
<td><?= e((string)$item['id']) ?></td><td><?= e($item['title'] ?? '') ?></td><td><?= e($item['slug'] ?? '') ?></td><td><?= e($item['status'] ?? '') ?></td><td><?= e($item['visibility'] ?? '') ?></td><td><?= e($item['rights_status'] ?? '') ?></td><td><?= ((int)($item['public_streaming_enabled'] ?? 0)) === 1 ? 'yes' : 'no' ?></td><td><a class="btn" href="/admin/series/<?= e((string)$item['id']) ?>/edit">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
