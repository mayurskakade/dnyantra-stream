<?php use function App\Support\csrf_field; ?>
<nav>
  <div class="wrap">
    <a href="/admin/dashboard">Dashboard</a>
    <a href="/admin/movies">Movies</a>
    <a href="/admin/series">Series</a>
    <a href="/admin/seasons">Seasons</a>
    <a href="/admin/episodes">Episodes</a>
    <a href="/admin/categories">Categories</a>
    <a href="/admin/genres">Genres</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/access">Access</a>
    <a href="/admin/audit-logs">Audit Logs</a>
    <form method="post" action="/admin/logout" style="display:inline;margin-left:10px;">
      <?= csrf_field() ?>
      <button class="btn" type="submit">Logout</button>
    </form>
  </div>
</nav>
