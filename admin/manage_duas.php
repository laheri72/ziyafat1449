<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

// Check if user has amali access
if (!has_amali_access()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Manage Duas';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Handle Add/Edit/Delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $dua_name = clean_input($_POST['dua_name']);
        $dua_name_arabic = clean_input($_POST['dua_name_arabic']);
        $category = clean_input($_POST['category']);
        $target_count = intval($_POST['target_count']);
        $description = clean_input($_POST['description']);
        $display_order = intval($_POST['display_order']);
        
        $sql = "INSERT INTO duas_master (dua_name, dua_name_arabic, category, target_count, description, display_order) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisi", $dua_name, $dua_name_arabic, $category, $target_count, $description, $display_order);
        
        if ($stmt->execute()) {
            $success = 'Dua added successfully!';
        } else {
            $error = 'Failed to add dua.';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $dua_name = clean_input($_POST['dua_name']);
        $dua_name_arabic = clean_input($_POST['dua_name_arabic']);
        $category = clean_input($_POST['category']);
        $target_count = intval($_POST['target_count']);
        $description = clean_input($_POST['description']);
        $display_order = intval($_POST['display_order']);
        
        $sql = "UPDATE duas_master SET dua_name = ?, dua_name_arabic = ?, category = ?, target_count = ?, description = ?, display_order = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisi", $dua_name, $dua_name_arabic, $category, $target_count, $description, $display_order, $id);
        
        if ($stmt->execute()) {
            $success = 'Dua updated successfully!';
        } else {
            $error = 'Failed to update dua.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE duas_master SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Dua deactivated successfully!';
        } else {
            $error = 'Failed to deactivate dua.';
        }
    } elseif ($action === 'activate') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE duas_master SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Dua activated successfully!';
        } else {
            $error = 'Failed to activate dua.';
        }
    }
}

// Get all duas
$sql = "SELECT * FROM duas_master ORDER BY display_order, id";
$duas = $conn->query($sql);

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-hands-praying"></i> Manage Duas</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Add New Dua Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Add New Dua</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="dua_name"><i class="fas fa-book"></i> Dua Name (English) *</label>
                <input type="text" id="dua_name" name="dua_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="dua_name_arabic"><i class="fas fa-language"></i> Dua Name (Arabic)</label>
                <input type="text" id="dua_name_arabic" name="dua_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="category"><i class="fas fa-tag"></i> Category *</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <option value="dua">Dua</option>
                    <option value="tasbeeh">Tasbeeh</option>
                    <option value="namaz">Namaz</option>
                </select>
            </div>

            <div class="form-group">
                <label for="target_count"><i class="fas fa-hashtag"></i> Target Count *</label>
                <input type="number" id="target_count" name="target_count" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="display_order"><i class="fas fa-sort-numeric-down"></i> Display Order *</label>
                <input type="number" id="display_order" name="display_order" class="form-control" min="0" value="0" required>
                <small class="form-text text-muted">Lower numbers appear first</small>
            </div>

            <div class="form-group">
                <label for="description"><i class="fas fa-info-circle"></i> Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Add Dua
            </button>
        </form>
    </div>

    <!-- Duas List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Duas</h3>
        </div>
        <div class="table-container">
            <?php if ($duas->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Dua Name</th>
                            <th>Arabic Name</th>
                            <th>Category</th>
                            <th>Target Count</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($dua = $duas->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $dua['display_order']; ?></strong></td>
                                <td><?php echo htmlspecialchars($dua['dua_name']); ?></td>
                                <td dir="rtl"><?php echo htmlspecialchars($dua['dua_name_arabic']); ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst(htmlspecialchars($dua['category'])); ?></span></td>
                                <td><?php echo $dua['target_count']; ?></td>
                                <td>
                                    <?php if ($dua['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editDua(<?php echo htmlspecialchars(json_encode($dua)); ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($dua['is_active']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this dua?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $dua['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-ban"></i> Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo $dua['id']; ?>">
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
                <p class="text-center">No duas found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:8px;">
        <h3><i class="fas fa-edit"></i> Edit Dua</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label for="edit_dua_name">Dua Name (English) *</label>
                <input type="text" id="edit_dua_name" name="dua_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_dua_name_arabic">Dua Name (Arabic)</label>
                <input type="text" id="edit_dua_name_arabic" name="dua_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="edit_category">Category *</label>
                <select id="edit_category" name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <option value="dua">Dua</option>
                    <option value="tasbeeh">Tasbeeh</option>
                    <option value="namaz">Namaz</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit_target_count">Target Count *</label>
                <input type="number" id="edit_target_count" name="target_count" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="edit_display_order">Display Order *</label>
                <input type="number" id="edit_display_order" name="display_order" class="form-control" min="0" required>
                <small class="form-text text-muted">Lower numbers appear first</small>
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
function editDua(dua) {
    document.getElementById('edit_id').value = dua.id;
    document.getElementById('edit_dua_name').value = dua.dua_name;
    document.getElementById('edit_dua_name_arabic').value = dua.dua_name_arabic || '';
    document.getElementById('edit_category').value = dua.category || '';
    document.getElementById('edit_target_count').value = dua.target_count;
    document.getElementById('edit_display_order').value = dua.display_order || 0;
    document.getElementById('edit_description').value = dua.description || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>