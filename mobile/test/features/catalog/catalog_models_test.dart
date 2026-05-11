import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/catalog/catalog_models.dart';

void main() {
  group('Movie model', () {
    test('parses detail payload', () {
      final movie = Movie.fromJson(<String, dynamic>{
        'id': 7,
        'slug': 'arrival',
        'title': 'Arrival',
        'synopsis': 'Linguist meets aliens',
        'year': 2016,
        'poster_url': 'https://cdn.example.com/arrival.jpg',
        'duration_seconds': 6960,
        'genres': <Map<String, dynamic>>[
          <String, dynamic>{'id': 1, 'name': 'Sci-Fi'}
        ],
        'media_asset': <String, dynamic>{
          'id': 77,
          'type': 'video',
        },
      });

      expect(movie.id, 7);
      expect(movie.slug, 'arrival');
      expect(movie.genres.first.name, 'Sci-Fi');
      expect(movie.mediaAsset?.id, 77);
    });
  });

  group('Series model', () {
    test('parses series payload with seasons', () {
      final series = Series.fromJson(<String, dynamic>{
        'id': 9,
        'slug': 'dark',
        'title': 'Dark',
        'seasons': <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 90,
            'number': 1,
            'episode_count': 10,
          }
        ],
      });

      expect(series.id, 9);
      expect(series.seasons, hasLength(1));
      expect(series.seasons.first.displayLabel, 'Season 1');
    });
  });

  group('Episode model', () {
    test('parses episode payload', () {
      final episode = Episode.fromJson(<String, dynamic>{
        'id': 91,
        'title': 'Beginnings and Endings',
        'number': 1,
        'duration_seconds': 3200,
      });

      expect(episode.id, 91);
      expect(episode.number, 1);
      expect(episode.durationSeconds, 3200);
    });
  });
}
