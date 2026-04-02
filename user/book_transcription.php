<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Book Transcription (Istinsakh ul Kutub)';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'select') {
        $book_id = intval($_POST['book_id']);
        
        // Check if already selected
        $check_sql = "SELECT id FROM book_transcription WHERE user_id = ? AND book_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = 'This book is already in your list.';
        } else {
            // Insert new selection
            $sql = "INSERT INTO book_transcription (user_id, book_id, status, started_date) 
                    VALUES (?, ?, 'selected', CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $book_id);
            
            if ($stmt->execute()) {
                $success = 'Book added to your transcription list!';
            } else {
                $error = 'Failed to add book.';
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
            $book_data = $result->fetch_assoc();
            $total_pages = $book_data['total_pages'];
            
            // Validate pages_completed
            if ($pages_completed < 0) {
                $error = 'Pages completed cannot be negative.';
            } elseif ($pages_completed > $total_pages) {
                $error = 'Pages completed cannot exceed total pages (' . $total_pages . ').';
            } else {
                // Update pages completed
                $sql = "UPDATE book_transcription 
                        SET pages_completed = ?
                        WHERE user_id = ? AND book_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $pages_completed, $user_id, $book_id);
                
                if ($stmt->execute()) {
                    $success = 'Progress updated successfully!';
                } else {
                    $error = 'Failed to update progress.';
                }
            }
        } else {
            $error = 'Book not found.';
        }
    } elseif ($action === 'complete') {
        $book_id = intval($_POST['book_id']);
        $notes = clean_input($_POST['notes']);
        
        // Get total pages and set pages_completed to total_pages
        $check_sql = "SELECT bm.total_pages 
                      FROM books_master bm 
                      WHERE bm.id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $book_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $book_data = $result->fetch_assoc();
        $total_pages = $book_data['total_pages'];
        
        // Update to completed
        $sql = "UPDATE book_transcription 
                SET status = 'completed', completed_date = CURDATE(), notes = ?, pages_completed = ?
                WHERE user_id = ? AND book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $notes, $total_pages, $user_id, $book_id);
        
        if ($stmt->execute()) {
            $success = 'Book marked as completed!';
        } else {
            $error = 'Failed to update status.';
        }
    } elseif ($action === 'update_notes') {
        $book_id = intval($_POST['book_id']);
        $notes = clean_input($_POST['notes']);
        
        // Update notes
        $sql = "UPDATE book_transcription SET notes = ? WHERE user_id = ? AND book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $notes, $user_id, $book_id);
        
        if ($stmt->execute()) {
            $success = 'Notes updated successfully!';
        } else {
            $error = 'Failed to update notes.';
        }
    }
}

// Get user's selected books with page tracking
$my_books = get_book_progress_with_pages($conn, $user_id);

// Get all available books
$all_books = get_available_books($conn);

// Create array of selected book IDs
$selected_book_ids = [];
$my_books->data_seek(0);
while ($book = $my_books->fetch_assoc()) {
    $selected_book_ids[] = $book['id'];
}
$my_books->data_seek(0);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i>Istinsakh ul Kutub</h1>
        <p>Select and track your book transcription progress</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- My Books -->
    <?php if ($my_books->num_rows > 0): ?>
        <h2 style="margin: 20px 0; font-size: 1.25rem; font-weight: 600;"><i class="fas fa-list"></i> My Kutub</h2>
        <?php while ($book = $my_books->fetch_assoc()): ?>
        <?php 
            $pages_completed = $book['pages_completed'] ?? 0;
            $total_pages = $book['total_pages'] ?? 0;
            $progress_percentage = $total_pages > 0 ? round(($pages_completed / $total_pages) * 100, 2) : 0;
        ?>
        <div class="card">
            <div class="card-header">
                <h3>
                    <?php echo htmlspecialchars($book['book_name']); ?>
                    <?php if (!$book['is_active']): ?>
                        <span class="badge badge-secondary" style="font-size: 12px; margin-left: 8px;">Deactivated</span>
                    <?php endif; ?>
                </h3>
                <p dir="rtl" style="font-size: 16px; color: #666;"><?php echo htmlspecialchars($book['book_name_arabic']); ?></p>
                <p style="font-size: 14px; color: #888;">
                    <i class="fas fa-user-edit"></i> Author: <?php echo htmlspecialchars($book['author']); ?>
                    | <i class="fas fa-file-alt"></i> Total Pages: <?php echo $total_pages; ?>
                </p>
            </div>
            
            <div style="padding: 20px;">
                <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
                    <div>
                        <strong>Status:</strong>
                        <?php if ($book['status'] === 'completed'): ?>
                            <span class="badge badge-success">Completed</span>
                        <?php else: ?>
                            <span class="badge badge-warning">In Progress</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Started:</strong> <?php echo date('M d, Y', strtotime($book['started_date'])); ?>
                    </div>
                    <div>
                        <strong>Completed:</strong> <?php echo $book['completed_date'] ? date('M d, Y', strtotime($book['completed_date'])) : '-'; ?>
                    </div>
                </div>

                <!-- Page Progress -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong><i class="fas fa-book-open"></i> Page Progress:</strong>
                        <span><?php echo $pages_completed; ?> / <?php echo $total_pages; ?> pages (<?php echo $progress_percentage; ?>%)</span>
                    </div>
                    <div style="background: #e0e0e0; border-radius: 10px; height: 20px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #4CAF50, #8BC34A); height: 100%; width: <?php echo $progress_percentage; ?>%; transition: width 0.3s;"></div>
                    </div>
                </div>

                <?php if ($book['status'] !== 'completed'): ?>
                <!-- Update Page Progress Form -->
                <form method="POST" action="" style="margin-bottom: 15px;">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                    
                    <div class="form-group">
                        <label for="pages_<?php echo $book['id']; ?>">
                            <i class="fas fa-edit"></i> Update Pages Completed
                        </label>
                        <input type="number" 
                               id="pages_<?php echo $book['id']; ?>" 
                               name="pages_completed" 
                               class="form-control" 
                               min="0" 
                               max="<?php echo $total_pages; ?>"
                               value="<?php echo $pages_completed; ?>"
                               required>
                        <small style="color: #666;">Enter the number of pages you have completed (0 to <?php echo $total_pages; ?>)</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Update Progress
                    </button>
                </form>

                <!-- Mark as Complete Form -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                    
                    <div class="form-group">
                        <label for="notes_<?php echo $book['id']; ?>">
                            <i class="fas fa-sticky-note"></i> Notes (Optional)
                        </label>
                        <textarea id="notes_<?php echo $book['id']; ?>" 
                                  name="notes" 
                                  class="form-control" 
                                  rows="3"><?php echo htmlspecialchars($book['notes'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Mark this book as completed? This will set pages to <?php echo $total_pages; ?>.');">
                        <i class="fas fa-check"></i> Mark as Completed
                    </button>
                </form>
                <?php else: ?>
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notes</label>
                        <p style="padding: 10px; background: #f5f5f5; border-radius: 4px;">
                            <?php echo htmlspecialchars($book['notes'] ?: 'No notes added.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <!-- Available Books to Select -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Kutub</h3>
        </div>
        <div style="padding: 20px;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="select">
                
                <div class="form-group">
                    <label for="book_id"><i class="fas fa-book"></i> Select Book *</label>
                    <select id="book_id" name="book_id" class="form-control" required>
                        <option value="">-- Select a Book --</option>
                        <?php while ($book = $all_books->fetch_assoc()): ?>
                            <?php if (!in_array($book['id'], $selected_book_ids)): ?>
                                <option value="<?php echo $book['id']; ?>">
                                    <?php echo htmlspecialchars($book['book_name']); ?> 
                                    (<?php echo htmlspecialchars($book['author']); ?>) 
                                    - <?php echo $book['total_pages']; ?> pages
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-plus"></i> Add to My List
                </button>
            </form>
        </div>
    </div>

    <?php if ($my_books->num_rows === 0): ?>
        <div class="card">
            <p class="text-center" style="padding: 20px;">
                <i class="fas fa-info-circle"></i> You haven't selected any books yet. Choose a book from the list above to start transcribing.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>