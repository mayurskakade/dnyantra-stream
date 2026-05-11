<?php
namespace App\Http\Transformers;

class MediaAssetTransformer {
    public function transform(?array $mediaAsset): ?array {
        if ($mediaAsset === null || empty($mediaAsset['id'])) {
            return null;
        }

        return [
            'id' => (int)$mediaAsset['id'],
            'type' => 'video',
        ];
    }
}
