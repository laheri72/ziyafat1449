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

$action = $_POST['action'] ?? '';

try {
    if ($action === 'select') {
        $book_id = intval($_POST['book_id']);
        
        // Check if already selected
        $check_sql = "SELECT id FROM book_transcription WHERE user_id = ? AND book_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This book is already in your list.']);
        } else {
            $sql = "INSERT INTO book_transcription (user_id, book_id, status, started_date) 
                    VALUES (?, ?, 'selected', CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $book_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Book added successfully!']);
            } else {
                throw new Exception('Failed to add book');
            }
        }
    } elseif ($action === 'update_progress') {
        $book_id = intval($_POST['book_id']);
        $pages_completed = intval($_POST['pages_completed']);
        
        // Get total pages for validation
        $check_sql = "SELECT bm.total_pages 
                      FROM books_master bm 
                      JOIN book_transcription bt ON bm.id = bt.book_id 
                      WHERE bt.user_id = ? AND bt.book_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $total_pages = $result->fetch_assoc()['total_pages'];
            
            if ($pages_completed < 0 || $pages_completed > $total_pages) {
                echo json_encode(['success' => false, 'message' => "Invalid page count (0-$total_pages)"]);
            } else {
                $sql = "UPDATE book_transcription SET pages_completed = ? WHERE user_id = ? AND book_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $pages_completed, $user_id, $book_id);
                
                if ($stmt->execute()) {
                    $pct = round(($pages_completed / $total_pages) * 100, 2);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Progress updated!',
                        'data' => [
                            'pages_completed' => $pages_completed,
                            'total_pages' => $total_pages,
                            'percentage' => $pct
                        ]
                    ]);
                } else {
                    throw new Exception('Failed to update progress');
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
        }
    } elseif ($action === 'complete') {
        $book_id = intval($_POST['book_id']);
        $notes = clean_input($_POST['notes'] ?? '');
        
        $check_sql = "SELECT total_pages FROM books_master WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $book_id);
        $check_stmt->execute();
        $total_pages = $check_stmt->get_result()->fetch_assoc()['total_pages'];
        
        $sql = "UPDATE book_transcription 
                SET status = 'completed', completed_date = CURDATE(), notes = ?, pages_completed = ?
                WHERE user_id = ? AND book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $notes, $total_pages, $user_id, $book_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book marked as completed!']);
        } else {
            throw new Exception('Failed to update status');
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>