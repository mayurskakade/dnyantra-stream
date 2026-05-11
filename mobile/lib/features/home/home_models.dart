class HomeData {
  const HomeData({
    required this.rails,
    required this.continueWatching,
  });

  final List<Rail> rails;
  final List<ContinueWatchingItem> continueWatching;

  bool get isEmpty => rails.isEmpty && continueWatching.isEmpty;

  factory HomeData.fromJson(dynamic data) {
    final payload = _asMap(data);

    final continueWatching = _asList(payload['continue_watching'])
        .map(ContinueWatchingItem.fromJson)
        .toList(growable: false);

    final railsValue = payload['rails'];
    List<Rail> rails;

    if (railsValue is List) {
      rails = railsValue.map(Rail.fromJson).toList(growable: false);
    } else {
      rails = <Rail>[];
      payload.forEach((key, value) {
        if (key == 'continue_watching') {
          return;
        }

        if (value is List && value.isNotEmpty) {
          rails.add(
            Rail(
              title: _humanizeKey(key),
              items: value.map(CatalogItem.fromJson).toList(growable: false),
            ),
          );
        }
      });
    }

    return HomeData(
      rails:
          rails.where((rail) => rail.items.isNotEmpty).toList(growable: false),
      continueWatching: continueWatching,
    );
  }
}

class Rail {
  const Rail({required this.title, required this.items});

  final String title;
  final List<CatalogItem> items;

  factory Rail.fromJson(dynamic data) {
    final payload = _asMap(data);
    final title = _readString(payload, const ['title']) ?? 'Untitled';
    final items = _asList(payload['items'])
        .map(CatalogItem.fromJson)
        .toList(growable: false);

    return Rail(title: title, items: items);
  }
}

class CatalogItem {
  const CatalogItem({
    required this.id,
    required this.title,
    this.slug,
    this.posterUrl,
    this.year,
    this.playableType,
    this.playableId,
  });

  final int id;
  final String title;
  final String? slug;
  final String? posterUrl;
  final int? year;
  final String? playableType;
  final int? playableId;

  factory CatalogItem.fromJson(dynamic data) {
    final payload = _asMap(data);
    final id = _readInt(payload, const ['id']) ?? 0;

    return CatalogItem(
      id: id,
      title: _readString(payload, const ['title', 'name']) ?? 'Untitled',
      slug: _readString(payload, const ['slug']),
      posterUrl: _readString(payload, const ['poster_url']),
      year: _readInt(payload, const ['year']),
      playableType: _readString(payload, const ['playable_type', 'type']),
      playableId:
          _readInt(payload, const ['playable_id']) ?? (id > 0 ? id : null),
    );
  }
}

class ContinueWatchingItem {
  const ContinueWatchingItem({
    required this.playableType,
    required this.playableId,
    required this.title,
    required this.posterUrl,
    required this.positionSeconds,
    required this.durationSeconds,
  });

  final String playableType;
  final int playableId;
  final String title;
  final String posterUrl;
  final int positionSeconds;
  final int durationSeconds;

  bool get canResume => positionSeconds > 5 && durationSeconds > 0;

  int get remainingSeconds {
    final remaining = durationSeconds - positionSeconds;
    if (remaining < 0) {
      return 0;
    }
    return remaining;
  }

  factory ContinueWatchingItem.fromJson(dynamic data) {
    final payload = _asMap(data);

    return ContinueWatchingItem(
      playableType:
          _readString(payload, const ['playable_type', 'type']) ?? 'movie',
      playableId: _readInt(payload, const ['playable_id', 'id']) ?? 0,
      title: _readString(payload, const ['title', 'name']) ?? 'Untitled',
      posterUrl: _readString(payload, const ['poster_url']) ?? '',
      positionSeconds: _readInt(payload, const ['position_seconds']) ?? 0,
      durationSeconds: _readInt(payload, const ['duration_seconds']) ?? 0,
    );
  }
}

Map<String, Object?> _asMap(dynamic value) {
  if (value is Map<String, Object?>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item as Object?));
  }
  return const <String, dynamic>{};
}

List<dynamic> _asList(dynamic value) {
  if (value is List) {
    return value;
  }
  return const <dynamic>[];
}

String _humanizeKey(String key) {
  final normalized = key.replaceAll('_', ' ').trim();
  if (normalized.isEmpty) {
    return 'Untitled';
  }
  return normalized[0].toUpperCase() + normalized.substring(1);
}

String? _readString(Map<String, Object?> source, List<String> keys) {
  for (final key in keys) {
    final value = source[key];
    if (value is String && value.trim().isNotEmpty) {
      return value.trim();
    }
  }
  return null;
}

int? _readInt(Map<String, Object?> source, List<String> keys) {
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
