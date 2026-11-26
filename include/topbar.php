<?php
// Database connection එක නැත්නම් එකතු කරගනිමු (path එක නිවැරදි කරගන්න)
require_once __DIR__ . '/../lib/db.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Default avatar URL එක (DB එකේ photo එකක් නැති වුණොත් පෙන්වන්න)[cite: 16]
$dp_url = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=2563eb&color=fff&rounded=true";

// Database එකෙන් LONGBLOB photo එක ගන්නවා[cite: 16]
if ($user_id > 0) {
    $query = "SELECT image FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        if (!empty($row['image'])) {
            $base64_image = base64_encode($row['image']);
            $dp_url = 'data:image/jpeg;base64,' . $base64_image;
        }
    }
}
?>

<div class="topbar">
    <div style="display: flex; align-items: center; gap: 15px;">
        <button id="sidebarToggle" class="mobile-menu-btn" title="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="page-title">
            <h2>Student Management System</h2>
        </div>
    </div>
    
    <div class="topbar-user-info">
        
        <!-- Dark Mode Toggle Button -->
        <button id="themeBtn" class="topbar-theme-btn" title="Toggle Theme">
            <i class="fa-solid <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'fa-sun' : 'fa-moon'; ?>"></i>
        </button>

        <!-- User Profile (DP and Details) -->
        <div class="topbar-user-profile">
            <img src="<?php echo $dp_url; ?>" alt="DP" class="topbar-dp">
            <div class="topbar-user-details">
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <span><?php echo htmlspecialchars($role); ?></span>
            </div>
        </div>

        <!-- Logout Button -->
        <a href="#" onclick="openLogoutModal(); return false;" class="topbar-logout-btn" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="admin-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div class="admin-modal-content" style="padding: 25px; border-radius: 12px; max-width: 400px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <i class="fa-solid fa-right-from-bracket fa-3x" style="color: #ef4444; margin-bottom: 15px;"></i>
        <h3 style="margin: 0; font-size: 1.5rem;">Confirm Logout</h3>
        <p class="logout-text" style="margin: 10px 0 25px 0;">Are you sure you want to log out of the system?</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button type="button" onclick="closeLogoutModal()" class="btn-cancel-logout" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; cursor: pointer;">Cancel</button>
            <a href="logout_backend.php" style="padding: 10px 20px; border-radius: 8px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer; text-decoration: none;">Yes, Logout</a>
        </div>
    </div>
</div>

<script>
function openLogoutModal() {
    document.getElementById('logoutConfirmModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutConfirmModal').style.display = 'none';
}
// Close modal if clicked outside
window.addEventListener('click', function(event) {
    var modal = document.getElementById('logoutConfirmModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});

// Sidebar Toggle Script
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        var closeBtn = document.getElementById('sidebarClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.remove('active');
            });
        }

        // Close sidebar when clicking outside of it on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Prevent clicks inside sidebar from closing it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>