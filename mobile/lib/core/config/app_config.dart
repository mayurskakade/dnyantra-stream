class AppConfig {
  const AppConfig({required this.baseUrl});

  final String baseUrl;

  static const String defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8080',
  );
}
