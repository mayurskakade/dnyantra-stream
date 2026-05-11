<?php use function App\Support\e; ?>
<h1>Genres</h1>
<p><a class="btn" href="/admin/genres/create">Create Genre</a></p>
<div class="card"><table><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Sort</th><th>Active</th><th>Action</th></tr></thead><tbody><?php foreach (($items ?? []) as $item): ?><tr><td><?= e((string)$item['id']) ?></td><td><?= e((string)$item['name']) ?></td><td><?= e((string)$item['slug']) ?></td><td><?= e((string)$item['sort_order']) ?></td><td><?= ((int)($item['is_active'] ?? 0)) === 1 ? 'yes' : 'no' ?></td><td><a class="btn" href="/admin/genres/<?= e((string)$item['id']) ?>/edit">Edit</a></td></tr><?php endforeach; ?></tbody></table></div>
