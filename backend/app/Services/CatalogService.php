<?php
namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Http\Transformers\CategoryTransformer;
use App\Http\Transformers\EpisodeTransformer;
use App\Http\Transformers\GenreTransformer;
use App\Http\Transformers\MovieTransformer;
use App\Http\Transformers\SeasonTransformer;
use App\Http\Transformers\SeriesTransformer;
use App\Repositories\CategoryRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\GenreRepository;
use App\Repositories\MovieRepository;
use App\Repositories\SeasonRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserContentAccessRepository;
use App\Repositories\WatchProgressRepository;

class CatalogService {
    public function __construct(
        private readonly MovieRepository $movieRepository = new MovieRepository(),
        private readonly SeriesRepository $seriesRepository = new SeriesRepository(),
        private readonly SeasonRepository $seasonRepository = new SeasonRepository(),
        private readonly EpisodeRepository $episodeRepository = new EpisodeRepository(),
        private readonly CategoryRepository $categoryRepository = new CategoryRepository(),
        private readonly GenreRepository $genreRepository = new GenreRepository(),
        private readonly WatchProgressRepository $watchProgressRepository = new WatchProgressRepository(),
        private readonly UserContentAccessRepository $userContentAccessRepository = new UserContentAccessRepository(),
        private readonly AccessPolicyService $accessPolicyService = new AccessPolicyService(),
        private readonly MovieTransformer $movieTransformer = new MovieTransformer(),
        private readonly SeriesTransformer $seriesTransformer = new SeriesTransformer(),
        private readonly SeasonTransformer $seasonTransformer = new SeasonTransformer(),
        private readonly EpisodeTransformer $episodeTransformer = new EpisodeTransformer(),
        private readonly CategoryTransformer $categoryTransformer = new CategoryTransformer(),
        private readonly GenreTransformer $genreTransformer = new GenreTransformer(),
    ) {}

    public function home(?array $user): array {
        $movies = $this->listMovies([], 1, 12, $user)['data'];
        $series = $this->listSeries([], 1, 12, $user)['data'];

        $rails = array_values(array_filter([
            [
                'title' => 'Featured Movies',
                'items' => $movies,
            ],
            [
                'title' => 'Featured Series',
                'items' => $series,
            ],
        ], static fn (array $rail): bool => $rail['items'] !== []));

        return [
            'rails' => $rails,
            'continue_watching' => $user ? $this->continueWatching($user) : [],
        ];
    }

    public function listCategories(): array {
        $rows = $this->categoryRepository->listActive();
        return array_map(fn (array $row): array => $this->categoryTransformer->transform($row), $rows);
    }

    public function listGenres(): array {
        $rows = $this->genreRepository->listActive();
        return array_map(fn (array $row): array => $this->genreTransformer->transform($row), $rows);
    }

    public function listMovies(array $filters, int $page, int $perPage, ?array $user): array {
        $rows = $this->movieRepository->findForCatalog($filters);
        $rowsWithAccess = array_map(fn (array $row): array => $this->withAssignedFlag($row, 'movie', $user), $rows);
        $visibleRows = $this->accessPolicyService->filterListedForUser($rowsWithAccess, $user);

        $total = count($visibleRows);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($visibleRows, $offset, $perPage);

        return [
            'data' => array_map(fn (array $row): array => $this->movieTransformer->listItem($row), $pageRows),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function getMovie(string $slug, ?array $user): array {
        $movie = $this->movieRepository->findBySlug($slug);
        if (!$movie || ($movie['status'] ?? '') !== 'published') {
            throw new NotFoundException('Movie not found');
        }

        $movie = $this->withAssignedFlag($movie, 'movie', $user);
        $this->accessPolicyService->assertCanView($user, 'movie', (int)$movie['id'], $movie);

        $categories = array_map(
            fn (array $row): array => $this->categoryTransformer->transform($row),
            $this->movieRepository->listCategories((int)$movie['id'])
        );
        $genres = array_map(
            fn (array $row): array => $this->genreTransformer->transform($row),
            $this->movieRepository->listGenres((int)$movie['id'])
        );

        return $this->movieTransformer->detail($movie, $categories, $genres);
    }

    public function listSeries(array $filters, int $page, int $perPage, ?array $user): array {
        $rows = $this->seriesRepository->findForCatalog($filters);
        $rowsWithAccess = array_map(fn (array $row): array => $this->withAssignedFlag($row, 'series', $user), $rows);
        $visibleRows = $this->accessPolicyService->filterListedForUser($rowsWithAccess, $user);

        $total = count($visibleRows);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($visibleRows, $offset, $perPage);

        return [
            'data' => array_map(fn (array $row): array => $this->seriesTransformer->listItem($row), $pageRows),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function getSeries(string $slug, ?array $user): array {
        $series = $this->seriesRepository->findBySlug($slug);
        if (!$series || ($series['status'] ?? '') !== 'published') {
            throw new NotFoundException('Series not found');
        }

        $series = $this->withAssignedFlag($series, 'series', $user);
        $this->accessPolicyService->assertCanView($user, 'series', (int)$series['id'], $series);

        $seasons = $this->listSeasons((int)$series['id']);

        return $this->seriesTransformer->detail($series, $seasons);
    }

    public function listSeasons(int $seriesId): array {
        $rows = $this->seasonRepository->listBySeriesId($seriesId);
        return array_map(fn (array $row): array => $this->seasonTransformer->transform($row), $rows);
    }

    public function listEpisodes(int $seasonId, ?array $user): array {
        $rows = $this->episodeRepository->listBySeasonId($seasonId);

        $visibleRows = [];
        foreach ($rows as $row) {
            $content = [
                'status' => $row['status'] ?? 'draft',
                'visibility' => $row['visibility'] ?? $row['series_visibility'] ?? 'private',
                'rights_status' => $row['series_rights_status'] ?? 'personal_only',
                'public_streaming_enabled' => (bool)($row['series_public_streaming_enabled'] ?? false),
                'public_from' => $row['series_public_from'] ?? null,
                'public_until' => $row['series_public_until'] ?? null,
                'is_listed_publicly' => (bool)($row['series_is_listed_publicly'] ?? false),
                'assigned' => $this->isAssigned($user, 'episode', (int)$row['id']),
            ];

            if ($this->accessPolicyService->canWatch($user, 'episode', (int)$row['id'], null, $content)) {
                $visibleRows[] = $row;
            }
        }

        return array_map(fn (array $row): array => $this->episodeTransformer->transform($row), $visibleRows);
    }

    public function continueWatching(array $user): array {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->watchProgressRepository->listContinueWatching($userId, 20);
        return array_map(static function (array $row): array {
            $isEpisode = ($row['playable_type'] ?? '') === 'episode';
            $title = $isEpisode ? ($row['episode_title'] ?? '') : ($row['movie_title'] ?? '');
            $posterUrl = $isEpisode ? ($row['series_poster_url'] ?? '') : ($row['movie_poster_url'] ?? '');

            return [
                'playable_type' => (string)($row['playable_type'] ?? ''),
                'playable_id' => (int)($row['playable_id'] ?? 0),
                'title' => (string)$title,
                'poster_url' => (string)$posterUrl,
                'position_seconds' => (int)($row['position_seconds'] ?? 0),
                'duration_seconds' => (int)($row['duration_seconds'] ?? 0),
            ];
        }, $rows);
    }

    public function publicCatalog(array $filters, int $page, int $perPage): array {
        $rows = $this->movieRepository->findForCatalog($filters);
        $publicRows = [];

        foreach ($rows as $row) {
            if ((int)($row['is_listed_publicly'] ?? 0) !== 1) {
                continue;
            }

            if ($this->accessPolicyService->isPublicAllowed($row)) {
                $publicRows[] = $row;
            }
        }

        $total = count($publicRows);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($publicRows, $offset, $perPage);

        return [
            'data' => array_map(fn (array $row): array => $this->movieTransformer->listItem($row), $pageRows),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function publicMovie(string $slug): array {
        $movie = $this->movieRepository->findBySlug($slug);
        if (!$movie || ($movie['status'] ?? '') !== 'published') {
            throw new NotFoundException('Movie not found');
        }

        if ((int)($movie['is_listed_publicly'] ?? 0) !== 1 || !$this->accessPolicyService->isPublicAllowed($movie)) {
            throw new NotFoundException('Movie not found');
        }

        $categories = array_map(
            fn (array $row): array => $this->categoryTransformer->transform($row),
            $this->movieRepository->listCategories((int)$movie['id'])
        );
        $genres = array_map(
            fn (array $row): array => $this->genreTransformer->transform($row),
            $this->movieRepository->listGenres((int)$movie['id'])
        );

        return $this->movieTransformer->detail($movie, $categories, $genres);
    }

    public function publicSeries(string $slug): array {
        $series = $this->seriesRepository->findBySlug($slug);
        if (!$series || ($series['status'] ?? '') !== 'published') {
            throw new NotFoundException('Series not found');
        }

        if ((int)($series['is_listed_publicly'] ?? 0) !== 1 || !$this->accessPolicyService->isPublicAllowed($series)) {
            throw new NotFoundException('Series not found');
        }

        return $this->seriesTransformer->detail($series, $this->listSeasons((int)$series['id']));
    }

    private function withAssignedFlag(array $row, string $type, ?array $user): array {
        $row['assigned'] = $this->isAssigned($user, $type, (int)($row['id'] ?? 0));
        return $row;
    }

    private function isAssigned(?array $user, string $type, int $contentId): bool {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0 || $contentId <= 0) {
            return false;
        }

        if (($user['role'] ?? '') === 'admin') {
            return true;
        }

        return $this->userContentAccessRepository->hasActiveAccess($userId, $type, $contentId);
    }
}
