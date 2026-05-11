<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class GenresController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) { parent::__construct($pdo); }

    public function index(Request $request): void {
        $rows = $this->fetchAll('SELECT id, name, slug, sort_order, is_active FROM genres ORDER BY sort_order ASC, id DESC');
        $this->renderPage('Genres', 'genres/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Genre', 'genres/create', ['item' => ['sort_order' => 0, 'is_active' => 1]]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Validation failed', ['name' => ['The field is required.']]);
        }

        $slug = trim((string)($input['slug'] ?? '')) ?: $this->slugify($name);
        $stmt = $this->pdo()->prepare('INSERT INTO genres (name, slug, sort_order, is_active) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $slug, (int)($input['sort_order'] ?? 0), $this->boolFromInput($input['is_active'] ?? '1', true) ? 1 : 0]);

        $id = (int)$this->pdo()->lastInsertId();
        $this->audit($request, 'genre.create', 'genre', $id, null, $this->findOrFail('genres', $id));
        $this->flash('Genre created.');
        $this->redirect('/admin/genres/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $this->renderPage('Edit Genre', 'genres/edit', ['item' => $this->findOrFail('genres', $id)]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('genres', $id);
        $input = $request->input();
        $name = trim((string)($input['name'] ?? $before['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Validation failed', ['name' => ['The field is required.']]);
        }

        $slug = trim((string)($input['slug'] ?? '')) ?: (string)$before['slug'];
        $stmt = $this->pdo()->prepare('UPDATE genres SET name=?, slug=?, sort_order=?, is_active=?, updated_at=? WHERE id=?');
        $stmt->execute([$name, $slug, (int)($input['sort_order'] ?? $before['sort_order'] ?? 0), $this->boolFromInput($input['is_active'] ?? null, (bool)($before['is_active'] ?? true)) ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);

        $this->audit($request, 'genre.update', 'genre', $id, $before, $this->findOrFail('genres', $id));
        $this->flash('Genre updated.');
        $this->redirect('/admin/genres/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('genres', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM genres WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'genre.delete', 'genre', $id, $before, null);
        $this->flash('Genre deleted.');
        $this->redirect('/admin/genres');
    }
}
