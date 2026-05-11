import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_controller.dart';
import '../catalog/catalog_screen.dart';
import '../catalog/movie_detail_screen.dart';
import '../catalog/series_detail_screen.dart';
import '../profile/profile_screen.dart';
import 'home_controller.dart';
import 'home_models.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final homeAsync = ref.watch(homeProvider);
    final authSession = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Home${authSession?.user != null ? ' · ${authSession!.user!.displayName}' : ''}',
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Catalog',
            onPressed: () =>
                Navigator.of(context).pushNamed(CatalogScreen.routeName),
            icon: const Icon(Icons.video_library_outlined),
          ),
          IconButton(
            tooltip: 'Profile',
            onPressed: () =>
                Navigator.of(context).pushNamed(ProfileScreen.routeName),
            icon: const Icon(Icons.person_outline),
          ),
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
                onRefresh: () => ref.invalidate(homeProvider));
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
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
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
          OutlinedButton(onPressed: onRefresh, child: const Text('Refresh')),
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
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 12),
      children: <Widget>[
        if (home.continueWatching.isNotEmpty)
          _ContinueWatchingStrip(items: home.continueWatching),
        ...home.rails.map((rail) => _RailSection(rail: rail)),
      ],
    );
  }
}

class _ContinueWatchingStrip extends StatelessWidget {
  const _ContinueWatchingStrip({required this.items});

  final List<ContinueWatchingItem> items;

  @override
  Widget build(BuildContext context) {
    return _HorizontalSection(
      title: 'Continue Watching',
      children: items
          .map(
            (item) => _PosterCard(
              title: item.title,
              subtitle: _resumeText(item),
              imageUrl: item.posterUrl,
              onTap: () {
                Navigator.of(context).pushNamed(
                  '/player',
                  arguments: <String, dynamic>{
                    'playable_type': item.playableType,
                    'playable_id': item.playableId,
                  },
                );
              },
            ),
          )
          .toList(growable: false),
    );
  }

  String _resumeText(ContinueWatchingItem item) {
    final minutes = (item.positionSeconds / 60).floor();
    final seconds = item.positionSeconds % 60;
    return 'Resume ${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }
}

class _RailSection extends StatelessWidget {
  const _RailSection({required this.rail});

  final Rail rail;

  @override
  Widget build(BuildContext context) {
    return _HorizontalSection(
      title: rail.title,
      children: rail.items
          .map(
            (item) => _PosterCard(
              title: item.title,
              subtitle: item.year?.toString(),
              imageUrl: item.posterUrl,
              onTap: () => _openItem(context, item),
            ),
          )
          .toList(growable: false),
    );
  }

  void _openItem(BuildContext context, CatalogItem item) {
    final type = item.playableType;

    if (item.slug != null && item.slug!.isNotEmpty) {
      if (type == 'series') {
        Navigator.of(context).pushNamed(
          SeriesDetailScreen.routeName,
          arguments: SeriesDetailArgs(slug: item.slug!),
        );
        return;
      }

      Navigator.of(context).pushNamed(
        MovieDetailScreen.routeName,
        arguments: MovieDetailArgs(slug: item.slug!),
      );
      return;
    }

    final playableId = item.playableId;
    if (playableId != null && playableId > 0) {
      Navigator.of(context).pushNamed(
        '/player',
        arguments: <String, dynamic>{
          'playable_type': type ?? 'movie',
          'playable_id': playableId,
        },
      );
    }
  }
}

class _HorizontalSection extends StatelessWidget {
  const _HorizontalSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
          child: Text(
            title,
            style: Theme.of(context)
                .textTheme
                .titleLarge
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        SizedBox(
          height: 220,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemBuilder: (_, index) => children[index],
            separatorBuilder: (_, __) => const SizedBox(width: 12),
            itemCount: children.length,
          ),
        ),
      ],
    );
  }
}

class _PosterCard extends StatelessWidget {
  const _PosterCard({
    required this.title,
    required this.onTap,
    this.subtitle,
    this.imageUrl,
  });

  final String title;
  final String? subtitle;
  final String? imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: SizedBox(
        width: 140,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: imageUrl == null || imageUrl!.isEmpty
                    ? const ColoredBox(
                        color: Color(0x22000000),
                        child: Center(child: Icon(Icons.movie_outlined)),
                      )
                    : Image.network(
                        imageUrl!,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const ColoredBox(
                          color: Color(0x22000000),
                          child: Center(
                            child: Icon(Icons.broken_image_outlined),
                          ),
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            if (subtitle != null && subtitle!.isNotEmpty)
              Text(
                subtitle!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
          ],
        ),
      ),
    );
  }
}
