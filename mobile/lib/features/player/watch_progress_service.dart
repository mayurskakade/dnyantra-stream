import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

class WatchProgressService {
  WatchProgressService(this._dio);

  final Dio _dio;

  Future<void> postProgress({
    required int playableId,
    required String playableType,
    required int positionSeconds,
    required int durationSeconds,
  }) async {
    try {
      await _dio.post<dynamic>(
        '/api/watch-progress',
        data: <String, dynamic>{
          'playable_id': playableId,
          'playable_type': playableType,
          'position_seconds': positionSeconds,
          'duration_seconds': durationSeconds,
        },
      );
    } on DioException {
      // Best-effort telemetry; failures are intentionally ignored.
    } catch (_) {
      // Keep UI/playback flow resilient even when telemetry payloads fail.
    }
  }
}

final watchProgressServiceProvider = Provider<WatchProgressService>((ref) {
  final dio = ref.watch(apiDioProvider);
  return WatchProgressService(dio);
});
