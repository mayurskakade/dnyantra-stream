import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import 'playback_session_model.dart';
import 'player_controller.dart';
import 'player_repository.dart';
import 'resume_dialog.dart';

class PlayerRouteArgs {
  const PlayerRouteArgs({
    required this.playableType,
    required this.playableId,
  });

  final String playableType;
  final int playableId;
}

class PlayerScreen extends ConsumerStatefulWidget {
  const PlayerScreen({
    super.key,
    required this.playableType,
    required this.playableId,
  });

  static const String routeName = '/player';

  final String playableType;
  final int playableId;

  @override
  ConsumerState<PlayerScreen> createState() => _PlayerScreenState();
}

class _PlayerScreenState extends ConsumerState<PlayerScreen>
    with WidgetsBindingObserver {
  bool _isBootstrapping = true;
  Duration? _resumeFrom;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future<void>.microtask(_bootstrapAndPlay);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.detached) {
      ref.read(playerControllerProvider.notifier).onAppBackgrounded();
    }
  }

  Future<void> _bootstrapAndPlay() async {
    setState(() {
      _isBootstrapping = true;
    });

    final resumeFrom = await _resolveResumePosition();
    if (!mounted) {
      return;
    }

    _resumeFrom = resumeFrom;
    await ref.read(playerControllerProvider.notifier).initializePlayer(
          playableType: widget.playableType,
          playableId: widget.playableId,
          resumeFrom: resumeFrom,
        );

    if (!mounted) {
      return;
    }

    setState(() {
      _isBootstrapping = false;
    });
  }

  Future<Duration?> _resolveResumePosition() async {
    final repository = ref.read(playerRepositoryProvider);
    final progress = await repository.fetchProgress(
      playableType: widget.playableType,
      playableId: widget.playableId,
    );

    if (!mounted || progress == null) {
      return null;
    }

    return _handleProgressDecision(progress);
  }

  Future<Duration?> _handleProgressDecision(
      WatchProgressSnapshot progress) async {
    if (progress.completed) {
      return null;
    }

    if (!progress.shouldOfferResume) {
      return null;
    }

    final decision = await showResumeDialog(
      context,
      position: Duration(seconds: progress.positionSeconds),
    );

    if (decision == ResumeDecision.resume) {
      return Duration(seconds: progress.positionSeconds);
    }

    return null;
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(playerControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Player'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Retry',
            onPressed: _bootstrapAndPlay,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _isBootstrapping && state is PlayerLoading
          ? const _PlayerLoadingView()
          : _buildState(context, state),
    );
  }

  Widget _buildState(BuildContext context, PlayerState state) {
    if (state is PlayerLoading) {
      return const _PlayerLoadingView();
    }

    if (state is PlayerError) {
      return _PlayerErrorView(
        message: state.message,
        onRetry: _bootstrapAndPlay,
      );
    }

    if (state is PlayerEmpty) {
      return _PlayerEmptyView(
        message: state.message,
        onRetry: _bootstrapAndPlay,
      );
    }

    if (state is PlayerReady) {
      return _PlayerReadyView(
        controller: state.controller,
        onTogglePlayPause: () {
          ref.read(playerControllerProvider.notifier).togglePlayPause();
        },
        onSeek: (value) {
          ref.read(playerControllerProvider.notifier).seekTo(value);
        },
        onFlushPause: () {
          ref.read(playerControllerProvider.notifier).onAppBackgrounded();
        },
        resumeFrom: _resumeFrom,
      );
    }

    return const _PlayerLoadingView();
  }
}

class _PlayerLoadingView extends StatelessWidget {
  const _PlayerLoadingView();

  @override
  Widget build(BuildContext context) {
    return const Center(child: CircularProgressIndicator());
  }
}

class _PlayerErrorView extends StatelessWidget {
  const _PlayerErrorView({required this.message, required this.onRetry});

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

class _PlayerEmptyView extends StatelessWidget {
  const _PlayerEmptyView({required this.message, required this.onRetry});

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
            OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _PlayerReadyView extends StatefulWidget {
  const _PlayerReadyView({
    required this.controller,
    required this.onTogglePlayPause,
    required this.onSeek,
    required this.onFlushPause,
    required this.resumeFrom,
  });

  final VideoPlayerController controller;
  final VoidCallback onTogglePlayPause;
  final ValueChanged<Duration> onSeek;
  final VoidCallback onFlushPause;
  final Duration? resumeFrom;

  @override
  State<_PlayerReadyView> createState() => _PlayerReadyViewState();
}

class _PlayerReadyViewState extends State<_PlayerReadyView> {
  double? _draggingSeconds;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<VideoPlayerValue>(
      valueListenable: widget.controller,
      builder: (context, value, _) {
        if (!value.isInitialized) {
          return const _PlayerLoadingView();
        }

        final duration = value.duration;
        final rawPosition = _draggingSeconds == null
            ? value.position
            : Duration(seconds: _draggingSeconds!.toInt());
        final boundedPosition = _boundPosition(rawPosition, duration);

        return Column(
          children: <Widget>[
            Expanded(
              child: Center(
                child: AspectRatio(
                  aspectRatio:
                      value.aspectRatio == 0 ? 16 / 9 : value.aspectRatio,
                  child: VideoPlayer(widget.controller),
                ),
              ),
            ),
            if (widget.resumeFrom != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  'Resumed at ${_formatClock(widget.resumeFrom!)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Column(
                children: <Widget>[
                  Slider(
                    min: 0,
                    max: duration.inSeconds <= 0
                        ? 1
                        : duration.inSeconds.toDouble(),
                    value: duration.inSeconds <= 0
                        ? 0
                        : boundedPosition.inSeconds
                            .toDouble()
                            .clamp(0, duration.inSeconds.toDouble()),
                    onChangeStart: (value) {
                      _draggingSeconds = value;
                    },
                    onChanged: (value) {
                      setState(() {
                        _draggingSeconds = value;
                      });
                    },
                    onChangeEnd: (value) {
                      _draggingSeconds = null;
                      widget.onSeek(Duration(seconds: value.toInt()));
                    },
                  ),
                  Row(
                    children: <Widget>[
                      Text(_formatClock(boundedPosition)),
                      const Spacer(),
                      Text('-${_formatClock(duration - boundedPosition)}'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      FilledButton.icon(
                        onPressed: widget.onTogglePlayPause,
                        icon: Icon(
                            value.isPlaying ? Icons.pause : Icons.play_arrow),
                        label: Text(value.isPlaying ? 'Pause' : 'Play'),
                      ),
                      const SizedBox(width: 12),
                      OutlinedButton(
                        onPressed: widget.onFlushPause,
                        child: const Text('Sync now'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        );
      },
    );
  }

  Duration _boundPosition(Duration position, Duration total) {
    if (position < Duration.zero) {
      return Duration.zero;
    }

    if (total > Duration.zero && position > total) {
      return total;
    }

    return position;
  }

  String _formatClock(Duration value) {
    final safe = value.isNegative ? Duration.zero : value;
    final totalSeconds = safe.inSeconds;
    final hours = totalSeconds ~/ 3600;
    final minutes = (totalSeconds % 3600) ~/ 60;
    final seconds = totalSeconds % 60;

    if (hours > 0) {
      return '$hours:${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
    }

    return '$minutes:${seconds.toString().padLeft(2, '0')}';
  }
}
