<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/db.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$is_super_admin = ($role === 'Super Admin' || $role === 'SuperAdmin');
$is_admin = ($role === 'Admin' || $is_super_admin);

if (!$is_admin && $role !== 'Instructor') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    header("Location: dashboard.php");
    exit();
}

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = mysqli_real_escape_string($conn, trim($_POST['course_id'] ?? ''));
    $batch_id = mysqli_real_escape_string($conn, trim($_POST['batch_id'] ?? ''));
    $course_name = mysqli_real_escape_string($conn, trim($_POST['course_name'] ?? ''));
    $attendance_date = trim($_POST['attendance_date'] ?? '');
    $marked_by = trim($_POST['marked_by'] ?? '');
    $attendance_data = $_POST['attendance'] ?? []; // Array [student_number => 1/0]

    // Fallback for course_name if not passed
    if (empty($course_name) && !empty($course_id)) {
        $cq = mysqli_query($conn, "SELECT course_name FROM courses WHERE course_id = '$course_id' LIMIT 1");
        if ($cq && mysqli_num_rows($cq) > 0) {
            $course_name = mysqli_fetch_assoc($cq)['course_name'];
        }
    }
    $course_name = mysqli_real_escape_string($conn, $course_name);

    // Fallback for marked_by
    if (empty($marked_by)) {
        $uq = mysqli_query($conn, "SELECT custom_id FROM users WHERE id = '$user_id' LIMIT 1");
        if ($uq && mysqli_num_rows($uq) > 0) {
            $marked_by = mysqli_fetch_assoc($uq)['custom_id'] ?? 'Admin';
        } else {
            $marked_by = $_SESSION['username'] ?? 'Admin';
        }
    }
    $marked_by = mysqli_real_escape_string($conn, $marked_by);

    // Validation
    if (empty($attendance_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please select a valid date.']);
            exit();
        }
        header("Location: settings.php?tab=past_attendance&err=invalid_date");
        exit();
    }

    if (empty($attendance_data) || !is_array($attendance_data)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No student attendance records were marked.']);
            exit();
        }
        header("Location: settings.php?tab=past_attendance&err=no_students");
        exit();
    }

    $att_datetime = $attendance_date . ' 09:00:00';
    $year_of_date = date('Y', strtotime($attendance_date));

    // Get the last ID count for generating unique attendance_id
    $q_last = "SELECT attendance_id FROM student_attendance WHERE attendance_id LIKE 'ATD-$year_of_date-%' ORDER BY id DESC LIMIT 1";
    $r_last = mysqli_query($conn, $q_last);
    $count = 0;
    if ($r_last && mysqli_num_rows($r_last) > 0) {
        $last_id = mysqli_fetch_assoc($r_last)['attendance_id'];
        $parts = explode('-', $last_id);
        $count = intval(end($parts));
    }

    $present_count = 0;
    $absent_count = 0;
    $total_marked = 0;

    foreach ($attendance_data as $student_number => $status) {
        $st_num = mysqli_real_escape_string($conn, $student_number);
        $stat = (intval($status) === 1) ? 1 : 0;

        if ($stat === 1) $present_count++;
        else $absent_count++;
        $total_marked++;

        // Check if already marked on this specific date
        $check_q = "SELECT id FROM student_attendance WHERE student_number = '$st_num' AND DATE(date) = '$attendance_date' LIMIT 1";
        $check = mysqli_query($conn, $check_q);

        if ($check && mysqli_num_rows($check) > 0) {
            // Update existing entry
            $row_id = mysqli_fetch_assoc($check)['id'];
            $update_q = "UPDATE student_attendance 
                         SET absent_present = $stat, 
                             course_name = '$course_name', 
                             marked_by = '$marked_by', 
                             date = '$att_datetime' 
                         WHERE id = '$row_id'";
            mysqli_query($conn, $update_q);
        } else {
            // Insert new entry
            $count++;
            $atd_id = "ATD-" . $year_of_date . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            $insert_q = "INSERT INTO student_attendance (attendance_id, student_number, course_name, absent_present, date, marked_by) 
                         VALUES ('$atd_id', '$st_num', '$course_name', $stat, '$att_datetime', '$marked_by')";
            mysqli_query($conn, $insert_q);
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Historical attendance for $attendance_date successfully saved!",
            'date' => $attendance_date,
            'total' => $total_marked,
            'present' => $present_count,
            'absent' => $absent_count,
            'course_id' => $course_id,
            'batch_id' => $batch_id
        ]);
        exit();
    }

    header("Location: settings.php?tab=past_attendance&msg=att_saved&date=$attendance_date&course=$course_id&batch=$batch_id&p=$present_count&a=$absent_count");
    exit();
}

header("Location: settings.php");
exit();
