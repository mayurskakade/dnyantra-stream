<?php
use App\Controllers\Api\AuthController;
use App\Controllers\Api\CatalogController;
use App\Controllers\Api\PlaybackController;
use App\Controllers\Api\WatchProgressController;
use App\Middleware\AuthMiddleware;

$authController = new AuthController();
$catalogController = new CatalogController();
$playbackController = new PlaybackController();
$watchProgressController = new WatchProgressController();
$authMiddleware = new AuthMiddleware();

$router->post('/api/auth/login', fn($request)=>$authController->login($request));
$router->post('/api/auth/refresh', fn($request)=>$authController->refresh($request));
$router->post('/api/auth/logout', fn($request)=>$authController->logout($request));
$router->get('/api/me', fn($request)=>$authController->me($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/api/playback-sessions', fn($request)=>$playbackController->create($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/public/playback-sessions', fn($request)=>$playbackController->createPublic($request));

// --- Agent 03: catalog ---
$router->get('/api/home', fn($request)=>$catalogController->home($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/categories', fn($request)=>$catalogController->listCategories(), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/genres', fn($request)=>$catalogController->listGenres(), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/movies', fn($request)=>$catalogController->listMovies($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/movies/{slug}', fn($request)=>$catalogController->showMovie($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/series', fn($request)=>$catalogController->listSeries($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/series/{slug}', fn($request)=>$catalogController->showSeries($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/seasons/{id}/episodes', fn($request)=>$catalogController->listEpisodes($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->get('/api/continue-watching', fn($request)=>$catalogController->continueWatching($request), [fn($request)=>$authMiddleware->handle($request)]);

// --- Agent 05: watch progress ---
$router->post('/api/watch-progress', fn($request)=>$watchProgressController->upsert($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/api/watch-progress/mark-watched', fn($request)=>$watchProgressController->markWatched($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/api/watch-progress/clear', fn($request)=>$watchProgressController->clear($request), [fn($request)=>$authMiddleware->handle($request)]);

// --- Agent 05: public route table ---
require __DIR__ . '/public.php';
