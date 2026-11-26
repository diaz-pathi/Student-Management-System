<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin' && $role !== 'Instructor') {
    header("Location: dashboard.php");
    exit();
}

// Create students table if not exists (based on ER diagram)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS students (st_id INT AUTO_INCREMENT PRIMARY KEY, st_custom_id VARCHAR(30), name VARCHAR(50), nic VARCHAR(12), gender VARCHAR(6), address VARCHAR(100), email VARCHAR(100), phone VARCHAR(10), image LONGBLOB, course_id VARCHAR(30), batch_id VARCHAR(30), status VARCHAR(30) DEFAULT 'Active', role VARCHAR(20) DEFAULT 'Student')");

// Student status toggle 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $student_id = intval($_POST['student_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $update_q = "UPDATE students SET status = '$new_status' WHERE st_id = $student_id";
    if (mysqli_query($conn, $update_q)) {
        header("Location: students.php");
        exit();
    }
}

// Delete confirmation 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $student_id = intval($_POST['student_id']);
    $delete_q = "DELETE FROM students WHERE st_id = $student_id";
    if (mysqli_query($conn, $delete_q)) {
        header("Location: students.php");
        exit();
    }
}

// Courses list
$q_courses = "SELECT course_id, course_name FROM courses ORDER BY course_name ASC";
$r_courses = mysqli_query($conn, $q_courses);
$courses = [];
if ($r_courses && mysqli_num_rows($r_courses) > 0) {
    while ($row = mysqli_fetch_assoc($r_courses)) {
        $courses[] = $row;
    }
}

$instructor_course = "";
$selected_course = trim($_GET['course_id'] ?? '');
$selected_batch = trim($_GET['batch_id'] ?? '');
$search = trim($_GET['search'] ?? '');

$active_batch_id = '';
$active_batch_name = '';
$active_batch_students_count = 0;

if ($role === 'Instructor') {
    // Get instructor's course
    $uname = $_SESSION['username'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $iq = mysqli_query($conn, "SELECT course_id FROM users WHERE (id = '$user_id' OR username = '$uname') AND role = 'Instructor'");
    if ($iq && mysqli_num_rows($iq) > 0) {
        $instructor_course = mysqli_fetch_assoc($iq)['course_id'];
    }
    $selected_course = $instructor_course;

    // Active Batch Info & Count
    $bq_active = mysqli_query($conn, "SELECT batch_id, batch_name FROM batches WHERE course_id = '$instructor_course' AND (status = 'Not Pass Out' OR status = 'Active') LIMIT 1");
    if ($bq_active && mysqli_num_rows($bq_active) > 0) {
        $bres = mysqli_fetch_assoc($bq_active);
        $active_batch_id = $bres['batch_id'];
        $active_batch_name = $bres['batch_name'];
    }

    if ($active_batch_id) {
        $ab_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE course_id = '$instructor_course' AND batch_id = '$active_batch_id'");
        $active_batch_students_count = mysqli_fetch_assoc($ab_q)['count'] ?? 0;
    }

    // Batches list for Instructor combo box filter
    $q_batches = "SELECT batch_id, batch_name, status FROM batches WHERE course_id = '$instructor_course' ORDER BY id DESC";

    // Card 2: Active students in instructor's course (or selected batch)
    $active_where = "course_id = '$instructor_course' AND status = 'Active'";
    if (!empty($selected_batch)) {
        $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
        $active_where .= " AND batch_id = '$batch_esc'";
    }
    $q_active = "SELECT COUNT(*) as count FROM students WHERE $active_where";
    $active_count = mysqli_fetch_assoc(mysqli_query($conn, $q_active))['count'] ?? 0;

    // Card 3: Inactive students in instructor's course (or selected batch)
    $inactive_where = "course_id = '$instructor_course' AND status = 'Inactive'";
    if (!empty($selected_batch)) {
        $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
        $inactive_where .= " AND batch_id = '$batch_esc'";
    }
    $q_inactive = "SELECT COUNT(*) as count FROM students WHERE $inactive_where";
    $inactive_count = mysqli_fetch_assoc(mysqli_query($conn, $q_inactive))['count'] ?? 0;

    // Card 4: Total Batches of instructor's course
    $q_batches_count = "SELECT COUNT(*) as count FROM batches WHERE course_id = '$instructor_course'";
    $total_batches_count = mysqli_fetch_assoc(mysqli_query($conn, $q_batches_count))['count'] ?? 0;

    // Card 5: Total students across all batches of instructor (Active, Inactive, Passed Out)
    $q_total = "SELECT COUNT(*) as count FROM students WHERE course_id = '$instructor_course'";
    $total_count = mysqli_fetch_assoc(mysqli_query($conn, $q_total))['count'] ?? 0;

} else {
    // Admin & Super Admin
    $selected_course_esc = mysqli_real_escape_string($conn, $selected_course);

    // Batches for Admin/SuperAdmin combo box filter (filtered by selected course if selected)
    if (!empty($selected_course)) {
        $q_batches = "SELECT batch_id, batch_name, status FROM batches WHERE course_id = '$selected_course_esc' ORDER BY id DESC";
    } else {
        $q_batches = "SELECT batch_id, batch_name, status FROM batches ORDER BY id DESC";
    }

    // Card 1: Total Active
    $active_where = "status = 'Active'";
    if (!empty($selected_course)) {
        $active_where .= " AND course_id = '$selected_course_esc'";
    }
    if (!empty($selected_batch)) {
        $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
        $active_where .= " AND batch_id = '$batch_esc'";
    }
    $q_active = "SELECT COUNT(*) as count FROM students WHERE $active_where";
    $active_count = mysqli_fetch_assoc(mysqli_query($conn, $q_active))['count'] ?? 0;

    // Card 2: Total Inactive
    $inactive_where = "status = 'Inactive'";
    if (!empty($selected_course)) {
        $inactive_where .= " AND course_id = '$selected_course_esc'";
    }
    if (!empty($selected_batch)) {
        $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
        $inactive_where .= " AND batch_id = '$batch_esc'";
    }
    $q_inactive = "SELECT COUNT(*) as count FROM students WHERE $inactive_where";
    $inactive_count = mysqli_fetch_assoc(mysqli_query($conn, $q_inactive))['count'] ?? 0;

    // Card 3: Total Batches
    if (!empty($selected_course)) {
        $q_batches_count = "SELECT COUNT(*) as count FROM batches WHERE course_id = '$selected_course_esc'";
    } else {
        $q_batches_count = "SELECT COUNT(*) as count FROM batches";
    }
    $total_batches_count = mysqli_fetch_assoc(mysqli_query($conn, $q_batches_count))['count'] ?? 0;

    // Card 4: Total Students
    if (!empty($selected_course)) {
        $q_total_where = "course_id = '$selected_course_esc'";
        if (!empty($selected_batch)) {
            $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
            $q_total_where .= " AND batch_id = '$batch_esc'";
        }
        $q_total = "SELECT COUNT(*) as count FROM students WHERE $q_total_where";
    } else {
        $q_total = "SELECT COUNT(*) as count FROM students";
        if (!empty($selected_batch)) {
            $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
            $q_total .= " WHERE batch_id = '$batch_esc'";
        }
    }
    $total_count = mysqli_fetch_assoc(mysqli_query($conn, $q_total))['count'] ?? 0;
}

// Student List with search, course and batch filter
$q_list = "SELECT s.st_id as id, s.st_custom_id as custom_id, s.name as fullname, s.nic, s.dob, s.address, s.gender, s.status, s.email, s.phone, s.image, s.course_id, s.batch_id, s.role, c.course_name, b.batch_name 
           FROM students s 
           LEFT JOIN courses c ON s.course_id = c.course_id 
           LEFT JOIN batches b ON s.batch_id = b.batch_id";

$conditions = [];
if ($role === 'Instructor') {
    $conditions[] = "s.course_id = '$instructor_course'";
} elseif (!empty($selected_course)) {
    $conditions[] = "s.course_id = '$selected_course_esc'";
}

if (!empty($selected_batch)) {
    $batch_esc = mysqli_real_escape_string($conn, $selected_batch);
    $conditions[] = "s.batch_id = '$batch_esc'";
}

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(s.name LIKE '%$search_esc%' OR s.st_custom_id LIKE '%$search_esc%' OR s.nic LIKE '%$search_esc%')";
}

if (!empty($conditions)) {
    $q_list .= " WHERE " . implode(" AND ", $conditions);
}

$q_list .= " ORDER BY s.st_id DESC";
$r_list = mysqli_query($conn, $q_list);

// Batches list for filter dropdown
$r_batches = mysqli_query($conn, $q_batches);
$batches = [];
if ($r_batches && mysqli_num_rows($r_batches) > 0) {
    while ($row = mysqli_fetch_assoc($r_batches)) {
        $batches[] = $row;
    }
}

// Fetch all batches for Edit modal dropdown
$all_batches = [];
$r_all_batches = mysqli_query($conn, "SELECT batch_id, batch_name, course_id FROM batches ORDER BY id DESC");
if ($r_all_batches && mysqli_num_rows($r_all_batches) > 0) {
    while ($row = mysqli_fetch_assoc($r_all_batches)) {
        $all_batches[] = $row;
    }
}

// Collect student records into array for table rendering and bulk printing
$students_data = [];
if ($r_list && mysqli_num_rows($r_list) > 0) {
    while ($student_row = mysqli_fetch_assoc($r_list)) {
        $img_data = !empty($student_row['image']) ? 'data:image/jpeg;base64,' . base64_encode($student_row['image']) : '';
        $student_row['img_src'] = $img_data;
        unset($student_row['image']); // Free memory
        $students_data[] = $student_row;
    }
}

include('../include/header.php'); 
?>

<!-- QR Code Generator, HTML5 QR Scanner & HTML2Canvas for ID Card Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <h2>Manage Students</h2>

            <!-- Cards Grid -->
            <div class="cards-grid">
                <?php if ($role === 'Instructor'): ?>
                    <!-- 1. Active Batch Total Students Card (FIRST) -->
                    <div class="card-item">
                        <i class="fa-solid fa-user-group fa-2x" style="color: #6366f1;"></i>
                        <div>
                            <h3><?php echo $active_batch_students_count; ?></h3>
                            <p>Active Batch (<?php echo htmlspecialchars($active_batch_name ?: 'None'); ?>)</p>
                        </div>
                    </div>

                    <!-- 2. Active Students Card -->
                    <div class="card-item">
                        <i class="fa-solid fa-user-check fa-2x" style="color: #10b981;"></i>
                        <div>
                            <h3><?php echo $active_count; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>

                    <!-- 3. Inactive Students Card -->
                    <div class="card-item">
                        <i class="fa-solid fa-user-xmark fa-2x" style="color: #ef4444;"></i>
                        <div>
                            <h3><?php echo $inactive_count; ?></h3>
                            <p>Inactive Students</p>
                        </div>
                    </div>

                    <!-- 4. Total Batches Card -->
                    <div class="card-item">
                        <i class="fa-solid fa-layer-group fa-2x" style="color: #f59e0b;"></i>
                        <div>
                            <h3><?php echo $total_batches_count; ?></h3>
                            <p>Total Batches</p>
                        </div>
                    </div>

                    <!-- 5. Total Students in All Batches Card (LAST) -->
                    <div class="card-item">
                        <i class="fa-solid fa-users fa-2x" style="color: #8b5cf6;"></i>
                        <div>
                            <h3><?php echo $total_count; ?></h3>
                            <p>Total Enrolled</p>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Admin Cards -->
                    <div class="card-item">
                        <i class="fa-solid fa-user-check fa-2x" style="color: #10b981;"></i>
                        <div>
                            <h3><?php echo $active_count; ?></h3>
                            <p>Total Active</p>
                        </div>
                    </div>
                    <div class="card-item">
                        <i class="fa-solid fa-user-xmark fa-2x" style="color: #ef4444;"></i>
                        <div>
                            <h3><?php echo $inactive_count; ?></h3>
                            <p>Inactive Students</p>
                        </div>
                    </div>
                    <div class="card-item">
                        <i class="fa-solid fa-layer-group fa-2x" style="color: #f59e0b;"></i>
                        <div>
                            <h3><?php echo $total_batches_count; ?></h3>
                            <p>Total Batches</p>
                        </div>
                    </div>
                    <div class="card-item">
                        <i class="fa-solid fa-users fa-2x" style="color: #8b5cf6;"></i>
                        <div>
                            <h3><?php echo $total_count; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Page Action Row -->
            <div class="admin-header-row" style="margin-top: 25px;">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="btn-add" onclick="openStudentQrScanner()" style="background: #059669;" title="Scan Student QR">
                        <i class="fa-solid fa-qrcode"></i> Scan Student QR
                    </button>
                    <button type="button" class="btn-add" onclick="printAllStudentPasses()" style="background: #2563eb;" title="Print All Student Passes (CR80 Standard)">
                        <i class="fa-solid fa-print"></i> Print All Passes
                    </button>
                </div>
                <?php if ($role !== 'Instructor'): ?>
                    <a href="add_student.php" class="btn-add"><i class="fa-solid fa-plus"></i> Add Student</a>
                <?php endif; ?>
            </div>

            <!-- Filter & Search Bar -->
            <form method="GET" action="students.php" id="studentFilterForm" style="margin-bottom: 20px;">
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    
                    <!-- Search Input Box -->
                    <div class="input-box search-input-box" style="flex: 1; min-width: 220px; display: flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 12px;">
                        <input type="text" name="search" placeholder="Search by Name, ID or NIC..." value="<?php echo htmlspecialchars($search); ?>" style="border: none; outline: none; background: transparent; padding: 10px 0; width: 100%; color: inherit;" autocomplete="off">
                    </div>

                    <?php if ($role !== 'Instructor'): ?>
                    <!-- Course Combo Box Filter (Admin & Super Admin) -->
                    <div style="min-width: 190px;">
                        <select name="course_id" id="filter_course_id" onchange="handleCourseFilterChange(this)" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: inherit; width: 100%; cursor: pointer;">
                            <option value="">-- All Courses --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($selected_course === $c['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Batch Combo Box Filter -->
                    <div style="min-width: 190px;">
                        <select name="batch_id" id="filter_batch_id" onchange="this.form.submit()" class="modal-input" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: inherit; width: 100%; cursor: pointer;">
                            <option value="">-- All Batches --</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['batch_id']); ?>" <?php echo ($selected_batch === $b['batch_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['batch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Button -->
                    <button type="submit" class="btn-add" style="background: #2563eb; padding: 10px 18px;" title="Filter"><i class="fa-solid fa-search"></i> Search</button>
                    
                    <!-- Reset Button -->
                    <?php if (!empty($search) || !empty($selected_batch) || !empty($selected_course)): ?>
                        <a href="students.php" class="btn-add" style="background: #64748b; padding: 10px 18px;" title="Clear Filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Role</th>
                            <?php if ($role !== 'Instructor'): ?>
                            <th>Course</th>
                            <?php endif; ?>
                            <th>Batch</th>
                            <th>Status</th>
                            <?php if ($role !== 'Instructor'): ?>
                            <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students_data)): ?>
                            <?php foreach ($students_data as $student): 
                                $img_src = $student['img_src'] ?? '';
                                $st_status = $student['status'] ?? 'Active';
                                if ($st_status === 'Active') {
                                    $st_display_status = 'Active';
                                    $st_status_class = 'status-active';
                                } elseif ($st_status === 'Inactive') {
                                    $st_display_status = 'Inactive';
                                    $st_status_class = 'status-inactive';
                                } else {
                                    $st_display_status = 'Passed Out';
                                    $st_status_class = 'status-passed-out';
                                }
                            ?>
                                <tr class="admin-row" 
                                onclick="openStudentModal(
                                    '<?php echo $student['id']; ?>', 
                                    '<?php echo htmlspecialchars($student['custom_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['fullname'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['nic'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['dob'] ?? '', ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($student['address'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['gender'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['email'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['phone'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($st_display_status, ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($student['course_name'] ?? '', ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($student['batch_name'] ?? '', ENT_QUOTES); ?>',
                                    '<?php echo $img_src; ?>',
                                    '<?php echo htmlspecialchars($student['role'] ?? 'Student', ENT_QUOTES); ?>'
                                )">
                                    <td><?php echo htmlspecialchars($student['custom_id'] ?? ''); ?></td>
                                    <td class="admin-user-cell">
                                        <?php if (!empty($img_src)): ?>
                                            <img src="<?php echo $img_src; ?>" alt="Profile" class="admin-dp">
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-user default-avatar"></i>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($student['fullname']); ?></span>
                                    </td>
                                    <td>
                                        <?php if(($student['role'] ?? 'Student') === 'Monitor'): ?>
                                            <span style="background:#8b5cf6; color:white; padding:2px 8px; border-radius:12px; font-size:0.8em; font-weight:600;">Monitor</span>
                                        <?php else: ?>
                                            <span style="background:#64748b; color:white; padding:2px 8px; border-radius:12px; font-size:0.8em; font-weight:600;">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($role !== 'Instructor'): ?>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($student['batch_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="<?php echo $st_status_class; ?>"><?php echo $st_display_status; ?></span>
                                    </td>
                                    <?php if ($role !== 'Instructor'): ?>
                                    <td onclick="event.stopPropagation();">
                                        <!-- Table Edit Button -->
                                            <button type="button" class="btn-edit" onclick="openStudentEditModal(
                                                '<?php echo $student['id']; ?>', 
                                                '<?php echo htmlspecialchars($student['fullname'], ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($student['nic'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($student['dob'] ?? '', ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($student['address'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($student['gender'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($student['email'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($student['phone'] ?? '', ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($student['course_id'] ?? '', ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($student['batch_id'] ?? '', ENT_QUOTES); ?>'
                                            )">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button type="button" class="btn-delete" onclick="openStudentDeleteModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['fullname'], ENT_QUOTES); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($role === 'Instructor') ? '5' : '7'; ?>" style="text-align: center;">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>   
    </div>  
</div>
<?php include('../include/footer.php'); ?>

<!-- View Student Detail Modal (With National ID Card Student Pass) -->
<div id="studentModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 860px; max-height: 90vh; overflow-y: auto;">
        <span class="admin-modal-close" onclick="closeStudentModal()">&times;</span>
        <h3 class="admin-modal-title"><i class="fa-solid fa-id-card" style="color: #2563eb; margin-right: 6px;"></i> Student Profile & Identification Pass</h3>
        
        <div class="admin-modal-body" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
            <!-- Left: Student Photo -->
            <div class="admin-modal-photo-box" id="s_photo_box" style="flex: 0 0 110px; text-align: center;">
                <img id="s_photo" src="" alt="Profile Photo" style="width: 100px; height: 100px; border-radius:12px; object-fit: cover; display:none; border: 2px solid var(--rep-card-border, #e2e8f0);">
                <div id="s_default_dp" style="width: 100px; height: 100px; border-radius: 12px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #64748b;">
                    <i class="fa-solid fa-user"></i>
                </div>
            </div>

            <!-- Center: Student Details Grid -->
            <div class="admin-modal-details" style="flex: 1; min-width: 280px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <p><strong>Student ID:</strong> <span id="s_custom_id" style="font-weight: 700; color: #2563eb;"></span></p>
                <p><strong>Full Name:</strong> <span id="s_fullname"></span></p>
                <p><strong>NIC Number:</strong> <span id="s_nic"></span></p>
                <p><strong>Date of Birth:</strong> <span id="s_dob"></span></p>
                <p><strong>Gender:</strong> <span id="s_gender"></span></p>
                <p><strong>Phone:</strong> <span id="s_phone"></span></p>
                <p style="grid-column: span 2;"><strong>Address:</strong> <span id="s_address"></span></p>
                <p style="grid-column: span 2;"><strong>Email:</strong> <span id="s_email"></span></p>
                <p><strong>Course:</strong> <span id="s_course"></span></p>
                <p><strong>Batch:</strong> <span id="s_batch"></span></p>
                <p style="grid-column: span 2;"><strong>Status:</strong> <span id="s_status"></span></p>
            </div>
        </div>

        <!-- Official Student Pass / National ID Card Preview (CR80 Standard 85.6mm x 54mm Dimension) -->
        <div class="id-pass-section" style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <h4 style="margin: 0; color: var(--rep-text-main, #1e293b); font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-id-badge" style="color: #2563eb;"></i> Official Student Identification Pass (National ID Card CR80 Standard)
                </h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-export btn-print" onclick="printStudentIdCard()" style="padding: 7px 15px; font-size: 0.82rem; border-radius: 6px; background: #2563eb;" title="Print CR80 ID Card">
                        <i class="fa-solid fa-print"></i> Print ID Card (85.6mm × 54mm)
                    </button>
                </div>
            </div>

            <!-- The Physical Proportion ID Card Box -->
            <div id="student_id_card_element" class="student-id-card-wrap">
                <!-- Header Banner -->
                <div class="id-card-header">
                    <div class="id-card-logo-box">
                        <img src="../images/vta.png" alt="VTA Logo" style="width: 100%; height: 100%; object-fit: contain; display: block;">
                    </div>
                    <div class="id-card-header-text">
                        <div class="id-inst-name">NATIONAL VOCATIONAL TRAINING INSTITUTE</div>
                        <div class="id-inst-branch">BADDEGAMA CENTER • VOCATIONAL TRAINING AUTHORITY</div>
                    </div>
                    <div class="id-card-tag-badge">STUDENT PASS</div>
                </div>

                <!-- Card Main Body -->
                <div class="id-card-body">
                    <!-- Left: Photo Column -->
                    <div class="id-card-photo-col">
                        <div class="id-photo-frame">
                            <img id="pass_photo" src="" alt="Student Photo" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <div id="pass_photo_placeholder" class="id-photo-placeholder">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Middle: Info Column -->
                    <div class="id-card-info-col">
                        <div class="id-student-name" id="pass_name">STUDENT FULL NAME</div>
                        <div class="id-student-num" id="pass_custom_id">ST-2024-000</div>
                        <div class="id-info-row">
                            <span class="id-info-label">NIC:</span>
                            <span class="id-info-val" id="pass_nic">-</span>
                        </div>
                        <div class="id-info-row">
                            <span class="id-info-label">Course:</span>
                            <span class="id-info-val" id="pass_course">-</span>
                        </div>
                        <div class="id-info-row">
                            <span class="id-info-label">Batch:</span>
                            <span class="id-info-val" id="pass_batch">-</span>
                        </div>
                    </div>

                    <!-- Right: QR Column -->
                    <div class="id-card-qr-col">
                        <div id="student_qr_container" class="id-qr-box"></div>
                        <div class="id-qr-caption">ATTENDANCE QR</div>
                    </div>
                </div>

                <!-- Footer Ribbon -->
                <div class="id-card-footer">
                    <span>OFFICIAL STUDENT PASS • NVTI BADDEGAMA</span>
                    <span style="font-size: 8px; opacity: 0.85;">AUTHORIZED SIGNATURE</span>
                </div>
            </div>
        </div>

        <div class="admin-modal-footer" style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <?php if ($role === 'Instructor'): ?>
                <button type="button" class="btn-add" id="btn_set_monitor" onclick="openSetMonitorModal()" style="background: #8b5cf6;">
                    <i class="fa-solid fa-user-shield"></i> Set as Monitor
                </button>
                <form id="form_remove_monitor" action="remove_monitor_process.php" method="POST" style="display: none;">
                    <input type="hidden" name="student_id" id="rm_student_id">
                    <button type="submit" class="btn-delete" style="background-color: #f97316; color: #ffffff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                        <i class="fa-solid fa-user-minus"></i> Remove Monitor
                    </button>
                </form>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="student_id" id="s_student_id">
                <input type="hidden" name="new_status" id="s_new_status">
                <button type="submit" name="toggle_status" id="btn_s_toggle_status" class="btn-mark-inactive">
                    <i class="fa-solid fa-user-xmark"></i> Mark as Inactive
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Student QR Scanner Modal -->
<div id="qrScanStudentModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 500px; text-align: center;">
        <span class="admin-modal-close" onclick="closeStudentQrScanner()">&times;</span>
        <h3 class="admin-modal-title"><i class="fa-solid fa-camera" style="color: #2563eb; margin-right: 6px;"></i> Scan Student QR Code</h3>
        
        <p style="font-size: 0.88rem; color: #64748b; margin-top: -10px; margin-bottom: 15px;">
            Align the student QR pass within the frame to look up details
        </p>

        <div id="student_qr_reader" style="width: 100%; border-radius: 12px; overflow: hidden; border: 2px dashed #cbd5e1; background: #0f172a; min-height: 250px;"></div>

        <div id="student_qr_scan_status" style="margin-top: 12px; font-size: 0.9rem; font-weight: 600; min-height: 24px;"></div>

        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn-modal-dismiss" onclick="closeStudentQrScanner()">Close</button>
            <button type="button" class="btn-add" id="btn_restart_student_scanner" onclick="startStudentScanner()" style="display: none; background: #2563eb;">
                <i class="fa-solid fa-rotate-right"></i> Scan Another
            </button>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentModal" class="admin-modal">
    <div class="admin-modal-content" style="max-height: 90vh; overflow-y: auto;">
        <span class="admin-modal-close" onclick="closeStudentEditModal()">&times;</span>
        <h3 class="admin-modal-title">Edit Student Details</h3>
        
        <form id="editStudentForm" action="update_student_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="student_id" id="edit_student_id">

            <div class="modal-form-group">
                <label>Full Name</label>
                <input type="text" name="fullname" id="edit_s_fullname" class="modal-input" placeholder="Enter Fullname" required>
            </div>

            <div class="modal-form-group">
                <label>NIC Number</label>
                <input type="text" name="nic" id="edit_s_nic" class="modal-input" placeholder="Enter NIC Number" required>
            </div>

            <div class="modal-form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="edit_s_dob" class="modal-input" required>
            </div>

            <div class="modal-form-group">
                <label>Address</label>
                <input type="text" name="address" id="edit_s_address" class="modal-input" placeholder="Enter Address">
            </div>

            <div class="modal-form-group">
                <label>Gender</label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label><input type="radio" name="gender" id="edit_s_gender_male" value="Male"> Male</label>
                    <label><input type="radio" name="gender" id="edit_s_gender_female" value="Female"> Female</label>
                </div>
            </div>

            <div class="modal-form-group">
                <label>E-mail Address</label>
                <input type="email" name="email" id="edit_s_email" class="modal-input" placeholder="Enter Email Address">
            </div>

            <div class="modal-form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" id="edit_s_phone" class="modal-input" placeholder="Enter Phone Number">
            </div>

            <div class="modal-form-group">
                <label>Course Name</label>
                <select name="course_id" id="edit_s_course_id" class="modal-input" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_id']); ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-form-group">
                <label>Batch Name</label>
                <select name="batch_id" id="edit_s_batch_id" class="modal-input" required>
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($all_batches as $b): ?>
                        <option value="<?php echo htmlspecialchars($b['batch_id']); ?>"><?php echo htmlspecialchars($b['batch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-form-group">
                <label>Profile Picture</label>
                <input type="file" name="profile_image" id="edit_s_profile_image" accept="image/*" class="modal-input">
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="button" onclick="document.getElementById('updateStudentConfirmModal').style.display='flex';" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Confirmation Modal -->
<div id="updateStudentConfirmModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeUpdateStudentConfirmModal()">&times;</span>
        <i class="fa-solid fa-circle-question fa-3x" style="color: #2563eb; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Update</h3>
        <p>Are you sure you want to update this student's details?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" onclick="closeUpdateStudentConfirmModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: transparent; cursor: pointer;">Cancel</button>
            <button type="button" onclick="document.getElementById('editStudentForm').submit();" style="padding: 8px 16px; border-radius: 6px; border: none; background: #2563eb; color: white; font-weight: 600; cursor: pointer;">Yes, Update</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteStudentModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeStudentDeleteModal()">&times;</span>
        <i class="fa-solid fa-triangle-exclamation fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Delete</h3>
        <p>Are you sure you want to delete <strong id="delete_s_name"></strong>?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" class="del-btn" onclick="closeStudentDeleteModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background-color:light-gray ; cursor: pointer;">Cancel</button>
            <form method="POST">
                <input type="hidden" name="student_id" id="delete_s_id">
                <button type="submit" name="confirm_delete" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Update Success Modal -->
<?php if (isset($_GET['updated']) && $_GET['updated'] === 'success'): ?>
<div id="successStudentModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981; margin-bottom: 15px;"></i>
        <h3>Update Successful!</h3>
        <p style="margin-top: 8px; color: #64748b;">Student details have been updated successfully.</p>
        <button type="button" onclick="closeStudentSuccessModal()" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<!-- Update Success Modal for Monitor -->
<?php if (isset($_GET['monitor']) && $_GET['monitor'] === 'success'): ?>
<div id="successMonitorModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981; margin-bottom: 15px;"></i>
        <h3>Monitor Setted Successfully!</h3>
        <p style="margin-top: 8px; color: #64748b;">The student is now a Monitor.</p>
        <button type="button" onclick="document.getElementById('successMonitorModal').style.display='none'; window.location.href='students.php';" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<!-- Success Modal for Monitor Removed -->
<?php if (isset($_GET['monitor_removed']) && $_GET['monitor_removed'] === 'success'): ?>
<div id="successMonitorRemovedModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-user-minus fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3>Monitor Removed!</h3>
        <p style="margin-top: 8px; color: #64748b;">The student's monitor privileges have been removed.</p>
        <button type="button" onclick="document.getElementById('successMonitorRemovedModal').style.display='none'; window.location.href='students.php';" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<!-- Print Successful Modal -->
<div id="printSuccessModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center; border-radius: 16px; padding: 25px 20px;">
        <div style="width: 65px; height: 65px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
            <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981;"></i>
        </div>
        <h3 style="margin: 0 0 8px 0; font-size: 1.25rem; font-weight: 700; color: var(--rep-text-main, #0f172a);">Card Printed Successfully!</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
            Student Pass for <strong id="print_succ_student_name" style="color: #2563eb;">Student</strong> has been sent to printer.
        </p>
        <div style="margin-top: 20px; display: flex; justify-content: center;">
            <button type="button" onclick="closePrintSuccessModal()" style="padding: 9px 26px; border-radius: 8px; border: none; background: #10b981; color: white; font-weight: 600; font-size: 0.92rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                <i class="fa-solid fa-check"></i> OK
            </button>
        </div>
    </div>
</div>

<!-- Set Monitor Modal -->
<div id="setMonitorModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px;">
        <span class="admin-modal-close" onclick="closeSetMonitorModal()">&times;</span>
        <h3 class="admin-modal-title">Set as Monitor</h3>
        
        <form id="setMonitorForm" action="set_monitor_process.php" method="POST">
            <input type="hidden" name="student_id" id="monitor_student_id">

            <div class="modal-form-group">
                <label>Username <span id="m_username_err" style="color: red; font-size: 0.8em; margin-left: 10px;"></span></label>
                <input type="text" name="username" id="m_username" class="modal-input" placeholder="Enter Username" required onkeyup="checkMonitorUsername()">
            </div>

            <div class="modal-form-group" style="position: relative;">
                <label>Password</label>
                <input type="password" name="password" id="m_password" class="modal-input" placeholder="Enter Password" required onkeyup="validateMonitorPassword()">
                <i class="fa-solid fa-eye" id="m_toggle_pwd" style="position: absolute; right: 15px; top: 40px; cursor: pointer; color: #64748b;" onclick="togglePassword('m_password', 'm_toggle_pwd')"></i>
            </div>

            <div class="modal-form-group" style="position: relative;">
                <label>Confirm Password <span id="m_pwd_err" style="color: red; font-size: 0.8em; margin-left: 10px;"></span></label>
                <input type="password" name="confirm_password" id="m_confirm_password" class="modal-input" placeholder="Confirm Password" required onkeyup="validateMonitorPassword()">
                <i class="fa-solid fa-eye" id="m_toggle_cpwd" style="position: absolute; right: 15px; top: 40px; cursor: pointer; color: #64748b;" onclick="togglePassword('m_confirm_password', 'm_toggle_cpwd')"></i>
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="submit" id="btn_submit_monitor" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;" disabled>
                    <i class="fa-solid fa-user-check"></i> Set Monitor
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let m_uname_valid = false;
let m_pwd_valid = false;

function checkMonitorSubmit() {
    document.getElementById('btn_submit_monitor').disabled = !(m_uname_valid && m_pwd_valid);
}

function checkMonitorUsername() {
    let un = document.getElementById('m_username').value.trim();
    let err = document.getElementById('m_username_err');
    
    if (un.length < 3) {
        err.textContent = "Too short";
        err.style.color = "red";
        m_uname_valid = false;
        checkMonitorSubmit();
        return;
    }
    
    // AJAX to check uniqueness using check_instructor_duplicate.php
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "check_instructor_duplicate.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            let res = xhr.responseText.trim();
            if (res !== 'OK') {
                err.textContent = "Taken";
                err.style.color = "red";
                m_uname_valid = false;
            } else {
                err.textContent = "Available";
                err.style.color = "green";
                m_uname_valid = true;
            }
            checkMonitorSubmit();
        }
    };
    // Correct parameters for check_instructor_duplicate.php
    xhr.send("field=username&value=" + encodeURIComponent(un));
}

function validateMonitorPassword() {
    let p = document.getElementById('m_password').value;
    let cp = document.getElementById('m_confirm_password').value;
    let err = document.getElementById('m_pwd_err');
    
    // Strong password check: 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
    let strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    
    if (p === "") {
        err.textContent = "Required";
        err.style.color = "red";
        m_pwd_valid = false;
    } else if (!strongRegex.test(p)) {
        err.textContent = "Weak Password (Need 8 chars, A-Z, a-z, 0-9, special char)";
        err.style.color = "red";
        m_pwd_valid = false;
    } else if (p !== cp) {
        err.textContent = "Passwords do not match";
        err.style.color = "red";
        m_pwd_valid = false;
    } else {
        err.textContent = "Strong & Matched";
        err.style.color = "green";
        m_pwd_valid = true;
    }
    checkMonitorSubmit();
}

function togglePassword(inputId, iconId) {
    let input = document.getElementById(inputId);
    let icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function openSetMonitorModal() {
    // get current student id from the view modal
    let sid = document.getElementById('s_student_id').value;
    document.getElementById('monitor_student_id').value = sid;
    
    // Reset forms
    document.getElementById('m_username').value = '';
    document.getElementById('m_password').value = '';
    document.getElementById('m_confirm_password').value = '';
    document.getElementById('m_username_err').textContent = '';
    document.getElementById('m_pwd_err').textContent = '';
    m_uname_valid = false;
    m_pwd_valid = false;
    checkMonitorSubmit();

    document.getElementById('studentModal').style.display = 'none';
    document.getElementById('setMonitorModal').style.display = 'flex';
}

function closeSetMonitorModal() {
    document.getElementById('setMonitorModal').style.display = 'none';
}
function openStudentModal(id, custom_id, fullname, nic, dob, address, gender, email, phone, status, course, batch, image, role) {
    const setText = (elemId, val) => {
        let el = document.getElementById(elemId);
        if (el) el.textContent = val || '-';
    };

    setText('s_custom_id', custom_id);
    setText('s_fullname', fullname);
    setText('s_nic', nic);
    setText('s_dob', dob);
    setText('s_address', address);
    setText('s_gender', gender);
    setText('s_email', email);
    setText('s_phone', phone);
    setText('s_course', course);
    setText('s_batch', batch);
    
    let statusSpan = document.getElementById('s_status');
    if (statusSpan) {
        if (status === 'Active') {
            statusSpan.textContent = 'Active';
            statusSpan.className = 'status-active';
        } else if (status === 'Inactive') {
            statusSpan.textContent = 'Inactive';
            statusSpan.className = 'status-inactive';
        } else {
            statusSpan.textContent = 'Passed Out';
            statusSpan.className = 'status-passed-out';
        }
    }

    // Top Profile Photo
    let photo = document.getElementById('s_photo');
    let defaultDp = document.getElementById('s_default_dp');
    if (image) {
        if (photo) {
            photo.src = image;
            photo.style.display = 'block';
        }
        if (defaultDp) defaultDp.style.display = 'none';
    } else {
        if (photo) photo.style.display = 'none';
        if (defaultDp) defaultDp.style.display = 'flex';
    }

    // National ID Card Pass Fields
    let passPhoto = document.getElementById('pass_photo');
    let passPlaceholder = document.getElementById('pass_photo_placeholder');
    if (image) {
        if (passPhoto) {
            passPhoto.src = image;
            passPhoto.style.display = 'block';
        }
        if (passPlaceholder) passPlaceholder.style.display = 'none';
    } else {
        if (passPhoto) passPhoto.style.display = 'none';
        if (passPlaceholder) passPlaceholder.style.display = 'flex';
    }

    setText('pass_name', fullname);
    setText('pass_custom_id', custom_id);
    setText('pass_nic', nic);
    setText('pass_course', course);
    setText('pass_batch', batch);

    // Generate Dynamic QR Code inside the Pass Card
    let qrContainer = document.getElementById('student_qr_container');
    if (qrContainer) {
        qrContainer.innerHTML = '';
        if (custom_id) {
            try {
                new QRCode(qrContainer, {
                    text: custom_id,
                    width: 140,
                    height: 140,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
                setTimeout(function() {
                    let canvases = qrContainer.querySelectorAll('canvas');
                    let imgs = qrContainer.querySelectorAll('img');
                    if (imgs.length > 0) {
                        canvases.forEach(c => c.style.display = 'none');
                        imgs[0].style.display = 'block';
                        imgs[0].style.width = '70px';
                        imgs[0].style.height = '70px';
                        imgs[0].style.margin = 'auto';
                    }
                }, 40);
            } catch(e) {
                console.error("QRCode generation error:", e);
            }
        }
    }

    let sStudentId = document.getElementById('s_student_id');
    if (sStudentId) sStudentId.value = id;
    
    let rmStudentId = document.getElementById('rm_student_id');
    if (rmStudentId) rmStudentId.value = id;

    let btnToggle = document.getElementById('btn_s_toggle_status');
    let sNewStatus = document.getElementById('s_new_status');
    if (btnToggle && sNewStatus) {
        if (status === 'Active') {
            sNewStatus.value = 'Inactive';
            btnToggle.innerHTML = '<i class="fa-solid fa-user-xmark"></i> Mark as Inactive';
            btnToggle.className = 'btn-mark-inactive';
            btnToggle.style.display = 'block';
        } else if (status === 'Inactive') {
            sNewStatus.value = 'Active';
            btnToggle.innerHTML = '<i class="fa-solid fa-user-check"></i> Mark as Active';
            btnToggle.className = 'btn-mark-active';
            btnToggle.style.display = 'block';
        } else {
            sNewStatus.value = 'Active';
            btnToggle.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Reactivate as Active';
            btnToggle.className = 'btn-mark-active';
            btnToggle.style.display = 'block';
        }
    }

    let btnMonitor = document.getElementById('btn_set_monitor');
    let formRemoveMonitor = document.getElementById('form_remove_monitor');
    
    if (btnMonitor) {
        if (status === 'Inactive' || status === 'Passed Out' || role === 'Monitor') {
            btnMonitor.style.display = 'none';
        } else {
            btnMonitor.style.display = 'block';
        }
    }
    
    if (formRemoveMonitor) {
        if (role === 'Monitor') {
            formRemoveMonitor.style.display = 'block';
        } else {
            formRemoveMonitor.style.display = 'none';
        }
    }

    let modal = document.getElementById('studentModal');
    if (modal) modal.style.display = 'flex';
}

function closeStudentModal() {
    let modal = document.getElementById('studentModal');
    if (modal) modal.style.display = 'none';

    // Clear dynamic QR code and photos to prevent DOM remnants
    let qrContainer = document.getElementById('student_qr_container');
    if (qrContainer) qrContainer.innerHTML = '';
    
    let photo = document.getElementById('s_photo');
    if (photo) photo.src = '';
    
    let passPhoto = document.getElementById('pass_photo');
    if (passPhoto) passPhoto.src = '';
}

// QR Code Scanner Logic for Students Page
let studentQrScanner = null;
let isStudentScanLocked = false;
let studentScanGracePeriodUntil = 0;
let lastScannedStudentQr = '';

function openStudentQrScanner() {
    closeStudentModal(); // Close student profile modal if already open
    isStudentScanLocked = false;
    
    document.getElementById('qrScanStudentModal').style.display = 'flex';
    document.getElementById('student_qr_scan_status').innerHTML = '<span style="color: #2563eb;"><i class="fa-solid fa-spinner fa-spin"></i> Initializing camera...</span>';
    document.getElementById('btn_restart_student_scanner').style.display = 'none';
    
    setTimeout(startStudentScanner, 300);
}

function startStudentScanner() {
    isStudentScanLocked = false;
    // Set 1.2 second grace period to prevent immediate re-trigger from previous card/screen
    studentScanGracePeriodUntil = Date.now() + 1200;
    
    document.getElementById('btn_restart_student_scanner').style.display = 'none';
    document.getElementById('student_qr_scan_status').innerHTML = '<span style="color: #2563eb;"><i class="fa-solid fa-camera"></i> Camera active. Align student QR in frame...</span>';

    if (studentQrScanner) {
        try {
            studentQrScanner.stop().then(() => {
                try { studentQrScanner.clear(); } catch(e) {}
                initHtml5Scanner();
            }).catch(() => {
                initHtml5Scanner();
            });
            return;
        } catch(e) {
            initHtml5Scanner();
            return;
        }
    }
    initHtml5Scanner();
}

function initHtml5Scanner() {
    const readerElement = document.getElementById("student_qr_reader");
    if (!readerElement) return;
    readerElement.innerHTML = '';

    studentQrScanner = new Html5Qrcode("student_qr_reader");
    studentQrScanner.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 220, height: 220 }
        },
        (decodedText, decodedResult) => {
            // Ignore scans during the first 1.2 seconds of camera activation
            if (Date.now() < studentScanGracePeriodUntil) {
                return;
            }
            if (!isStudentScanLocked && decodedText) {
                handleStudentQrScanned(decodedText);
            }
        },
        (errorMessage) => {
            // scanning in progress
        }
    ).catch(err => {
        document.getElementById('student_qr_scan_status').innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Camera access error: ' + err + '</span>';
        document.getElementById('btn_restart_student_scanner').style.display = 'inline-flex';
    });
}

function handleStudentQrScanned(qrData) {
    if (isStudentScanLocked) return;
    isStudentScanLocked = true; // Lock immediately to prevent duplicate triggers

    qrData = (qrData || '').trim();
    if (!qrData) {
        isStudentScanLocked = false;
        return;
    }

    lastScannedStudentQr = qrData;

    // Stop camera safely
    if (studentQrScanner) {
        try {
            studentQrScanner.stop().then(() => {
                try { studentQrScanner.clear(); } catch(e) {}
            }).catch(() => {});
        } catch(e) {}
    }

    let statusEl = document.getElementById('student_qr_scan_status');
    let restartBtn = document.getElementById('btn_restart_student_scanner');
    
    if (statusEl) {
        statusEl.innerHTML = '<span style="color: #2563eb;"><i class="fa-solid fa-spinner fa-spin"></i> Scanned QR: <strong>' + qrData + '</strong>. Fetching student...</span>';
    }

    let formData = new FormData();
    formData.append('qr_data', qrData);

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "get_student_by_qr.php", true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                let res = null;
                try {
                    res = JSON.parse(xhr.responseText);
                } catch(e) {
                    console.error("Parse Error:", xhr.responseText);
                    isStudentScanLocked = false;
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Error parsing response</span>';
                    }
                    if (restartBtn) restartBtn.style.display = 'inline-flex';
                    return;
                }

                if (res && res.success && res.student) {
                    // Close scanner modal
                    document.getElementById('qrScanStudentModal').style.display = 'none';
                    let readerElement = document.getElementById("student_qr_reader");
                    if (readerElement) readerElement.innerHTML = '';
                    
                    let s = res.student;
                    openStudentModal(
                        s.id,
                        s.custom_id,
                        s.fullname,
                        s.nic,
                        s.dob,
                        s.address,
                        s.gender,
                        s.email,
                        s.phone,
                        s.status,
                        s.course_name,
                        s.batch_name,
                        s.image,
                        s.role
                    );
                } else {
                    isStudentScanLocked = false;
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i> ' + (res && res.message ? res.message : 'Student not found') + '</span>';
                    }
                    if (restartBtn) restartBtn.style.display = 'inline-flex';
                }
            } else {
                isStudentScanLocked = false;
                if (statusEl) {
                    statusEl.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Server HTTP Error (' + xhr.status + ')</span>';
                }
                if (restartBtn) restartBtn.style.display = 'inline-flex';
            }
        }
    };
    xhr.onerror = function() {
        isStudentScanLocked = false;
        if (statusEl) {
            statusEl.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Network connection error.</span>';
        }
        if (restartBtn) restartBtn.style.display = 'inline-flex';
    };
    xhr.send(formData);
}

function closeStudentQrScanner() {
    isStudentScanLocked = true;
    if (studentQrScanner) {
        try {
            studentQrScanner.stop().then(() => {
                try { studentQrScanner.clear(); } catch(e) {}
            }).catch(err => {});
        } catch(e) {}
    }
    document.getElementById('qrScanStudentModal').style.display = 'none';
    let readerElement = document.getElementById("student_qr_reader");
    if (readerElement) readerElement.innerHTML = '';
}

function downloadFullStudentIdCard() {
    let cardElement = document.getElementById('student_id_card_element');
    let cid = document.getElementById('pass_custom_id').textContent || document.getElementById('s_custom_id').textContent || 'Student_Pass';
    
    if (!cardElement) return;

    if (typeof html2canvas !== 'undefined') {
        html2canvas(cardElement, {
            scale: 3, // 300 DPI high-definition scale
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false
        }).then(canvas => {
            let a = document.createElement('a');
            a.href = canvas.toDataURL("image/png");
            a.download = cid + '_National_ID_Pass.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }).catch(err => {
            console.error("html2canvas export error:", err);
            downloadStudentQrCode();
        });
    } else {
        downloadStudentQrCode();
    }
}

function downloadStudentQrCode() {
    let img = document.querySelector('#student_qr_container img');
    let cid = document.getElementById('s_custom_id').textContent || 'student_qr';
    if (img && img.src) {
        let a = document.createElement('a');
        a.href = img.src;
        a.download = cid + '_QR.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    } else {
        let canvas = document.querySelector('#student_qr_container canvas');
        if (canvas) {
            let a = document.createElement('a');
            a.href = canvas.toDataURL("image/png");
            a.download = cid + '_QR.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }
}

function printStudentIdCard() {
    let cid = document.getElementById('pass_custom_id').textContent || document.getElementById('s_custom_id').textContent || '';
    let name = document.getElementById('pass_name').textContent || document.getElementById('s_fullname').textContent || '';
    let nic = document.getElementById('pass_nic').textContent || document.getElementById('s_nic').textContent || '-';
    let course = document.getElementById('pass_course').textContent || document.getElementById('s_course').textContent || '-';
    let batch = document.getElementById('pass_batch').textContent || document.getElementById('s_batch').textContent || '-';

    // Student Photo
    let photoImg = document.getElementById('pass_photo');
    let photoSrc = (photoImg && photoImg.src && photoImg.style.display !== 'none') ? photoImg.src : '';

    // VTA Logo
    let logoImg = document.querySelector('.id-card-logo-box img');
    let logoSrc = (logoImg && logoImg.src) ? logoImg.src : '../images/vta.png';

    // Guaranteed High-Res QR Generation
    let qrDiv = document.createElement('div');
    new QRCode(qrDiv, {
        text: cid,
        width: 300,
        height: 300,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    let qrDataUrl = '';
    let tempCanvas = qrDiv.querySelector('canvas');
    if (tempCanvas) {
        qrDataUrl = tempCanvas.toDataURL("image/png");
    } else {
        let existingCanvas = document.querySelector('#student_qr_container canvas');
        if (existingCanvas) qrDataUrl = existingCanvas.toDataURL("image/png");
    }

    let printWin = window.open('', '', 'width=700,height=550');
    printWin.document.write('<!DOCTYPE html><html><head><title>Student ID Pass - ' + cid + '</title>');
    printWin.document.write('<style>');
    printWin.document.write('@page { size: auto; margin: 0mm; }');
    printWin.document.write('* { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }');
    printWin.document.write('html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; font-family: "Segoe UI", Arial, sans-serif; height: auto !important; min-height: 0 !important; overflow: hidden !important; }');
    
    // Print container & CR80 exact 85.6mm x 54mm physical card (Strictly 1 Single Page)
    printWin.document.write('.print-wrapper { width: 100%; display: flex; align-items: center; justify-content: center; padding: 20px 0 0 0; margin: 0 auto; page-break-inside: avoid !important; page-break-after: avoid !important; page-break-before: avoid !important; break-inside: avoid !important; break-after: avoid !important; }');
    
    // Dashed cutting line around the card for easy cutting
    printWin.document.write('.card-cut-container { padding: 1.5mm; border: 1px dashed #64748b; border-radius: 4mm; display: inline-flex; align-items: center; justify-content: center; page-break-inside: avoid !important; break-inside: avoid !important; background: #ffffff !important; box-sizing: border-box; position: relative; }');
    printWin.document.write('.id-card { width: 85.6mm; height: 54mm; background: #ffffff !important; border: 1.2px solid #2563eb; border-radius: 3.18mm; overflow: hidden; position: relative; display: flex; flex-direction: column; justify-content: space-between; box-shadow: none !important; page-break-inside: avoid !important; page-break-after: avoid !important; page-break-before: avoid !important; break-inside: avoid !important; break-after: avoid !important; box-sizing: border-box; }');
    
    // Header banner
    printWin.document.write('.id-hdr { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important; color: #ffffff; padding: 2mm 3mm; display: flex; align-items: center; justify-content: space-between; border-bottom: 1.5px solid #f59e0b; gap: 2.5mm; }');
    printWin.document.write('.id-hdr-logo { width: 6.5mm; height: 6.5mm; background: #ffffff; border-radius: 1.2mm; padding: 0.5mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }');
    printWin.document.write('.id-hdr-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }');
    printWin.document.write('.id-hdr-content { flex: 1; min-width: 0; }');
    printWin.document.write('.id-hdr-title { font-size: 6.8pt; font-weight: 800; letter-spacing: 0.2px; line-height: 1.15; color: #ffffff; }');
    printWin.document.write('.id-hdr-sub { font-size: 5pt; color: #93c5fd; font-weight: 600; }');
    printWin.document.write('.id-hdr-badge { background: #f59e0b; color: #0f172a; font-size: 5.2pt; font-weight: 800; padding: 1px 4px; border-radius: 2px; flex-shrink: 0; }');
    
    // Body layout
    printWin.document.write('.id-body { display: flex; align-items: center; justify-content: space-between; padding: 2mm 3mm; flex: 1; gap: 2.5mm; background: #ffffff !important; }');
    
    // Photo
    printWin.document.write('.id-photo-box { width: 20mm; height: 26mm; border: 1.2px solid #2563eb; border-radius: 1.5mm; overflow: hidden; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }');
    printWin.document.write('.id-photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }');
    printWin.document.write('.id-photo-ph { font-size: 14pt; color: #94a3b8; font-weight: bold; }');
    
    // Details
    printWin.document.write('.id-info { flex: 1; min-width: 0; line-height: 1.18; text-align: left; }');
    printWin.document.write('.id-name { font-size: 7pt; font-weight: 800; color: #0f172a; white-space: normal !important; overflow: visible !important; word-break: break-word; text-transform: uppercase; line-height: 1.15; }');
    printWin.document.write('.id-cid { font-size: 6.8pt; font-weight: 800; color: #2563eb; margin: 0.3mm 0 0.8mm 0; }');
    printWin.document.write('.id-row { font-size: 5.6pt; color: #334155; display: flex; margin-bottom: 0.4mm; line-height: 1.15; }');
    printWin.document.write('.id-lbl { font-weight: 700; color: #64748b; width: 9.5mm; flex-shrink: 0; }');
    printWin.document.write('.id-val { font-weight: 600; color: #0f172a; white-space: normal !important; overflow: visible !important; word-break: break-word; }');
    
    // QR Code
    printWin.document.write('.id-qr-wrap { width: 22mm; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }');
    printWin.document.write('.id-qr-box { width: 20.5mm; height: 20.5mm; padding: 0.8mm; border: 0.8px solid #cbd5e1; border-radius: 1.5mm; background: #ffffff !important; display: flex !important; align-items: center !important; justify-content: center !important; margin: 0 auto; box-sizing: border-box; }');
    printWin.document.write('.id-qr-box img { width: 100% !important; height: 100% !important; display: block !important; margin: auto !important; object-fit: contain; image-rendering: pixelated; }');
    printWin.document.write('.id-qr-tag { font-size: 4.6pt; font-weight: 700; color: #64748b; margin-top: 0.6mm; letter-spacing: 0.2px; text-align: center; }');
    
    // Footer
    printWin.document.write('.id-ftr { background: #f1f5f9 !important; border-top: 0.8px solid #e2e8f0; padding: 1mm 3.2mm; font-size: 5pt; font-weight: 600; color: #475569; display: flex; justify-content: space-between; align-items: center; }');
    printWin.document.write('</style></head><body>');
    
    printWin.document.write('<div class="print-wrapper">');
    printWin.document.write('<div class="card-cut-container">');
    printWin.document.write('<div class="id-card">');
    
    // Header
    printWin.document.write('<div class="id-hdr">');
    printWin.document.write('<div class="id-hdr-logo"><img src="' + logoSrc + '"/></div>');
    printWin.document.write('<div class="id-hdr-content"><div class="id-hdr-title">NATIONAL VOCATIONAL TRAINING INSTITUTE</div><div class="id-hdr-sub">BADDEGAMA CENTER • VOCATIONAL TRAINING AUTHORITY</div></div>');
    printWin.document.write('<div class="id-hdr-badge">STUDENT PASS</div>');
    printWin.document.write('</div>');
    
    // Body
    printWin.document.write('<div class="id-body">');
    
    // Photo
    printWin.document.write('<div class="id-photo-box">');
    if (photoSrc) {
        printWin.document.write('<img src="' + photoSrc + '"/>');
    } else {
        printWin.document.write('<div class="id-photo-ph">PHOTO</div>');
    }
    printWin.document.write('</div>');
    
    // Info
    printWin.document.write('<div class="id-info">');
    printWin.document.write('<div class="id-name">' + name + '</div>');
    printWin.document.write('<div class="id-cid">' + cid + '</div>');
    printWin.document.write('<div class="id-row"><span class="id-lbl">NIC:</span><span class="id-val">' + nic + '</span></div>');
    printWin.document.write('<div class="id-row"><span class="id-lbl">Course:</span><span class="id-val">' + course + '</span></div>');
    printWin.document.write('<div class="id-row"><span class="id-lbl">Batch:</span><span class="id-val">' + batch + '</span></div>');
    printWin.document.write('</div>');
    
    // QR Code
    printWin.document.write('<div class="id-qr-wrap">');
    printWin.document.write('<div class="id-qr-box"><img src="' + qrDataUrl + '"/></div>');
    printWin.document.write('<div class="id-qr-tag">ATTENDANCE QR</div>');
    printWin.document.write('</div>');
    
    printWin.document.write('</div>'); // end body
    
    // Footer
    printWin.document.write('<div class="id-ftr">');
    printWin.document.write('<span>OFFICIAL STUDENT PASS • NVTI BADDEGAMA</span>');
    printWin.document.write('<span>AUTHORIZED SIGNATURE</span>');
    printWin.document.write('</div>');
    
    printWin.document.write('</div>'); // end id-card
    printWin.document.write('</div>'); // end card-cut-container
    printWin.document.write('</div>'); // end print-wrapper
    
    // Image loading synchronization script
    printWin.document.write('<script>');
    printWin.document.write('var hasPrinted = false;');
    printWin.document.write('function triggerPrint() {');
    printWin.document.write('    if (hasPrinted) return;');
    printWin.document.write('    hasPrinted = true;');
    printWin.document.write('    window.focus();');
    printWin.document.write('    window.print();');
    printWin.document.write('}');
    printWin.document.write('function notifyParentAndClose() {');
    printWin.document.write('    try {');
    printWin.document.write('        if (window.opener && typeof window.opener.showPrintSuccessModal === "function") {');
    printWin.document.write('            window.opener.showPrintSuccessModal(' + JSON.stringify(name) + ');');
    printWin.document.write('        }');
    printWin.document.write('    } catch(e) {}');
    printWin.document.write('    window.close();');
    printWin.document.write('}');
    printWin.document.write('window.onafterprint = notifyParentAndClose;');
    printWin.document.write('window.addEventListener("afterprint", notifyParentAndClose);');
    printWin.document.write('window.onload = function() {');
    printWin.document.write('    var images = document.images;');
    printWin.document.write('    var total = images.length;');
    printWin.document.write('    if (total === 0) { triggerPrint(); return; }');
    printWin.document.write('    var loaded = 0;');
    printWin.document.write('    function checkAll() {');
    printWin.document.write('        loaded++;');
    printWin.document.write('        if (loaded >= total) { setTimeout(triggerPrint, 200); }');
    printWin.document.write('    }');
    printWin.document.write('    for (var i = 0; i < total; i++) {');
    printWin.document.write('        if (images[i].complete) { checkAll(); }');
    printWin.document.write('        else { images[i].addEventListener("load", checkAll); images[i].addEventListener("error", checkAll); }');
    printWin.document.write('    }');
    printWin.document.write('};');
    printWin.document.write('setTimeout(triggerPrint, 1500);'); // Fallback trigger
    printWin.document.write('<\/script>');

    printWin.document.write('</body></html>');
    printWin.document.close();
}

// All students in current view for batch pass printing
const allStudentsData = <?php echo json_encode($students_data); ?>;

function handleCourseFilterChange(selectEl) {
    let batchSelect = document.getElementById('filter_batch_id');
    if (batchSelect) {
        batchSelect.value = ''; // Reset batch when switching course
    }
    if (selectEl && selectEl.form) {
        selectEl.form.submit();
    }
}

function printAllStudentPasses() {
    if (!allStudentsData || allStudentsData.length === 0) {
        alert("No students found in the current view to print passes.");
        return;
    }

    let logoImg = document.querySelector('.id-card-logo-box img');
    let logoSrc = (logoImg && logoImg.src) ? logoImg.src : '../images/vta.png';

    const escapeHtml = (str) => {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };

    let studentCardsHtml = '';

    for (let i = 0; i < allStudentsData.length; i++) {
        let st = allStudentsData[i];
        let cid = escapeHtml(st.custom_id || '');
        let rawCid = st.custom_id || '';
        let name = escapeHtml(st.fullname || '');
        let nic = escapeHtml(st.nic || '-');
        let course = escapeHtml(st.course_name || '-');
        let batch = escapeHtml(st.batch_name || '-');
        let photoSrc = st.img_src || '';

        // Generate QR code for student
        let qrDataUrl = '';
        if (rawCid) {
            let qrDiv = document.createElement('div');
            new QRCode(qrDiv, {
                text: rawCid,
                width: 300,
                height: 300,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            let tempCanvas = qrDiv.querySelector('canvas');
            if (tempCanvas) {
                qrDataUrl = tempCanvas.toDataURL("image/png");
            } else {
                let tempImg = qrDiv.querySelector('img');
                if (tempImg && tempImg.src) qrDataUrl = tempImg.src;
            }
        }

        let photoHtml = photoSrc 
            ? '<img src="' + photoSrc + '" alt="Student Photo"/>' 
            : '<div class="id-photo-ph"><i class="fa-solid fa-user"></i></div>';

        studentCardsHtml += `
            <div class="card-cut-container">
                <div class="id-card">
                    <!-- Header -->
                    <div class="id-hdr">
                        <div class="id-hdr-logo"><img src="${logoSrc}" alt="VTA Logo"/></div>
                        <div class="id-hdr-content">
                            <div class="id-hdr-title">NATIONAL VOCATIONAL TRAINING INSTITUTE</div>
                            <div class="id-hdr-sub">BADDEGAMA CENTER • VOCATIONAL TRAINING AUTHORITY</div>
                        </div>
                        <div class="id-hdr-badge">STUDENT PASS</div>
                    </div>

                    <!-- Body -->
                    <div class="id-body">
                        <!-- Photo -->
                        <div class="id-photo-box">
                            ${photoHtml}
                        </div>

                        <!-- Info -->
                        <div class="id-info">
                            <div class="id-name">${name}</div>
                            <div class="id-cid">${cid}</div>
                            <div class="id-row"><span class="id-lbl">NIC:</span><span class="id-val">${nic}</span></div>
                            <div class="id-row"><span class="id-lbl">Course:</span><span class="id-val">${course}</span></div>
                            <div class="id-row"><span class="id-lbl">Batch:</span><span class="id-val">${batch}</span></div>
                        </div>

                        <!-- QR Code -->
                        <div class="id-qr-wrap">
                            <div class="id-qr-box">
                                ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR Code"/>` : ''}
                            </div>
                            <div class="id-qr-tag">ATTENDANCE QR</div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="id-ftr">
                        <span>OFFICIAL STUDENT PASS • NVTI BADDEGAMA</span>
                        <span>AUTHORIZED SIGNATURE</span>
                    </div>
                </div>
            </div>
        `;
    }

    let printWin = window.open('', '', 'width=950,height=750');
    printWin.document.write('<!DOCTYPE html><html><head><title>Student Passes (' + allStudentsData.length + ' Students)</title>');
    printWin.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">');
    printWin.document.write('<style>');
    printWin.document.write('@page { size: A4 portrait; margin: 8mm 6mm; }');
    printWin.document.write('* { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }');
    printWin.document.write('html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; font-family: "Segoe UI", Arial, sans-serif; }');
    
    // Grid container for multiple CR80 cards on A4
    printWin.document.write('.batch-print-container { width: 100%; display: flex; flex-wrap: wrap; justify-content: center; gap: 3.5mm 4mm; padding: 0; margin: 0 auto; }');
    
    // Dashed cutting line around each card for easy scissors cutting
    printWin.document.write('.card-cut-container { padding: 1.5mm; border: 1px dashed #64748b; border-radius: 4mm; display: inline-flex; align-items: center; justify-content: center; page-break-inside: avoid !important; break-inside: avoid !important; background: #ffffff !important; box-sizing: border-box; position: relative; }');
    
    // Exact CR80 physical card dimensions (85.6mm x 54mm) with break-inside avoid
    printWin.document.write('.id-card { width: 85.6mm; height: 54mm; background: #ffffff !important; border: 1.2px solid #2563eb; border-radius: 3.18mm; overflow: hidden; position: relative; display: flex; flex-direction: column; justify-content: space-between; box-shadow: none !important; page-break-inside: avoid !important; break-inside: avoid !important; box-sizing: border-box; }');
    
    // Header banner
    printWin.document.write('.id-hdr { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important; color: #ffffff; padding: 2mm 3mm; display: flex; align-items: center; justify-content: space-between; border-bottom: 1.5px solid #f59e0b; gap: 2.5mm; }');
    printWin.document.write('.id-hdr-logo { width: 6.5mm; height: 6.5mm; background: #ffffff; border-radius: 1.2mm; padding: 0.5mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }');
    printWin.document.write('.id-hdr-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }');
    printWin.document.write('.id-hdr-content { flex: 1; min-width: 0; }');
    printWin.document.write('.id-hdr-title { font-size: 6.8pt; font-weight: 800; letter-spacing: 0.2px; line-height: 1.15; color: #ffffff; }');
    printWin.document.write('.id-hdr-sub { font-size: 5pt; color: #93c5fd; font-weight: 600; }');
    printWin.document.write('.id-hdr-badge { background: #f59e0b; color: #0f172a; font-size: 5.2pt; font-weight: 800; padding: 1px 4px; border-radius: 2px; flex-shrink: 0; }');
    
    // Body layout
    printWin.document.write('.id-body { display: flex; align-items: center; justify-content: space-between; padding: 2mm 3mm; flex: 1; gap: 2.5mm; background: #ffffff !important; }');
    
    // Photo
    printWin.document.write('.id-photo-box { width: 20mm; height: 26mm; border: 1.2px solid #2563eb; border-radius: 1.5mm; overflow: hidden; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }');
    printWin.document.write('.id-photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }');
    printWin.document.write('.id-photo-ph { font-size: 14pt; color: #94a3b8; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }');
    
    // Details
    printWin.document.write('.id-info { flex: 1; min-width: 0; line-height: 1.18; text-align: left; }');
    printWin.document.write('.id-name { font-size: 7pt; font-weight: 800; color: #0f172a; white-space: normal !important; overflow: visible !important; word-break: break-word; text-transform: uppercase; line-height: 1.15; }');
    printWin.document.write('.id-cid { font-size: 6.8pt; font-weight: 800; color: #2563eb; margin: 0.3mm 0 0.8mm 0; }');
    printWin.document.write('.id-row { font-size: 5.6pt; color: #334155; display: flex; margin-bottom: 0.4mm; line-height: 1.15; }');
    printWin.document.write('.id-lbl { font-weight: 700; color: #64748b; width: 9.5mm; flex-shrink: 0; }');
    printWin.document.write('.id-val { font-weight: 600; color: #0f172a; white-space: normal !important; overflow: visible !important; word-break: break-word; }');
    
    // QR Code
    printWin.document.write('.id-qr-wrap { width: 22mm; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }');
    printWin.document.write('.id-qr-box { width: 20.5mm; height: 20.5mm; padding: 0.8mm; border: 0.8px solid #cbd5e1; border-radius: 1.5mm; background: #ffffff !important; display: flex !important; align-items: center !important; justify-content: center !important; margin: 0 auto; box-sizing: border-box; }');
    printWin.document.write('.id-qr-box img { width: 100% !important; height: 100% !important; display: block !important; margin: auto !important; object-fit: contain; image-rendering: pixelated; }');
    printWin.document.write('.id-qr-tag { font-size: 4.6pt; font-weight: 700; color: #64748b; margin-top: 0.6mm; letter-spacing: 0.2px; text-align: center; }');
    
    // Footer
    printWin.document.write('.id-ftr { background: #f1f5f9 !important; border-top: 0.8px solid #e2e8f0; padding: 1mm 3.2mm; font-size: 5pt; font-weight: 600; color: #475569; display: flex; justify-content: space-between; align-items: center; }');
    printWin.document.write('</style></head><body>');
    
    printWin.document.write('<div class="batch-print-container">');
    printWin.document.write(studentCardsHtml);
    printWin.document.write('</div>');
    
    // Image loading synchronization script
    printWin.document.write('<script>');
    printWin.document.write('var hasPrinted = false;');
    printWin.document.write('function triggerPrint() {');
    printWin.document.write('    if (hasPrinted) return;');
    printWin.document.write('    hasPrinted = true;');
    printWin.document.write('    window.focus();');
    printWin.document.write('    window.print();');
    printWin.document.write('}');
    printWin.document.write('function notifyParentAndClose() {');
    printWin.document.write('    try {');
    printWin.document.write('        if (window.opener && typeof window.opener.showPrintSuccessModal === "function") {');
    printWin.document.write('            window.opener.showPrintSuccessModal("All (' + allStudentsData.length + ') Students");');
    printWin.document.write('        }');
    printWin.document.write('    } catch(e) {}');
    printWin.document.write('    window.close();');
    printWin.document.write('}');
    printWin.document.write('window.onafterprint = notifyParentAndClose;');
    printWin.document.write('window.addEventListener("afterprint", notifyParentAndClose);');
    printWin.document.write('window.onload = function() {');
    printWin.document.write('    var images = document.images;');
    printWin.document.write('    var total = images.length;');
    printWin.document.write('    if (total === 0) { triggerPrint(); return; }');
    printWin.document.write('    var loaded = 0;');
    printWin.document.write('    function checkAll() {');
    printWin.document.write('        loaded++;');
    printWin.document.write('        if (loaded >= total) { setTimeout(triggerPrint, 250); }');
    printWin.document.write('    }');
    printWin.document.write('    for (var i = 0; i < total; i++) {');
    printWin.document.write('        if (images[i].complete) { checkAll(); }');
    printWin.document.write('        else { images[i].addEventListener("load", checkAll); images[i].addEventListener("error", checkAll); }');
    printWin.document.write('    }');
    printWin.document.write('};');
    printWin.document.write('setTimeout(triggerPrint, 2000);'); // Fallback trigger
    printWin.document.write('<\/script>');

    printWin.document.write('</body></html>');
    printWin.document.close();
}

function showPrintSuccessModal(studentName) {
    let nameEl = document.getElementById('print_succ_student_name');
    if (nameEl) nameEl.textContent = studentName || 'Student';
    let modal = document.getElementById('printSuccessModal');
    if (modal) modal.style.display = 'flex';
}

function closePrintSuccessModal() {
    let modal = document.getElementById('printSuccessModal');
    if (modal) modal.style.display = 'none';
}

// Alias for backwards compatibility
function printStudentQrCard() {
    printStudentIdCard();
}

function openStudentEditModal(id, fullname, nic, dob, address, gender, email, phone, course_id, batch_id) {
    document.getElementById('edit_student_id').value = id;
    document.getElementById('edit_s_fullname').value = fullname;
    document.getElementById('edit_s_nic').value = nic;
    document.getElementById('edit_s_dob').value = dob;
    document.getElementById('edit_s_address').value = address;
    
    if (gender === 'Male') document.getElementById('edit_s_gender_male').checked = true;
    else if (gender === 'Female') document.getElementById('edit_s_gender_female').checked = true;

    document.getElementById('edit_s_email').value = email;
    document.getElementById('edit_s_phone').value = phone;
    document.getElementById('edit_s_course_id').value = course_id;
    document.getElementById('edit_s_batch_id').value = batch_id;

    document.getElementById('editStudentModal').style.display = 'flex';
}

function closeStudentEditModal() {
    document.getElementById('editStudentModal').style.display = 'none';
}

function openStudentDeleteModal(id, name) {
    document.getElementById('delete_s_id').value = id;
    document.getElementById('delete_s_name').textContent = name;
    document.getElementById('deleteStudentModal').style.display = 'flex';
}

function closeStudentDeleteModal() {
    document.getElementById('deleteStudentModal').style.display = 'none';
}

function closeStudentSuccessModal() {
    document.getElementById('successStudentModal').style.display = 'none';
    window.location.href = 'students.php';
}

function closeUpdateStudentConfirmModal() {
    document.getElementById('updateStudentConfirmModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    let modals = ['studentModal', 'editStudentModal', 'deleteStudentModal', 'updateStudentConfirmModal', 'successStudentModal', 'qrScanStudentModal', 'printSuccessModal'];
    modals.forEach(modalId => {
        let modal = document.getElementById(modalId);
        if (event.target == modal) {
            if (modalId === 'qrScanStudentModal') {
                closeStudentQrScanner();
            } else {
                modal.style.display = 'none';
            }
        }
    });
}
</script>
