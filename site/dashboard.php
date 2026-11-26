<?php
session_start();
require_once('../lib/db.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ==========================================
// 📊 DYNAMIC DATA FETCHING 
// ==========================================

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Default variables
$total_students = 0;
$total_courses = 0;
$total_instructors = 0;
$total_admins = 0;
$today_students_present = 0;
$student_percentage = 0;
$active_batch_name = '';
$active_students_count = 0;
if ($role === 'Instructor') {
    // Get Instructor's course info
    $q_inst = "SELECT u.course_id, c.course_name FROM users u LEFT JOIN courses c ON u.course_id = c.course_id WHERE u.id = '$user_id'";
    $inst_res = mysqli_fetch_assoc(mysqli_query($conn, $q_inst));
    $inst_course_id = $inst_res['course_id'] ?? '';
    $inst_course_name = $inst_res['course_name'] ?? '';

    // Category 1: Current Active Batch info
    $active_batch = '';
    $active_batch_name = '';
    $active_batch_total_students = 0;
    $active_batch_active_students = 0;
    $active_batch_inactive_students = 0;
    $total_course_batches = 0;
    $total_course_students = 0;

    if ($inst_course_id) {
        $bq = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE course_id = '$inst_course_id' AND (status = 'Not Pass Out' OR status = 'Active') LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $bres = mysqli_fetch_assoc($bq);
            $active_batch = $bres['batch_id'];
            $active_batch_name = $bres['batch_name'];
        }

        if ($active_batch) {
            // Total students in active batch
            $active_batch_total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$inst_course_id' AND batch_id = '$active_batch'"))['count'] ?? 0;

            // Active status students in active batch
            $active_batch_active_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$inst_course_id' AND batch_id = '$active_batch' AND (status = 'Not Pass Out' OR status = 'Active' OR status IS NULL)"))['count'] ?? 0;

            // Inactive status students in active batch
            $active_batch_inactive_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$inst_course_id' AND batch_id = '$active_batch' AND status = 'Inactive'"))['count'] ?? 0;
            
            // Category 2: Today's Attendance
            $today_students_present = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT sa.student_number) as count 
                               FROM student_attendance sa 
                               JOIN students s ON sa.student_number = s.st_custom_id
                               WHERE s.course_id = '$inst_course_id' AND s.batch_id = '$active_batch' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL) AND DATE(sa.date) = CURDATE() AND sa.absent_present = 1"))['count'] ?? 0;
        }

        // Category 3: Total Batches & Students across all batches in this course
        $total_course_batches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM batches WHERE course_id = '$inst_course_id'"))['count'] ?? 0;
        $total_course_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$inst_course_id'"))['count'] ?? 0;
    }
    
    $student_percentage = ($active_batch_active_students > 0) ? round(($today_students_present / $active_batch_active_students) * 100, 1) : 0;
    $today_students_absent = ($active_batch_active_students > $today_students_present) ? ($active_batch_active_students - $today_students_present) : 0;
    $absent_percentage = ($active_batch_active_students > 0) ? round(($today_students_absent / $active_batch_active_students) * 100, 1) : 0;

} else {
    // ADMIN CALCS
    $total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students"))['count'] ?? 0;
    $total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM courses"))['count'] ?? 0;
    $total_instructors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'Instructor'"))['count'] ?? 0;
    $total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role IN ('Admin', 'Super Admin', 'SuperAdmin')"))['count'] ?? 0;
    
    $today_students_present = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_number) as count FROM student_attendance WHERE DATE(date) = CURDATE() AND absent_present = 1"))['count'] ?? 0;
    $student_percentage = ($total_students > 0) ? round(($today_students_present / $total_students) * 100, 1) : 0;
    $today_students_absent = ($total_students > $today_students_present) ? ($total_students - $today_students_present) : 0;
    $absent_percentage = ($total_students > 0) ? round(($today_students_absent / $total_students) * 100, 1) : 0;

    // Admin: Fetch all courses and their detailed stats for the table
    $courses_status_list = [];
    $courses_q = mysqli_query($conn, "SELECT course_id, course_name, instructor_name FROM courses ORDER BY course_name ASC");
    if ($courses_q && mysqli_num_rows($courses_q) > 0) {
        while ($c_row = mysqli_fetch_assoc($courses_q)) {
            $c_id = $c_row['course_id'];

            // 1. Find active batch for this course
            $active_b_id = '';
            $active_b_query = mysqli_query($conn, "SELECT batch_id FROM batches WHERE course_id = '$c_id' AND (status = 'Not Pass Out' OR status = 'Active') ORDER BY id DESC LIMIT 1");
            if ($active_b_query && mysqli_num_rows($active_b_query) > 0) {
                $active_b_id = mysqli_fetch_assoc($active_b_query)['batch_id'];
            }

            // 2. Active batch metrics & Today Attendance
            $active_b_total = 0;
            $active_b_active = 0;
            $active_b_inactive = 0;
            $today_present = 0;
            $today_absent = 0;
            $pres_pct = 0;
            $abs_pct = 0;

            if (!empty($active_b_id)) {
                $active_b_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$c_id' AND batch_id = '$active_b_id'"))['count'] ?? 0;
                $active_b_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$c_id' AND batch_id = '$active_b_id' AND (status = 'Not Pass Out' OR status = 'Active' OR status IS NULL)"))['count'] ?? 0;
                $active_b_inactive = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$c_id' AND batch_id = '$active_b_id' AND status = 'Inactive'"))['count'] ?? 0;

                // Today Attendance calculation for active batch
                $today_present = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT sa.student_number) as count 
                                   FROM student_attendance sa 
                                   JOIN students s ON sa.student_number = s.st_custom_id
                                   WHERE s.course_id = '$c_id' AND s.batch_id = '$active_b_id' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL) AND DATE(sa.date) = CURDATE() AND sa.absent_present = 1"))['count'] ?? 0;

                $pres_pct = ($active_b_active > 0) ? round(($today_present / $active_b_active) * 100, 1) : 0;
                $today_absent = ($active_b_active > $today_present) ? ($active_b_active - $today_present) : 0;
                $abs_pct = ($active_b_active > 0) ? round(($today_absent / $active_b_active) * 100, 1) : 0;
            }

            // 3. Course-wide overall batches and student metrics
            $tot_batches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM batches WHERE course_id = '$c_id'"))['count'] ?? 0;
            $tot_students_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$c_id'"))['count'] ?? 0;
            $passed_out_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$c_id' AND status = 'Passed Out'"))['count'] ?? 0;

            $courses_status_list[] = [
                'course_name' => $c_row['course_name'],
                'instructor_name' => $c_row['instructor_name'],
                'active_batch_total' => $active_b_total,
                'active_batch_active' => $active_b_active,
                'active_batch_inactive' => $active_b_inactive,
                'today_present' => $today_present,
                'pres_pct' => $pres_pct,
                'today_absent' => $today_absent,
                'abs_pct' => $abs_pct,
                'total_batches' => $tot_batches,
                'total_students_all' => $tot_students_all,
                'passed_out_students' => $passed_out_students,
            ];
        }
    }
}

// ==========================================

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <?php if ($role === 'Instructor'): ?>
                <!-- 🎓 Top Header: Course Name, Current Date & Time -->
                <div class="dashboard-banner">
                    <div>
                        <span class="banner-label">Assigned Course</span>
                        <h1 class="banner-title">
                            <i class="fa-solid fa-graduation-cap" style="color: #38bdf8;"></i>
                            <?php echo htmlspecialchars($inst_course_name ?: 'No Course Assigned'); ?>
                        </h1>
                        <span class="banner-subtitle">Course ID: <?php echo htmlspecialchars($inst_course_id ?: 'N/A'); ?></span>
                    </div>
                    <div class="date-time-box">
                        <div class="date-time-item">
                            <i class="fa-regular fa-calendar-days" style="font-size: 1.4em; color: #38bdf8;"></i>
                            <div>
                                <span class="banner-label" style="margin-bottom:0;">Today's Date</span>
                                <span class="date-time-value"><?php echo date('l, F j, Y'); ?></span>
                            </div>
                        </div>
                        <div class="date-time-item" style="border-left: 1px solid #ccc; padding-left: 15px;">
                            <i class="fa-regular fa-clock" style="font-size: 1.4em; color: #38bdf8;"></i>
                            <div>
                                <span class="banner-label" style="margin-bottom:0;">Current Time</span>
                                <span class="date-time-value" id="live-time"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 📂 Category 1: Current Active Batch Overview -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <i class="fa-solid fa-layer-group" style="color: #6366f1; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">Current Active Batch Overview</h3>
                    </div>
                    <div class="cards-grid" style="margin-top: 0;">
                        <!-- 1. Active Batch Name -->
                        <div class="card-item">
                            <i class="fa-solid fa-chalkboard" style="font-size: 26px; color: #8b5cf6;"></i>
                            <div>
                                <h3 style="font-size: 1.25em; word-break: break-word;"><?php echo htmlspecialchars($active_batch_name ?: 'None'); ?></h3>
                                <p>Active Batch Name</p>
                            </div>
                        </div>

                        <!-- 2. Total Students in Active Batch -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-group" style="font-size: 26px; color: #3b82f6;"></i>
                            <div>
                                <h3><?php echo $active_batch_total_students; ?></h3>
                                <p>Total Batch Students</p>
                            </div>
                        </div>

                        <!-- 3. Active Students in Active Batch -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-check" style="font-size: 26px; color: #10b981;"></i>
                            <div>
                                <h3><?php echo $active_batch_active_students; ?></h3>
                                <p>Active Students</p>
                            </div>
                        </div>

                        <!-- 4. Inactive Students in Active Batch -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-xmark" style="font-size: 26px; color: #ef4444;"></i>
                            <div>
                                <h3><?php echo $active_batch_inactive_students; ?></h3>
                                <p>Inactive Students</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 📊 Category 2: Today's Attendance Overview -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <i class="fa-solid fa-clipboard-user" style="color: #10b981; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">Today's Attendance Overview (<?php echo date('M d, Y'); ?>)</h3>
                    </div>
                    <div class="cards-grid" style="margin-top: 0;">
                        <!-- 1. Present Students Today -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-check" style="font-size: 26px; color: #10b981;"></i>
                            <div>
                                <h3><?php echo $today_students_present; ?> <span style="font-size: 14px; color: #10b981; font-weight: 600;">(<?php echo $student_percentage; ?>%)</span></h3>
                                <p>Present Students Today</p>
                            </div>
                        </div>

                        <!-- 2. Absent Students Today -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-xmark" style="font-size: 26px; color: #ef4444;"></i>
                            <div>
                                <h3><?php echo $today_students_absent; ?> <span style="font-size: 14px; color: #ef4444; font-weight: 600;">(<?php echo $absent_percentage; ?>%)</span></h3>
                                <p>Absent Students Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 📚 Category 3: Course & All Batches Summary -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <i class="fa-solid fa-book-open-reader" style="color: #2563eb; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">Overall Course & Batches Summary</h3>
                    </div>
                    <div class="cards-grid" style="margin-top: 0;">
                        <!-- 1. Total Batches in Course -->
                        <div class="card-item">
                            <i class="fa-solid fa-layer-group" style="font-size: 26px; color: #8b5cf6;"></i>
                            <div>
                                <h3><?php echo $total_course_batches; ?></h3>
                                <p>Total Batches</p>
                            </div>
                        </div>

                        <!-- 2. Total Students in All Batches -->
                        <div class="card-item">
                            <i class="fa-solid fa-users" style="font-size: 26px; color: #2563eb;"></i>
                            <div>
                                <h3><?php echo $total_course_students; ?></h3>
                                <p>Total Students (All Batches)</p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Admin Dashboard View -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
                    <h2 style="margin: 0; font-weight: 700;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h2>

                    <!-- 📅 Current Date & Time Widget (Right-aligned) -->
                    <div class="admin-date-time-widget">
                        <div class="date-time-item">
                            <i class="fa-solid fa-calendar-days date-time-icon"></i>
                            <div>
                                <span class="date-time-label">TODAY'S DATE</span>
                                <span class="date-time-value"><?php echo date('l, F j, Y'); ?></span>
                            </div>
                        </div>
                        <div class="date-time-divider"></div>
                        <div class="date-time-item">
                            <i class="fa-regular fa-clock date-time-icon"></i>
                            <div>
                                <span class="date-time-label">CURRENT TIME</span>
                                <span class="date-time-value" id="live-time-admin">--:--:-- --</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Category 2: System Overview Counts -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <i class="fa-solid fa-server" style="color: #6366f1; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">System Overview</h3>
                    </div>
                    <div class="cards-grid" style="margin-top: 0;">
                        <div class="card-item">
                            <i class="fa-solid fa-user-shield" style="font-size: 24px; color: #9333ea;"></i>
                            <div>
                                <h3><?php echo $total_admins; ?></h3>
                                <p>System Admins</p>
                            </div>
                        </div>
                        <div class="card-item">
                            <i class="fa-solid fa-book" style="font-size: 24px; color: #16a34a;"></i>
                            <div>
                                <h3><?php echo $total_courses; ?></h3>
                                <p>Total Courses</p>
                            </div>
                        </div>
                        <div class="card-item">
                            <i class="fa-solid fa-layer-group" style="font-size: 24px; color: #8b5cf6;"></i>
                            <div>
                                <h3><?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM batches"))['count'] ?? 0; ?></h3>
                                <p>Total Batches</p>
                            </div>
                        </div>
                        <div class="card-item">
                            <i class="fa-solid fa-chalkboard-user" style="font-size: 24px; color: #ea580c;"></i>
                            <div>
                                <h3><?php echo $total_instructors; ?></h3>
                                <p>Total Instructors</p>
                            </div>
                        </div>
                        <div class="card-item">
                            <i class="fa-solid fa-users" style="font-size: 24px; color: #2563eb;"></i>
                            <div>
                                <h3><?php echo $total_students; ?></h3>
                                <p>Total Students</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category 3: Today's Attendance Overview -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <i class="fa-solid fa-clipboard-user" style="color: #10b981; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">Today's Attendance Overview (<?php echo date('M d, Y'); ?>)</h3>
                    </div>
                    <div class="cards-grid" style="margin-top: 0;">
                        <!-- Present Students Today -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-check" style="font-size: 26px; color: #10b981;"></i>
                            <div>
                                <h3><?php echo $today_students_present; ?> <span style="font-size: 14px; color: #10b981; font-weight: 600;">(<?php echo $student_percentage; ?>%)</span></h3>
                                <p>Present Students Today</p>
                            </div>
                        </div>
                        <!-- Absent Students Today -->
                        <div class="card-item">
                            <i class="fa-solid fa-user-xmark" style="font-size: 26px; color: #ef4444;"></i>
                            <div>
                                <h3><?php echo $today_students_absent; ?> <span style="font-size: 14px; color: #ef4444; font-weight: 600;">(<?php echo $absent_percentage; ?>%)</span></h3>
                                <p>Absent Students Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category 4: System Courses Status -->
                <div class="table-container" style="margin-top: 0; margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <i class="fa-solid fa-list-check" style="color: #f59e0b; font-size: 1.15em;"></i>
                        <h3 style="margin: 0; font-size: 1.15em; font-weight: 600;">System Courses Status</h3>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Instructor Name</th>
                                <th style="text-align: center;" title="Total students in the active batch">Active Batch Total</th>
                                <th style="text-align: center;" title="Active students in the active batch">Active (Active Batch)</th>
                                <th style="text-align: center;" title="Inactive students in the active batch">Inactive (Active Batch)</th>
                                <th style="text-align: center;" title="Today's present students in active batch">Today Present</th>
                                <th style="text-align: center;" title="Today's absent students in active batch">Today Absent</th>
                                <th style="text-align: center;" title="Total batches created for this course">Total Batches</th>
                                <th style="text-align: center;" title="Total students across all batches">Total Students (All Batches)</th>
                                <th style="text-align: center;" title="Passed out students in this course">Passed Out Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($courses_status_list)): ?>
                                <?php foreach ($courses_status_list as $c_row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c_row['course_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c_row['instructor_name'] ?: 'Not Assigned'); ?></td>
                                        <td style="text-align: center;"><strong><?php echo (int)$c_row['active_batch_total']; ?></strong></td>
                                        <td style="text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo (int)$c_row['active_batch_active']; ?></span></td>
                                        <td style="text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo (int)$c_row['active_batch_inactive']; ?></span></td>
                                        <td style="text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo (int)$c_row['today_present']; ?> <span style="font-size: 0.88em;">(<?php echo $c_row['pres_pct']; ?>%)</span></span></td>
                                        <td style="text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo (int)$c_row['today_absent']; ?> <span style="font-size: 0.88em;">(<?php echo $c_row['abs_pct']; ?>%)</span></span></td>
                                        <td style="text-align: center;"><strong><?php echo (int)$c_row['total_batches']; ?></strong></td>
                                        <td style="text-align: center;"><strong><?php echo (int)$c_row['total_students_all']; ?></strong></td>
                                        <td style="text-align: center;"><span style="color: #8b5cf6; font-weight: 600;"><?php echo (int)$c_row['passed_out_students']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" style="text-align:center;">No courses available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        const liveTimeEl = document.getElementById('live-time');
        if (liveTimeEl) liveTimeEl.textContent = timeString;
        
        const liveTimeAdminEl = document.getElementById('live-time-admin');
        if (liveTimeAdminEl) liveTimeAdminEl.textContent = timeString;
    }
    
    // Update time immediately, then every second
    updateTime();
    setInterval(updateTime, 1000);
</script>

<?php include('../include/footer.php'); ?>