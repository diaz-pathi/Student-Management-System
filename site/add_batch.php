<?php
session_start();
require_once('../lib/db.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin' && $role !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Select box සඳහා courses ටික අරගැනීම
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
                <h2>Add New Batch</h2>
                <a href="courses.php" class="btn-add" style="background-color: #64748b;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Courses & Batches
                </a>
            </div>

            <div class="form-card-container" style="background: #fff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto;">
                <form action="add_batch_backend.php" method="POST">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="batch_name">Batch Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-users-rectangle"></i>
                            <input type="text" name="batch_name" id="batch_name" placeholder="Enter Batch Name (e.g. Batch 01)" value="<?php echo isset($_GET['batch_name']) ? htmlspecialchars($_GET['batch_name']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'batch_name' && isset($_GET['error']) && $_GET['error'] == 'batch_name_empty'){
                                    echo "* Batch Name is Required!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="course_id">Assign Course</label>
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
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'course_id' && isset($_GET['error']) && $_GET['error'] == 'course_id_empty'){
                                    echo "* Please Assign a Course!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="start_date">Start Date</label>
                        <div class="input-box">
                            <i class="fa-solid fa-calendar"></i>
                            <input type="date" name="start_date" id="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="end_date">End Date</label>
                        <div class="input-box">
                            <i class="fa-solid fa-calendar-check"></i>
                            <input type="date" name="end_date" id="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="btn-login" style="width: 100%; padding: 12px; font-weight: 600; cursor: pointer;">Add Batch</button>
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
        <h2>Batch Added Successfully!</h2>
        <p>New Batch has been created.</p>
        <a href="courses.php" class="btn-modal">Go to List</a>
    </div>
</div>
<?php endif; ?>
