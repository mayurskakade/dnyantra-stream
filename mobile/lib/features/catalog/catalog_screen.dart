import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'catalog_repository.dart';
import 'models.dart';

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  final dio = ref.watch(apiDioProvider);
  return CatalogRepository(dio);
});

final catalogProvider = FutureProvider<CatalogData>((ref) {
  final repository = ref.watch(catalogRepositoryProvider);
  return repository.fetchCatalog();
});

class CatalogScreen extends ConsumerWidget {
  const CatalogScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalogAsync = ref.watch(catalogProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catalog'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(catalogProvider),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: catalogAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _CatalogErrorState(
          message: 'Failed to load catalog: $error',
          onRetry: () => ref.invalidate(catalogProvider),
        ),
        data: (catalog) {
          if (catalog.isEmpty) {
            return _CatalogEmptyState(
              onRefresh: () => ref.invalidate(catalogProvider),
            );
          }
          return _CatalogDataState(catalog: catalog);
        },
      ),
    );
  }
}

class _CatalogErrorState extends StatelessWidget {
  const _CatalogErrorState({required this.message, required this.onRetry});

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
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _CatalogEmptyState extends StatelessWidget {
  const _CatalogEmptyState({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Text('Catalog is empty.'),
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

class _CatalogDataState extends StatelessWidget {
  const _CatalogDataState({required this.catalog});

  final CatalogData catalog;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        _CatalogSection(title: 'Categories', items: catalog.categories),
        const SizedBox(height: 24),
        _CatalogSection(title: 'Genres', items: catalog.genres),
      ],
    );
  }
}

class _CatalogSection extends StatelessWidget {
  const _CatalogSection({required this.title, required this.items});

  final String title;
  final List<CatalogListItem> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: Theme.of(context)
              .textTheme
              .titleLarge
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 12),
        if (items.isEmpty)
          const Text('None')
        else
          ...items.map(
            (item) => ListTile(
              contentPadding: EdgeInsets.zero,
              dense: true,
              title: Text(item.title),
              subtitle: item.subtitle == null ? null : Text(item.subtitle!),
              trailing: Text(item.id),
            ),
          ),
      ],
    );
  }
}
