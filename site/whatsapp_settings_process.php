<?php
// site/whatsapp_settings_process.php
// Handles saving WhatsApp settings and sending live test messages

session_start();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/whatsapp_helper.php';

$role = $_SESSION['role'] ?? '';
$is_super_admin = ($role === 'Super Admin' || $role === 'SuperAdmin');
$is_admin = ($role === 'Admin' || $is_super_admin);

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    // 1. Save Settings Action
    if ($action === 'save_settings') {
        $gateway_provider = mysqli_real_escape_string($conn, trim($_POST['gateway_provider'] ?? 'ultramsg'));
        $api_url = mysqli_real_escape_string($conn, trim($_POST['api_url'] ?? ''));
        $instance_id = mysqli_real_escape_string($conn, trim($_POST['instance_id'] ?? ''));
        $api_token = mysqli_real_escape_string($conn, trim($_POST['api_token'] ?? ''));
        $sender_phone = mysqli_real_escape_string($conn, trim($_POST['sender_phone'] ?? ''));
        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $present_template = mysqli_real_escape_string($conn, trim($_POST['present_template'] ?? ''));
        $absent_template = mysqli_real_escape_string($conn, trim($_POST['absent_template'] ?? ''));

        init_whatsapp_table($conn);
        $check = mysqli_query($conn, "SELECT id FROM whatsapp_settings LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            $id = $row['id'];
            $q = "UPDATE whatsapp_settings SET 
                    gateway_provider = '$gateway_provider',
                    api_url = '$api_url',
                    instance_id = '$instance_id',
                    api_token = '$api_token',
                    sender_phone = '$sender_phone',
                    is_enabled = $is_enabled,
                    present_template = '$present_template',
                    absent_template = '$absent_template'
                  WHERE id = $id";
            mysqli_query($conn, $q);
        } else {
            $q = "INSERT INTO whatsapp_settings 
                    (gateway_provider, api_url, instance_id, api_token, sender_phone, is_enabled, present_template, absent_template)
                  VALUES 
                    ('$gateway_provider', '$api_url', '$instance_id', '$api_token', '$sender_phone', $is_enabled, '$present_template', '$absent_template')";
            mysqli_query($conn, $q);
        }

        header("Location: settings.php?tab=whatsapp&msg=wa_saved");
        exit();
    }

    // 2. Test Message Action
    elseif ($action === 'test_message') {
        $test_phone = trim($_POST['test_phone'] ?? '');
        $test_msg = trim($_POST['test_message'] ?? 'This is a test notification from NVTI Baddegama Student Management System. WhatsApp Gateway is connected successfully!');

        if (empty($test_phone)) {
            header("Location: settings.php?tab=whatsapp&err=empty_phone");
            exit();
        }

        $res = send_whatsapp_message($conn, $test_phone, $test_msg);
        if ($res['status']) {
            header("Location: settings.php?tab=whatsapp&msg=test_sent");
        } else {
            $err_msg = urlencode($res['message'] ?? 'Failed to send test message');
            header("Location: settings.php?tab=whatsapp&err=test_failed&errmsg=$err_msg");
        }
        exit();
    }
}

header("Location: settings.php?tab=whatsapp");
exit();
