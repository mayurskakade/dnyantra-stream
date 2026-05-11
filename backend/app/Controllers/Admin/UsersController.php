<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class UsersController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) { parent::__construct($pdo); }

    public function index(Request $request): void {
        $rows = $this->fetchAll('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY id DESC LIMIT 200');
        $this->renderPage('Users', 'users/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create User', 'users/create', ['item' => ['role' => 'viewer', 'is_active' => 1]]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $role = (string)($input['role'] ?? 'viewer');

        if ($name === '' || $email === '' || $password === '') {
            throw new ValidationException('Validation failed', ['name' => ['name, email and password are required.']]);
        }

        if (!in_array($role, ['admin', 'viewer'], true)) {
            throw new ValidationException('Validation failed', ['role' => ['Invalid role.']]);
        }

        $stmt = $this->pdo()->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $this->boolFromInput($input['is_active'] ?? '1', true) ? 1 : 0]);

        $id = (int)$this->pdo()->lastInsertId();
        $after = $this->findOrFail('users', $id);
        unset($after['password_hash']);
        $this->audit($request, 'user.create', 'user', $id, null, $after);
        $this->flash('User created.');
        $this->redirect('/admin/users/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $item = $this->findOrFail('users', $id);
        unset($item['password_hash']);
        $this->renderPage('Edit User', 'users/edit', ['item' => $item]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('users', $id);
        $input = $request->input();

        $name = trim((string)($input['name'] ?? $before['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? $before['email'] ?? '')));
        $role = (string)($input['role'] ?? $before['role'] ?? 'viewer');
        if ($name === '' || $email === '') {
            throw new ValidationException('Validation failed', ['name' => ['name and email are required.']]);
        }
        if (!in_array($role, ['admin', 'viewer'], true)) {
            throw new ValidationException('Validation failed', ['role' => ['Invalid role.']]);
        }

        $password = (string)($input['password'] ?? '');
        if ($password !== '') {
            $stmt = $this->pdo()->prepare('UPDATE users SET name=?, email=?, role=?, is_active=?, password_hash=?, updated_at=? WHERE id=?');
            $stmt->execute([$name, $email, $role, $this->boolFromInput($input['is_active'] ?? null, (bool)($before['is_active'] ?? true)) ? 1 : 0, password_hash($password, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $id]);
        } else {
            $stmt = $this->pdo()->prepare('UPDATE users SET name=?, email=?, role=?, is_active=?, updated_at=? WHERE id=?');
            $stmt->execute([$name, $email, $role, $this->boolFromInput($input['is_active'] ?? null, (bool)($before['is_active'] ?? true)) ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);
        }

        $after = $this->findOrFail('users', $id);
        unset($before['password_hash'], $after['password_hash']);
        $this->audit($request, 'user.update', 'user', $id, $before, $after);
        $this->flash('User updated.');
        $this->redirect('/admin/users/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('users', $id);
        unset($before['password_hash']);
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'user.delete', 'user', $id, $before, null);
        $this->flash('User deleted.');
        $this->redirect('/admin/users');
    }
}
