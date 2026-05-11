<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Api\CatalogController;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Exceptions\NotFoundException;
use App\Services\CatalogService;
use PHPUnit\Framework\TestCase;

class CatalogEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        http_response_code(200);
    }

    public function test_list_movies_endpoint_returns_paginated_payload(): void
    {
        $service = $this->createMock(CatalogService::class);
        $service->expects($this->once())
            ->method('listMovies')
            ->with(['category_id' => 3], 2, 15, ['id' => 7, 'role' => 'viewer', 'is_active' => true])
            ->willReturn([
                'data' => [['id' => 1, 'slug' => 'movie-1', 'title' => 'Movie 1', 'year' => 2024, 'poster_url' => 'p', 'duration_seconds' => 90]],
                'page' => 2,
                'per_page' => 15,
                'total' => 30,
            ]);

        $_GET = ['page' => '2', 'per_page' => '15', 'category_id' => '3'];
        $_SERVER['REQUEST_URI'] = '/api/movies?page=2&per_page=15&category_id=3';

        $controller = new CatalogController($service);
        $request = new Request();
        $request->setAttribute('auth_user', ['id' => 7, 'role' => 'viewer', 'is_active' => true]);

        $response = $this->capture(static fn () => $controller->listMovies($request));

        $this->assertSame(200, $response['status']);
        $this->assertSame(2, $response['body']['page']);
        $this->assertSame(15, $response['body']['per_page']);
        $this->assertSame(30, $response['body']['total']);
        $this->assertSame('movie-1', $response['body']['data'][0]['slug']);
    }

    public function test_private_movie_detail_maps_to_404_not_found_response(): void
    {
        $service = $this->createMock(CatalogService::class);
        $service->expects($this->once())
            ->method('getMovie')
            ->with('private-movie', ['id' => 42, 'role' => 'viewer', 'is_active' => true])
            ->willThrowException(new NotFoundException('Movie not found'));

        $controller = new CatalogController($service);
        $request = new Request();
        $request->setAttribute('auth_user', ['id' => 42, 'role' => 'viewer', 'is_active' => true]);
        $request->setAttribute('route.params', ['slug' => 'private-movie']);

        $_SERVER['REQUEST_URI'] = '/api/movies/private-movie';

        $response = $this->captureWithErrorHandler(static fn () => $controller->showMovie($request));

        $this->assertSame(404, $response['status']);
        $this->assertSame('not_found', $response['body']['error']['code']);
    }

    public function test_public_catalog_endpoint_returns_payload_shape(): void
    {
        $service = $this->createMock(CatalogService::class);
        $service->expects($this->once())
            ->method('publicCatalog')
            ->with([], 1, 20)
            ->willReturn([
                'data' => [['id' => 9, 'slug' => 'public-movie', 'title' => 'Public Movie', 'year' => 2020, 'poster_url' => 'poster', 'duration_seconds' => 100]],
                'page' => 1,
                'per_page' => 20,
                'total' => 1,
            ]);

        $_SERVER['REQUEST_URI'] = '/public/catalog';
        $controller = new CatalogController($service);

        $response = $this->capture(static fn () => $controller->publicCatalog(new Request()));

        $this->assertSame(200, $response['status']);
        $this->assertSame(1, $response['body']['total']);
        $this->assertSame('public-movie', $response['body']['data'][0]['slug']);
    }

    private function capture(callable $callback): array
    {
        ob_start();
        $callback();
        $output = (string)ob_get_clean();

        return [
            'status' => http_response_code(),
            'body' => json_decode($output, true),
        ];
    }

    private function captureWithErrorHandler(callable $callback): array
    {
        $errorHandler = new ErrorHandler();

        ob_start();
        try {
            $callback();
        } catch (\Throwable $throwable) {
            $errorHandler->handleException($throwable);
        }
        $output = (string)ob_get_clean();

        return [
            'status' => http_response_code(),
            'body' => json_decode($output, true),
        ];
    }
}
