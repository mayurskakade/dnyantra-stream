import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/auth_controller.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';
import 'features/player/player_screen.dart';
import 'features/splash/splash_screen.dart';

void main() {
  runApp(const ProviderScope(child: App()));
}

class App extends ConsumerWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);

    return MaterialApp(
      title: 'Private Stream',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
      ),
      onGenerateRoute: (settings) {
        if (settings.name == PlayerScreen.routeName) {
          final args = settings.arguments;
          if (args is PlayerRouteArgs) {
            return MaterialPageRoute<void>(
              builder: (_) => PlayerScreen(
                playableType: args.playableType,
                playableId: args.playableId,
              ),
              settings: settings,
            );
          }
        }
        return null;
      },
      home: auth.when(
        loading: () => const SplashScreen(),
        error: (_, __) => const LoginScreen(),
        data: (session) {
          if (session.isAuthenticated) {
            return const HomeScreen();
          }
          return const LoginScreen();
        },
      ),
    );
  }
}
