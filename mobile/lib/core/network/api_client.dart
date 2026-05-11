import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';

const String skipAuthHeaderKey = 'skipAuthHeader';
const String skipAuthRefreshKey = 'skipAuthRefresh';
const String retriedRequestKey = 'retriedRequest';

final appConfigProvider = Provider<AppConfig>((ref) {
  final baseUrl = AppConfig.defaultBaseUrl;
  return AppConfig(baseUrl: baseUrl);
});

final tokenStorageProvider = Provider<TokenStorage>((ref) {
  return SecureTokenStorage(const FlutterSecureStorage());
});

final authDioProvider = Provider<Dio>((ref) {
  final config = ref.watch(appConfigProvider);
  return Dio(
    BaseOptions(
      baseUrl: config.baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      sendTimeout: const Duration(seconds: 10),
      headers: const <String, dynamic>{'Accept': 'application/json'},
    ),
  );
});

final apiDioProvider = Provider<Dio>((ref) {
  final config = ref.watch(appConfigProvider);
  final tokenStorage = ref.watch(tokenStorageProvider);
  final refreshDio = ref.watch(authDioProvider);

  final dio = Dio(
    BaseOptions(
      baseUrl: config.baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      sendTimeout: const Duration(seconds: 10),
      headers: const <String, dynamic>{'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    _AuthInterceptor(
      dio: dio,
      tokenStorage: tokenStorage,
      refreshAccessToken: () => _refreshAccessToken(
          refreshDio: refreshDio, tokenStorage: tokenStorage),
      onRefreshFailed: tokenStorage.clear,
    ),
  );

  return dio;
});

class _AuthInterceptor extends QueuedInterceptor {
  _AuthInterceptor({
    required Dio dio,
    required TokenStorage tokenStorage,
    required Future<String?> Function() refreshAccessToken,
    required Future<void> Function() onRefreshFailed,
  })  : _dio = dio,
        _tokenStorage = tokenStorage,
        _refreshAccessToken = refreshAccessToken,
        _onRefreshFailed = onRefreshFailed;

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final Future<String?> Function() _refreshAccessToken;
  final Future<void> Function() _onRefreshFailed;

  Future<String?>? _refreshFuture;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final skipAuthHeader = options.extra[skipAuthHeaderKey] == true;
    if (!skipAuthHeader) {
      final token = await _tokenStorage.readAccessToken();
      if (token != null && token.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    }

    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final statusCode = err.response?.statusCode;
    final shouldSkipRefresh =
        err.requestOptions.extra[skipAuthRefreshKey] == true;
    final wasRetried = err.requestOptions.extra[retriedRequestKey] == true;

    if (statusCode != 401 || shouldSkipRefresh || wasRetried) {
      handler.next(err);
      return;
    }

    final refreshedAccessToken = await _refreshTokenSafely();
    if (refreshedAccessToken == null) {
      await _onRefreshFailed();
      handler.next(err);
      return;
    }

    try {
      final requestOptions = err.requestOptions;
      final response = await _dio.request<dynamic>(
        requestOptions.path,
        data: requestOptions.data,
        queryParameters: requestOptions.queryParameters,
        cancelToken: requestOptions.cancelToken,
        onReceiveProgress: requestOptions.onReceiveProgress,
        onSendProgress: requestOptions.onSendProgress,
        options: Options(
          method: requestOptions.method,
          headers: <String, dynamic>{
            ...requestOptions.headers,
            'Authorization': 'Bearer $refreshedAccessToken',
          },
          responseType: requestOptions.responseType,
          contentType: requestOptions.contentType,
          followRedirects: requestOptions.followRedirects,
          listFormat: requestOptions.listFormat,
          maxRedirects: requestOptions.maxRedirects,
          persistentConnection: requestOptions.persistentConnection,
          receiveDataWhenStatusError: requestOptions.receiveDataWhenStatusError,
          receiveTimeout: requestOptions.receiveTimeout,
          requestEncoder: requestOptions.requestEncoder,
          responseDecoder: requestOptions.responseDecoder,
          sendTimeout: requestOptions.sendTimeout,
          validateStatus: requestOptions.validateStatus,
          extra: <String, dynamic>{
            ...requestOptions.extra,
            retriedRequestKey: true,
          },
        ),
      );
      handler.resolve(response);
    } on DioException {
      handler.next(err);
    }
  }

  Future<String?> _refreshTokenSafely() {
    final inFlight = _refreshFuture;
    if (inFlight != null) {
      return inFlight;
    }

    final refreshCall = _refreshAccessToken();
    _refreshFuture = refreshCall;

    return refreshCall.whenComplete(() {
      _refreshFuture = null;
    });
  }
}

Future<String?> _refreshAccessToken({
  required Dio refreshDio,
  required TokenStorage tokenStorage,
}) async {
  final refreshToken = await tokenStorage.readRefreshToken();
  if (refreshToken == null) {
    return null;
  }

  try {
    final response = await refreshDio.post<dynamic>(
      '/api/auth/refresh',
      data: <String, dynamic>{'refresh_token': refreshToken},
      options: Options(
        extra: const <String, dynamic>{
          skipAuthHeaderKey: true,
          skipAuthRefreshKey: true,
        },
      ),
    );

    final payload = _asMap(response.data);
    final tokenContainer = _asMap(payload['tokens']);
    final source = tokenContainer.isEmpty ? payload : tokenContainer;

    final accessToken =
        _readString(source['access_token'] ?? source['accessToken']);
    final newRefreshToken =
        _readString(source['refresh_token'] ?? source['refreshToken']);

    if (accessToken == null || newRefreshToken == null) {
      return null;
    }

    await tokenStorage.writeTokens(
      accessToken: accessToken,
      refreshToken: newRefreshToken,
    );
    return accessToken;
  } on DioException {
    return null;
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

String? _readString(dynamic value) {
  if (value is String && value.trim().isNotEmpty) {
    return value.trim();
  }
  return null;
}
