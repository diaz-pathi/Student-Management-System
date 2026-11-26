<?php
session_start();
require_once('../lib/db.php');

// Access Control
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    
    $admin_id = intval($_POST['admin_id']);
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $nic      = trim($_POST['nic']);
    $address  = trim($_POST['address']);
    $dob      = trim($_POST['dob']);
    $gender   = trim($_POST['gender'] ?? '');
    
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Server-Side Validations ---
    $errors = [];

    if (empty($fullname) || empty($username) || empty($nic) || empty($email) || empty($phone)) {
        $errors[] = "Please fill in all required fields.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }

    if (!preg_match("/^([0-9]{9}[vVxX]|[0-9]{12})$/", $nic)) {
        $errors[] = "Please enter a valid NIC number.";
    }

    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode("<br>", $errors);
        header("Location: admins.php?error=validation");
        exit();
    }

    // Sanitize Inputs
    $fullname = mysqli_real_escape_string($conn, $fullname);
    $username = mysqli_real_escape_string($conn, $username);
    $nic      = mysqli_real_escape_string($conn, $nic);
    $address  = mysqli_real_escape_string($conn, $address);
    $dob      = mysqli_real_escape_string($conn, $dob);
    $gender   = mysqli_real_escape_string($conn, $gender);

    $email    = mysqli_real_escape_string($conn, $email);
    $phone    = mysqli_real_escape_string($conn, $phone);

    // Dynamic SQL Query (Role is intentionally excluded)
    $query = "UPDATE users SET 
                fullname = '$fullname', 
                username = '$username', 
                nic = '$nic', 
                address = '$address', 
                dob = '$dob', 
                gender = '$gender', 
                 
                email = '$email', 
                phone = '$phone'";

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query .= ", password = '$hashed_password'";
    }

    // Handle Profile Picture
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $imgData = addslashes(file_get_contents($_FILES['profile_image']['tmp_name']));
        $query .= ", image = '$imgData'";
    }

    $query .= " WHERE id = $admin_id AND role NOT IN ('Super Admin', 'SuperAdmin')";

    if (mysqli_query($conn, $query)) {
        header("Location: admins.php?updated=success");
        exit();
    } else {
        $_SESSION['error_msg'] = "Database Update Error: " . mysqli_error($conn);
        header("Location: admins.php?error=db");
        exit();
    }
}
?>