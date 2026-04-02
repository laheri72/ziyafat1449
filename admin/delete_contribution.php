<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$contribution_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($contribution_id === 0) {
    header('Location: view_users.php');
    exit();
}

// Delete contribution
$sql = "DELETE FROM contributions WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contribution_id);

if ($stmt->execute()) {
    if ($user_id > 0) {
        header("Location: user_details.php?id=$user_id&success=Contribution deleted successfully");
    } else {
        header('Location: view_users.php?success=Contribution deleted successfully');
    }
} else {
    if ($user_id > 0) {
        header("Location: user_details.php?id=$user_id&error=Failed to delete contribution");
    } else {
        header('Location: view_users.php?error=Failed to delete contribution');
    }
}
exit();
?>