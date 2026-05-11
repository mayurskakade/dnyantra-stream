import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/player/playback_session_model.dart';
import 'package:private_stream_mobile/features/player/watch_progress_service.dart';

void main() {
  group('WatchProgressService', () {
    test('flushNow sends immediate update', () async {
      final sent = <WatchProgressUpdate>[];
      final service = WatchProgressService(
        uploader: (update) async {
          sent.add(update);
          return WatchProgressSnapshot(
            playableType: update.playableType,
            playableId: update.playableId,
            positionSeconds: update.positionSeconds,
            durationSeconds: update.durationSeconds,
            completed: false,
          );
        },
      );

      var currentPosition = const Duration(seconds: 32);
      service.attach(
        playableType: 'movie',
        playableId: 1,
        positionProvider: () => currentPosition,
        durationProvider: () => const Duration(seconds: 120),
        isPlayingProvider: () => true,
      );

      await service.flushNow(reason: WatchProgressFlushReason.pause);
      await service.dispose();

      expect(sent, hasLength(1));
      expect(sent.first.positionSeconds, 32);
      expect(sent.first.reason, WatchProgressFlushReason.pause);
    });

    test('seek events are debounced', () async {
      final sent = <WatchProgressUpdate>[];
      final service = WatchProgressService(
        uploader: (update) async {
          sent.add(update);
          return WatchProgressSnapshot(
            playableType: update.playableType,
            playableId: update.playableId,
            positionSeconds: update.positionSeconds,
            durationSeconds: update.durationSeconds,
            completed: false,
          );
        },
        seekDebounce: const Duration(milliseconds: 10),
      );

      service.attach(
        playableType: 'episode',
        playableId: 7,
        positionProvider: () => const Duration(seconds: 55),
        durationProvider: () => const Duration(seconds: 200),
        isPlayingProvider: () => true,
      );

      service.onSeek();
      service.onSeek();
      service.onSeek();

      await Future<void>.delayed(const Duration(milliseconds: 30));
      await service.dispose();

      expect(sent.where((it) => it.reason == WatchProgressFlushReason.seek),
          hasLength(1));
    });

    test('keeps queued payloads on non-fatal network error', () async {
      var attempts = 0;
      final sent = <WatchProgressUpdate>[];
      final service = WatchProgressService(
        uploader: (update) async {
          attempts += 1;
          if (attempts == 1) {
            throw DioException(
              requestOptions: RequestOptions(path: '/api/watch-progress'),
            );
          }

          sent.add(update);
          return WatchProgressSnapshot(
            playableType: update.playableType,
            playableId: update.playableId,
            positionSeconds: update.positionSeconds,
            durationSeconds: update.durationSeconds,
            completed: false,
          );
        },
      );

      service.attach(
        playableType: 'movie',
        playableId: 4,
        positionProvider: () => const Duration(seconds: 12),
        durationProvider: () => const Duration(seconds: 90),
        isPlayingProvider: () => true,
      );

      await service.flushNow(reason: WatchProgressFlushReason.pause);
      await service.flushNow(reason: WatchProgressFlushReason.background);
      await service.dispose();

      expect(attempts, greaterThanOrEqualTo(2));
      expect(sent, isNotEmpty);
    });
  });
}
