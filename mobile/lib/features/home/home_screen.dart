import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_controller.dart';
import 'home_controller.dart';
import 'home_repository.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final homeAsync = ref.watch(homeProvider);
    final authSession = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(
            'Home${authSession?.user != null ? ' · ${authSession!.user!.displayName}' : ''}'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(homeProvider),
            icon: const Icon(Icons.refresh),
          ),
          IconButton(
            tooltip: 'Logout',
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              ref.invalidate(homeProvider);
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: homeAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _HomeErrorState(
          message: _errorText(error),
          onRetry: () => ref.invalidate(homeProvider),
        ),
        data: (home) {
          if (home.isEmpty) {
            return _HomeEmptyState(
              onRefresh: () => ref.invalidate(homeProvider),
            );
          }
          return _HomeDataState(home: home);
        },
      ),
    );
  }

  String _errorText(Object error) {
    return 'Failed to load /api/home: $error';
  }
}

class _HomeErrorState extends StatelessWidget {
  const _HomeErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              message,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeEmptyState extends StatelessWidget {
  const _HomeEmptyState({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Text('No home content yet.'),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: onRefresh,
            child: const Text('Refresh'),
          ),
        ],
      ),
    );
  }
}

class _HomeDataState extends StatelessWidget {
  const _HomeDataState({required this.home});

  final HomeData home;

  @override
  Widget build(BuildContext context) {
    final entries = home.payload.entries.toList(growable: false);

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: entries.length,
      separatorBuilder: (_, __) => const Divider(height: 24),
      itemBuilder: (context, index) {
        final entry = entries[index];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              entry.key,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 4),
            Text(
              _summarizeValue(entry.value),
              maxLines: 4,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        );
      },
    );
  }

  String _summarizeValue(dynamic value) {
    if (value is List) {
      return 'List(${value.length})';
    }
    if (value is Map) {
      return 'Object keys: ${(value.keys).join(', ')}';
    }
    return value.toString();
  }
}
