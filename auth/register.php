<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $its_number = clean_input($_POST['its_number']);
    $tr_number = clean_input($_POST['tr_number']);
    $category = clean_input($_POST['category']);
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $phone_number = clean_input($_POST['phone_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($its_number) || empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
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
            $sql = "INSERT INTO users (its_number, tr_number, category, name, email, phone_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $its_number, $tr_number, $category, $name, $email, $phone_number, $password);

            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$page_title = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Ziyafat us Shukr</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2><i class="fas fa-user-plus"></i> Register</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

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
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>