class PlaybackSession {
  const PlaybackSession({
    required this.playbackUrl,
    required this.sessionId,
    this.expiresAt,
  });

  final String playbackUrl;
  final String sessionId;
  final DateTime? expiresAt;

  factory PlaybackSession.fromJson(dynamic data) {
    final payload = _asMap(data);
    final nested = _asMap(payload['data']);
    final source = nested.isEmpty ? payload : nested;

    final playbackUrl = _readString(source, <String>[
      'playback_url',
      'playbackUrl',
      'url',
    ]);
    final sessionId = _readString(source, <String>[
      'session_id',
      'sessionId',
      'id',
    ]);

    if (playbackUrl == null || sessionId == null) {
      throw const FormatException('Invalid playback session response payload');
    }

    return PlaybackSession(
      playbackUrl: playbackUrl,
      sessionId: sessionId,
      expiresAt: _readDateTime(source, <String>['expires_at', 'expiresAt']),
    );
  }
}

class WatchProgressSnapshot {
  const WatchProgressSnapshot({
    required this.playableType,
    required this.playableId,
    required this.positionSeconds,
    required this.durationSeconds,
    required this.completed,
  });

  final String playableType;
  final int playableId;
  final int positionSeconds;
  final int durationSeconds;
  final bool completed;

  bool get shouldOfferResume => !completed && positionSeconds > 5;

  factory WatchProgressSnapshot.fromJson(
    dynamic data, {
    required String playableType,
    required int playableId,
    required int fallbackDurationSeconds,
  }) {
    final payload = _asMap(data);
    final nested = _asMap(payload['data']);
    final source = nested.isEmpty ? payload : nested;

    final positionSeconds = _readInt(source, <String>[
      'position_seconds',
      'positionSeconds',
      'position',
    ]);
    final durationSeconds = _readInt(source, <String>[
      'duration_seconds',
      'durationSeconds',
      'duration',
    ]);

    final completed = _readBool(source, <String>['completed']) ??
        _isCompleted(
          positionSeconds ?? 0,
          durationSeconds ?? fallbackDurationSeconds,
        );

    return WatchProgressSnapshot(
      playableType: playableType,
      playableId: playableId,
      positionSeconds: positionSeconds ?? 0,
      durationSeconds: durationSeconds ?? fallbackDurationSeconds,
      completed: completed,
    );
  }

  factory WatchProgressSnapshot.fromHomeRow(
    Map<String, dynamic> row, {
    required String playableType,
    required int playableId,
  }) {
    final positionSeconds = _readInt(row, <String>[
          'position_seconds',
          'positionSeconds',
          'progress_seconds',
        ]) ??
        0;
    final durationSeconds = _readInt(row, <String>[
          'duration_seconds',
          'durationSeconds',
          'runtime_seconds',
        ]) ??
        0;
    final completed = _readBool(row, <String>['completed']) ??
        _isCompleted(positionSeconds, durationSeconds);

    return WatchProgressSnapshot(
      playableType: playableType,
      playableId: playableId,
      positionSeconds: positionSeconds,
      durationSeconds: durationSeconds,
      completed: completed,
    );
  }

  static bool _isCompleted(int positionSeconds, int durationSeconds) {
    if (durationSeconds <= 0) {
      return false;
    }
    return positionSeconds / durationSeconds >= 0.9;
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

int? _readInt(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is int) {
      return value;
    }
    if (value is double) {
      return value.floor();
    }
    if (value is String && value.trim().isNotEmpty) {
      final parsed = int.tryParse(value.trim());
      if (parsed != null) {
        return parsed;
      }
    }
  }

  return null;
}

bool? _readBool(Map<String, dynamic> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is bool) {
      return value;
    }
    if (value is num) {
      return value != 0;
    }
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      if (normalized == 'true' || normalized == '1') {
        return true;
      }
      if (normalized == 'false' || normalized == '0') {
        return false;
      }
    }
  }

  return null;
}
