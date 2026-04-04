<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
init_session();
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
if (isset($_POST['target_user_id']) && has_amali_access()) {
    $user_id = intval($_POST['target_user_id']);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input
$dua_id = isset($_POST['dua_id']) ? intval($_POST['dua_id']) : 0;
$count_to_add = isset($_POST['count_to_add']) ? intval($_POST['count_to_add']) : 0;
$entry_date = isset($_POST['entry_date']) ? clean_input($_POST['entry_date']) : date('Y-m-d');
$notes = isset($_POST['notes']) ? clean_input($_POST['notes']) : '';

// Validate inputs
if ($dua_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid dua ID']);
    exit();
}

if ($count_to_add <= 0) {
    echo json_encode(['success' => false, 'message' => 'Count must be greater than 0']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Add new entry
    $sql = "INSERT INTO dua_entries (user_id, dua_id, count_added, entry_date, notes) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $user_id, $dua_id, $count_to_add, $entry_date, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add entry');
    }
    
    // Ensure dua_progress record exists
    $sql2 = "INSERT IGNORE INTO dua_progress (user_id, dua_id, last_updated) 
             VALUES (?, ?, CURDATE())";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("ii", $user_id, $dua_id);
    $stmt2->execute();
    
    // Update last_updated
    $sql3 = "UPDATE dua_progress SET last_updated = CURDATE() WHERE user_id = ? AND dua_id = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("ii", $user_id, $dua_id);
    $stmt3->execute();
    
    // Get updated totals
    $sql4 = "SELECT 
                dm.id,
                dm.dua_name,
                dm.target_count,
                COALESCE(SUM(de.count_added), 0) as completed_count,
                ROUND((COALESCE(SUM(de.count_added), 0) / dm.target_count) * 100, 2) as progress_percentage
             FROM duas_master dm
             LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
             WHERE dm.id = ?
             GROUP BY dm.id, dm.dua_name, dm.target_count";
    $stmt4 = $conn->prepare($sql4);
    $stmt4->bind_param("ii", $user_id, $dua_id);
    $stmt4->execute();
    $result = $stmt4->get_result();
    $updated_data = $result->fetch_assoc();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Entry added successfully!',
        'data' => [
            'completed_count' => $updated_data['completed_count'],
            'target_count' => $updated_data['target_count'],
            'progress_percentage' => $updated_data['progress_percentage']
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>