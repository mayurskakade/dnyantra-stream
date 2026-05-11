import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../home/home_models.dart';
import 'catalog_models.dart';

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  final dio = ref.watch(apiDioProvider);
  return CatalogRepository(dio);
});

class CatalogRepository {
  CatalogRepository(this._dio);

  final Dio _dio;

  Future<PaginatedResult<Movie>> listMovies({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _dio.get<dynamic>(
      '/api/movies',
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
      },
    );

    return _parsePaginated(response.data, Movie.fromJson,
        fallbackPage: page, fallbackPerPage: perPage);
  }

  Future<PaginatedResult<Series>> listSeries({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _dio.get<dynamic>(
      '/api/series',
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
      },
    );

    return _parsePaginated(response.data, Series.fromJson,
        fallbackPage: page, fallbackPerPage: perPage);
  }

  Future<Movie> getMovie(String slug) async {
    final response = await _dio.get<dynamic>('/api/movies/$slug');
    return Movie.fromJson(response.data);
  }

  Future<Series> getSeries(String slug) async {
    final response = await _dio.get<dynamic>('/api/series/$slug');
    return Series.fromJson(response.data);
  }

  Future<List<Episode>> listEpisodes(int seasonId) async {
    final response = await _dio.get<dynamic>('/api/seasons/$seasonId/episodes');
    final payload = _asMap(response.data);
    final list = _asList(payload['data']);
    return list.map(Episode.fromJson).toList(growable: false);
  }

  Future<List<ContinueWatchingItem>> listContinueWatching() async {
    final response = await _dio.get<dynamic>('/api/continue-watching');
    final payload = _asMap(response.data);
    final rootList = _asList(payload['data']);
    final fallbackList = _asList(payload['continue_watching']);
    final items = rootList.isNotEmpty ? rootList : fallbackList;

    return items
        .map(ContinueWatchingItem.fromJson)
        .toList(growable: false)
        .where((item) => item.playableId > 0)
        .toList(growable: false);
  }

  PaginatedResult<T> _parsePaginated<T>(
    dynamic data,
    T Function(dynamic value) mapper, {
    required int fallbackPage,
    required int fallbackPerPage,
  }) {
    final payload = _asMap(data);
    final list = _asList(payload['data']).map(mapper).toList(growable: false);

    final page = _readInt(payload, const ['page']) ?? fallbackPage;
    final perPage = _readInt(payload, const ['per_page']) ?? fallbackPerPage;
    final total = _readInt(payload, const ['total']) ?? 0;

    return PaginatedResult<T>(
      items: list,
      page: page,
      perPage: perPage,
      total: total,
    );
  }
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

int? _readInt(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null) {
        return parsed;
      }
    }
  }
  return null;
}
