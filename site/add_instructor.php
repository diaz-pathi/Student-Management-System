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

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <div class="admin-header-row" style="margin-bottom: 20px;">
                <h2>Add New Instructor</h2>
                <a href="instructors.php" class="btn-add" style="background-color: #64748b;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Instructors
                </a>
            </div>

            <div class="form-card-container" style="background: #fff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto;">
                <form action="add_instructor_backend.php" method="POST" enctype="multipart/form-data" id="addInstructorForm">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="fullname">Full Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-person"></i>
                            <input type="text" name="fullname" id="fullname" placeholder="Enter Fullname" value="<?php echo isset($_GET['fullname']) ? htmlspecialchars($_GET['fullname']) : ''; ?>" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="username">Username</label>
                        <div class="input-box">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" name="username" id="username" placeholder="Enter Username" value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>" required autocomplete="off" onkeyup="liveValidate('username', this.value)">
                        </div>
                        <small class="error-msg" id="err_username" style="color: #ef4444;">
                            <?php if(isset($_GET['field']) && $_GET['field'] == 'username' && isset($_GET['error'])) echo "* Error in username"; ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="nic">NIC Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" name="nic" id="nic" placeholder="Enter NIC Number" value="<?php echo isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : ''; ?>" required autocomplete="off" onkeyup="liveValidate('nic', this.value)">
                        </div>
                        <small class="error-msg" id="err_nic" style="color: #ef4444;">
                            <?php if(isset($_GET['field']) && $_GET['field'] == 'nic' && isset($_GET['error'])) echo "* Error in NIC"; ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="address">Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-location-dot"></i>
                            <input type="text" name="address" id="address" placeholder="Enter Address" value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="dob">Date of Birth</label>
                        <div class="input-box">
                            <i class="fa-solid fa-calendar"></i>
                            <input type="date" name="dob" id="dob" value="<?php echo isset($_GET['dob']) ? htmlspecialchars($_GET['dob']) : ''; ?>" required autocomplete="off">
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
                        <label for="course_id">Assign Course</label>
                        <div class="input-box">
                            <i class="fa-solid fa-book"></i>
                            <select name="course_id" id="course_id" style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px;">
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
                        <label for="email">E-mail Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="Enter Email Address" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required autocomplete="off" onkeyup="liveValidate('email', this.value)">
                        </div>
                        <small class="error-msg" id="err_email" style="color: #ef4444;">
                            <?php if(isset($_GET['field']) && $_GET['field'] == 'email' && isset($_GET['error'])) echo "* Error in Email"; ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="phone">Phone Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="phone" id="phone" placeholder="Enter Phone Number" value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>" required autocomplete="off" onkeyup="liveValidate('phone', this.value)">
                        </div>
                        <small class="error-msg" id="err_phone" style="color: #ef4444;">
                            <?php if(isset($_GET['field']) && $_GET['field'] == 'phone' && isset($_GET['error'])) echo "* Error in Phone"; ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="password">Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Enter Password" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="pwconfirm">Confirm Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="pwconfirm" id="pwconfirm" placeholder="Retype Password" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="img">Profile Picture</label>
                        <div class="input-box">
                            <i class="fa-solid fa-image"></i>
                            <input type="file" name="img" id="img" accept="image/*">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login" style="width: 100%; padding: 12px; font-weight: 600; cursor: pointer;">Add Instructor</button>
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
        <h2>Instructor Added Successfully!</h2>
        <p>New Instructor account has been registered.</p>
        <a href="instructors.php" class="btn-modal">Go to Instructor List</a>
    </div>
</div>
<?php endif; ?>

<script>
function liveValidate(field, value) {
    if (value.length === 0) {
        document.getElementById('err_' + field).textContent = "";
        return;
    }

    // Pattern checks via JS
    if (field === 'nic') {
        let oldNIC = /^[0-9]{9}[vVxX]$/;
        let newNIC = /^[0-9]{12}$/;
        if (!oldNIC.test(value) && !newNIC.test(value)) {
            document.getElementById('err_nic').textContent = "* Invalid NIC format";
            return;
        } else {
            document.getElementById('err_nic').textContent = "";
        }
    }

    if (field === 'email') {
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(value)) {
            document.getElementById('err_email').textContent = "* Invalid Email format";
            return;
        } else {
            document.getElementById('err_email').textContent = "";
        }
    }

    if (field === 'phone') {
        let phonePattern = /^0[0-9]{9}$/;
        if (!phonePattern.test(value)) {
            document.getElementById('err_phone').textContent = "* Invalid Phone format";
            return;
        } else {
            document.getElementById('err_phone').textContent = "";
        }
    }

    // AJAX check for DB duplicates
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "check_instructor_duplicate.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            let response = xhr.responseText.trim();
            if (response !== "OK") {
                document.getElementById('err_' + field).textContent = response;
            } else {
                document.getElementById('err_' + field).textContent = ""; // Clear if OK
            }
        }
    };
    xhr.send("field=" + field + "&value=" + encodeURIComponent(value));
}
</script>
