<?php
session_start();
require_once('../lib/db.php');

// Super Admin හෝ Admin කෙනෙක්ද කියලා check කිරීම
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Instructor status toggle කිරීම (Active <-> Inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $instructor_id = intval($_POST['instructor_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $update_q = "UPDATE users SET status = '$new_status' WHERE id = $instructor_id AND role = 'Instructor'";
    if (mysqli_query($conn, $update_q)) {
        header("Location: instructors.php");
        exit();
    }
}

// Delete confirmation Handle කිරීම
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $instructor_id = intval($_POST['instructor_id']);
    $delete_q = "DELETE FROM users WHERE id = $instructor_id AND role = 'Instructor'";
    if (mysqli_query($conn, $delete_q)) {
        header("Location: instructors.php");
        exit();
    }
}

// 1. Total Instructors Count
$q_total = "SELECT COUNT(*) as count FROM users WHERE role = 'Instructor'";
$total_count = mysqli_fetch_assoc(mysqli_query($conn, $q_total))['count'] ?? 0;

// 2. Active Instructors Count
$q_active = "SELECT COUNT(*) as count FROM users WHERE role = 'Instructor' AND status = 'Active'";
$active_count = mysqli_fetch_assoc(mysqli_query($conn, $q_active))['count'] ?? 0;

// 3. Inactive Instructors Count
$q_inactive = "SELECT COUNT(*) as count FROM users WHERE role = 'Instructor' AND status = 'Inactive'";
$inactive_count = mysqli_fetch_assoc(mysqli_query($conn, $q_inactive))['count'] ?? 0;

// Instructor ලැයිස්තුව
$q_list = "SELECT u.id, u.custom_id, u.fullname, u.username, u.nic, u.address, u.dob, u.gender, u.status, u.email, u.phone, u.role, u.image, u.course_id, c.course_name 
           FROM users u 
           LEFT JOIN courses c ON u.course_id = c.course_id 
           WHERE u.role = 'Instructor' 
           ORDER BY u.id DESC";
$r_list = mysqli_query($conn, $q_list);

// Select box සඳහා courses ටික අරගැනීම (Edit modal එක සඳහා)
$q_courses = "SELECT course_id, course_name FROM courses";
$r_courses = mysqli_query($conn, $q_courses);
$courses = [];
if ($r_courses && mysqli_num_rows($r_courses) > 0) {
    while ($row = mysqli_fetch_assoc($r_courses)) {
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
            <h2>Manage Instructors</h2>

            <!-- Cards Grid -->
            <div class="cards-grid">
                <div class="card-item">
                    <i class="fa-solid fa-chalkboard-user fa-2x" style="color: #2563eb;"></i>
                    <div>
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Instructors</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-user-check fa-2x" style="color: #10b981;"></i>
                    <div>
                        <h3><?php echo $active_count; ?></h3>
                        <p>Active Instructors</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-user-xmark fa-2x" style="color: #ef4444;"></i>
                    <div>
                        <h3><?php echo $inactive_count; ?></h3>
                        <p>Inactive Instructors</p>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="admin-header-row">
                <h3>Instructor List</h3>
                <a href="add_instructor.php" class="btn-add"><i class="fa-solid fa-plus"></i> Add New Instructor</a>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Instructor Name</th>
                            <th>Role</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($r_list) > 0): ?>
                            <?php while ($instructor = mysqli_fetch_assoc($r_list)): ?>
                                <?php 
                                    $img_src = !empty($instructor['image']) ? 'data:image/jpeg;base64,' . base64_encode($instructor['image']) : '';
                                    $course_display = !empty($instructor['course_name']) ? htmlspecialchars($instructor['course_name']) : 'N/A';
                                ?>
                                <tr class="admin-row" 
                                onclick="openInstructorModal(
                                    '<?php echo $instructor['id']; ?>', 
                                    '<?php echo htmlspecialchars($instructor['custom_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['username'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['fullname'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['nic'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['address'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['dob'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['gender'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['email'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['phone'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($instructor['status'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($course_display, ENT_QUOTES); ?>',
                                    '<?php echo $img_src; ?>'
                                )">
                                    <td class="admin-user-cell">
                                        <?php if (!empty($instructor['image'])): ?>
                                            <img src="<?php echo $img_src; ?>" alt="Profile" class="admin-dp">
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-user default-avatar"></i>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($instructor['fullname']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($instructor['role']); ?></td>
                                    <td><?php echo $course_display; ?></td>
                                    <td>
                                        <?php if ($instructor['status'] == 'Active'): ?>
                                            <span class="status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <!-- Table Edit Button -->
                                            <button type="button" class="btn-edit" onclick="openInstructorEditModal(
                                                '<?php echo $instructor['id']; ?>', 
                                                '<?php echo htmlspecialchars($instructor['fullname'], ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['username'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['nic'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['address'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['dob'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['gender'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['email'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($instructor['phone'] ?? '', ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($instructor['course_id'] ?? '', ENT_QUOTES); ?>'
                                            )">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button type="button" class="btn-delete" onclick="openInstructorDeleteModal(<?php echo $instructor['id']; ?>, '<?php echo htmlspecialchars($instructor['fullname'], ENT_QUOTES); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No instructors found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        
        <?php include('../include/footer.php'); ?>
    </div>
</div>

<!-- View Instructor Detail Modal -->
<div id="instructorModal" class="admin-modal">
    <div class="admin-modal-content">
        <span class="admin-modal-close" onclick="closeInstructorModal()">&times;</span>
        <h3 class="admin-modal-title">Instructor Details</h3>
        
        <div class="admin-modal-body" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
            <!-- Instructor Photo -->
            <div class="admin-modal-photo-box" id="i_photo_box" style="flex: 0 0 120px; text-align: center;">
                <img id="i_photo" src="" alt="Profile Photo" style="width: 100px; height: 100px; border-radius:12px; object-fit: cover; display:none;">
            </div>

            <!-- Instructor Details Grid -->
            <div class="admin-modal-details" style="flex: 1; min-width: 280px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <p><strong>ID:</strong> <span id="i_custom_id"></span></p>
                <p><strong>Username:</strong> <span id="i_username"></span></p>
                <p style="grid-column: span 2;"><strong>Full Name:</strong> <span id="i_fullname"></span></p>
                <p><strong>NIC Number:</strong> <span id="i_nic"></span></p>
                <p><strong>Gender:</strong> <span id="i_gender"></span></p>
                <p style="grid-column: span 2;"><strong>Address:</strong> <span id="i_address"></span></p>
                <p><strong>Date of Birth:</strong> <span id="i_dob"></span></p>
                <p><strong>Course:</strong> <span id="i_course"></span></p>
                <p><strong>Email:</strong> <span id="i_email"></span></p>
                <p><strong>Phone:</strong> <span id="i_phone"></span></p>
                <p style="grid-column: span 2;"><strong>Status:</strong> <span id="i_status"></span></p>
            </div>
        </div>

        <div class="admin-modal-footer" style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end;">
            <form method="POST">
                <input type="hidden" name="instructor_id" id="i_instructor_id">
                <input type="hidden" name="new_status" id="i_new_status">
                <button type="submit" name="toggle_status" id="btn_i_toggle_status" class="btn-mark-inactive">
                    <i class="fa-solid fa-user-xmark"></i> Mark as Inactive
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Instructor Modal -->
<div id="editInstructorModal" class="admin-modal">
    <div class="admin-modal-content" style="max-height: 90vh; overflow-y: auto;">
        <span class="admin-modal-close" onclick="closeInstructorEditModal()">&times;</span>
        <h3 class="admin-modal-title">Edit Instructor Details</h3>
        
        <form id="editInstructorForm" action="update_instructor_process.php" method="POST" enctype="multipart/form-data" onsubmit="return validateInstructorEditForm()">
            <input type="hidden" name="instructor_id" id="edit_i_id">

            <div class="modal-form-group">
                <label>Full Name</label>
                <input type="text" name="fullname" id="edit_i_fullname" class="modal-input" placeholder="Enter Fullname" required>
            </div>

            <div class="modal-form-group">
                <label>Username</label>
                <input type="text" name="username" id="edit_i_username" class="modal-input" placeholder="Enter Username" required>
            </div>

            <div class="modal-form-group">
                <label>NIC Number</label>
                <input type="text" name="nic" id="edit_i_nic" class="modal-input" placeholder="Enter NIC Number" required>
            </div>

            <div class="modal-form-group">
                <label>Address</label>
                <input type="text" name="address" id="edit_i_address" class="modal-input" placeholder="Enter Address">
            </div>

            <div class="modal-form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="edit_i_dob" class="modal-input">
            </div>

            <div class="modal-form-group">
                <label>Gender</label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label><input type="radio" name="gender" id="edit_i_gender_male" value="Male"> Male</label>
                    <label><input type="radio" name="gender" id="edit_i_gender_female" value="Female"> Female</label>
                </div>
            </div>

            <div class="modal-form-group">
                <label>Assign Course</label>
                <select name="course_id" id="edit_i_course_id" class="modal-input">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_id']); ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-form-group">
                <label>E-mail Address</label>
                <input type="email" name="email" id="edit_i_email" class="modal-input" placeholder="Enter Email Address" required>
            </div>

            <div class="modal-form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" id="edit_i_phone" class="modal-input" placeholder="Enter Phone Number" required>
            </div>

            <div class="modal-form-group">
                <label>Password (Leave blank to keep current password)</label>
                <input type="password" name="password" id="edit_i_password" class="modal-input" placeholder="Enter New Password">
            </div>

            <div class="modal-form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="edit_i_confirm_password" class="modal-input" placeholder="Retype Password">
            </div>

            <div class="modal-form-group">
                <label>Profile Picture</label>
                <input type="file" name="profile_image" id="edit_i_profile_image" accept="image/*" class="modal-input">
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="submit" name="update_instructor" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Instructor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Confirmation Modal -->
<div id="updateInstructorConfirmModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeUpdateInstructorConfirmModal()">&times;</span>
        <i class="fa-solid fa-circle-question fa-3x" style="color: #2563eb; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Update</h3>
        <p>Are you sure you want to update this instructor's details?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" onclick="closeUpdateInstructorConfirmModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: transparent; cursor: pointer;">Cancel</button>
            <button type="button" onclick="submitInstructorEditForm()" style="padding: 8px 16px; border-radius: 6px; border: none; background: #2563eb; color: white; font-weight: 600; cursor: pointer;">Yes, Update</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteInstructorModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeInstructorDeleteModal()">&times;</span>
        <i class="fa-solid fa-triangle-exclamation fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Delete</h3>
        <p>Are you sure you want to delete <strong id="delete_i_name"></strong>?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" class="del-btn" onclick="closeInstructorDeleteModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background-color:light-gray ; cursor: pointer;">Cancel</button>
            <form method="POST">
                <input type="hidden" name="instructor_id" id="delete_i_id">
                <button type="submit" name="confirm_delete" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Update Success Modal -->
<?php if (isset($_GET['updated']) && $_GET['updated'] === 'success'): ?>
<div id="successInstructorModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981; margin-bottom: 15px;"></i>
        <h3>Update Successful!</h3>
        <p style="margin-top: 8px; color: #64748b;">Instructor details have been updated successfully.</p>
        <button type="button" onclick="closeInstructorSuccessModal()" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
function openInstructorModal(id, custom_id, username, fullname, nic, address, dob, gender, email, phone, status, course, image) {
    document.getElementById('i_custom_id').textContent = custom_id;
    document.getElementById('i_username').textContent = username;
    document.getElementById('i_fullname').textContent = fullname;
    document.getElementById('i_nic').textContent = nic;
    document.getElementById('i_address').textContent = address;
    document.getElementById('i_dob').textContent = dob;
    document.getElementById('i_gender').textContent = gender;
    document.getElementById('i_email').textContent = email;
    document.getElementById('i_phone').textContent = phone;
    document.getElementById('i_course').textContent = course;
    
    let statusSpan = document.getElementById('i_status');
    statusSpan.textContent = status;
    statusSpan.className = status === 'Active' ? 'status-active' : 'status-inactive';

    let photo = document.getElementById('i_photo');
    if (image) {
        photo.src = image;
        photo.style.display = 'block';
    } else {
        photo.style.display = 'none';
    }

    document.getElementById('i_instructor_id').value = id;
    
    let btnToggle = document.getElementById('btn_i_toggle_status');
    if (status === 'Active') {
        document.getElementById('i_new_status').value = 'Inactive';
        btnToggle.innerHTML = '<i class="fa-solid fa-user-xmark"></i> Mark as Inactive';
        btnToggle.className = 'btn-mark-inactive';
    } else {
        document.getElementById('i_new_status').value = 'Active';
        btnToggle.innerHTML = '<i class="fa-solid fa-user-check"></i> Mark as Active';
        btnToggle.className = 'btn-mark-active';
    }

    document.getElementById('instructorModal').style.display = 'flex';
}

function closeInstructorModal() {
    document.getElementById('instructorModal').style.display = 'none';
}

function openInstructorEditModal(id, fullname, username, nic, address, dob, gender, email, phone, course_id) {
    document.getElementById('edit_i_id').value = id;
    document.getElementById('edit_i_fullname').value = fullname;
    document.getElementById('edit_i_username').value = username;
    document.getElementById('edit_i_nic').value = nic;
    document.getElementById('edit_i_address').value = address;
    document.getElementById('edit_i_dob').value = dob;
    
    if (gender === 'Male') document.getElementById('edit_i_gender_male').checked = true;
    else if (gender === 'Female') document.getElementById('edit_i_gender_female').checked = true;

    document.getElementById('edit_i_email').value = email;
    document.getElementById('edit_i_phone').value = phone;
    document.getElementById('edit_i_course_id').value = course_id;

    document.getElementById('editInstructorModal').style.display = 'flex';
}

function closeInstructorEditModal() {
    document.getElementById('editInstructorModal').style.display = 'none';
}

function openInstructorDeleteModal(id, name) {
    document.getElementById('delete_i_id').value = id;
    document.getElementById('delete_i_name').textContent = name;
    document.getElementById('deleteInstructorModal').style.display = 'flex';
}

function closeInstructorDeleteModal() {
    document.getElementById('deleteInstructorModal').style.display = 'none';
}

function closeInstructorSuccessModal() {
    document.getElementById('successInstructorModal').style.display = 'none';
    window.location.href = 'instructors.php';
}

function validateInstructorEditForm() {
    let pw = document.getElementById('edit_i_password').value;
    let pwConfirm = document.getElementById('edit_i_confirm_password').value;
    
    if (pw !== '' && pw !== pwConfirm) {
        alert("Passwords do not match!");
        return false;
    }
    
    document.getElementById('updateInstructorConfirmModal').style.display = 'flex';
    return false; // Prevent immediate submission
}

function closeUpdateInstructorConfirmModal() {
    document.getElementById('updateInstructorConfirmModal').style.display = 'none';
}

function submitInstructorEditForm() {
    document.getElementById('editInstructorForm').submit();
}

// Close modals when clicking outside
window.onclick = function(event) {
    let modals = ['instructorModal', 'editInstructorModal', 'deleteInstructorModal', 'updateInstructorConfirmModal', 'successInstructorModal'];
    modals.forEach(modalId => {
        let modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}
</script>
