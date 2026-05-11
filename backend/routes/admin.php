<?php
use App\Controllers\Admin\AccessController;
use App\Controllers\Admin\AdminAuthController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EpisodesController;
use App\Controllers\Admin\GenresController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\MoviesController;
use App\Controllers\Admin\SeasonsController;
use App\Controllers\Admin\SeriesController;
use App\Controllers\Admin\UsersController;
use App\Middleware\AdminSessionMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\RateLimiterFactory;
use App\Exceptions\AuthorizationException;

$adminAuthController = new AdminAuthController();
$dashboardController = new DashboardController();
$moviesController = new MoviesController();
$seriesController = new SeriesController();
$seasonsController = new SeasonsController();
$episodesController = new EpisodesController();
$categoriesController = new CategoriesController();
$genresController = new GenresController();
$usersController = new UsersController();
$mediaController = new MediaController();
$accessController = new AccessController();
$auditLogController = new AuditLogController();

$adminSessionMiddleware = new AdminSessionMiddleware();
$csrfMiddleware = new CsrfMiddleware();
$rateLimiter = RateLimiterFactory::create();

$clientIp = static function (): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
};

$adminLoginRateLimit = static function ($request) use ($rateLimiter, $clientIp): bool {
    $email = strtolower(trim((string)($request->input()['email'] ?? '')));
    $result = $rateLimiter->hit('admin:login:' . $clientIp() . ':' . $email, 5, 60);
    if ($result->allowed) {
        return true;
    }

    header('Retry-After: ' . $result->retryAfterSeconds);
    throw new AuthorizationException('Rate limit exceeded', 429, 'rate_limited');
};

$requireAdmin = fn($request) => $adminSessionMiddleware->handle($request);
$requireCsrf = fn($request) => $csrfMiddleware->handle($request);

// --- Agent 02: admin auth/session ---
$router->get('/admin/login', fn($request) => $adminAuthController->showLogin($request));
$router->post('/admin/login', fn($request) => $adminAuthController->login($request), [$requireCsrf, $adminLoginRateLimit]);
$router->post('/admin/logout', fn($request) => $adminAuthController->logout($request), [$requireAdmin, $requireCsrf]);

// --- Agent 06: admin portal ---
$router->get('/admin/dashboard', fn($request) => $dashboardController->index($request), [$requireAdmin]);

$router->get('/admin/movies', fn($request) => $moviesController->index($request), [$requireAdmin]);
$router->get('/admin/movies/create', fn($request) => $moviesController->create($request), [$requireAdmin]);
$router->post('/admin/movies', fn($request) => $moviesController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/movies/{id}/edit', fn($request) => $moviesController->edit($request), [$requireAdmin]);
$router->post('/admin/movies/{id}/update', fn($request) => $moviesController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/movies/{id}/delete', fn($request) => $moviesController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/series', fn($request) => $seriesController->index($request), [$requireAdmin]);
$router->get('/admin/series/create', fn($request) => $seriesController->create($request), [$requireAdmin]);
$router->post('/admin/series', fn($request) => $seriesController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/series/{id}/edit', fn($request) => $seriesController->edit($request), [$requireAdmin]);
$router->post('/admin/series/{id}/update', fn($request) => $seriesController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/series/{id}/delete', fn($request) => $seriesController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/seasons', fn($request) => $seasonsController->index($request), [$requireAdmin]);
$router->get('/admin/seasons/create', fn($request) => $seasonsController->create($request), [$requireAdmin]);
$router->post('/admin/seasons', fn($request) => $seasonsController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/seasons/{id}/edit', fn($request) => $seasonsController->edit($request), [$requireAdmin]);
$router->post('/admin/seasons/{id}/update', fn($request) => $seasonsController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/seasons/{id}/delete', fn($request) => $seasonsController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/episodes', fn($request) => $episodesController->index($request), [$requireAdmin]);
$router->get('/admin/episodes/create', fn($request) => $episodesController->create($request), [$requireAdmin]);
$router->post('/admin/episodes', fn($request) => $episodesController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/episodes/{id}/edit', fn($request) => $episodesController->edit($request), [$requireAdmin]);
$router->post('/admin/episodes/{id}/update', fn($request) => $episodesController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/episodes/{id}/delete', fn($request) => $episodesController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/categories', fn($request) => $categoriesController->index($request), [$requireAdmin]);
$router->get('/admin/categories/create', fn($request) => $categoriesController->create($request), [$requireAdmin]);
$router->post('/admin/categories', fn($request) => $categoriesController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/categories/{id}/edit', fn($request) => $categoriesController->edit($request), [$requireAdmin]);
$router->post('/admin/categories/{id}/update', fn($request) => $categoriesController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/categories/{id}/delete', fn($request) => $categoriesController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/genres', fn($request) => $genresController->index($request), [$requireAdmin]);
$router->get('/admin/genres/create', fn($request) => $genresController->create($request), [$requireAdmin]);
$router->post('/admin/genres', fn($request) => $genresController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/genres/{id}/edit', fn($request) => $genresController->edit($request), [$requireAdmin]);
$router->post('/admin/genres/{id}/update', fn($request) => $genresController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/genres/{id}/delete', fn($request) => $genresController->delete($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/users', fn($request) => $usersController->index($request), [$requireAdmin]);
$router->get('/admin/users/create', fn($request) => $usersController->create($request), [$requireAdmin]);
$router->post('/admin/users', fn($request) => $usersController->store($request), [$requireAdmin, $requireCsrf]);
$router->get('/admin/users/{id}/edit', fn($request) => $usersController->edit($request), [$requireAdmin]);
$router->post('/admin/users/{id}/update', fn($request) => $usersController->update($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/users/{id}/delete', fn($request) => $usersController->delete($request), [$requireAdmin, $requireCsrf]);

$router->post('/admin/media/upload-url', fn($request) => $mediaController->requestUploadUrl($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/media/{id}/sync', fn($request) => $mediaController->syncStatus($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/media/{id}/delete', fn($request) => $mediaController->deleteAsset($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/access', fn($request) => $accessController->index($request), [$requireAdmin]);
$router->post('/admin/access/assign', fn($request) => $accessController->assign($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/access/revoke', fn($request) => $accessController->revoke($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/share-links/create', fn($request) => $accessController->createShareLink($request), [$requireAdmin, $requireCsrf]);
$router->post('/admin/share-links/revoke', fn($request) => $accessController->revokeShareLink($request), [$requireAdmin, $requireCsrf]);

$router->get('/admin/audit-logs', fn($request) => $auditLogController->index($request), [$requireAdmin]);
