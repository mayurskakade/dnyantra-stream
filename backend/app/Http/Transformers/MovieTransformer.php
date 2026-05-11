<?php
namespace App\Http\Transformers;

class MovieTransformer {
    public function __construct(
        private readonly MediaAssetTransformer $mediaAssetTransformer = new MediaAssetTransformer(),
    ) {}

    public function listItem(array $movie): array {
        return [
            'id' => (int)($movie['id'] ?? 0),
            'slug' => (string)($movie['slug'] ?? ''),
            'title' => (string)($movie['title'] ?? ''),
            'year' => isset($movie['year']) ? (int)$movie['year'] : null,
            'poster_url' => (string)($movie['poster_url'] ?? ''),
            'duration_seconds' => isset($movie['duration_seconds']) ? (int)$movie['duration_seconds'] : null,
        ];
    }

    public function detail(array $movie, array $categories, array $genres): array {
        return [
            'id' => (int)($movie['id'] ?? 0),
            'slug' => (string)($movie['slug'] ?? ''),
            'title' => (string)($movie['title'] ?? ''),
            'synopsis' => $movie['synopsis'] ?? null,
            'year' => isset($movie['year']) ? (int)$movie['year'] : null,
            'poster_url' => (string)($movie['poster_url'] ?? ''),
            'banner_url' => (string)($movie['banner_url'] ?? ''),
            'duration_seconds' => isset($movie['duration_seconds']) ? (int)$movie['duration_seconds'] : null,
            'categories' => $categories,
            'genres' => $genres,
            'media_asset' => $this->mediaAssetTransformer->transform([
                'id' => $movie['media_asset_id'] ?? null,
            ]),
        ];
    }
}
