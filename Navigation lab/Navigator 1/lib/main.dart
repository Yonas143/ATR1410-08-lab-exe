import 'package:flutter/material.dart';
import 'package:lab_03/screen_four.dart';
import 'package:lab_03/screen_one.dart';
import 'package:lab_03/screen_three.dart';
import 'package:lab_03/screen_two.dart';

void main() {
  runApp(Home());
}

class Home extends StatelessWidget {
  const Home({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: "Navigation Demo - Nav 1.0",
      initialRoute: '/',
      routes: {
        '/': (context) => ScreenOne(),
        '/second': (context) => ScreenTwo(),
      },
      onGenerateRoute: (RouteSettings settings) {
        switch (settings.name) {
          case '/three':
            return MaterialPageRoute(builder: (context) => ScreenThree());
          case '/four':
            return MaterialPageRoute(builder: (context) => ScreenFour());
        }
      },
    );
  }
}
