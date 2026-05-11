import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:private_stream_mobile/core/network/api_client.dart';
import 'package:private_stream_mobile/core/storage/token_storage.dart';
import 'package:private_stream_mobile/main.dart';

void main() {
  testWidgets('shows login screen when no stored session',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          tokenStorageProvider.overrideWithValue(InMemoryTokenStorage()),
        ],
        child: const App(),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
