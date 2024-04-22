import 'package:flutter/material.dart';
import 'package:lab_03/screen_y.dart';

class ScreenX extends StatelessWidget {
  const ScreenX({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Screen X"),
      ),
      body: Center(
        child: ElevatedButton(
            child: const Text("Next"),
            onPressed: () async {
              final reply = await Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (BuildContext context) => ScreenY(),
                      settings: const RouteSettings(arguments: "Data from x")));
              print(reply);
            }),
      ),
    );
  }
}
