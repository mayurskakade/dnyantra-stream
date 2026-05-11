<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class SeasonsController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $rows = $this->pdo()->query('SELECT se.id, se.series_id, se.season_number, se.title, s.title AS series_title FROM seasons se LEFT JOIN series s ON s.id = se.series_id ORDER BY se.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->renderPage('Seasons', 'seasons/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Season', 'seasons/create', ['item' => ['season_number' => 1]]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $seriesId = (int)($input['series_id'] ?? 0);
        $number = (int)($input['season_number'] ?? 0);
        if ($seriesId <= 0 || $number <= 0) {
            throw new ValidationException('Validation failed', ['series_id' => ['series_id and season_number must be positive.']]);
        }

        $stmt = $this->pdo()->prepare('INSERT INTO seasons (series_id, season_number, title) VALUES (?, ?, ?)');
        $stmt->execute([$seriesId, $number, (string)($input['title'] ?? '')]);

        $id = (int)$this->pdo()->lastInsertId();
        $after = $this->findOrFail('seasons', $id);
        $this->audit($request, 'season.create', 'season', $id, null, $after);
        $this->flash('Season created.');
        $this->redirect('/admin/seasons/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $item = $this->findOrFail('seasons', $id);
        $this->renderPage('Edit Season', 'seasons/edit', ['item' => $item]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('seasons', $id);
        $input = $request->input();

        $seriesId = (int)($input['series_id'] ?? $before['series_id']);
        $number = (int)($input['season_number'] ?? $before['season_number']);
        if ($seriesId <= 0 || $number <= 0) {
            throw new ValidationException('Validation failed', ['series_id' => ['series_id and season_number must be positive.']]);
        }

        $stmt = $this->pdo()->prepare('UPDATE seasons SET series_id=?, season_number=?, title=?, updated_at=? WHERE id=?');
        $stmt->execute([$seriesId, $number, (string)($input['title'] ?? $before['title'] ?? ''), gmdate('Y-m-d H:i:s'), $id]);

        $after = $this->findOrFail('seasons', $id);
        $this->audit($request, 'season.update', 'season', $id, $before, $after);
        $this->flash('Season updated.');
        $this->redirect('/admin/seasons/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('seasons', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM seasons WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'season.delete', 'season', $id, $before, null);
        $this->flash('Season deleted.');
        $this->redirect('/admin/seasons');
    }
}
