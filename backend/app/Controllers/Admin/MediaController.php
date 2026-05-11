<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\MediaAssetRepository;
use App\Services\CloudflareStreamService;
use App\Services\R2Service;
use PDO;

class MediaController extends BaseAdminController {
    public function __construct(
        ?PDO $pdo = null,
        private readonly CloudflareStreamService $streamService = new CloudflareStreamService(),
        private readonly R2Service $r2Service = new R2Service(),
        private readonly MediaAssetRepository $mediaAssetRepository = new MediaAssetRepository(),
    ) {
        parent::__construct($pdo);
    }

    public function requestUploadUrl(Request $request): void {
        $input = $request->input();
        $meta = [
            'maxDurationSeconds' => (int)($input['max_duration_seconds'] ?? 7200),
            'meta' => ['origin' => 'admin_portal', 'filename' => (string)($input['filename'] ?? '')],
        ];

        $upload = $this->streamService->createDirectUploadUrl($meta);
        $assetId = $this->mediaAssetRepository->createStreamUploadAsset((string)$upload['uid'], (string)($input['filename'] ?? null));

        $this->audit($request, 'media.create_upload', 'media_asset', $assetId, null, [
            'id' => $assetId,
            'provider_uid' => (string)$upload['uid'],
            'status' => 'uploading',
        ]);

        Response::json([
            'media_asset_id' => $assetId,
            'upload_url' => (string)$upload['upload_url'],
            'uid' => (string)$upload['uid'],
        ]);
    }

    public function syncStatus(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $asset = $this->mediaAssetRepository->findById($id);
        if ($asset === null) {
            Response::json(['error' => ['code' => 'not_found', 'message' => 'Media asset not found']], 404);
            return;
        }

        $status = $this->streamService->getVideoStatus((string)$asset['provider_uid']);
        $before = $asset;
        $newStatus = (string)($status['status']['state'] ?? $status['status'] ?? 'processing');
        $duration = isset($status['duration']) ? (int)$status['duration'] : null;
        $thumbnail = isset($status['thumbnail']) ? (string)$status['thumbnail'] : null;

        $this->mediaAssetRepository->updateStatus($id, $newStatus, $duration, $thumbnail);
        $after = $this->mediaAssetRepository->findById($id);
        $this->audit($request, 'media.sync_status', 'media_asset', $id, $before, $after);

        Response::json([
            'media_asset_id' => $id,
            'status' => $newStatus,
            'duration_seconds' => $duration,
            'thumbnail_url' => $thumbnail,
        ]);
    }

    public function deleteAsset(Request $request): void {
        $id = (int)$request->getRouteParam('id');
        $asset = $this->mediaAssetRepository->findById($id);
        if ($asset === null) {
            Response::json(['ok' => true]);
            return;
        }

        $before = $asset;
        if ((string)($asset['provider'] ?? '') === 'cloudflare_stream') {
            $this->streamService->deleteVideo((string)$asset['provider_uid']);
        }

        if ((string)($asset['provider'] ?? '') === 'r2_hls') {
            $key = (string)($request->input()['storage_key'] ?? '');
            if ($key !== '') {
                $this->r2Service->deleteObject($key);
            }
        }

        $this->mediaAssetRepository->deleteById($id);
        $this->audit($request, 'media.delete', 'media_asset', $id, $before, null);
        Response::json(['ok' => true]);
    }
}
