<?php
session_start();
require_once('../lib/db.php');

// Access Control
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instructor_id'])) {
    
    $instructor_id = intval($_POST['instructor_id']);
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $nic      = trim($_POST['nic']);
    $address  = trim($_POST['address']);
    $dob      = trim($_POST['dob']);
    $gender   = trim($_POST['gender'] ?? '');
    $course_id = trim($_POST['course_id'] ?? '');
    
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Server-Side Validations ---
    $errors = [];

    if (empty($fullname) || empty($username) || empty($nic) || empty($email) || empty($phone) || empty($course_id)) {
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
        header("Location: instructors.php?error=validation");
        exit();
    }

    // Sanitize Inputs
    $fullname = mysqli_real_escape_string($conn, $fullname);
    $username = mysqli_real_escape_string($conn, $username);
    $nic      = mysqli_real_escape_string($conn, $nic);
    $address  = mysqli_real_escape_string($conn, $address);
    $dob      = mysqli_real_escape_string($conn, $dob);
    $gender   = mysqli_real_escape_string($conn, $gender);
    $course_id = mysqli_real_escape_string($conn, $course_id);

    $email    = mysqli_real_escape_string($conn, $email);
    $phone    = mysqli_real_escape_string($conn, $phone);

    $query = "UPDATE users SET 
                fullname = '$fullname', 
                username = '$username', 
                nic = '$nic', 
                address = '$address', 
                dob = '$dob', 
                gender = '$gender', 
                course_id = '$course_id',
                email = '$email', 
                phone = '$phone'";

    if (!empty($password)) {
        $hashed_password = md5($password);
        $query .= ", password = '$hashed_password'";
    }

    // Handle Profile Picture
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $imgData = addslashes(file_get_contents($_FILES['profile_image']['tmp_name']));
        $query .= ", image = '$imgData'";
    }

    $query .= " WHERE id = $instructor_id AND role = 'Instructor'";

    if (mysqli_query($conn, $query)) {
        header("Location: instructors.php?updated=success");
        exit();
    } else {
        $_SESSION['error_msg'] = "Database Update Error: " . mysqli_error($conn);
        header("Location: instructors.php?error=db");
        exit();
    }
}
?>
