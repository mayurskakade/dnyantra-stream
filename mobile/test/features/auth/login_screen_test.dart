import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:private_stream_mobile/features/auth/login_screen.dart';

import '../../widget_test_helpers.dart';

void main() {
  testWidgets('renders login form fields', (WidgetTester tester) async {
    await tester.pumpWidget(
      buildTestApp(child: const LoginScreen()),
    );

    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });

  testWidgets('shows required field errors when empty submit is attempted',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      buildTestApp(child: const LoginScreen()),
    );

    await tester.enterText(find.byType(TextFormField).first, '');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Email is required'), findsOneWidget);
    expect(find.text('Password is required'), findsOneWidget);
  });

  testWidgets('shows invalid email validation message',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      buildTestApp(child: const LoginScreen()),
    );

    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'invalid');
    await tester.enterText(fields.at(1), 'secret');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Enter a valid email'), findsOneWidget);
  });
}
