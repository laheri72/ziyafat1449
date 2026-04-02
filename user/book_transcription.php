<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Book Transcription (Istinsakh ul Kutub)';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

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

<style>
    .book-card {
        margin-bottom: 20px;
    }
    .book-grid-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    @media (max-width: 480px) {
        .book-header {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .book-arabic-name {
            font-size: 1.25rem !important;
        }
    }
</style>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Istinsakh ul Kutub</h1>
        <p>Select and track your book transcription progress</p>
    </div>

    <!-- My Books Section -->
    <div id="my-books-container">
        <?php if ($my_books->num_rows > 0): ?>
            <h2 class="mb-3" style="font-size: 1.25rem;"><i class="fas fa-list"></i> My Kutub</h2>
            <?php while ($book = $my_books->fetch_assoc()): ?>
            <?php 
                $pages_completed = $book['pages_completed'] ?? 0;
                $total_pages = $book['total_pages'] ?? 0;
                $progress_percentage = $total_pages > 0 ? round(($pages_completed / $total_pages) * 100, 2) : 0;
                $is_completed = ($book['status'] === 'completed');
            ?>
            <div class="card book-card" data-book-id="<?php echo $book['id']; ?>">
                <div class="card-header book-header">
                    <div>
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary);">By <?php echo htmlspecialchars($book['author']); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span class="book-arabic-name" dir="rtl" style="font-size: 1.5rem; color: var(--primary-600); display: block;"><?php echo htmlspecialchars($book['book_name_arabic']); ?></span>
                        <span class="badge <?php echo $is_completed ? 'badge-success' : 'badge-warning'; ?> status-badge">
                            <?php echo $is_completed ? 'Completed' : 'In Progress'; ?>
                        </span>
                    </div>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <div class="book-grid-info">
                        <div><strong>Started:</strong> <?php echo date('M d, Y', strtotime($book['started_date'])); ?></div>
                        <div><strong>Total Pages:</strong> <?php echo $total_pages; ?></div>
                        <?php if ($is_completed): ?>
                            <div><strong>Completed:</strong> <?php echo date('M d, Y', strtotime($book['completed_date'])); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress-label">
                            <span class="progress-label-text">Progress: <span class="completed-count"><?php echo $pages_completed; ?></span> / <?php echo $total_pages; ?> pages</span>
                            <span class="progress-label-value"><span class="percent-text"><?php echo $progress_percentage; ?></span>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                    </div>

                    <?php if (!$is_completed): ?>
                    <form class="ajax-book-form mt-3" data-action="update_progress">
                        <input type="hidden" name="action" value="update_progress">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        
                        <div class="form-group">
                            <label>Update Pages Completed</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="pages_completed" class="form-control" 
                                       min="0" max="<?php echo $total_pages; ?>" 
                                       value="<?php echo $pages_completed; ?>" required>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>

                    <form class="ajax-book-form" data-action="complete">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <div class="form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any notes..."><?php echo htmlspecialchars($book['notes'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Mark as completed?')">
                            <i class="fas fa-check"></i> Mark as Completed
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="mt-2">
                            <strong>Notes:</strong>
                            <p style="padding: 10px; background: var(--bg-tertiary); border-radius: var(--radius-md); font-style: italic;">
                                <?php echo htmlspecialchars($book['notes'] ?: 'No notes.'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- Available Books -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Kutub</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <form class="ajax-book-form" data-action="select">
                <input type="hidden" name="action" value="select">
                <div class="form-group">
                    <label>Select Book</label>
                    <select name="book_id" class="form-control" required>
                        <option value="">-- Choose a Book --</option>
                        <?php 
                        $all_books->data_seek(0);
                        while ($book = $all_books->fetch_assoc()): 
                            if (!in_array($book['id'], $selected_book_ids)):
                        ?>
                            <option value="<?php echo $book['id']; ?>">
                                <?php echo htmlspecialchars($book['book_name']); ?> (<?php echo $book['total_pages']; ?> pages)
                            </option>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-plus"></i> Add to My List
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.ajax-book-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const action = this.getAttribute('data-action');
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch('ajax_book_transcription.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                if (action === 'select' || action === 'complete') {
                    // Reload to reflect significant structure changes
                    setTimeout(() => location.reload(), 1000);
                } else if (action === 'update_progress') {
                    // Direct UI update
                    const card = this.closest('.book-card');
                    card.querySelector('.completed-count').innerText = result.data.pages_completed;
                    card.querySelector('.percent-text').innerText = result.data.percentage;
                    card.querySelector('.progress-fill').style.width = result.data.percentage + '%';
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Connection error', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
