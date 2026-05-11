<?php
namespace App\Http\Transformers;

class GenreTransformer {
    public function transform(array $genre): array {
        return [
            'id' => (int)($genre['id'] ?? 0),
            'name' => (string)($genre['name'] ?? ''),
            'slug' => (string)($genre['slug'] ?? ''),
        ];
    }
}
