<?php use function App\Support\e; ?>
<h1>Seasons</h1>
<p><a class="btn" href="/admin/seasons/create">Create Season</a></p>
<div class="card"><table><thead><tr><th>ID</th><th>Series ID</th><th>Series</th><th>Season #</th><th>Title</th><th>Action</th></tr></thead><tbody>
<?php foreach (($items ?? []) as $item): ?><tr><td><?= e((string)$item['id']) ?></td><td><?= e((string)$item['series_id']) ?></td><td><?= e((string)($item['series_title'] ?? '')) ?></td><td><?= e((string)$item['season_number']) ?></td><td><?= e((string)($item['title'] ?? '')) ?></td><td><a class="btn" href="/admin/seasons/<?= e((string)$item['id']) ?>/edit">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
