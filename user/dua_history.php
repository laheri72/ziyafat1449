<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Dua Entry History';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];
$dua_id = isset($_GET['dua_id']) ? intval($_GET['dua_id']) : null;
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'edit_entry') {
        $entry_id = intval($_POST['entry_id']);
        $count_added = intval($_POST['count_added']);
        
        // Validate count
        if ($count_added <= 0) {
            $error = 'Count must be greater than 0.';
        } else {
            // Update entry
            $sql = "UPDATE dua_entries 
                    SET count_added = ?
                    WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $count_added, $entry_id, $user_id);
            
            if ($stmt->execute()) {
                $success = 'Entry updated successfully!';
            } else {
                $error = 'Failed to update entry.';
            }
        }
    } elseif ($action === 'delete_entry') {
        $entry_id = intval($_POST['entry_id']);
        
        // Delete entry
        $sql = "DELETE FROM dua_entries WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $entry_id, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Entry deleted successfully!';
        } else {
            $error = 'Failed to delete entry.';
        }
    }
}

// Get dua details
if ($dua_id) {
    $sql = "SELECT * FROM duas_master WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dua_id);
    $stmt->execute();
    $dua = $stmt->get_result()->fetch_assoc();
    
    if (!$dua) {
        header('Location: dua_tracking.php');
        exit();
    }
    
    // Get entries for this dua
    $entries = get_dua_entries($conn, $user_id, $dua_id);
    
    // Get total count
    $sql = "SELECT COALESCE(SUM(count_added), 0) as total FROM dua_entries WHERE user_id = ? AND dua_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $dua_id);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['total'];
} else {
    header('Location: dua_tracking.php');
    exit();
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-history"></i> Dua Entry History</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><?php echo htmlspecialchars($dua['dua_name']); ?></h3>
            <p dir="rtl" style="font-size: 18px; color: #666;"><?php echo htmlspecialchars($dua['dua_name_arabic']); ?></p>
        </div>
        
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div>
                <strong>Total Completed:</strong> <?php echo $total_count; ?>
            </div>
            <div>
                <strong>Target:</strong> <?php echo $dua['target_count']; ?>
            </div>
            <div>
                <strong>Remaining:</strong> <?php echo max(0, $dua['target_count'] - $total_count); ?>
            </div>
        </div>

        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min(round(($total_count / $dua['target_count']) * 100, 2), 100); ?>%">
                    <?php echo min(round(($total_count / $dua['target_count']) * 100, 2), 100); ?>%
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Entry History</h3>
        </div>
        <div class="table-container">
            <?php if ($entries->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Count Added</th>
                            <th>Recorded On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($entry = $entries->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                                <td><strong><?php echo $entry['count_added']; ?></strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?></td>
                                <td>
                                    <button onclick="editEntry(<?php echo htmlspecialchars(json_encode($entry)); ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this entry?');">
                                        <input type="hidden" name="action" value="delete_entry">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No entries found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="action-buttons">
        <a href="dua_tracking.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dua Tracking
        </a>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:8px;">
        <h3><i class="fas fa-edit"></i> Edit Dua Entry</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_entry">
            <input type="hidden" name="entry_id" id="edit_entry_id">
            
            <div class="form-group">
                <label for="edit_count_added"><i class="fas fa-hashtag"></i> Count Added *</label>
                <input type="number" id="edit_count_added" name="count_added" class="form-control" min="1" required>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Entry
                </button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editEntry(entry) {
    document.getElementById('edit_entry_id').value = entry.id;
    document.getElementById('edit_count_added').value = entry.count_added;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>