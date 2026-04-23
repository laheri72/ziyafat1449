<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

if (!is_admin()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Add New User';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $its_number = clean_input($_POST['its_number']);
    $tr_number = clean_input($_POST['tr_number']);
    $category = clean_input($_POST['category']);
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $phone_number = clean_input($_POST['phone_number']);
    $password = $_POST['password'];
    $role = clean_input($_POST['role']);
    $admin_type = ($role === 'admin' && !empty($_POST['admin_type'])) ? clean_input($_POST['admin_type']) : null;

    if (empty($its_number) || empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } else {
        // Check if email or ITS number already exists
        $sql = "SELECT * FROM users WHERE email = ? OR its_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $its_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email or ITS number already exists';
        } else {
            // Insert new user
            $sql = "INSERT INTO users (its_number, tr_number, category, name, email, phone_number, password, role, admin_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $its_number, $tr_number, $category, $name, $email, $phone_number, $password, $role, $admin_type);

            if ($stmt->execute()) {
                $success = 'User added successfully!';
            } else {
                $error = 'Failed to add user. Please try again.';
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-user-plus"></i> Add New User</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <div class="form-group">
                <label for="its_number"><i class="fas fa-id-card"></i> ITS Number *</label>
                <input type="text" id="its_number" name="its_number" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="tr_number"><i class="fas fa-id-badge"></i> TR Number</label>
                <input type="text" id="tr_number" name="tr_number" class="form-control">
            </div>

            <div class="form-group">
                <label for="category"><i class="fas fa-mosque"></i> Jamea</label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select Jamea --</option>
                    <option value="Surat">Surat</option>
                    <option value="Marol">Marol</option>
                    <option value="Karachi">Karachi</option>
                    <option value="Nairobi">Nairobi</option>
                    <option value="Muntasib">Muntasib</option>
                </select>
            </div>

            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="+1234567890">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password *</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Role *</label>
                <select id="role" name="role" class="form-control" required onchange="toggleAdminType()">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group" id="admin_type_group" style="display: none;">
                <label for="admin_type"><i class="fas fa-user-shield"></i> Admin Type</label>
                <select id="admin_type" name="admin_type" class="form-control">
                    <option value="">Select Admin Type</option>
                    <option value="super_admin">Super Admin (Full Access)</option>
                    <option value="finance_admin">Finance Admin</option>
                    <option value="amali_coordinator">Amali Coordinator</option>
                </select>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add User
                </button>
                <a href="view_users.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAdminType() {
    var role = document.getElementById('role').value;
    var adminTypeGroup = document.getElementById('admin_type_group');
    if (role === 'admin') {
        adminTypeGroup.style.display = 'block';
    } else {
        adminTypeGroup.style.display = 'none';
        document.getElementById('admin_type').value = '';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>