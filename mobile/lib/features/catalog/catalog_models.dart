class MediaAsset {
  const MediaAsset({required this.id, required this.type});

  final int id;
  final String type;

  factory MediaAsset.fromJson(dynamic data) {
    final payload = _asMap(data);
    return MediaAsset(
      id: _readInt(payload, const ['id']) ?? 0,
      type: _readString(payload, const ['type']) ?? 'video',
    );
  }
}

class Category {
  const Category({required this.id, required this.name, this.slug});

  final int id;
  final String name;
  final String? slug;

  factory Category.fromJson(dynamic data) {
    final payload = _asMap(data);
    return Category(
      id: _readInt(payload, const ['id']) ?? 0,
      name: _readString(payload, const ['name', 'title']) ?? 'Untitled',
      slug: _readString(payload, const ['slug']),
    );
  }
}

class Genre {
  const Genre({required this.id, required this.name, this.slug});

  final int id;
  final String name;
  final String? slug;

  factory Genre.fromJson(dynamic data) {
    final payload = _asMap(data);
    return Genre(
      id: _readInt(payload, const ['id']) ?? 0,
      name: _readString(payload, const ['name', 'title']) ?? 'Untitled',
      slug: _readString(payload, const ['slug']),
    );
  }
}

class Movie {
  const Movie({
    required this.id,
    required this.slug,
    required this.title,
    this.synopsis,
    this.year,
    this.posterUrl,
    this.bannerUrl,
    this.durationSeconds,
    this.categories = const <Category>[],
    this.genres = const <Genre>[],
    this.mediaAsset,
  });

  final int id;
  final String slug;
  final String title;
  final String? synopsis;
  final int? year;
  final String? posterUrl;
  final String? bannerUrl;
  final int? durationSeconds;
  final List<Category> categories;
  final List<Genre> genres;
  final MediaAsset? mediaAsset;

  factory Movie.fromJson(dynamic data) {
    final payload = _asMap(data);

    return Movie(
      id: _readInt(payload, const ['id']) ?? 0,
      slug: _readString(payload, const ['slug']) ?? '',
      title: _readString(payload, const ['title']) ?? 'Untitled',
      synopsis: _readString(payload, const ['synopsis', 'description']),
      year: _readInt(payload, const ['year']),
      posterUrl: _readString(payload, const ['poster_url']),
      bannerUrl: _readString(payload, const ['banner_url']),
      durationSeconds: _readInt(payload, const ['duration_seconds']),
      categories: _asList(payload['categories'])
          .map(Category.fromJson)
          .toList(growable: false),
      genres: _asList(payload['genres'])
          .map(Genre.fromJson)
          .toList(growable: false),
      mediaAsset: payload['media_asset'] == null
          ? null
          : MediaAsset.fromJson(payload['media_asset']),
    );
  }
}

class Series {
  const Series({
    required this.id,
    required this.slug,
    required this.title,
    this.synopsis,
    this.posterUrl,
    this.bannerUrl,
    this.year,
    this.seasons = const <Season>[],
  });

  final int id;
  final String slug;
  final String title;
  final String? synopsis;
  final String? posterUrl;
  final String? bannerUrl;
  final int? year;
  final List<Season> seasons;

  factory Series.fromJson(dynamic data) {
    final payload = _asMap(data);

    return Series(
      id: _readInt(payload, const ['id']) ?? 0,
      slug: _readString(payload, const ['slug']) ?? '',
      title: _readString(payload, const ['title']) ?? 'Untitled',
      synopsis: _readString(payload, const ['synopsis', 'description']),
      posterUrl: _readString(payload, const ['poster_url']),
      bannerUrl: _readString(payload, const ['banner_url']),
      year: _readInt(payload, const ['year']),
      seasons: _asList(payload['seasons'])
          .map(Season.fromJson)
          .toList(growable: false),
    );
  }
}

class Season {
  const Season({
    required this.id,
    required this.number,
    this.title,
    this.episodeCount,
  });

  final int id;
  final int number;
  final String? title;
  final int? episodeCount;

  String get displayLabel {
    if (title != null && title!.trim().isNotEmpty) {
      return title!.trim();
    }
    return 'Season $number';
  }

  factory Season.fromJson(dynamic data) {
    final payload = _asMap(data);

    return Season(
      id: _readInt(payload, const ['id']) ?? 0,
      number: _readInt(payload, const ['number', 'season_number']) ?? 1,
      title: _readString(payload, const ['title', 'name']),
      episodeCount: _readInt(payload, const ['episode_count']),
    );
  }
}

class Episode {
  const Episode({
    required this.id,
    required this.title,
    required this.number,
    this.synopsis,
    this.durationSeconds,
    this.posterUrl,
  });

  final int id;
  final String title;
  final int number;
  final String? synopsis;
  final int? durationSeconds;
  final String? posterUrl;

  factory Episode.fromJson(dynamic data) {
    final payload = _asMap(data);

    return Episode(
      id: _readInt(payload, const ['id']) ?? 0,
      title: _readString(payload, const ['title', 'name']) ?? 'Untitled',
      number: _readInt(payload, const ['number', 'episode_number']) ?? 1,
      synopsis: _readString(payload, const ['synopsis', 'description']),
      durationSeconds: _readInt(payload, const ['duration_seconds']),
      posterUrl: _readString(payload, const ['poster_url']),
    );
  }
}

class PaginatedResult<T> {
  const PaginatedResult({
    required this.items,
    required this.page,
    required this.perPage,
    required this.total,
  });

  final List<T> items;
  final int page;
  final int perPage;
  final int total;

  bool get hasNextPage {
    if (items.isEmpty) {
      return false;
    }

    if (total > 0) {
      return page * perPage < total;
    }

    return items.length >= perPage;
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
