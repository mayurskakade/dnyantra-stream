import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../home/home_models.dart';
import 'catalog_models.dart';
import 'catalog_repository.dart';

class MovieDetailArgs {
  const MovieDetailArgs({required this.slug});

  final String slug;
}

class MovieDetailScreen extends ConsumerStatefulWidget {
  const MovieDetailScreen({super.key, required this.args});

  static const String routeName = '/movies/detail';

  final MovieDetailArgs args;

  @override
  ConsumerState<MovieDetailScreen> createState() => _MovieDetailScreenState();
}

class _MovieDetailScreenState extends ConsumerState<MovieDetailScreen> {
  late Future<_MovieDetailData> _detailFuture;

  @override
  void initState() {
    super.initState();
    _detailFuture = _load();
  }

  Future<_MovieDetailData> _load() async {
    final repository = ref.read(catalogRepositoryProvider);
    final movie = await repository.getMovie(widget.args.slug);

    ContinueWatchingItem? progress;
    try {
      final rows = await repository.listContinueWatching();
      progress = rows.cast<ContinueWatchingItem?>().firstWhere(
            (item) =>
                item != null &&
                item.playableType == 'movie' &&
                item.playableId == movie.id,
            orElse: () => null,
          );
    } catch (_) {
      progress = null;
    }

    return _MovieDetailData(movie: movie, progress: progress);
  }

  void _retry() {
    setState(() {
      _detailFuture = _load();
    });
  }

  void _playMovie(Movie movie) {
    Navigator.of(context).pushNamed(
      '/player',
      arguments: <String, dynamic>{
        'playable_type': 'movie',
        'playable_id': movie.id,
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Movie')),
      body: FutureBuilder<_MovieDetailData>(
        future: _detailFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return _ErrorState(
              message: 'Failed to load movie details: ${snapshot.error}',
              onRetry: _retry,
            );
          }

          final data = snapshot.data;
          if (data == null || data.movie.id <= 0) {
            return _EmptyState(onRefresh: _retry);
          }

          final movie = data.movie;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[
              if (movie.posterUrl != null && movie.posterUrl!.isNotEmpty)
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.network(
                      movie.posterUrl!,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const ColoredBox(
                        color: Color(0x22000000),
                        child: Center(child: Icon(Icons.broken_image_outlined)),
                      ),
                    ),
                  ),
                ),
              const SizedBox(height: 16),
              Text(
                movie.title,
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  if (movie.year != null) Chip(label: Text('${movie.year}')),
                  if (movie.durationSeconds != null)
                    Chip(label: Text(_formatDuration(movie.durationSeconds!))),
                  ...movie.genres.map((genre) => Chip(label: Text(genre.name))),
                ],
              ),
              const SizedBox(height: 12),
              if (movie.synopsis != null && movie.synopsis!.isNotEmpty)
                Text(movie.synopsis!),
              const SizedBox(height: 16),
              if (data.progress != null) _ResumeHint(progress: data.progress!),
              FilledButton.icon(
                onPressed: () => _playMovie(movie),
                icon: const Icon(Icons.play_arrow),
                label: const Text('Play'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ResumeHint extends StatelessWidget {
  const _ResumeHint({required this.progress});

  final ContinueWatchingItem progress;

  @override
  Widget build(BuildContext context) {
    if (!progress.canResume) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Text(
            'Continue from ${_formatDuration(progress.positionSeconds)} '
            '(remaining ${_formatDuration(progress.remainingSeconds)})',
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

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

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Text('Movie not found.'),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRefresh, child: const Text('Retry')),
        ],
      ),
    );
  }
}

String _formatDuration(int seconds) {
  final duration = Duration(seconds: seconds);
  final hours = duration.inHours;
  final minutes = duration.inMinutes.remainder(60);
  final secs = duration.inSeconds.remainder(60);

  if (hours > 0) {
    return '${hours}h ${minutes}m';
  }

  return '${minutes.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';
}

class _MovieDetailData {
  const _MovieDetailData({required this.movie, required this.progress});

  final Movie movie;
  final ContinueWatchingItem? progress;
}
