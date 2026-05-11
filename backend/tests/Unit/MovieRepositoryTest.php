<?php
namespace Tests\Unit;

use App\Repositories\MovieRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MovieRepositoryTest extends TestCase {
    private PDO $pdo;
    private MovieRepository $repository;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE media_assets (id INTEGER PRIMARY KEY, provider TEXT, provider_uid TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE movies (
            id INTEGER PRIMARY KEY,
            title TEXT,
            slug TEXT,
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
            is_listed_publicly INTEGER
        )');
        $this->pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, sort_order INTEGER, is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE genres (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, sort_order INTEGER, is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE content_categories (category_id INTEGER, movie_id INTEGER)');
        $this->pdo->exec('CREATE TABLE content_genres (genre_id INTEGER, movie_id INTEGER)');

        $this->pdo->exec("INSERT INTO media_assets (id, provider, provider_uid, status) VALUES (1, 'cloudflare_stream', 'uid-1', 'ready')");
        $this->pdo->exec("INSERT INTO media_assets (id, provider, provider_uid, status) VALUES (2, 'cloudflare_stream', 'uid-2', 'ready')");

        $this->pdo->exec("INSERT INTO categories (id, name, slug, sort_order, is_active) VALUES (10, 'Drama', 'drama', 1, 1)");
        $this->pdo->exec("INSERT INTO categories (id, name, slug, sort_order, is_active) VALUES (11, 'Comedy', 'comedy', 2, 1)");
        $this->pdo->exec("INSERT INTO genres (id, name, slug, sort_order, is_active) VALUES (20, 'Thriller', 'thriller', 1, 1)");

        $this->pdo->exec("INSERT INTO movies (id, title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (1, 'Movie One', 'movie-one', 'synopsis', 120, 1, 'poster-1', 'banner-1', 2022, 'published', 'authenticated', 'owned_by_me', 1, 1)");
        $this->pdo->exec("INSERT INTO movies (id, title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (2, 'Movie Two', 'movie-two', 'synopsis', 95, 2, 'poster-2', 'banner-2', 2024, 'published', 'public', 'owned_by_me', 1, 1)");
        $this->pdo->exec("INSERT INTO movies (id, title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, is_listed_publicly) VALUES (3, 'Movie Draft', 'movie-draft', 'synopsis', 60, NULL, 'poster-3', 'banner-3', 2025, 'draft', 'private', 'personal_only', 0, 0)");

        $this->pdo->exec('INSERT INTO content_categories (category_id, movie_id) VALUES (10, 1)');
        $this->pdo->exec('INSERT INTO content_categories (category_id, movie_id) VALUES (11, 2)');
        $this->pdo->exec('INSERT INTO content_genres (genre_id, movie_id) VALUES (20, 1)');

        $this->repository = new MovieRepository($this->pdo);
    }

    public function test_find_for_catalog_returns_published_movies_sorted_by_year_desc(): void {
        $rows = $this->repository->findForCatalog();

        $this->assertCount(2, $rows);
        $this->assertSame('movie-two', $rows[0]['slug']);
        $this->assertSame('movie-one', $rows[1]['slug']);
    }

    public function test_find_for_catalog_applies_category_and_genre_filters(): void {
        $categoryRows = $this->repository->findForCatalog(['category_id' => 10]);
        $genreRows = $this->repository->findForCatalog(['genre_id' => 20]);

        $this->assertCount(1, $categoryRows);
        $this->assertSame('movie-one', $categoryRows[0]['slug']);

        $this->assertCount(1, $genreRows);
        $this->assertSame('movie-one', $genreRows[0]['slug']);
    }

    public function test_find_by_slug_and_relationship_lists_return_expected_rows(): void {
        $movie = $this->repository->findBySlug('movie-one');

        $this->assertNotNull($movie);
        $this->assertSame('uid-1', $movie['provider_uid']);

        $categories = $this->repository->listCategories(1);
        $genres = $this->repository->listGenres(1);

        $this->assertCount(1, $categories);
        $this->assertSame('Drama', $categories[0]['name']);
        $this->assertCount(1, $genres);
        $this->assertSame('Thriller', $genres[0]['name']);
    }
}
