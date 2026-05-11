import 'package:dio/dio.dart';

import '../../core/storage/token_storage.dart';
import 'auth_models.dart';

class AuthRepository {
  AuthRepository({required Dio dio, required TokenStorage tokenStorage})
      : _dio = dio,
        _tokenStorage = tokenStorage;

  final Dio _dio;
  final TokenStorage _tokenStorage;

  Future<bool> hasStoredSession() async {
    final accessToken = await _tokenStorage.readAccessToken();
    final refreshToken = await _tokenStorage.readRefreshToken();
    return accessToken != null && refreshToken != null;
  }

  Future<void> login({required String email, required String password}) async {
    final response = await _dio.post<dynamic>(
      '/api/auth/login',
      data: <String, dynamic>{
        'email': email,
        'password': password,
      },
    );

    final tokens = AuthTokens.fromResponse(response.data);
    await _tokenStorage.writeTokens(
      accessToken: tokens.accessToken,
      refreshToken: tokens.refreshToken,
    );
  }

  Future<AppUser> getMe() async {
    final accessToken = await _tokenStorage.readAccessToken();
    if (accessToken == null) {
      throw StateError('No stored access token');
    }

    final response = await _dio.get<dynamic>(
      '/api/me',
      options: Options(headers: <String, dynamic>{
        'Authorization': 'Bearer $accessToken',
      }),
    );

    return AppUser.fromResponse(response.data);
  }

  Future<String?> refreshAccessToken() async {
    final refreshToken = await _tokenStorage.readRefreshToken();
    if (refreshToken == null) {
      return null;
    }

    try {
      final response = await _dio.post<dynamic>(
        '/api/auth/refresh',
        data: <String, dynamic>{
          'refresh_token': refreshToken,
        },
      );

      final tokens = AuthTokens.fromResponse(response.data);
      await _tokenStorage.writeTokens(
        accessToken: tokens.accessToken,
        refreshToken: tokens.refreshToken,
      );
      return tokens.accessToken;
    } on DioException {
      return null;
    } on FormatException {
      return null;
    }
  }

  Future<AppUser?> refreshSession() async {
    final accessToken = await refreshAccessToken();
    if (accessToken == null) {
      return null;
    }

    try {
      return await getMe();
    } on DioException {
      return null;
    }
  }

  Future<void> logout() async {
    final refreshToken = await _tokenStorage.readRefreshToken();

    try {
      await _dio.post<dynamic>(
        '/api/auth/logout',
        data: refreshToken == null
            ? null
            : <String, dynamic>{'refresh_token': refreshToken},
      );
    } on DioException {
      // Local token clearing is mandatory even if backend logout fails.
    } finally {
      await _tokenStorage.clear();
    }
  }

  Future<void> clearTokens() {
    return _tokenStorage.clear();
  }
}
