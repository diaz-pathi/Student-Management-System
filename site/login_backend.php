<?php 
session_start();
require_once('../lib/db.php');

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $user_input = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Empty validation
    if (empty($user_input) || empty($password)){
        header("Location: ../login.php?error=empty_fields"); 
        exit();
    } else {

        $hashedPassword = md5($password);

        // User සොයා ගැනීම
        $sql = "SELECT * FROM users WHERE (username = '$user_input' OR email = '$user_input') AND password = '$hashedPassword'";
        $result = $conn->query($sql);

        if($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ⚠️ Status එක Check කිරීම
            if (isset($user['status']) && strtolower($user['status']) === 'inactive') {
                header("Location: ../login.php?error=inactive_account&username=" . urlencode($user_input));
                exit();
            }

            // Active User කෙනෙක් නම් Sessions set කිරීම
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['custom_id'] = $user['custom_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_pic'] = $user['image'];

            // 🌍 Store Logging Device Timezone and Date in Session & Cookie
            $client_tz = trim($_POST['client_timezone'] ?? '');
            if (!empty($client_tz) && in_array($client_tz, DateTimeZone::listIdentifiers(), true)) {
                $_SESSION['client_timezone'] = $client_tz;
                setcookie('client_timezone', $client_tz, time() + 31536000, '/', '', false, false);
                date_default_timezone_set($client_tz);

                try {
                    $dtz = new DateTimeZone($client_tz);
                    $now_dt = new DateTime('now', $dtz);
                    $offset_str = $now_dt->format('P');
                    if (!empty($offset_str)) {
                        $conn->query("SET time_zone = '$offset_str'");
                    }
                } catch (Exception $e) {}
            }

            if (isset($_POST['client_timezone_offset'])) {
                $offset_val = (int)$_POST['client_timezone_offset'];
                $_SESSION['client_tz_offset'] = $offset_val;
                setcookie('client_tz_offset', $offset_val, time() + 31536000, '/', '', false, false);
            }

            if (!empty($_POST['client_device_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['client_device_date'])) {
                $_SESSION['client_device_date'] = $_POST['client_device_date'];
                setcookie('client_device_date', $_POST['client_device_date'], time() + 86400, '/', '', false, false);
            }

            // 📝 system_logs table එකට Login Time එක සහ Username එකතු කිරීම
            $custom_id = mysqli_real_escape_string($conn, $user['custom_id'] ?? '');
            $username_val = mysqli_real_escape_string($conn, $user['username'] ?? '');
            $role = mysqli_real_escape_string($conn, $user['role'] ?? '');

            // Ensure table and username column exist
            @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_custom_id VARCHAR(30), username VARCHAR(100), role VARCHAR(20), login_time DATETIME, logout_time DATETIME, status VARCHAR(20))");
            $chk_col = @mysqli_query($conn, "SHOW COLUMNS FROM system_logs LIKE 'username'");
            if ($chk_col && mysqli_num_rows($chk_col) === 0) {
                @mysqli_query($conn, "ALTER TABLE system_logs ADD COLUMN username VARCHAR(100) AFTER user_custom_id");
            }

            // Close any previous unclosed sessions for this user
            @mysqli_query($conn, "UPDATE system_logs SET status = 'Closed' WHERE user_custom_id = '$custom_id' AND (logout_time IS NULL OR status = 'Active')");

            $log_sql = "INSERT INTO system_logs (user_custom_id, username, role, login_time, status) VALUES ('$custom_id', '$username_val', '$role', NOW(), 'Active')";
            
            if ($conn->query($log_sql) === TRUE) {
                $_SESSION['log_id'] = $conn->insert_id; // Logout එකේදී update කිරීමට log_id එක තබා ගැනීම
            }

            if ($_SESSION['role'] === 'Monitor') {
                header("Location: ../site/attendance.php"); 
            } else {
                header("Location: ../site/dashboard.php"); 
            }
            exit();
        } else {
            header("Location: ../login.php?error=invalid_credentials&username=" . urlencode($user_input));
            exit();
        }
    }
} else {
    header("Location: ../login.php");
    exit();
}
?>