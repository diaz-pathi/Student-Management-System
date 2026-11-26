<?php
session_start();
require_once('../lib/db.php');

// Super Admin කෙනෙක්ද කියලා check කිරීම
$role = $_SESSION['role'] ?? '';
if ($role !== 'Super Admin' && $role !== 'SuperAdmin') {
    header("Location: dashboard.php");
    exit();
}

include('../include/header.php'); 
?>

<div class="dashboard-container">
    <?php include('../include/sidebar.php'); ?>

    <div class="main-wrapper">
        <?php include('../include/topbar.php'); ?>

        <div class="content">
            <div class="admin-header-row" style="margin-bottom: 20px;">
                <h2>Add New Admin</h2>
                <a href="admins.php" class="btn-add" style="background-color: #64748b;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Admins
                </a>
            </div>

            <div class="form-card-container" style="background: #fff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto;">
                <form action="add_admin_backend.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="fullname" style="color:black">Full Name</label>
                        <div class="input-box">
                            <i class="fa-solid fa-person"></i>
                            <input type="text" name="fullname" id="fullname" placeholder="Enter Fullname" value="<?php echo isset($_GET['fullname']) ? htmlspecialchars($_GET['fullname']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'fullname' && isset($_GET['error']) && $_GET['error'] == 'fullname_empty'){
                                    echo "* Full Name is Required!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="username" style="color:black">Username</label>
                        <div class="input-box">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" name="username" id="username" placeholder="Enter Username" value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'username'){
                                    if($_GET['error'] == 'username_empty') echo "* Username is Required!";
                                    elseif($_GET['error'] == 'username_exists') echo "* This Username is already taken!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="nic" style="color:black">NIC Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" name="nic" id="nic" placeholder="Enter NIC Number" value="<?php echo isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'nic'){
                                    if($_GET['error'] == 'nic_empty') echo "* NIC Number is Required!";
                                    elseif($_GET['error'] == 'nic_invalid') echo "* Enter a valid NIC Number!";
                                    elseif($_GET['error'] == 'nic_exists') echo "* This NIC Number is already registered!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="address" style="color:black">Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-location-dot"></i>
                            <input type="text" name="address" id="address" placeholder="Enter Address" value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'address' && isset($_GET['error']) && $_GET['error'] == 'address_empty'){
                                    echo "* Address is Required!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="dob" style="color:black">Date of Birth</label>
                        <div class="input-box">
                            <i class="fa-solid fa-calendar"></i>
                            <input type="date" name="dob" id="dob" value="<?php echo isset($_GET['dob']) ? htmlspecialchars($_GET['dob']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'dob' && isset($_GET['error']) && $_GET['error'] == 'dob_empty'){
                                    echo "* Date of Birth is Required!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color:black">Gender</label>
                        <div class="gender-options" style="display: flex; gap: 20px; margin-top: 5px;">
                            <label class="radio_label" style="color:black">
                                <input type="radio" name="gender" value="Male" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Male') ? 'checked' : ''; ?> required>
                                Male
                            </label>
                            <label class="radio_label" style="color:black">
                                <input type="radio" name="gender" value="Female" <?php echo (isset($_GET['gender']) && $_GET['gender'] == 'Female') ? 'checked' : ''; ?> required>
                                Female
                            </label>
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'gender' && isset($_GET['error']) && $_GET['error'] == 'gender_empty'){
                                    echo "* Please Select gender!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="email" style="color:black">E-mail Address</label>
                        <div class="input-box">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="Enter Email Address" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'email'){
                                    if($_GET['error'] == 'email_empty') echo "* Email is required!";
                                    elseif($_GET['error'] == 'email_invalid') echo "* Please enter a valid email address!";
                                    elseif($_GET['error'] == 'email_exists') echo "* This Email is already registered!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="phone" style="color:black">Phone Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="phone" id="phone" placeholder="Enter Phone Number" value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>" required autocomplete="off">
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'phone'){
                                    if($_GET['error'] == 'phone_empty') echo "* Phone Number is required!";
                                    elseif($_GET['error'] == 'phone_invalid') echo "* Please enter a valid Phone Number!";
                                    elseif($_GET['error'] == 'phone_exists') echo "* This Phone Number is already registered!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="password" style="color:black">Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Enter Password" required autocomplete="off">
                            <i class="fa-solid fa-eye toggle-eye" id="togglePassword"></i>
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php
                                if(isset($_GET['field']) && $_GET['field'] == 'password' && isset($_GET['error']) && $_GET['error'] == 'weak_password'){
                                    echo "* Password does not meet the Requirements!";
                                }
                            ?>
                            <ul class="pw-requirements" id="pwRequirements">
                                <li id="req-length"><i class="fa-solid fa-circle-xmark"></i> At least 8 characters</li>
                                <li id="req-uppercase"><i class="fa-solid fa-circle-xmark"></i> At least one uppercase letter (A-Z)</li>
                                <li id="req-lowercase"><i class="fa-solid fa-circle-xmark"></i> At least one lowercase letter (a-z)</li>
                                <li id="req-number"><i class="fa-solid fa-circle-xmark"></i> At least one number (0-9)</li>
                                <li id="req-special"><i class="fa-solid fa-circle-xmark"></i> At least one special character (@, #, $, etc.)</li>
                            </ul>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="pwconfirm" style="color:black">Confirm Password</label>
                        <div class="input-box">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="pwconfirm" id="pwconfirm" placeholder="Retype Password" required autocomplete="off">
                            <i class="fa-solid fa-eye toggle-eye" id="toggleConfirmPassword"></i>
                        </div>
                        <small class="error-msg" style="color: #ef4444;">
                            <?php 
                                if(isset($_GET['field']) && $_GET['field'] == 'pwconfirm' && isset($_GET['error']) && $_GET['error'] == 'password_mismatch'){
                                    echo "* Passwords do not match!";
                                }
                            ?>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="img" style="color:black">Profile Picture</label>
                        <div class="input-box">
                            <i class="fa-solid fa-image"></i>
                            <input type="file" name="img" id="img" accept="image/*">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login" style="width: 100%; padding: 12px; font-weight: 600; cursor: pointer;">Add Admin</button>
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
        <h2>Admin Added Successfully!</h2>
        <p>New Admin account has been registered.</p>
        <a href="admins.php" class="btn-modal">Go to Admin List</a>
    </div>
</div>
<?php endif; ?>