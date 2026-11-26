function applyTheme(theme) {
    var isDark = theme === "dark";
    if (isDark) {
        document.documentElement.classList.add("darkmode");
        document.body.classList.add("darkmode");
    } else {
        document.documentElement.classList.remove("darkmode");
        document.body.classList.remove("darkmode");
    }
    localStorage.setItem("theme", isDark ? "dark" : "light");
    document.cookie = "theme=" + (isDark ? "dark" : "light") + "; path=/; max-age=31536000; SameSite=Lax";

    let btn = document.getElementById("themeBtn");
    if (btn) {
        let icon = btn.querySelector("i");
        if (icon) {
            icon.className = isDark ? "fa-solid fa-sun" : "fa-solid fa-moon";
        }
    }
}

function darkMode() {
    let isCurrentlyDark = document.body.classList.contains("darkmode");
    applyTheme(isCurrentlyDark ? "light" : "dark");
}

let btn = document.getElementById("themeBtn");
if (btn) {
    btn.addEventListener('click', darkMode);
}

document.addEventListener("DOMContentLoaded", () => {
    let savedtheme = localStorage.getItem("theme");
    if (!savedtheme) {
        savedtheme = document.body.classList.contains("darkmode") ? "dark" : "light";
        localStorage.setItem("theme", savedtheme);
        document.cookie = "theme=" + savedtheme + "; path=/; max-age=31536000; SameSite=Lax";
    }

    if (savedtheme === "dark") {
        document.documentElement.classList.add("darkmode");
        document.body.classList.add("darkmode");
    } else {
        document.documentElement.classList.remove("darkmode");
        document.body.classList.remove("darkmode");
    }

    let themeBtn = document.getElementById("themeBtn");
    if (themeBtn) {
        let icon = themeBtn.querySelector("i");
        if (icon) {
            icon.className = (savedtheme === "dark") ? "fa-solid fa-sun" : "fa-solid fa-moon";
        }
    }
});


// Function to toggle password visibility
function setupPasswordToggle(toggleId, inputId) {
const toggleIcon = document.querySelector(toggleId);
const passwordInput = document.querySelector(inputId);

if (toggleIcon && passwordInput) {
toggleIcon.addEventListener("click", function () {
const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
passwordInput.setAttribute("type", type);

this.classList.toggle("fa-eye");
this.classList.toggle("fa-eye-slash");
});
}
}

// Password fields ????? Call ?????
setupPasswordToggle("#togglePassword", "#password");
setupPasswordToggle("#toggleConfirmPassword", "#pwconfirm");

const passwordInput = document.getElementById('password');
const reqBox = document.getElementById('pwRequirements');

const reqLength = document.getElementById('req-length');
const reqUppercase = document.getElementById('req-uppercase');
const reqLowercase = document.getElementById('req-lowercase');
const reqNumber = document.getElementById('req-number');
const reqSpecial = document.getElementById('req-special');

if (passwordInput && reqBox) {
// 1. Input ?? Click / Focus ?????? ?????? List ?? ????????
passwordInput.addEventListener('focus', () => {
reqBox.style.display = 'block';
});

// 2. Input ????? ????? ????? (Blur) List ?? Hide ?????
passwordInput.addEventListener('blur', () => {
if(passwordInput.value === '') {
reqBox.style.display = 'none';
}
});

// 3. Real-time Live Validation (Type ??? ??????????)
passwordInput.addEventListener('input', () => {
const val = passwordInput.value;

function validateRule(elem, isValid) {
if (!elem) return;
const icon = elem.querySelector('i');
if (isValid) {
elem.classList.add('valid');
if (icon) icon.className = 'fa-solid fa-circle-check';
} else {
elem.classList.remove('valid');
if (icon) icon.className = 'fa-solid fa-circle-xmark';
}
}

validateRule(reqLength, val.length >= 8);
validateRule(reqUppercase, /[A-Z]/.test(val));
validateRule(reqLowercase, /[a-z]/.test(val));
validateRule(reqNumber, /[0-9]/.test(val));
validateRule(reqSpecial, /[!@#$%^&*(),.?":{}|<>]/.test(val));
});
}

const confirmInput = document.getElementById('pwconfirm');
const confirmMsg = document.getElementById('confirm-msg');

function checkPasswordMatch() {
if (!passwordInput || !confirmInput || !confirmMsg) return;
const passwordVal = passwordInput.value;
const confirmVal = confirmInput.value;

if (confirmVal === '') {
confirmMsg.style.display = 'none';
return;
}

confirmMsg.style.display = 'block';

if (passwordVal === confirmVal) {
confirmMsg.textContent = '? Passwords match!';
confirmMsg.style.color = '#16a34a';
} else {
confirmMsg.textContent = '? Passwords do not match!';
confirmMsg.style.color = '#ef4444';
}
}

if (confirmInput) {
confirmInput.addEventListener('input', checkPasswordMatch);
}
if (passwordInput && confirmInput) {
passwordInput.addEventListener('input', () => {
if (confirmInput.value !== '') {
checkPasswordMatch();
}
});
}

/* =========================================
ADMIN MODAL & DELETE MODAL FUNCTIONS
========================================= */
function openAdminModal(id, customId, username, fullname, nic, address, dob, gender, role, email, phone, status, imgSrc) {
const adminIdInput = document.getElementById('m_admin_id');
const customIdSpan = document.getElementById('m_custom_id');
const usernameSpan = document.getElementById('m_username');
const fullnameSpan = document.getElementById('m_fullname');
const nicSpan = document.getElementById('m_nic');
const addressSpan = document.getElementById('m_address');
const dobSpan = document.getElementById('m_dob');
const genderSpan = document.getElementById('m_gender');
const roleSpan = document.getElementById('m_role');
const emailSpan = document.getElementById('m_email');
const phoneSpan = document.getElementById('m_phone');
const statusSpan = document.getElementById('m_status');
const photoBox = document.getElementById('m_photo_box');
const photoImg = document.getElementById('m_photo');
const toggleBtn = document.getElementById('btn_toggle_status');
const statusInput = document.getElementById('m_new_status');

if (adminIdInput) adminIdInput.value = id;
if (customIdSpan) customIdSpan.innerText = customId || 'N/A';
if (usernameSpan) usernameSpan.innerText = username || 'N/A';
if (fullnameSpan) fullnameSpan.innerText = fullname || 'N/A';
if (nicSpan) nicSpan.innerText = nic || 'N/A';
if (addressSpan) addressSpan.innerText = address || 'N/A';
if (dobSpan) dobSpan.innerText = dob || 'N/A';
if (genderSpan) genderSpan.innerText = gender || 'N/A';
if (roleSpan) roleSpan.innerText = role || 'N/A';
if (emailSpan) emailSpan.innerText = email || 'N/A';
if (phoneSpan) phoneSpan.innerText = phone || 'N/A';
if (statusSpan) {
statusSpan.innerText = status;
statusSpan.className = status === 'Active' ? 'status-active' : 'status-inactive';
}

if (photoBox && photoImg) {
if (imgSrc && imgSrc !== '') {
photoImg.src = imgSrc;
photoImg.style.display = 'block';
} else {
photoImg.style.display = 'none';
}
}

if (toggleBtn) {
if (role === 'Super Admin' || role === 'SuperAdmin') {
toggleBtn.style.display = 'none';
} else {
toggleBtn.style.display = 'inline-flex';
if (status === 'Inactive') {
toggleBtn.innerHTML = '<i class="fa-solid fa-user-check"></i> Mark as Active';
toggleBtn.className = 'btn-mark-active';
if (statusInput) statusInput.value = 'Active';
} else {
toggleBtn.innerHTML = '<i class="fa-solid fa-user-xmark"></i> Mark as Inactive';
toggleBtn.className = 'btn-mark-inactive';
if (statusInput) statusInput.value = 'Inactive';
}
}
}

const modal = document.getElementById('adminModal');
if (modal) modal.style.display = 'flex';
}

function closeAdminModal() {
const modal = document.getElementById('adminModal');
if (modal) modal.style.display = 'none';
}

// Delete Modal Functions
function openDeleteModal(id, fullname) {
document.getElementById('delete_admin_id').value = id;
document.getElementById('delete_admin_name').innerText = fullname;
document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
document.getElementById('deleteModal').style.display = 'none';
}

// Backdrop Click Listener
window.addEventListener('click', function(event) {
const adminModal = document.getElementById('adminModal');
const deleteModal = document.getElementById('deleteModal');
if (event.target === adminModal) {
closeAdminModal();
}
if (event.target === deleteModal) {
closeDeleteModal();
}
});

/* =========================================
EDIT & CONFIRMATION MODAL FUNCTIONS
========================================= */

// Edit Modal ?? open ?? values set ?????
function openEditModal(id, fullname, role, status) {
document.getElementById('edit_admin_id').value = id;
document.getElementById('edit_fullname').value = fullname;
document.getElementById('edit_role').value = role;
document.getElementById('edit_status').value = status;

const editModal = document.getElementById('editAdminModal');
if (editModal) editModal.style.display = 'flex';
}

function closeEditModal() {
const editModal = document.getElementById('editAdminModal');
if (editModal) editModal.style.display = 'none';
}

// Confirmation Modal ?? Open ?????
function openUpdateConfirmModal() {
const confirmModal = document.getElementById('updateConfirmModal');
if (confirmModal) confirmModal.style.display = 'flex';
}

function closeUpdateConfirmModal() {
const confirmModal = document.getElementById('updateConfirmModal');
if (confirmModal) confirmModal.style.display = 'none';
}

// Confirm Modal ????? Yes ?????? Form ?? Submit ?????
function submitEditForm() {
document.getElementById('editAdminForm').submit();
}

// Success Modal ?? ?????
function closeSuccessModal() {
const successModal = document.getElementById('successModal');
if (successModal) successModal.style.display = 'none';

// URL ????? ?updated=success ????? ?? clean ?????
window.history.replaceState({}, document.title, window.location.pathname);
}

// Modal Backdrop Click Listener (???? ??? ?? ????? Modals ???)
window.addEventListener('click', function(event) {
const adminModal = document.getElementById('adminModal');
const deleteModal = document.getElementById('deleteModal');
const editAdminModal = document.getElementById('editAdminModal');
const updateConfirmModal = document.getElementById('updateConfirmModal');

if (event.target === adminModal) closeAdminModal();
if (event.target === deleteModal) closeDeleteModal();
if (event.target === editAdminModal) closeEditModal();
if (event.target === updateConfirmModal) closeUpdateConfirmModal();
});

function openEditModal(id, fullname, username, nic, address, dob, gender, email, phone) {
document.getElementById('edit_admin_id').value = id;
document.getElementById('edit_fullname').value = fullname || '';
document.getElementById('edit_username').value = username || '';
document.getElementById('edit_nic').value = nic || '';
document.getElementById('edit_address').value = address || '';
document.getElementById('edit_dob').value = dob || '';
document.getElementById('edit_email').value = email || '';
document.getElementById('edit_phone').value = phone || '';

if (gender === 'Female') {
document.getElementById('edit_gender_female').checked = true;
} else {
document.getElementById('edit_gender_male').checked = true;
}



const editModal = document.getElementById('editAdminModal');
if (editModal) editModal.style.display = 'flex';
}

function closeEditModal() {
const editModal = document.getElementById('editAdminModal');
if (editModal) editModal.style.display = 'none';
}

function validateEditForm() {
let fullname = document.getElementById('edit_fullname').value.trim();
let username = document.getElementById('edit_username').value.trim();
let nic = document.getElementById('edit_nic').value.trim();
let email = document.getElementById('edit_email').value.trim();
let phone = document.getElementById('edit_phone').value.trim();
let password = document.getElementById('edit_password').value;
let confirmPassword = document.getElementById('edit_confirm_password').value;

if (fullname === "" || username === "" || nic === "" || email === "" || phone === "") {
alert("Please fill in all required fields.");
return false;
}

let emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
if (!emailPattern.test(email)) {
alert("Please enter a valid email address.");
return false;
}

let phonePattern = /^[0-9]{10}$/;
if (!phonePattern.test(phone)) {
alert("Phone number must be exactly 10 digits.");
return false;
}

let nicPattern = /^([0-9]{9}[vVxX]|[0-9]{12})$/;
if (!nicPattern.test(nic)) {
alert("Please enter a valid NIC number.");
return false;
}

if (password !== "") {
if (password.length < 6) {
alert("Password must be at least 6 characters long.");
return false;
}
if (password !== confirmPassword) {
alert("Passwords do not match.");
return false;
}
}

return true;
}