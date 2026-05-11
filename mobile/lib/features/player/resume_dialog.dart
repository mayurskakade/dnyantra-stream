import 'package:flutter/material.dart';

enum ResumeDecision {
  resume,
  startOver,
}

Future<ResumeDecision?> showResumeDialog(
  BuildContext context, {
  required Duration position,
}) {
  final message = _formatDuration(position);

  return showDialog<ResumeDecision>(
    context: context,
    builder: (context) {
      return AlertDialog(
        title: const Text('Resume playback?'),
        content: Text('Resume from $message?'),
        actions: <Widget>[
          TextButton(
            onPressed: () {
              Navigator.of(context).pop(ResumeDecision.startOver);
            },
            child: const Text('Start over'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.of(context).pop(ResumeDecision.resume);
            },
            child: const Text('Resume'),
          ),
        ],
      );
    },
  );
}

String _formatDuration(Duration value) {
  final minutes = value.inMinutes;
  final seconds = value.inSeconds.remainder(60);
  return '$minutes:${seconds.toString().padLeft(2, '0')}';
}
