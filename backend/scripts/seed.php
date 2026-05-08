<?php
require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$pdo = App\Core\Database::pdo();

$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), is_active=VALUES(is_active)');
  $stmt->execute(['Admin','admin@example.com',password_hash('ChangeMe123!', PASSWORD_DEFAULT),'admin']);

  $pdo->prepare('INSERT INTO categories (name,slug,sort_order,is_active) VALUES (?,?,0,1) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute(['Personal Library','personal-library']);
  $pdo->prepare('INSERT INTO genres (name,slug,sort_order,is_active) VALUES (?,?,0,1) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute(['Documentary','documentary']);

  $pdo->prepare("INSERT INTO movies (title,slug,status,visibility,rights_status,public_streaming_enabled,is_listed_publicly) VALUES (?,?,?,?,?,0,0)
    ON DUPLICATE KEY UPDATE title=VALUES(title),status='draft',visibility='private',rights_status='personal_only'")
    ->execute(['Sample Private Draft','sample-private-draft','draft','private','personal_only']);

  $pdo->commit();
  echo "seeded\n";
} catch (Throwable $e) {
  $pdo->rollBack();
  throw $e;
}
