import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'player_controller.dart';

class PlayerScreen extends ConsumerStatefulWidget {
  const PlayerScreen({
    super.key,
    required this.playableId,
    this.playableType = 'movie',
    this.isPublic = false,
  });

  final int playableId;
  final String playableType;
  final bool isPublic;

  @override
  ConsumerState<PlayerScreen> createState() => _PlayerScreenState();
}

class _PlayerScreenState extends ConsumerState<PlayerScreen> {
  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_loadSession);
  }

  Future<void> _loadSession() {
    return ref.read(playerControllerProvider.notifier).requestPlaybackSession(
          playableId: widget.playableId,
          playableType: widget.playableType,
          isPublic: widget.isPublic,
        );
  }

  @override
  Widget build(BuildContext context) {
    final sessionAsync = ref.watch(playerControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Player'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Reload Session',
            onPressed: _loadSession,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: sessionAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _PlayerErrorState(
          message: 'Failed to create playback session: $error',
          onRetry: _loadSession,
        ),
        data: (session) {
          if (session == null) {
            return _PlayerEmptyState(onRequestSession: _loadSession);
          }

          return _PlayerDataState(
            playbackUrl: session.playbackUrl,
            sessionId: session.sessionId,
            expiresAt: session.expiresAt,
          );
        },
      ),
    );
  }
}

class _PlayerErrorState extends StatelessWidget {
  const _PlayerErrorState({required this.message, required this.onRetry});

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

class _PlayerEmptyState extends StatelessWidget {
  const _PlayerEmptyState({required this.onRequestSession});

  final VoidCallback onRequestSession;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Text('No playback session yet.'),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: onRequestSession,
            child: const Text('Request Session'),
          ),
        ],
      ),
    );
  }
}

class _PlayerDataState extends StatelessWidget {
  const _PlayerDataState({
    required this.playbackUrl,
    required this.sessionId,
    required this.expiresAt,
  });

  final String playbackUrl;
  final String sessionId;
  final DateTime? expiresAt;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Text(
          'Playback URL',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        SelectableText(playbackUrl),
        const SizedBox(height: 20),
        Text(
          'Session ID',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        SelectableText(sessionId),
        if (expiresAt != null) ...<Widget>[
          const SizedBox(height: 20),
          Text(
            'Expires At (UTC)',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          SelectableText(expiresAt!.toIso8601String()),
        ],
      ],
    );
  }
}
