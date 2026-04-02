<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'My Profile';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Get user data
$user = get_user_by_id($conn, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $tr_number = clean_input($_POST['tr_number']);
    $category = clean_input($_POST['category']);
    $phone_number = clean_input($_POST['phone_number']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required';
    } else {
        // Check if email is already taken by another user
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email is already taken';
        } else {
            // Update basic info
            $sql = "UPDATE users SET name = ?, email = ?, tr_number = ?, category = ?, phone_number = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $name, $email, $tr_number, $category, $phone_number, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;

                // Update password if provided
                if (!empty($current_password) && !empty($new_password)) {
                    if ($current_password !== $user['password']) {
                        $error = 'Current password is incorrect';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'New passwords do not match';
                    } else {
                        $sql = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $new_password, $_SESSION['user_id']);
                        $stmt->execute();
                    }
                }

                if (empty($error)) {
                    $success = 'Profile updated successfully';
                    $user = get_user_by_id($conn, $_SESSION['user_id']);
                }
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user"></i> My Profile</h1>
        <p>Manage your account information and settings</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Profile Information</h3>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="its_number"><i class="fas fa-id-card"></i> ITS Number</label>
                <input type="text" id="its_number" class="form-control" value="<?php echo htmlspecialchars($user['its_number']); ?>" disabled>
                <small>ITS Number cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="tr_number"><i class="fas fa-id-badge"></i> TR Number</label>
                <input type="text" id="tr_number" name="tr_number" class="form-control" value="<?php echo htmlspecialchars($user['tr_number']); ?>">
            </div>

            <div class="form-group">
                <label for="category"><i class="fas fa-mosque"></i> Jamea</label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select Jamea --</option>
                    <option value="Surat" <?php echo ($user['category'] === 'Surat') ? 'selected' : ''; ?>>Surat</option>
                    <option value="Marol" <?php echo ($user['category'] === 'Marol') ? 'selected' : ''; ?>>Marol</option>
                    <option value="Karachi" <?php echo ($user['category'] === 'Karachi') ? 'selected' : ''; ?>>Karachi</option>
                    <option value="Nairobi" <?php echo ($user['category'] === 'Nairobi') ? 'selected' : ''; ?>>Nairobi</option>
                    <option value="Muntasib" <?php echo ($user['category'] === 'Muntasib') ? 'selected' : ''; ?>>Muntasib</option>
                </select>
            </div>

            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="+1234567890">
            </div>

            <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-color);">

            <h4 class="mb-2" style="font-size: 1.1rem; font-weight: 500;"><i class="fas fa-key"></i> Change Password</h4>
            <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">Leave blank if you don't want to change password</p>

            <div class="form-group">
                <label for="current_password"><i class="fas fa-lock"></i> Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control">
            </div>

            <div class="form-group">
                <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control">
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>