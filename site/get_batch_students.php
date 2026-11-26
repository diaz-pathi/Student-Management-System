<?php
require_once('../lib/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batch_id'])) {
    $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);

    $query = "SELECT st_custom_id, name, status, image FROM students WHERE batch_id = '$batch_id' ORDER BY name ASC";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $st = $row['status'] ?? 'Active';
            if ($st === 'Active') {
                $status_class = 'status-active';
                $display_status = 'Active';
            } elseif ($st === 'Inactive') {
                $status_class = 'status-inactive';
                $display_status = 'Inactive';
            } else {
                $status_class = 'status-passed-out';
                $display_status = 'Passed Out';
            }
            
            // Handle Profile Image
            $image_src = '../assets/default-avatar.png'; // Fallback
            if (!empty($row['image'])) {
                $base64_img = base64_encode($row['image']);
                // Determine mime type if possible, or just default to jpeg/png for simple rendering
                $image_src = 'data:image/jpeg;base64,' . $base64_img;
            }

            echo "<tr class='student-row-mobile'>";
            echo "<td data-label='ID'>" . htmlspecialchars($row['st_custom_id']) . "</td>";
            echo "<td data-label='Name'>
                    <div style='display: flex; align-items: center; gap: 10px;'>
                        <img src='" . htmlspecialchars($image_src, ENT_QUOTES) . "' alt='Profile' style='width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #ccc;'>
                        <span>" . htmlspecialchars($row['name']) . "</span>
                    </div>
                  </td>";
            echo "<td data-label='Status'><span class='$status_class' style='white-space: nowrap;'>" . htmlspecialchars($display_status) . "</span></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='text-align:center;'>No students found in this batch.</td></tr>";
    }
}
?>
