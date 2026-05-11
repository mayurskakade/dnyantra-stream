import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'home_repository.dart';

final homeRepositoryProvider = Provider<HomeRepository>((ref) {
  final dio = ref.watch(apiDioProvider);
  return HomeRepository(dio);
});

final homeProvider = FutureProvider<HomeData>((ref) {
  final repository = ref.watch(homeRepositoryProvider);
  return repository.fetchHome();
});
