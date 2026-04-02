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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get input
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['selections']) || !is_array($data['selections'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$selections = $data['selections'];
$success_count = 0;
$errors = [];

try {
    // Start transaction
    $conn->begin_transaction();
    
    foreach ($selections as $selection) {
        $quran_number = intval($selection['quran_number']);
        $juz_number = intval($selection['juz_number']);
        
        if ($quran_number < 1 || $quran_number > 4 || $juz_number < 1 || $juz_number > 30) {
            continue;
        }
        
        // Check if already completed
        $check_sql = "SELECT id FROM quran_progress WHERE user_id = ? AND quran_number = ? AND juz_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Insert new completion
            $sql = "INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) 
                    VALUES (?, ?, ?, 1, CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Failed to update progress for Quran $quran_number Juz $juz_number";
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get updated progress for response
    $quran_progress = get_quran_progress($conn, $user_id);
    
    // Get per-quran counts
    $quran_counts = [];
    for ($q = 1; $q <= 4; $q++) {
        $q_sql = "SELECT COUNT(*) as count FROM quran_progress WHERE user_id = ? AND quran_number = ? AND is_completed = 1";
        $q_stmt = $conn->prepare($q_sql);
        $q_stmt->bind_param("ii", $user_id, $q);
        $q_stmt->execute();
        $quran_counts[$q] = $q_stmt->get_result()->fetch_assoc()['count'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $success_count > 0 ? "$success_count Juz marked as completed!" : "No new progress to update.",
        'overall_progress' => $quran_progress,
        'quran_counts' => $quran_counts,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>