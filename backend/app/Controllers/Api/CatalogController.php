<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\CatalogService;

class CatalogController {
    public function __construct(
        private readonly CatalogService $catalogService = new CatalogService(),
        private readonly Validator $validator = new Validator(),
    ) {}

    public function home(Request $request): void {
        $user = $request->attribute('auth_user');
        Response::json($this->catalogService->home($user));
    }

    public function listCategories(): void {
        Response::json(['data' => $this->catalogService->listCategories()]);
    }

    public function listGenres(): void {
        Response::json(['data' => $this->catalogService->listGenres()]);
    }

    public function listMovies(Request $request): void {
        [$filters, $page, $perPage] = $this->extractListRequest($request);
        $user = $request->attribute('auth_user');
        Response::json($this->catalogService->listMovies($filters, $page, $perPage, $user));
    }

    public function showMovie(Request $request): void {
        $slug = (string)$request->getRouteParam('slug');
        $user = $request->attribute('auth_user');
        Response::json($this->catalogService->getMovie($slug, $user));
    }

    public function listSeries(Request $request): void {
        [$filters, $page, $perPage] = $this->extractListRequest($request);
        $user = $request->attribute('auth_user');
        Response::json($this->catalogService->listSeries($filters, $page, $perPage, $user));
    }

    public function showSeries(Request $request): void {
        $slug = (string)$request->getRouteParam('slug');
        $user = $request->attribute('auth_user');
        Response::json($this->catalogService->getSeries($slug, $user));
    }

    public function listEpisodes(Request $request): void {
        $seasonId = (int)$request->getRouteParam('id');
        $user = $request->attribute('auth_user');
        Response::json(['data' => $this->catalogService->listEpisodes($seasonId, $user)]);
    }

    public function continueWatching(Request $request): void {
        $user = $request->attribute('auth_user') ?? [];
        Response::json(['data' => $this->catalogService->continueWatching($user)]);
    }

    public function publicCatalog(Request $request): void {
        [$filters, $page, $perPage] = $this->extractListRequest($request);
        Response::json($this->catalogService->publicCatalog($filters, $page, $perPage));
    }

    public function publicMovie(Request $request): void {
        $slug = (string)$request->getRouteParam('slug');
        Response::json($this->catalogService->publicMovie($slug));
    }

    public function publicSeries(Request $request): void {
        $slug = (string)$request->getRouteParam('slug');
        Response::json($this->catalogService->publicSeries($slug));
    }

    private function extractListRequest(Request $request): array {
        $input = $this->validator->validate($request->input(), [
            'page' => 'int|min:1|max:10000',
            'per_page' => 'int|min:1|max:50',
            'category_id' => 'int|min:1',
            'genre_id' => 'int|min:1',
        ]);

        $page = max(1, (int)($input['page'] ?? 1));
        $perPage = max(1, min(50, (int)($input['per_page'] ?? 20)));

        $filters = [];
        if (!empty($input['category_id'])) {
            $filters['category_id'] = (int)$input['category_id'];
        }
        if (!empty($input['genre_id'])) {
            $filters['genre_id'] = (int)$input['genre_id'];
        }

        return [$filters, $page, $perPage];
    }
}
