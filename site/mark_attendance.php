<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$fullname = $_SESSION['fullname'] ?? '';

if ($role !== 'Instructor' && $role !== 'Monitor') {
    header("Location: dashboard.php");
    exit();
}

// Get active batch for the instructor or monitor
$q_inst = "SELECT u.course_id, u.custom_id, c.course_name FROM users u LEFT JOIN courses c ON u.course_id = c.course_id WHERE u.id = '$user_id'";
$inst_res = mysqli_fetch_assoc(mysqli_query($conn, $q_inst));
$course_id = $inst_res['course_id'] ?? '';
$instructor_custom_id = $inst_res['custom_id'] ?? '';
$course_name = $inst_res['course_name'] ?? '';

$active_batch = '';
$batch_name = '';

if ($role === 'Monitor' && $instructor_custom_id) {
    // Monitor's batch = same batch they belong to as a student
    $mon_bq = mysqli_query($conn, "SELECT batch_id FROM students WHERE st_custom_id = '$instructor_custom_id' LIMIT 1");
    if ($mon_bq && mysqli_num_rows($mon_bq) > 0) {
        $mon_bid = mysqli_fetch_assoc($mon_bq)['batch_id'];
        $bn_q = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE batch_id = '$mon_bid' LIMIT 1");
        if ($bn_q && mysqli_num_rows($bn_q) > 0) {
            $bres = mysqli_fetch_assoc($bn_q);
            $active_batch = $bres['batch_id'];
            $batch_name = $bres['batch_name'];
        }
    }
}

if (!$active_batch && $course_id) {
    $bq = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE course_id = '$course_id' AND (status = 'Not Pass Out' OR status = 'Active') LIMIT 1");
    if ($bq && mysqli_num_rows($bq) > 0) {
        $bres = mysqli_fetch_assoc($bq);
        $active_batch = $bres['batch_id'];
        $batch_name = $bres['batch_name'];
    }
}

if (!$active_batch) {
    header("Location: attendance.php?error=nobatch");
    exit();
}

$today = date('Y-m-d');
$students = [];
$sq = "SELECT s.st_custom_id, s.name, s.image, a.absent_present as att_status 
       FROM students s 
       LEFT JOIN student_attendance a ON s.st_custom_id = a.student_number AND DATE(a.date) = '$today'
       WHERE s.course_id = '$course_id' AND s.batch_id = '$active_batch' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL) 
       ORDER BY s.name ASC";
$sres = mysqli_query($conn, $sq);
if ($sres) {
    while ($row = mysqli_fetch_assoc($sres)) {
        $students[] = $row;
    }
}

include('../include/header.php'); 
?>

<!-- HTML5 QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <div class="admin-header-row" style="flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0;">Mark Attendance - <?php echo htmlspecialchars($batch_name); ?></h2>
                    <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.9rem;">Course: <strong><?php echo htmlspecialchars($course_name); ?></strong> | Date: <strong><?php echo date('l, M d, Y'); ?></strong></p>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn-add" onclick="openAttendanceQrScanner()" style="background: #059669; padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-camera"></i> Live QR Scanner
                    </button>
                    <a href="attendance.php" class="btn-back-styled"><i class="fa-solid fa-arrow-left"></i> Back</a>
                </div>
            </div>

            <!-- Attendance Table Form -->
            <div class="table-container" style="margin-top: 20px;">
                <form action="mark_attendance_process.php" method="POST" id="markAttendanceForm">
                    <input type="hidden" name="course_name" value="<?php echo htmlspecialchars($course_name); ?>">
                    <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">
                    <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($active_batch); ?>">
                    <input type="hidden" name="batch_name" value="<?php echo htmlspecialchars($batch_name); ?>">
                    <input type="hidden" name="marked_by" value="<?php echo htmlspecialchars($instructor_custom_id); ?>">
                    <input type="hidden" name="attendance_date" id="device_attendance_date" value="<?php echo date('Y-m-d'); ?>">
                    
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $s): 
                                    $st_cid = $s['st_custom_id'];
                                    $att_status = $s['att_status'];
                                    $is_present = ($att_status === '1' || $att_status === 1) ? 'checked' : '';
                                    $is_absent  = ($att_status === '0' || $att_status === 0) ? 'checked' : '';
                                ?>
                                    <tr id="row_st_<?php echo htmlspecialchars($st_cid); ?>">
                                        <td>
                                            <?php if (!empty($s['image'])): ?>
                                                <img src="data:image/jpeg;base64,<?php echo base64_encode($s['image']); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border: 2px solid #e2e8f0;">
                                            <?php else: ?>
                                                <div style="width:40px; height:40px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#64748b;"><i class="fa-solid fa-user"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($st_cid); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                        <td>
                                            <div style="display:flex; gap:15px; align-items:center;">
                                                <label class="radio-attendance-opt present" id="label_p_<?php echo htmlspecialchars($st_cid); ?>">
                                                    <input type="radio" id="radio_p_<?php echo htmlspecialchars($st_cid); ?>" name="attendance[<?php echo htmlspecialchars($st_cid); ?>]" value="1" <?php echo $is_present; ?> required> Present
                                                </label>
                                                <label class="radio-attendance-opt absent" id="label_a_<?php echo htmlspecialchars($st_cid); ?>">
                                                    <input type="radio" id="radio_a_<?php echo htmlspecialchars($st_cid); ?>" name="attendance[<?php echo htmlspecialchars($st_cid); ?>]" value="0" <?php echo $is_absent; ?> required> Absent
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; padding: 25px;">No students found in this batch.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 25px; display:flex; justify-content:space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <p style="margin:0; font-size:0.88rem; color:#64748b;">
                            <i class="fa-solid fa-bell" style="color:#059669; margin-right: 5px;"></i>
                            Finishing attendance will automatically send WhatsApp absent alerts to students marked Absent.
                        </p>
                        <button type="submit" class="btn-update-submit" style="padding: 12px 28px; font-size: 16px; background: #2563eb; border-radius: 8px;">
                            <i class="fa-solid fa-check-double"></i> Finish Attendance & Notify Absent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 1. Live QR Attendance Scanner Modal -->
<div id="qrAttendanceModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 520px; text-align: center;">
        <span class="admin-modal-close" onclick="closeAttendanceQrScanner()">&times;</span>
        <h3 class="admin-modal-title" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            <i class="fa-solid fa-qrcode" style="color: #059669;"></i> Live QR Attendance Scanner
        </h3>
        
        <p style="font-size: 0.88rem; color: #64748b; margin-top: -8px; margin-bottom: 15px;">
            Show student QR code to camera for instant check-in & WhatsApp alert
        </p>

        <div id="attendance_qr_reader" style="width: 100%; border-radius: 12px; overflow: hidden; border: 2px dashed #059669; background: #0f172a; min-height: 270px;"></div>

        <div id="attendance_qr_scan_status" style="margin-top: 14px; font-size: 0.92rem; font-weight: 600; min-height: 26px;"></div>

        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn-modal-dismiss" onclick="closeAttendanceQrScanner()">Close Scanner</button>
            <span style="font-size: 0.82rem; color: #64748b;"><i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Auto Check-in Active</span>
        </div>
    </div>
</div>

<!-- 2. Attendance Marked Success Popup Modal (Displays for 3 Seconds) -->
<div id="attendanceSuccessModal" class="admin-modal" style="z-index: 10000;">
    <div class="admin-modal-content" style="max-width: 440px; text-align: center; border-top: 6px solid #10b981; animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
        
        <!-- Glowing Check Icon & Photo Header -->
        <div style="margin-top: 5px; position: relative; display: inline-block;">
            <div id="succ_photo_container" style="width: 100px; height: 100px; margin: 0 auto; border-radius: 50%; padding: 4px; background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 0 20px rgba(16, 185, 129, 0.35);">
                <img id="succ_student_photo" src="" alt="Student" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: #ffffff; display: none;">
                <div id="succ_default_avatar" style="width: 100%; height: 100%; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #059669;">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <div style="position: absolute; bottom: 0; right: calc(50% - 46px); width: 28px; height: 28px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 2px solid #fff;">
                <i class="fa-solid fa-check"></i>
            </div>
        </div>

        <!-- Student Info -->
        <h3 id="succ_student_name" style="margin: 15px 0 4px 0; font-size: 1.35rem; color: var(--text-color, #1e293b);">Student Name</h3>
        <p id="succ_student_id" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #2563eb;">ST-2024-001</p>

        <!-- Attendance Status Badge -->
        <div style="margin: 16px 0; background: #d1fae5; color: #065f46; border: 1px solid #34d399; border-radius: 30px; padding: 8px 16px; font-weight: 700; font-size: 1.05rem; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-circle-check"></i> Attendance Marked. Thank you!
        </div>

        <!-- WhatsApp Notification Badge -->
        <div id="succ_whatsapp_badge" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; color: #166534; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 15px;">
            <i class="fa-brands fa-whatsapp" style="font-size: 1.2rem; color: #22c55e;"></i>
            <span id="succ_whatsapp_text">WhatsApp notification sent successfully!</span>
        </div>

        <!-- 3-Second Timer Progress Bar -->
        <div style="width: 100%; background: #e2e8f0; height: 5px; border-radius: 4px; overflow: hidden; margin-top: 10px;">
            <div id="succ_progress_bar" style="height: 100%; width: 100%; background: #10b981; transition: width 3s linear;"></div>
        </div>
        <p style="margin: 6px 0 0 0; font-size: 0.78rem; color: #94a3b8;">Resuming camera scan in 3 seconds...</p>
    </div>
</div>

<!-- =========================================================
     3. BATCH ATTENDANCE FINISH SUCCESS MODAL
     ========================================================= -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'marked'): ?>
<div id="successAttendanceMarkedModal" class="admin-modal" style="display: flex; z-index: 10000;">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center; border-radius: 16px; padding: 28px 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <div style="width: 68px; height: 68px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
            <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981;"></i>
        </div>
        <h3 style="margin: 0 0 8px 0; font-size: 1.35rem; font-weight: 700; color: #0f172a;">Attendance Recorded!</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.95rem; line-height: 1.5;">
            Student attendance records have been successfully saved to the system and absent notifications dispatched.
        </p>
        <div style="margin-top: 22px; display: flex; justify-content: center;">
            <button type="button" onclick="closeAttendanceMarkedSuccessModal()" style="padding: 10px 28px; border-radius: 8px; border: none; background: #10b981; color: white; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                <i class="fa-solid fa-check"></i> OK
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeAttendanceMarkedSuccessModal() {
    let m = document.getElementById('successAttendanceMarkedModal');
    if (m) {
        m.style.display = 'none';
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}
let attendanceQrScanner = null;
let autoResumeTimer = null;
let isProcessingQr = false;

const currentCourseName = "<?php echo addslashes($course_name); ?>";
const currentActiveBatch = "<?php echo addslashes($active_batch); ?>";
const currentMarkedBy = "<?php echo addslashes($instructor_custom_id); ?>";

function openAttendanceQrScanner() {
    document.getElementById('qrAttendanceModal').style.display = 'flex';
    document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #2563eb;"><i class="fa-solid fa-spinner fa-spin"></i> Initializing camera...</span>';
    startAttendanceScanner();
}

function startAttendanceScanner() {
    isProcessingQr = false;
    document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #059669;"><i class="fa-solid fa-video"></i> Camera active. Ready to scan student QR codes...</span>';

    if (attendanceQrScanner) {
        try { attendanceQrScanner.clear(); } catch(e) {}
    }

    attendanceQrScanner = new Html5Qrcode("attendance_qr_reader");
    attendanceQrScanner.start(
        { facingMode: "environment" },
        {
            fps: 15,
            qrbox: { width: 230, height: 230 }
        },
        (decodedText, decodedResult) => {
            if (!isProcessingQr) {
                handleAttendanceQrScanned(decodedText);
            }
        },
        (errorMessage) => {
            // normal frame scan loop
        }
    ).catch(err => {
        document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Camera access error: ' + err + '</span>';
    });
}

function handleAttendanceQrScanned(qrData) {
    isProcessingQr = true;
    
    // Pause scanning animation / status
    document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #2563eb;"><i class="fa-solid fa-spinner fa-spin"></i> Marking attendance for <strong>' + qrData + '</strong>...</span>';

    let formData = new FormData();
    formData.append("qr_data", qrData);
    formData.append("course_name", currentCourseName);
    formData.append("active_batch", currentActiveBatch);
    formData.append("marked_by", currentMarkedBy);

    // 🌍 Append device local date
    var d = new Date();
    var pad = function(n) { return n < 10 ? '0' + n : n; };
    var localDeviceDate = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    formData.append("device_date", localDeviceDate);

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax_mark_qr_attendance.php", true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                try {
                    let res = JSON.parse(xhr.responseText);
                    if (res.success && res.student) {
                        // 1. Update Table UI in background
                        updateStudentRowInTable(res.student.custom_id);

                        // 2. Temporarily pause QR scanner while popup is open
                        if (attendanceQrScanner) {
                            attendanceQrScanner.stop().then(() => {
                                try { attendanceQrScanner.clear(); } catch(e) {}
                            }).catch(err => {});
                        }

                        // 3. Show 3-Second Popup
                        showAttendanceSuccessPopup(res.student, res.whatsapp);
                    } else {
                        document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i> ' + (res.message || 'Scan error') + '</span>';
                        // Resume scanning after 2 seconds
                        setTimeout(() => {
                            isProcessingQr = false;
                        }, 2000);
                    }
                } catch(e) {
                    document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #ef4444;">Error processing attendance response</span>';
                    setTimeout(() => { isProcessingQr = false; }, 2000);
                }
            } else {
                document.getElementById('attendance_qr_scan_status').innerHTML = '<span style="color: #ef4444;">Server error (' + xhr.status + ')</span>';
                setTimeout(() => { isProcessingQr = false; }, 2000);
            }
        }
    };
    xhr.send(formData);
}

function updateStudentRowInTable(customId) {
    let pRadio = document.getElementById('radio_p_' + customId);
    let aRadio = document.getElementById('radio_a_' + customId);
    if (pRadio) {
        pRadio.checked = true;
    }
    if (aRadio) {
        aRadio.checked = false;
    }
    let row = document.getElementById('row_st_' + customId);
    if (row) {
        row.style.backgroundColor = 'rgba(16, 185, 129, 0.12)';
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 3000);
    }
}

function showAttendanceSuccessPopup(student, whatsapp) {
    document.getElementById('succ_student_name').textContent = student.name;
    document.getElementById('succ_student_id').textContent = student.custom_id + ' • ' + (student.batch_name || '');
    
    let photoImg = document.getElementById('succ_student_photo');
    let defaultAvatar = document.getElementById('succ_default_avatar');
    
    if (student.image) {
        photoImg.src = student.image;
        photoImg.style.display = 'block';
        defaultAvatar.style.display = 'none';
    } else {
        photoImg.style.display = 'none';
        defaultAvatar.style.display = 'flex';
    }

    // WhatsApp status text
    let waBadge = document.getElementById('succ_whatsapp_badge');
    let waText = document.getElementById('succ_whatsapp_text');
    if (whatsapp && whatsapp.status) {
        waBadge.style.display = 'flex';
        waBadge.style.background = '#f0fdf4';
        waBadge.style.borderColor = '#bbf7d0';
        waBadge.style.color = '#166534';
        waText.textContent = 'WhatsApp notification dispatched to ' + (student.phone || 'student');
    } else {
        waBadge.style.display = 'flex';
        waBadge.style.background = '#f8fafc';
        waBadge.style.borderColor = '#e2e8f0';
        waBadge.style.color = '#64748b';
        waText.textContent = whatsapp && whatsapp.message ? whatsapp.message : 'Attendance recorded';
    }

    // Reset Progress Bar
    let pBar = document.getElementById('succ_progress_bar');
    pBar.style.transition = 'none';
    pBar.style.width = '100%';
    setTimeout(() => {
        pBar.style.transition = 'width 3s linear';
        pBar.style.width = '0%';
    }, 50);

    // Show modal
    document.getElementById('attendanceSuccessModal').style.display = 'flex';

    // Clear any previous timer
    if (autoResumeTimer) clearTimeout(autoResumeTimer);

    // Auto-dismiss after 3 seconds and return to QR camera
    autoResumeTimer = setTimeout(() => {
        document.getElementById('attendanceSuccessModal').style.display = 'none';
        
        // If the scanner modal is still open, restart camera for the next student
        if (document.getElementById('qrAttendanceModal').style.display === 'flex') {
            startAttendanceScanner();
        }
    }, 3000);
}

function closeAttendanceQrScanner() {
    if (attendanceQrScanner) {
        attendanceQrScanner.stop().then(() => {
            try { attendanceQrScanner.clear(); } catch(e) {}
        }).catch(err => {});
    }
    if (autoResumeTimer) clearTimeout(autoResumeTimer);
    document.getElementById('qrAttendanceModal').style.display = 'none';
    document.getElementById('attendanceSuccessModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    let scanModal = document.getElementById('qrAttendanceModal');
    let succModal = document.getElementById('attendanceSuccessModal');
    let markedModal = document.getElementById('successAttendanceMarkedModal');
    if (event.target == scanModal) {
        closeAttendanceQrScanner();
    }
    if (event.target == succModal) {
        succModal.style.display = 'none';
        if (scanModal && scanModal.style.display === 'flex') {
            startAttendanceScanner();
        }
    }
    if (event.target == markedModal) {
        closeAttendanceMarkedSuccessModal();
    }
}

// Ensure form submission always transmits latest client device date
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('markAttendanceForm');
    if (form) {
        form.addEventListener('submit', function() {
            var d = new Date();
            var pad = function(n) { return n < 10 ? '0' + n : n; };
            var localDate = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            var dateInput = document.getElementById('device_attendance_date');
            if (dateInput) dateInput.value = localDate;
        });
    }
});
</script>

<?php include('../include/footer.php'); ?>
