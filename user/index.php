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

// Get detailed progress for each category
$dua_progress_detail = get_dua_progress($conn, $user_id, 'dua');
$tasbeeh_progress_detail = get_dua_progress($conn, $user_id, 'tasbeeh');
$namaz_progress_detail = get_dua_progress($conn, $user_id, 'namaz');

// Get finance data
$settings = get_system_settings($conn);
$contributions = get_user_contributions($conn, $user_id);
$finance_progress = calculate_percentage($contributions['total_inr'], $settings['target_amount_inr']); 
$remaining_inr = $settings['target_amount_inr'] - $contributions['total_inr'];

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
            </div>
        </div>
    </div>

    <!-- Ziyarat Card -->
    <div class="card" style="border-top: 4px solid #0ea5e9; background-color: #f0f9ff;">
        <div class="card-header" style="background-color: #e0f2fe;">
            <h3 style="color: #0369a1;"><i class="fas fa-mosque"></i> Ziyarat Raudat Tahera</h3>
        </div>
        <div style="padding: var(--spacing-lg); text-align: center;">
            <p style="margin-bottom: 1rem; color: #0c4a6e;">Perform your Ziyarat and record your presence at Raudat Tahera through the official portal.</p>
            <a href="https://ziyarat1449.web.app/" target="_blank" class="btn btn-info" style="background-color: #0ea5e9; border-color: #0284c7; padding: 0.75rem 2rem;">
                <i class="fas fa-external-link-alt"></i> Open Ziyarat Portal
            </a>
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

        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-card-header">
                <h4>Contribution Paid</h4>
                <div class="stat-icon" style="color: #10b981;">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo format_currency($contributions['total_inr'], 'INR'); ?></div>
            <div class="stat-label">Remaining: <?php echo format_currency($remaining_inr, 'INR'); ?></div>
        </div>
    </div>

    <!-- Finance Progress Overview -->
    <div class="card" style="border-top: 4px solid #10b981;">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding-usd"></i> Ziyafat Contribution Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text">Paid: <?php echo format_currency($contributions['total_inr'], 'INR'); ?> / <?php echo format_currency($settings['target_amount_inr'], 'INR'); ?></span>
                <span class="progress-label-value"><?php echo $finance_progress; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $finance_progress; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
            </div>
        </div>
        <?php if ($user['category'] === 'Surat'): ?>
        <p class="text-center mt-2">
            <a href="surat_finance_report.php" class="btn btn-success" style="background-color: #10b981; border-color: #059669;">
                <i class="fas fa-file-invoice-dollar"></i> View Detailed Finance Report
            </a>
        </p>
        <?php endif; ?>
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
            <h3><i class="fas fa-chart-bar"></i> Dua, Tasbeeh & Namaz Detailed Progress</h3>
        </div>
        
        <!-- Duas -->
        <div style="padding: 1rem; border-bottom: 1px solid #eee;">
            <h4 style="margin-bottom: 1rem; color: #10b981;"><i class="fas fa-hands-praying"></i> Duas</h4>
            <div class="table-container">
                <?php if ($dua_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Dua Name</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dua = $dua_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dua['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $dua['progress_percentage']; ?>%; height: 12px;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $dua['completed_count']; ?></td>
                                    <td><?php echo $dua['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tasbeeh -->
        <div style="padding: 1rem; border-bottom: 1px solid #eee;">
            <h4 style="margin-bottom: 1rem; color: #f59e0b;"><i class="fas fa-dharmachakra"></i> Tasbeeh</h4>
            <div class="table-container">
                <?php if ($tasbeeh_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tasbeeh</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tasbeeh = $tasbeeh_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tasbeeh['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $tasbeeh['progress_percentage']; ?>%; height: 12px; background: #f59e0b;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $tasbeeh['completed_count']; ?></td>
                                    <td><?php echo $tasbeeh['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Namaz -->
        <div style="padding: 1rem;">
            <h4 style="margin-bottom: 1rem; color: #8b5cf6;"><i class="fas fa-mosque"></i> Namaz</h4>
            <div class="table-container">
                <?php if ($namaz_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Namaz</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($namaz = $namaz_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($namaz['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $namaz['progress_percentage']; ?>%; height: 12px; background: #8b5cf6;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $namaz['completed_count']; ?></td>
                                    <td><?php echo $namaz['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-center mt-2" style="padding-bottom: 1rem;">
            <a href="dua_tracking.php" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Add New Entry
            </a>
        </p>
    </div>

    <!-- Book Progress Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Istinsakh ul Kutub Progress</h3>
        </div>
        <div class="table-container">
            <?php if ($book_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $book_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td>
                                    <?php if ($book['status'] === 'completed'): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $book['started_date'] ? date('M d, Y', strtotime($book['started_date'])) : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center" style="padding: 1rem;">No books currently selected.</p>
            <?php endif; ?>
        </div>
        <p class="text-center mt-2" style="padding-bottom: 1rem;">
            <a href="book_transcription.php" class="btn btn-warning">
                <i class="fas fa-arrow-right"></i> Manage Books
            </a>
        </p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>