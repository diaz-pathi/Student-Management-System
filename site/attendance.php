<?php
session_start();
require_once('../lib/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$today_date = date('l, M d, Y');

$is_admin = ($role === 'Admin' || $role === 'SuperAdmin' || $role === 'Super Admin' || ($role !== 'Instructor' && $role !== 'Monitor'));

// ==========================================
// 📊 1. DATA CALCULATION FOR TOP CARDS
// ==========================================

// Card 1: Total Attendance & Percentage (Active Students)
$query_tot = "SELECT COUNT(*) as tot FROM students WHERE (status = 'Not Pass Out' OR status = 'Active' OR status IS NULL)";
$total_students = mysqli_fetch_assoc(mysqli_query($conn, $query_tot))['tot'] ?? 0;

$query_pres = "SELECT COUNT(DISTINCT sa.student_number) as p 
               FROM student_attendance sa 
               JOIN students s ON sa.student_number = s.st_custom_id 
               WHERE (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL) AND DATE(sa.date) = CURDATE() AND sa.absent_present = 1";
$today_present = mysqli_fetch_assoc(mysqli_query($conn, $query_pres))['p'] ?? 0;

$today_absent = ($total_students > $today_present) ? ($total_students - $today_present) : 0;
$total_percentage = ($total_students > 0) ? round(($today_present / $total_students) * 100, 1) : 0;
$total_absent_percentage = ($total_students > 0) ? round(($today_absent / $total_students) * 100, 1) : 0;

// ==========================================
// 📚 COURSE WISE ATTENDANCE BREAKDOWN
// ==========================================
$course_stats = [];
$q_courses = "SELECT id, course_id, course_name, course_duration, instructor_name FROM courses ORDER BY id ASC";
$r_courses = mysqli_query($conn, $q_courses);

if ($r_courses) {
    while($c = mysqli_fetch_assoc($r_courses)) {
        $cid = $c['course_id'];
        
        // Total active students in this course
        $q_ctot = "SELECT COUNT(*) as c_tot FROM students WHERE course_id = '$cid' AND (status = 'Not Pass Out' OR status = 'Active' OR status IS NULL)";
        $c_tot = mysqli_fetch_assoc(mysqli_query($conn, $q_ctot))['c_tot'] ?? 0;
        
        // Present in this course today
        $q_c_pres = "SELECT COUNT(DISTINCT sa.student_number) as c_p 
                     FROM student_attendance sa 
                     JOIN students s ON sa.student_number = s.st_custom_id 
                     WHERE s.course_id = '$cid' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL) AND DATE(sa.date) = CURDATE() AND sa.absent_present = 1";
        $c_pres = mysqli_fetch_assoc(mysqli_query($conn, $q_c_pres))['c_p'] ?? 0;
        
        // Absent count
        $c_abs = ($c_tot >= $c_pres) ? ($c_tot - $c_pres) : 0;
        
        // Percentages
        $c_p_pct = ($c_tot > 0) ? round(($c_pres / $c_tot) * 100, 1) : 0;
        $c_a_pct = ($c_tot > 0) ? round(($c_abs / $c_tot) * 100, 1) : 0;
        
        $course_stats[] = [
            'id' => $c['id'],
            'cid' => $cid,
            'name' => $c['course_name'],
            'duration' => $c['course_duration'] ?? 'N/A',
            'instructor' => $c['instructor_name'] ?? 'Not Assigned',
            'tot' => (int)$c_tot,
            'p' => (int)$c_pres,
            'ab' => (int)$c_abs,
            'p_pct' => $c_p_pct,
            'a_pct' => $c_a_pct
        ];
    }
}

// Find Most and Least Attended (Cards 2 & 3)
$most_attended = null;
$least_attended = null;
$max_pct = -1;
$min_pct = 101; 

foreach($course_stats as $stat) {
    if ($stat['tot'] > 0) {
        if ($stat['p_pct'] > $max_pct) {
            $max_pct = $stat['p_pct'];
            $most_attended = $stat;
        }
        if ($stat['p_pct'] <= 50 && $stat['p_pct'] < $min_pct) {
            $min_pct = $stat['p_pct'];
            $least_attended = $stat;
        }
    }
}

// ==========================================
// 🧑‍🏫 2. DATA FOR INSTRUCTOR VIEW
// ==========================================
$instructor_students = [];
$inst_active_batch = '';
$inst_course_name = '';
$inst_tot = 0;
$inst_p = 0;
$inst_a = 0;

if ($role == 'Instructor') {
    $q_inst = "SELECT u.course_id, c.course_name FROM users u LEFT JOIN courses c ON u.course_id = c.course_id WHERE u.id = '$user_id'";
    $inst_res = mysqli_fetch_assoc(mysqli_query($conn, $q_inst));
    $inst_course_id = $inst_res['course_id'] ?? '';
    $inst_course_name = $inst_res['course_name'] ?? '';
    
    if ($inst_course_id) {
        $bq = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE course_id = '$inst_course_id' AND (status = 'Not Pass Out' OR status = 'Active') LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $bres = mysqli_fetch_assoc($bq);
            $inst_active_batch = $bres['batch_id'];
            $inst_batch_name = $bres['batch_name'];
        }

        if ($inst_active_batch) {
            $q_st = "SELECT s.st_custom_id, s.name, s.image, sa.absent_present 
                     FROM students s 
                     LEFT JOIN student_attendance sa ON s.st_custom_id = sa.student_number AND DATE(sa.date) = CURDATE()
                     WHERE s.course_id = '$inst_course_id' AND s.batch_id = '$inst_active_batch' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL)";
            $r_st = mysqli_query($conn, $q_st);
            while($st = mysqli_fetch_assoc($r_st)) {
                $instructor_students[] = $st;
                $inst_tot++;
                if ($st['absent_present'] === '1') $inst_p++;
                elseif ($st['absent_present'] === '0') $inst_a++;
            }
        }
    }
}

// ==========================================
// 📋 3. DATA FOR MONITOR VIEW
// ==========================================
$monitor_students = [];
$mon_active_batch = '';
$mon_batch_name = '';
$mon_course_name = '';
$mon_tot = 0; $mon_p = 0; $mon_a = 0;

if ($role == 'Monitor') {
    $q_mon = "SELECT u.course_id, c.course_name FROM users u LEFT JOIN courses c ON u.course_id = c.course_id WHERE u.id = '$user_id'";
    $mon_res = mysqli_fetch_assoc(mysqli_query($conn, $q_mon));
    $mon_course_id = $mon_res['course_id'] ?? '';
    $mon_course_name = $mon_res['course_name'] ?? '';

    if ($mon_course_id) {
        $mon_custom_id = $_SESSION['custom_id'] ?? '';
        if (empty($mon_custom_id)) {
            $q_cid = "SELECT custom_id FROM users WHERE id = '$user_id'";
            $cid_res = mysqli_fetch_assoc(mysqli_query($conn, $q_cid));
            $mon_custom_id = $cid_res['custom_id'] ?? '';
        }

        $mon_batch_id = '';
        if ($mon_custom_id) {
            $bst_q = mysqli_query($conn, "SELECT batch_id FROM students WHERE st_custom_id = '$mon_custom_id' LIMIT 1");
            if ($bst_q && mysqli_num_rows($bst_q) > 0) {
                $mon_batch_id = mysqli_fetch_assoc($bst_q)['batch_id'];
            }
        }

        if (empty($mon_batch_id)) {
            $bq2 = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE course_id = '$mon_course_id' AND (status = 'Not Pass Out' OR status = 'Active') LIMIT 1");
            if ($bq2 && mysqli_num_rows($bq2) > 0) {
                $br = mysqli_fetch_assoc($bq2);
                $mon_batch_id = $br['batch_id'];
                $mon_batch_name = $br['batch_name'];
            }
        } else {
            $bn_q = mysqli_query($conn, "SELECT batch_name FROM batches WHERE batch_id = '$mon_batch_id' LIMIT 1");
            if ($bn_q && mysqli_num_rows($bn_q) > 0) {
                $mon_batch_name = mysqli_fetch_assoc($bn_q)['batch_name'];
            }
        }

        $mon_active_batch = $mon_batch_id;

        if ($mon_active_batch) {
            $q_mst = "SELECT s.st_custom_id, s.name, s.image, sa.absent_present 
                      FROM students s 
                      LEFT JOIN student_attendance sa ON s.st_custom_id = sa.student_number AND DATE(sa.date) = CURDATE()
                      WHERE s.course_id = '$mon_course_id' AND s.batch_id = '$mon_active_batch' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL)";
            $r_mst = mysqli_query($conn, $q_mst);
            while($mst = mysqli_fetch_assoc($r_mst)) {
                $monitor_students[] = $mst;
                $mon_tot++;
                if ($mst['absent_present'] === '1') $mon_p++;
                elseif ($mst['absent_present'] === '0') $mon_a++;
            }
        }
    }
}

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            
            <!-- Date Display -->
            <div class="att-date-wrapper">
                <span class="att-date-badge"><i class="fa-solid fa-calendar-day"></i> <?php echo $today_date; ?></span>
            </div>

            <?php if ($is_admin): ?>
            <!-- Top 3 Summary Cards -->
            <div class="att-top-grid">
                
                <!-- Card 1: All Courses Summary -->
                <div class="att-summary-card">
                    <h4><i class="fa-solid fa-users-rays"></i> Today Total Attendance</h4>
                    <h2><?php echo $today_present; ?> / <?php echo $total_students; ?></h2>
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 8px;">
                        <span class="att-pct green-pct"><i class="fa-solid fa-user-check"></i> <?php echo $total_percentage; ?>% Present</span>
                        <span class="att-pct red-pct"><i class="fa-solid fa-user-xmark"></i> <?php echo $total_absent_percentage; ?>% Absent</span>
                    </div>
                </div>

                <!-- Card 2: Most Attended -->
                <div class="att-summary-card">
                    <h4><i class="fa-solid fa-arrow-trend-up"></i> Most Attended Course</h4>
                    <?php if($most_attended): ?>
                        <h3><?php echo htmlspecialchars($most_attended['name']); ?></h3>
                        <p><?php echo $most_attended['p']; ?> / <?php echo $most_attended['tot']; ?> Students</p>
                        <span class="att-pct green-pct"><?php echo $most_attended['p_pct']; ?>% Present</span>
                    <?php else: ?>
                        <p style="color: #64748b; margin-top: 15px;">No attendance recorded yet today</p>
                    <?php endif; ?>
                </div>

                <!-- Card 3: Least Attended (<= 50%) -->
                <div class="att-summary-card">
                    <h4><i class="fa-solid fa-arrow-trend-down"></i> Least Attended Course(&le; 50%)</h4>
                    <?php if($least_attended): ?>
                        <h3><?php echo htmlspecialchars($least_attended['name']); ?></h3>
                        <p><?php echo $least_attended['p']; ?> / <?php echo $least_attended['tot']; ?> Students</p>
                        <span class="att-pct red-pct"><?php echo $least_attended['p_pct']; ?>% Present</span>
                    <?php else: ?>
                        <h3 class="good-news"><i class="fa-solid fa-champagne-glasses"></i> All courses &gt; 50%!</h3>
                    <?php endif; ?>
                </div>

            </div>
            <?php endif; ?>

            <!-- Main Container -->
            <div class="att-main-box">
                
                <?php if($is_admin): ?>
                    <!-- ADMIN VIEW: Active Courses Attendance Details -->
                    <div class="admin-att-header">
                        <div class="admin-att-title-group">
                            <h3 class="box-title" style="margin-bottom: 4px; border-bottom: none; padding-bottom: 0;">
                                <i class="fa-solid fa-layer-group" style="color: #2563eb;"></i> Active Courses Attendance Summary
                            </h3>
                            <p class="admin-att-subtitle">Real-time attendance breakdown and percentages for each course today</p>
                        </div>
                        <div class="admin-att-controls">
                            <div class="att-search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="courseSearchInput" placeholder="Filter courses..." onkeyup="filterCoursesList()">
                            </div>
                            <span class="att-total-badge"><i class="fa-solid fa-graduation-cap"></i> <?php echo count($course_stats); ?> Courses</span>
                        </div>
                    </div>
                    
                    <div class="course-attendance-grid" id="courseGridContainer">
                        <?php if(!empty($course_stats)): ?>
                            <?php foreach($course_stats as $cs): ?>
                                <div class="course-att-card" 
                                     data-course-id="<?php echo htmlspecialchars(strtolower($cs['cid'])); ?>" 
                                     data-course-name="<?php echo htmlspecialchars(strtolower($cs['name'])); ?>"
                                     data-instructor="<?php echo htmlspecialchars(strtolower($cs['instructor'])); ?>"
                                     onclick="openCourseAttendanceModal('<?php echo htmlspecialchars($cs['cid']); ?>')">
                                    
                                    <!-- Card Header -->
                                    <div class="c-card-header">
                                        <div class="c-icon-badge">
                                            <i class="fa-solid fa-book-open-reader"></i>
                                        </div>
                                        <div class="c-info-block">
                                            <div class="c-id-tag"><?php echo htmlspecialchars($cs['cid']); ?></div>
                                            <h4 class="c-title" title="<?php echo htmlspecialchars($cs['name']); ?>"><?php echo htmlspecialchars($cs['name']); ?></h4>
                                        </div>
                                    </div>

                                    <!-- Course Metadata Badges -->
                                    <div class="c-meta-row">
                                        <span class="c-meta-item" title="Instructor"><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($cs['instructor']); ?></span>
                                        <span class="c-meta-item" title="Duration"><i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($cs['duration']); ?></span>
                                    </div>

                                    <!-- 3 Stat Blocks (Total, Present, Absent) -->
                                    <div class="c-stats-triad">
                                        <!-- Total Students -->
                                        <div class="stat-pill stat-tot">
                                            <div class="stat-pill-label"><i class="fa-solid fa-users"></i> Total</div>
                                            <div class="stat-pill-val"><?php echo $cs['tot']; ?></div>
                                            <div class="stat-pill-sub">Enrolled</div>
                                        </div>

                                        <!-- Present Students & Percentage -->
                                        <div class="stat-pill stat-pres">
                                            <div class="stat-pill-label"><i class="fa-solid fa-user-check"></i> Present</div>
                                            <div class="stat-pill-val"><?php echo $cs['p']; ?></div>
                                            <div class="stat-pill-pct"><?php echo $cs['p_pct']; ?>%</div>
                                        </div>

                                        <!-- Absent Students & Percentage -->
                                        <div class="stat-pill stat-abs">
                                            <div class="stat-pill-label"><i class="fa-solid fa-user-xmark"></i> Absent</div>
                                            <div class="stat-pill-val"><?php echo $cs['ab']; ?></div>
                                            <div class="stat-pill-pct"><?php echo $cs['a_pct']; ?>%</div>
                                        </div>
                                    </div>

                                    <!-- Visual Attendance Ratio Bar -->
                                    <div class="c-progress-wrapper">
                                        <div class="c-progress-bar">
                                            <?php if ($cs['tot'] > 0): ?>
                                                <div class="c-prog-fill c-prog-pres" style="width: <?php echo $cs['p_pct']; ?>%;" title="Present: <?php echo $cs['p_pct']; ?>%"></div>
                                                <div class="c-prog-fill c-prog-abs" style="width: <?php echo $cs['a_pct']; ?>%;" title="Absent: <?php echo $cs['a_pct']; ?>%"></div>
                                            <?php else: ?>
                                                <div class="c-prog-fill c-prog-empty" style="width: 100%;" title="No active students"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="c-progress-labels">
                                            <span class="lbl-pres"><span class="dot dot-green"></span> Present: <strong><?php echo $cs['p_pct']; ?>%</strong></span>
                                            <span class="lbl-abs"><span class="dot dot-red"></span> Absent: <strong><?php echo $cs['a_pct']; ?>%</strong></span>
                                        </div>
                                    </div>

                                    <!-- Card Action Footer -->
                                    <div class="c-card-footer">
                                        <button type="button" class="btn-card-action" onclick="event.stopPropagation(); openCourseAttendanceModal('<?php echo htmlspecialchars($cs['cid']); ?>')">
                                            <span><i class="fa-solid fa-list-check"></i> View Student List</span>
                                            <i class="fa-solid fa-chevron-right c-arrow-icon"></i>
                                        </button>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-box">
                                <i class="fa-solid fa-folder-open"></i>
                                <h4>No Courses Found</h4>
                                <p>There are no courses currently created in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif($role == 'Instructor'): ?>
                    <!-- INSTRUCTOR VIEW: Student List -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 class="box-title" style="margin: 0;">My Active Batch (<?php echo htmlspecialchars($inst_batch_name ?? 'None'); ?>)</h3>
                        <?php if ($inst_active_batch): ?>
                            <a href="mark_attendance.php" class="btn-add"><i class="fa-solid fa-pen-to-square"></i> Mark Attendance</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($inst_active_batch): ?>
                        <div class="inst-stats-mini" style="display: flex; gap: 15px; margin-bottom: 18px; flex-wrap: wrap;">
                            <div class="stat-card-total" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Total</h4>
                                <h2 style="margin:0;"><?php echo $inst_tot; ?></h2>
                            </div>
                            <div class="stat-card-present" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Present</h4>
                                <h2 style="margin:0;"><?php echo $inst_p; ?></h2>
                                <span class="att-pct green-pct"><?php echo ($inst_tot > 0) ? round(($inst_p/$inst_tot)*100,1) : 0; ?>%</span>
                            </div>
                            <div class="stat-card-absent" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Absent</h4>
                                <h2 style="margin:0;"><?php echo $inst_a; ?></h2>
                                <span class="att-pct red-pct"><?php echo ($inst_tot > 0) ? round(($inst_a/$inst_tot)*100,1) : 0; ?>%</span>
                            </div>
                        </div>

                        <div class="student-list-container">
                            <?php if(count($instructor_students) > 0): ?>
                                <?php foreach($instructor_students as $st): 
                                    $img_src = '';
                                    if (!empty($st['image'])) {
                                        $img_src = 'data:image/jpeg;base64,' . base64_encode($st['image']);
                                    }
                                ?>
                                    <div class="student-row">
                                        <div class="s-name" style="display: flex; align-items: center; gap: 12px;">
                                            <?php if ($img_src): ?>
                                                <img src="<?php echo $img_src; ?>" alt="Student Photo" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle-user" style="font-size: 34px; color: #94a3b8;"></i>
                                            <?php endif; ?>
                                            <div>
                                                <span style="font-weight: 600; display: block;"><?php echo htmlspecialchars($st['name']); ?></span>
                                                <span style="font-size: 0.8em; color: #64748b;"><?php echo htmlspecialchars($st['st_custom_id'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                        <div class="s-line"></div>
                                        <div class="s-status">
                                            <?php 
                                                if($st['absent_present'] === '1') {
                                                    echo '<span class="status-badge present"><i class="fa-solid fa-check"></i> Present</span>';
                                                } else if($st['absent_present'] === '0') {
                                                    echo '<span class="status-badge absent"><i class="fa-solid fa-xmark"></i> Absent</span>';
                                                } else {
                                                    echo '<span class="status-badge not-marked"><i class="fa-solid fa-clock"></i> Not Marked</span>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #64748b; padding: 20px;">No active students found in your batch.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 20px;">You don't have any active batch assigned.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- MONITOR VIEW -->
                <?php if ($role == 'Monitor'): ?>
                    <div class="instructor-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 18px;">
                        <div>
                            <h3 style="margin:0;"><?php echo htmlspecialchars($mon_course_name); ?></h3>
                            <p style="margin:4px 0 0 0; color: #64748b; font-size: 0.9em;">Batch: <strong><?php echo htmlspecialchars($mon_batch_name); ?></strong></p>
                        </div>
                        <a href="mark_attendance.php" class="btn-add" style="background: #2563eb;">
                            <i class="fa-solid fa-clipboard-check"></i> Mark Attendance
                        </a>
                    </div>

                    <?php if ($mon_active_batch): ?>
                        <div class="inst-stats-mini" style="display: flex; gap: 15px; margin-bottom: 18px; flex-wrap: wrap;">
                            <div class="stat-card-total" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Total</h4>
                                <h2 style="margin:0;"><?php echo $mon_tot; ?></h2>
                            </div>
                            <div class="stat-card-present" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Present</h4>
                                <h2 style="margin:0;"><?php echo $mon_p; ?></h2>
                                <span class="att-pct green-pct"><?php echo ($mon_tot > 0) ? round(($mon_p/$mon_tot)*100,1) : 0; ?>%</span>
                            </div>
                            <div class="stat-card-absent" style="flex:1; border-radius: 8px; padding: 14px; text-align: center; min-width: 100px;">
                                <h4 style="margin:0 0 6px; font-size: 0.85em;">Absent</h4>
                                <h2 style="margin:0;"><?php echo $mon_a; ?></h2>
                                <span class="att-pct red-pct"><?php echo ($mon_tot > 0) ? round(($mon_a/$mon_tot)*100,1) : 0; ?>%</span>
                            </div>
                        </div>

                        <div class="student-list-container">
                            <?php if(count($monitor_students) > 0): ?>
                                <?php foreach($monitor_students as $mst):
                                    $mimg = !empty($mst['image']) ? 'data:image/jpeg;base64,' . base64_encode($mst['image']) : '';
                                ?>
                                    <div class="student-row">
                                        <div class="s-name" style="display: flex; align-items: center; gap: 12px;">
                                            <?php if ($mimg): ?>
                                                <img src="<?php echo $mimg; ?>" alt="Photo" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle-user" style="font-size: 34px; color: #94a3b8;"></i>
                                            <?php endif; ?>
                                            <div>
                                                <span style="font-weight: 600; display: block;"><?php echo htmlspecialchars($mst['name']); ?></span>
                                                <span style="font-size: 0.8em; color: #64748b;"><?php echo htmlspecialchars($mst['st_custom_id'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                        <div class="s-line"></div>
                                        <div class="s-status">
                                            <?php
                                                if($mst['absent_present'] === '1') {
                                                    echo '<span class="status-badge present"><i class="fa-solid fa-check"></i> Present</span>';
                                                } else if($mst['absent_present'] === '0') {
                                                    echo '<span class="status-badge absent"><i class="fa-solid fa-xmark"></i> Absent</span>';
                                                } else {
                                                    echo '<span class="status-badge not-marked"><i class="fa-solid fa-clock"></i> Not Marked</span>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #64748b; padding: 20px;">No active students found in your batch.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 20px;">No active batch found for your course.</p>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<!-- Course Attendance Modals for Admin -->
<?php if ($is_admin): ?>
    <?php foreach($course_stats as $cs): 
        $cid = $cs['cid'];
        $q_st_c = "SELECT s.st_custom_id, s.name, s.image, sa.absent_present 
                   FROM students s 
                   LEFT JOIN student_attendance sa ON s.st_custom_id = sa.student_number AND DATE(sa.date) = CURDATE()
                   WHERE s.course_id = '$cid' AND (s.status = 'Not Pass Out' OR s.status = 'Active' OR s.status IS NULL)
                   ORDER BY s.name ASC";
        $r_st_c = mysqli_query($conn, $q_st_c);
    ?>
    <div id="modal_course_<?php echo htmlspecialchars($cid); ?>" class="admin-modal">
        <div class="admin-modal-content">
            
            <!-- Modal Header -->
            <div class="modal-head">
                <div class="modal-title-wrap">
                    <span class="modal-cid-badge"><?php echo htmlspecialchars($cs['cid']); ?></span>
                    <h3 class="modal-head-title"><?php echo htmlspecialchars($cs['name']); ?></h3>
                    <p class="modal-head-sub"><i class="fa-solid fa-calendar-day"></i> Today's Live Attendance</p>
                </div>
                <button type="button" class="modal-btn-close" onclick="closeCourseAttendanceModal('<?php echo htmlspecialchars($cid); ?>')" title="Close">&times;</button>
            </div>

            <!-- Modal Mini Stat Bar -->
            <div class="modal-stats-bar">
                <div class="m-stat m-stat-tot">
                    <span>Total Students</span>
                    <strong><?php echo $cs['tot']; ?></strong>
                </div>
                <div class="m-stat m-stat-pres">
                    <span>Present Today</span>
                    <strong><?php echo $cs['p']; ?> <small>(<?php echo $cs['p_pct']; ?>%)</small></strong>
                </div>
                <div class="m-stat m-stat-abs">
                    <span>Absent Today</span>
                    <strong><?php echo $cs['ab']; ?> <small>(<?php echo $cs['a_pct']; ?>%)</small></strong>
                </div>
            </div>

            <!-- Student Search Filter inside Modal -->
            <div class="modal-search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search student by name or ID..." onkeyup="filterModalStudents(this, '<?php echo htmlspecialchars($cid); ?>')">
            </div>

            <!-- Student List Container -->
            <div class="student-list-container modal-students-scroll" id="modal_st_list_<?php echo htmlspecialchars($cid); ?>">
                <?php if($r_st_c && mysqli_num_rows($r_st_c) > 0): ?>
                    <?php while($c_st = mysqli_fetch_assoc($r_st_c)): 
                        $c_img_src = '';
                        if (!empty($c_st['image'])) {
                            $c_img_src = 'data:image/jpeg;base64,' . base64_encode($c_st['image']);
                        }
                    ?>
                        <div class="student-row custom-student-row" data-st-name="<?php echo htmlspecialchars(strtolower($c_st['name'])); ?>" data-st-id="<?php echo htmlspecialchars(strtolower($c_st['st_custom_id'])); ?>">
                            <div class="s-name" style="display: flex; align-items: center; gap: 12px;">
                                <?php if ($c_img_src): ?>
                                    <img src="<?php echo $c_img_src; ?>" alt="Photo" class="modal-student-avatar">
                                <?php else: ?>
                                    <div class="modal-student-avatar-fallback">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="m-st-fullname"><?php echo htmlspecialchars($c_st['name']); ?></span>
                                    <span class="m-st-id-tag"><?php echo htmlspecialchars($c_st['st_custom_id']); ?></span>
                                </div>
                            </div>
                            <div class="s-status" style="margin-left: auto;">
                                <?php 
                                    if($c_st['absent_present'] === '1') {
                                        echo '<span class="status-badge present"><i class="fa-solid fa-check"></i> Present</span>';
                                    } else if($c_st['absent_present'] === '0') {
                                        echo '<span class="status-badge absent"><i class="fa-solid fa-xmark"></i> Absent</span>';
                                    } else {
                                        echo '<span class="status-badge not-marked"><i class="fa-solid fa-clock"></i> Not Marked</span>';
                                    }
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: #64748b;">
                        <i class="fa-solid fa-user-slash" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p style="margin: 0;">No active students found in this course.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer-row">
                <button type="button" class="btn-modal-dismiss" onclick="closeCourseAttendanceModal('<?php echo htmlspecialchars($cid); ?>')">Close</button>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- =========================================================
     ATTENDANCE RECORDED SUCCESS MODAL
     ========================================================= -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'marked'): ?>
<div id="successAttendanceModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center; border-radius: 16px; padding: 28px 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <div style="width: 68px; height: 68px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
            <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981;"></i>
        </div>
        <h3 style="margin: 0 0 8px 0; font-size: 1.35rem; font-weight: 700; color: #0f172a;">Attendance Recorded!</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.95rem; line-height: 1.5;">
            Student attendance records have been successfully saved to the system and absent notifications dispatched.
        </p>
        <div style="margin-top: 22px; display: flex; justify-content: center;">
            <button type="button" onclick="closeAttendanceSuccessModal()" style="padding: 10px 28px; border-radius: 8px; border: none; background: #10b981; color: white; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                <i class="fa-solid fa-check"></i> OK
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Close Attendance Recorded Success Modal
function closeAttendanceSuccessModal() {
    var modal = document.getElementById('successAttendanceModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Open Course Attendance Modal
function openCourseAttendanceModal(cid) {
    var modal = document.getElementById('modal_course_' + cid);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Close Course Attendance Modal
function closeCourseAttendanceModal(cid) {
    var modal = document.getElementById('modal_course_' + cid);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Filter Courses on the main page
function filterCoursesList() {
    var query = document.getElementById('courseSearchInput').value.toLowerCase();
    var cards = document.querySelectorAll('.course-att-card');
    
    cards.forEach(function(card) {
        var cid = card.getAttribute('data-course-id') || '';
        var cname = card.getAttribute('data-course-name') || '';
        var inst = card.getAttribute('data-instructor') || '';
        
        if (cid.indexOf(query) !== -1 || cname.indexOf(query) !== -1 || inst.indexOf(query) !== -1) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Filter Students inside the Modal
function filterModalStudents(input, cid) {
    var query = input.value.toLowerCase();
    var container = document.getElementById('modal_st_list_' + cid);
    if (!container) return;
    
    var rows = container.querySelectorAll('.custom-student-row');
    rows.forEach(function(row) {
        var stName = row.getAttribute('data-st-name') || '';
        var stId = row.getAttribute('data-st-id') || '';
        
        if (stName.indexOf(query) !== -1 || stId.indexOf(query) !== -1) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

// Close modals when clicking outside modal-content
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('admin-modal')) {
        if (event.target.id === 'successAttendanceModal') {
            closeAttendanceSuccessModal();
        } else {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
});

// Escape key to close modal
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        var openModals = document.querySelectorAll('.admin-modal');
        openModals.forEach(function(modal) {
            if (modal.style.display === 'flex') {
                if (modal.id === 'successAttendanceModal') {
                    closeAttendanceSuccessModal();
                } else {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
        });
    }
});
</script>

<?php include('../include/footer.php'); ?>