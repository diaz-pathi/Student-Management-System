<?php
session_start();
require_once('../lib/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Instructor') {
        header("Location: students.php?error=unauthorized");
        exit();
    }

    $student_id = intval($_POST['student_id']);

    // Get student details
    $sq = mysqli_query($conn, "SELECT st_custom_id FROM students WHERE st_id = $student_id");
    if ($sq && mysqli_num_rows($sq) > 0) {
        $st = mysqli_fetch_assoc($sq);
        $st_custom_id = $st['st_custom_id'];

        // Remove from users where custom_id matches and role = Monitor
        $stmt = $conn->prepare("DELETE FROM users WHERE custom_id = ? AND role = 'Monitor'");
        $stmt->bind_param("s", $st_custom_id);
        $stmt->execute();
        $stmt->close();

        // Update students table role back to Student
        mysqli_query($conn, "UPDATE students SET role = 'Student' WHERE st_id = $student_id");

        header("Location: students.php?monitor_removed=success");
        exit();
    } else {
        header("Location: students.php?error=nostudent");
        exit();
    }
} else {
    header("Location: students.php");
    exit();
}
?>
