<?php
namespace App\Http\Transformers;

class SeasonTransformer {
    public function transform(array $season): array {
        return [
            'id' => (int)($season['id'] ?? 0),
            'number' => (int)($season['season_number'] ?? $season['number'] ?? 0),
            'title' => isset($season['title']) ? (string)$season['title'] : null,
            'episode_count' => (int)($season['episode_count'] ?? 0),
        ];
    }
}
