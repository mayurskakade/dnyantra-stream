import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/catalog/models.dart';
import 'package:private_stream_mobile/features/player/playback_session_model.dart';

void main() {
  group('CatalogListItem parsing', () {
    test('parses map payload with common keys', () {
      final item = CatalogListItem.fromJson(<String, dynamic>{
        'id': 42,
        'name': 'Action',
        'description': 'Fast paced content',
        'image_url': 'https://cdn.example.com/action.jpg',
      });

      expect(item.id, '42');
      expect(item.title, 'Action');
      expect(item.subtitle, 'Fast paced content');
      expect(item.imageUrl, 'https://cdn.example.com/action.jpg');
    });

    test('parses string payload via fromDynamic', () {
      final item = CatalogListItem.fromDynamic('Drama');

      expect(item.id, 'Drama');
      expect(item.title, 'Drama');
      expect(item.subtitle, isNull);
    });
  });

  group('PlaybackSession parsing', () {
    test('parses backend playback session response', () {
      final session = PlaybackSession.fromJson(<String, dynamic>{
        'playback_url': 'https://stream.example.com/manifest.m3u8',
        'expires_at': '2030-01-01T00:00:00Z',
        'session_id': 'sess_abc123',
      });

      expect(session.playbackUrl, 'https://stream.example.com/manifest.m3u8');
      expect(session.sessionId, 'sess_abc123');
      expect(session.expiresAt, DateTime.parse('2030-01-01T00:00:00Z').toUtc());
    });

    test('throws on missing playback_url and session_id', () {
      expect(
        () => PlaybackSession.fromJson(<String, dynamic>{
          'expires_at': '2030-01-01T00:00:00Z',
        }),
        throwsFormatException,
      );
    });
  });
}
