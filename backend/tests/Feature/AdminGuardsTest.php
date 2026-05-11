<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Admin\MoviesController;
use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminGuardsTest extends TestCase
{
    private array $serverBackup = [];
    private array $postBackup = [];
    private array $getBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function test_movie_update_rejects_public_visibility_when_rights_do_not_allow_it(): void
    {
        $pdo = $this->makePdo();
        $this->seedMovie($pdo, 1, 'private', 'personal_only', 0);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/movies/1/update';
        $_POST = [
            'title' => 'My Movie',
            'slug' => 'my-movie',
            'visibility' => 'public',
            'rights_status' => 'personal_only',
            'public_streaming_enabled' => '0',
        ];

        $request = new Request();
        $request->setAttribute('route.params', ['id' => '1']);
        $request->setAttribute('auth_user', ['id' => 101, 'role' => 'admin']);

        $controller = new MoviesController($pdo);

        try {
            $controller->update($request);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('validation_failed', $exception->errorCode());
        }

        $row = $pdo->query('SELECT visibility, rights_status, public_streaming_enabled FROM movies WHERE id = 1')?->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('private', $row['visibility']);
        $this->assertSame('personal_only', $row['rights_status']);
        $this->assertSame(0, (int) $row['public_streaming_enabled']);
    }

    public function test_movie_update_allows_public_visibility_when_rights_and_streaming_flags_are_valid(): void
    {
        $pdo = $this->makePdo();
        $this->seedMovie($pdo, 1, 'private', 'personal_only', 0);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/movies/1/update';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent06';
        $_POST = [
            'title' => 'My Movie',
            'slug' => 'my-movie',
            'visibility' => 'public',
            'rights_status' => 'owned_by_me',
            'public_streaming_enabled' => '1',
        ];

        $request = new Request();
        $request->setAttribute('route.params', ['id' => '1']);
        $request->setAttribute('auth_user', ['id' => 101, 'role' => 'admin']);

        $controller = new MoviesController($pdo);
        $controller->update($request);

        $row = $pdo->query('SELECT visibility, rights_status, public_streaming_enabled FROM movies WHERE id = 1')?->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('public', $row['visibility']);
        $this->assertSame('owned_by_me', $row['rights_status']);
        $this->assertSame(1, (int) $row['public_streaming_enabled']);

        $audit = $pdo->query("SELECT action, entity_type, admin_user_id FROM admin_audit_logs ORDER BY id DESC LIMIT 1")?->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($audit);
        $this->assertSame('movie.update', $audit['action']);
        $this->assertSame('movie', $audit['entity_type']);
        $this->assertSame(101, (int) $audit['admin_user_id']);
    }

    private function makePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE movies (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            slug TEXT NOT NULL,
            synopsis TEXT,
            duration_seconds INTEGER,
            media_asset_id INTEGER,
            poster_url TEXT,
            banner_url TEXT,
            release_year INTEGER,
            status TEXT,
            visibility TEXT,
            rights_status TEXT,
            public_streaming_enabled INTEGER,
            public_from TEXT,
            public_until TEXT,
            is_listed_publicly INTEGER,
            updated_at TEXT
        )');

        $pdo->exec('CREATE TABLE admin_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_user_id INTEGER,
            action TEXT,
            entity_type TEXT,
            entity_id TEXT,
            before_state TEXT,
            after_state TEXT,
            metadata TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT
        )');

        return $pdo;
    }

    private function seedMovie(PDO $pdo, int $id, string $visibility, string $rightsStatus, int $publicStreamingEnabled): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO movies (
                id, title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url,
                release_year, status, visibility, rights_status, public_streaming_enabled,
                public_from, public_until, is_listed_publicly, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $id,
            'My Movie',
            'my-movie',
            'Synopsis',
            3600,
            null,
            '',
            '',
            2024,
            'draft',
            $visibility,
            $rightsStatus,
            $publicStreamingEnabled,
            null,
            null,
            0,
            gmdate('Y-m-d H:i:s'),
        ]);
    }
}
