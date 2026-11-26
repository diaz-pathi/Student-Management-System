<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_name = $_POST['course_name'] ?? '';
    $course_duration = $_POST['course_duration'] ?? '';
    $instructor_name = $_POST['instructor_name'] ?? '';

    $course_data = "course_name=" . urlencode($course_name) .
                   "&course_duration=" . urlencode($course_duration) .
                   "&instructor_name=" . urlencode($instructor_name);

    if (empty($course_name)) {
        header("Location: add_course.php?error=course_name_empty&field=course_name&$course_data");
        exit();
    }

    // Auto-generate course_id
    $prefix = "CRS";
    $year = date("Y");
    $search_prefix = $prefix . "-" . $year . "-%";

    $id_sql = "SELECT course_id FROM courses WHERE course_id LIKE '$search_prefix' ORDER BY id DESC LIMIT 1";
    $id_result = $conn->query($id_sql);

    if ($id_result && $id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $last_id = $row['course_id']; 
        $num = (int)substr($last_id, -3) + 1;
        $course_id = $prefix . "-" . $year . "-" . sprintf("%03d", $num);
    } else {
        $course_id = $prefix . "-" . $year . "-001";
    }

    $sql = "INSERT INTO courses (course_id, course_name, course_duration, instructor_name) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $course_id, $course_name, $course_duration, $instructor_name);

    if ($stmt->execute()) {
        header("Location: add_course.php?status=success");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: courses.php");
    exit();
}
