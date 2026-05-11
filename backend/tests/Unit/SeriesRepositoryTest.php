<?php
namespace Tests\Unit;

use App\Repositories\SeriesRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class SeriesRepositoryTest extends TestCase {
    private PDO $pdo;
    private SeriesRepository $repository;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE series (
            id INTEGER PRIMARY KEY,
            title TEXT,
            slug TEXT,
            synopsis TEXT,
            poster_url TEXT,
            banner_url TEXT,
            release_year INTEGER,
            status TEXT,
            visibility TEXT,
            rights_status TEXT,
            public_streaming_enabled INTEGER,
            public_from TEXT,
            public_until TEXT,
            is_listed_publicly INTEGER
        )');
        $this->pdo->exec('CREATE TABLE content_categories (category_id INTEGER, series_id INTEGER)');
        $this->pdo->exec('CREATE TABLE content_genres (genre_id INTEGER, series_id INTEGER)');

        $this->pdo->exec("INSERT INTO series (id, title, slug, synopsis, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (1, 'Series One', 'series-one', 's1', 'poster-1', 'banner-1', 2023, 'published', 'authenticated', 'owned_by_me', 1, 1)");
        $this->pdo->exec("INSERT INTO series (id, title, slug, synopsis, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (2, 'Series Two', 'series-two', 's2', 'poster-2', 'banner-2', 2025, 'published', 'public', 'owned_by_me', 1, 1)");
        $this->pdo->exec("INSERT INTO series (id, title, slug, synopsis, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (3, 'Series Draft', 'series-draft', 's3', 'poster-3', 'banner-3', 2021, 'draft', 'private', 'personal_only', 0, 0)");

        $this->pdo->exec('INSERT INTO content_categories (category_id, series_id) VALUES (101, 1)');
        $this->pdo->exec('INSERT INTO content_genres (genre_id, series_id) VALUES (201, 2)');

        $this->repository = new SeriesRepository($this->pdo);
    }

    public function test_find_for_catalog_returns_only_published_series_sorted_desc(): void {
        $rows = $this->repository->findForCatalog();

        $this->assertCount(2, $rows);
        $this->assertSame('series-two', $rows[0]['slug']);
        $this->assertSame('series-one', $rows[1]['slug']);
    }

    public function test_find_for_catalog_applies_category_and_genre_filters(): void {
        $categoryRows = $this->repository->findForCatalog(['category_id' => 101]);
        $genreRows = $this->repository->findForCatalog(['genre_id' => 201]);

        $this->assertCount(1, $categoryRows);
        $this->assertSame('series-one', $categoryRows[0]['slug']);

        $this->assertCount(1, $genreRows);
        $this->assertSame('series-two', $genreRows[0]['slug']);
    }

    public function test_find_by_slug_returns_single_series(): void {
        $series = $this->repository->findBySlug('series-two');

        $this->assertNotNull($series);
        $this->assertSame('Series Two', $series['title']);
        $this->assertSame('published', $series['status']);
    }
}
