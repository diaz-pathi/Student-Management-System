<?php
// site/get_student_by_qr.php
// Lookup student details via scanned st_custom_id

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/db.php';

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

if (empty($user_id) && empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}

if (empty($role) && !empty($user_id)) {
    $uq = mysqli_query($conn, "SELECT role FROM users WHERE id = '" . (int)$user_id . "' LIMIT 1");
    if ($uq && $ur = mysqli_fetch_assoc($uq)) {
        $role = $ur['role'];
        $_SESSION['role'] = $role;
    }
}

$qr_data = trim($_POST['qr_data'] ?? $_GET['qr_data'] ?? '');
if (empty($qr_data)) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received.']);
    exit();
}

// Check if QR data is JSON formatted
if (strpos($qr_data, '{') === 0) {
    $json = json_decode($qr_data, true);
    if ($json && !empty($json['id'])) {
        $qr_data = $json['id'];
    } elseif ($json && !empty($json['st_custom_id'])) {
        $qr_data = $json['st_custom_id'];
    }
}

$clean_id = trim(preg_replace('/[^\w\-]/', '', $qr_data));
$escaped_raw = mysqli_real_escape_string($conn, $qr_data);
$escaped_clean = mysqli_real_escape_string($conn, $clean_id);

// If user is Instructor, find instructor's course
$instructor_course = '';
if ($role === 'Instructor') {
    $uname = $_SESSION['username'] ?? '';
    $iq = mysqli_query($conn, "SELECT course_id FROM users WHERE (id = '$user_id' OR username = '$uname') AND role = 'Instructor' LIMIT 1");
    if ($iq && mysqli_num_rows($iq) > 0) {
        $instructor_course = mysqli_fetch_assoc($iq)['course_id'];
    }
}

$sql = "SELECT s.st_id as id, s.st_custom_id as custom_id, s.name as fullname, s.nic, s.dob, s.address, s.gender, s.status, s.email, s.phone, s.image, s.course_id, s.batch_id, s.role, c.course_name, b.batch_name 
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.course_id 
        LEFT JOIN batches b ON s.batch_id = b.batch_id 
        WHERE LOWER(TRIM(s.st_custom_id)) = LOWER('$escaped_raw') 
           OR LOWER(TRIM(s.st_custom_id)) = LOWER('$escaped_clean') 
           OR LOWER(TRIM(s.nic)) = LOWER('$escaped_raw') 
           OR LOWER(TRIM(s.nic)) = LOWER('$escaped_clean') 
           OR s.st_id = '$escaped_clean' 
        LIMIT 1";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'message' => "No student found with ID: " . htmlspecialchars($qr_data)]);
    exit();
}

$student = mysqli_fetch_assoc($res);

// Check instructor course scope
if ($role === 'Instructor' && !empty($instructor_course) && !empty($student['course_id']) && $student['course_id'] !== $instructor_course) {
    echo json_encode([
        'success' => false, 
        'message' => "Student found (" . htmlspecialchars($student['fullname']) . "), but they belong to another course: " . htmlspecialchars($student['course_name'] ?? $student['course_id'])
    ]);
    exit();
}

$img_src = !empty($student['image']) ? 'data:image/jpeg;base64,' . base64_encode($student['image']) : '';

echo json_encode([
    'success' => true,
    'student' => [
        'id' => $student['id'],
        'custom_id' => $student['custom_id'],
        'fullname' => $student['fullname'],
        'nic' => $student['nic'] ?? '',
        'dob' => $student['dob'] ?? '',
        'address' => $student['address'] ?? '',
        'gender' => $student['gender'] ?? '',
        'email' => $student['email'] ?? '',
        'phone' => $student['phone'] ?? '',
        'status' => $student['status'] ?? 'Active',
        'course_id' => $student['course_id'] ?? '',
        'course_name' => $student['course_name'] ?? ($student['course_id'] ?? 'N/A'),
        'batch_id' => $student['batch_id'] ?? '',
        'batch_name' => $student['batch_name'] ?? ($student['batch_id'] ?? 'N/A'),
        'role' => $student['role'] ?? 'Student',
        'image' => $img_src
    ]
]);
