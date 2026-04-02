<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'User Dashboard';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get user data
$user = get_user_by_id($conn, $user_id);

// Get Quran progress
$quran_progress = get_quran_progress($conn, $user_id);

// Get category-wise totals
$sql_cat_totals = "SELECT 
                    dm.category,
                    COALESCE(SUM(de.count_added), 0) as total_count
                FROM duas_master dm
                LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
                WHERE dm.is_active = 1
                GROUP BY dm.category";
$stmt = $conn->prepare($sql_cat_totals);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cat_result = $stmt->get_result();
$category_totals = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
while ($row = $cat_result->fetch_assoc()) {
    $category_totals[$row['category']] = $row['total_count'];
}

// Get Book progress
$book_progress = get_book_progress($conn, $user_id);

// Get overall summary
$summary = get_amali_summary($conn, $user_id);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        <p>Track your Amali Janib progress</p>
    </div>

    <!-- Quick Navigation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-compass"></i> Quick Navigation</h3>
        </div>
        <div style="padding: var(--spacing-xl);">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 0;">
                <a href="quran_tracking.php" class="btn btn-primary" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-quran" style="font-size: 1.5rem;"></i>
                    <span>Quran Hifzan</span>
                </a>
                <a href="dua_tracking.php" class="btn btn-success" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-hands-praying" style="font-size: 1.5rem;"></i>
                    <span>Dua Tracking</span>
                </a>
                <a href="book_transcription.php" class="btn btn-warning" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-book" style="font-size: 1.5rem;"></i>
                    <span>Istinsakh Kutub</span>
                </a>
                <a href="amali_janib.php" class="btn btn-secondary" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-chart-line" style="font-size: 1.5rem;"></i>
                    <span>Full Dashboard</span>
                </a>
            </div>
        </div>
    </div>


    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <h4>Quran Completed</h4>
                <div class="stat-icon">
                    <i class="fas fa-quran"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['completed_qurans'] ?? 0; ?>/4</div>
            <div class="stat-label"><?php echo $quran_progress['completed_juz']; ?> Juz (<?php echo $quran_progress['progress_percentage']; ?>%)</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <h4>Duas Recited</h4>
                <div class="stat-icon">
                    <i class="fas fa-hands-praying"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['dua']; ?></div>
            <div class="stat-label">Duas</div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <h4>Tasbeeh</h4>
                <div class="stat-icon">
                    <i class="fas fa-dharmachakra"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['tasbeeh']; ?></div>
            <div class="stat-label">Tasbeeh Count</div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <h4>Namaz</h4>
                <div class="stat-icon">
                    <i class="fas fa-mosque"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['namaz']; ?></div>
            <div class="stat-label">Namaz Count</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <h4>Kutub Completed</h4>
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['books_completed'] ?? 0; ?></div>
            <div class="stat-label">Istinsakh Completed</div>
        </div>

        <div class="stat-card purple">
            <div class="stat-card-header">
                <h4>Kutub In Progress</h4>
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['books_in_progress'] ?? 0; ?></div>
            <div class="stat-label">Current Istinsakh</div>
        </div>
    </div>

    <!-- Quran Progress Overview -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-quran"></i> Quran Recitation Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text">Overall Progress: <?php echo $quran_progress['completed_juz']; ?> / 120 Juz</span>
                <span class="progress-label-value"><?php echo $quran_progress['progress_percentage']; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $quran_progress['progress_percentage']; ?>%"></div>
            </div>
        </div>
        <p class="text-center mt-2">
            <a href="quran_tracking.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> View Details & Update
            </a>
        </p>
    </div>

    <!-- Category Progress Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Dua, Tasbeeh & Namaz Summary</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-card-header">
                    <h4>Duas</h4>
                    <div class="stat-icon">
                        <i class="fas fa-hands-praying"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_totals['dua']; ?></div>
                <div class="stat-label">Total Recited</div>
            </div>

            <div class="stat-card info">
                <div class="stat-card-header">
                    <h4>Tasbeeh</h4>
                    <div class="stat-icon">
                        <i class="fas fa-dharmachakra"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_totals['tasbeeh']; ?></div>
                <div class="stat-label">Total Count</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-card-header">
                    <h4>Namaz</h4>
                    <div class="stat-icon">
                        <i class="fas fa-mosque"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_totals['namaz']; ?></div>
                <div class="stat-label">Total Count</div>
            </div>
        </div>
        <p class="text-center mt-2">
            <a href="dua_tracking.php" class="btn btn-success">
                <i class="fas fa-arrow-right"></i> Update Progress
            </a>
        </p>
    </div>

    <!-- Book Progress Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Istinsakh ul Kutub Progress</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-card-header">
                    <h4>Kutub Completed</h4>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $summary['books_completed'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-card-header">
                    <h4>Kutub In Progress</h4>
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $summary['books_in_progress'] ?? 0; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <p class="text-center mt-2">
            <a href="book_transcription.php" class="btn btn-warning">
                <i class="fas fa-arrow-right"></i> Manage Books
            </a>
        </p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>