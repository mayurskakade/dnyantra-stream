import 'package:dio/dio.dart';

import 'home_models.dart';

class HomeRepository {
  HomeRepository(this._dio);

  final Dio _dio;

  Future<HomeData> fetchHome() async {
    final response = await _dio.get<dynamic>('/api/home');
    return HomeData.fromJson(response.data);
  }
}
