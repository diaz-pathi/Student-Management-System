<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$q_courses = "SELECT course_id, course_name FROM courses";
$r_courses = mysqli_query($conn, $q_courses);
$courses = [];
if ($r_courses && mysqli_num_rows($r_courses) > 0) {
    while ($row = mysqli_fetch_assoc($r_courses)) {
        $courses[] = $row;
    }
}

$q_batches = "SELECT batch_id, batch_name FROM batches";
$r_batches = mysqli_query($conn, $q_batches);
$batches = [];
if ($r_batches && mysqli_num_rows($r_batches) > 0) {
    while ($row = mysqli_fetch_assoc($r_batches)) {
        $batches[] = $row;
    }
}

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <div class="admin-header-row" style="margin-bottom: 20px;">
                <h2>Add New Student</h2>
                <a href="students.php" class="btn-add" style="background-color: #64748b;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Students
                </a>
            </div>

            <div class="form-card-container" style="background: #fff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto;">
                <form action="add_student_backend.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="name">Student Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-person"></i>
                            <input type="text" name="name" id="name" placeholder="Enter Fullname" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="nic">NIC Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" name="nic" id="nic" placeholder="Enter NIC Number" value="<?php echo isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'nic'){
                                    if($_GET['error'] == 'nic_invalid') echo "* Invalid NIC Pattern!";
                                    elseif($_GET['error'] == 'nic_exists') echo "* This NIC already exists!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="dob">Date of Birth</label>
                        <div class="input-box">
                            <i class="fa-solid fa-calendar-days"></i>
                            <input type="date" name="dob" id="dob" value="<?php echo isset($_GET['dob']) ? htmlspecialchars($_GET['dob']) : ''; ?>" required autocomplete="off" style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="address">Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-location-dot"></i>
                            <input type="text" name="address" id="address" placeholder="Enter Address" value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Gender</label>
                        <div class="gender-options" style="display: flex; gap: 20px; margin-top: 5px;">
                            <label class="radio_label">
                                <input type="radio" name="gender" value="Male" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Male') ? 'checked' : ''; ?> required>
                                Male
                            </label>
                            <label class="radio_label">
                                <input type="radio" name="gender" value="Female" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Female') ? 'checked' : ''; ?> required>
                                Female
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="course_id">Course</label>
                        <div class="input-box">
                            <i class="fa-solid fa-book"></i>
                            <select name="course_id" id="course_id" required style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px;">
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $c['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="batch_id">Batch</label>
                        <div class="input-box">
                            <i class="fa-solid fa-users-rectangle"></i>
                            <select name="batch_id" id="batch_id" required style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px;">
                                <option value="">-- Select Batch --</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['batch_id']); ?>" <?php echo (isset($_GET['batch_id']) && $_GET['batch_id'] == $b['batch_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['batch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="email">E-mail Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="Enter Email Address" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'email'){
                                    if($_GET['error'] == 'email_invalid') echo "* Invalid Email Address!";
                                    elseif($_GET['error'] == 'email_exists') echo "* This Email already exists!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="phone">Phone Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="phone" id="phone" placeholder="Enter Phone Number" value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'phone'){
                                    if($_GET['error'] == 'phone_invalid') echo "* Invalid Phone Number!";
                                    elseif($_GET['error'] == 'phone_exists') echo "* This Phone Number already exists!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="img">Profile Picture</label>
                        <div class="input-box">
                            <i class="fa-solid fa-image"></i>
                            <input type="file" name="img" id="img" accept="image/*">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login" style="width: 100%; padding: 12px; font-weight: 600; cursor: pointer;">Add Student</button>
                </form>
            </div>
        </div>

        <?php include('../include/footer.php'); ?>
    </div>
</div>

<!-- Success Modal -->
<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<div class="modal-overlay" id="successModal">
    <div class="modal-card">
        <div class="modal-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2>Student Added Successfully!</h2>
        <p>New Student account has been registered.</p>
        <a href="students.php" class="btn-modal">Go to Student List</a>
    </div>
</div>
<?php endif; ?>
