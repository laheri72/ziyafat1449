<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0 || $user_id === $_SESSION['user_id']) {
    header('Location: view_users.php');
    exit();
}

// Delete user (contributions will be deleted automatically due to CASCADE)
$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    header('Location: view_users.php?success=User deleted successfully');
} else {
    header('Location: view_users.php?error=Failed to delete user');
}
exit();
?>