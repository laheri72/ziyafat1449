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
    if (isset($_POST['reset_password'])) {
        if (is_super_admin()) {
            if (!empty($user['tr_number'])) {
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $user['tr_number'], $user_id);
                if ($stmt->execute()) {
                    $success = 'Password reset to TR Number (' . $user['tr_number'] . ') successfully!';
                } else {
                    $error = 'Failed to reset password.';
                }
            } else {
                $error = 'User does not have a TR Number. Password cannot be reset to TR Number.';
            }
        } else {
            $error = 'Only Super Admins can reset passwords.';
        }
    } else {
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

    <!-- Quick Data Entry (Admin Override) -->
    <?php if (has_amali_access()): ?>
    <div class="card" style="margin-top: 1rem; border-left: 5px solid #10b981;">
        <div class="card-header" style="background-color: #ecfdf5; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color: #047857; margin: 0;"><i class="fas fa-bolt"></i> Quick Data Entry (Admin Override)</h3>
            <span class="badge badge-success">Amali Coordinator</span>
        </div>
        <div style="padding: var(--spacing-lg); display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            
            <!-- Quick Quran Entry -->
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; grid-column: 1 / -1;">
                <h4 style="margin-top: 0; color: #0f172a; font-size: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-quran text-success"></i> Mark Quran Juz (Multi-select)</span>
                    <button type="button" class="btn btn-success btn-sm" onclick="submitQuickQuran(<?php echo $user_id; ?>)">
                        <i class="fas fa-save"></i> Save Selected Juz
                    </button>
                </h4>
                
                <style>
                    .admin-quran-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; }
                    .admin-quran-tab { padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; background: #f1f5f9; color: #64748b; }
                    .admin-quran-tab.active { background: #10b981; color: #fff; font-weight: bold; }
                    .admin-juz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 0.4rem; }
                    .admin-juz-item { border: 1px solid #e2e8f0; padding: 0.4rem; text-align: center; border-radius: 4px; cursor: pointer; font-size: 0.75rem; background: #fff; }
                    .admin-juz-item.completed { background: #dcfce7; color: #166534; border-color: #86efac; cursor: default; pointer-events: none; opacity: 0.7; }
                    .admin-juz-item.selected { background: #fef3c7; color: #92400e; border-color: #fcd34d; font-weight: bold; }
                    .admin-juz-pane { display: none; }
                    .admin-juz-pane.active { display: block; }
                </style>

                <div class="admin-quran-tabs">
                    <?php for($q=1; $q<=4; $q++): ?>
                        <div class="admin-quran-tab <?php echo $q===1 ? 'active' : ''; ?>" onclick="switchAdminQuran(<?php echo $q; ?>)" id="tab-quran-<?php echo $q; ?>">Quran <?php echo $q; ?></div>
                    <?php endfor; ?>
                </div>

                <?php 
                // Get all completed juz for this user to pre-mark them
                $comp_juz_sql = "SELECT quran_number, juz_number FROM quran_progress WHERE user_id = ? AND is_completed = 1";
                $comp_stmt = $conn->prepare($comp_juz_sql);
                $comp_stmt->bind_param("i", $user_id);
                $comp_stmt->execute();
                $comp_res = $comp_stmt->get_result();
                $completed_juz_map = [];
                while($row = $comp_res->fetch_assoc()) {
                    $completed_juz_map[$row['quran_number']][$row['juz_number']] = true;
                }
                ?>

                <?php for($q=1; $q<=4; $q++): ?>
                <div class="admin-juz-pane <?php echo $q===1 ? 'active' : ''; ?>" id="pane-quran-<?php echo $q; ?>">
                    <div style="margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                        <span style="font-size: 0.8rem; color: #64748b;">Click to select multiple juz. Completed ones are green.</span>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size: 0.7rem; padding: 2px 8px;" onclick="selectAllAdminJuz(<?php echo $q; ?>)">
                            <i class="fas fa-check-double"></i> Select All Remaining
                        </button>
                    </div>
                    <div class="admin-juz-grid">
                        <?php for($j=1; $j<=30; $j++): 
                            $is_done = isset($completed_juz_map[$q][$j]);
                        ?>
                            <div class="admin-juz-item <?php echo $is_done ? 'completed' : ''; ?>" 
                                 data-quran="<?php echo $q; ?>" data-juz="<?php echo $j; ?>"
                                 onclick="toggleAdminJuz(this)">
                                <?php echo $j; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Quick Tasbeeh Entry -->
            <?php
            $tasbeeh_list = $conn->query("SELECT id, dua_name FROM duas_master WHERE is_active = 1 AND category = 'tasbeeh' ORDER BY display_order");
            ?>
            <div style="background: #fff7ed; padding: 1rem; border-radius: 8px; border: 1px solid #ffedd5;">
                <h4 style="margin-top: 0; color: #9a3412; font-size: 1rem;"><i class="fas fa-dharmachakra text-warning"></i> Add Tasbeeh Count</h4>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <select id="quick_tasbeeh_id" class="form-control" style="padding: 0.4rem; font-size: 0.85rem;">
                        <option value="">Select Tasbeeh...</option>
                        <?php while($t = $tasbeeh_list->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['dua_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <input type="number" id="quick_tasbeeh_count" class="form-control" placeholder="Count to add" min="1" style="padding: 0.4rem; font-size: 0.85rem;">
                </div>
                <button type="button" class="btn btn-warning btn-sm w-100" style="background-color: #f97316; border-color: #ea580c;" onclick="submitQuickDua(<?php echo $user_id; ?>, 'tasbeeh')">
                    <i class="fas fa-plus"></i> Add Tasbeeh
                </button>
            </div>

            <!-- Quick Dua/Namaz Entry -->
            <?php
            $duas_list = $conn->query("SELECT id, dua_name, category FROM duas_master WHERE is_active = 1 AND category IN ('dua', 'namaz') ORDER BY category, display_order");
            ?>
            <div style="background: #f5f3ff; padding: 1rem; border-radius: 8px; border: 1px solid #ddd6fe;">
                <h4 style="margin-top: 0; color: #5b21b6; font-size: 1rem;"><i class="fas fa-hands-praying text-purple"></i> Add Dua/Namaz Count</h4>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <select id="quick_dua_id" class="form-control" style="padding: 0.4rem; font-size: 0.85rem;">
                        <option value="">Select Dua/Namaz...</option>
                        <?php while($dua = $duas_list->fetch_assoc()): ?>
                            <option value="<?php echo $dua['id']; ?>">[<?php echo ucfirst($dua['category']); ?>] <?php echo htmlspecialchars($dua['dua_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <input type="number" id="quick_dua_count" class="form-control" placeholder="Count to add" min="1" style="padding: 0.4rem; font-size: 0.85rem;">
                </div>
                <button type="button" class="btn btn-primary btn-sm w-100" style="background-color: #8b5cf6; border-color: #7c3aed;" onclick="submitQuickDua(<?php echo $user_id; ?>, 'dua')">
                    <i class="fas fa-plus"></i> Add Count
                </button>
            </div>

            <!-- Quick Book Entry -->
            <?php
            $books_list = $conn->query("SELECT bm.id, bm.book_name, bt.id as is_selected FROM books_master bm LEFT JOIN book_transcription bt ON bm.id = bt.book_id AND bt.user_id = $user_id WHERE bm.is_active = 1 ORDER BY bm.display_order");
            ?>
            <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; border: 1px solid #fee2e2;">
                <h4 style="margin-top: 0; color: #7f1d1d; font-size: 1rem;"><i class="fas fa-book text-danger"></i> Update Book Progress</h4>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <select id="quick_book_id" class="form-control" style="padding: 0.4rem; font-size: 0.85rem;">
                        <option value="">Select Book...</option>
                        <?php while($book = $books_list->fetch_assoc()): ?>
                            <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['book_name']); ?> <?php echo $book['is_selected'] ? '(Active)' : '(Not Started)'; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <input type="number" id="quick_book_pages" class="form-control" placeholder="Total pages completed" min="0" style="padding: 0.4rem; font-size: 0.85rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary btn-sm w-100" onclick="submitQuickBook(<?php echo $user_id; ?>, 'select')">
                        <i class="fas fa-play"></i> Start Book
                    </button>
                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="submitQuickBook(<?php echo $user_id; ?>, 'update_progress')">
                        <i class="fas fa-save"></i> Update Pages
                    </button>
                </div>
            </div>
        </div>
    </div>
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

            <?php if (is_super_admin()): ?>
                <div class="alert alert-warning" style="margin: 20px 0; border-left: 5px solid #f59e0b;">
                    <h4 style="margin-top: 0; color: #92400e;"><i class="fas fa-user-shield"></i> Super Admin: Password Reset</h4>
                    <p style="margin-bottom: 15px;">You can reset this user's password to their <strong>TR Number (<?php echo htmlspecialchars($user['tr_number'] ?: 'Not Set'); ?>)</strong> if they have forgotten it.</p>
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to reset the password to the TR Number?');">
                        <button type="submit" name="reset_password" class="btn btn-warning" <?php echo empty($user['tr_number']) ? 'disabled' : ''; ?>>
                            <i class="fas fa-sync-alt"></i> Reset Password to TR Number
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Password cannot be changed from this page. User can change their password from their profile page.
                </div>
            <?php endif; ?>

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

    </div>

    <script>
async function submitQuickQuran(userId) {
    const selectedItems = document.querySelectorAll('.admin-juz-item.selected');
    if (selectedItems.length === 0) return showToast('Please select at least one Juz.', 'error');
    
    const selections = Array.from(selectedItems).map(item => ({
        quran_number: item.dataset.quran,
        juz_number: item.dataset.juz
    }));
    
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    try {
        const response = await fetch('../user/ajax_quran_tracking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                target_user_id: userId,
                selections: selections 
            })
        });
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'Quran progress updated!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to update progress', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (e) {
        showToast('Network error occurred.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function switchAdminQuran(quranNum) {
    document.querySelectorAll('.admin-quran-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.admin-juz-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-quran-' + quranNum).classList.add('active');
    document.getElementById('pane-quran-' + quranNum).classList.add('active');
}

function toggleAdminJuz(element) {
    if (element.classList.contains('completed')) return;
    element.classList.toggle('selected');
}

function selectAllAdminJuz(quranNum) {
    const pane = document.getElementById('pane-quran-' + quranNum);
    const items = pane.querySelectorAll('.admin-juz-item:not(.completed)');
    const allSelected = Array.from(items).every(i => i.classList.contains('selected'));
    
    items.forEach(i => {
        if (allSelected) i.classList.remove('selected');
        else i.classList.add('selected');
    });
}

async function submitQuickDua(userId, type) {
    const idField = type === 'tasbeeh' ? 'quick_tasbeeh_id' : 'quick_dua_id';
    const countField = type === 'tasbeeh' ? 'quick_tasbeeh_count' : 'quick_dua_count';
    
    const duaId = document.getElementById(idField).value;
    const count = document.getElementById(countField).value;
    
    if (!duaId || !count) return showToast('Please select an item and enter a count.', 'error');
    
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    const formData = new FormData();
    formData.append('target_user_id', userId);
    formData.append('dua_id', duaId);
    formData.append('count_to_add', count);
    
    try {
        const response = await fetch('../user/ajax_dua_entry.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showToast('Entry added for user!', 'success');
            document.getElementById(countField).value = '';
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to add count', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (e) {
        showToast('Network error occurred.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function submitQuickBook(userId, action) {
    const bookId = document.getElementById('quick_book_id').value;
    const pages = document.getElementById('quick_book_pages').value;
    
    if (!bookId) return showToast('Please select a book.', 'error');
    if (action === 'update_progress' && !pages) return showToast('Please enter pages completed.', 'error');
    
    const btn = event.currentTarget;
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('target_user_id', userId);
    formData.append('action', action);
    formData.append('book_id', bookId);
    if (action === 'update_progress') formData.append('pages_completed', pages);
    
    try {
        const response = await fetch('../user/ajax_book_transcription.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showToast('Book progress updated for user!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to update book', 'error');
        }
    } catch (e) {
        showToast('Network error occurred.', 'error');
    }
    btn.disabled = false;
}

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