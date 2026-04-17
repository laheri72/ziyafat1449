<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

init_session();

// Check if user is logged in and is an admin
if (!is_logged_in() || !is_admin() || !has_amali_access()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get input
$data = json_decode(file_get_contents('php://input'), true);
$user_ids = $data['user_ids'] ?? [];
$dua_id = intval($data['dua_id'] ?? 0);
$count = intval($data['count'] ?? 0);

if (empty($user_ids)) {
    echo json_encode(['success' => false, 'message' => 'No users selected']);
    exit();
}

if ($dua_id <= 0 || $count <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item or count']);
    exit();
}

try {
    $conn->begin_transaction();

    // Keep virtual bulk accounts normalized for future reporting logic.
    $conn->query("UPDATE users SET role = 'system', category = 'system' WHERE its_number LIKE '000000%'");
    
    $sql = "INSERT INTO dua_entries (user_id, dua_id, count_added, entry_date) VALUES (?, ?, ?, CURDATE())";
    $stmt = $conn->prepare($sql);
    
    foreach ($user_ids as $id) {
        $user_id = intval($id);
        $stmt->bind_param("iii", $user_id, $dua_id, $count);
        $stmt->execute();
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Successfully updated ' . count($user_ids) . ' users.']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>