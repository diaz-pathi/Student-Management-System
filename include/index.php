<?php 
require_once('../lib/db.php');

// URL එකේ status=success නැත්නම් විතරක් Super Admin ඉන්නවද බලලා Login එකට යවන්න
if (!isset($_GET['status']) || $_GET['status'] !== 'success') {
    $check_admin = "SELECT COUNT(*) as total FROM users WHERE role = 'Super Admin'";
    $result = $conn->query($check_admin);

    if($result){
        $row = $result->fetch_assoc();

        if($row['total'] > 0){
            header("Location:../login.php");
            exit();
        }
    }
}
?>
<?php
include('include/header.php');
?>
<button class="btn-darkmode" id="themeBtn"><i class="fa-solid fa-moon"></i></button>
<div class="login-wrapper">
    <div class="login-card">
        <div class="image">
            <img src="../images/vta.png" alt="vtabadge">
        </div>
        <div class="card-header">
            <h2>NVTI BADDEGAMA</h2>
            <p>Super Admin Registration</p>
        </div>
        <form action="../site/superadminregister_backend.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <div class="input-box">
                    <i class="fa-solid fa-person"></i>
                    <input type="text" name="fullname" id="fullname" placeholder="Enter Fullname" value="<?php echo isset($_GET['fullname']) ? htmlspecialchars($_GET['fullname']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'fullname'){
                            if($_GET['error'] == 'fullname_empty') {
                                echo "* Full Name is Required!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-box">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="username" id="username" placeholder="Enter Username" value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'username'){
                            if($_GET['error'] == 'username_empty') {
                                echo "* Userame is Required!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="nic">NIC Number</label>
                <div class="input-box">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" name="nic" id="nic" placeholder="Enter NIC Number" value="<?php echo isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg" id="username-error">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'nic'){
                            if($_GET['error'] == 'nic_empty') {
                                echo "NIC Numbe Required!";
                            }else if($_GET['error'] == 'nic_invalid') {
                            echo"* Enter vaild NIC Number!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <div class="input-box">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" name="address" id="address" placeholder="Enter Address" value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'address'){
                            if($_GET['error'] == 'address_empty') {
                                echo "* Address is Required!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <div class="input-box">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" name="dob" id="dob" value="<?php echo isset($_GET['dob']) ? htmlspecialchars($_GET['dob']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'dob'){
                            if($_GET['error'] == 'dob_empty') {
                                echo "* Date of Birth is Required!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <div class="gender-options">
                    <label for="male" class="radio_label">
                        <input type="radio" name="gender" value="Male" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Male') ? 'checked' : ''; ?> required>
                        Male
                    </label>
                        <label for="female" class="radio_label">
                            <input type="radio" name="gender" value="Female" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Female') ? 'checked' : ''; ?> required>
                            Female
                        </label>
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'gender'){
                            if($_GET['error'] == 'gender_empty') {
                                echo "* Please Select your gender!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="email">E-mail Address</label>
                <div class="input-box">
                    <input type="email" name="email" id="email" placeholder="Enter Email Address" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'email'){
                            if($_GET['error'] == 'email_empty') {
                                echo "* Email is required!";
                            } elseif($_GET['error'] == 'email_invalid') {
                                echo "* Please enter a valid email address!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-box">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" name="phone" id="phone" placeholder="Enter Phone Number" value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>" required autocomplete="off">
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_GET['field']) && $_GET['field'] == 'phone'){
                            if($_GET['error'] == 'phone_empty') {
                                echo "* Phone Number is required!";
                            } elseif($_GET['error'] == 'phone_invalid') {
                                echo "* Please enter a valid Phone Number!";
                            }
                        }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="Password">Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Enter Password" required autocomplete="off">
                    <i class="fa-solid fa-eye toggle-eye" id="togglePassword"></i>
                </div>
                <small class="error-msg">
                    <?php
                        if(isset($_POST['field']) && $_GET['field'] == 'password'){
                            if($_POST['error'] == 'weak_password') echo "Password does not meet the Requirements!";

                        }
                    ?>
                    <!-- Live Password Strength Requirements List -->
                        <ul class="pw-requirements" id="pwRequirements">
                            <li id="req-length"><i class="fa-solid fa-circle-xmark"></i> At least 8 characters</li>
                            <li id="req-uppercase"><i class="fa-solid fa-circle-xmark"></i> At least one uppercase letter (A-Z)</li>
                            <li id="req-lowercase"><i class="fa-solid fa-circle-xmark"></i> At least one lowercase letter (a-z)</li>
                            <li id="req-number"><i class="fa-solid fa-circle-xmark"></i> At least one number (0-9)</li>
                            <li id="req-special"><i class="fa-solid fa-circle-xmark"></i> At least one special character (@, #, $, etc.)</li>
                        </ul>
                </small>
            </div>
            <div class="form-group">
                <label for="pwconfirm">Confirm Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="pwconfirm" id="pwconfirm" placeholder="Retype Password" required autocomplete="off">
                    <i class="fa-solid fa-eye toggle-eye" id="toggleConfirmPassword"></i>
                </div>
                <small class="error-msg" id="confirm-msg" style="display: none;">
                    <?php if(isset($_GET['field']) && $_GET['field']== 'pwconfirm'){
                        if($_GET['error'] == 'password_mismatch') echo "* Passwords do not match!";
                    }
                    ?>
                </small>
            </div>
            <div class="form-group">
                <label for="profile_image">Profile Picture</label>
                <div class="input-box">
                    <i class="fa-solid fa-image"></i>
                    <input type="file" name="img" id="img" accept="image/*">
                </div>
            </div>
            
            <button type="submit" class="btn-login">Register</button>
        </form>
    </div>
</div>

<!-- Success Modal -->
<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<div class="modal-overlay" id="successModal">
    <div class="modal-card">
        <div class="modal-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2>Registration Successful!</h2>
        <p>Super Admin account created successfully. Please login to continue.</p>
        <a href="login.php" class="btn-modal">Go to Login</a>
    </div>
</div>
<?php endif; ?>

<?php
include('../include/footer.php');
?>