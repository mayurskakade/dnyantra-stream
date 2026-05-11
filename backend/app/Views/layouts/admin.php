<?php use function App\Support\e; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Admin') ?></title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif; margin: 0; color: #1f2937; }
    .wrap { max-width: 1120px; margin: 0 auto; padding: 16px; }
    nav { background: #111827; color: #fff; }
    nav a { color: #fff; margin-right: 12px; text-decoration: none; font-size: 14px; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #fff; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 8px; }
    input, select, textarea { width: 100%; padding: 8px; margin: 4px 0 10px; box-sizing: border-box; }
    .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .actions { display: flex; gap: 8px; align-items: center; }
    .danger { background: #b91c1c; color: #fff; border: 0; padding: 8px 10px; border-radius: 6px; }
    .btn { background: #111827; color: #fff; border: 0; padding: 8px 10px; border-radius: 6px; text-decoration: none; display: inline-block; }
    .muted { color: #6b7280; font-size: 13px; }
    .error { color: #b91c1c; }
    .ok { color: #065f46; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../partials/nav.php'; ?>
  <div class="wrap">
    <?php include __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?? '' ?>
  </div>
</body>
</html>
