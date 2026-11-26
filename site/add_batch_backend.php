<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $batch_name = $_POST['batch_name'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;

    $batch_data = "batch_name=" . urlencode($batch_name) .
                  "&course_id=" . urlencode($course_id) .
                  "&start_date=" . urlencode($_POST['start_date'] ?? '') .
                  "&end_date=" . urlencode($_POST['end_date'] ?? '');

    if (empty($batch_name)) {
        header("Location: add_batch.php?error=batch_name_empty&field=batch_name&$batch_data");
        exit();
    }
    if (empty($course_id)) {
        header("Location: add_batch.php?error=course_id_empty&field=course_id&$batch_data");
        exit();
    }

    // Auto-generate batch_id
    $prefix = "BAT";
    $year = date("Y");
    $search_prefix = $prefix . "-" . $year . "-%";

    $id_sql = "SELECT batch_id FROM batches WHERE batch_id LIKE '$search_prefix' ORDER BY id DESC LIMIT 1";
    $id_result = $conn->query($id_sql);

    if ($id_result && $id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $last_id = $row['batch_id']; 
        $num = (int)substr($last_id, -3) + 1;
        $batch_id = $prefix . "-" . $year . "-" . sprintf("%03d", $num);
    } else {
        $batch_id = $prefix . "-" . $year . "-001";
    }

    $sql = "INSERT INTO batches (batch_id, batch_name, course_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $batch_id, $batch_name, $course_id, $start_date, $end_date);

    if ($stmt->execute()) {
        header("Location: add_batch.php?status=success");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: courses.php");
    exit();
}
