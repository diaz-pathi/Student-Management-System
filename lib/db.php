<?php
$servername ="localhost";
$username ="root";
$password ="";
$dbname ="nvti_sms_sys";

$conn = new mysqli($servername,$username,$password,$dbname);

if($conn->connect_error) {
    die("Database Connection Failed" . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// -------------------------------------------------------------------------
// 🌍 Device-Aware Timezone & Date Configuration
// Calibrates PHP and MySQL session to match the logging device's date & time
// -------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Detect client timezone: Session -> Cookie -> Default ('Asia/Colombo')
$client_tz = $_SESSION['client_timezone'] ?? $_COOKIE['client_timezone'] ?? 'Asia/Colombo';

// Validate IANA timezone identifier
if (!in_array($client_tz, DateTimeZone::listIdentifiers(), true)) {
    $client_tz = 'Asia/Colombo';
}

// 2. Set PHP default timezone
date_default_timezone_set($client_tz);

// 3. Compute UTC offset for MySQL session (e.g. '+05:30', '-04:00')
try {
    $dtz = new DateTimeZone($client_tz);
    $now_dt = new DateTime('now', $dtz);
    $offset_str = $now_dt->format('P');
} catch (Exception $e) {
    $offset_str = '+05:30';
}

// Fallback to offset minutes cookie if timezone couldn't be parsed
if (empty($offset_str) && isset($_COOKIE['client_tz_offset'])) {
    $min = (int)$_COOKIE['client_tz_offset'];
    $sign = ($min <= 0) ? '+' : '-';
    $abs = abs($min);
    $hours = floor($abs / 60);
    $mins = $abs % 60;
    $offset_str = sprintf('%s%02d:%02d', $sign, $hours, $mins);
}

// 4. Align MySQL session time_zone so CURDATE() and NOW() strictly match the device
if (!empty($offset_str)) {
    $conn->query("SET time_zone = '$offset_str'");
}
?>