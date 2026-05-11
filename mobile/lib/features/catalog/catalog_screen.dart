import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'catalog_repository.dart';
import 'movie_detail_screen.dart';
import 'series_detail_screen.dart';

class CatalogScreen extends StatelessWidget {
  const CatalogScreen({super.key});

  static const String routeName = '/catalog';

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Catalog'),
          bottom: const TabBar(
            tabs: <Tab>[
              Tab(text: 'Movies'),
              Tab(text: 'Series'),
            ],
          ),
        ),
        body: TabBarView(
          children: <Widget>[
            _MoviesTab(),
            _SeriesTab(),
          ],
        ),
      ),
    );
  }
}

class _MoviesTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return _CatalogTabList(
      loader: (int page) async {
        final repository = ref.read(catalogRepositoryProvider);
        final result = await repository.listMovies(page: page);

        return _CatalogPage(
          hasNextPage: result.hasNextPage,
          items: result.items
              .map(
                (movie) => _CatalogCardData(
                  id: movie.id,
                  title: movie.title,
                  subtitle: movie.year?.toString(),
                  imageUrl: movie.posterUrl,
                  slug: movie.slug,
                ),
              )
              .toList(growable: false),
        );
      },
      emptyMessage: 'No movies available yet.',
      onTapItem: (context, item) {
        Navigator.of(context).pushNamed(
          MovieDetailScreen.routeName,
          arguments: MovieDetailArgs(slug: item.slug),
        );
      },
    );
  }
}

class _SeriesTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return _CatalogTabList(
      loader: (int page) async {
        final repository = ref.read(catalogRepositoryProvider);
        final result = await repository.listSeries(page: page);

        return _CatalogPage(
          hasNextPage: result.hasNextPage,
          items: result.items
              .map(
                (series) => _CatalogCardData(
                  id: series.id,
                  title: series.title,
                  subtitle: series.year?.toString(),
                  imageUrl: series.posterUrl,
                  slug: series.slug,
                ),
              )
              .toList(growable: false),
        );
      },
      emptyMessage: 'No series available yet.',
      onTapItem: (context, item) {
        Navigator.of(context).pushNamed(
          SeriesDetailScreen.routeName,
          arguments: SeriesDetailArgs(slug: item.slug),
        );
      },
    );
  }
}

class _CatalogTabList extends StatefulWidget {
  const _CatalogTabList({
    required this.loader,
    required this.onTapItem,
    required this.emptyMessage,
  });

  final Future<_CatalogPage> Function(int page) loader;
  final void Function(BuildContext context, _CatalogCardData item) onTapItem;
  final String emptyMessage;

  @override
  State<_CatalogTabList> createState() => _CatalogTabListState();
}

class _CatalogTabListState extends State<_CatalogTabList> {
  final ScrollController _scrollController = ScrollController();

  List<_CatalogCardData> _items = const <_CatalogCardData>[];
  int _page = 1;
  bool _isInitialLoading = true;
  bool _isLoadingMore = false;
  bool _hasNextPage = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadFirstPage();
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  Future<void> _loadFirstPage() async {
    setState(() {
      _isInitialLoading = true;
      _error = null;
      _page = 1;
      _items = const <_CatalogCardData>[];
      _hasNextPage = false;
    });

    try {
      final result = await widget.loader(1);
      if (!mounted) {
        return;
      }

      setState(() {
        _items = result.items;
        _hasNextPage = result.hasNextPage;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _error = error;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isInitialLoading = false;
        });
      }
    }
  }

  Future<void> _loadNextPage() async {
    if (_isLoadingMore || !_hasNextPage) {
      return;
    }

    setState(() {
      _isLoadingMore = true;
    });

    try {
      final nextPage = _page + 1;
      final result = await widget.loader(nextPage);
      if (!mounted) {
        return;
      }

      setState(() {
        _page = nextPage;
        _items = <_CatalogCardData>[..._items, ...result.items];
        _hasNextPage = result.hasNextPage;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingMore = false;
        });
      }
    }
  }

  void _onScroll() {
    if (!_scrollController.hasClients) {
      return;
    }

    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 240) {
      _loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isInitialLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _items.isEmpty) {
      return _ErrorState(
        message: 'Failed to load catalog: $_error',
        onRetry: _loadFirstPage,
      );
    }

    if (_items.isEmpty) {
      return _EmptyState(
          message: widget.emptyMessage, onRefresh: _loadFirstPage);
    }

    final itemCount = _items.length + (_isLoadingMore ? 1 : 0);

    return RefreshIndicator(
      onRefresh: _loadFirstPage,
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(12),
        itemCount: itemCount,
        itemBuilder: (context, index) {
          if (index >= _items.length) {
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(child: CircularProgressIndicator()),
            );
          }

          final item = _items[index];
          return Card(
            child: ListTile(
              onTap: () => widget.onTapItem(context, item),
              leading: _PosterThumbnail(url: item.imageUrl),
              title: Text(item.title),
              subtitle: item.subtitle == null ? null : Text(item.subtitle!),
              trailing: const Icon(Icons.chevron_right),
            ),
          );
        },
      ),
    );
  }
}

class _PosterThumbnail extends StatelessWidget {
  const _PosterThumbnail({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) {
      return const SizedBox(
        width: 48,
        height: 72,
        child: DecoratedBox(
          decoration: BoxDecoration(color: Color(0x22000000)),
          child: Icon(Icons.movie_outlined),
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(4),
      child: Image.network(
        url!,
        width: 48,
        height: 72,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) {
          return const SizedBox(
            width: 48,
            height: 72,
            child: DecoratedBox(
              decoration: BoxDecoration(color: Color(0x22000000)),
              child: Icon(Icons.broken_image_outlined),
            ),
          );
        },
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

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

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message, required this.onRefresh});

  final String message;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(message),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRefresh, child: const Text('Refresh')),
        ],
      ),
    );
  }
}

class _CatalogPage {
  const _CatalogPage({required this.items, required this.hasNextPage});

  final List<_CatalogCardData> items;
  final bool hasNextPage;
}

class _CatalogCardData {
  const _CatalogCardData({
    required this.id,
    required this.title,
    required this.slug,
    this.subtitle,
    this.imageUrl,
  });

  final int id;
  final String title;
  final String slug;
  final String? subtitle;
  final String? imageUrl;
}
