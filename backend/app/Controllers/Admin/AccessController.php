<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use PDO;

class AccessController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) { parent::__construct($pdo); }

    public function index(Request $request): void {
        $rows = $this->fetchAll('SELECT id, user_id, movie_id, series_id, episode_id, access_type, expires_at, created_at FROM user_content_access ORDER BY id DESC LIMIT 200');
        $shares = $this->fetchAll('SELECT id, token_hash, movie_id, series_id, episode_id, is_active, expires_at, max_uses, used_count FROM share_links ORDER BY id DESC LIMIT 100');
        $this->renderPage('Access Control', 'access/index', ['items' => $rows, 'share_links' => $shares]);
    }

    public function assign(Request $request): void {
        $input = $request->input();
        $userId = (int)($input['user_id'] ?? 0);
        $type = (string)($input['playable_type'] ?? 'movie');
        $playableId = (int)($input['playable_id'] ?? 0);

        if ($userId <= 0 || $playableId <= 0 || !in_array($type, ['movie', 'series', 'episode'], true)) {
            throw new ValidationException('Validation failed', ['user_id' => ['user_id, playable_type and playable_id are required.']]);
        }

        $columns = ['movie_id' => null, 'series_id' => null, 'episode_id' => null];
        $columns[$type . '_id'] = $playableId;

        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_content_access (user_id, movie_id, series_id, episode_id, granted_by_user_id, access_type, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE access_type=VALUES(access_type), expires_at=VALUES(expires_at), granted_by_user_id=VALUES(granted_by_user_id), updated_at=NOW()'
        );
        $stmt->execute([
            $userId,
            $columns['movie_id'],
            $columns['series_id'],
            $columns['episode_id'],
            $this->authAdminId($request),
            (string)($input['access_type'] ?? 'granted'),
            ($input['expires_at'] ?? '') !== '' ? (string)$input['expires_at'] : null,
        ]);

        $id = (int)$this->pdo()->lastInsertId();
        $this->audit($request, 'access.assign', 'user_content_access', $id > 0 ? $id : null, null, [
            'user_id' => $userId,
            'playable_type' => $type,
            'playable_id' => $playableId,
        ]);

        $this->flash('Access assigned.');
        $this->redirect('/admin/access');
    }

    public function revoke(Request $request): void {
        $input = $request->input();
        $accessId = (int)($input['access_id'] ?? 0);
        if ($accessId <= 0) {
            throw new ValidationException('Validation failed', ['access_id' => ['access_id is required.']]);
        }

        $stmt = $this->pdo()->prepare('SELECT * FROM user_content_access WHERE id = ? LIMIT 1');
        $stmt->execute([$accessId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $delete = $this->pdo()->prepare('DELETE FROM user_content_access WHERE id = ?');
        $delete->execute([$accessId]);

        $this->audit($request, 'access.revoke', 'user_content_access', $accessId, $before, null);
        $this->flash('Access revoked.');
        $this->redirect('/admin/access');
    }

    public function createShareLink(Request $request): void {
        $input = $request->input();
        $type = (string)($input['playable_type'] ?? 'movie');
        $playableId = (int)($input['playable_id'] ?? 0);

        if ($playableId <= 0 || !in_array($type, ['movie', 'series', 'episode'], true)) {
            throw new ValidationException('Validation failed', ['playable_id' => ['playable_type and playable_id are required.']]);
        }

        $columns = ['movie_id' => null, 'series_id' => null, 'episode_id' => null];
        $columns[$type . '_id'] = $playableId;

        $rawToken = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO share_links (token_hash, movie_id, series_id, episode_id, created_by_user_id, is_active, max_uses, used_count, expires_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, 0, ?)' 
        );
        $stmt->execute([
            $tokenHash,
            $columns['movie_id'],
            $columns['series_id'],
            $columns['episode_id'],
            $this->authAdminId($request),
            $this->normalizeNullableInt($input['max_uses'] ?? null),
            ($input['expires_at'] ?? '') !== '' ? (string)$input['expires_at'] : null,
        ]);

        $id = (int)$this->pdo()->lastInsertId();
        $this->audit($request, 'share_link.create', 'share_link', $id, null, [
            'id' => $id,
            'playable_type' => $type,
            'playable_id' => $playableId,
        ]);

        Response::json(['share_link_id' => $id, 'share_token' => $rawToken]);
    }

    public function revokeShareLink(Request $request): void {
        $shareLinkId = (int)($request->input()['share_link_id'] ?? 0);
        if ($shareLinkId <= 0) {
            throw new ValidationException('Validation failed', ['share_link_id' => ['share_link_id is required.']]);
        }

        $stmt = $this->pdo()->prepare('SELECT * FROM share_links WHERE id = ? LIMIT 1');
        $stmt->execute([$shareLinkId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $update = $this->pdo()->prepare('UPDATE share_links SET is_active = 0, revoked_at = ?, updated_at = ? WHERE id = ?');
        $now = gmdate('Y-m-d H:i:s');
        $update->execute([$now, $now, $shareLinkId]);

        $stmt = $this->pdo()->prepare('SELECT * FROM share_links WHERE id = ? LIMIT 1');
        $stmt->execute([$shareLinkId]);
        $after = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $this->audit($request, 'share_link.revoke', 'share_link', $shareLinkId, $before, $after);
        $this->flash('Share link revoked.');
        $this->redirect('/admin/access');
    }
}
