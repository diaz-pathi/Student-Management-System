<?php
session_start();
require_once('../lib/db.php');
require_once('../lib/whatsapp_helper.php');

$role = $_SESSION['role'] ?? '';

if ($role !== 'Instructor' && $role !== 'Monitor') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $course_name = mysqli_real_escape_string($conn, $_POST['course_name'] ?? '');
    $course_id = mysqli_real_escape_string($conn, $_POST['course_id'] ?? '');
    $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id'] ?? '');
    $batch_name = mysqli_real_escape_string($conn, $_POST['batch_name'] ?? '');
    $marked_by = trim($_POST['marked_by'] ?? '');

    if (empty($marked_by)) {
        $q_u = mysqli_query($conn, "SELECT custom_id FROM users WHERE id = '$user_id'");
        if ($q_u && mysqli_num_rows($q_u) > 0) {
            $marked_by = mysqli_fetch_assoc($q_u)['custom_id'] ?? '';
        }
    }
    $marked_by = mysqli_real_escape_string($conn, $marked_by);
    $attendance_data = $_POST['attendance'] ?? []; // Array [student_number => 1/0]

    if (empty($attendance_data)) {
        header("Location: attendance.php?error=nodata");
        exit();
    }

    $attendance_date = trim($_POST['attendance_date'] ?? '');
    if (empty($attendance_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
        $attendance_date = date('Y-m-d');
    }
    $attendance_date = mysqli_real_escape_string($conn, $attendance_date);
    $current_datetime = $attendance_date . ' ' . date('H:i:s');
    $current_year = date('Y', strtotime($attendance_date));
    
    // Get the last ID once before the loop
    $q = "SELECT attendance_id FROM student_attendance WHERE attendance_id LIKE 'ATD-$current_year-%' ORDER BY id DESC LIMIT 1";
    $r = mysqli_query($conn, $q);
    if ($r && mysqli_num_rows($r) > 0) {
        $last_id = mysqli_fetch_assoc($r)['attendance_id'];
        $parts = explode('-', $last_id);
        $count = intval(end($parts));
    } else {
        $count = 0;
    }

    foreach ($attendance_data as $student_number => $status) {
        $st_num = mysqli_real_escape_string($conn, $student_number);
        $stat = intval($status);

        // check if already marked on this device date
        $check = mysqli_query($conn, "SELECT id FROM student_attendance WHERE student_number = '$st_num' AND DATE(date) = '$attendance_date'");
        if ($check && mysqli_num_rows($check) > 0) {
            // Update
            mysqli_query($conn, "UPDATE student_attendance SET absent_present = $stat, course_name = '$course_name', marked_by = '$marked_by', date = '$current_datetime' WHERE student_number = '$st_num' AND DATE(date) = '$attendance_date'");
        } else {
            // Insert
            $count++;
            $atd_id = "ATD-" . $current_year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            $query = "INSERT INTO student_attendance (attendance_id, student_number, course_name, absent_present, date, marked_by) 
                      VALUES ('$atd_id', '$st_num', '$course_name', $stat, '$current_datetime', '$marked_by')";
            mysqli_query($conn, $query);
        }
    }

    // Automatically send WhatsApp notification to absent students in this batch
    if (!empty($course_id) && !empty($batch_id)) {
        send_absent_notifications_for_batch($conn, $course_id, $batch_id, $course_name, $batch_name, $attendance_date);
    }

    header("Location: attendance.php?success=marked");
    exit();
}
?>
