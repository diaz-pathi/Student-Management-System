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
    $batch_name = trim($_POST['batch_name']);
    $course_id = trim($_POST['course_id']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;

    if (empty($batch_name) || empty($course_id)) {
        header("Location: courses.php?error=validation");
        exit();
    }

    $batch_name = mysqli_real_escape_string($conn, $batch_name);
    $course_id = mysqli_real_escape_string($conn, $course_id);
    
    if ($start_date !== NULL) $start_date = "'" . mysqli_real_escape_string($conn, $start_date) . "'";
    else $start_date = "NULL";
    
    if ($end_date !== NULL) $end_date = "'" . mysqli_real_escape_string($conn, $end_date) . "'";
    else $end_date = "NULL";

    $query = "UPDATE batches SET 
                batch_name = '$batch_name', 
                course_id = '$course_id', 
                start_date = $start_date, 
                end_date = $end_date 
              WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        header("Location: courses.php?updated=success");
        exit();
    } else {
        header("Location: courses.php?error=db");
        exit();
    }
}
?>
