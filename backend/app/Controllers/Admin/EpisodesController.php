<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Exceptions\ValidationException;
use PDO;

class EpisodesController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $rows = $this->pdo()->query('SELECT e.id, e.series_id, e.season_id, e.episode_number, e.title, e.status, e.visibility FROM episodes e ORDER BY e.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->renderPage('Episodes', 'episodes/index', ['items' => $rows]);
    }

    public function create(Request $request): void {
        $this->renderPage('Create Episode', 'episodes/create', ['item' => ['status' => 'draft', 'visibility' => 'private']]);
    }

    public function store(Request $request): void {
        $input = $request->input();
        $seriesId = (int)($input['series_id'] ?? 0);
        $seasonId = (int)($input['season_id'] ?? 0);
        $episodeNumber = (int)($input['episode_number'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));

        if ($seriesId <= 0 || $seasonId <= 0 || $episodeNumber <= 0 || $title === '') {
            throw new ValidationException('Validation failed', ['title' => ['series_id, season_id, episode_number, and title are required.']]);
        }

        $visibility = (string)($input['visibility'] ?? 'private');
        if ($visibility === 'public') {
            $this->assertSeriesPublicReady($seriesId);
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO episodes (series_id, season_id, episode_number, title, synopsis, duration_seconds, media_asset_id, status, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $seriesId,
            $seasonId,
            $episodeNumber,
            $title,
            (string)($input['synopsis'] ?? ''),
            $this->normalizeNullableInt($input['duration_seconds'] ?? null),
            $this->normalizeNullableInt($input['media_asset_id'] ?? null),
            (string)($input['status'] ?? 'draft'),
            $visibility,
        ]);

        $id = (int)$this->pdo()->lastInsertId();
        $after = $this->findOrFail('episodes', $id);
        $this->audit($request, 'episode.create', 'episode', $id, null, $after);
        $this->flash('Episode created.');
        $this->redirect('/admin/episodes/' . $id . '/edit');
    }

    public function edit(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $item = $this->findOrFail('episodes', $id);
        $this->renderPage('Edit Episode', 'episodes/edit', ['item' => $item]);
    }

    public function update(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('episodes', $id);
        $input = $request->input();

        $seriesId = (int)($input['series_id'] ?? $before['series_id']);
        $seasonId = (int)($input['season_id'] ?? $before['season_id']);
        $episodeNumber = (int)($input['episode_number'] ?? $before['episode_number']);
        $title = trim((string)($input['title'] ?? $before['title'] ?? ''));

        if ($seriesId <= 0 || $seasonId <= 0 || $episodeNumber <= 0 || $title === '') {
            throw new ValidationException('Validation failed', ['title' => ['series_id, season_id, episode_number, and title are required.']]);
        }

        $visibility = (string)($input['visibility'] ?? $before['visibility'] ?? 'private');
        if ($visibility === 'public') {
            $this->assertSeriesPublicReady($seriesId);
        }

        $stmt = $this->pdo()->prepare(
            'UPDATE episodes SET series_id=?, season_id=?, episode_number=?, title=?, synopsis=?, duration_seconds=?, media_asset_id=?, status=?, visibility=?, updated_at=? WHERE id=?'
        );
        $stmt->execute([
            $seriesId,
            $seasonId,
            $episodeNumber,
            $title,
            (string)($input['synopsis'] ?? $before['synopsis'] ?? ''),
            $this->normalizeNullableInt($input['duration_seconds'] ?? $before['duration_seconds'] ?? null),
            $this->normalizeNullableInt($input['media_asset_id'] ?? $before['media_asset_id'] ?? null),
            (string)($input['status'] ?? $before['status'] ?? 'draft'),
            $visibility,
            gmdate('Y-m-d H:i:s'),
            $id,
        ]);

        $after = $this->findOrFail('episodes', $id);
        $this->audit($request, 'episode.update', 'episode', $id, $before, $after);
        $this->flash('Episode updated.');
        $this->redirect('/admin/episodes/' . $id . '/edit');
    }

    public function delete(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $before = $this->findOrFail('episodes', $id);
        $stmt = $this->pdo()->prepare('DELETE FROM episodes WHERE id = ?');
        $stmt->execute([$id]);
        $this->audit($request, 'episode.delete', 'episode', $id, $before, null);
        $this->flash('Episode deleted.');
        $this->redirect('/admin/episodes');
    }

    private function assertSeriesPublicReady(int $seriesId): void {
        $series = $this->findOrFail('series', $seriesId);
        $visibilityFields = $this->normalizeVisibilityFields([
            'visibility' => 'public',
            'rights_status' => (string)($series['rights_status'] ?? 'personal_only'),
            'public_streaming_enabled' => (int)($series['public_streaming_enabled'] ?? 0),
        ], $series);

        if ($visibilityFields['visibility'] !== 'public') {
            throw new ValidationException('Validation failed', ['visibility' => ['Series is not configured for public streaming.']]);
        }
    }
}
