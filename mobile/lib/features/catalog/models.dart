class CatalogListItem {
  const CatalogListItem({
    required this.id,
    required this.title,
    this.subtitle,
    this.imageUrl,
  });

  final String id;
  final String title;
  final String? subtitle;
  final String? imageUrl;

  factory CatalogListItem.fromDynamic(dynamic value) {
    if (value is Map<String, dynamic>) {
      return CatalogListItem.fromJson(value);
    }
    if (value is Map) {
      return CatalogListItem.fromJson(
        value.map((key, item) => MapEntry(key.toString(), item)),
      );
    }
    if (value is String) {
      final trimmed = value.trim();
      return CatalogListItem(id: trimmed, title: trimmed);
    }
    if (value is num) {
      final normalized = value.toString();
      return CatalogListItem(id: normalized, title: normalized);
    }

    return const CatalogListItem(id: 'unknown', title: 'Unknown');
  }

  factory CatalogListItem.fromJson(Map<String, dynamic> json) {
    final id = _readString(json, <String>['id', 'slug', 'uuid']) ?? 'unknown';
    final title = _readString(
          json,
          <String>['title', 'name', 'label', 'display_name'],
        ) ??
        'Untitled';
    final subtitle =
        _readString(json, <String>['description', 'subtitle', 'tagline']);
    final imageUrl = _readString(
        json, <String>['image_url', 'image', 'poster', 'thumbnail']);

    return CatalogListItem(
      id: id,
      title: title,
      subtitle: subtitle,
      imageUrl: imageUrl,
    );
  }
}

class CatalogData {
  const CatalogData({required this.categories, required this.genres});

  final List<CatalogListItem> categories;
  final List<CatalogListItem> genres;

  bool get isEmpty => categories.isEmpty && genres.isEmpty;
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
