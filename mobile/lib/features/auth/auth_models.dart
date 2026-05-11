class AuthTokens {
  const AuthTokens({required this.accessToken, required this.refreshToken});

  final String accessToken;
  final String refreshToken;

  factory AuthTokens.fromResponse(dynamic data) {
    final payload = _asMap(data);
    final tokenContainer = _asMap(payload['tokens']);
    final source = tokenContainer.isEmpty ? payload : tokenContainer;

    final accessToken = _readString(source, ['access_token', 'accessToken']);
    final refreshToken = _readString(source, ['refresh_token', 'refreshToken']);

    if (accessToken == null || refreshToken == null) {
      throw const FormatException('Invalid auth token response payload');
    }

    return AuthTokens(accessToken: accessToken, refreshToken: refreshToken);
  }
}

class AppUser {
  const AppUser({
    required this.id,
    required this.email,
    this.name,
  });

  final int id;
  final String email;
  final String? name;

  String get displayName =>
      (name != null && name!.trim().isNotEmpty) ? name!.trim() : email;

  factory AppUser.fromResponse(dynamic data) {
    final payload = _asMap(data);
    final userMap = _asMap(payload['user']);
    final source = userMap.isEmpty ? payload : userMap;

    final id = _readInt(source, ['id']) ?? 0;
    final email = _readString(source, ['email']) ?? 'unknown@example.com';
    final name = _readString(source, ['name']);

    return AppUser(id: id, email: email, name: name);
  }
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map(
      (key, item) => MapEntry(key.toString(), item),
    );
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
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null) {
        return parsed;
      }
    }
  }
  return null;
}
