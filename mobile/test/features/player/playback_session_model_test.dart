import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/player/playback_session_model.dart';

void main() {
  group('PlaybackSession', () {
    test('parses playback session payload', () {
      final session = PlaybackSession.fromJson(<String, dynamic>{
        'data': <String, dynamic>{
          'playback_url': 'https://stream.example.com/movie.m3u8',
          'session_id': 'sess_123',
          'expires_at': 1736000000,
        },
      });

      expect(session.playbackUrl, 'https://stream.example.com/movie.m3u8');
      expect(session.sessionId, 'sess_123');
      expect(session.expiresAt, isNotNull);
    });

    test('throws on missing required fields', () {
      expect(
        () => PlaybackSession.fromJson(
            <String, dynamic>{'data': <String, dynamic>{}}),
        throwsFormatException,
      );
    });
  });

  group('WatchProgressSnapshot', () {
    test('uses explicit completed from payload', () {
      final snapshot = WatchProgressSnapshot.fromJson(
        <String, dynamic>{
          'position_seconds': 400,
          'duration_seconds': 500,
          'completed': true,
        },
        playableType: 'movie',
        playableId: 10,
        fallbackDurationSeconds: 0,
      );

      expect(snapshot.completed, isTrue);
      expect(snapshot.positionSeconds, 400);
      expect(snapshot.durationSeconds, 500);
    });

    test('infers completion when ratio reaches 90 percent', () {
      final snapshot = WatchProgressSnapshot.fromHomeRow(
        <String, dynamic>{
          'position_seconds': 90,
          'duration_seconds': 100,
        },
        playableType: 'episode',
        playableId: 99,
      );

      expect(snapshot.completed, isTrue);
      expect(snapshot.shouldOfferResume, isFalse);
    });
  });
}
