import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/catalog/catalog_models.dart';
import 'package:private_stream_mobile/features/catalog/catalog_repository.dart';
import 'package:private_stream_mobile/features/catalog/movie_detail_screen.dart';
import 'package:private_stream_mobile/features/home/home_models.dart';

void main() {
  testWidgets('renders movie detail with play CTA', (tester) async {
    final fakeRepository = _MovieDetailRepository(
      movie: const Movie(
        id: 10,
        slug: 'inception',
        title: 'Inception',
        synopsis: 'Dream inside a dream',
        year: 2010,
        durationSeconds: 8880,
      ),
      continueWatching: const <ContinueWatchingItem>[
        ContinueWatchingItem(
          playableType: 'movie',
          playableId: 10,
          title: 'Inception',
          posterUrl: '',
          positionSeconds: 640,
          durationSeconds: 8880,
        )
      ],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          catalogRepositoryProvider.overrideWithValue(fakeRepository),
        ],
        child: MaterialApp(
          onGenerateRoute: (settings) {
            if (settings.name == '/player') {
              final args = settings.arguments as Map<dynamic, dynamic>;
              return MaterialPageRoute<void>(
                builder: (_) => Scaffold(
                  body: Text(
                      'Player ${args['playable_type']} ${args['playable_id']}'),
                ),
              );
            }
            return null;
          },
          home:
              const MovieDetailScreen(args: MovieDetailArgs(slug: 'inception')),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Inception'), findsOneWidget);
    expect(find.text('Dream inside a dream'), findsOneWidget);
    expect(find.textContaining('Continue from'), findsOneWidget);

    await tester.tap(find.text('Play'));
    await tester.pumpAndSettle();

    expect(find.text('Player movie 10'), findsOneWidget);
  });

  testWidgets('renders error state when movie fetch fails', (tester) async {
    final fakeRepository = _MovieDetailRepository(
      error: DioException(
        requestOptions: RequestOptions(path: '/api/movies/missing'),
        message: 'not found',
      ),
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          catalogRepositoryProvider.overrideWithValue(fakeRepository),
        ],
        child: const MaterialApp(
          home: MovieDetailScreen(args: MovieDetailArgs(slug: 'missing')),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.textContaining('Failed to load movie details'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
  });
}

class _MovieDetailRepository extends CatalogRepository {
  _MovieDetailRepository({
    this.movie,
    this.continueWatching = const <ContinueWatchingItem>[],
    this.error,
  }) : super(Dio());

  final Movie? movie;
  final List<ContinueWatchingItem> continueWatching;
  final Object? error;

  @override
  Future<Movie> getMovie(String slug) async {
    if (error != null) {
      throw error!;
    }
    return movie!;
  }

  @override
  Future<List<ContinueWatchingItem>> listContinueWatching() async {
    return continueWatching;
  }

  @override
  Future<PaginatedResult<Movie>> listMovies({int page = 1, int perPage = 20}) {
    throw UnimplementedError();
  }

  @override
  Future<PaginatedResult<Series>> listSeries({int page = 1, int perPage = 20}) {
    throw UnimplementedError();
  }

  @override
  Future<Series> getSeries(String slug) {
    throw UnimplementedError();
  }

  @override
  Future<List<Episode>> listEpisodes(int seasonId) {
    throw UnimplementedError();
  }
}
