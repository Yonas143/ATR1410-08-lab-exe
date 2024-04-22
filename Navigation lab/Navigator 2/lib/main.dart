import 'package:courses_app/course.dart';
import 'package:courses_app/course_details_screen.dart';
import 'package:courses_app/courses_list_screen.dart';
import 'package:flutter/material.dart';

void main() {
  runApp(const CourseApp());
}

class CourseApp extends StatefulWidget {
  const CourseApp({super.key});

  @override
  State<CourseApp> createState() => _CourseAppState();
}

class _CourseAppState extends State<CourseApp> {
  Course? _selectedCourse;

  void _tabHandler(Course course) {
    setState(() {
      _selectedCourse = course;
    });
  }

  List<Course> courses = [
    Course(
      code: "SiTE-001",
      title: "Introduction to Programming",
      description: "Computer Organization, Architecture, Programming",
    ),
    Course(
      code: "SiTE-002",
      title: "Fundamentals of Cybersecurity",
      description: "Security studies, Computer crimes",
    ),
    Course(
      code: "SiTE-003",
      title: "Discrete Mathematics",
      description: "Number theory, logic",
    ),
    Course(
      code: "SiTE-004",
      title: "Mobile Programming",
      description: "Android, iOS Programming",
    ),
    Course(
      code: "SiTE-005",
      title: "Fundamentals of Networking",
      description: "Internet, routing, and network",
    ),
    Course(
      code: "SiTE-006",
      title: "Introduction to OOP",
      description: "Java Programming",
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Navigator 2.0',
      home: Navigator(
        pages: [
          MaterialPage(
            key: const ValueKey("CourseListScreen"),
            child: CoursesListScreen(
              courses: courses,
              onTapped: _tabHandler,
            ),
          ),
          if (_selectedCourse != null)
            MaterialPage(
                key: ValueKey(_selectedCourse),
                child: CourseDetailsScreen(
                  course: _selectedCourse!,
                ))
        ],
        onPopPage: (route, result) {
          if (!route.didPop(result)) {
            return false;
          }
          setState(() {
            _selectedCourse = null;
          });
          return true;
        },
      ),
    );
  }
}
