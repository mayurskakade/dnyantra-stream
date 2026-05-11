import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'playback_session_model.dart';

final playerRepositoryProvider = Provider<PlayerRepository>((ref) {
  final dio = ref.watch(apiDioProvider);
  return PlayerRepository(dio);
});

class PlayerRepository {
  PlayerRepository(this._dio);

  final Dio _dio;

  Future<PlaybackSession> createSession({
    required String playableType,
    required int playableId,
  }) async {
    final response = await _dio.post<dynamic>(
      '/api/playback-sessions',
      data: <String, dynamic>{
        'playable_type': playableType,
        'playable_id': playableId,
      },
    );

    return PlaybackSession.fromJson(response.data);
  }

  Future<WatchProgressSnapshot> postWatchProgress({
    required String playableType,
    required int playableId,
    required int positionSeconds,
    required int durationSeconds,
  }) async {
    final response = await _dio.post<dynamic>(
      '/api/watch-progress',
      data: <String, dynamic>{
        'playable_type': playableType,
        'playable_id': playableId,
        'position_seconds': positionSeconds,
        'duration_seconds': durationSeconds,
      },
    );

    return WatchProgressSnapshot.fromJson(
      response.data,
      playableType: playableType,
      playableId: playableId,
      fallbackDurationSeconds: durationSeconds,
    );
  }

  Future<WatchProgressSnapshot?> fetchProgress({
    required String playableType,
    required int playableId,
  }) async {
    try {
      final response = await _dio.get<dynamic>('/api/home');
      final payload = _asMap(response.data);
      final candidates = _extractCandidateRows(payload);

      for (final row in candidates) {
        final rowType = _readString(row, <String>[
          'playable_type',
          'playableType',
          'type',
        ]);
        final rowId = _readInt(row, <String>[
          'playable_id',
          'playableId',
          'id',
        ]);

        if (rowType == playableType && rowId == playableId) {
          return WatchProgressSnapshot.fromHomeRow(
            row,
            playableType: playableType,
            playableId: playableId,
          );
        }
      }

      return null;
    } on DioException {
      return null;
    }
  }

  List<Map<String, dynamic>> _extractCandidateRows(Map<String, dynamic> root) {
    final queue = <dynamic>[
      root['continue_watching'],
      _asMap(root['data'])['continue_watching'],
      root,
      _asMap(root['data']),
    ];
    final rows = <Map<String, dynamic>>[];

    while (queue.isNotEmpty) {
      final current = queue.removeLast();
      if (current is List) {
        queue.addAll(current);
        continue;
      }

      if (current is Map) {
        final row = _asMap(current);
        if (_looksLikeProgressRow(row)) {
          rows.add(row);
        }
        queue.addAll(row.values);
      }
    }

    return rows;
  }

  bool _looksLikeProgressRow(Map<String, dynamic> row) {
    return _readString(row, const <String>['playable_type', 'playableType']) !=
            null &&
        _readInt(row, const <String>['playable_id', 'playableId']) != null;
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

String? _readString(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is String && value.trim().isNotEmpty) {
      return value.trim();
    }
  }

  return null;
}

int? _readInt(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is int) {
      return value;
    }
    if (value is double) {
      return value.floor();
    }
    if (value is String) {
      final parsed = int.tryParse(value.trim());
      if (parsed != null) {
        return parsed;
      }
    }
  }

  return null;
}
