<?php
namespace App\Http\Transformers;

class EpisodeTransformer {
    public function transform(array $episode): array {
        return [
            'id' => (int)($episode['id'] ?? 0),
            'number' => (int)($episode['episode_number'] ?? $episode['number'] ?? 0),
            'title' => (string)($episode['title'] ?? ''),
            'synopsis' => $episode['synopsis'] ?? null,
            'duration_seconds' => isset($episode['duration_seconds']) ? (int)$episode['duration_seconds'] : null,
            'poster_url' => (string)($episode['series_poster_url'] ?? $episode['poster_url'] ?? ''),
        ];
    }
}
