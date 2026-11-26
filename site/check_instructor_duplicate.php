<?php
require_once('../lib/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if (!empty($field) && !empty($value)) {
        
        $allowed_fields = ['username', 'nic', 'email', 'phone'];
        if (in_array($field, $allowed_fields)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE $field = ? LIMIT 1");
            $stmt->bind_param("s", $value);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo "* This " . ucfirst($field) . " is already taken/registered!";
            } else {
                echo "OK";
            }
            $stmt->close();
        }
    }
}
?>
