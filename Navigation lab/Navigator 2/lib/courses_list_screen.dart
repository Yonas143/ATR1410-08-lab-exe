import 'package:courses_app/course.dart';
import 'package:flutter/material.dart';

class CoursesListScreen extends StatelessWidget {
  const CoursesListScreen(
      {super.key, required this.courses, required this.onTapped});
  final List<Course> courses;
  final ValueChanged<Course> onTapped;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Courses List"),
      ),
      body: ListView(
        children: [
          for (var course in courses)
            ListTile(
              title: Text(course.title),
              subtitle: Text(course.code),
              onTap: () => onTapped(course),
            ),
        ],
      ),
    );
  }
}
