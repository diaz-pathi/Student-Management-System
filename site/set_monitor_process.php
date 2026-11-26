<?php
session_start();
require_once('../lib/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $hashed_password = md5($_POST['password']);
    
    // Fetch student details using st_id
    $sq = mysqli_query($conn, "SELECT * FROM students WHERE st_id = $student_id");
    if ($sq && mysqli_num_rows($sq) > 0) {
        $st = mysqli_fetch_assoc($sq);
        
        $st_custom_id = $st['st_custom_id'];
        $fullname = mysqli_real_escape_string($conn, $st['name']);
        $nic = mysqli_real_escape_string($conn, $st['nic'] ?? '');
        $address = mysqli_real_escape_string($conn, $st['address'] ?? '');
        $dob = mysqli_real_escape_string($conn, $st['dob'] ?? '');
        $gender = mysqli_real_escape_string($conn, $st['gender'] ?? '');
        $email = mysqli_real_escape_string($conn, $st['email'] ?? '');
        $phone = mysqli_real_escape_string($conn, $st['phone'] ?? '');
        $course_id = mysqli_real_escape_string($conn, $st['course_id'] ?? '');
        $status = mysqli_real_escape_string($conn, $st['status'] ?? 'Active');

        // Check if inactive
        if ($status === 'Inactive') {
            header("Location: students.php?error=inactive_student");
            exit();
        }
        
        // Prepare variables properly for SQL strict mode
        $dob_val = empty($dob) ? null : $dob;
        $image_val = !empty($st['image']) ? $st['image'] : null;
        
        $role = 'Monitor';
        $uq = "INSERT INTO users (custom_id, fullname, username, nic, address, dob, gender, role, password, email, phone, image, course_id, status)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $uq);
        
        mysqli_stmt_bind_param($stmt, "ssssssssssssss", 
            $st_custom_id, $fullname, $username, $nic, $address, $dob_val, $gender, 
            $role, $hashed_password, $email, $phone, $image_val, $course_id, $status
        );
        
        if (mysqli_stmt_execute($stmt)) {
            // Update students table role
            mysqli_query($conn, "UPDATE students SET role = 'Monitor' WHERE st_id = $student_id");
            
            header("Location: students.php?monitor=success");
            exit();
        } else {
            $err = urlencode(mysqli_stmt_error($stmt));
            header("Location: students.php?error=insertfailed&msg=$err");
            exit();
        }
    } else {
        header("Location: students.php?error=nostudent");
        exit();
    }
}
?>
