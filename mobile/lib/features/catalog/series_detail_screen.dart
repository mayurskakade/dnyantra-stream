import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'catalog_models.dart';
import 'catalog_repository.dart';

class SeriesDetailArgs {
  const SeriesDetailArgs({required this.slug});

  final String slug;
}

class SeriesDetailScreen extends ConsumerStatefulWidget {
  const SeriesDetailScreen({super.key, required this.args});

  static const String routeName = '/series/detail';

  final SeriesDetailArgs args;

  @override
  ConsumerState<SeriesDetailScreen> createState() => _SeriesDetailScreenState();
}

class _SeriesDetailScreenState extends ConsumerState<SeriesDetailScreen> {
  late Future<Series> _seriesFuture;
  AsyncValue<List<Episode>> _episodesState = const AsyncValue.loading();
  int? _selectedSeasonId;

  @override
  void initState() {
    super.initState();
    _seriesFuture = _loadSeries();
  }

  Future<Series> _loadSeries() async {
    final repository = ref.read(catalogRepositoryProvider);
    final series = await repository.getSeries(widget.args.slug);

    if (series.seasons.isEmpty) {
      _episodesState = const AsyncValue.data(<Episode>[]);
      _selectedSeasonId = null;
    } else {
      final fallbackSeasonId = _selectedSeasonId ?? series.seasons.first.id;
      _selectedSeasonId = fallbackSeasonId;
      unawaited(_loadEpisodes(fallbackSeasonId));
    }

    return series;
  }

  Future<void> _loadEpisodes(int seasonId) async {
    setState(() {
      _episodesState = const AsyncValue.loading();
      _selectedSeasonId = seasonId;
    });

    try {
      final repository = ref.read(catalogRepositoryProvider);
      final episodes = await repository.listEpisodes(seasonId);
      if (!mounted) {
        return;
      }

      setState(() {
        _episodesState = AsyncValue.data(episodes);
      });
    } catch (error, stackTrace) {
      if (!mounted) {
        return;
      }

      setState(() {
        _episodesState = AsyncValue.error(error, stackTrace);
      });
    }
  }

  void _retrySeries() {
    setState(() {
      _episodesState = const AsyncValue.loading();
      _seriesFuture = _loadSeries();
    });
  }

  void _playEpisode(Episode episode) {
    Navigator.of(context).pushNamed(
      '/player',
      arguments: <String, dynamic>{
        'playable_type': 'episode',
        'playable_id': episode.id,
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Series')),
      body: FutureBuilder<Series>(
        future: _seriesFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return _ErrorState(
              message: 'Failed to load series details: ${snapshot.error}',
              onRetry: _retrySeries,
            );
          }

          final series = snapshot.data;
          if (series == null || series.id <= 0) {
            return _EmptySeriesState(onRefresh: _retrySeries);
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[
              Text(
                series.title,
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              if (series.synopsis != null && series.synopsis!.isNotEmpty)
                Text(series.synopsis!),
              const SizedBox(height: 16),
              if (series.seasons.isEmpty)
                const _EmptyEpisodesState(
                  message: 'No seasons available for this series yet.',
                )
              else ...<Widget>[
                DropdownButtonFormField<int>(
                  value: _selectedSeasonId,
                  decoration: const InputDecoration(
                    labelText: 'Season',
                    border: OutlineInputBorder(),
                  ),
                  items: series.seasons
                      .map(
                        (season) => DropdownMenuItem<int>(
                          value: season.id,
                          child: Text(
                            '${season.displayLabel}${season.episodeCount != null ? ' (${season.episodeCount})' : ''}',
                          ),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: (value) {
                    if (value != null) {
                      _loadEpisodes(value);
                    }
                  },
                ),
                const SizedBox(height: 16),
                _episodesState.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (error, _) => _ErrorState(
                    message: 'Failed to load episodes: $error',
                    onRetry: () {
                      final seasonId = _selectedSeasonId;
                      if (seasonId != null) {
                        _loadEpisodes(seasonId);
                      }
                    },
                  ),
                  data: (episodes) {
                    if (episodes.isEmpty) {
                      return const _EmptyEpisodesState(
                        message: 'No episodes available for this season.',
                      );
                    }

                    return Column(
                      children: episodes
                          .map(
                            (episode) => Card(
                              child: ListTile(
                                title: Text(
                                    'E${episode.number} · ${episode.title}'),
                                subtitle: episode.durationSeconds == null
                                    ? null
                                    : Text(_formatDuration(
                                        episode.durationSeconds!)),
                                trailing: FilledButton(
                                  onPressed: () => _playEpisode(episode),
                                  child: const Text('Play'),
                                ),
                              ),
                            ),
                          )
                          .toList(growable: false),
                    );
                  },
                ),
              ],
            ],
          );
        },
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

class _EmptySeriesState extends StatelessWidget {
  const _EmptySeriesState({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Text('Series not found.'),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRefresh, child: const Text('Retry')),
        ],
      ),
    );
  }
}

class _EmptyEpisodesState extends StatelessWidget {
  const _EmptyEpisodesState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(child: Text(message));
  }
}

String _formatDuration(int seconds) {
  final duration = Duration(seconds: seconds);
  final minutes = duration.inMinutes;
  final secs = duration.inSeconds.remainder(60);
  return '${minutes.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';
}
