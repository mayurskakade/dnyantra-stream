import 'package:dio/dio.dart';

class HomeData {
  const HomeData(this.payload);

  final Map<String, dynamic> payload;

  bool get isEmpty {
    if (payload.isEmpty) {
      return true;
    }

    for (final value in payload.values) {
      if (value is List && value.isNotEmpty) {
        return false;
      }
      if (value is Map && value.isNotEmpty) {
        return false;
      }
      if (value is String && value.trim().isNotEmpty) {
        return false;
      }
      if (value is num) {
        return false;
      }
      if (value is bool && value) {
        return false;
      }
    }

    return true;
  }
}

class HomeRepository {
  HomeRepository(this._dio);

  final Dio _dio;

  Future<HomeData> fetchHome() async {
    final response = await _dio.get<dynamic>('/api/home');
    final payload = _asMap(response.data);
    return HomeData(payload);
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
