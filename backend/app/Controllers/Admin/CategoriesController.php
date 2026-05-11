<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class CategoriesController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) { parent::__construct($pdo); }

    public function index(Request $request): void {
        $rows = $this->pdo()->query('SELECT id, name, slug, sort_order, is_active FROM categories ORDER BY sort_order ASC, id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->renderPage('Categories', 'categories/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Category', 'categories/create', ['item' => ['sort_order' => 0, 'is_active' => 1]]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Validation failed', ['name' => ['The field is required.']]);
        }

        $slug = trim((string)($input['slug'] ?? '')) ?: $this->slugify($name);
        $stmt = $this->pdo()->prepare('INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $slug, (int)($input['sort_order'] ?? 0), $this->boolFromInput($input['is_active'] ?? '1', true) ? 1 : 0]);

        $id = (int)$this->pdo()->lastInsertId();
        $this->audit($request, 'category.create', 'category', $id, null, $this->findOrFail('categories', $id));
        $this->flash('Category created.');
        $this->redirect('/admin/categories/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $this->renderPage('Edit Category', 'categories/edit', ['item' => $this->findOrFail('categories', $id)]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('categories', $id);
        $input = $request->input();
        $name = trim((string)($input['name'] ?? $before['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Validation failed', ['name' => ['The field is required.']]);
        }

        $slug = trim((string)($input['slug'] ?? '')) ?: (string)$before['slug'];
        $stmt = $this->pdo()->prepare('UPDATE categories SET name=?, slug=?, sort_order=?, is_active=?, updated_at=? WHERE id=?');
        $stmt->execute([$name, $slug, (int)($input['sort_order'] ?? $before['sort_order'] ?? 0), $this->boolFromInput($input['is_active'] ?? null, (bool)($before['is_active'] ?? true)) ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);

        $this->audit($request, 'category.update', 'category', $id, $before, $this->findOrFail('categories', $id));
        $this->flash('Category updated.');
        $this->redirect('/admin/categories/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('categories', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'category.delete', 'category', $id, $before, null);
        $this->flash('Category deleted.');
        $this->redirect('/admin/categories');
    }
}
