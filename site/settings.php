<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/whatsapp_helper.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'User';

$is_super_admin = ($role === 'Super Admin' || $role === 'SuperAdmin');
$is_admin = ($role === 'Admin' || $is_super_admin);

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Get user custom_id for marked_by
$user_custom_id = $_SESSION['custom_id'] ?? '';
if (empty($user_custom_id) && !empty($user_id)) {
    $uq = mysqli_query($conn, "SELECT custom_id FROM users WHERE id = '$user_id' LIMIT 1");
    if ($uq && mysqli_num_rows($uq) > 0) {
        $user_custom_id = mysqli_fetch_assoc($uq)['custom_id'] ?? 'Admin';
    }
}

// Auto-create holidays table if not exists
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(date)
)");

// Active Tab (holidays / past_attendance / whatsapp)
$tab = $_GET['tab'] ?? 'holidays';
if (!in_array($tab, ['holidays', 'past_attendance', 'whatsapp'])) {
    $tab = 'holidays';
}

// Load WhatsApp Gateway Settings
$wa_settings = get_whatsapp_settings($conn);

// Fetch all courses for dropdown
$all_courses = [];
$q_crs = "SELECT course_id, course_name, instructor_name FROM courses ORDER BY course_name ASC";
$r_crs = mysqli_query($conn, $q_crs);
if ($r_crs && mysqli_num_rows($r_crs) > 0) {
    while ($c = mysqli_fetch_assoc($r_crs)) {
        $all_courses[] = $c;
    }
}

// Fetch all batches grouped by course_id for fast cascading
$batches_by_course = [];
$q_bt = "SELECT id, batch_id, batch_name, course_id, status FROM batches ORDER BY (status = 'Active' OR status = 'Not Pass Out') DESC, id DESC";
$r_bt = mysqli_query($conn, $q_bt);
if ($r_bt && mysqli_num_rows($r_bt) > 0) {
    while ($b = mysqli_fetch_assoc($r_bt)) {
        $batches_by_course[$b['course_id']][] = $b;
    }
}

// Fetch Holidays for list
$holiday_year_filter = $_GET['h_year'] ?? 'all';
$holiday_search = trim($_GET['h_search'] ?? '');

$q_hol = "SELECT id, holiday_name, date FROM holidays WHERE 1=1 ";
if ($holiday_year_filter !== 'all' && is_numeric($holiday_year_filter)) {
    $y_esc = (int)$holiday_year_filter;
    $q_hol .= " AND YEAR(date) = $y_esc ";
}
if (!empty($holiday_search)) {
    $hs_esc = mysqli_real_escape_string($conn, $holiday_search);
    $q_hol .= " AND (holiday_name LIKE '%$hs_esc%' OR date LIKE '%$hs_esc%') ";
}
$q_hol .= " ORDER BY date DESC";
$r_hol = mysqli_query($conn, $q_hol);
$holidays_list = [];
$holiday_years_available = [];
if ($r_hol && mysqli_num_rows($r_hol) > 0) {
    while ($h = mysqli_fetch_assoc($r_hol)) {
        $holidays_list[] = $h;
        $yr = date('Y', strtotime($h['date']));
        if (!in_array($yr, $holiday_years_available)) {
            $holiday_years_available[] = $yr;
        }
    }
}
rsort($holiday_years_available);

// -------------------------------------------------------------
// PAST ATTENDANCE DATA PREPARATION
// -------------------------------------------------------------
$selected_past_course = $_GET['past_course'] ?? ($all_courses[0]['course_id'] ?? '');
$available_past_batches = $batches_by_course[$selected_past_course] ?? [];
$selected_past_batch = $_GET['past_batch'] ?? ($available_past_batches[0]['batch_id'] ?? '');
$selected_past_date = $_GET['past_date'] ?? date('Y-m-d');

$past_students_data = [];
$past_existing_attendance = [];
$is_existing_marked = false;

if ($tab === 'past_attendance' && !empty($selected_past_course) && !empty($selected_past_batch) && !empty($selected_past_date)) {
    $pc_esc = mysqli_real_escape_string($conn, $selected_past_course);
    $pb_esc = mysqli_real_escape_string($conn, $selected_past_batch);
    $pd_esc = mysqli_real_escape_string($conn, $selected_past_date);

    // Fetch all students (Active, Inactive, etc.) in this course and batch
    $q_pst = "SELECT st_id, st_custom_id, name, status, nic, image 
              FROM students 
              WHERE course_id = '$pc_esc' AND batch_id = '$pb_esc'
              ORDER BY (status = 'Active') DESC, name ASC";
    $r_pst = mysqli_query($conn, $q_pst);
    if ($r_pst && mysqli_num_rows($r_pst) > 0) {
        while ($st = mysqli_fetch_assoc($r_pst)) {
            $past_students_data[] = $st;
        }
    }

    if (!empty($past_students_data)) {
        $st_custom_ids = array_map(function($st) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $st['st_custom_id']) . "'";
        }, $past_students_data);
        $st_id_in_clause = implode(',', $st_custom_ids);

        // Check if attendance records already exist on that past date
        $q_ex_att = "SELECT student_number, absent_present, marked_by 
                     FROM student_attendance 
                     WHERE DATE(date) = '$pd_esc' AND student_number IN ($st_id_in_clause)";
        $r_ex_att = mysqli_query($conn, $q_ex_att);
        if ($r_ex_att && mysqli_num_rows($r_ex_att) > 0) {
            $is_existing_marked = true;
            while ($row = mysqli_fetch_assoc($r_ex_att)) {
                $past_existing_attendance[$row['student_number']] = (int)$row['absent_present'];
            }
        }
    }
}

// Fetch all holidays lookup map for fast checking and JS alerts
$all_holidays_map = [];
$q_all_h = mysqli_query($conn, "SELECT date, holiday_name FROM holidays");
if ($q_all_h && mysqli_num_rows($q_all_h) > 0) {
    while ($hr = mysqli_fetch_assoc($q_all_h)) {
        $all_holidays_map[$hr['date']] = $hr['holiday_name'];
    }
}

// Check if selected past date is a weekend (Saturday / Sunday) or holiday
$past_day_name = date('l', strtotime($selected_past_date));
$is_past_weekend = in_array($past_day_name, ['Saturday', 'Sunday']);
$is_past_holiday = isset($all_holidays_map[$selected_past_date]);
$past_holiday_title = $is_past_holiday ? $all_holidays_map[$selected_past_date] : '';

// Find Course & Batch info for display
$selected_past_course_name = '';
foreach ($all_courses as $c) {
    if ($c['course_id'] === $selected_past_course) {
        $selected_past_course_name = $c['course_name'];
        break;
    }
}
$selected_past_batch_name = '';
foreach ($available_past_batches as $b) {
    if ($b['batch_id'] === $selected_past_batch) {
        $selected_past_batch_name = $b['batch_name'];
        break;
    }
}

include __DIR__ . '/../include/header.php'; 
?>

<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../include/topbar.php'; ?>

        <div class="content">
            
            <!-- Page Header -->
            <div class="admin-header-row">
                <div>
                    <h2><i class="fa-solid fa-gear" style="color: #2563eb; margin-right: 8px;"></i> System Settings & Controls</h2>
                    <p style="color: var(--set-text-muted); font-size: 0.9rem; margin-top: 4px;">
                        Configure institutional holidays and record historical attendance logs
                    </p>
                </div>
            </div>

            <!-- Settings Tabs Navigation -->
            <div class="settings-tabs-container">
                <a href="settings.php?tab=holidays" class="settings-tab-btn <?php echo ($tab === 'holidays') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-umbrella-beach"></i> 1. Holiday Management
                </a>
                <a href="settings.php?tab=past_attendance" class="settings-tab-btn <?php echo ($tab === 'past_attendance') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> 2. Historical / Past Attendance Marking
                </a>
                <a href="settings.php?tab=whatsapp" class="settings-tab-btn <?php echo ($tab === 'whatsapp') ? 'active' : ''; ?>">
                    <i class="fa-brands fa-whatsapp" style="color: #22c55e;"></i> 3. WhatsApp Gateway & Automation
                </a>
            </div>

            <!-- Notifications / Flash Messages -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
                <div class="alert-banner success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                    <span><strong>Success!</strong> Successfully added/updated <?php echo (int)($_GET['count'] ?? 1); ?> holiday date(s).</span>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
                <div class="alert-banner success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                    <span><strong>Success!</strong> Holiday details updated successfully.</span>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert-banner success">
                    <i class="fa-solid fa-trash-can" style="font-size: 1.2rem;"></i>
                    <span><strong>Deleted!</strong> Holiday removed successfully from system.</span>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'att_saved'): ?>
                <div class="alert-banner success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                    <span><strong>Success!</strong> Historical attendance for <strong><?php echo htmlspecialchars($_GET['date'] ?? ''); ?></strong> successfully recorded (<?php echo (int)($_GET['p'] ?? 0); ?> Present, <?php echo (int)($_GET['a'] ?? 0); ?> Absent).</span>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'wa_saved'): ?>
                <div class="alert-banner success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                    <span><strong>Saved!</strong> WhatsApp Gateway configuration and notification templates saved successfully.</span>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'test_sent'): ?>
                <div class="alert-banner success">
                    <i class="fa-brands fa-whatsapp" style="font-size: 1.2rem; color: #22c55e;"></i>
                    <span><strong>Delivered!</strong> Test WhatsApp message sent successfully! Your WhatsApp Gateway connection is verified.</span>
                </div>
            <?php elseif (isset($_GET['err']) && $_GET['err'] === 'test_failed'): ?>
                <div class="alert-banner danger">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.2rem;"></i>
                    <span><strong>WhatsApp Delivery Failed:</strong> <?php echo htmlspecialchars($_GET['errmsg'] ?? 'Could not send test message. Check your Instance ID and Token.'); ?></span>
                </div>
            <?php elseif (isset($_GET['err']) && $_GET['err'] === 'empty_phone'): ?>
                <div class="alert-banner danger">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem;"></i>
                    <span><strong>Error:</strong> Please provide a recipient phone number for the test message.</span>
                </div>
            <?php elseif (isset($_GET['err'])): ?>
                <div class="alert-banner danger">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem;"></i>
                    <span><strong>Error:</strong> Please check your input and try again.</span>
                </div>
            <?php endif; ?>

            <!-- =========================================================
                 TAB 1: HOLIDAY MANAGEMENT
                 ========================================================= -->
            <?php if ($tab === 'holidays'): ?>

                <!-- Card 1: Add New Holidays (Dynamic Multi-Row Input Form) -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div>
                            <h3><i class="fa-solid fa-calendar-plus" style="color: #2563eb;"></i> Add Institution Holidays</h3>
                            <p>Enter holiday titles and dates. Use the buttons to add or remove multiple rows before saving.</p>
                        </div>
                    </div>

                    <form action="holiday_process.php" method="POST" id="addHolidaysForm">
                        <input type="hidden" name="action" value="add_holidays">
                        
                        <div class="holiday-rows-wrapper" id="holidayRowsContainer">
                            <!-- Row 1 (Default) -->
                            <div class="holiday-row-item">
                                <div class="field-name">
                                    <input type="text" name="holiday_name[]" placeholder="Holiday Title (e.g. Sinhala & Tamil New Year / Poya Day)" required autocomplete="off">
                                </div>
                                <div class="field-date">
                                    <input type="date" name="holiday_date[]" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <button type="button" class="btn-remove-row" onclick="removeHolidayRow(this)" title="Remove Row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Action Control Buttons -->
                        <div class="btn-action-group">
                            <button type="button" class="btn-add-row" onclick="addHolidayRow()">
                                <i class="fa-solid fa-plus-circle"></i> + Add Another Holiday Field
                            </button>
                            <button type="submit" class="btn-submit-main" style="margin-left: auto;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Holidays
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Existing Holidays List Table -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div>
                            <h3><i class="fa-solid fa-calendar-days" style="color: #2563eb;"></i> Scheduled Institutional Holidays</h3>
                            <p>All recorded public holidays, poya days, and institutional holidays.</p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <!-- Year Filter -->
                            <form method="GET" action="settings.php" style="display: flex; gap: 10px;">
                                <input type="hidden" name="tab" value="holidays">
                                <select name="h_year" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main); font-size: 0.9rem;">
                                    <option value="all" <?php echo ($holiday_year_filter === 'all') ? 'selected' : ''; ?>>All Years</option>
                                    <?php foreach ($holiday_years_available as $yr): ?>
                                        <option value="<?php echo $yr; ?>" <?php echo ($holiday_year_filter == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="register-table-wrapper">
                        <table class="standard-report-table" id="holidaysTable">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">#</th>
                                    <th>Holiday Title</th>
                                    <th>Date</th>
                                    <th>Day of Week</th>
                                    <th style="width: 140px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($holidays_list)): ?>
                                    <?php $i = 1; foreach ($holidays_list as $h): 
                                        $t = strtotime($h['date']);
                                        $day_name = date('l', $t);
                                    ?>
                                        <tr>
                                            <td><strong>#<?php echo $i++; ?></strong></td>
                                            <td>
                                                <span style="font-weight: 600; font-size: 0.95rem; color: var(--set-text-main);">
                                                    <i class="fa-solid fa-umbrella-beach" style="color: #f59e0b; margin-right: 8px;"></i>
                                                    <?php echo htmlspecialchars($h['holiday_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: #2563eb;">
                                                    <i class="fa-regular fa-calendar" style="margin-right: 6px;"></i>
                                                    <?php echo htmlspecialchars($h['date']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-weight: 600; font-size: 0.82rem; background: #e0e7ff; color: #3730a3;">
                                                    <?php echo $day_name; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 8px;">
                                                    <button type="button" class="btn-add" style="background: #2563eb; padding: 6px 12px; font-size: 0.82rem;" onclick="openEditHolidayModal(<?php echo (int)$h['id']; ?>, '<?php echo htmlspecialchars(addslashes($h['holiday_name'])); ?>', '<?php echo htmlspecialchars($h['date']); ?>')">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button" class="btn-add" style="background: #dc2626; padding: 6px 12px; font-size: 0.82rem;" onclick="openDeleteHolidayModal(<?php echo (int)$h['id']; ?>, '<?php echo htmlspecialchars(addslashes($h['holiday_name'])); ?>', '<?php echo htmlspecialchars($h['date']); ?>')">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--set-text-muted);">
                                            <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; opacity: 0.4; margin-bottom: 8px; display: block;"></i>
                                            No holidays recorded yet. Add your first holiday above.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- =========================================================
                 TAB 2: HISTORICAL / PAST ATTENDANCE MARKING
                 ========================================================= -->
            <?php elseif ($tab === 'past_attendance'): ?>

                <!-- Selection & Filters Panel -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div>
                            <h3><i class="fa-solid fa-clock-rotate-left" style="color: #2563eb;"></i> Select Course, Batch & Historical Date</h3>
                            <p>Choose the target course, batch, and the specific past date to record attendance.</p>
                        </div>
                    </div>

                    <form method="GET" action="settings.php" class="past-filter-grid" id="pastAttendanceFilterForm">
                        <input type="hidden" name="tab" value="past_attendance">

                        <!-- Course Selector -->
                        <div class="past-filter-item">
                            <label><i class="fa-solid fa-book-bookmark"></i> Course</label>
                            <select name="past_course" id="pastCourseSelect" onchange="onCourseChanged(this.value)">
                                <?php foreach ($all_courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($selected_past_course === $c['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Batch Selector -->
                        <div class="past-filter-item">
                            <label><i class="fa-solid fa-people-roof"></i> Batch</label>
                            <select name="past_batch" id="pastBatchSelect">
                                <?php if (!empty($available_past_batches)): ?>
                                    <?php foreach ($available_past_batches as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b['batch_id']); ?>" <?php echo ($selected_past_batch === $b['batch_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['batch_name']); ?> (<?php echo htmlspecialchars($b['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No batches for this course</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Historical Date Picker -->
                        <div class="past-filter-item">
                            <label><i class="fa-solid fa-calendar-day"></i> Historical Date</label>
                            <input type="date" name="past_date" id="pastDateInput" value="<?php echo htmlspecialchars($selected_past_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="checkPastDateLiveAlert(this.value)" required>
                            <div id="dateLiveAlertBox" style="margin-top: 6px; font-size: 0.84rem;"></div>
                        </div>

                        <!-- Submit Button -->
                        <div class="past-filter-item" style="flex: 0 0 auto;">
                            <button type="submit" class="btn-submit-main" style="padding: 10px 20px;">
                                <i class="fa-solid fa-users-viewfinder"></i> Load Students
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Students Attendance Sheet -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-clipboard-user" style="color: #2563eb;"></i> 
                                Student Attendance Sheet: <?php echo htmlspecialchars($selected_past_course_name); ?> 
                                <span style="font-size: 0.95rem; font-weight: 500; color: var(--set-text-muted);">
                                    (Batch: <?php echo htmlspecialchars($selected_past_batch_name); ?> &bull; Date: <?php echo date('F d, Y (l)', strtotime($selected_past_date)); ?>)
                                </span>
                            </h3>
                            <?php if ($is_existing_marked): ?>
                                <p style="color: #d97706; font-weight: 600;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Attendance records already exist for this date. Submitting will update the records.
                                </p>
                            <?php else: ?>
                                <p>Mark each student as Present or Absent and click "Save Historical Attendance".</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Holiday / Weekend Alert Notices -->
                    <?php if ($is_past_holiday): ?>
                        <div class="alert-banner warning" style="background: #fef08a; color: #854d0e; border: 1px solid #eab308; margin-bottom: 18px;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.3rem;"></i>
                            <div>
                                <strong>Public Holiday Notice:</strong> The selected date (<strong><?php echo htmlspecialchars($selected_past_date); ?></strong>) is recorded as a Public / Institutional Holiday: <strong><?php echo htmlspecialchars($past_holiday_title); ?></strong>.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_past_weekend): ?>
                        <div class="alert-banner warning" style="background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; margin-bottom: 18px;">
                            <i class="fa-solid fa-calendar-week" style="font-size: 1.3rem;"></i>
                            <div>
                                <strong>Weekend Notice:</strong> The selected date (<strong><?php echo htmlspecialchars($selected_past_date); ?></strong>) falls on a Weekend (<strong><?php echo htmlspecialchars($past_day_name); ?></strong>).
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($past_students_data)): ?>
                        <form action="past_attendance_process.php" method="POST" id="pastAttendanceForm" onsubmit="submitPastAttendanceAjax(event)">
                            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selected_past_course); ?>">
                            <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($selected_past_batch); ?>">
                            <input type="hidden" name="course_name" value="<?php echo htmlspecialchars($selected_past_course_name); ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_past_date); ?>">
                            <input type="hidden" name="marked_by" value="<?php echo htmlspecialchars($user_custom_id); ?>">
                            <input type="hidden" name="is_ajax" value="1">

                            <!-- Quick Action Bar -->
                            <div class="att-quick-bar">
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <span style="font-weight: 600; color: var(--set-text-main);">Quick Action:</span>
                                    <button type="button" class="btn-quick-p" onclick="markAllAttendance(1)">
                                        <i class="fa-solid fa-check-double"></i> Mark All Present
                                    </button>
                                    <button type="button" class="btn-quick-a" onclick="markAllAttendance(0)">
                                        <i class="fa-solid fa-xmark"></i> Mark All Absent
                                    </button>
                                </div>
                                <div style="font-size: 0.9rem; font-weight: 600; color: var(--set-text-muted);">
                                    Total Students: <strong><?php echo count($past_students_data); ?></strong>
                                </div>
                            </div>

                            <div class="register-table-wrapper">
                                <table class="standard-report-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Photo</th>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>NIC</th>
                                            <th>Status</th>
                                            <th style="width: 220px; text-align: center;">Attendance Mark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($past_students_data as $st): 
                                            $snum = $st['st_custom_id'];
                                            $st_img = !empty($st['image']) ? 'data:image/jpeg;base64,' . base64_encode($st['image']) : '';
                                            
                                            // Determine checked status
                                            if (isset($past_existing_attendance[$snum])) {
                                                $val = $past_existing_attendance[$snum];
                                                $is_p = ($val === 1) ? 'checked' : '';
                                                $is_a = ($val === 0) ? 'checked' : '';
                                            } else {
                                                // Default to Present
                                                $is_p = 'checked';
                                                $is_a = '';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($st_img)): ?>
                                                        <img src="<?php echo $st_img; ?>" alt="DP" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--set-card-border);">
                                                    <?php else: ?>
                                                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                                            <i class="fa-solid fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($st['st_custom_id']); ?></strong></td>
                                                <td><span style="font-weight: 600; color: var(--set-text-main);"><?php echo htmlspecialchars($st['name']); ?></span></td>
                                                <td><?php echo htmlspecialchars($st['nic'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                        $st_st = $st['status'] ?? 'Active';
                                                        if ($st_st === 'Active') $pill_cls = 'status-active-pill';
                                                        elseif ($st_st === 'Inactive') $pill_cls = 'status-inactive-pill';
                                                        else $pill_cls = 'status-passout-pill';
                                                    ?>
                                                    <span class="status-pill <?php echo $pill_cls; ?>">
                                                        <?php echo htmlspecialchars($st_st); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div class="radio-attendance-box" style="justify-content: center;">
                                                        <label class="radio-attendance-opt present">
                                                            <input type="radio" class="att-radio-p" name="attendance[<?php echo htmlspecialchars($snum); ?>]" value="1" <?php echo $is_p; ?> required>
                                                            Present
                                                        </label>
                                                        <label class="radio-attendance-opt absent">
                                                            <input type="radio" class="att-radio-a" name="attendance[<?php echo htmlspecialchars($snum); ?>]" value="0" <?php echo $is_a; ?> required>
                                                            Absent
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                                <button type="submit" id="btnSubmitPastAtt" class="btn-submit-main" style="padding: 12px 28px; font-size: 1rem;">
                                    <i class="fa-solid fa-check-double"></i> Save Attendance for <?php echo htmlspecialchars($selected_past_date); ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--set-text-muted);">
                            <i class="fa-solid fa-users-slash" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 12px; display: block;"></i>
                            <h4>No active students found</h4>
                            <p>No active students are currently enrolled in this course and batch.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

            <!-- =========================================================
                 TAB 3: WHATSAPP GATEWAY & NOTIFICATION SETTINGS
                 ========================================================= -->
            <?php if ($tab === 'whatsapp'): ?>

                <!-- Row 1: Gateway Credentials & Message Templates Form -->
                <form action="whatsapp_settings_process.php" method="POST">
                    <input type="hidden" name="action" value="save_settings">

                    <!-- Card 1: API Configuration -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3><i class="fa-brands fa-whatsapp" style="color: #22c55e;"></i> WhatsApp API Gateway Configuration</h3>
                                <p>Connect your WhatsApp account gateway (UltraMsg, Fonnte, Wablas, or Custom HTTP API) to dispatch instant attendance alerts.</p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="font-weight: 600; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="is_enabled" value="1" <?php echo !empty($wa_settings['is_enabled']) ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #22c55e;">
                                    <span>Enable WhatsApp Notifications</span>
                                </label>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-top: 10px;">
                            
                            <!-- Provider Select -->
                            <div class="filter-item">
                                <label>Gateway Provider</label>
                                <select name="gateway_provider" id="wa_gateway_provider" onchange="toggleWaProviderFields()" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);">
                                    <option value="ultramsg" <?php echo (($wa_settings['gateway_provider'] ?? '') === 'ultramsg') ? 'selected' : ''; ?>>UltraMsg (Recommended • Official HTTP API)</option>
                                    <option value="fonnte" <?php echo (($wa_settings['gateway_provider'] ?? '') === 'fonnte') ? 'selected' : ''; ?>>Fonnte</option>
                                    <option value="wablas" <?php echo (($wa_settings['gateway_provider'] ?? '') === 'wablas') ? 'selected' : ''; ?>>Wablas</option>
                                    <option value="custom_webhook" <?php echo (($wa_settings['gateway_provider'] ?? '') === 'custom_webhook') ? 'selected' : ''; ?>>Custom HTTP API / Webhook</option>
                                </select>
                            </div>

                            <!-- Instance ID / Account SID -->
                            <div class="filter-item" id="field_instance_id">
                                <label>Instance ID / Account ID</label>
                                <input type="text" name="instance_id" value="<?php echo htmlspecialchars($wa_settings['instance_id'] ?? ''); ?>" placeholder="e.g. instance123456" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);" autocomplete="off">
                            </div>

                            <!-- API Token / Key -->
                            <div class="filter-item">
                                <label>API Token / Secret Key</label>
                                <input type="password" name="api_token" id="wa_api_token" value="<?php echo htmlspecialchars($wa_settings['api_token'] ?? ''); ?>" placeholder="Enter API Token / Auth Key" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);" autocomplete="off">
                            </div>

                            <!-- API URL (Optional/Custom) -->
                            <div class="filter-item" id="field_api_url">
                                <label>API Endpoint URL</label>
                                <input type="url" name="api_url" value="<?php echo htmlspecialchars($wa_settings['api_url'] ?? 'https://api.ultramsg.com'); ?>" placeholder="https://api.ultramsg.com" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);">
                            </div>

                            <!-- Sender Phone -->
                            <div class="filter-item">
                                <label>Sender WhatsApp Number (Optional)</label>
                                <input type="text" name="sender_phone" value="<?php echo htmlspecialchars($wa_settings['sender_phone'] ?? ''); ?>" placeholder="e.g. 94771234567" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);">
                            </div>
                        </div>

                        <!-- Guide Notice Box -->
                        <div style="background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 12px 16px; margin-top: 18px; font-size: 0.88rem; color: var(--set-text-muted);">
                            <strong style="color: #2563eb;"><i class="fa-solid fa-circle-info"></i> How to connect:</strong>
                            Create a free account on <a href="https://ultramsg.com" target="_blank" style="color: #2563eb; font-weight: 600; text-decoration: underline;">UltraMsg.com</a> (or your preferred gateway), scan the QR code to pair your WhatsApp number, and paste your <strong>Instance ID</strong> and <strong>Token</strong> above.
                        </div>
                    </div>

                    <!-- Card 2: Message Templates -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3><i class="fa-solid fa-message" style="color: #2563eb;"></i> Automated Notification Templates</h3>
                                <p>Customize messages dispatched to student phone numbers. Click any dynamic placeholder tag to insert it into the message.</p>
                            </div>
                        </div>

                        <!-- 1. Present Notification Template -->
                        <div style="margin-bottom: 20px;">
                            <label style="font-size: 0.88rem; font-weight: 700; color: var(--set-text-main); display: block; margin-bottom: 6px;">
                                <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Present Attendance Notification Template
                            </label>
                            <div style="margin-bottom: 8px; display: flex; gap: 6px; flex-wrap: wrap;">
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{student_name}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {student_name}</span>
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{student_id}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {student_id}</span>
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{course_name}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {course_name}</span>
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{batch_name}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {batch_name}</span>
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{date}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {date}</span>
                                <span onclick="insertTemplateTag('txt_present_tmpl', '{time}')" style="cursor: pointer; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {time}</span>
                            </div>
                            <textarea id="txt_present_tmpl" name="present_template" rows="3" class="modal-input" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main); font-family: inherit; font-size: 0.92rem;"><?php echo htmlspecialchars($wa_settings['present_template'] ?? ''); ?></textarea>
                        </div>

                        <!-- 2. Absent Notification Template -->
                        <div style="margin-bottom: 20px;">
                            <label style="font-size: 0.88rem; font-weight: 700; color: var(--set-text-main); display: block; margin-bottom: 6px;">
                                <i class="fa-solid fa-circle-xmark" style="color: #ef4444;"></i> Absent Notification Template (Dispatched when finishing attendance)
                            </label>
                            <div style="margin-bottom: 8px; display: flex; gap: 6px; flex-wrap: wrap;">
                                <span onclick="insertTemplateTag('txt_absent_tmpl', '{student_name}')" style="cursor: pointer; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {student_name}</span>
                                <span onclick="insertTemplateTag('txt_absent_tmpl', '{student_id}')" style="cursor: pointer; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {student_id}</span>
                                <span onclick="insertTemplateTag('txt_absent_tmpl', '{course_name}')" style="cursor: pointer; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {course_name}</span>
                                <span onclick="insertTemplateTag('txt_absent_tmpl', '{batch_name}')" style="cursor: pointer; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {batch_name}</span>
                                <span onclick="insertTemplateTag('txt_absent_tmpl', '{date}')" style="cursor: pointer; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; font-weight: 600;">+ {date}</span>
                            </div>
                            <textarea id="txt_absent_tmpl" name="absent_template" rows="3" class="modal-input" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main); font-family: inherit; font-size: 0.92rem;"><?php echo htmlspecialchars($wa_settings['absent_template'] ?? ''); ?></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
                            <button type="submit" class="btn-submit-main" style="padding: 12px 28px; font-size: 1rem;">
                                <i class="fa-solid fa-floppy-disk"></i> Save WhatsApp Configuration
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Card 3: Live Test Message Tool -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div>
                            <h3><i class="fa-solid fa-paper-plane" style="color: #2563eb;"></i> Test WhatsApp Connection</h3>
                            <p>Send a quick test message to verify your WhatsApp Gateway connection.</p>
                        </div>
                    </div>

                    <form action="whatsapp_settings_process.php" method="POST" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
                        <input type="hidden" name="action" value="test_message">

                        <div style="flex: 1; min-width: 200px;">
                            <label style="font-size: 0.85rem; font-weight: 600; color: var(--set-text-muted); display: block; margin-bottom: 6px;">Recipient Phone Number</label>
                            <input type="text" name="test_phone" placeholder="e.g. 0712345678 or 94712345678" required class="modal-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);">
                        </div>

                        <div style="flex: 2; min-width: 280px;">
                            <label style="font-size: 0.85rem; font-weight: 600; color: var(--set-text-muted); display: block; margin-bottom: 6px;">Test Message</label>
                            <input type="text" name="test_message" value="Hello! This is a test notification from NVTI Baddegama Student Management System." class="modal-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main);">
                        </div>

                        <button type="submit" class="btn-add" style="background: #2563eb; padding: 10px 22px; height: 42px;">
                            <i class="fa-solid fa-paper-plane"></i> Send Test Message
                        </button>
                    </form>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- =========================================================
     MODALS SECTION (Edit Holiday, Delete Holiday, Success Modal)
     ========================================================= -->

<!-- 1. EDIT HOLIDAY MODAL -->
<div class="custom-modal-overlay" id="editHolidayModal">
    <div class="custom-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="margin: 0; color: var(--set-text-main); font-size: 1.2rem;">
                <i class="fa-solid fa-pen-to-square" style="color: #2563eb; margin-right: 6px;"></i> Edit Holiday
            </h3>
            <button type="button" onclick="closeModal('editHolidayModal')" style="background: none; border: none; font-size: 1.5rem; color: var(--set-text-muted); cursor: pointer;">&times;</button>
        </div>
        <form action="holiday_process.php" method="POST">
            <input type="hidden" name="action" value="update_holiday">
            <input type="hidden" name="holiday_id" id="editHolidayId">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--set-text-muted); margin-bottom: 6px;">Holiday Title</label>
                <input type="text" name="holiday_name" id="editHolidayName" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main); font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 22px;">
                <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--set-text-muted); margin-bottom: 6px;">Date</label>
                <input type="date" name="holiday_date" id="editHolidayDate" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--set-card-border); background: var(--set-card-bg); color: var(--set-text-main); font-size: 0.95rem;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-add" style="background: #64748b;" onclick="closeModal('editHolidayModal')">Cancel</button>
                <button type="submit" class="btn-submit-main"><i class="fa-solid fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. DELETE HOLIDAY MODAL -->
<div class="custom-modal-overlay" id="deleteHolidayModal">
    <div class="custom-modal-box" style="text-align: center;">
        <div class="modal-icon-bubble bubble-danger">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 style="margin: 0 0 8px 0; color: var(--set-text-main); font-size: 1.25rem;">Delete Holiday?</h3>
        <p style="color: var(--set-text-muted); margin-bottom: 20px; font-size: 0.92rem;">
            Are you sure you want to delete <strong id="deleteHolidayTitle" style="color: var(--set-text-main);"></strong> on <strong id="deleteHolidayDateText" style="color: #ef4444;"></strong>?
        </p>
        <form action="holiday_process.php" method="POST">
            <input type="hidden" name="action" value="delete_holiday">
            <input type="hidden" name="holiday_id" id="deleteHolidayId">
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-add" style="background: #64748b; padding: 10px 20px;" onclick="closeModal('deleteHolidayModal')">Cancel</button>
                <button type="submit" class="btn-add" style="background: #dc2626; padding: 10px 22px;">Delete Now</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. PAST ATTENDANCE SUCCESS MODAL -->
<div class="custom-modal-overlay" id="pastAttSuccessModal">
    <div class="custom-modal-box" style="text-align: center;">
        <div class="modal-icon-bubble bubble-success">
            <i class="fa-solid fa-check"></i>
        </div>
        <h3 style="margin: 0 0 8px 0; color: var(--set-text-main); font-size: 1.3rem;">Attendance Successfully Saved!</h3>
        <p id="pastSuccessMsg" style="color: var(--set-text-muted); margin-bottom: 22px; font-size: 0.95rem;">
            Historical attendance records have been successfully stored in the database.
        </p>
        <div id="pastSuccessDetails" style="background: var(--set-table-stripe); padding: 14px; border-radius: 10px; border: 1px solid var(--set-card-border); margin-bottom: 22px; text-align: left; font-size: 0.88rem;">
        </div>
        <button type="button" class="btn-submit-main" style="width: 100%; justify-content: center;" onclick="closeModal('pastAttSuccessModal'); window.location.reload();">
            <i class="fa-solid fa-circle-check"></i> Continue
        </button>
    </div>
</div>

<!-- =========================================================
     JAVASCRIPT LOGIC
     ========================================================= -->
<script>
// Batches and Holidays lookup maps in JS
const batchesMap = <?php echo json_encode($batches_by_course); ?>;
const allHolidaysMap = <?php echo json_encode($all_holidays_map); ?>;

// Live checking for Weekend / Holiday when selecting past date
function checkPastDateLiveAlert(dateVal) {
    const alertBox = document.getElementById('dateLiveAlertBox');
    if (!alertBox || !dateVal) return;

    alertBox.innerHTML = '';

    // Check Weekend
    const parts = dateVal.split('-');
    if (parts.length === 3) {
        const d = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
        const dayIdx = d.getDay(); // 0 = Sunday, 6 = Saturday
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayName = dayNames[dayIdx];

        let badges = [];

        // Check if Holiday
        if (allHolidaysMap[dateVal]) {
            badges.push(`
                <span style="display: inline-flex; align-items: center; gap: 5px; color: #854d0e; background: #fef08a; padding: 4px 9px; border-radius: 6px; font-weight: 600; border: 1px solid #eab308;">
                    <i class="fa-solid fa-umbrella-beach"></i> Holiday: ${allHolidaysMap[dateVal]}
                </span>
            `);
        }

        // Check if Saturday or Sunday
        if (dayIdx === 0 || dayIdx === 6) {
            badges.push(`
                <span style="display: inline-flex; align-items: center; gap: 5px; color: #1e40af; background: #dbeafe; padding: 4px 9px; border-radius: 6px; font-weight: 600; border: 1px solid #93c5fd;">
                    <i class="fa-solid fa-calendar-week"></i> ${dayName} (Weekend)
                </span>
            `);
        }

        if (badges.length > 0) {
            alertBox.innerHTML = badges.join(' ');
        }
    }
}

// Check on initial page load
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('pastDateInput');
    if (dateInput && dateInput.value) {
        checkPastDateLiveAlert(dateInput.value);
    }
});

// Dynamic course change handler
function onCourseChanged(courseId) {
    const batchSelect = document.getElementById('pastBatchSelect');
    if (!batchSelect) return;

    batchSelect.innerHTML = '';
    const batches = batchesMap[courseId] || [];

    if (batches.length > 0) {
        batches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.batch_id;
            opt.textContent = `${b.batch_name} (${b.status})`;
            batchSelect.appendChild(opt);
        });
    } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No batches found for this course';
        batchSelect.appendChild(opt);
    }
}

// -------------------------------------------------------------
// DYNAMIC MULTI-ROW HOLIDAY INPUT LOGIC
// -------------------------------------------------------------
function addHolidayRow() {
    const container = document.getElementById('holidayRowsContainer');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'holiday-row-item';
    row.innerHTML = `
        <div class="field-name">
            <input type="text" name="holiday_name[]" placeholder="Holiday Title (e.g. Christmas Day / Special Holiday)" required autocomplete="off">
        </div>
        <div class="field-date">
            <input type="date" name="holiday_date[]" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <button type="button" class="btn-remove-row" onclick="removeHolidayRow(this)" title="Remove Row">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeHolidayRow(btn) {
    const container = document.getElementById('holidayRowsContainer');
    const rows = container.querySelectorAll('.holiday-row-item');
    if (rows.length > 1) {
        btn.closest('.holiday-row-item').remove();
    } else {
        // If only 1 row left, just clear values
        const inputs = rows[0].querySelectorAll('input');
        inputs.forEach(i => i.value = '');
    }
}

// -------------------------------------------------------------
// MODAL CONTROLS
// -------------------------------------------------------------
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
}

function openEditHolidayModal(id, name, date) {
    document.getElementById('editHolidayId').value = id;
    document.getElementById('editHolidayName').value = name;
    document.getElementById('editHolidayDate').value = date;
    document.getElementById('editHolidayModal').style.display = 'flex';
}

function openDeleteHolidayModal(id, name, date) {
    document.getElementById('deleteHolidayId').value = id;
    document.getElementById('deleteHolidayTitle').textContent = name;
    document.getElementById('deleteHolidayDateText').textContent = date;
    document.getElementById('deleteHolidayModal').style.display = 'flex';
}

// -------------------------------------------------------------
// PAST ATTENDANCE MARK ALL (PRESENT / ABSENT)
// -------------------------------------------------------------
function markAllAttendance(status) {
    if (status === 1) {
        document.querySelectorAll('.att-radio-p').forEach(r => r.checked = true);
    } else {
        document.querySelectorAll('.att-radio-a').forEach(r => r.checked = true);
    }
}

// -------------------------------------------------------------
// SUBMIT PAST ATTENDANCE VIA AJAX & SHOW SUCCESS MODAL
// -------------------------------------------------------------
function submitPastAttendanceAjax(e) {
    e.preventDefault();
    const form = document.getElementById('pastAttendanceForm');
    const submitBtn = document.getElementById('btnSubmitPastAtt');
    
    if (!form) return;

    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
    }

    const formData = new FormData(form);

    fetch('past_attendance_process.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }

        if (data.success) {
            document.getElementById('pastSuccessMsg').textContent = data.message;
            document.getElementById('pastSuccessDetails').innerHTML = `
                <div><strong>Date:</strong> ${data.date}</div>
                <div><strong>Total Students:</strong> ${data.total}</div>
                <div style="color: #10b981;"><strong>Present:</strong> ${data.present}</div>
                <div style="color: #ef4444;"><strong>Absent:</strong> ${data.absent}</div>
            `;
            document.getElementById('pastAttSuccessModal').style.display = 'flex';
        } else {
            alert(data.message || 'Failed to save attendance.');
        }
    })
    .catch(err => {
        console.error("Attendance submission error:", err);
        if (submitBtn) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }
        // Fallback to normal form submit
        form.removeAttribute('onsubmit');
        form.submit();
    });
}

// WhatsApp Template Helper
function insertTemplateTag(textareaId, tag) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const before = text.substring(0, start);
    const after = text.substring(end, text.length);
    
    textarea.value = before + tag + after;
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
}

function toggleWaProviderFields() {
    const prov = document.getElementById('wa_gateway_provider').value;
    const instField = document.getElementById('field_instance_id');
    const urlField = document.getElementById('field_api_url');
    
    if (prov === 'ultramsg') {
        if (instField) instField.style.display = 'block';
    } else if (prov === 'fonnte') {
        if (instField) instField.style.display = 'none';
    } else {
        if (instField) instField.style.display = 'block';
    }
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('custom-modal-overlay')) {
        e.target.style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
