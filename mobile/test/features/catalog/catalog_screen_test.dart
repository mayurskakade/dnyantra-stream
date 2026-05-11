import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/catalog/catalog_models.dart';
import 'package:private_stream_mobile/features/catalog/catalog_repository.dart';
import 'package:private_stream_mobile/features/catalog/catalog_screen.dart';
import 'package:private_stream_mobile/features/home/home_models.dart';

void main() {
  testWidgets('renders movie list data', (tester) async {
    final completer = Completer<PaginatedResult<Movie>>();

    final fakeRepository = FakeCatalogRepository(
      listMoviesHandler: ({required int page, required int perPage}) {
        return completer.future;
      },
      listSeriesHandler: ({required int page, required int perPage}) async {
        return const PaginatedResult<Series>(
          items: <Series>[],
          page: 1,
          perPage: 20,
          total: 0,
        );
      },
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          catalogRepositoryProvider.overrideWithValue(fakeRepository),
        ],
        child: const MaterialApp(home: CatalogScreen()),
      ),
    );

    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    completer.complete(
      const PaginatedResult<Movie>(
        items: <Movie>[
          Movie(
            id: 1,
            slug: 'movie-1',
            title: 'Movie One',
            year: 2024,
          ),
        ],
        page: 1,
        perPage: 20,
        total: 1,
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Movie One'), findsOneWidget);
    expect(find.text('2024'), findsOneWidget);
  });

  testWidgets('renders empty state when no movies', (tester) async {
    final fakeRepository = FakeCatalogRepository(
      listMoviesHandler: ({required int page, required int perPage}) async {
        return const PaginatedResult<Movie>(
          items: <Movie>[],
          page: 1,
          perPage: 20,
          total: 0,
        );
      },
      listSeriesHandler: ({required int page, required int perPage}) async {
        return const PaginatedResult<Series>(
          items: <Series>[],
          page: 1,
          perPage: 20,
          total: 0,
        );
      },
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          catalogRepositoryProvider.overrideWithValue(fakeRepository),
        ],
        child: const MaterialApp(home: CatalogScreen()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('No movies available yet.'), findsOneWidget);
  });

  testWidgets('renders error state on movies failure', (tester) async {
    final fakeRepository = FakeCatalogRepository(
      listMoviesHandler: ({required int page, required int perPage}) async {
        throw DioException(
          requestOptions: RequestOptions(path: '/api/movies'),
          message: 'network error',
        );
      },
      listSeriesHandler: ({required int page, required int perPage}) async {
        return const PaginatedResult<Series>(
          items: <Series>[],
          page: 1,
          perPage: 20,
          total: 0,
        );
      },
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          catalogRepositoryProvider.overrideWithValue(fakeRepository),
        ],
        child: const MaterialApp(home: CatalogScreen()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.textContaining('Failed to load catalog'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
  });
}

typedef ListMoviesHandler = Future<PaginatedResult<Movie>> Function({
  required int page,
  required int perPage,
});
typedef ListSeriesHandler = Future<PaginatedResult<Series>> Function({
  required int page,
  required int perPage,
});

class FakeCatalogRepository extends CatalogRepository {
  FakeCatalogRepository({
    required this.listMoviesHandler,
    required this.listSeriesHandler,
  }) : super(Dio());

  final ListMoviesHandler listMoviesHandler;
  final ListSeriesHandler listSeriesHandler;

  @override
  Future<PaginatedResult<Movie>> listMovies({
    int page = 1,
    int perPage = 20,
  }) {
    return listMoviesHandler(page: page, perPage: perPage);
  }

  @override
  Future<PaginatedResult<Series>> listSeries({
    int page = 1,
    int perPage = 20,
  }) {
    return listSeriesHandler(page: page, perPage: perPage);
  }

  @override
  Future<Movie> getMovie(String slug) {
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

  @override
  Future<List<ContinueWatchingItem>> listContinueWatching() {
    throw UnimplementedError();
  }
}
