import 'package:dio/dio.dart';

import 'models.dart';

class CatalogRepository {
  CatalogRepository(this._dio);

  final Dio _dio;

  Future<CatalogData> fetchCatalog() async {
    Object? firstError;

    final categoriesResult =
        await _fetchList('/api/categories', fallbackKey: 'categories');
    if (categoriesResult.error != null) {
      firstError ??= categoriesResult.error;
    }

    final genresResult = await _fetchList('/api/genres', fallbackKey: 'genres');
    if (genresResult.error != null) {
      firstError ??= genresResult.error;
    }

    var categories = categoriesResult.items;
    var genres = genresResult.items;

    if (categories.isEmpty || genres.isEmpty) {
      final homeFallback = await _fetchHomeFallback();
      if (categories.isEmpty) {
        categories = homeFallback.categories;
      }
      if (genres.isEmpty) {
        genres = homeFallback.genres;
      }
    }

    if (categories.isEmpty && genres.isEmpty && firstError != null) {
      throw firstError;
    }

    return CatalogData(categories: categories, genres: genres);
  }

  Future<_ParsedListResult> _fetchList(
    String path, {
    required String fallbackKey,
  }) async {
    try {
      final response = await _dio.get<dynamic>(path);
      final payload = _asMap(response.data);
      final items = _extractItems(payload, fallbackKey);
      return _ParsedListResult(items: items);
    } on DioException catch (error) {
      return _ParsedListResult(items: const <CatalogListItem>[], error: error);
    }
  }

  Future<CatalogData> _fetchHomeFallback() async {
    try {
      final response = await _dio.get<dynamic>('/api/home');
      final payload = _asMap(response.data);

      return CatalogData(
        categories: _extractItems(payload, 'categories'),
        genres: _extractItems(payload, 'genres'),
      );
    } on DioException {
      return const CatalogData(
          categories: <CatalogListItem>[], genres: <CatalogListItem>[]);
    }
  }

  List<CatalogListItem> _extractItems(
    Map<String, dynamic> payload,
    String key,
  ) {
    final candidates = <dynamic>[
      payload['data'],
      payload[key],
      payload['items'],
      payload['results'],
      _asMap(payload['data'])[key],
      _asMap(payload['data'])['items'],
    ];

    for (final candidate in candidates) {
      final list = _asList(candidate);
      if (list.isNotEmpty) {
        return list.map(CatalogListItem.fromDynamic).toList(growable: false);
      }
    }

    return const <CatalogListItem>[];
  }
}

class _ParsedListResult {
  const _ParsedListResult({required this.items, this.error});

  final List<CatalogListItem> items;
  final Object? error;
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item));
  }
  return const <String, dynamic>{};
}

List<dynamic> _asList(dynamic value) {
  if (value is List) {
    return value;
  }
  return const <dynamic>[];
}
