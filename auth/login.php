<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ../admin/index.php');
    } else {
        header('Location: ../user/index.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $its_number = clean_input($_POST['its_number']);
    $password = $_POST['password'];

    if (empty($its_number) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $sql = "SELECT * FROM users WHERE its_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $its_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Plain text password comparison
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['its_number'] = $user['its_number'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_type'] = $user['admin_type'] ?? null;

                if ($user['role'] === 'admin') {
                    header('Location: ../admin/index.php');
                } else {
                    header('Location: ../user/index.php');
                }
                exit();
            } else {
                $error = 'Invalid ITS  or password';
            }
        } else {
            $error = 'Invalid ITS or password';
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> – Ziyafat us Shukr</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <!-- Header -->
        <div class="auth-header">
            <div class="logo-circle">
                <i class="fas fa-users"></i>
            </div>
            <h1>Ziyafat us Shukr</h>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="its_number">ITS Number</label>
                <div class="input-wrapper">
                    <i class="fas fa-id-card"></i>
                    <input
                        type="text"
                        id="its_number"
                        name="its_number"
                        placeholder="Enter your ITS number"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-primary">
                Sign In
            </button>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
            <p>© 1449 H · Ziyafat us Shukr</p>
        
        </div>

    </div>
</div>

</body>
</html>
