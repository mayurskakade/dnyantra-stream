<?php
require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); $dotenv->safeLoad();
$pdo = App\Core\Database::pdo();
$sql = file_get_contents(__DIR__.'/../database/migrations/001_init.sql');
$pdo->exec($sql);
echo "migrated\n";
