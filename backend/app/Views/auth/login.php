<?php
use function App\Support\csrf_field;
use function App\Support\e;
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Login</title></head>
<body style="font-family:Arial,sans-serif;max-width:460px;margin:40px auto;padding:16px;">
  <h1>Admin Login</h1>
  <?php if (!empty($error)): ?><p style="color:#b91c1c"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/login">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" required value="<?= e($email ?? '') ?>"></label><br><br>
    <label>Password <input type="password" name="password" required></label><br><br>
    <button type="submit">Login</button>
  </form>
</body>
</html>
