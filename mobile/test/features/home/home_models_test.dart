import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/home/home_models.dart';

void main() {
  group('HomeData', () {
    test('parses rails and continue watching payload', () {
      final model = HomeData.fromJson(<String, dynamic>{
        'rails': <Map<String, dynamic>>[
          <String, dynamic>{
            'title': 'Featured',
            'items': <Map<String, dynamic>>[
              <String, dynamic>{
                'id': 1,
                'slug': 'the-sample-movie',
                'title': 'The Sample Movie',
                'poster_url': 'https://cdn.example.com/movie.jpg',
                'year': 2025,
                'playable_type': 'movie',
                'playable_id': 1,
              }
            ],
          }
        ],
        'continue_watching': <Map<String, dynamic>>[
          <String, dynamic>{
            'playable_type': 'episode',
            'playable_id': 11,
            'title': 'Episode 1',
            'poster_url': 'https://cdn.example.com/ep1.jpg',
            'position_seconds': 120,
            'duration_seconds': 1800,
          }
        ],
      });

      expect(model.rails, hasLength(1));
      expect(model.rails.first.items.first.title, 'The Sample Movie');
      expect(model.continueWatching, hasLength(1));
      expect(model.continueWatching.first.playableType, 'episode');
      expect(model.continueWatching.first.canResume, isTrue);
    });

    test('supports fallback rail parsing from top-level keys', () {
      final model = HomeData.fromJson(<String, dynamic>{
        'featured': <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 5,
            'title': 'Fallback Item',
          }
        ],
        'continue_watching': const <Map<String, dynamic>>[],
      });

      expect(model.rails, hasLength(1));
      expect(model.rails.first.title, 'Featured');
      expect(model.isEmpty, isFalse);
    });
  });
}
