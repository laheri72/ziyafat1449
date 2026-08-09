<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/supabase_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

if (!$user || empty($user['tr_number'])) {
    echo json_encode(['success' => false, 'message' => 'TR Number not found for user profile']);
    exit();
}

$available_in_mumbai = isset($_POST['available_in_mumbai']) ? ($_POST['available_in_mumbai'] === '1' || $_POST['available_in_mumbai'] === 'true') : false;

$success = update_supabase_student_availability($user['tr_number'], $available_in_mumbai);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Mumbai availability status updated!',
        'available_in_mumbai' => $available_in_mumbai
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update availability in Ziyarat Flow.'
    ]);
}
?>
