import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:private_stream_mobile/core/network/api_client.dart';
import 'package:private_stream_mobile/core/storage/token_storage.dart';

typedef MockRouteResponder = Response<dynamic> Function(RequestOptions options);

Dio buildMockDio(
    {Map<String, MockRouteResponder> routes =
        const <String, MockRouteResponder>{}}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: 'http://localhost:8080',
      headers: const <String, dynamic>{'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
        final routeKey = '${options.method.toUpperCase()} ${options.path}';
        final responder = routes[routeKey];

        if (responder == null) {
          handler.reject(
            DioException(
              requestOptions: options,
              response: Response<dynamic>(
                requestOptions: options,
                statusCode: 404,
                data: <String, dynamic>{'error': 'No mock route configured'},
              ),
              type: DioExceptionType.badResponse,
            ),
          );
          return;
        }

        handler.resolve(responder(options));
      },
    ),
  );

  return dio;
}

Widget buildTestApp({
  required Widget child,
  TokenStorage? tokenStorage,
  Dio? authDio,
  Dio? apiDio,
  List<Override> overrides = const <Override>[],
}) {
  final resolvedTokenStorage = tokenStorage ?? InMemoryTokenStorage();
  final resolvedAuthDio = authDio ?? buildMockDio();
  final resolvedApiDio = apiDio ?? resolvedAuthDio;

  return ProviderScope(
    overrides: <Override>[
      tokenStorageProvider.overrideWithValue(resolvedTokenStorage),
      authDioProvider.overrideWithValue(resolvedAuthDio),
      apiDioProvider.overrideWithValue(resolvedApiDio),
      ...overrides,
    ],
    child: MaterialApp(home: child),
  );
}
