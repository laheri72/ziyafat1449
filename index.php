<?php
session_start();

// Redirect based on login status
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: user/index.php');
    }
} else {
    header('Location: auth/login.php');
}
exit();
?>