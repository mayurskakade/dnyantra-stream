import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/auth_controller.dart';
import 'features/auth/login_screen.dart';
import 'features/catalog/catalog_screen.dart';
import 'features/catalog/movie_detail_screen.dart';
import 'features/catalog/series_detail_screen.dart';
import 'features/home/home_screen.dart';
import 'features/player/player_screen.dart';
import 'features/profile/profile_screen.dart';
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
        switch (settings.name) {
          case CatalogScreen.routeName:
            return MaterialPageRoute<void>(
              builder: (_) => const CatalogScreen(),
              settings: settings,
            );
          case MovieDetailScreen.routeName:
            final args = settings.arguments;
            if (args is MovieDetailArgs) {
              return MaterialPageRoute<void>(
                builder: (_) => MovieDetailScreen(args: args),
                settings: settings,
              );
            }
            break;
          case SeriesDetailScreen.routeName:
            final args = settings.arguments;
            if (args is SeriesDetailArgs) {
              return MaterialPageRoute<void>(
                builder: (_) => SeriesDetailScreen(args: args),
                settings: settings,
              );
            }
            break;
          case ProfileScreen.routeName:
            return MaterialPageRoute<void>(
              builder: (_) => const ProfileScreen(),
              settings: settings,
            );
          case '/player':
            final args = settings.arguments;
            if (args is Map) {
              final payload = _asStringDynamicMap(args);
              final playableId = _toInt(payload['playable_id']);
              if (playableId != null && playableId > 0) {
                final playableType =
                    _toString(payload['playable_type']) ?? 'movie';
                return MaterialPageRoute<void>(
                  builder: (_) => PlayerScreen(
                    playableId: playableId,
                    playableType: playableType,
                  ),
                  settings: settings,
                );
              }
            }
            break;
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

Map<String, dynamic> _asStringDynamicMap(dynamic data) {
  if (data is Map<String, dynamic>) {
    return data;
  }
  if (data is Map) {
    return data.map((key, value) => MapEntry(key.toString(), value));
  }
  return const <String, dynamic>{};
}

String? _toString(dynamic value) {
  if (value is String && value.trim().isNotEmpty) {
    return value.trim();
  }
  return null;
}

int? _toInt(dynamic value) {
  if (value is int) {
    return value;
  }
  if (value is num) {
    return value.toInt();
  }
  if (value is String) {
    return int.tryParse(value);
  }
  return null;
}
