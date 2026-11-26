<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $student_id = intval($_POST['student_id']);
    $fullname = trim($_POST['fullname']);
    $nic      = trim($_POST['nic']);
    $dob      = trim($_POST['dob'] ?? '');
    $address  = trim($_POST['address']);
    $gender   = trim($_POST['gender'] ?? '');
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $course_id = trim($_POST['course_id'] ?? '');
    $batch_id = trim($_POST['batch_id'] ?? '');

    $errors = [];

    if (empty($fullname) || empty($nic) || empty($email) || empty($phone)) {
        $errors[] = "Please fill in all required fields.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!preg_match("/^0[0-9]{9}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits starting with 0.";
    }

    if (!preg_match("/^([0-9]{9}[vVxX]|[0-9]{12})$/", $nic)) {
        $errors[] = "Please enter a valid NIC number.";
    }

    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode("<br>", $errors);
        header("Location: students.php?error=validation");
        exit();
    }

    $fullname = mysqli_real_escape_string($conn, $fullname);
    $nic      = mysqli_real_escape_string($conn, $nic);
    $dob_val  = empty($dob) ? "NULL" : "'" . mysqli_real_escape_string($conn, $dob) . "'";
    $address  = mysqli_real_escape_string($conn, $address);
    $gender   = mysqli_real_escape_string($conn, $gender);
    $email    = mysqli_real_escape_string($conn, $email);
    $phone    = mysqli_real_escape_string($conn, $phone);
    $course_id = mysqli_real_escape_string($conn, $course_id);
    $batch_id = mysqli_real_escape_string($conn, $batch_id);

    $query = "UPDATE students SET 
                name = '$fullname', 
                nic = '$nic', 
                dob = $dob_val,
                address = '$address', 
                gender = '$gender', 
                email = '$email', 
                phone = '$phone',
                course_id = '$course_id',
                batch_id = '$batch_id'";

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $imgData = addslashes(file_get_contents($_FILES['profile_image']['tmp_name']));
        $query .= ", image = '$imgData'";
    }

    $query .= " WHERE st_id = $student_id";

    if (mysqli_query($conn, $query)) {
        header("Location: students.php?updated=success");
        exit();
    } else {
        $_SESSION['error_msg'] = "Database Update Error: " . mysqli_error($conn);
        header("Location: students.php?error=db");
        exit();
    }
}
?>
