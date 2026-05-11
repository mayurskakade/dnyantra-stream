<?php
namespace App\Http\Transformers;

class SeriesTransformer {
    public function listItem(array $series): array {
        return [
            'id' => (int)($series['id'] ?? 0),
            'slug' => (string)($series['slug'] ?? ''),
            'title' => (string)($series['title'] ?? ''),
            'year' => isset($series['year']) ? (int)$series['year'] : null,
            'poster_url' => (string)($series['poster_url'] ?? ''),
        ];
    }

    public function detail(array $series, array $seasons): array {
        return [
            'id' => (int)($series['id'] ?? 0),
            'slug' => (string)($series['slug'] ?? ''),
            'title' => (string)($series['title'] ?? ''),
            'synopsis' => $series['synopsis'] ?? null,
            'poster_url' => (string)($series['poster_url'] ?? ''),
            'banner_url' => (string)($series['banner_url'] ?? ''),
            'year' => isset($series['year']) ? (int)$series['year'] : null,
            'seasons' => $seasons,
        ];
    }
}
