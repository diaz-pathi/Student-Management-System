<?php
session_start();
require_once('../lib/db.php');

// Super Admin කෙනෙක්ද කියලා check කිරීම
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin') {
    header("Location: dashboard.php");
    exit();
}

// Admin Details Update කිරීම Handle කිරීම
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_update'])) {
    $admin_id = intval($_POST['admin_id']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $admin_role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $update_q = "UPDATE users SET fullname = '$fullname', role = '$admin_role', status = '$status' WHERE id = $admin_id";
    if (mysqli_query($conn, $update_q)) {
        header("Location: admins.php?updated=success");
        exit();
    }
}

// Admin status toggle කිරීම (Active <-> Inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $admin_id = intval($_POST['admin_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $update_q = "UPDATE users SET status = '$new_status' WHERE id = $admin_id AND role NOT IN ('Super Admin', 'SuperAdmin')";
    if (mysqli_query($conn, $update_q)) {
        header("Location: admins.php");
        exit();
    }
}

// Delete confirmation Handle කිරීම
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $admin_id = intval($_POST['admin_id']);
    $delete_q = "DELETE FROM users WHERE id = $admin_id AND role NOT IN ('Super Admin', 'SuperAdmin')";
    if (mysqli_query($conn, $delete_q)) {
        header("Location: admins.php");
        exit();
    }
}

// 1. Super Admin Count
$q_sa = "SELECT COUNT(*) as count FROM users WHERE role IN ('Super Admin', 'SuperAdmin')";
$sa_count = mysqli_fetch_assoc(mysqli_query($conn, $q_sa))['count'] ?? 0;

// 2. Admin Count
$q_a = "SELECT COUNT(*) as count FROM users WHERE role = 'Admin'";
$a_count = mysqli_fetch_assoc(mysqli_query($conn, $q_a))['count'] ?? 0;

// 3. Active Admin Count
$q_active = "SELECT COUNT(*) as count FROM users WHERE role IN ('Admin', 'Super Admin', 'SuperAdmin') AND status = 'Active'";
$active_count = mysqli_fetch_assoc(mysqli_query($conn, $q_active))['count'] ?? 0;

// 4. Inactive Admin Count
$q_inactive = "SELECT COUNT(*) as count FROM users WHERE role IN ('Admin', 'Super Admin', 'SuperAdmin') AND status = 'Inactive'";
$inactive_count = mysqli_fetch_assoc(mysqli_query($conn, $q_inactive))['count'] ?? 0;

// Admin ලැයිස්තුව (custom_id එකද ඇතුළත්ව)
$q_list = "SELECT id, custom_id, fullname, username, nic, address, dob, gender, status, email, phone, role, image FROM users WHERE role IN ('Admin', 'Super Admin', 'SuperAdmin') ORDER BY role DESC";
$r_list = mysqli_query($conn, $q_list);

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <h2>Manage Admins</h2>

            <!-- Cards Grid -->
            <div class="cards-grid">
                <div class="card-item">
                    <i class="fa-solid fa-user-shield fa-2x" style="color: #2563eb;"></i>
                    <div>
                        <h3><?php echo $sa_count; ?></h3>
                        <p>Super Admins</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-user-gear fa-2x" style="color: #8b5cf6;"></i>
                    <div>
                        <h3><?php echo $a_count; ?></h3>
                        <p>Admins</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-user-check fa-2x" style="color: #10b981;"></i>
                    <div>
                        <h3><?php echo $active_count; ?></h3>
                        <p>Total Active Admins</p>
                    </div>
                </div>
                <div class="card-item">
                    <i class="fa-solid fa-user-xmark fa-2x" style="color: #ef4444;"></i>
                    <div>
                        <h3><?php echo $inactive_count; ?></h3>
                        <p>Inactive Admins</p>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="admin-header-row">
                <h3>Admin List</h3>
                <a href="add_admin.php" class="btn-add"><i class="fa-solid fa-plus"></i> Add New Admin</a>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Admin Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($r_list) > 0): ?>
                            <?php while ($admin = mysqli_fetch_assoc($r_list)): ?>
                                <?php 
                                    $img_src = !empty($admin['image']) ? 'data:image/jpeg;base64,' . base64_encode($admin['image']) : '';
                                ?>
                                <tr class="admin-row" 
                                onclick="openAdminModal(
                                    '<?php echo $admin['id']; ?>', 
                                    '<?php echo htmlspecialchars($admin['custom_id'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['username'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['fullname'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['nic'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['address'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['dob'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['gender'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['role'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['email'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['phone'] ?? '', ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($admin['status'], ENT_QUOTES); ?>', 
                                    '<?php echo $img_src; ?>'
                                )">
                                    <td class="admin-user-cell">
                                        <?php if (!empty($admin['image'])): ?>
                                            <img src="<?php echo $img_src; ?>" alt="Profile" class="admin-dp">
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-user default-avatar"></i>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($admin['fullname']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['role']); ?></td>
                                    <td>
                                        <?php if ($admin['status'] == 'Active'): ?>
                                            <span class="status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <!-- Table Edit Button -->
                                            <button type="button" class="btn-edit" onclick="openEditModal(
                                                '<?php echo $admin['id']; ?>', 
                                                '<?php echo htmlspecialchars($admin['fullname'], ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['username'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['nic'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['address'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['dob'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['gender'] ?? '', ENT_QUOTES); ?>', 
                                                 
                                                '<?php echo htmlspecialchars($admin['email'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($admin['phone'] ?? '', ENT_QUOTES); ?>'
                                            )">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        <?php if ($admin['role'] !== 'Super Admin' && $admin['role'] !== 'SuperAdmin'): ?>
                                            <button type="button" class="btn-delete" onclick="openDeleteModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['fullname'], ENT_QUOTES); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No admins found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        
        <?php include('../include/footer.php'); ?>
    </div>
</div>

<!-- View Admin Detail Modal -->
<div id="adminModal" class="admin-modal">
    <div class="admin-modal-content">
        <span class="admin-modal-close" onclick="closeAdminModal()">&times;</span>
        <h3 class="admin-modal-title">Admin Details</h3>
        
        <div class="admin-modal-body" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
            <!-- Admin Photo -->
            <div class="admin-modal-photo-box" id="m_photo_box" style="flex: 0 0 120px; text-align: center;">
                <img id="m_photo" src="" alt="Profile Photo" style="width: 100px; height: 100px; border-radius:12px; object-fit: cover; display:none;">
            </div>

            <!-- Admin Details Grid -->
            <div class="admin-modal-details" style="flex: 1; min-width: 280px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <p><strong>ID:</strong> <span id="m_custom_id"></span></p>
                <p><strong>Username:</strong> <span id="m_username"></span></p>
                <p style="grid-column: span 2;"><strong>Full Name:</strong> <span id="m_fullname"></span></p>
                <p><strong>NIC Number:</strong> <span id="m_nic"></span></p>
                <p><strong>Gender:</strong> <span id="m_gender"></span></p>
                <p style="grid-column: span 2;"><strong>Address:</strong> <span id="m_address"></span></p>
                <p><strong>Date of Birth:</strong> <span id="m_dob"></span></p>
                <p><strong>Role:</strong> <span id="m_role"></span></p>
                <p><strong>Email:</strong> <span id="m_email"></span></p>
                <p><strong>Phone:</strong> <span id="m_phone"></span></p>
                <p style="grid-column: span 2;"><strong>Status:</strong> <span id="m_status"></span></p>
            </div>
        </div>

        <div class="admin-modal-footer" style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end;">
            <form method="POST">
                <input type="hidden" name="admin_id" id="m_admin_id">
                <input type="hidden" name="new_status" id="m_new_status">
                <button type="submit" name="toggle_status" id="btn_toggle_status" class="btn-mark-inactive">
                    <i class="fa-solid fa-user-xmark"></i> Mark as Inactive
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div id="editAdminModal" class="admin-modal">
    <div class="admin-modal-content" style="max-height: 90vh; overflow-y: auto;">
        <span class="admin-modal-close" onclick="closeEditModal()">&times;</span>
        <h3 class="admin-modal-title">Edit Admin Details</h3>
        
        <form id="editAdminForm" action="update_admin_process.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEditForm()">
            <input type="hidden" name="admin_id" id="edit_admin_id">

            <div class="modal-form-group">
                <label>Full Name</label>
                <input type="text" name="fullname" id="edit_fullname" class="modal-input" placeholder="Enter Fullname" required>
            </div>

            <div class="modal-form-group">
                <label>Username</label>
                <input type="text" name="username" id="edit_username" class="modal-input" placeholder="Enter Username" required>
            </div>

            <div class="modal-form-group">
                <label>NIC Number</label>
                <input type="text" name="nic" id="edit_nic" class="modal-input" placeholder="Enter NIC Number" required>
            </div>

            <div class="modal-form-group">
                <label>Address</label>
                <input type="text" name="address" id="edit_address" class="modal-input" placeholder="Enter Address">
            </div>

            <div class="modal-form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="edit_dob" class="modal-input">
            </div>

            <div class="modal-form-group">
                <label>Gender</label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label><input type="radio" name="gender" id="edit_gender_male" value="Male"> Male</label>
                    <label><input type="radio" name="gender" id="edit_gender_female" value="Female"> Female</label>
                </div>
            </div>

            

            <div class="modal-form-group">
                <label>E-mail Address</label>
                <input type="email" name="email" id="edit_email" class="modal-input" placeholder="Enter Email Address" required>
            </div>

            <div class="modal-form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" id="edit_phone" class="modal-input" placeholder="Enter Phone Number" required>
            </div>

            <div class="modal-form-group">
                <label>Password (Leave blank to keep current password)</label>
                <input type="password" name="password" id="edit_password" class="modal-input" placeholder="Enter New Password">
            </div>

            <div class="modal-form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="edit_confirm_password" class="modal-input" placeholder="Retype Password">
            </div>

            <div class="modal-form-group">
                <label>Profile Picture</label>
                <input type="file" name="profile_image" id="edit_profile_image" accept="image/*" class="modal-input">
            </div>

            <div class="admin-modal-footer" style="margin-top: 20px;">
                <button type="submit" name="update_admin" class="btn-update-submit" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Admin
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Confirmation Modal -->
<div id="updateConfirmModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeUpdateConfirmModal()">&times;</span>
        <i class="fa-solid fa-circle-question fa-3x" style="color: #2563eb; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Update</h3>
        <p>Are you sure you want to update this admin's details?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" onclick="closeUpdateConfirmModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: transparent; cursor: pointer;">Cancel</button>
            <button type="button" onclick="submitEditForm()" style="padding: 8px 16px; border-radius: 6px; border: none; background: #2563eb; color: white; font-weight: 600; cursor: pointer;">Yes, Update</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
        <span class="admin-modal-close" onclick="closeDeleteModal()">&times;</span>
        <i class="fa-solid fa-triangle-exclamation fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin-bottom: 10px;">Confirm Delete</h3>
        <p>Are you sure you want to delete <strong id="delete_admin_name"></strong>?</p>
        
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <button type="button" class="del-btn" onclick="closeDeleteModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background-color:light-gray ; cursor: pointer;">Cancel</button>
            <form method="POST">
                <input type="hidden" name="admin_id" id="delete_admin_id">
                <button type="submit" name="confirm_delete" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Update Success Modal -->
<?php if (isset($_GET['updated']) && $_GET['updated'] === 'success'): ?>
<div id="successModal" class="admin-modal" style="display: flex;">
    <div class="admin-modal-content" style="max-width: 380px; text-align: center;">
        <i class="fa-solid fa-circle-check fa-3x" style="color: #10b981; margin-bottom: 15px;"></i>
        <h3>Update Successful!</h3>
        <p style="margin-top: 8px; color: #64748b;">Admin details have been updated successfully.</p>
        <button type="button" onclick="closeSuccessModal()" style="margin-top: 20px; padding: 8px 20px; border-radius: 6px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer;">OK</button>
    </div>
</div>
<?php endif; ?>