<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/db.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'User';
$current_log_id = $_SESSION['log_id'] ?? 0;
$current_custom_id = $_SESSION['custom_id'] ?? '';

if (empty($current_custom_id) && !empty($user_id)) {
    $uq = mysqli_query($conn, "SELECT custom_id FROM users WHERE id = '$user_id'");
    if ($uq && mysqli_num_rows($uq) > 0) {
        $current_custom_id = mysqli_fetch_assoc($uq)['custom_id'] ?? '';
    }
}

if (!in_array($role, ['Super Admin', 'SuperAdmin', 'Admin', 'Instructor'])) {
    header("Location: dashboard.php");
    exit();
}

$is_super_admin = ($role === 'Super Admin' || $role === 'SuperAdmin');
$is_admin = ($role === 'Admin' || $is_super_admin);
$is_instructor = ($role === 'Instructor');

// Fetch Instructor's assigned course
$instructor_course = "";
if ($is_instructor) {
    $uname = $_SESSION['username'] ?? '';
    $iq = mysqli_query($conn, "SELECT course_id FROM users WHERE (id = '$user_id' OR username = '$uname') AND role = 'Instructor'");
    if ($iq && mysqli_num_rows($iq) > 0) {
        $instructor_course = mysqli_fetch_assoc($iq)['course_id'] ?? '';
    }
}

// Active Tab / Category
$tab = $_GET['tab'] ?? 'attendance';
if ($is_instructor && in_array($tab, ['courses_summary', 'system_logs'])) {
    $tab = 'attendance';
}

// Fetch all courses for dropdowns
$all_courses = [];
$q_crs = "SELECT course_id, course_name, instructor_name FROM courses ORDER BY course_name ASC";
$r_crs = mysqli_query($conn, $q_crs);
if ($r_crs && mysqli_num_rows($r_crs) > 0) {
    while ($c = mysqli_fetch_assoc($r_crs)) {
        $all_courses[] = $c;
    }
}

// =========================================================================
// 1. ATTENDANCE REPORT LOGIC
// =========================================================================
$att_report_type = $_GET['att_report_type'] ?? 'monthly'; // 'monthly' or 'full'
$att_month = $_GET['att_month'] ?? date('Y-m'); // Default current month YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $att_month)) {
    $att_month = date('Y-m');
}

// Generate all calendar dates for selected month
$year_sel = (int)substr($att_month, 0, 4);
$month_sel = (int)substr($att_month, 5, 2);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_sel, $year_sel);
$att_month_dates = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $att_month_dates[] = sprintf('%04d-%02d-%02d', $year_sel, $month_sel, $d);
}

// Fetch scheduled holidays for this selected month
$holidays_in_month = [];
$month_esc_hol = mysqli_real_escape_string($conn, $att_month);
$q_hol = "SELECT date, holiday_name FROM holidays WHERE DATE_FORMAT(date, '%Y-%m') = '$month_esc_hol'";
$r_hol = @mysqli_query($conn, $q_hol);
if ($r_hol && mysqli_num_rows($r_hol) > 0) {
    while ($h = mysqli_fetch_assoc($r_hol)) {
        $holidays_in_month[$h['date']] = $h['holiday_name'];
    }
}

// Selected course for attendance
if ($is_instructor) {
    $selected_att_course = $instructor_course;
} else {
    $selected_att_course = $_GET['att_course_id'] ?? ($all_courses[0]['course_id'] ?? '');
}

// Fetch batches for this course
$att_course_batches = [];
$active_batch_id_default = '';
if (!empty($selected_att_course)) {
    $course_esc = mysqli_real_escape_string($conn, $selected_att_course);
    $q_bt = "SELECT batch_id, batch_name, status FROM batches WHERE course_id = '$course_esc' ORDER BY (status = 'Active' OR status = 'Not Pass Out') DESC, id DESC";
    $r_bt = mysqli_query($conn, $q_bt);
    if ($r_bt && mysqli_num_rows($r_bt) > 0) {
        while ($b = mysqli_fetch_assoc($r_bt)) {
            $att_course_batches[] = $b;
            if (empty($active_batch_id_default) && in_array($b['status'], ['Active', 'Not Pass Out'])) {
                $active_batch_id_default = $b['batch_id'];
            }
        }
    }
}

// If no active batch found, pick first batch
if (empty($active_batch_id_default) && !empty($att_course_batches)) {
    $active_batch_id_default = $att_course_batches[0]['batch_id'];
}

$selected_att_batch = $_GET['att_batch_id'] ?? $active_batch_id_default;

// Get selected course & batch details
$selected_course_info = null;
foreach ($all_courses as $c) {
    if ($c['course_id'] === $selected_att_course) {
        $selected_course_info = $c;
        break;
    }
}

$selected_batch_info = null;
foreach ($att_course_batches as $b) {
    if ($b['batch_id'] === $selected_att_batch) {
        $selected_batch_info = $b;
        break;
    }
}

// Fetch Students in selected Course & Batch
$att_students = [];
$att_matrix_data = [];
$att_distinct_dates = [];
$month_present_total = 0;
$month_absent_total = 0;
$month_total_records = 0;

if (!empty($selected_att_course) && !empty($selected_att_batch)) {
    $c_esc = mysqli_real_escape_string($conn, $selected_att_course);
    $b_esc = mysqli_real_escape_string($conn, $selected_att_batch);

    // 1. Get Students
    $q_st = "SELECT st_id, st_custom_id, name, status, nic, phone 
             FROM students 
             WHERE course_id = '$c_esc' AND batch_id = '$b_esc' 
             ORDER BY name ASC";
    $r_st = mysqli_query($conn, $q_st);
    if ($r_st && mysqli_num_rows($r_st) > 0) {
        while ($st = mysqli_fetch_assoc($r_st)) {
            $att_students[$st['st_custom_id']] = $st;
        }
    }

    if (!empty($att_students)) {
        $st_custom_ids = array_map(function($id) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $id) . "'";
        }, array_keys($att_students));
        $st_id_in_clause = implode(',', $st_custom_ids);

        if ($att_report_type === 'monthly') {
            // Validate and parse month (YYYY-MM)
            if (!preg_match('/^\d{4}-\d{2}$/', $att_month)) {
                $att_month = date('Y-m');
            }
            $month_esc = mysqli_real_escape_string($conn, $att_month);
            
            // Get all distinct marked dates for this batch in the selected month
            $q_dates = "SELECT DISTINCT DATE(date) as mark_date 
                        FROM student_attendance 
                        WHERE DATE_FORMAT(date, '%Y-%m') = '$month_esc' 
                          AND student_number IN ($st_id_in_clause) 
                        ORDER BY DATE(date) ASC";
            $r_dates = mysqli_query($conn, $q_dates);
            if ($r_dates && mysqli_num_rows($r_dates) > 0) {
                while ($d = mysqli_fetch_assoc($r_dates)) {
                    $att_distinct_dates[] = $d['mark_date'];
                }
            }

            // Fetch attendance entries for the month
            $q_att_data = "SELECT student_number, DATE(date) as att_date, absent_present 
                           FROM student_attendance 
                           WHERE DATE_FORMAT(date, '%Y-%m') = '$month_esc' 
                             AND student_number IN ($st_id_in_clause)";
            $r_att_data = mysqli_query($conn, $q_att_data);
            if ($r_att_data && mysqli_num_rows($r_att_data) > 0) {
                while ($row = mysqli_fetch_assoc($r_att_data)) {
                    $snum = $row['student_number'];
                    $adate = $row['att_date'];
                    $status = (int)$row['absent_present'];
                    $att_matrix_data[$snum][$adate] = $status;
                    
                    if ($status === 1) {
                        $month_present_total++;
                    } else {
                        $month_absent_total++;
                    }
                    $month_total_records++;
                }
            }

        } else {
            // FULL ATTENDANCE REPORT (All time for this course & batch)
            $q_att_full = "SELECT student_number, 
                                  COUNT(id) as total_marked, 
                                  SUM(CASE WHEN absent_present = 1 THEN 1 ELSE 0 END) as present_days, 
                                  SUM(CASE WHEN absent_present = 0 THEN 1 ELSE 0 END) as absent_days 
                           FROM student_attendance 
                           WHERE student_number IN ($st_id_in_clause) 
                           GROUP BY student_number";
            $r_att_full = mysqli_query($conn, $q_att_full);
            if ($r_att_full && mysqli_num_rows($r_att_full) > 0) {
                while ($row = mysqli_fetch_assoc($r_att_full)) {
                    $att_matrix_data[$row['student_number']] = [
                        'total' => (int)$row['total_marked'],
                        'present' => (int)$row['present_days'],
                        'absent' => (int)$row['absent_days']
                    ];
                    $month_present_total += (int)$row['present_days'];
                    $month_absent_total += (int)$row['absent_days'];
                    $month_total_records += (int)$row['total_marked'];
                }
            }
        }
    }
}

$month_present_pct = $month_total_records > 0 ? round(($month_present_total / $month_total_records) * 100, 1) : 0;
$month_absent_pct = $month_total_records > 0 ? round(($month_absent_total / $month_total_records) * 100, 1) : 0;

// =========================================================================
// 2. COURSE ENROLLMENT REPORT LOGIC
// =========================================================================
$enroll_course_filter = $_GET['enroll_course'] ?? '';
$enroll_batch_filter = $_GET['enroll_batch'] ?? '';
$enroll_status_filter = $_GET['enroll_status'] ?? '';
$enroll_search = trim($_GET['enroll_search'] ?? '');

$q_enroll = "SELECT s.st_id, s.st_custom_id, s.name, s.nic, s.gender, s.phone, s.status, s.enrolled_date, 
                    c.course_name, c.course_id, b.batch_name, b.batch_id 
             FROM students s 
             LEFT JOIN courses c ON s.course_id = c.course_id 
             LEFT JOIN batches b ON s.batch_id = b.batch_id 
             WHERE 1=1 ";

if ($is_instructor) {
    $q_enroll .= " AND s.course_id = '" . mysqli_real_escape_string($conn, $instructor_course) . "'";
} elseif (!empty($enroll_course_filter)) {
    $q_enroll .= " AND s.course_id = '" . mysqli_real_escape_string($conn, $enroll_course_filter) . "'";
}

if (!empty($enroll_batch_filter)) {
    $q_enroll .= " AND s.batch_id = '" . mysqli_real_escape_string($conn, $enroll_batch_filter) . "'";
}

if (!empty($enroll_status_filter)) {
    $q_enroll .= " AND s.status = '" . mysqli_real_escape_string($conn, $enroll_status_filter) . "'";
}

if (!empty($enroll_search)) {
    $s_esc = mysqli_real_escape_string($conn, $enroll_search);
    $q_enroll .= " AND (s.st_custom_id LIKE '%$s_esc%' OR s.name LIKE '%$s_esc%' OR s.nic LIKE '%$s_esc%') ";
}

$q_enroll .= " ORDER BY c.course_name ASC, b.batch_name ASC, s.name ASC";
$r_enroll = mysqli_query($conn, $q_enroll);
$enrollment_list = [];
$total_enrolled = 0;
$active_enrolled = 0;
$inactive_enrolled = 0;
$passed_enrolled = 0;

if ($r_enroll && mysqli_num_rows($r_enroll) > 0) {
    while ($row = mysqli_fetch_assoc($r_enroll)) {
        $enrollment_list[] = $row;
        $total_enrolled++;
        $st = $row['status'] ?? 'Active';
        if ($st === 'Active') $active_enrolled++;
        elseif ($st === 'Inactive') $inactive_enrolled++;
        else $passed_enrolled++;
    }
}

// =========================================================================
// 3. COURSES & BATCH SUMMARY REPORT LOGIC
// =========================================================================
$cs_course_filter = $_GET['cs_course'] ?? 'all';
$cs_batch_filter = $_GET['cs_batch'] ?? 'all';

$courses_summary_data = [];
$cs_batches_list = [];
$single_course_stats = null;
$single_course_students = [];

if ($cs_course_filter === 'all') {
    // Mode A: All Courses Overview
    $q_all_cr = "SELECT c.id, c.course_id, c.course_name, c.course_duration, c.instructor_name, 
                        COUNT(DISTINCT b.batch_id) as total_batches, 
                        COUNT(DISTINCT s.st_id) as total_students,
                        SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_students
                 FROM courses c 
                 LEFT JOIN batches b ON c.course_id = b.course_id 
                 LEFT JOIN students s ON c.course_id = s.course_id 
                 GROUP BY c.id 
                 ORDER BY c.course_name ASC";
    $r_all_cr = mysqli_query($conn, $q_all_cr);
    if ($r_all_cr && mysqli_num_rows($r_all_cr) > 0) {
        while ($row = mysqli_fetch_assoc($r_all_cr)) {
            $courses_summary_data[] = $row;
        }
    }
} else {
    // Mode B: Single Course Selected -> Fetch its Batches and Students
    $cs_c_esc = mysqli_real_escape_string($conn, $cs_course_filter);
    
    // Fetch Batches of this course for dropdown
    $q_cs_bt = "SELECT batch_id, batch_name, status FROM batches WHERE course_id = '$cs_c_esc' ORDER BY id DESC";
    $r_cs_bt = mysqli_query($conn, $q_cs_bt);
    if ($r_cs_bt && mysqli_num_rows($r_cs_bt) > 0) {
        while ($b = mysqli_fetch_assoc($r_cs_bt)) {
            $cs_batches_list[] = $b;
        }
    }

    // Fetch Course & Instructor info
    $q_c_info = "SELECT * FROM courses WHERE course_id = '$cs_c_esc' LIMIT 1";
    $r_c_info = mysqli_query($conn, $q_c_info);
    $course_record = mysqli_fetch_assoc($r_c_info);

    // Calculate Summary Stats for this course / batch
    $st_where = "s.course_id = '$cs_c_esc'";
    if ($cs_batch_filter !== 'all' && !empty($cs_batch_filter)) {
        $cs_b_esc = mysqli_real_escape_string($conn, $cs_batch_filter);
        $st_where .= " AND s.batch_id = '$cs_b_esc'";
    }

    $q_st_stats = "SELECT 
                    COUNT(*) as total_st, 
                    SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_st, 
                    SUM(CASE WHEN s.status = 'Inactive' THEN 1 ELSE 0 END) as inactive_st, 
                    SUM(CASE WHEN s.status = 'Passed Out' OR s.status = 'Pass Out' THEN 1 ELSE 0 END) as passed_st 
                   FROM students s 
                   WHERE $st_where";
    $r_st_stats = mysqli_query($conn, $q_st_stats);
    $st_stats_res = mysqli_fetch_assoc($r_st_stats);

    $single_course_stats = [
        'course_id' => $course_record['course_id'] ?? $cs_course_filter,
        'course_name' => $course_record['course_name'] ?? 'N/A',
        'instructor_name' => $course_record['instructor_name'] ?? 'Not Assigned',
        'duration' => $course_record['course_duration'] ?? 'N/A',
        'total_students' => (int)($st_stats_res['total_st'] ?? 0),
        'active_students' => (int)($st_stats_res['active_st'] ?? 0),
        'inactive_students' => (int)($st_stats_res['inactive_st'] ?? 0),
        'passed_students' => (int)($st_stats_res['passed_st'] ?? 0),
    ];

    // Fetch Students in this course / batch (with Profile Photo, ID, Name, NIC, Status)
    $q_single_st = "SELECT s.st_id, s.st_custom_id, s.name, s.nic, s.image, s.status, b.batch_name 
                    FROM students s 
                    LEFT JOIN batches b ON s.batch_id = b.batch_id 
                    WHERE $st_where 
                    ORDER BY b.batch_name ASC, s.name ASC";
    $r_single_st = mysqli_query($conn, $q_single_st);
    if ($r_single_st && mysqli_num_rows($r_single_st) > 0) {
        while ($st = mysqli_fetch_assoc($r_single_st)) {
            $single_course_students[] = $st;
        }
    }
}

// =========================================================================
// 4. SYSTEM LOGIN LOGS REPORT LOGIC
// =========================================================================
$log_role_filter = $_GET['log_role'] ?? 'all';
$log_date_filter = $_GET['log_date'] ?? '';

// Ensure system_logs table exists and has username column
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_custom_id VARCHAR(30), username VARCHAR(100), role VARCHAR(20), login_time DATETIME, logout_time DATETIME, status VARCHAR(20))");
$chk_col = @mysqli_query($conn, "SHOW COLUMNS FROM system_logs LIKE 'username'");
if ($chk_col && mysqli_num_rows($chk_col) === 0) {
    @mysqli_query($conn, "ALTER TABLE system_logs ADD COLUMN username VARCHAR(100) AFTER user_custom_id");
}

$q_logs = "SELECT sl.*, COALESCE(NULLIF(sl.username, ''), u.username, 'N/A') AS display_username 
           FROM system_logs sl 
           LEFT JOIN users u ON sl.user_custom_id = u.custom_id 
           WHERE 1=1 ";
if ($log_role_filter !== 'all' && !empty($log_role_filter)) {
    $lr_esc = mysqli_real_escape_string($conn, $log_role_filter);
    $q_logs .= " AND sl.role = '$lr_esc' ";
}
if (!empty($log_date_filter)) {
    $ld_esc = mysqli_real_escape_string($conn, $log_date_filter);
    $q_logs .= " AND DATE(sl.login_time) = '$ld_esc' ";
}
$q_logs .= " ORDER BY sl.id DESC LIMIT 200";
$r_logs = mysqli_query($conn, $q_logs);
$system_logs_data = [];
$total_logs_count = 0;
$active_sessions_count = 0;

if ($r_logs && mysqli_num_rows($r_logs) > 0) {
    while ($l = mysqli_fetch_assoc($r_logs)) {
        $system_logs_data[] = $l;
        $total_logs_count++;
        
        $is_curr_active = false;
        if (empty($l['logout_time'])) {
            if (!empty($current_log_id) && $l['id'] == $current_log_id) {
                $is_curr_active = true;
            } elseif ($l['user_custom_id'] === $current_custom_id) {
                $is_curr_active = true;
            }
        }
        if ($is_curr_active) {
            $active_sessions_count++;
        }
    }
}

include __DIR__ . '/../include/header.php'; 
?>

<!-- html2pdf Library for High-Quality PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../include/topbar.php'; ?>

        <div class="content">
            
            <!-- Page Header -->
            <div class="admin-header-row">
                <div>
                    <h2><i class="fa-solid fa-chart-pie" style="color: #2563eb; margin-right: 8px;"></i> System Reports & Analytics</h2>
                    <p style="color: var(--rep-text-muted); font-size: 0.9rem; margin-top: 4px;">Generate, analyze, and export comprehensive institutional reports</p>
                </div>
            </div>

            <!-- Category Navigation Tabs -->
            <div class="report-tabs-container">
                <a href="reports.php?tab=attendance" class="report-tab-btn <?php echo ($tab === 'attendance') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i> 1. Attendance Report
                </a>
                <a href="reports.php?tab=enrollment" class="report-tab-btn <?php echo ($tab === 'enrollment') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-graduate"></i> 2. Course Enrollment Report
                </a>
                <?php if ($is_admin): ?>
                    <a href="reports.php?tab=courses_summary" class="report-tab-btn <?php echo ($tab === 'courses_summary') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-layer-group"></i> 3. Courses & Batch Summary
                    </a>
                    <a href="reports.php?tab=system_logs" class="report-tab-btn <?php echo ($tab === 'system_logs') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-shield-halved"></i> 4. System Login Logs
                    </a>
                <?php endif; ?>
            </div>

            <!-- =========================================================
                 CATEGORY 1: STUDENT ATTENDANCE REPORT
                 ========================================================= -->
            <?php if ($tab === 'attendance'): ?>

                <!-- Filters for Attendance -->
                <div class="report-filter-panel">
                    <form method="GET" action="reports.php" id="attendanceFilterForm" class="filter-grid">
                        <input type="hidden" name="tab" value="attendance">
                        
                        <!-- Course Selector -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-book-bookmark"></i> Course</label>
                            <?php if ($is_instructor): ?>
                                <select name="att_course_id" class="modal-input" disabled style="opacity: 0.85; cursor: not-allowed;">
                                    <option value="<?php echo htmlspecialchars($instructor_course); ?>">
                                        <?php echo htmlspecialchars($selected_course_info['course_name'] ?? $instructor_course); ?>
                                    </option>
                                </select>
                                <input type="hidden" name="att_course_id" value="<?php echo htmlspecialchars($instructor_course); ?>">
                            <?php else: ?>
                                <select name="att_course_id" onchange="this.form.submit()">
                                    <?php foreach ($all_courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($selected_att_course === $c['course_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Batch Selector -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-people-roof"></i> Batch</label>
                            <select name="att_batch_id" onchange="this.form.submit()">
                                <?php if (!empty($att_course_batches)): ?>
                                    <?php foreach ($att_course_batches as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b['batch_id']); ?>" <?php echo ($selected_att_batch === $b['batch_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['batch_name']); ?> (<?php echo htmlspecialchars($b['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No batches found</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Combo Box 1: Report Type (Monthly / Full) -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-filter"></i> Report Type</label>
                            <select name="att_report_type" id="attReportTypeSelect" onchange="toggleMonthFilter(this.value)">
                                <option value="monthly" <?php echo ($att_report_type === 'monthly') ? 'selected' : ''; ?>>Monthly Report</option>
                                <option value="full" <?php echo ($att_report_type === 'full') ? 'selected' : ''; ?>>Full Attendance (Overall)</option>
                            </select>
                        </div>

                        <!-- Combo Box 2: Month Picker (Visible ONLY when Monthly is selected) -->
                        <div class="filter-item" id="monthFilterContainer" style="<?php echo ($att_report_type === 'full') ? 'display: none;' : ''; ?>">
                            <label><i class="fa-solid fa-calendar-days"></i> Select Month</label>
                            <input type="month" name="att_month" id="attMonthInput" value="<?php echo htmlspecialchars($att_month); ?>" onchange="this.form.submit()">
                        </div>

                        <!-- Submit / Reset -->
                        <div class="filter-item" style="flex: 0 0 auto; min-width: auto;">
                            <button type="submit" class="btn-add" style="background: var(--rep-primary); padding: 10px 18px;">
                                <i class="fa-solid fa-rotate"></i> Refresh
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Printable Branded Header -->
                <div id="printableReportSection">
                    <div class="print-only-header">
                        <img src="../images/vta.png" alt="Logo" style="height: 60px;">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem;">NATIONAL VOCATIONAL TRAINING INSTITUTE - BADDEGAMA</h2>
                            <h3 style="margin: 3px 0; font-size: 1rem; color: #2563eb;">STUDENT ATTENDANCE REPORT</h3>
                            <p style="margin: 0; font-size: 0.85rem; color: #475569;">
                                <strong>Course:</strong> <?php echo htmlspecialchars($selected_course_info['course_name'] ?? 'N/A'); ?> | 
                                <strong>Batch:</strong> <?php echo htmlspecialchars($selected_batch_info['batch_name'] ?? 'N/A'); ?> | 
                                <strong>Period:</strong> <?php echo ($att_report_type === 'monthly') ? date('F Y', strtotime($att_month . '-01')) : 'Full Overall'; ?> | 
                                <strong>Generated:</strong> <?php echo date('Y-m-d H:i'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Action Bar with Export Buttons -->
                    <div class="report-action-bar">
                        <div class="report-title-info">
                            <h3>
                                <i class="fa-solid fa-book-open-reader" style="color: #2563eb;"></i> 
                                Classroom Attendance Register Sheet 
                                <?php if ($att_report_type === 'monthly'): ?>
                                    <span style="font-size: 0.95rem; font-weight: 500; color: var(--rep-text-muted);">
                                        (<?php echo date('F Y', strtotime($att_month . '-01')); ?>)
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 0.95rem; font-weight: 500; color: var(--rep-text-muted);">(Full Duration)</span>
                                <?php endif; ?>
                            </h3>
                            <p>
                                <strong>Course:</strong> <?php echo htmlspecialchars($selected_course_info['course_name'] ?? 'N/A'); ?> &bull; 
                                <strong>Batch:</strong> <?php echo htmlspecialchars($selected_batch_info['batch_name'] ?? 'N/A'); ?> &bull; 
                                <strong>Instructor:</strong> <?php echo htmlspecialchars($selected_course_info['instructor_name'] ?? 'Not Assigned'); ?>
                            </p>
                        </div>
                        <div class="report-export-group">
                            <button type="button" onclick="exportAttendanceCSV()" class="btn-export btn-csv" title="Export CSV">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" onclick="exportAttendancePDF()" class="btn-export btn-pdf" title="Export PDF">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <!-- Register Matrix Table (Monthly Mode) -->
                    <?php if ($att_report_type === 'monthly'): ?>
                        <div class="register-table-wrapper" id="attendanceTableContainer">
                            <table class="register-table" id="attendanceReportTable">
                                <thead>
                                    <tr>
                                        <th class="col-student-id sticky-col-1">Student ID</th>
                                        <th class="col-student-name sticky-col-2">Student Name</th>
                                        <?php foreach ($att_month_dates as $adate): 
                                            $timestamp = strtotime($adate);
                                            $dnum = date('d', $timestamp);
                                            $dname = date('D', $timestamp);
                                            $is_weekend = ($dname === 'Sat' || $dname === 'Sun');
                                            $is_holiday = isset($holidays_in_month[$adate]);
                                            $holiday_title = $is_holiday ? $holidays_in_month[$adate] : '';

                                            $th_class = '';
                                            if ($is_holiday) {
                                                $th_class = 'cell-holiday';
                                            } elseif ($is_weekend) {
                                                $th_class = 'cell-weekend';
                                            }

                                            $hdr_title = $is_holiday ? "Public Holiday: $holiday_title ($adate)" : $adate;
                                        ?>
                                            <th class="cell-date-hdr <?php echo $th_class; ?>" title="<?php echo htmlspecialchars($hdr_title); ?>">
                                                <span class="day-num"><?php echo $dnum; ?></span>
                                                <span class="day-name"><?php echo $is_holiday ? '<i class="fa-solid fa-umbrella-beach"></i>' : $dname; ?></span>
                                            </th>
                                        <?php endforeach; ?>
                                        <th class="col-present-pct">
                                            Present %
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($att_students)): ?>
                                        <?php foreach ($att_students as $st_id => $st): 
                                            $total_sessions_marked = count($att_distinct_dates);
                                            $present_count = 0;
                                            $absent_count = 0;
                                        ?>
                                            <tr>
                                                <td class="col-student-id sticky-col-1"><?php echo htmlspecialchars($st['st_custom_id']); ?></td>
                                                <td class="col-student-name sticky-col-2"><strong><?php echo htmlspecialchars($st['name']); ?></strong></td>
                                                
                                                <?php foreach ($att_month_dates as $adate): 
                                                    $timestamp = strtotime($adate);
                                                    $dname = date('D', $timestamp);
                                                    $is_weekend = ($dname === 'Sat' || $dname === 'Sun');
                                                    $is_holiday = isset($holidays_in_month[$adate]);
                                                    $holiday_title = $is_holiday ? $holidays_in_month[$adate] : '';

                                                    $td_class = '';
                                                    if ($is_holiday) {
                                                        $td_class = ' cell-holiday-td';
                                                    } elseif ($is_weekend) {
                                                        $td_class = ' cell-weekend-td';
                                                    }
                                                    
                                                    $status = $att_matrix_data[$st_id][$adate] ?? null;
                                                    if ($is_holiday) {
                                                        echo "<td class='$td_class'><span class='att-mark-h' title='Holiday: " . htmlspecialchars($holiday_title) . " ($adate)'>H</span></td>";
                                                    } elseif ($status === 1) {
                                                        $present_count++;
                                                        echo "<td class='$td_class'><span class='att-mark-p' title='Present on $adate'>P</span></td>";
                                                    } elseif ($status === 0) {
                                                        $absent_count++;
                                                        echo "<td class='$td_class'><span class='att-mark-a' title='Absent on $adate'>A</span></td>";
                                                    } else {
                                                        echo "<td class='$td_class'><span class='att-mark-dash'>-</span></td>";
                                                    }
                                                ?>
                                                <?php endforeach; ?>

                                                <?php 
                                                    // Attendance Percentage Calculation
                                                    $student_pct = $total_sessions_marked > 0 ? round(($present_count / $total_sessions_marked) * 100, 1) : 0;
                                                    
                                                    // Color coding strictly per criteria:
                                                    // >= 80% : Green
                                                    // 50% - 79%: Yellow
                                                    // < 50% : Red
                                                    if ($student_pct >= 80) {
                                                        $badge_class = 'pct-green';
                                                    } elseif ($student_pct >= 50) {
                                                        $badge_class = 'pct-yellow';
                                                    } else {
                                                        $badge_class = 'pct-red';
                                                    }
                                                ?>
                                                <td>
                                                    <span class="pct-badge <?php echo $badge_class; ?>">
                                                        <?php echo $student_pct; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo count($att_month_dates) + 3; ?>" style="padding: 25px; text-align: center; color: var(--rep-text-muted);">
                                                No students found in this course and batch.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <!-- FULL ATTENDANCE REPORT TABLE (All Time) -->
                    <?php else: ?>
                        <div class="register-table-wrapper" id="attendanceTableContainer">
                            <table class="standard-report-table" id="attendanceReportTable">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Status</th>
                                        <th>Total Days Marked</th>
                                        <th>Present Days</th>
                                        <th>Absent Days</th>
                                        <th>Overall Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($att_students)): ?>
                                        <?php foreach ($att_students as $st_id => $st): 
                                            $data = $att_matrix_data[$st_id] ?? ['total' => 0, 'present' => 0, 'absent' => 0];
                                            $tot = $data['total'];
                                            $p = $data['present'];
                                            $a = $data['absent'];
                                            $pct = $tot > 0 ? round(($p / $tot) * 100, 1) : 0;

                                            if ($pct >= 80) {
                                                $badge_class = 'pct-green';
                                            } elseif ($pct >= 50) {
                                                $badge_class = 'pct-yellow';
                                            } else {
                                                $badge_class = 'pct-red';
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($st['st_custom_id']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                                <td>
                                                    <span class="status-pill status-<?php echo strtolower($st['status'] ?? 'active'); ?>-pill">
                                                        <?php echo htmlspecialchars($st['status'] ?? 'Active'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $tot; ?></td>
                                                <td style="color: #10b981; font-weight: bold;"><?php echo $p; ?></td>
                                                <td style="color: #ef4444; font-weight: bold;"><?php echo $a; ?></td>
                                                <td>
                                                    <span class="pct-badge <?php echo $badge_class; ?>">
                                                        <?php echo $pct; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 25px;">No attendance records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Bottom Summary Cards for Selected Month / Full -->
                    <div class="report-summary-grid">
                        
                        <!-- Present Students & Percentage Card -->
                        <div class="summary-card">
                            <div class="summary-icon icon-green">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Present Records</h4>
                                <h2><?php echo $month_present_total; ?> <span style="font-size: 0.95rem; font-weight: 500; color: #10b981;">(<?php echo $month_present_pct; ?>%)</span></h2>
                                <div class="summary-sub" style="color: #10b981;">
                                    <?php echo ($att_report_type === 'monthly') ? 'Present count in selected month' : 'Total present marks'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Absent Students & Percentage Card -->
                        <div class="summary-card">
                            <div class="summary-icon icon-red">
                                <i class="fa-solid fa-user-xmark"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Absent Records</h4>
                                <h2><?php echo $month_absent_total; ?> <span style="font-size: 0.95rem; font-weight: 500; color: #ef4444;">(<?php echo $month_absent_pct; ?>%)</span></h2>
                                <div class="summary-sub" style="color: #ef4444;">
                                    <?php echo ($att_report_type === 'monthly') ? 'Absent count in selected month' : 'Total absent marks'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Total Students in Batch -->
                        <div class="summary-card">
                            <div class="summary-icon icon-blue">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Students in Batch</h4>
                                <h2><?php echo count($att_students); ?></h2>
                                <div class="summary-sub" style="color: #2563eb;">Total enrolled in this batch</div>
                            </div>
                        </div>

                        <!-- Total Attendance Sessions Conducted -->
                        <div class="summary-card">
                            <div class="summary-icon icon-purple">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Sessions Conducted</h4>
                                <h2><?php echo ($att_report_type === 'monthly') ? count($att_distinct_dates) : $month_total_records; ?></h2>
                                <div class="summary-sub" style="color: #7c3aed;">
                                    <?php echo ($att_report_type === 'monthly') ? 'Marked days in ' . date('M Y', strtotime($att_month . '-01')) : 'Total records'; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            <!-- =========================================================
                 CATEGORY 2: COURSE ENROLLMENT REPORT
                 ========================================================= -->
            <?php elseif ($tab === 'enrollment'): ?>

                <!-- Filters for Enrollment -->
                <div class="report-filter-panel">
                    <form method="GET" action="reports.php" class="filter-grid">
                        <input type="hidden" name="tab" value="enrollment">
                        
                        <!-- Search Box -->
                        <div class="filter-item" style="flex: 2 1 250px;">
                            <label><i class="fa-solid fa-magnifying-glass"></i> Search Student</label>
                            <input type="text" name="enroll_search" placeholder="Search by Student ID, Name, NIC..." value="<?php echo htmlspecialchars($enroll_search); ?>">
                        </div>

                        <!-- Course Filter -->
                        <?php if (!$is_instructor): ?>
                        <div class="filter-item">
                            <label><i class="fa-solid fa-book"></i> Filter Course</label>
                            <select name="enroll_course" onchange="this.form.submit()">
                                <option value="">-- All Courses --</option>
                                <?php foreach ($all_courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($enroll_course_filter === $c['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Status Filter -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-sliders"></i> Status</label>
                            <select name="enroll_status" onchange="this.form.submit()">
                                <option value="">-- All Statuses --</option>
                                <option value="Active" <?php echo ($enroll_status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($enroll_status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Passed Out" <?php echo ($enroll_status_filter === 'Passed Out') ? 'selected' : ''; ?>>Passed Out</option>
                            </select>
                        </div>

                        <!-- Buttons -->
                        <div class="filter-item" style="flex: 0 0 auto; display: flex; gap: 8px; flex-direction: row; align-items: flex-end;">
                            <button type="submit" class="btn-add" style="background: var(--rep-primary); padding: 10px 18px;">
                                <i class="fa-solid fa-search"></i> Search
                            </button>
                            <?php if (!empty($enroll_search) || !empty($enroll_course_filter) || !empty($enroll_status_filter)): ?>
                                <a href="reports.php?tab=enrollment" class="btn-add" style="background: #64748b; padding: 10px 18px; text-decoration: none;">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div id="printableReportSection">
                    <!-- Printable Branded Header -->
                    <div class="print-only-header">
                        <img src="../images/vta.png" alt="VTA Logo">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem;">NATIONAL VOCATIONAL TRAINING INSTITUTE - BADDEGAMA</h2>
                            <h3 style="margin: 3px 0; font-size: 1rem; color: #2563eb;">STUDENT ENROLLMENT REPORT</h3>
                            <p style="margin: 0; font-size: 0.85rem; color: #475569;">
                                <strong>Total Enrolled:</strong> <?php echo $total_enrolled; ?> | 
                                <strong>Active:</strong> <?php echo $active_enrolled; ?> | 
                                <strong>Generated:</strong> <?php echo date('Y-m-d H:i'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="report-action-bar">
                        <div class="report-title-info">
                            <h3><i class="fa-solid fa-user-graduate" style="color: #2563eb;"></i> Student Enrollment Report</h3>
                            <p>Complete record of registered students, course assignments, batch allocations, and enrollment dates.</p>
                        </div>
                        <div class="report-export-group">
                            <button type="button" onclick="exportReportToCSV('enrollmentReportTable', 'NVTI_Student_Enrollment_Report_<?php echo date('Y-m-d'); ?>.csv')" class="btn-export btn-csv">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" onclick="exportReportToPDF('printableReportSection', 'Student Enrollment Report', 'NVTI_Student_Enrollment_Report_<?php echo date('Y-m-d'); ?>.pdf')" class="btn-export btn-pdf">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <!-- Summary Grid -->
                    <div class="report-summary-grid" style="margin-top: 0;">
                        <div class="summary-card">
                            <div class="summary-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                            <div class="summary-details">
                                <h4>Total Enrolled</h4>
                                <h2><?php echo $total_enrolled; ?></h2>
                                <div class="summary-sub" style="color: #2563eb;">Students recorded</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon icon-green"><i class="fa-solid fa-user-check"></i></div>
                            <div class="summary-details">
                                <h4>Active Students</h4>
                                <h2><?php echo $active_enrolled; ?> <span style="font-size: 0.9rem; font-weight: 500; color: #10b981;">(<?php echo $total_enrolled > 0 ? round(($active_enrolled/$total_enrolled)*100,1) : 0; ?>%)</span></h2>
                                <div class="summary-sub" style="color: #10b981;">Currently studying</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon icon-red"><i class="fa-solid fa-user-xmark"></i></div>
                            <div class="summary-details">
                                <h4>Inactive Students</h4>
                                <h2><?php echo $inactive_enrolled; ?> <span style="font-size: 0.9rem; font-weight: 500; color: #ef4444;">(<?php echo $total_enrolled > 0 ? round(($inactive_enrolled/$total_enrolled)*100,1) : 0; ?>%)</span></h2>
                                <div class="summary-sub" style="color: #ef4444;">Discontinued / Inactive</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon icon-purple"><i class="fa-solid fa-award"></i></div>
                            <div class="summary-details">
                                <h4>Passed Out</h4>
                                <h2><?php echo $passed_enrolled; ?></h2>
                                <div class="summary-sub" style="color: #7c3aed;">Completed courses</div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrollment Table -->
                    <div class="register-table-wrapper">
                        <table class="standard-report-table" id="enrollmentReportTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>NIC</th>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                    <th>Enrolled Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($enrollment_list)): ?>
                                    <?php foreach ($enrollment_list as $row): 
                                        $en_status = $row['status'] ?? 'Active';
                                        if ($en_status === 'Active') $pill = 'status-active-pill';
                                        elseif ($en_status === 'Inactive') $pill = 'status-inactive-pill';
                                        else $pill = 'status-passout-pill';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['st_custom_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nic']); ?></td>
                                            <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['batch_name'] ?? 'N/A'); ?></td>
                                            <td><span class="status-pill <?php echo $pill; ?>"><?php echo htmlspecialchars($en_status); ?></span></td>
                                            <td><?php echo !empty($row['enrolled_date']) ? htmlspecialchars($row['enrolled_date']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 25px;">No students found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- =========================================================
                 CATEGORY 3: COURSES & BATCH SUMMARY REPORT
                 ========================================================= -->
            <?php elseif ($tab === 'courses_summary' && $is_admin): ?>

                <!-- Filters for Courses & Batch Summary -->
                <div class="report-filter-panel">
                    <form method="GET" action="reports.php" class="filter-grid">
                        <input type="hidden" name="tab" value="courses_summary">
                        
                        <!-- Course Filter (All Courses + Individual Courses) -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-graduation-cap"></i> Select Course Filter</label>
                            <select name="cs_course" id="csCourseSelect" onchange="this.form.submit()">
                                <option value="all" <?php echo ($cs_course_filter === 'all') ? 'selected' : ''; ?>>-- All Courses --</option>
                                <?php foreach ($all_courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($cs_course_filter === $c['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Batch Filter (Appears ONLY if a single course is selected) -->
                        <?php if ($cs_course_filter !== 'all'): ?>
                            <div class="filter-item" id="csBatchContainer">
                                <label><i class="fa-solid fa-layer-group"></i> Select Batch</label>
                                <select name="cs_batch" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($cs_batch_filter === 'all') ? 'selected' : ''; ?>>-- All Batches --</option>
                                    <?php foreach ($cs_batches_list as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b['batch_id']); ?>" <?php echo ($cs_batch_filter === $b['batch_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['batch_name']); ?> (<?php echo htmlspecialchars($b['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Refresh -->
                        <div class="filter-item" style="flex: 0 0 auto;">
                            <button type="submit" class="btn-add" style="background: var(--rep-primary); padding: 10px 18px;">
                                <i class="fa-solid fa-arrows-rotate"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>

                <div id="printableReportSection">
                    <!-- Printable Branded Header -->
                    <div class="print-only-header">
                        <img src="../images/vta.png" alt="VTA Logo">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem;">NATIONAL VOCATIONAL TRAINING INSTITUTE - BADDEGAMA</h2>
                            <h3 style="margin: 3px 0; font-size: 1rem; color: #2563eb;">COURSES & BATCHES SUMMARY REPORT</h3>
                            <p style="margin: 0; font-size: 0.85rem; color: #475569;">
                                <strong>Generated:</strong> <?php echo date('Y-m-d H:i'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- ACTION BAR -->
                    <div class="report-action-bar">
                        <div class="report-title-info">
                            <?php if ($cs_course_filter === 'all'): ?>
                                <h3><i class="fa-solid fa-layer-group" style="color: #2563eb;"></i> All Courses & Batches Overview</h3>
                                <p>Comprehensive institutional summary of courses, assigned instructors, batch distributions, and enrollment metrics.</p>
                            <?php else: ?>
                                <h3>
                                    <i class="fa-solid fa-book-bookmark" style="color: #2563eb;"></i> 
                                    Course Details: <?php echo htmlspecialchars($single_course_stats['course_name'] ?? ''); ?>
                                </h3>
                                <p>
                                    <strong>Course ID:</strong> <?php echo htmlspecialchars($single_course_stats['course_id'] ?? ''); ?> &bull; 
                                    <strong>Instructor:</strong> <?php echo htmlspecialchars($single_course_stats['instructor_name'] ?? ''); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="report-export-group">
                            <button type="button" onclick="exportReportToCSV('coursesSummaryTable', 'NVTI_Courses_Batch_Summary_<?php echo date('Y-m-d'); ?>.csv')" class="btn-export btn-csv">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" onclick="exportReportToPDF('printableReportSection', 'Course & Batch Summary Report', 'NVTI_Courses_Batch_Summary_<?php echo date('Y-m-d'); ?>.pdf')" class="btn-export btn-pdf">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <!-- VIEW A: ALL COURSES SELECTED -->
                    <?php if ($cs_course_filter === 'all'): ?>
                        <div class="register-table-wrapper">
                            <table class="standard-report-table" id="coursesSummaryTable">
                                <thead>
                                    <tr>
                                        <th>Course ID</th>
                                        <th>Course Name</th>
                                        <th>Assigned Instructor</th>
                                        <th>Duration</th>
                                        <th>Total Batches</th>
                                        <th>Total Enrolled Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($courses_summary_data)): ?>
                                        <?php foreach ($courses_summary_data as $row): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['course_id']); ?></strong></td>
                                                <td><strong><?php echo htmlspecialchars($row['course_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['instructor_name'] ?? 'Not Assigned'); ?></td>
                                                <td><?php echo htmlspecialchars($row['course_duration'] ?? 'N/A'); ?></td>
                                                <td style="font-weight: 600; color: #7c3aed;"><?php echo $row['total_batches']; ?></td>
                                                <td style="font-weight: 700; color: #2563eb;"><?php echo $row['total_students']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 25px;">No courses found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <!-- VIEW B: SPECIFIC COURSE SELECTED -->
                    <?php else: ?>
                        
                        <!-- Summary Stats Cards -->
                        <div class="report-summary-grid" style="margin-top: 0;">
                            <div class="summary-card">
                                <div class="summary-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                                <div class="summary-details">
                                    <h4>Total Students</h4>
                                    <h2><?php echo $single_course_stats['total_students']; ?></h2>
                                    <div class="summary-sub" style="color: #2563eb;">In selected scope</div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon icon-green"><i class="fa-solid fa-user-check"></i></div>
                                <div class="summary-details">
                                    <h4>Currently Active</h4>
                                    <h2><?php echo $single_course_stats['active_students']; ?></h2>
                                    <div class="summary-sub" style="color: #10b981;">Active status</div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon icon-red"><i class="fa-solid fa-user-xmark"></i></div>
                                <div class="summary-details">
                                    <h4>Inactive Students</h4>
                                    <h2><?php echo $single_course_stats['inactive_students']; ?></h2>
                                    <div class="summary-sub" style="color: #ef4444;">Inactive status</div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon icon-purple"><i class="fa-solid fa-graduation-cap"></i></div>
                                <div class="summary-details">
                                    <h4>Passed Out</h4>
                                    <h2><?php echo $single_course_stats['passed_students']; ?></h2>
                                    <div class="summary-sub" style="color: #7c3aed;">Completed</div>
                                </div>
                            </div>
                        </div>

                        <!-- Students Table with Profile Photo, ID, Name, NIC, Status -->
                        <h4 style="margin: 25px 0 12px 0; font-size: 1.1rem; color: var(--rep-text-main);">
                            <i class="fa-solid fa-id-card-clip" style="color: #2563eb;"></i> Enrolled Students List
                        </h4>
                        
                        <div class="register-table-wrapper">
                            <table class="standard-report-table" id="coursesSummaryTable">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Photo</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>NIC</th>
                                        <th>Batch</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($single_course_students)): ?>
                                        <?php foreach ($single_course_students as $st): 
                                            $st_img = !empty($st['image']) ? 'data:image/jpeg;base64,' . base64_encode($st['image']) : '';
                                            $st_status = $st['status'] ?? 'Active';
                                            if ($st_status === 'Active') $pill = 'status-active-pill';
                                            elseif ($st_status === 'Inactive') $pill = 'status-inactive-pill';
                                            else $pill = 'status-passout-pill';
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($st_img)): ?>
                                                        <img src="<?php echo $st_img; ?>" alt="DP" class="rep-student-dp">
                                                    <?php else: ?>
                                                        <div class="rep-student-dp" style="display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #64748b;">
                                                            <i class="fa-solid fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($st['st_custom_id']); ?></strong></td>
                                                <td><strong><?php echo htmlspecialchars($st['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($st['nic']); ?></td>
                                                <td><?php echo htmlspecialchars($st['batch_name'] ?? 'N/A'); ?></td>
                                                <td><span class="status-pill <?php echo $pill; ?>"><?php echo htmlspecialchars($st_status); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 25px;">No students found in this course/batch.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>

            <!-- =========================================================
                 CATEGORY 4: SYSTEM LOGIN LOGS REPORT
                 ========================================================= -->
            <?php elseif ($tab === 'system_logs' && $is_admin): ?>

                <!-- Filters for System Logs -->
                <div class="report-filter-panel">
                    <form method="GET" action="reports.php" class="filter-grid">
                        <input type="hidden" name="tab" value="system_logs">
                        
                        <!-- Role Filter -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-user-shield"></i> Filter by Role</label>
                            <select name="log_role" onchange="this.form.submit()">
                                <option value="all" <?php echo ($log_role_filter === 'all') ? 'selected' : ''; ?>>-- All User Roles --</option>
                                <option value="Super Admin" <?php echo ($log_role_filter === 'Super Admin') ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="Admin" <?php echo ($log_role_filter === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="Instructor" <?php echo ($log_role_filter === 'Instructor') ? 'selected' : ''; ?>>Instructor</option>
                                <option value="Monitor" <?php echo ($log_role_filter === 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div class="filter-item">
                            <label><i class="fa-solid fa-calendar-day"></i> Filter by Date</label>
                            <input type="date" name="log_date" value="<?php echo htmlspecialchars($log_date_filter); ?>" onchange="this.form.submit()">
                        </div>

                        <!-- Refresh / Reset -->
                        <div class="filter-item" style="flex: 0 0 auto; display: flex; gap: 8px; flex-direction: row; align-items: flex-end;">
                            <button type="submit" class="btn-add" style="background: var(--rep-primary); padding: 10px 18px;">
                                <i class="fa-solid fa-filter"></i> Apply
                            </button>
                            <?php if ($log_role_filter !== 'all' || !empty($log_date_filter)): ?>
                                <a href="reports.php?tab=system_logs" class="btn-add" style="background: #64748b; padding: 10px 18px; text-decoration: none;">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div id="printableReportSection">
                    <!-- Printable Branded Header -->
                    <div class="print-only-header">
                        <img src="../images/vta.png" alt="VTA Logo">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem;">NATIONAL VOCATIONAL TRAINING INSTITUTE - BADDEGAMA</h2>
                            <h3 style="margin: 3px 0; font-size: 1rem; color: #2563eb;">SYSTEM AUTHENTICATION & LOGIN LOGS</h3>
                            <p style="margin: 0; font-size: 0.85rem; color: #475569;">
                                <strong>Total Logs:</strong> <?php echo $total_logs_count; ?> | 
                                <strong>Active Sessions:</strong> <?php echo $active_sessions_count; ?> | 
                                <strong>Generated:</strong> <?php echo date('Y-m-d H:i'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="report-action-bar">
                        <div class="report-title-info">
                            <h3><i class="fa-solid fa-shield-halved" style="color: #2563eb;"></i> System Authentication & Login Logs</h3>
                            <p>Complete audit log of system logins, active sessions, and user role activities.</p>
                        </div>
                        <div class="report-export-group">
                            <button type="button" onclick="exportReportToCSV('systemLogsTable', 'NVTI_System_Login_Logs_<?php echo date('Y-m-d'); ?>.csv')" class="btn-export btn-csv">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" onclick="exportReportToPDF('printableReportSection', 'System Login Logs', 'NVTI_System_Login_Logs_<?php echo date('Y-m-d'); ?>.pdf')" class="btn-export btn-pdf">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <!-- Summary Grid -->
                    <div class="report-summary-grid" style="margin-top: 0;">
                        <div class="summary-card">
                            <div class="summary-icon icon-blue"><i class="fa-solid fa-list-check"></i></div>
                            <div class="summary-details">
                                <h4>Total Logs Displayed</h4>
                                <h2><?php echo $total_logs_count; ?></h2>
                                <div class="summary-sub" style="color: #2563eb;">Recent authentication entries</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon icon-green"><i class="fa-solid fa-signal"></i></div>
                            <div class="summary-details">
                                <h4>Active Sessions</h4>
                                <h2><?php echo $active_sessions_count; ?></h2>
                                <div class="summary-sub" style="color: #10b981;">Currently logged in</div>
                            </div>
                        </div>
                    </div>

                    <!-- Logs Table -->
                    <div class="register-table-wrapper">
                        <table class="standard-report-table" id="systemLogsTable">
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>User Custom ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($system_logs_data)): ?>
                                    <?php foreach ($system_logs_data as $log): 
                                        $l_role = $log['role'] ?? 'User';
                                        if ($l_role === 'Super Admin' || $l_role === 'SuperAdmin') $role_bg = '#ede9fe; color: #6d28d9;';
                                        elseif ($l_role === 'Admin') $role_bg = '#dbeafe; color: #1d4ed8;';
                                        elseif ($l_role === 'Instructor') $role_bg = '#fef3c7; color: #b45309;';
                                        else $role_bg = '#ccfbf1; color: #0f766e;';

                                        $uname_display = !empty($log['display_username']) ? $log['display_username'] : (!empty($log['username']) ? $log['username'] : '-');

                                        // Check if this row is the currently logged in user's active session
                                        $is_active_session = false;
                                        if (empty($log['logout_time'])) {
                                            if (!empty($current_log_id) && $log['id'] == $current_log_id) {
                                                $is_active_session = true;
                                            } elseif ($log['user_custom_id'] === $current_custom_id) {
                                                $is_active_session = true;
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td>#<?php echo $log['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($log['user_custom_id']); ?></strong></td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--rep-text-main);">
                                                    <i class="fa-solid fa-user" style="color: #64748b; font-size: 0.8rem; margin-right: 4px;"></i>
                                                    <?php echo htmlspecialchars($uname_display); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="display: inline-block; padding: 3px 10px; border-radius: 14px; font-weight: 600; font-size: 0.82rem; background: <?php echo $role_bg; ?>">
                                                    <?php echo htmlspecialchars($l_role); ?>
                                                </span>
                                            </td>
                                            <td><i class="fa-regular fa-clock" style="color: #64748b;"></i> <?php echo htmlspecialchars($log['login_time']); ?></td>
                                            <td>
                                                <?php if (!empty($log['logout_time'])): ?>
                                                    <i class="fa-regular fa-circle-check" style="color: #10b981;"></i> <?php echo htmlspecialchars($log['logout_time']); ?>
                                                <?php elseif ($is_active_session): ?>
                                                    <span class="status-pill status-active-pill"><i class="fa-solid fa-bolt"></i> Active Session</span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.85rem;"><i class="fa-solid fa-arrow-right-from-bracket" style="opacity: 0.6; margin-right: 4px;"></i> Not Logged Out</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_active_session): ?>
                                                    <span class="status-pill status-active-pill">Active</span>
                                                <?php elseif (!empty($log['logout_time'])): ?>
                                                    <span class="status-pill status-inactive-pill"><?php echo htmlspecialchars(!empty($log['status']) && $log['status'] !== 'Active' ? $log['status'] : 'Logged Out'); ?></span>
                                                <?php else: ?>
                                                    <span class="status-pill status-inactive-pill">Closed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 25px;">No login logs found matching your filter criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Report Export Success Modal -->
<div id="reportSuccessModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center; border-radius: 16px; padding: 25px 20px;">
        <div style="width: 65px; height: 65px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
            <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981;"></i>
        </div>
        <h3 id="report_succ_title" style="margin: 0 0 8px 0; font-size: 1.25rem; font-weight: 700; color: var(--rep-text-main, #0f172a);">Export Successful!</h3>
        <p id="report_succ_msg" style="margin: 0; color: var(--rep-text-muted, #64748b); font-size: 0.9rem;">
            Your report has been exported successfully.
        </p>
        <div style="margin-top: 20px; display: flex; justify-content: center;">
            <button type="button" onclick="closeReportSuccessModal()" style="padding: 9px 26px; border-radius: 8px; border: none; background: #10b981; color: white; font-weight: 600; font-size: 0.92rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                <i class="fa-solid fa-check"></i> OK
            </button>
        </div>
    </div>
</div>

<!-- =========================================================
     JAVASCRIPT LOGIC FOR DYNAMIC FILTERS, CSV & PDF EXPORTS
     ========================================================= -->
<script>
// Toggle Month Filter visibility based on Combo Box 1 selection
function toggleMonthFilter(val) {
    var monthContainer = document.getElementById('monthFilterContainer');
    if (val === 'monthly') {
        monthContainer.style.display = 'flex';
    } else {
        monthContainer.style.display = 'none';
    }
    document.getElementById('attendanceFilterForm').submit();
}

// Attendance Table CSV Exporter
function exportAttendanceCSV() {
    var table = document.getElementById('attendanceReportTable');
    if (!table) {
        alert("No table data found to export.");
        return;
    }
    var filename = "NVTI_Attendance_Report_<?php echo htmlspecialchars($selected_att_batch); ?>_<?php echo ($att_report_type === 'monthly') ? $att_month : 'Full'; ?>.csv";
    exportReportToCSV('attendanceReportTable', filename);
}

// Attendance Table PDF Exporter
function exportAttendancePDF() {
    window.print();
}

// Generic CSV Export function with UTF-8 BOM encoding for Excel
function exportReportToCSV(tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) {
        alert("Table not found for export.");
        return;
    }

    var csv = [];
    var rows = table.querySelectorAll("tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [];
        var cols = rows[i].querySelectorAll("th, td");
        
        for (var j = 0; j < cols.length; j++) {
            // Clean inner text, replace newlines with space, double up quotes
            var data = cols[j].innerText.trim().replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }

    // Download CSV blob with UTF-8 BOM
    var csvContent = "\uFEFF" + csv.join("\r\n");
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(function() {
        showReportSuccessModal('CSV Exported Successfully!', 'The CSV data file (' + filename + ') has been downloaded to your device.');
    }, 300);
}

// Generic PDF Export function triggering high-definition master print/PDF engine
function exportReportToPDF(elementId, titleText, filename, orientation) {
    window.print();
}

// Print / PDF Export completion listener
window.addEventListener('afterprint', function() {
    showReportSuccessModal('PDF Report Generated Successfully!', 'The official institutional report has been processed and saved / sent to printer.');
});

function showReportSuccessModal(title, msg) {
    let titleEl = document.getElementById('report_succ_title');
    let msgEl = document.getElementById('report_succ_msg');
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = msg;
    let m = document.getElementById('reportSuccessModal');
    if (m) m.style.display = 'flex';
}

function closeReportSuccessModal() {
    let m = document.getElementById('reportSuccessModal');
    if (m) m.style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    let m = document.getElementById('reportSuccessModal');
    if (e.target === m) {
        closeReportSuccessModal();
    }
});
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
