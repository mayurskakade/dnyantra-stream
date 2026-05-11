import 'dart:async';

import 'package:dio/dio.dart';

import 'playback_session_model.dart';

enum WatchProgressFlushReason {
  heartbeat,
  pause,
  seek,
  background,
  completion,
}

class WatchProgressUpdate {
  const WatchProgressUpdate({
    required this.playableType,
    required this.playableId,
    required this.positionSeconds,
    required this.durationSeconds,
    required this.reason,
  });

  final String playableType;
  final int playableId;
  final int positionSeconds;
  final int durationSeconds;
  final WatchProgressFlushReason reason;

  bool get isCompleted {
    if (durationSeconds <= 0) {
      return false;
    }
    return reason == WatchProgressFlushReason.completion ||
        positionSeconds / durationSeconds >= 0.9;
  }
}

typedef WatchProgressUploader = Future<WatchProgressSnapshot> Function(
  WatchProgressUpdate update,
);

typedef WatchProgressFatalErrorHandler = void Function(
    String message, Object e);

class WatchProgressService {
  WatchProgressService({
    required WatchProgressUploader uploader,
    Duration heartbeatInterval = const Duration(seconds: 20),
    Duration seekDebounce = const Duration(milliseconds: 500),
  })  : _uploader = uploader,
        _heartbeatInterval = heartbeatInterval,
        _seekDebounce = seekDebounce;

  final WatchProgressUploader _uploader;
  final Duration _heartbeatInterval;
  final Duration _seekDebounce;

  final List<WatchProgressUpdate> _queue = <WatchProgressUpdate>[];

  Timer? _heartbeatTimer;
  Timer? _seekDebounceTimer;

  String? _playableType;
  int? _playableId;
  Duration Function()? _positionProvider;
  Duration Function()? _durationProvider;
  bool Function()? _isPlayingProvider;

  WatchProgressFatalErrorHandler? _onFatalError;

  bool _isFlushing = false;

  void attach({
    required String playableType,
    required int playableId,
    required Duration Function() positionProvider,
    required Duration Function() durationProvider,
    required bool Function() isPlayingProvider,
    WatchProgressFatalErrorHandler? onFatalError,
  }) {
    _playableType = playableType;
    _playableId = playableId;
    _positionProvider = positionProvider;
    _durationProvider = durationProvider;
    _isPlayingProvider = isPlayingProvider;
    _onFatalError = onFatalError;

    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(_heartbeatInterval, (_) {
      if (_isPlayingProvider?.call() == true) {
        unawaited(flushNow(reason: WatchProgressFlushReason.heartbeat));
      }
    });
  }

  Future<void> flushNow({required WatchProgressFlushReason reason}) async {
    final update = _buildUpdate(reason: reason);
    if (update == null) {
      return;
    }

    _enqueue(update);
    await _drainQueue();
  }

  void onPause() {
    unawaited(flushNow(reason: WatchProgressFlushReason.pause));
  }

  void onBackground() {
    unawaited(flushNow(reason: WatchProgressFlushReason.background));
  }

  void onSeek() {
    _seekDebounceTimer?.cancel();
    _seekDebounceTimer = Timer(_seekDebounce, () {
      unawaited(flushNow(reason: WatchProgressFlushReason.seek));
    });
  }

  void onComplete() {
    unawaited(flushNow(reason: WatchProgressFlushReason.completion));
  }

  Future<void> dispose() async {
    _heartbeatTimer?.cancel();
    _seekDebounceTimer?.cancel();
    await _drainQueue();

    _positionProvider = null;
    _durationProvider = null;
    _isPlayingProvider = null;
    _playableType = null;
    _playableId = null;
  }

  WatchProgressUpdate? _buildUpdate({
    required WatchProgressFlushReason reason,
  }) {
    final playableType = _playableType;
    final playableId = _playableId;
    final positionProvider = _positionProvider;
    final durationProvider = _durationProvider;

    if (playableType == null ||
        playableId == null ||
        positionProvider == null ||
        durationProvider == null) {
      return null;
    }

    final positionSeconds = positionProvider().inSeconds;
    final durationSeconds = durationProvider().inSeconds;

    if (positionSeconds < 0 || durationSeconds < 0) {
      return null;
    }

    return WatchProgressUpdate(
      playableType: playableType,
      playableId: playableId,
      positionSeconds: positionSeconds,
      durationSeconds: durationSeconds,
      reason: reason,
    );
  }

  void _enqueue(WatchProgressUpdate update) {
    if (_queue.isNotEmpty) {
      final last = _queue.last;
      if (last.playableId == update.playableId &&
          last.playableType == update.playableType &&
          last.positionSeconds == update.positionSeconds &&
          last.durationSeconds == update.durationSeconds &&
          last.reason == update.reason) {
        return;
      }
    }

    _queue.add(update);
  }

  Future<void> _drainQueue() async {
    if (_isFlushing || _queue.isEmpty) {
      return;
    }

    _isFlushing = true;
    try {
      while (_queue.isNotEmpty) {
        final item = _queue.first;
        try {
          await _uploader(item);
          _queue.removeAt(0);
        } on DioException catch (error) {
          final statusCode = error.response?.statusCode;
          if (statusCode != null &&
              (statusCode == 401 ||
                  statusCode == 403 ||
                  statusCode == 404 ||
                  statusCode == 410 ||
                  statusCode == 422)) {
            _onFatalError?.call(
              'Playback session expired. Please request a new session.',
              error,
            );
          }
          break;
        } catch (error) {
          break;
        }
      }
    } finally {
      _isFlushing = false;
    }
  }
}
