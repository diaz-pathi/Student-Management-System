<?php
session_start();
require_once('../lib/db.php');

// Super Admin කෙනෙක්ද කියලා check කිරීම
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $fullname = $_POST['fullname'] ?? '';
    $username = $_POST['username'] ?? '';
    $nic      = $_POST['nic'] ?? '';
    $address  = $_POST['address'] ?? '';
    $dob      = $_POST['dob'] ?? '';
    $gender   = $_POST['gender'] ?? '';
    $email    = $_POST['email'] ?? '';
    $phone    = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $pwconfirm= $_POST['pwconfirm'] ?? '';

    // Data ටික URL Parameter string එකකට හදාගැනීම
    $user_data = "fullname=" . urlencode($fullname) .
                 "&username=" . urlencode($username) .
                 "&nic=" . urlencode($nic) .
                 "&address=" . urlencode($address) .
                 "&dob=" . urlencode($dob) .
                 "&gender=" . urlencode($gender) .
                 "&email=" . urlencode($email) .
                 "&phone=" . urlencode($phone);

    // Empty Validations
    if (empty($fullname)) {
        header("Location: add_admin.php?error=fullname_empty&field=fullname&$user_data");
        exit();
    }
    if (empty($username)) {
        header("Location: add_admin.php?error=username_empty&field=username&$user_data");
        exit();
    }
    if (empty($nic)) {
        header("Location: add_admin.php?error=nic_empty&field=nic&$user_data");
        exit();
    }
    if (empty($address)) {
        header("Location: add_admin.php?error=address_empty&field=address&$user_data");
        exit();
    }
    if (empty($dob)) {
        header("Location: add_admin.php?error=dob_empty&field=dob&$user_data");
        exit();
    }
    if (empty($gender)) {
        header("Location: add_admin.php?error=gender_empty&field=gender&$user_data");
        exit();
    }
    if (empty($email)) {
        header("Location: add_admin.php?error=email_empty&field=email&$user_data");
        exit();
    }
    if (empty($phone)) {
        header("Location: add_admin.php?error=phone_empty&field=phone&$user_data");
        exit();
    }
    if (empty($password)) {
        header("Location: add_admin.php?error=password_empty&field=password&$user_data");
        exit();
    }
    if (empty($pwconfirm)) {
        header("Location: add_admin.php?error=pwconfirm_empty&field=pwconfirm&$user_data");
        exit();
    }

    // NIC Pattern Checking
    $old_nic_pattern = "/^[0-9]{9}[vVxX]$/";
    $new_nic_pattern = "/^[0-9]{12}$/";
    if (!(preg_match($old_nic_pattern, $nic) || preg_match($new_nic_pattern, $nic))) {
        header("Location: add_admin.php?error=nic_invalid&field=nic&$user_data");
        exit();
    }

    // Email Pattern Checking
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: add_admin.php?error=email_invalid&field=email&$user_data");
        exit();
    }

    // Phone Number Pattern Checking
    $phone_pattern = "/^0[0-9]{9}$/";
    if (!preg_match($phone_pattern, $phone)) {
        header("Location: add_admin.php?error=phone_invalid&field=phone&$user_data");
        exit();
    }

    // ==========================================
    // 🔍 DATABASE DUPLICATE CHECKS (Username, NIC, Email, Phone)
    // ==========================================
    
    // Check Username
    $check_un = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_un->bind_param("s", $username);
    $check_un->execute();
    if ($check_un->get_result()->num_rows > 0) {
        header("Location: add_admin.php?error=username_exists&field=username&$user_data");
        exit();
    }

    // Check NIC
    $check_nic = $conn->prepare("SELECT id FROM users WHERE nic = ?");
    $check_nic->bind_param("s", $nic);
    $check_nic->execute();
    if ($check_nic->get_result()->num_rows > 0) {
        header("Location: add_admin.php?error=nic_exists&field=nic&$user_data");
        exit();
    }

    // Check Email
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        header("Location: add_admin.php?error=email_exists&field=email&$user_data");
        exit();
    }

    // Check Phone
    $check_phone = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $check_phone->bind_param("s", $phone);
    $check_phone->execute();
    if ($check_phone->get_result()->num_rows > 0) {
        header("Location: add_admin.php?error=phone_exists&field=phone&$user_data");
        exit();
    }
    // ==========================================

    // Password Strength Check
    $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?\":{}|<>]).{8,}$/";
    if (!preg_match($password_pattern, $password)) {
        header("Location: add_admin.php?error=weak_password&field=password&$user_data");
        exit();
    }

    // Password & Confirm Password Matching
    if ($password !== $pwconfirm) {
        header("Location: add_admin.php?error=password_mismatch&field=pwconfirm&$user_data");
        exit();
    } else {
        $hashed_password = md5($password);
    }

    // Profile Image Handling
    if (isset($_FILES['img']['tmp_name']) && !empty($_FILES['img']['tmp_name'])) {
        $imgData = file_get_contents($_FILES['img']['tmp_name']);
    } else {
        $imgData = null;
    }

    // ==========================================
    // 🆔 CUSTOM ID GENERATION LOGIC (For Admin - ADM)
    // ==========================================
    $prefix = "ADM";
    $year = date("Y");
    $search_prefix = $prefix . "-" . $year . "-%";

    $id_sql = "SELECT custom_id FROM users WHERE custom_id LIKE '$search_prefix' ORDER BY id DESC LIMIT 1";
    $id_result = $conn->query($id_sql);

    if ($id_result && $id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $last_id = $row['custom_id']; 
        $num = (int)substr($last_id, -3) + 1;
        $custom_id = $prefix . "-" . $year . "-" . sprintf("%03d", $num);
    } else {
        $custom_id = $prefix . "-" . $year . "-001";
    }
    // ==========================================

    // Database Insert (Role set to 'Admin')
    $sql = "INSERT INTO users (custom_id, fullname, username, nic, address, dob, gender, email, phone, password, image, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Admin', 'Active')";

    $stmt = $conn->prepare($sql);
    $null = NULL;
    
    $stmt->bind_param("ssssssssssb", $custom_id, $fullname, $username, $nic, $address, $dob, $gender, $email, $phone, $hashed_password, $null);

    if ($imgData !== null) {
        $stmt->send_long_data(10, $imgData);
    }

    if ($stmt->execute()) {
        header("Location: add_admin.php?status=success");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: admins.php");
    exit();
}