<?php
// site/ajax_mark_qr_attendance.php
// Live QR Scanner Attendance Marking & WhatsApp Notification Dispatcher

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/whatsapp_helper.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($role !== 'Instructor' && $role !== 'Monitor' && $role !== 'Admin' && $role !== 'Super Admin' && $role !== 'SuperAdmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$qr_data = trim($_POST['qr_data'] ?? '');
if (empty($qr_data)) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received.']);
    exit();
}

$st_custom_id = mysqli_real_escape_string($conn, $qr_data);

// Fetch user's custom_id for marked_by
$user_custom_id = $_SESSION['custom_id'] ?? '';
if (empty($user_custom_id) && !empty($user_id)) {
    $uq = mysqli_query($conn, "SELECT custom_id FROM users WHERE id = '$user_id' LIMIT 1");
    if ($uq && mysqli_num_rows($uq) > 0) {
        $user_custom_id = mysqli_fetch_assoc($uq)['custom_id'] ?? '';
    }
}
$marked_by = !empty($user_custom_id) ? $user_custom_id : ($role . ' ' . ($_SESSION['username'] ?? ''));

// Fetch student by st_custom_id or nic
$sq = "SELECT s.st_id, s.st_custom_id, s.name, s.phone, s.image, s.course_id, s.batch_id, s.status, c.course_name, b.batch_name 
       FROM students s 
       LEFT JOIN courses c ON s.course_id = c.course_id 
       LEFT JOIN batches b ON s.batch_id = b.batch_id 
       WHERE s.st_custom_id = '$st_custom_id' OR s.nic = '$st_custom_id' LIMIT 1";

$sres = mysqli_query($conn, $sq);
if (!$sres || mysqli_num_rows($sres) === 0) {
    echo json_encode(['success' => false, 'message' => "No student found with ID: " . htmlspecialchars($qr_data)]);
    exit();
}

$student = mysqli_fetch_assoc($sres);
$st_num = $student['st_custom_id'];
$course_name = $student['course_name'] ?? $student['course_id'];
$batch_name = $student['batch_name'] ?? $student['batch_id'];

// Optional batch filter validation if passed
$active_batch = trim($_POST['active_batch'] ?? '');
if (!empty($active_batch) && $student['batch_id'] !== $active_batch) {
    echo json_encode([
        'success' => false, 
        'message' => "Student " . htmlspecialchars($student['name']) . " belongs to Batch (" . htmlspecialchars($batch_name) . "), not the active batch being marked."
    ]);
    exit();
}

// 🌍 Device Date Resolution
$target_date = trim($_POST['device_date'] ?? '');
if (empty($target_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    $target_date = date('Y-m-d');
}
$target_date = mysqli_real_escape_string($conn, $target_date);
$current_datetime = $target_date . ' ' . date('H:i:s');
$current_year = date('Y', strtotime($target_date));

// Check if attendance already marked for target date
$check = mysqli_query($conn, "SELECT id, absent_present FROM student_attendance WHERE student_number = '$st_num' AND DATE(date) = '$target_date' LIMIT 1");
$already_present = false;

if ($check && mysqli_num_rows($check) > 0) {
    $existing = mysqli_fetch_assoc($check);
    if ((int)$existing['absent_present'] === 1) {
        $already_present = true;
    }
    // Update to Present
    mysqli_query($conn, "UPDATE student_attendance SET absent_present = 1, course_name = '" . mysqli_real_escape_string($conn, $course_name) . "', marked_by = '" . mysqli_real_escape_string($conn, $marked_by) . "', date = '$current_datetime' WHERE student_number = '$st_num' AND DATE(date) = '$target_date'");
} else {
    // Generate new attendance ID
    $q_atd = "SELECT attendance_id FROM student_attendance WHERE attendance_id LIKE 'ATD-$current_year-%' ORDER BY id DESC LIMIT 1";
    $r_atd = mysqli_query($conn, $q_atd);
    $count = 0;
    if ($r_atd && mysqli_num_rows($r_atd) > 0) {
        $last_id = mysqli_fetch_assoc($r_atd)['attendance_id'];
        $parts = explode('-', $last_id);
        $count = intval(end($parts));
    }
    $count++;
    $atd_id = "ATD-" . $current_year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    $ins_q = "INSERT INTO student_attendance (attendance_id, student_number, course_name, absent_present, date, marked_by) 
              VALUES ('$atd_id', '$st_num', '" . mysqli_real_escape_string($conn, $course_name) . "', 1, '$current_datetime', '" . mysqli_real_escape_string($conn, $marked_by) . "')";
    mysqli_query($conn, $ins_q);
}

// Automatically trigger WhatsApp Present Notification
$wa_result = send_present_notification($conn, $st_num, $course_name, $batch_name, $target_date);

$img_src = !empty($student['image']) ? 'data:image/jpeg;base64,' . base64_encode($student['image']) : '';

echo json_encode([
    'success' => true,
    'already_marked' => $already_present,
    'message' => $already_present ? 'Attendance was already marked earlier today (Verified Present).' : 'Attendance Marked Successfully!',
    'student' => [
        'custom_id' => $student['st_custom_id'],
        'name' => $student['name'],
        'phone' => $student['phone'] ?? '',
        'course_name' => $course_name,
        'batch_name' => $batch_name,
        'time' => date('h:i:s A'),
        'date' => date('l, d M Y', strtotime($target_date)),
        'image' => $img_src
    ],
    'whatsapp' => [
        'status' => $wa_result['status'] ?? false,
        'message' => $wa_result['message'] ?? 'Notification sent successfully'
    ]
]);
