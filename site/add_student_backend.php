<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name     = $_POST['name'] ?? '';
    $nic      = $_POST['nic'] ?? '';
    $dob      = $_POST['dob'] ?? '';
    $address  = $_POST['address'] ?? '';
    $gender   = $_POST['gender'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $batch_id = $_POST['batch_id'] ?? '';
    $email    = $_POST['email'] ?? '';
    $phone    = $_POST['phone'] ?? '';

    $user_data = "name=" . urlencode($name) .
                 "&nic=" . urlencode($nic) .
                 "&dob=" . urlencode($dob) .
                 "&address=" . urlencode($address) .
                 "&gender=" . urlencode($gender) .
                 "&course_id=" . urlencode($course_id) .
                 "&batch_id=" . urlencode($batch_id) .
                 "&email=" . urlencode($email) .
                 "&phone=" . urlencode($phone);

    if (empty($name) || empty($nic) || empty($address) || empty($gender) || empty($course_id) || empty($batch_id) || empty($email) || empty($phone)) {
        header("Location: add_student.php?error=empty_fields&$user_data");
        exit();
    }

    $old_nic_pattern = "/^[0-9]{9}[vVxX]$/";
    $new_nic_pattern = "/^[0-9]{12}$/";
    if (!(preg_match($old_nic_pattern, $nic) || preg_match($new_nic_pattern, $nic))) {
        header("Location: add_student.php?error=nic_invalid&field=nic&$user_data");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: add_student.php?error=email_invalid&field=email&$user_data");
        exit();
    }

    $phone_pattern = "/^0[0-9]{9}$/";
    if (!preg_match($phone_pattern, $phone)) {
        header("Location: add_student.php?error=phone_invalid&field=phone&$user_data");
        exit();
    }

    $check_nic = $conn->prepare("SELECT st_id FROM students WHERE nic = ?");
    $check_nic->bind_param("s", $nic);
    $check_nic->execute();
    if ($check_nic->get_result()->num_rows > 0) {
        header("Location: add_student.php?error=nic_exists&field=nic&$user_data");
        exit();
    }

    $check_email = $conn->prepare("SELECT st_id FROM students WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        header("Location: add_student.php?error=email_exists&field=email&$user_data");
        exit();
    }

    $check_phone = $conn->prepare("SELECT st_id FROM students WHERE phone = ?");
    $check_phone->bind_param("s", $phone);
    $check_phone->execute();
    if ($check_phone->get_result()->num_rows > 0) {
        header("Location: add_student.php?error=phone_exists&field=phone&$user_data");
        exit();
    }

    if (isset($_FILES['img']['tmp_name']) && !empty($_FILES['img']['tmp_name'])) {
        $imgData = file_get_contents($_FILES['img']['tmp_name']);
    } else {
        $imgData = null;
    }

    // Auto-generate Student ID
    $prefix = "STU";
    $year = date("Y");
    $search_prefix = $prefix . "-" . $year . "-%";

    $id_sql = "SELECT st_custom_id FROM students WHERE st_custom_id LIKE '$search_prefix' ORDER BY st_id DESC LIMIT 1";
    $id_result = $conn->query($id_sql);

    if ($id_result && $id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $last_id = $row['st_custom_id']; 
        $num = (int)substr($last_id, -3) + 1;
        $custom_id = $prefix . "-" . $year . "-" . sprintf("%03d", $num);
    } else {
        $custom_id = $prefix . "-" . $year . "-001";
    }

    // Insert into students table
    $role_val = 'Student';
    $sql = "INSERT INTO students (st_custom_id, name, nic, dob, address, gender, course_id, batch_id, email, phone, image, status, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";

    $stmt = $conn->prepare($sql);
    $null = NULL;
    $dob_val = empty($dob) ? null : $dob;
    
    $stmt->bind_param("ssssssssssbs", $custom_id, $name, $nic, $dob_val, $address, $gender, $course_id, $batch_id, $email, $phone, $null, $role_val);

    if ($imgData !== null) {
        $stmt->send_long_data(10, $imgData);
    }

    if ($stmt->execute()) {
        header("Location: add_student.php?status=success");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: students.php");
    exit();
}
