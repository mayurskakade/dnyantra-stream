import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'auth_models.dart';
import 'auth_repository.dart';

class AuthSession {
  const AuthSession._({required this.isAuthenticated, this.user});

  const AuthSession.unauthenticated() : this._(isAuthenticated: false);

  const AuthSession.authenticated(AppUser user)
      : this._(isAuthenticated: true, user: user);

  final bool isAuthenticated;
  final AppUser? user;
}

class AuthController extends AsyncNotifier<AuthSession> {
  @override
  Future<AuthSession> build() async {
    final repo = ref.read(authRepositoryProvider);
    final hasStoredSession = await repo.hasStoredSession();

    if (!hasStoredSession) {
      return const AuthSession.unauthenticated();
    }

    try {
      final user = await repo.getMe();
      return AuthSession.authenticated(user);
    } on DioException {
      final refreshedUser = await repo.refreshSession();
      if (refreshedUser != null) {
        return AuthSession.authenticated(refreshedUser);
      }
      await repo.clearTokens();
      return const AuthSession.unauthenticated();
    }
  }

  Future<String?> login(
      {required String email, required String password}) async {
    final repo = ref.read(authRepositoryProvider);

    try {
      await repo.login(email: email, password: password);
      final user = await repo.getMe();
      state = AsyncValue.data(AuthSession.authenticated(user));
      return null;
    } on DioException catch (exception) {
      await repo.clearTokens();
      state = const AsyncValue.data(AuthSession.unauthenticated());
      return _dioErrorMessage(exception);
    } on FormatException {
      await repo.clearTokens();
      state = const AsyncValue.data(AuthSession.unauthenticated());
      return 'Unexpected login response format';
    } catch (_) {
      await repo.clearTokens();
      state = const AsyncValue.data(AuthSession.unauthenticated());
      return 'Login failed unexpectedly. Please try again.';
    }
  }

  Future<void> logout() async {
    final repo = ref.read(authRepositoryProvider);
    await repo.logout();
    state = const AsyncValue.data(AuthSession.unauthenticated());
  }

  String _dioErrorMessage(DioException exception) {
    final statusCode = exception.response?.statusCode;
    if (statusCode == 401) {
      return 'Invalid email or password';
    }
    if (statusCode != null) {
      return 'Login failed (HTTP $statusCode)';
    }
    return 'Login failed. Please check network connectivity.';
  }
}

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthSession>(AuthController.new);

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final dio = ref.watch(authDioProvider);
  final tokenStorage = ref.watch(tokenStorageProvider);
  return AuthRepository(dio: dio, tokenStorage: tokenStorage);
});
