<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class SeriesController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $rows = $this->pdo()->query('SELECT id, title, slug, status, visibility, rights_status, public_streaming_enabled, updated_at FROM series ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->renderPage('Series', 'series/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Series', 'series/create', ['item' => [
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
        $payload['title'] = $title;
        $payload['slug'] = trim((string)($input['slug'] ?? '')) ?: $this->slugify($title);
        $visibility = $this->normalizeVisibilityFields($payload, []);
        $payload = array_merge($payload, $visibility);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO series (title, slug, synopsis, poster_url, banner_url, release_year, status, visibility, rights_status, public_streaming_enabled, public_from, public_until, is_listed_publicly)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
        );
        $stmt->execute([
            $payload['title'],
            $payload['slug'],
            $payload['synopsis'],
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
        $after = $this->findOrFail('series', $id);
        $this->audit($request, 'series.create', 'series', $id, null, $after);
        $this->flash('Series created.');
        $this->redirect('/admin/series/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $item = $this->findOrFail('series', $id);
        $this->renderPage('Edit Series', 'series/edit', ['item' => $item]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('series', $id);
        $input = $request->input();

        $payload = $this->buildPayload($input, $before);
        $payload['title'] = trim((string)($input['title'] ?? $before['title'] ?? ''));
        if ($payload['title'] === '') {
            throw new ValidationException('Validation failed', ['title' => ['The field is required.']]);
        }

        $payload['slug'] = trim((string)($input['slug'] ?? '')) ?: (string)$before['slug'];
        $visibility = $this->normalizeVisibilityFields($payload, $before);
        $payload = array_merge($payload, $visibility);

        $stmt = $this->pdo()->prepare(
            'UPDATE series SET title=?, slug=?, synopsis=?, poster_url=?, banner_url=?, release_year=?, status=?, visibility=?, rights_status=?, public_streaming_enabled=?, public_from=?, public_until=?, is_listed_publicly=?, updated_at=? WHERE id=?'
        );
        $stmt->execute([
            $payload['title'],
            $payload['slug'],
            $payload['synopsis'],
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

        $after = $this->findOrFail('series', $id);
        $this->audit($request, 'series.update', 'series', $id, $before, $after);
        $this->flash('Series updated.');
        $this->redirect('/admin/series/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('series', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM series WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'series.delete', 'series', $id, $before, null);
        $this->flash('Series deleted.');
        $this->redirect('/admin/series');
    }

    private function buildPayload(array $input, array $existing = []): array {
        return [
            'synopsis' => (string)($input['synopsis'] ?? $existing['synopsis'] ?? ''),
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
