<?php
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Super Admin role එකේ space එකක් තිබුණත් නැතත් අඳුරගන්න
$is_super_admin = ($role == 'Super Admin' || $role == 'SuperAdmin');
$is_admin = ($role == 'Admin' || $is_super_admin);
?>
<div class="sidebar">
    <div class="brand" style="position: relative;">
        <button id="sidebarClose" class="mobile-close-btn" title="Close Menu" style="display: none; position: absolute; top: 10px; right: 10px; background: transparent; border: none; color: #fff; font-size: 1.2rem; cursor: pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img src="../images/vta.png" alt="NVTI Logo">
        <h3>NVTI BADDEGAMA</h3>
    </div>
    <ul class="menu">
        <!-- 1. Common Menu (Dashboard) except for Monitor -->
        <?php if ($role !== 'Monitor'): ?>
        <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
        </li>
        <?php endif; ?>

        <!-- 2. Attendance Menu -->
        <?php if ($is_admin || $role == 'Instructor' || $role == 'Monitor'): ?>
            <li class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
                <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
            </li>
        <?php endif; ?>

        <!-- 3. Super Admin ට විතරක් පෙනෙන Manage Admins Menu -->
        <?php if ($is_super_admin): ?>
            <li class="<?php echo ($current_page == 'admins.php') ? 'active' : ''; ?>">
                <a href="admins.php"><i class="fa-solid fa-user-shield"></i> Manage Admins</a>
            </li>
        <?php endif; ?>

        <!-- 4. Super Admin සහ Admin දෙදෙනාටම පෙනෙන Management Menus -->
        <?php if ($is_admin): ?>
            <li class="<?php echo ($current_page == 'instructors.php') ? 'active' : ''; ?>">
                <a href="instructors.php"><i class="fa-solid fa-chalkboard-user"></i> Manage Instructors</a>
            </li>
            <li class="<?php echo ($current_page == 'courses.php') ? 'active' : ''; ?>">
                <a href="courses.php"><i class="fa-solid fa-book-open"></i>Manage Courses</a>
            </li>
        <?php endif; ?>

        <?php if ($is_admin || $role == 'Instructor'): ?>
            <li class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
                <a href="students.php"><i class="fa-solid fa-user-graduate"></i> Manage Students</a>
            </li>
        <?php endif; ?>

        <!-- 5. Reports Menu -->
        <?php if ($is_admin || $role == 'Instructor'): ?>
            <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <a href="reports.php"><i class="fa-solid fa-file-invoice"></i> Reports</a>
            </li>
        <?php endif; ?>

        <!-- 6. Settings Menu (Admin & Super Admin) -->
        <?php if ($is_admin): ?>
            <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
            </li>
        <?php endif; ?>

        <!-- 6. Student Menu -->
        <?php if ($role == 'Student'): ?>
            <li class="<?php echo ($current_page == 'my_attendance.php') ? 'active' : ''; ?>">
                <a href="my_attendance.php"><i class="fa-solid fa-user-check"></i> My Attendance</a>
            </li>
        <?php endif; ?>
    </ul>
</div>