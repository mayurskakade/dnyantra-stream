<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class MoviesController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $rows = $this->fetchAll('SELECT id, title, slug, status, visibility, rights_status, public_streaming_enabled, updated_at FROM movies ORDER BY id DESC LIMIT 200');
        $this->renderPage('Movies', 'movies/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Movie', 'movies/create', ['item' => [
            'status' => 'draft',
            'visibility' => 'private',
            'rights_status' => 'personal_only',
            'public_streaming_enabled' => 0,
            'is_listed_publicly' => 0,
        ]]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException('Validation failed', ['title' => ['The field is required.']]);
        }

        $payload = $this->buildPayload($input);
        $payload['slug'] = trim((string)($input['slug'] ?? '')) ?: $this->slugify($title);
        $payload['title'] = $title;

        $visibility = $this->normalizeVisibilityFields($payload, []);
        $payload = array_merge($payload, $visibility);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO movies (title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, public_from, public_until, is_listed_publicly)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
        );
        $stmt->execute([
            $payload['title'],
            $payload['slug'],
            $payload['synopsis'],
            $payload['duration_seconds'],
            $payload['media_asset_id'],
            $payload['poster_url'],
            $payload['banner_url'],
            $payload['release_year'],
            $payload['status'],
            $payload['visibility'],
            $payload['rights_status'],
            $payload['public_streaming_enabled'],
            $payload['public_from'],
            $payload['public_until'],
            $payload['is_listed_publicly'],
        ]);

        $id = (int)$this->pdo()->lastInsertId();
        $after = $this->findOrFail('movies', $id);
        $this->audit($request, 'movie.create', 'movie', $id, null, $after);
        $this->flash('Movie created.');
        $this->redirect('/admin/movies/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $item = $this->findOrFail('movies', $id);
        $this->renderPage('Edit Movie', 'movies/edit', ['item' => $item]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('movies', $id);
        $input = $request->input();

        $payload = $this->buildPayload($input, $before);
        $payload['title'] = trim((string)($input['title'] ?? $before['title'] ?? ''));
        if ($payload['title'] === '') {
            throw new ValidationException('Validation failed', ['title' => ['The field is required.']]);
        }

        $slugInput = trim((string)($input['slug'] ?? ''));
        $payload['slug'] = $slugInput !== '' ? $slugInput : (string)$before['slug'];

        $visibility = $this->normalizeVisibilityFields($payload, $before);
        $payload = array_merge($payload, $visibility);

        $stmt = $this->pdo()->prepare(
            'UPDATE movies SET title=?, slug=?, synopsis=?, duration_seconds=?, media_asset_id=?, poster_url=?, banner_url=?, release_year=?, status=?, visibility=?, rights_status=?, public_streaming_enabled=?, public_from=?, public_until=?, is_listed_publicly=?, updated_at=? WHERE id=?'
        );
        $stmt->execute([
            $payload['title'],
            $payload['slug'],
            $payload['synopsis'],
            $payload['duration_seconds'],
            $payload['media_asset_id'],
            $payload['poster_url'],
            $payload['banner_url'],
            $payload['release_year'],
            $payload['status'],
            $payload['visibility'],
            $payload['rights_status'],
            $payload['public_streaming_enabled'],
            $payload['public_from'],
            $payload['public_until'],
            $payload['is_listed_publicly'],
            gmdate('Y-m-d H:i:s'),
            $id,
        ]);

        $after = $this->findOrFail('movies', $id);
        $this->audit($request, 'movie.update', 'movie', $id, $before, $after);
        $this->flash('Movie updated.');
        $this->redirect('/admin/movies/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('movies', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM movies WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'movie.delete', 'movie', $id, $before, null);
        $this->flash('Movie deleted.');
        $this->redirect('/admin/movies');
    }

    private function buildPayload(array $input, array $existing = []): array {
        return [
            'synopsis' => (string)($input['synopsis'] ?? $existing['synopsis'] ?? ''),
            'duration_seconds' => $this->normalizeNullableInt($input['duration_seconds'] ?? $existing['duration_seconds'] ?? null),
            'media_asset_id' => $this->normalizeNullableInt($input['media_asset_id'] ?? $existing['media_asset_id'] ?? null),
            'poster_url' => (string)($input['poster_url'] ?? $existing['poster_url'] ?? ''),
            'banner_url' => (string)($input['banner_url'] ?? $existing['banner_url'] ?? ''),
            'release_year' => $this->normalizeNullableInt($input['release_year'] ?? $existing['release_year'] ?? null),
            'status' => (string)($input['status'] ?? $existing['status'] ?? 'draft'),
            'visibility' => (string)($input['visibility'] ?? $existing['visibility'] ?? 'private'),
            'rights_status' => (string)($input['rights_status'] ?? $existing['rights_status'] ?? 'personal_only'),
            'public_streaming_enabled' => $this->boolFromInput($input['public_streaming_enabled'] ?? null, (bool)($existing['public_streaming_enabled'] ?? false)) ? 1 : 0,
            'public_from' => ($input['public_from'] ?? $existing['public_from'] ?? '') !== '' ? (string)($input['public_from'] ?? $existing['public_from']) : null,
            'public_until' => ($input['public_until'] ?? $existing['public_until'] ?? '') !== '' ? (string)($input['public_until'] ?? $existing['public_until']) : null,
            'is_listed_publicly' => $this->boolFromInput($input['is_listed_publicly'] ?? null, (bool)($existing['is_listed_publicly'] ?? false)) ? 1 : 0,
        ];
    }
}
