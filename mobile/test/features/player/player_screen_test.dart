import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/player/playback_session_model.dart';
import 'package:private_stream_mobile/features/player/player_controller.dart';
import 'package:private_stream_mobile/features/player/player_repository.dart';
import 'package:private_stream_mobile/features/player/player_screen.dart';

class _FakePlayerRepository extends PlayerRepository {
  _FakePlayerRepository() : super(Dio());

  @override
  Future<PlaybackSession> createSession({
    required String playableType,
    required int playableId,
  }) async {
    return const PlaybackSession(
      playbackUrl: 'https://example.com/stream.m3u8',
      sessionId: 'sess',
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

class _FakePlayerController extends PlayerController {
  _FakePlayerController(this._initial);

  final PlayerState _initial;

  @override
  PlayerState build() => _initial;

  @override
  Future<void> initializePlayer({
    required String playableType,
    required int playableId,
    Duration? resumeFrom,
  }) async {}
}

void main() {
  Future<void> pumpWithState(
    WidgetTester tester,
    PlayerState state,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          playerRepositoryProvider.overrideWithValue(_FakePlayerRepository()),
          playerControllerProvider
              .overrideWith(() => _FakePlayerController(state)),
        ],
        child: const MaterialApp(
          home: PlayerScreen(
            playableType: 'movie',
            playableId: 1,
          ),
        ),
      ),
    );

    await tester.pump();
  }

  testWidgets('renders loading state', (tester) async {
    await pumpWithState(tester, PlayerLoading());

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });

  testWidgets('renders error state with retry', (tester) async {
    await pumpWithState(
      tester,
      PlayerError(message: 'Playback failed'),
    );

    expect(find.text('Playback failed'), findsOneWidget);
    expect(find.text('Retry'), findsWidgets);
  });

  testWidgets('renders empty state', (tester) async {
    await pumpWithState(
      tester,
      PlayerEmpty(message: 'No media asset available for playback.'),
    );

    expect(find.text('No media asset available for playback.'), findsOneWidget);
    expect(find.text('Retry'), findsWidgets);
  });
}
