<?php
// lib/whatsapp_helper.php
// WhatsApp Gateway & Automated Messaging Helper for NVTI SMS

require_once __DIR__ . '/db.php';

// Auto-create whatsapp_settings table if it doesn't exist
function init_whatsapp_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gateway_provider VARCHAR(50) NOT NULL DEFAULT 'ultramsg',
        api_url VARCHAR(255) NULL,
        instance_id VARCHAR(100) NULL,
        api_token TEXT NULL,
        sender_phone VARCHAR(30) NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        present_template TEXT NULL,
        absent_template TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $sql);

    // Insert default settings row if empty
    $chk = mysqli_query($conn, "SELECT id FROM whatsapp_settings LIMIT 1");
    if ($chk && mysqli_num_rows($chk) === 0) {
        $default_present = "Hello {student_name}, your attendance for {course_name} ({batch_name}) has been marked as PRESENT today ({date} at {time}). Thank you! - NVTI Baddegama";
        $default_absent = "Dear Parent/Student, {student_name} ({student_id}) has been marked ABSENT for {course_name} ({batch_name}) today ({date}). Please contact the institute if you have any questions. - NVTI Baddegama";
        
        $ins = "INSERT INTO whatsapp_settings 
            (gateway_provider, api_url, instance_id, api_token, sender_phone, is_enabled, present_template, absent_template)
            VALUES 
            ('ultramsg', 'https://api.ultramsg.com', '', '', '', 1, '" . mysqli_real_escape_string($conn, $default_present) . "', '" . mysqli_real_escape_string($conn, $default_absent) . "')";
        mysqli_query($conn, $ins);
    }
}

// Fetch WhatsApp settings from database
function get_whatsapp_settings($conn) {
    init_whatsapp_table($conn);
    $res = mysqli_query($conn, "SELECT * FROM whatsapp_settings ORDER BY id ASC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    return [
        'gateway_provider' => 'ultramsg',
        'api_url' => 'https://api.ultramsg.com',
        'instance_id' => '',
        'api_token' => '',
        'sender_phone' => '',
        'is_enabled' => 1,
        'present_template' => "Hello {student_name}, your attendance for {course_name} ({batch_name}) has been marked as PRESENT today ({date} at {time}). Thank you! - NVTI Baddegama",
        'absent_template' => "Dear Parent/Student, {student_name} ({student_id}) has been marked ABSENT for {course_name} ({batch_name}) today ({date}). Please contact the institute if you have any questions. - NVTI Baddegama"
    ];
}

// Normalize phone number to standard international format (defaults to Sri Lanka +94 if leading 0)
function normalize_phone_number($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (empty($clean)) {
        return '';
    }
    // Sri Lanka phone normalization (e.g. 071xxxxxxx -> 9471xxxxxxx)
    if (strpos($clean, '0') === 0 && strlen($clean) === 10) {
        $clean = '94' . substr($clean, 1);
    }
    return $clean;
}

// Core dispatcher to send WhatsApp message via chosen gateway
function send_whatsapp_message($conn, $phone, $message) {
    $settings = get_whatsapp_settings($conn);
    if (empty($settings['is_enabled'])) {
        return ['status' => false, 'message' => 'WhatsApp notifications are currently disabled in Settings.'];
    }

    $to_number = normalize_phone_number($phone);
    if (empty($to_number)) {
        return ['status' => false, 'message' => 'Invalid or empty phone number.'];
    }

    $provider = strtolower(trim($settings['gateway_provider'] ?? 'ultramsg'));
    $api_url = trim($settings['api_url'] ?? '');
    $instance_id = trim($settings['instance_id'] ?? '');
    $api_token = trim($settings['api_token'] ?? '');

    // Provider 1: UltraMsg (Official HTTP API)
    if ($provider === 'ultramsg') {
        if (empty($instance_id) || empty($api_token)) {
            return ['status' => false, 'message' => 'UltraMsg Instance ID or Token is missing.'];
        }
        $endpoint = "https://api.ultramsg.com/" . urlencode($instance_id) . "/messages/chat";
        $data = [
            'token' => $api_token,
            'to' => $to_number,
            'body' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['status' => false, 'message' => 'CURL Error: ' . $err];
        }
        $res_json = json_decode($response, true);
        if (isset($res_json['sent']) && ($res_json['sent'] === 'true' || $res_json['sent'] === true)) {
            return ['status' => true, 'response' => $res_json];
        }
        return ['status' => true, 'response' => $response];
    }

    // Provider 2: Fonnte (Popular Indonesian / SE Asia API)
    elseif ($provider === 'fonnte') {
        if (empty($api_token)) {
            return ['status' => false, 'message' => 'Fonnte Token is missing.'];
        }
        $endpoint = "https://api.fonnte.com/send";
        $data = [
            'target' => $to_number,
            'message' => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: " . $api_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['status' => false, 'message' => 'CURL Error: ' . $err];
        }
        return ['status' => true, 'response' => $response];
    }

    // Provider 3: Wablas
    elseif ($provider === 'wablas') {
        if (empty($api_token) || empty($api_url)) {
            return ['status' => false, 'message' => 'Wablas API URL or Token is missing.'];
        }
        $endpoint = rtrim($api_url, '/') . '/api/send-message';
        $data = [
            'phone' => $to_number,
            'message' => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: " . $api_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['status' => false, 'message' => 'CURL Error: ' . $err];
        }
        return ['status' => true, 'response' => $response];
    }

    // Provider 4: Generic Webhook / Custom HTTP API
    elseif ($provider === 'custom_webhook') {
        if (empty($api_url)) {
            return ['status' => false, 'message' => 'Custom Webhook API URL is missing.'];
        }
        $payload = json_encode([
            'phone' => $to_number,
            'message' => $message,
            'instance_id' => $instance_id,
            'token' => $api_token
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['status' => false, 'message' => 'CURL Error: ' . $err];
        }
        return ['status' => true, 'response' => $response];
    }

    return ['status' => false, 'message' => 'Unsupported gateway provider selected.'];
}

// Send Instant WhatsApp Notification for Present Student
function send_present_notification($conn, $student_custom_id, $course_name = '', $batch_name = '', $target_date = null) {
    $st_esc = mysqli_real_escape_string($conn, $student_custom_id);
    $q = "SELECT s.name, s.phone, s.course_id, s.batch_id, c.course_name, b.batch_name 
          FROM students s 
          LEFT JOIN courses c ON s.course_id = c.course_id 
          LEFT JOIN batches b ON s.batch_id = b.batch_id 
          WHERE s.st_custom_id = '$st_esc' LIMIT 1";
    $res = mysqli_query($conn, $q);
    if (!$res || mysqli_num_rows($res) === 0) {
        return ['status' => false, 'message' => 'Student not found.'];
    }

    $st = mysqli_fetch_assoc($res);
    $phone = $st['phone'] ?? '';
    if (empty($phone)) {
        return ['status' => false, 'message' => 'Student does not have a registered phone number.'];
    }

    $c_name = !empty($course_name) ? $course_name : ($st['course_name'] ?? $st['course_id']);
    $b_name = !empty($batch_name) ? $batch_name : ($st['batch_name'] ?? $st['batch_id']);
    $msg_date = (!empty($target_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) ? $target_date : date('Y-m-d');
    
    $settings = get_whatsapp_settings($conn);
    $template = !empty($settings['present_template']) ? $settings['present_template'] : "Hello {student_name}, your attendance for {course_name} ({batch_name}) has been marked as PRESENT today ({date} at {time}). Thank you!";

    $message = str_replace(
        ['{student_name}', '{student_id}', '{course_name}', '{batch_name}', '{date}', '{time}'],
        [$st['name'], $student_custom_id, $c_name, $b_name, $msg_date, date('h:i A')],
        $template
    );

    return send_whatsapp_message($conn, $phone, $message);
}

// Send WhatsApp Notifications to all Absent Students in a Batch (with 6-second interval)
function send_absent_notifications_for_batch($conn, $course_id, $batch_id, $course_name = '', $batch_name = '', $target_date = null) {
    @set_time_limit(300); // Allow sufficient time for multi-recipient rate delay
    $c_esc = mysqli_real_escape_string($conn, $course_id);
    $b_esc = mysqli_real_escape_string($conn, $batch_id);
    $check_date = (!empty($target_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) ? $target_date : date('Y-m-d');
    $check_date_esc = mysqli_real_escape_string($conn, $check_date);

    // Fetch batch & course details if not provided
    if (empty($course_name)) {
        $cq = mysqli_query($conn, "SELECT course_name FROM courses WHERE course_id = '$c_esc' LIMIT 1");
        if ($cq && mysqli_num_rows($cq) > 0) {
            $course_name = mysqli_fetch_assoc($cq)['course_name'] ?? $course_id;
        }
    }
    if (empty($batch_name)) {
        $bq = mysqli_query($conn, "SELECT batch_name FROM batches WHERE batch_id = '$b_esc' LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $batch_name = mysqli_fetch_assoc($bq)['batch_name'] ?? $batch_id;
        }
    }

    // Query students in this batch who are marked absent or not marked present for target date
    $q = "SELECT s.st_custom_id, s.name, s.phone 
          FROM students s 
          LEFT JOIN student_attendance sa ON s.st_custom_id = sa.student_number AND DATE(sa.date) = '$check_date_esc'
          WHERE s.course_id = '$c_esc' AND s.batch_id = '$b_esc' 
            AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL)
            AND (sa.absent_present = 0 OR sa.absent_present IS NULL)";
    
    $res = mysqli_query($conn, $q);
    $sent_count = 0;
    $failed_count = 0;

    $settings = get_whatsapp_settings($conn);
    $template = !empty($settings['absent_template']) ? $settings['absent_template'] : "Dear Parent/Student, {student_name} ({student_id}) has been marked ABSENT for {course_name} ({batch_name}) today ({date}). Please contact the institute if you have any questions.";

    if ($res && mysqli_num_rows($res) > 0) {
        $first_msg = true;
        while ($row = mysqli_fetch_assoc($res)) {
            $phone = $row['phone'] ?? '';
            if (!empty($phone)) {
                // 6-second time gap between consecutive message deliveries to avoid rate limiting
                if (!$first_msg) {
                    sleep(6);
                }
                $first_msg = false;

                $message = str_replace(
                    ['{student_name}', '{student_id}', '{course_name}', '{batch_name}', '{date}', '{time}'],
                    [$row['name'], $row['st_custom_id'], $course_name, $batch_name, $check_date, date('h:i A')],
                    $template
                );

                $send_res = send_whatsapp_message($conn, $phone, $message);
                if ($send_res['status']) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }
    }

    return [
        'total_absent' => mysqli_num_rows($res),
        'sent_count' => $sent_count,
        'failed_count' => $failed_count
    ];
}
