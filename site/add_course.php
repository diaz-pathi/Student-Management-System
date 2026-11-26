<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Fetch instructors for the combobox
$q_instructors = "SELECT fullname FROM users WHERE role = 'Instructor'";
$r_instructors = mysqli_query($conn, $q_instructors);
$instructors = [];
if ($r_instructors && mysqli_num_rows($r_instructors) > 0) {
    while ($row = mysqli_fetch_assoc($r_instructors)) {
        $instructors[] = $row['fullname'];
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
                <h2>Add New Course</h2>
                <a href="courses.php" class="btn-add" style="background-color: #64748b;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Courses
                </a>
            </div>

            <div class="form-card-container" style="background: #fff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto;">
                <form action="add_course_backend.php" method="POST">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="course_name">Course Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-book"></i>
                            <input type="text" name="course_name" id="course_name" placeholder="Enter Course Name" value="<?php echo isset($_GET['course_name']) ? htmlspecialchars($_GET['course_name']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'course_name' && isset($_GET['error']) && $_GET['error'] == 'course_name_empty'){
                                    echo "* Course Name is Required!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="course_duration">Course Duration</label>
                        <div class="input-box">
                            <i class="fa-solid fa-clock"></i>
                            <select name="course_duration" id="course_duration" required style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px;">
                                <option value="">-- Select Duration --</option>
                                <option value="6 Months" <?php echo (isset($_GET['course_duration']) && $_GET['course_duration'] == '6 Months') ? 'selected' : ''; ?>>6 Months</option>
                                <option value="1 Year" <?php echo (isset($_GET['course_duration']) && $_GET['course_duration'] == '1 Year') ? 'selected' : ''; ?>>1 Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="instructor_name">Instructor Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-person-chalkboard"></i>
                            <select name="instructor_name" id="instructor_name" required style="width: 100%; border: none; outline: none; background: transparent; padding: 10px; padding-left: 35px; border-radius: 6px;">
                                <option value="">-- Select Instructor --</option>
                                <?php foreach ($instructors as $inst): ?>
                                    <option value="<?php echo htmlspecialchars($inst); ?>" <?php echo (isset($_GET['instructor_name']) && $_GET['instructor_name'] == $inst) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($inst); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" style="width: 100%; padding: 12px; font-weight: 600; cursor: pointer;">Add Course</button>
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
        <h2>Course Added Successfully!</h2>
        <p>New Course has been created.</p>
        <a href="courses.php" class="btn-modal">Go to Course List</a>
    </div>
</div>
<?php endif; ?>
