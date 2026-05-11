import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'playback_session_model.dart';

class PlayerController extends AsyncNotifier<PlaybackSessionModel?> {
  @override
  Future<PlaybackSessionModel?> build() async {
    return null;
  }

  Future<void> requestPlaybackSession({
    required int playableId,
    required String playableType,
    bool isPublic = false,
  }) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final dio = ref.read(apiDioProvider);
      final response = await dio.post<dynamic>(
        isPublic ? '/public/playback-sessions' : '/api/playback-sessions',
        data: <String, dynamic>{
          'playable_id': playableId,
          'playable_type': playableType,
        },
        options: isPublic
            ? Options(
                extra: const <String, dynamic>{
                  skipAuthHeaderKey: true,
                  skipAuthRefreshKey: true,
                },
              )
            : null,
      );

      return PlaybackSessionModel.fromJson(response.data);
    });
  }
}

final playerControllerProvider =
    AsyncNotifierProvider<PlayerController, PlaybackSessionModel?>(
  PlayerController.new,
);
