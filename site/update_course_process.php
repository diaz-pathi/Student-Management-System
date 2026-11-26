<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id = intval($_POST['id']);
    // course_id should not be changed typically, but if submitted we can update or ignore. We'll keep it.
    $course_id = trim($_POST['course_id'] ?? '');
    $course_name = trim($_POST['course_name']);
    $course_duration = trim($_POST['course_duration']);
    $instructor_name = trim($_POST['instructor_name']);

    if (empty($course_name)) {
        header("Location: courses.php?error=validation");
        exit();
    }

    $course_id = mysqli_real_escape_string($conn, $course_id);
    $course_name = mysqli_real_escape_string($conn, $course_name);
    $course_duration = mysqli_real_escape_string($conn, $course_duration);
    $instructor_name = mysqli_real_escape_string($conn, $instructor_name);

    $query = "UPDATE courses SET 
                course_name = '$course_name', 
                course_duration = '$course_duration', 
                instructor_name = '$instructor_name' 
              WHERE id = $id";

    // If we wanted to allow updating course_id, we'd add it to query. But better to leave it.

    if (mysqli_query($conn, $query)) {
        header("Location: courses.php?updated=success");
        exit();
    } else {
        header("Location: courses.php?error=db");
        exit();
    }
}
?>
