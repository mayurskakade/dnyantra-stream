<?php
use function App\Support\e;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php if (is_array($flash)): ?>
  <p class="<?= ($flash['type'] ?? '') === 'error' ? 'error' : 'ok' ?>"><?= e($flash['message'] ?? '') ?></p>
<?php endif; ?>
