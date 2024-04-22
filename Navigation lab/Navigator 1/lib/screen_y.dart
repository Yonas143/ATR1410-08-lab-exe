import 'package:flutter/material.dart';

class ScreenY extends StatelessWidget {
  const ScreenY({super.key});

  @override
  Widget build(BuildContext context) {
    final text = ModalRoute.of(context)!.settings.arguments as String;
    return Scaffold(
      appBar: AppBar(
        title: const Text("Screen Y"),
      ),
      body: Center(
        child: Text(text),
      ),
    );
  }
}
