<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use PDO;

class DashboardController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $pdo = $this->pdo();
        $counts = [
            'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'movies' => (int)$pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn(),
            'series' => (int)$pdo->query('SELECT COUNT(*) FROM series')->fetchColumn(),
            'episodes' => (int)$pdo->query('SELECT COUNT(*) FROM episodes')->fetchColumn(),
            'sessions_today' => 0,
        ];

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM playback_sessions WHERE DATE(started_at) = ?');
        $stmt->execute([gmdate('Y-m-d')]);
        $counts['sessions_today'] = (int)$stmt->fetchColumn();

        $this->renderPage('Dashboard', 'dashboard/index', ['counts' => $counts]);
    }
}
