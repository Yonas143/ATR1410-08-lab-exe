import 'package:courses_app/course.dart';
import 'package:flutter/material.dart';

class CourseDetailsScreen extends StatelessWidget {
  final Course course;
  const CourseDetailsScreen({super.key, required this.course});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(course.title)),
      body: Center(
        child: Column(
          children: [
            Text(course.title),
            Text(course.code),
            Text(course.description),
          ],
        ),
      ),
    );
  }
}
