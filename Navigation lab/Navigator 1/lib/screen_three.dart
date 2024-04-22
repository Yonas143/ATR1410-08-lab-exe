import 'package:flutter/material.dart';
import 'package:lab_03/screen_four.dart';

class ScreenThree extends StatelessWidget {
  const ScreenThree({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Screen Three"),
      ),
      body: Center(
        child: ElevatedButton(
            child: const Text("Next"),
            onPressed: () {
              Navigator.pushNamed(context, '/four');
            }),
      ),
    );
  }
}
