import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:private_stream_mobile/features/player/playback_session_model.dart';
import 'package:private_stream_mobile/features/player/player_controller.dart';
import 'package:private_stream_mobile/features/player/player_repository.dart';

class _FakePlayerRepository extends PlayerRepository {
  _FakePlayerRepository() : super(Dio());

  Object? sessionError;
  PlaybackSession? session;

  @override
  Future<PlaybackSession> createSession({
    required String playableType,
    required int playableId,
  }) async {
    if (sessionError != null) {
      throw sessionError!;
    }

    return session ??
        const PlaybackSession(
          playbackUrl: '',
          sessionId: 'missing-media',
        );
  }

  @override
  Future<WatchProgressSnapshot?> fetchProgress({
    required String playableType,
    required int playableId,
  }) async {
    return null;
  }

  @override
  Future<WatchProgressSnapshot> postWatchProgress({
    required String playableType,
    required int playableId,
    required int positionSeconds,
    required int durationSeconds,
  }) async {
    return WatchProgressSnapshot(
      playableType: playableType,
      playableId: playableId,
      positionSeconds: positionSeconds,
      durationSeconds: durationSeconds,
      completed: false,
    );
  }
}

void main() {
  group('PlayerController', () {
    test('transitions to PlayerError when session request fails', () async {
      final repo = _FakePlayerRepository()
        ..sessionError = DioException(
          requestOptions: RequestOptions(path: '/api/playback-sessions'),
          response: Response<dynamic>(
            requestOptions: RequestOptions(path: '/api/playback-sessions'),
            statusCode: 401,
          ),
        );

      final container = ProviderContainer(
        overrides: <Override>[
          playerRepositoryProvider.overrideWithValue(repo),
        ],
      );
      addTearDown(container.dispose);

      await container.read(playerControllerProvider.notifier).initializePlayer(
            playableType: 'movie',
            playableId: 1,
          );

      final state = container.read(playerControllerProvider);
      expect(state, isA<PlayerError>());
    });

    test('transitions to PlayerEmpty when playback url is unavailable',
        () async {
      final repo = _FakePlayerRepository()
        ..session = const PlaybackSession(
          playbackUrl: '   ',
          sessionId: 'session-1',
        );

      final container = ProviderContainer(
        overrides: <Override>[
          playerRepositoryProvider.overrideWithValue(repo),
        ],
      );
      addTearDown(container.dispose);

      await container.read(playerControllerProvider.notifier).initializePlayer(
            playableType: 'episode',
            playableId: 5,
          );

      final state = container.read(playerControllerProvider);
      expect(state, isA<PlayerEmpty>());
    });
  });
}
