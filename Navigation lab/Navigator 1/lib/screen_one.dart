import 'package:flutter/material.dart';
import 'package:lab_03/screen_three.dart';
import 'package:lab_03/screen_two.dart';
import 'package:lab_03/screen_x.dart';

class ScreenOne extends StatelessWidget {
  const ScreenOne({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Screen One"),
      ),
      backgroundColor: Colors.blue[200],
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              "Screen 1",
              style: TextStyle(
                fontSize: 24,
              ),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (BuildContext context) => const ScreenTwo(),
                    ));
              },
              child: const Text("Go to Screen 2"),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (BuildContext context) => const ScreenThree(),
                    ));
              },
              child: const Text("Go to Screen 3"),
            ),
          ],
        ),
      ),
    );
  }
}
