<?php use function App\Support\e; ?>
<h1>Episodes</h1>
<p><a class="btn" href="/admin/episodes/create">Create Episode</a></p>
<div class="card"><table><thead><tr><th>ID</th><th>Series</th><th>Season</th><th>#</th><th>Title</th><th>Status</th><th>Visibility</th><th>Action</th></tr></thead><tbody>
<?php foreach (($items ?? []) as $item): ?><tr><td><?= e((string)$item['id']) ?></td><td><?= e((string)$item['series_id']) ?></td><td><?= e((string)$item['season_id']) ?></td><td><?= e((string)$item['episode_number']) ?></td><td><?= e((string)$item['title']) ?></td><td><?= e((string)$item['status']) ?></td><td><?= e((string)$item['visibility']) ?></td><td><a class="btn" href="/admin/episodes/<?= e((string)$item['id']) ?>/edit">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
