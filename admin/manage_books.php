<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

// Check if user has amali access
if (!has_amali_access()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Manage Books';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Handle Add/Edit/Delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $book_name = clean_input($_POST['book_name']);
        $book_name_arabic = clean_input($_POST['book_name_arabic']);
        $author = clean_input($_POST['author']);
        $total_pages = intval($_POST['total_pages']);
        $description = clean_input($_POST['description']);
        
        $sql = "INSERT INTO books_master (book_name, book_name_arabic, author, total_pages, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssis", $book_name, $book_name_arabic, $author, $total_pages, $description);
        
        if ($stmt->execute()) {
            $success = 'Book added successfully!';
        } else {
            $error = 'Failed to add book.';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $book_name = clean_input($_POST['book_name']);
        $book_name_arabic = clean_input($_POST['book_name_arabic']);
        $author = clean_input($_POST['author']);
        $total_pages = intval($_POST['total_pages']);
        $description = clean_input($_POST['description']);
        
        $sql = "UPDATE books_master SET book_name = ?, book_name_arabic = ?, author = ?, total_pages = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisi", $book_name, $book_name_arabic, $author, $total_pages, $description, $id);
        
        if ($stmt->execute()) {
            $success = 'Book updated successfully!';
        } else {
            $error = 'Failed to update book.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE books_master SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Book deactivated successfully!';
        } else {
            $error = 'Failed to deactivate book.';
        }
    } elseif ($action === 'activate') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE books_master SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Book activated successfully!';
        } else {
            $error = 'Failed to activate book.';
        }
    }
}

// Get all books
$sql = "SELECT * FROM books_master ORDER BY display_order, id";
$books = $conn->query($sql);

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-book"></i> Manage Books (Istinsakh ul Kutub)</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Add New Book Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Add New Book</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="book_name"><i class="fas fa-book"></i> Book Name (English) *</label>
                <input type="text" id="book_name" name="book_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="book_name_arabic"><i class="fas fa-language"></i> Book Name (Arabic)</label>
                <input type="text" id="book_name_arabic" name="book_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="author"><i class="fas fa-user-edit"></i> Author *</label>
                <input type="text" id="author" name="author" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="total_pages"><i class="fas fa-file-alt"></i> Total Pages *</label>
                <input type="number" id="total_pages" name="total_pages" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="description"><i class="fas fa-info-circle"></i> Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Add Book
            </button>
        </form>
    </div>

    <!-- Books List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Books</h3>
        </div>
        <div class="table-container">
            <?php if ($books->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book Name</th>
                            <th>Arabic Name</th>
                            <th>Author</th>
                            <th>Total Pages</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $book['id']; ?></td>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td dir="rtl"><?php echo htmlspecialchars($book['book_name_arabic']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo $book['total_pages']; ?></td>
                                <td>
                                    <?php if ($book['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($book['is_active']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this book?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-ban"></i> Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No books found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:8px;">
        <h3><i class="fas fa-edit"></i> Edit Book</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label for="edit_book_name">Book Name (English) *</label>
                <input type="text" id="edit_book_name" name="book_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_book_name_arabic">Book Name (Arabic)</label>
                <input type="text" id="edit_book_name_arabic" name="book_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="edit_author">Author *</label>
                <input type="text" id="edit_author" name="author" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_total_pages">Total Pages *</label>
                <input type="number" id="edit_total_pages" name="total_pages" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update
                </button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editBook(book) {
    document.getElementById('edit_id').value = book.id;
    document.getElementById('edit_book_name').value = book.book_name;
    document.getElementById('edit_book_name_arabic').value = book.book_name_arabic || '';
    document.getElementById('edit_author').value = book.author;
    document.getElementById('edit_total_pages').value = book.total_pages;
    document.getElementById('edit_description').value = book.description || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>