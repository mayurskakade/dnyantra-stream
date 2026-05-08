import 'package:flutter/material.dart';
void main() => runApp(const App());
class App extends StatelessWidget { const App({super.key}); @override Widget build(BuildContext c)=>MaterialApp(home: Scaffold(appBar: AppBar(title: const Text('Private Stream')), body: const Center(child: Text('Skeleton')))); }
