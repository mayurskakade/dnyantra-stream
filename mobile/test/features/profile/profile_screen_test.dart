import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:private_stream_mobile/features/auth/auth_controller.dart';
import 'package:private_stream_mobile/features/auth/auth_models.dart';
import 'package:private_stream_mobile/features/profile/profile_screen.dart';

void main() {
  testWidgets('shows user profile and logout button', (tester) async {
    var didLogout = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          authControllerProvider.overrideWith(
            () => _FakeAuthController(
              initialSession: AuthSession.authenticated(
                const AppUser(
                  id: 2,
                  email: 'user@example.com',
                  name: 'Test User',
                ),
              ),
              onLogout: () {
                didLogout = true;
              },
            ),
          ),
        ],
        child: const MaterialApp(home: ProfileScreen()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Test User'), findsOneWidget);
    expect(find.text('user@example.com'), findsOneWidget);
    expect(find.text('Logout'), findsOneWidget);

    await tester.tap(find.text('Logout'));
    await tester.pumpAndSettle();

    expect(didLogout, isTrue);
  });

  testWidgets('shows empty state for unauthenticated session', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          authControllerProvider.overrideWith(
            () => _FakeAuthController(
              initialSession: const AuthSession.unauthenticated(),
              onLogout: () {},
            ),
          ),
        ],
        child: const MaterialApp(home: ProfileScreen()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('No profile data available.'), findsOneWidget);
  });
}

class _FakeAuthController extends AuthController {
  _FakeAuthController({
    required this.initialSession,
    required this.onLogout,
  });

  final AuthSession initialSession;
  final VoidCallback onLogout;

  @override
  Future<AuthSession> build() async {
    return initialSession;
  }

  @override
  Future<void> logout() async {
    onLogout();
    state = const AsyncValue.data(AuthSession.unauthenticated());
  }
}
