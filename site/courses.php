<?php
session_start();
require_once('../lib/db.php');

// Super Admin හෝ Admin කෙනෙක්ද කියලා check කිරීම
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Delete confirmation Handle කිරීම (Course)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $course_id_delete = intval($_POST['delete_id']);
    $delete_q = "DELETE FROM courses WHERE id = $course_id_delete";
    if (mysqli_query($conn, $delete_q)) {
        header("Location: courses.php");
        exit();
    }
}

// Delete confirmation Handle කිරීම (Batch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete_batch'])) {
    $batch_id_delete = intval($_POST['delete_batch_id']);
    $delete_q = "DELETE FROM batches WHERE id = $batch_id_delete";
    if (mysqli_query($conn, $delete_q)) {
        header("Location: courses.php");
        exit();
    }
}

// Batch status toggle කිරීම
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_batch_status'])) {
    $b_id = intval($_POST['toggle_batch_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_batch_status']);

    $batch_code = "";
    $bq = mysqli_query($conn, "SELECT batch_id FROM batches WHERE id = $b_id");
    if ($bq && mysqli_num_rows($bq) > 0) {
        $batch_code = mysqli_fetch_assoc($bq)['batch_id'];
    }

    $update_q = "UPDATE batches SET status = '$new_status' WHERE id = $b_id";
    if (mysqli_query($conn, $update_q)) {
        // Cascade to students
        if ($batch_code !== "") {
            mysqli_query($conn, "UPDATE students SET status = '$new_status' WHERE batch_id = '$batch_code'");
        }
        header("Location: courses.php");
        exit();
    }
}

// 1. Total Courses Count
$q_total = "SELECT COUNT(*) as count FROM courses";
$total_count = mysqli_fetch_assoc(mysqli_query($conn, $q_total))['count'] ?? 0;

// 2. Total Batches Count
$q_batches = "SELECT COUNT(*) as count FROM batches";
// Create batches table if not exists (in case it's not created yet in the DB)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS batches (id INT AUTO_INCREMENT PRIMARY KEY, batch_id VARCHAR(30), batch_name VARCHAR(50), course_id VARCHAR(30), start_date DATE, end_date DATE, status VARCHAR(30) DEFAULT 'Active')");

$total_batches = mysqli_fetch_assoc(mysqli_query($conn, $q_batches))['count'] ?? 0;

// Courses ලැයිස්තුව
$q_list = "SELECT id, course_id, course_name, course_duration, instructor_name FROM courses ORDER BY id DESC";
$r_list = mysqli_query($conn, $q_list);

// Batches ලැයිස්තුව
$q_b_list = "SELECT b.id, b.batch_id, b.batch_name, b.course_id, c.course_name, b.start_date, b.end_date, b.status 
             FROM batches b 
             LEFT JOIN courses c ON b.course_id = c.course_id 
             ORDER BY b.id DESC";
$r_b_list = mysqli_query($conn, $q_b_list);

// Instructors for dropdowns
$q_instructors = "SELECT fullname FROM users WHERE role = 'Instructor'";
$r_instructors = mysqli_query($conn, $q_instructors);
$instructors = [];
if ($r_instructors && mysqli_num_rows($r_instructors) > 0) {
    while ($row = mysqli_fetch_assoc($r_instructors)) {
        $instructors[] = $row['fullname'];
    }
}

// Courses for Batch dropdowns
$courses = [];
$r_c_list = mysqli_query($conn, "SELECT course_id, course_name FROM courses");
if ($r_c_list && mysqli_num_rows($r_c_list) > 0) {
    while ($row = mysqli_fetch_assoc($r_c_list)) {
        $courses[] = $row;
    }
}

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <h2>Manage Courses & Batches</h2>

            <!-- Cards Grid -->
            <div class="cards-grid">
                <div class="card-item">
                    <i class="fa-solid fa-book-open fa-2x" style="color: #2563eb;"></i>
                    <div>
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Courses</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-users-rectangle fa-2x" style="color: #8b5cf6;"></i>
                    <div>
                        <h3><?php echo $total_batches; ?></h3>
                        <p>Total Batches</p>
                    </div>
                </div>
            </div>

            <!-- Courses Table Section -->
            <div class="admin-header-row" style="margin-top: 30px;">
                <h3>Course List</h3>
                <a href="add_course.php" class="btn-add"><i class="fa-solid fa-plus"></i> Add New Course</a>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Course ID</th>
                            <th>Course Name</th>
                            <th>Duration</th>
                            <th>Instructor Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($r_list) > 0): ?>
                            <?php while ($course = mysqli_fetch_assoc($r_list)): ?>
                                <tr class="admin-row" 
                                onclick="openCourseModal(
                                    '<?php echo $course['id']; ?>', 
                                    '<?php echo htmlspecialchars($course['course_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($course['course_name'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($course['course_duration'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($course['instructor_name'] ?? '', ENT_QUOTES); ?>'
                                )">
                                    <td><?php echo htmlspecialchars($course['course_id']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_duration']); ?></td>
                                    <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                                    <td onclick="event.stopPropagation();">
                                        <!-- Table Edit Button -->
                                            <button type="button" class="btn-edit" onclick="openCourseEditModal(
                                                '<?php echo $course['id']; ?>', 
                                                '<?php echo htmlspecialchars($course['course_id'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($course['course_name'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($course['course_duration'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($course['instructor_name'] ?? '', ENT_QUOTES); ?>'
                                            )">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button type="button" class="btn-delete" onclick="openCourseDeleteModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name'], ENT_QUOTES); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No courses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Batches Table Section -->
            <div class="admin-header-row" style="margin-top: 40px;">
                <h3>Batch List</h3>
                <a href="add_batch.php" class="btn-add" style="background-color: #8b5cf6;"><i class="fa-solid fa-plus"></i> Add New Batch</a>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Batch Name</th>
                            <th>Course</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($r_b_list && mysqli_num_rows($r_b_list) > 0): ?>
                            <?php while ($batch = mysqli_fetch_assoc($r_b_list)): 
                                $b_status = $batch['status'] ?? 'Active';
                                $is_active = ($b_status === 'Active');
                                $display_status = $is_active ? 'Active' : 'Passed Out';
                                $next_status = $is_active ? 'Passed Out' : 'Active';
                                $status_badge_class = $is_active ? 'status-active' : 'status-passed-out';
                            ?>
                                <tr class="admin-row" 
                                onclick="openBatchModal(
                                    '<?php echo $batch['id']; ?>', 
                                    '<?php echo htmlspecialchars($batch['batch_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($batch['batch_name'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($batch['course_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($batch['course_name'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($batch['start_date'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($batch['end_date'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($display_status, ENT_QUOTES); ?>'
                                )">
                                    <td><?php echo htmlspecialchars($batch['batch_id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($batch['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($batch['start_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($batch['end_date'] ?? ''); ?></td>
                                    <td>
                                        <span class="<?php echo $status_badge_class; ?>"><?php echo $display_status; ?></span>
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <!-- Edit Batch Form -->
                                        <button type="button" class="btn-edit" title="Edit Batch" onclick="openBatchEditModal(
                                            '<?php echo $batch['id']; ?>', 
                                            '<?php echo htmlspecialchars($batch['batch_name'] ?? '', ENT_QUOTES); ?>', 
                                            '<?php echo htmlspecialchars($batch['course_id'] ?? '', ENT_QUOTES); ?>', 
                                            '<?php echo htmlspecialchars($batch['start_date'] ?? '', ENT_QUOTES); ?>', 
                                            '<?php echo htmlspecialchars($batch['end_date'] ?? '', ENT_QUOTES); ?>'
                                        )">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Update Batch Status Form -->
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('<?php echo $is_active ? 'Are you sure you want to mark this batch and all its students as Passed Out?' : 'Are you sure you want to reset this batch and all its students to Active?'; ?>');">
                                            <input type="hidden" name="toggle_batch_id" value="<?php echo $batch['id']; ?>">
                                            <input type="hidden" name="new_batch_status" value="<?php echo $next_status; ?>">
                                            <button type="submit" name="toggle_batch_status" class="btn-edit" style="background-color: <?php echo $is_active ? '#8b5cf6' : '#10b981'; ?>; color: #fff;" title="<?php echo $is_active ? 'Mark as Passed Out' : 'Reset to Active'; ?>">
                                                <i class="fa-solid <?php echo $is_active ? 'fa-graduation-cap' : 'fa-rotate-left'; ?>"></i>
                                            </button>
                                        </form>
                                        <!-- Delete Batch Form -->
                                        <button type="button" class="btn-delete" title="Delete Batch" onclick="openBatchDeleteModal(<?php echo $batch['id']; ?>, '<?php echo htmlspecialchars($batch['batch_name'], ENT_QUOTES); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No batches found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        
        <?php include('../include/footer.php'); ?>
    </div>
</div>

<!-- View Course Detail Modal -->
<div id="courseModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 500px;">
        <span class="admin-modal-close" onclick="closeCourseModal()">&times;</span>
        <h3 class="admin-modal-title">Course Details</h3>
        
        <div class="admin-modal-body" style="display: flex; gap: 20px; flex-direction: column;">
            <div class="admin-modal-details" style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                <p><strong>Course ID:</strong> <span id="v_course_id"></span></p>
                <p><strong>Course Name:</strong> <span id="v_course_name"></span></p>
                <p><strong>Course Duration:</strong> <span id="v_course_duration"></span></p>
                <p><strong>Instructor Name:</strong> <span id="v_instructor_name"></span></p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editCourseModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <span class="admin-modal-close" onclick="closeCourseEditModal()">&times;</span>
        <h3 class="admin-modal-title">Edit Course Details</h3>
        
        <form id="editCourseForm" action="update_course_process.php" method="POST">
            <input type="hidden" name="id" id="edit_c_id">

            <div class="modal-form-group">
                <label>Course Name</label>
                <input type="text" name="course_name" id="edit_c_course_name" class="modal-input" placeholder="Enter Course Name" required>
            </div>

            <div class="modal-form-group">
                <label>Course Duration</label>
                <select name="course_duration" id="edit_c_course_duration" class="modal-input">
                    <option value="">-- Select Duration --</option>
                    <option value="6 Months">6 Months</option>
                    <option value="1 Year">1 Year</option>
                </select>
            </div>

            <div class="modal-form-group">
                <label>Instructor Name</label>
                <select name="instructor_name" id="edit_c_instructor_name" class="modal-input">
                    <option value="">-- Select Instructor --</option>
                    <?php foreach ($instructors as $inst): ?>
                        <option value="<?php echo htmlspecialchars($inst); ?>"><?php echo htmlspecialchars($inst); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="button" onclick="document.getElementById('updateCourseConfirmModal').style.display='flex';" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Course
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Confirmation Modal -->
<div id="updateCourseConfirmModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeUpdateCourseConfirmModal()">&times;</span>
        <i class="fa-solid fa-circle-question fa-3x" style="color: #2563eb; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Update</h3>
        <p>Are you sure you want to update this course details?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" onclick="closeUpdateCourseConfirmModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: transparent; cursor: pointer;">Cancel</button>
            <button type="button" onclick="document.getElementById('editCourseForm').submit();" style="padding: 8px 16px; border-radius: 6px; border: none; background: #2563eb; color: white; font-weight: 600; cursor: pointer;">Yes, Update</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteCourseModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeCourseDeleteModal()">&times;</span>
        <i class="fa-solid fa-triangle-exclamation fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Delete</h3>
        <p>Are you sure you want to delete <strong id="delete_c_name"></strong>?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" class="del-btn" onclick="closeCourseDeleteModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background-color:light-gray ; cursor: pointer;">Cancel</button>
            <form method="POST">
                <input type="hidden" name="delete_id" id="delete_c_id">
                <button type="submit" name="confirm_delete" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- View Batch Detail Modal -->
<div id="batchModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 600px;">
        <span class="admin-modal-close" onclick="closeBatchModal()">&times;</span>
        <h3 class="admin-modal-title">Batch Details</h3>
        
        <div class="admin-modal-body" style="display: flex; gap: 20px; flex-direction: column;">
            <div class="admin-modal-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <p><strong>Batch ID:</strong> <span id="v_batch_id"></span></p>
                <p><strong>Batch Name:</strong> <span id="v_batch_name"></span></p>
                <p><strong>Course Name:</strong> <span id="v_batch_course"></span></p>
                <p><strong>Status:</strong> <span id="v_batch_status"></span></p>
                <p><strong>Start Date:</strong> <span id="v_batch_start"></span></p>
                <p><strong>End Date:</strong> <span id="v_batch_end"></span></p>
            </div>
            
            <h4 style="margin-top: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Students in this Batch</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <table class="admin-table" style="width: 100%; font-size: 0.9em;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="batch_students_tbody">
                        <!-- Students will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div id="editBatchModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 500px;">
        <span class="admin-modal-close" onclick="closeBatchEditModal()">&times;</span>
        <h3 class="admin-modal-title">Edit Batch Details</h3>
        
        <form action="update_batch_process.php" method="POST">
            <input type="hidden" name="id" id="edit_b_id">

            <div class="modal-form-group">
                <label>Batch Name</label>
                <input type="text" name="batch_name" id="edit_b_batch_name" class="modal-input" required>
            </div>

            <div class="modal-form-group">
                <label>Assign Course</label>
                <select name="course_id" id="edit_b_course_id" class="modal-input" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_id']); ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" id="edit_b_start_date" class="modal-input">
            </div>

            <div class="modal-form-group">
                <label>End Date</label>
                <input type="date" name="end_date" id="edit_b_end_date" class="modal-input">
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="submit" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Batch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Batch Confirmation Modal -->
<div id="deleteBatchModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeBatchDeleteModal()">&times;</span>
        <i class="fa-solid fa-triangle-exclamation fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Delete Batch</h3>
        <p>Are you sure you want to delete <strong id="delete_b_name"></strong>?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" class="del-btn" onclick="closeBatchDeleteModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background-color:light-gray ; cursor: pointer;">Cancel</button>
            <form method="POST">
                <input type="hidden" name="delete_batch_id" id="delete_b_id">
                <button type="submit" name="confirm_delete_batch" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Update Success Modal -->
<?php if (isset($_GET['updated']) && $_GET['updated'] === 'success'): ?>
<div id="successCourseModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981; margin-bottom: 15px;"></i>
        <h3>Update Successful!</h3>
        <p style="margin-top: 8px; color: #64748b;">Course details have been updated successfully.</p>
        <button type="button" onclick="closeCourseSuccessModal()" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
function openCourseModal(id, course_id, course_name, duration, instructor) {
    document.getElementById('v_course_id').textContent = course_id;
    document.getElementById('v_course_name').textContent = course_name;
    document.getElementById('v_course_duration').textContent = duration;
    document.getElementById('v_instructor_name').textContent = instructor;
    
    document.getElementById('courseModal').style.display = 'flex';
}

function closeCourseModal() {
    document.getElementById('courseModal').style.display = 'none';
}

function openCourseEditModal(id, course_id, course_name, duration, instructor) {
    document.getElementById('edit_c_id').value = id;
    document.getElementById('edit_c_course_name').value = course_name;
    document.getElementById('edit_c_course_duration').value = duration;
    document.getElementById('edit_c_instructor_name').value = instructor;

    document.getElementById('editCourseModal').style.display = 'flex';
}

function closeCourseEditModal() {
    document.getElementById('editCourseModal').style.display = 'none';
}

function openBatchModal(id, batch_id, batch_name, course_id, course_name, start_date, end_date, status) {
    document.getElementById('v_batch_id').textContent = batch_id;
    document.getElementById('v_batch_name').textContent = batch_name;
    document.getElementById('v_batch_course').textContent = course_name;
    document.getElementById('v_batch_start').textContent = start_date;
    document.getElementById('v_batch_end').textContent = end_date;
    
    let statusSpan = document.getElementById('v_batch_status');
    let isActive = (status === 'Active' || status === 'Not Pass Out');
    statusSpan.textContent = isActive ? 'Active' : 'Passed Out';
    statusSpan.className = isActive ? 'status-active' : 'status-passed-out';
    
    // Fetch students via AJAX
    document.getElementById('batch_students_tbody').innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "get_batch_students.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            document.getElementById('batch_students_tbody').innerHTML = xhr.responseText;
        }
    };
    xhr.send("batch_id=" + encodeURIComponent(batch_id));

    document.getElementById('batchModal').style.display = 'flex';
}

function closeBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
}

function openBatchEditModal(id, batch_name, course_id, start_date, end_date) {
    document.getElementById('edit_b_id').value = id;
    document.getElementById('edit_b_batch_name').value = batch_name;
    document.getElementById('edit_b_course_id').value = course_id;
    document.getElementById('edit_b_start_date').value = start_date;
    document.getElementById('edit_b_end_date').value = end_date;

    document.getElementById('editBatchModal').style.display = 'flex';
}

function closeBatchEditModal() {
    document.getElementById('editBatchModal').style.display = 'none';
}

function openCourseDeleteModal(id, name) {
    document.getElementById('delete_c_id').value = id;
    document.getElementById('delete_c_name').textContent = name;
    document.getElementById('deleteCourseModal').style.display = 'flex';
}

function closeCourseDeleteModal() {
    document.getElementById('deleteCourseModal').style.display = 'none';
}

function openBatchDeleteModal(id, name) {
    document.getElementById('delete_b_id').value = id;
    document.getElementById('delete_b_name').textContent = name;
    document.getElementById('deleteBatchModal').style.display = 'flex';
}

function closeBatchDeleteModal() {
    document.getElementById('deleteBatchModal').style.display = 'none';
}

function closeCourseSuccessModal() {
    document.getElementById('successCourseModal').style.display = 'none';
    window.location.href = 'courses.php';
}

function closeUpdateCourseConfirmModal() {
    document.getElementById('updateCourseConfirmModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    let modals = ['courseModal', 'editCourseModal', 'deleteCourseModal', 'updateCourseConfirmModal', 'successCourseModal', 'deleteBatchModal'];
    modals.forEach(modalId => {
        let modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}
</script>
