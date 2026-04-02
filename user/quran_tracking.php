<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Quran Recitation Tracking';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quran_number = intval($_POST['quran_number']);
    $juz_number = intval($_POST['juz_number']);
    
    // Check if already completed
    $check_sql = "SELECT is_completed FROM quran_progress WHERE user_id = ? AND quran_number = ? AND juz_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        if ($row['is_completed'] == 1) {
            $error = 'This Juz is already marked as completed and cannot be unmarked.';
        }
    } else {
        // Insert new completion
        $sql = "INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) 
                VALUES (?, ?, ?, 1, CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
        
        if ($stmt->execute()) {
            $success = 'Juz marked as completed successfully!';
        } else {
            $error = 'Failed to update progress.';
        }
    }
}

// Get all progress for this user
$sql = "SELECT * FROM quran_progress WHERE user_id = ? ORDER BY quran_number, juz_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress_result = $stmt->get_result();

// Create a map of completed juz
$completed_juz = [];
while ($row = $progress_result->fetch_assoc()) {
    if ($row['is_completed']) {
        $completed_juz[$row['quran_number']][$row['juz_number']] = true;
    }
}

// Get overall progress
$quran_progress = get_quran_progress($conn, $user_id);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-quran"></i> Quran Recitation Tracking</h1>
        <p>Track your progress across 4 Qurans (120 Juz total)</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Overall Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Overall Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text">Total Progress: <?php echo $quran_progress['completed_juz']; ?> / 120 Juz (4 Qurans × 30 Juz)</span>
                <span class="progress-label-value"><?php echo $quran_progress['progress_percentage']; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $quran_progress['progress_percentage']; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Quran Progress Grid -->
    <?php for ($quran = 1; $quran <= 4; $quran++): 
        $completed_in_quran = 0;
        for ($juz = 1; $juz <= 30; $juz++) {
            if (isset($completed_juz[$quran][$juz])) {
                $completed_in_quran++;
            }
        }
        $quran_percentage = round(($completed_in_quran / 30) * 100, 2);
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book-quran"></i> Quran #<?php echo $quran; ?> - <?php echo $completed_in_quran; ?>/30 Juz (<?php echo $quran_percentage; ?>%)</h3>
        </div>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $quran_percentage; ?>%"></div>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px; padding: 20px;">
            <?php for ($juz = 1; $juz <= 30; $juz++): 
                $is_completed = isset($completed_juz[$quran][$juz]);
            ?>
                <?php if ($is_completed): ?>
                    <div style="width: 100%; padding: 15px 5px; border: 2px solid #4CAF50; border-radius: 8px; 
                               background: #4CAF50; color: #fff; font-weight: bold; text-align: center;">
                        Juz <?php echo $juz; ?>
                        <br><i class="fas fa-check-circle"></i>
                    </div>
                <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="quran_number" value="<?php echo $quran; ?>">
                        <input type="hidden" name="juz_number" value="<?php echo $juz; ?>">
                        <button type="submit" 
                                style="width: 100%; padding: 15px 5px; border: 2px solid #ddd; border-radius: 8px; 
                                       background: #fff; color: #333; cursor: pointer; font-weight: bold; transition: all 0.3s;"
                                onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'">
                            Juz <?php echo $juz; ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
    <?php endfor; ?>

    <div class="card">
        <p class="text-center" style="padding: 20px;">
            <i class="fas fa-info-circle"></i> Click on any incomplete Juz to mark it as complete. Once marked, it cannot be unmarked.
        </p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>