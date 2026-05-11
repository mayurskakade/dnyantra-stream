<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use PDO;

class DashboardController extends BaseAdminController {
    public function __construct(?PDO $pdo = null) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $counts = [
            'users' => $this->fetchCount('SELECT COUNT(*) FROM users'),
            'movies' => $this->fetchCount('SELECT COUNT(*) FROM movies'),
            'series' => $this->fetchCount('SELECT COUNT(*) FROM series'),
            'episodes' => $this->fetchCount('SELECT COUNT(*) FROM episodes'),
            'sessions_today' => 0,
        ];

        $counts['sessions_today'] = $this->fetchCount(
            'SELECT COUNT(*) FROM playback_sessions WHERE DATE(started_at) = ?',
            [gmdate('Y-m-d')]
        );

        $this->renderPage('Dashboard', 'dashboard/index', ['counts' => $counts]);
    }
}
