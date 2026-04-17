<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

if (!is_super_admin()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Bulk Amali Entry';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$success = '';
$error = '';

// Normalize legacy virtual bulk accounts to system scope.
$conn->query("UPDATE users SET role = 'system', category = 'system' WHERE its_number LIKE '000000%'");

// 1. Ensure Collective User exists
$bulk_its = '00000000';
$check_sql = "SELECT id FROM users WHERE its_number = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("s", $bulk_its);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $ins_sql = "INSERT INTO users (its_number, name, role, category, password, email) VALUES (?, 'Collective Bulk Entries', 'system', 'system', 'nopassword', 'bulk@system.local')";
    $ins_stmt = $conn->prepare($ins_sql);
    $ins_stmt->bind_param("s", $bulk_its);
    $ins_stmt->execute();
    $bulk_user_id = $conn->insert_id;
} else {
    $bulk_user_id = $res->fetch_assoc()['id'];
    // Normalize legacy bulk account metadata for consistent role-based reporting.
    $fix_sql = "UPDATE users SET role = 'system', category = 'system' WHERE id = ?";
    $fix_stmt = $conn->prepare($fix_sql);
    $fix_stmt->bind_param("i", $bulk_user_id);
    $fix_stmt->execute();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0); // Prevent timeout for large batches
    $type = $_POST['entry_type'] ?? '';
    
    try {
        if ($type === 'quran') {
            $qurans_to_add = intval($_POST['quran_count'] ?? 0);
            if ($qurans_to_add > 0) {
                $juz_to_add = $qurans_to_add * 30;
                $juz_inserted = 0;
                $batch_size = 500; // Insert in chunks for efficiency
                
                // We will use virtual storage users '00000000' through '00000099'
                // to store bulk Qurans without hitting the 1-4 limit per user
                for ($v = 0; $v < 100; $v++) {
                    if ($juz_inserted >= $juz_to_add) break;
                    
                    $v_its = str_pad($v, 8, '0', STR_PAD_LEFT);
                    
                    // Ensure this storage user exists
                    $u_check = $conn->query("SELECT id FROM users WHERE its_number = '$v_its'");
                    if ($u_check->num_rows === 0) {
                        $conn->query("INSERT INTO users (its_number, name, role, category, password, email) 
                                     VALUES ('$v_its', 'Bulk Storage $v', 'system', 'system', 'nopassword', 'bulk$v@system.local')");
                        $v_user_id = $conn->insert_id;
                    } else {
                        $v_user_id = $u_check->fetch_assoc()['id'];
                        $conn->query("UPDATE users SET role = 'system', category = 'system' WHERE id = $v_user_id");
                    }
                    
                    // Fill this user's 4 Qurans (120 Juz)
                    for ($q = 1; $q <= 4; $q++) {
                        if ($juz_inserted >= $juz_to_add) break;
                        
                        // Check how many juz this user already has for this Quran
                        $count_check = $conn->query("SELECT COUNT(*) as c FROM quran_progress WHERE user_id = $v_user_id AND quran_number = $q");
                        $existing = $count_check->fetch_assoc()['c'];
                        
                        if ($existing < 30) {
                            $values = [];
                            for ($j = 1; $j <= 30; $j++) {
                                if ($juz_inserted >= $juz_to_add) break;
                                
                                // Check specific juz
                                $j_check = $conn->query("SELECT id FROM quran_progress WHERE user_id = $v_user_id AND quran_number = $q AND juz_number = $j");
                                if ($j_check->num_rows === 0) {
                                    $values[] = "($v_user_id, $q, $j, 1, CURDATE())";
                                    $juz_inserted++;
                                    
                                    // Periodic batch insert
                                    if (count($values) >= 100) {
                                        $conn->query("INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) VALUES " . implode(',', $values));
                                        $values = [];
                                    }
                                }
                            }
                            if (!empty($values)) {
                                $conn->query("INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) VALUES " . implode(',', $values));
                            }
                        }
                    }
                }
                
                $final_q = floor($juz_inserted / 30);
                $success = "Successfully added $juz_inserted Juz (approx $final_q full Qurans) to system storage.";
            }
        } elseif ($type === 'dua') {
            $conn->begin_transaction();
            $dua_id = intval($_POST['dua_id'] ?? 0);
            $count = intval($_POST['count'] ?? 0);
            if ($dua_id > 0 && $count > 0) {
                $sql = "INSERT INTO dua_entries (user_id, dua_id, count_added, entry_date) VALUES (?, ?, ?, CURDATE())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $bulk_user_id, $dua_id, $count);
                $stmt->execute();
                $success = "Successfully added collective count to the system.";
            }
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-layer-group"></i> Bulk Collective Amali Entry</h1>
        <p>Use this to add counts from physical data collections where individual names are unknown.</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

    <div class="stats-grid">
        <!-- Collective Quran Entry -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-quran"></i> Add Bulk Qurans</h3></div>
            <form method="POST" style="padding: 1.5rem;">
                <input type="hidden" name="entry_type" value="quran">
                <div class="form-group">
                    <label>Number of Full Qurans Completed collectively</label>
                    <input type="number" name="quran_count" class="form-control" placeholder="e.g. 50" required min="1">
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add to System Totals</button>
            </form>
        </div>

        <!-- Collective Dua/Tasbeeh Entry -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-hands-praying"></i> Add Bulk Dua/Tasbeeh</h3></div>
            <form method="POST" style="padding: 1.5rem;">
                <input type="hidden" name="entry_type" value="dua">
                <div class="form-group">
                    <label>Select Item</label>
                    <select name="dua_id" class="form-control" required>
                        <option value="">-- Select --</option>
                        <?php
                        $list = $conn->query("SELECT id, dua_name, category FROM duas_master WHERE is_active = 1 ORDER BY category, display_order");
                        while($row = $list->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>">[<?php echo ucfirst($row['category']); ?>] <?php echo htmlspecialchars($row['dua_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Count to Add</label>
                    <input type="number" name="count" class="form-control" placeholder="e.g. 5000" required min="1">
                </div>
                <button type="submit" class="btn btn-warning w-100"><i class="fas fa-plus"></i> Add to System Totals</button>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h3><i class="fas fa-info-circle"></i> Professional Note</h3></div>
        <div style="padding: 1.5rem;">
            <p>Entries made here are attributed to a <strong>System Virtual Account (ITS: 00000000)</strong>. 
            This ensures your overall progress bars and totals increase correctly while keeping individual user statistics accurate.
            This user is automatically excluded from "Active User" counts to prevent skewing community size data.</p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
