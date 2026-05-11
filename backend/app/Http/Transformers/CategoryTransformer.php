<?php
namespace App\Http\Transformers;

class CategoryTransformer {
    public function transform(array $category): array {
        return [
            'id' => (int)($category['id'] ?? 0),
            'name' => (string)($category['name'] ?? ''),
            'slug' => (string)($category['slug'] ?? ''),
        ];
    }
}
