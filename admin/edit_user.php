<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Edit User Details';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Get user ID
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    header('Location: view_users.php');
    exit();
}

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: view_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $its_number = clean_input($_POST['its_number']);
    $tr_number = clean_input($_POST['tr_number']);
    $category = clean_input($_POST['category']);
    $classification = clean_input($_POST['classification']);
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $phone_number = clean_input($_POST['phone_number']);
    $role = clean_input($_POST['role']);
    $admin_type = ($role === 'admin' && !empty($_POST['admin_type'])) ? clean_input($_POST['admin_type']) : null;

    if (empty($its_number) || empty($name) || empty($email)) {
        $error = 'Please fill in all required fields';
    } else {
        // Check if email or ITS number already exists (excluding current user)
        $sql = "SELECT * FROM users WHERE (email = ? OR its_number = ?) AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $email, $its_number, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email or ITS number already exists for another user';
        } else {
            // Update user
            $sql = "UPDATE users 
                    SET its_number = ?, tr_number = ?, category = ?, classification = ?, 
                        name = ?, email = ?, phone_number = ?, role = ?, admin_type = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $its_number, $tr_number, $category, $classification, 
                             $name, $email, $phone_number, $role, $admin_type, $user_id);

            if ($stmt->execute()) {
                $success = 'User details updated successfully!';
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update user details. Please try again.';
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-user-edit"></i> Edit User Details</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> User Information</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <p><strong>User ID:</strong> #<?php echo $user['id']; ?></p>
            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></p>
            <p><strong>Current Role:</strong> <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($user['role']); ?></span></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit User Details</h3>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="its_number"><i class="fas fa-id-card"></i> ITS Number *</label>
                <input type="text" id="its_number" name="its_number" class="form-control" 
                       value="<?php echo htmlspecialchars($user['its_number']); ?>" required>
            </div>

            <div class="form-group">
                <label for="tr_number"><i class="fas fa-id-badge"></i> TR Number</label>
                <input type="text" id="tr_number" name="tr_number" class="form-control" 
                       value="<?php echo htmlspecialchars($user['tr_number']); ?>">
            </div>

            <div class="form-group">
                <label for="category"><i class="fas fa-map-marker-alt"></i> Jamea (Branch)</label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select Jamea --</option>
                    <option value="Surat" <?php echo $user['category'] === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                    <option value="Marol" <?php echo $user['category'] === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                    <option value="Karachi" <?php echo $user['category'] === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                    <option value="Nairobi" <?php echo $user['category'] === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                    <option value="Muntasib" <?php echo $user['category'] === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                </select>
            </div>

            <div class="form-group">
                <label for="classification"><i class="fas fa-tags"></i> Classification (Class)</label>
                <select id="classification" name="classification" class="form-control">
                    <option value="">-- Select Classification --</option>
                    <option value="Talabat" <?php echo $user['classification'] === 'Talabat' ? 'selected' : ''; ?>>Talabat</option>
                    <option value="Taalebaat" <?php echo $user['classification'] === 'Taalebaat' ? 'selected' : ''; ?>>Taalebaat</option>
                    <option value="Muntasebeen" <?php echo $user['classification'] === 'Muntasebeen' ? 'selected' : ''; ?>>Muntasebeen</option>
                    <option value="Muntasebaat" <?php echo $user['classification'] === 'Muntasebaat' ? 'selected' : ''; ?>>Muntasebaat</option>
                </select>
            </div>

            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="form-control" 
                       value="<?php echo htmlspecialchars($user['phone_number']); ?>" placeholder="+1234567890">
            </div>

            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Role *</label>
                <select id="role" name="role" class="form-control" required onchange="toggleAdminType()">
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="form-group" id="admin_type_group" style="display: <?php echo $user['role'] === 'admin' ? 'block' : 'none'; ?>;">
                <label for="admin_type"><i class="fas fa-user-shield"></i> Admin Type</label>
                <select id="admin_type" name="admin_type" class="form-control">
                    <option value="">Select Admin Type</option>
                    <option value="super_admin" <?php echo $user['admin_type'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin (Full Access)</option>
                    <option value="finance_admin" <?php echo $user['admin_type'] === 'finance_admin' ? 'selected' : ''; ?>>Finance Admin</option>
                    <option value="amali_coordinator" <?php echo $user['admin_type'] === 'amali_coordinator' ? 'selected' : ''; ?>>Amali Coordinator (All Categories)</option>
                    <option value="surat_amali_coordinator" <?php echo $user['admin_type'] === 'surat_amali_coordinator' ? 'selected' : ''; ?>>Surat Amali Coordinator</option>
                    <option value="marol_amali_coordinator" <?php echo $user['admin_type'] === 'marol_amali_coordinator' ? 'selected' : ''; ?>>Marol Amali Coordinator</option>
                    <option value="karachi_amali_coordinator" <?php echo $user['admin_type'] === 'karachi_amali_coordinator' ? 'selected' : ''; ?>>Karachi Amali Coordinator</option>
                    <option value="nairobi_amali_coordinator" <?php echo $user['admin_type'] === 'nairobi_amali_coordinator' ? 'selected' : ''; ?>>Nairobi Amali Coordinator</option>
                    <option value="muntasib_amali_coordinator" <?php echo $user['admin_type'] === 'muntasib_amali_coordinator' ? 'selected' : ''; ?>>Muntasib Amali Coordinator</option>
                </select>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> Password cannot be changed from this page. User can change their password from their profile page.
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update User Details
                </button>
               
                <a href="view_users.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- User Activity Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> User Activity Summary</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <?php
            // Get user's activity stats
            $sql = "SELECT 
                        COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) as completed_juz,
                        FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) / 30) as completed_qurans,
                        COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN bt.book_id END) as books_completed,
                        COUNT(DISTINCT CASE WHEN bt.status = 'selected' THEN bt.book_id END) as books_in_progress
                    FROM users u
                    LEFT JOIN quran_progress qp ON u.id = qp.user_id
                    LEFT JOIN book_transcription bt ON u.id = bt.user_id
                    WHERE u.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();

            // Get dua stats
            $sql = "SELECT dm.category, COALESCE(SUM(de.count_added), 0) as count
                    FROM duas_master dm
                    LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
                    WHERE dm.is_active = 1
                    GROUP BY dm.category";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $dua_result = $stmt->get_result();
            $dua_stats = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
            while ($row = $dua_result->fetch_assoc()) {
                $dua_stats[$row['category']] = $row['count'];
            }
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="stat-card success">
                    <h4><i class="fas fa-quran"></i> Quran</h4>
                    <div class="stat-value"><?php echo $stats['completed_qurans']; ?> / 4</div>
                    <div class="stat-label"><?php echo $stats['completed_juz']; ?> Juz completed</div>
                </div>
                <div class="stat-card warning">
                    <h4><i class="fas fa-hands-praying"></i> Duas</h4>
                    <div class="stat-value"><?php echo number_format($dua_stats['dua']); ?></div>
                    <div class="stat-label">Recited</div>
                </div>
                <div class="stat-card info">
                    <h4><i class="fas fa-dharmachakra"></i> Tasbeeh</h4>
                    <div class="stat-value"><?php echo number_format($dua_stats['tasbeeh']); ?></div>
                    <div class="stat-label">Count</div>
                </div>
                <div class="stat-card purple">
                    <h4><i class="fas fa-mosque"></i> Namaz</h4>
                    <div class="stat-value"><?php echo number_format($dua_stats['namaz']); ?></div>
                    <div class="stat-label">Count</div>
                </div>
                <div class="stat-card danger">
                    <h4><i class="fas fa-book"></i> Kutub</h4>
                    <div class="stat-value"><?php echo $stats['books_completed']; ?></div>
                    <div class="stat-label"><?php echo $stats['books_in_progress']; ?> in progress</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAdminType() {
    var role = document.getElementById('role').value;
    var adminTypeGroup = document.getElementById('admin_type_group');
    if (role === 'admin') {
        adminTypeGroup.style.display = 'block';
    } else {
        adminTypeGroup.style.display = 'none';
        document.getElementById('admin_type').value = '';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>