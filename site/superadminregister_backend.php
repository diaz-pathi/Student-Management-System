<?php
require_once('../lib/db.php');

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

    // Data ටික URL Parameter string එකකට හදාගැනීම (. මගින් සම්බන්ධ කර ඇත)
    $user_data = "fullname=" . urlencode($fullname) .
                 "&username=" . urlencode($username) .
                 "&nic=" . urlencode($nic) .
                 "&address=" . urlencode($address) .
                 "&dob=" . urlencode($dob) .
                 "&gender=" . urlencode($gender) .
                 "&email=" . urlencode($email) .
                 "&phone=" . urlencode($phone);

    // Empty Validations (හැම එකටම &$user_data එකතු කර ඇත)
    if (empty($fullname)) {
        header("Location: ../index.php?error=fullname_empty&field=fullname&$user_data");
        exit();
    }

    if (empty($username)) {
        header("Location: ../index.php?error=username_empty&field=username&$user_data");
        exit();
    }

    if (empty($nic)) {
        header("Location: ../index.php?error=nic_empty&field=nic&$user_data");
        exit();
    }

    if (empty($address)) {
        header("Location: ../index.php?error=address_empty&field=address&$user_data");
        exit();
    }

    if (empty($dob)) {
        header("Location: ../index.php?error=dob_empty&field=dob&$user_data");
        exit();
    }

    if (empty($gender)) {
        header("Location: ../index.php?error=gender_empty&field=gender&$user_data");
        exit();
    }

    if (empty($email)) {
        header("Location: ../index.php?error=email_empty&field=email&$user_data");
        exit();
    }

    if (empty($phone)) {
        header("Location: ../index.php?error=phone_empty&field=phone&$user_data");
        exit();
    }

    if (empty($password)) {
        header("Location: ../index.php?error=password_empty&field=password&$user_data");
        exit();
    }

    if (empty($pwconfirm)) {
        header("Location: ../index.php?error=pwconfirm_empty&field=pwconfirm&$user_data");
        exit();
    }

    // NIC Checking
    $old_nic_pattern = "/^[0-9]{9}[vVxX]$/";
    $new_nic_pattern = "/^[0-9]{12}$/";

    if (!(preg_match($old_nic_pattern, $nic) || preg_match($new_nic_pattern, $nic))) {
        header("Location: ../index.php?error=nic_invalid&field=nic&$user_data");
        exit();
    }

    // Email Checking
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../index.php?error=email_invalid&field=email&$user_data");
        exit();
    }

    // Phone Number Checking
    $phone_pattern = "/^0[0-9]{9}$/";
    if (!preg_match($phone_pattern, $phone)) {
        header("Location: ../index.php?error=phone_invalid&field=phone&$user_data");
        exit(); // මෙතන exit() එකතු කරන ලදී
    }

    // Password Strength Check
    $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?\":{}|<>]).{8,}$/";
    if (!preg_match($password_pattern, $password)) {
        header("Location: ../index.php?error=weak_password&field=password&$user_data");
        exit();
    }

    // Password & Confirm Password Matching
    if ($password !== $pwconfirm) {
        header("Location: ../index.php?error=password_mismatch&field=pwconfirm&$user_data");
        exit();
    } else {
        $hashed_password = md5($password);
    }

    // Profile Image
    if (isset($_FILES['img']['tmp_name']) && !empty($_FILES['img']['tmp_name'])) {
        $imgData = file_get_contents($_FILES['img']['tmp_name']);
    } else {
        $imgData = null;
    }

    // ==========================================
    // 🆔 CUSTOM ID GENERATION LOGIC
    // ==========================================
    $prefix = "SA";
    $year = date("Y");
    $search_prefix = $prefix . "-" . $year . "-%";

    $id_sql = "SELECT custom_id FROM users WHERE custom_id LIKE '$search_prefix' ORDER BY id DESC LIMIT 1";
    $id_result = $conn->query($id_sql);

    if ($id_result && $id_result->num_rows > 0) {
        $row = $id_result->fetch_assoc();
        $last_id = $row['custom_id']; // e.g. "SA-2026-002"
        $num = (int)substr($last_id, -3) + 1;
        $custom_id = $prefix . "-" . $year . "-" . sprintf("%03d", $num);
    } else {
        $custom_id = $prefix . "-" . $year . "-001";
    }
    // ==========================================

    // Database Insert
    $sql = "INSERT INTO users (custom_id, fullname, username, nic, address, dob, gender, email, phone, password, image, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Super Admin')";

    $stmt = $conn->prepare($sql);
    $null = NULL;
    
    // Bind String ටිකට "s" එකක් වැඩි කළා ($custom_id සඳහා) -> "ssssssssssb"
    $stmt->bind_param("ssssssssssb", $custom_id, $fullname, $username, $nic, $address, $dob, $gender, $email, $phone, $hashed_password, $null);

    if ($imgData !== null) {
        $stmt->send_long_data(10, $imgData); // Index එක 10 වෙනවා custom_id නිසා
    }

    if ($stmt->execute()) {
        header("Location: ../index.php?status=success");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
}