<?php
require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$pdo = App\Core\Database::pdo();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS migration_tracking (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      migration_name VARCHAR(255) NOT NULL UNIQUE,
      applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
);

$migrationFiles = glob(__DIR__.'/../database/migrations/*.sql') ?: [];
natsort($migrationFiles);

$applied = $pdo->query('SELECT migration_name FROM migration_tracking')->fetchAll(PDO::FETCH_COLUMN);
$appliedMap = array_fill_keys($applied, true);

$appliedCount = 0;
foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile);
    if (isset($appliedMap[$migrationName])) {
        continue;
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration file: {$migrationName}");
    }

    // Forward-only runner: applied migrations are tracked and not rolled back in this script.
    $pdo->exec($sql);
    $stmt = $pdo->prepare('INSERT INTO migration_tracking (migration_name) VALUES (?)');
    $stmt->execute([$migrationName]);
    $appliedCount++;
    echo "applied {$migrationName}\n";
}

if ($appliedCount === 0) {
    echo "no pending migrations\n";
}
