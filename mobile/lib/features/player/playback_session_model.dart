class PlaybackSessionModel {
  const PlaybackSessionModel({
    required this.playbackUrl,
    required this.sessionId,
    this.expiresAt,
  });

  final String playbackUrl;
  final String sessionId;
  final DateTime? expiresAt;

  factory PlaybackSessionModel.fromJson(dynamic data) {
    final payload = _asMap(data);
    final source =
        _asMap(payload['data']).isEmpty ? payload : _asMap(payload['data']);

    final playbackUrl = _readString(
      source,
      <String>['playback_url', 'playbackUrl', 'url'],
    );
    final sessionId = _readString(
      source,
      <String>['session_id', 'sessionId', 'id'],
    );

    if (playbackUrl == null || sessionId == null) {
      throw const FormatException('Invalid playback session response payload');
    }

    final expiresAt =
        _readDateTime(source, <String>['expires_at', 'expiresAt']);

    return PlaybackSessionModel(
      playbackUrl: playbackUrl,
      sessionId: sessionId,
      expiresAt: expiresAt,
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

String? _readString(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is String && value.trim().isNotEmpty) {
      return value.trim();
    }
    if (value is num) {
      return value.toString();
    }
  }
  return null;
}

DateTime? _readDateTime(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is String && value.trim().isNotEmpty) {
      final parsed = DateTime.tryParse(value.trim());
      if (parsed != null) {
        return parsed.toUtc();
      }
    }
    if (value is int) {
      final millis = value > 9999999999 ? value : value * 1000;
      return DateTime.fromMillisecondsSinceEpoch(millis, isUtc: true);
    }
  }
  return null;
}
