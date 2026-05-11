<?php

use App\Controllers\Api\PlaybackController;

if (!isset($playbackController) || !$playbackController instanceof PlaybackController) {
    $playbackController = new PlaybackController();
}

// --- Agent 05: public playback ---
$router->post('/public/playback-sessions', fn($request)=>$playbackController->createPublic($request));
