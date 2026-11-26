<?php
session_start();
require_once('../lib/db.php');

// system_logs table එකේ logout_time සහ status එක update කිරීම
if (isset($_SESSION['log_id'])) {
    $log_id = $_SESSION['log_id'];
    $update_log = "UPDATE system_logs SET logout_time = NOW(), status = 'Logged Out' WHERE id = '$log_id'";
    $conn->query($update_log);
}

// Session විනාශ කිරීම
session_unset();
session_destroy();

// Login page එකට redirect කිරීම
header("Location: ../login.php?logout=success");
exit();
?>