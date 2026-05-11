<?php use function App\Support\e; ?>
<h1>Admin Dashboard</h1>
<div class="card">
  <div class="row">
    <div><strong>Users</strong><br><?= e((string)($counts['users'] ?? 0)) ?></div>
    <div><strong>Movies</strong><br><?= e((string)($counts['movies'] ?? 0)) ?></div>
    <div><strong>Series</strong><br><?= e((string)($counts['series'] ?? 0)) ?></div>
    <div><strong>Episodes</strong><br><?= e((string)($counts['episodes'] ?? 0)) ?></div>
    <div><strong>Sessions Today</strong><br><?= e((string)($counts['sessions_today'] ?? 0)) ?></div>
  </div>
</div>
