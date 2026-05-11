import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import 'playback_session_model.dart';
import 'player_repository.dart';
import 'watch_progress_service.dart';

sealed class PlayerState {}

class PlayerLoading extends PlayerState {}

class PlayerEmpty extends PlayerState {
  PlayerEmpty({required this.message});

  final String message;
}

class PlayerReady extends PlayerState {
  PlayerReady({
    required this.controller,
    required this.session,
    required this.resumeFrom,
  });

  final VideoPlayerController controller;
  final PlaybackSession session;
  final Duration? resumeFrom;
}

class PlayerError extends PlayerState {
  PlayerError({required this.message, this.cause});

  final String message;
  final Object? cause;
}

final playerControllerProvider =
    AutoDisposeNotifierProvider<PlayerController, PlayerState>(
  PlayerController.new,
);

class PlayerController extends AutoDisposeNotifier<PlayerState> {
  VideoPlayerController? _videoController;
  WatchProgressService? _watchProgress;

  Duration _lastKnownPosition = Duration.zero;
  bool _lastIsPlaying = false;
  bool _completionFlushed = false;

  @override
  PlayerState build() {
    ref.onDispose(_disposeResources);
    return PlayerLoading();
  }

  Future<void> initializePlayer({
    required String playableType,
    required int playableId,
    Duration? resumeFrom,
  }) async {
    state = PlayerLoading();
    await _disposeResources();

    final repository = ref.read(playerRepositoryProvider);

    try {
      final session = await repository.createSession(
        playableType: playableType,
        playableId: playableId,
      );

      if (session.playbackUrl.trim().isEmpty) {
        state = PlayerEmpty(message: 'No media asset available for playback.');
        return;
      }

      final controller =
          VideoPlayerController.networkUrl(Uri.parse(session.playbackUrl));
      await controller.initialize();

      final seekTarget = _normalizeResumePosition(
        desired: resumeFrom,
        totalDuration: controller.value.duration,
      );

      if (seekTarget != null && seekTarget > Duration.zero) {
        await controller.seekTo(seekTarget);
      }

      _attachProgressTracking(
        controller: controller,
        playableType: playableType,
        playableId: playableId,
        repository: repository,
      );

      await controller.play();

      _videoController = controller;
      state = PlayerReady(
        controller: controller,
        session: session,
        resumeFrom: seekTarget,
      );
    } on DioException catch (error) {
      state = PlayerError(
        message: _dioMessage(error),
        cause: error,
      );
    } catch (error) {
      state = PlayerError(
        message: 'Unable to start playback.',
        cause: error,
      );
    }
  }

  Future<void> retry({
    required String playableType,
    required int playableId,
    Duration? resumeFrom,
  }) {
    return initializePlayer(
      playableType: playableType,
      playableId: playableId,
      resumeFrom: resumeFrom,
    );
  }

  Future<void> togglePlayPause() async {
    final controller = _videoController;
    if (controller == null || !controller.value.isInitialized) {
      return;
    }

    if (controller.value.isPlaying) {
      await controller.pause();
      _watchProgress?.onPause();
      return;
    }

    await controller.play();
  }

  Future<void> seekTo(Duration target) async {
    final controller = _videoController;
    if (controller == null || !controller.value.isInitialized) {
      return;
    }

    await controller.seekTo(target);
    _watchProgress?.onSeek();
  }

  void onAppBackgrounded() {
    _watchProgress?.onBackground();
  }

  Future<void> _disposeResources() async {
    final watchProgress = _watchProgress;
    _watchProgress = null;
    if (watchProgress != null) {
      await watchProgress.dispose();
    }

    final controller = _videoController;
    _videoController = null;
    if (controller != null) {
      controller.removeListener(_onVideoTick);
      await controller.dispose();
    }

    _lastKnownPosition = Duration.zero;
    _lastIsPlaying = false;
    _completionFlushed = false;
  }

  void _attachProgressTracking({
    required VideoPlayerController controller,
    required String playableType,
    required int playableId,
    required PlayerRepository repository,
  }) {
    _watchProgress = WatchProgressService(
      uploader: (update) {
        return repository.postWatchProgress(
          playableType: update.playableType,
          playableId: update.playableId,
          positionSeconds: update.positionSeconds,
          durationSeconds: update.durationSeconds,
        );
      },
    )..attach(
        playableType: playableType,
        playableId: playableId,
        positionProvider: () => controller.value.position,
        durationProvider: () => controller.value.duration,
        isPlayingProvider: () => controller.value.isPlaying,
        onFatalError: (message, error) {
          state = PlayerError(message: message, cause: error);
        },
      );

    _lastKnownPosition = controller.value.position;
    _lastIsPlaying = controller.value.isPlaying;
    _completionFlushed = false;

    controller.addListener(_onVideoTick);
  }

  void _onVideoTick() {
    final controller = _videoController;
    if (controller == null) {
      return;
    }

    final value = controller.value;
    if (!value.isInitialized) {
      return;
    }

    final position = value.position;
    final duration = value.duration;

    if (_lastIsPlaying && !value.isPlaying) {
      _watchProgress?.onPause();
    }

    if ((position - _lastKnownPosition).abs() > const Duration(seconds: 2)) {
      _watchProgress?.onSeek();
    }

    final nearEnd = duration > Duration.zero &&
        position >= duration - const Duration(seconds: 1);
    if (nearEnd && !_completionFlushed) {
      _completionFlushed = true;
      _watchProgress?.onComplete();
    }

    _lastKnownPosition = position;
    _lastIsPlaying = value.isPlaying;
  }

  Duration? _normalizeResumePosition({
    required Duration? desired,
    required Duration totalDuration,
  }) {
    if (desired == null || desired <= Duration.zero) {
      return null;
    }

    if (totalDuration <= Duration.zero) {
      return desired;
    }

    if (desired >= totalDuration) {
      return totalDuration - const Duration(seconds: 1);
    }

    return desired;
  }

  String _dioMessage(DioException error) {
    final statusCode = error.response?.statusCode;
    final base = statusCode == null
        ? 'Failed to create playback session.'
        : 'Playback session request failed ($statusCode).';
    final responseData = error.response?.data;

    if (responseData is Map) {
      final message = responseData['message'];
      if (message is String && message.trim().isNotEmpty) {
        return message.trim();
      }
    }

    return base;
  }
}
