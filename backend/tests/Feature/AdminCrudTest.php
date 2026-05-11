<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Admin\MoviesController;
use App\Core\Request;
use App\Repositories\AdminAuditLogRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminCrudTest extends TestCase
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

    public function test_movie_store_defaults_visibility_and_rights(): void
    {
        $pdo = $this->makePdo();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/movies';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent06';
        $_POST = [
            'title' => 'Defaulted Movie',
            'slug' => 'defaulted-movie',
        ];

        $request = new Request();
        $request->setAttribute('auth_user', ['id' => 501, 'role' => 'admin']);

        $controller = new MoviesController($pdo);
        $controller->store($request);

        $row = $pdo->query('SELECT visibility, rights_status, public_streaming_enabled FROM movies ORDER BY id DESC LIMIT 1')
            ?->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('private', $row['visibility']);
        $this->assertSame('personal_only', $row['rights_status']);
        $this->assertSame(0, (int) $row['public_streaming_enabled']);
    }

    public function test_audit_logs_filter_by_entity_type_and_paginate(): void
    {
        $pdo = $this->makePdo();
        $repository = new AdminAuditLogRepository($pdo);

        $repository->insert([
            'admin_user_id' => 10,
            'action' => 'movie.create',
            'entity_type' => 'movie',
            'entity_id' => 11,
        ]);
        $repository->insert([
            'admin_user_id' => 10,
            'action' => 'series.create',
            'entity_type' => 'series',
            'entity_id' => 22,
        ]);
        $repository->insert([
            'admin_user_id' => 10,
            'action' => 'movie.update',
            'entity_type' => 'movie',
            'entity_id' => 33,
        ]);

        $page1 = $repository->paginate(['entity_type' => 'movie'], 1, 1);
        $this->assertSame(2, $page1['total']);
        $this->assertCount(1, $page1['data']);
        $this->assertSame('movie', $page1['data'][0]['entity_type']);

        $page2 = $repository->paginate(['entity_type' => 'movie'], 2, 1);
        $this->assertSame(2, $page2['total']);
        $this->assertCount(1, $page2['data']);
        $this->assertSame('movie', $page2['data'][0]['entity_type']);
    }

    private function makePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE movies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
}
