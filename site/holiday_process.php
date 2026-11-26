<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/db.php';

$role = $_SESSION['role'] ?? '';
$is_super_admin = ($role === 'Super Admin' || $role === 'SuperAdmin');
$is_admin = ($role === 'Admin' || $is_super_admin);

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Auto-create holidays table if it doesn't exist
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(date)
)");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. ADD BATCH HOLIDAYS
if ($action === 'add_holidays' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = $_POST['holiday_name'] ?? [];
    $dates = $_POST['holiday_date'] ?? [];

    $added_count = 0;
    if (is_array($names) && is_array($dates)) {
        for ($i = 0; $i < count($names); $i++) {
            $name = trim($names[$i] ?? '');
            $date = trim($dates[$i] ?? '');

            if (!empty($name) && !empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $name_esc = mysqli_real_escape_string($conn, $name);
                $date_esc = mysqli_real_escape_string($conn, $date);

                // Check if date already exists
                $chk = mysqli_query($conn, "SELECT id FROM holidays WHERE date = '$date_esc' LIMIT 1");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    // Update existing holiday name on that date
                    mysqli_query($conn, "UPDATE holidays SET holiday_name = '$name_esc' WHERE date = '$date_esc'");
                } else {
                    // Insert new holiday
                    mysqli_query($conn, "INSERT INTO holidays (holiday_name, date) VALUES ('$name_esc', '$date_esc')");
                }
                $added_count++;
            }
        }
    }

    if ($added_count > 0) {
        header("Location: settings.php?tab=holidays&msg=added&count=$added_count");
    } else {
        header("Location: settings.php?tab=holidays&err=empty_data");
    }
    exit();
}

// 2. UPDATE SINGLE HOLIDAY
if ($action === 'update_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['holiday_id'] ?? 0);
    $name = trim($_POST['holiday_name'] ?? '');
    $date = trim($_POST['holiday_date'] ?? '');

    if ($id > 0 && !empty($name) && !empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $name_esc = mysqli_real_escape_string($conn, $name);
        $date_esc = mysqli_real_escape_string($conn, $date);

        $q = "UPDATE holidays SET holiday_name = '$name_esc', date = '$date_esc' WHERE id = '$id'";
        if (mysqli_query($conn, $q)) {
            header("Location: settings.php?tab=holidays&msg=updated");
            exit();
        }
    }
    header("Location: settings.php?tab=holidays&err=update_failed");
    exit();
}

// 3. DELETE HOLIDAY
if ($action === 'delete_holiday') {
    $id = (int)($_POST['holiday_id'] ?? $_GET['id'] ?? 0);

    if ($id > 0) {
        mysqli_query($conn, "DELETE FROM holidays WHERE id = '$id'");
        header("Location: settings.php?tab=holidays&msg=deleted");
        exit();
    }
    header("Location: settings.php?tab=holidays&err=delete_failed");
    exit();
}

header("Location: settings.php?tab=holidays");
exit();
