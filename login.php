<?php
require_once('lib/db.php'); 
include('include/header.php'); ?>

<button class="btn-darkmode" id="themeBtn"><i class="fa-solid <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'fa-sun' : 'fa-moon'; ?>"></i></button>
<div class="login-wrapper">
    <div class="login-card">
        <div class="image">
            <img src="images/vta.png" alt="vtabadge">
        </div>
        <div class="card-header">
            <h2>NVTI BADDEGAMA</h2>
            <p>Student Management System</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php
                    if($_GET['error'] == 'empty_fields'){
                        echo "Please fill in all fields!";
                    }elseif($_GET['error'] == 'invalid_credentials'){
                        echo "Invalid Username or Password!";
                    }else{
                        echo "Somthing went wrong! Please try again.";
                    }
                ?>
            </div>
        <?php endif; ?>
        <form action="site/login_backend.php" method="POST" class="login-form">
            <!-- 🌍 Hidden Client Device Timezone & Date Fields -->
            <input type="hidden" name="client_timezone" id="client_timezone">
            <input type="hidden" name="client_timezone_offset" id="client_timezone_offset">
            <input type="hidden" name="client_device_date" id="client_device_date">

            <div class="form-group">
                <label for="username">UserName / E-mail </label>
                <div class="input-box">
                    <i class="fa-solid fa-user"></i>
                    <input type="text"
                       id="username"
                       name="username"
                       value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>"
                       placeholder="Enter Your Username or Email Address"
                       required
                       autocomplete="off"
                    >
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password"
                        name="password" 
                        id="password"
                        placeholder="Enter Password"
                        required
                    >
                    <i class="fa-solid fa-eye-slash toggle-eye" id="togglePassword"></i>
                </div>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</div>

<script>
    // Automatically capture logging device's exact timezone & local date
    (function() {
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Colombo';
            var offset = new Date().getTimezoneOffset();
            var d = new Date();
            var pad = function(n) { return n < 10 ? '0' + n : n; };
            var deviceDate = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

            var tzInput = document.getElementById('client_timezone');
            var offInput = document.getElementById('client_timezone_offset');
            var dtInput = document.getElementById('client_device_date');

            if (tzInput) tzInput.value = tz;
            if (offInput) offInput.value = offset;
            if (dtInput) dtInput.value = deviceDate;

            // Immediately set cookie so backend has it even before form submission
            document.cookie = "client_timezone=" + encodeURIComponent(tz) + "; path=/; max-age=31536000; SameSite=Lax";
            document.cookie = "client_tz_offset=" + encodeURIComponent(offset) + "; path=/; max-age=31536000; SameSite=Lax";
            document.cookie = "client_device_date=" + encodeURIComponent(deviceDate) + "; path=/; max-age=86400; SameSite=Lax";
        } catch (e) {}
    })();
</script>

<!-- 🚫 Inactive Account Warning Modal Popup -->
<?php if (isset($_GET['error']) && $_GET['error'] == 'inactive_account'): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-icon">
            <i class="fa-solid fa-user-lock" style="color: #ef4444;"></i>
        </div>
        <h2>Account Deactivated!</h2>
        <p>Your account is currently inactive. Please contact the System Administrator to regain access.</p>
        <a href="login.php" class="btn-modal" style="background-color: #ef4444;">OK, Understood</a>
    </div>
</div>
<?php endif; ?>

<?php include('include/footer.php'); ?>