<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Admin Dashboard';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Check admin type
$is_finance_admin = has_finance_access();
$is_amali_admin = has_amali_access();

// Get total users count
$sql = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($sql);
$total_users = $result->fetch_assoc()['total'];

// Finance-related data (only for finance admins)
if ($is_finance_admin) {
    // Get system settings
    $settings = get_system_settings($conn);
    
    // Calculate total target for all users
    $total_target_usd = $settings['target_amount_usd'] * $total_users;
    $total_target_inr = $settings['target_amount_inr'] * $total_users;
    
    // Get all contributions
    $all_contributions = get_all_contributions($conn);
    
    // Calculate remaining amounts
    $remaining_usd = $total_target_usd - $all_contributions['total_usd'];
    $remaining_inr = $total_target_inr - $all_contributions['total_inr'];
    
    // Calculate progress percentage
    $progress = calculate_percentage($all_contributions['total_usd'], $total_target_usd);
    
    // Get recent transactions
    $sql = "SELECT c.*, u.name, u.its_number, u.tr_number 
            FROM contributions c 
            JOIN users u ON c.user_id = u.id 
            ORDER BY c.created_at DESC 
            LIMIT 10";
    $recent_transactions = $conn->query($sql);
}

// Amali-related data (only for amali coordinators)
if ($is_amali_admin) {
    // Get overall amali statistics - Quran and Books
    $sql = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) as total_juz_completed,
                FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) / 30) as total_qurans_completed,
                COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN CONCAT(bt.user_id, '-', bt.book_id) END) as total_books_completed
            FROM users u
            LEFT JOIN quran_progress qp ON u.id = qp.user_id
            LEFT JOIN book_transcription bt ON u.id = bt.user_id";
    $amali_stats = $conn->query($sql)->fetch_assoc();
    
    // Get category-wise dua counts
    $sql_categories = "SELECT 
                        dm.category,
                        COALESCE(SUM(de.count_added), 0) as total_count
                    FROM duas_master dm
                    LEFT JOIN dua_entries de ON dm.id = de.dua_id
                    WHERE dm.is_active = 1
                    GROUP BY dm.category";
    $category_result = $conn->query($sql_categories);
    $category_stats = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
    while ($row = $category_result->fetch_assoc()) {
        $category_stats[$row['category']] = $row['total_count'];
    }
    
    // Calculate overall progress
    $target_qurans = $total_users * 4; // 4 Qurans per user
    $target_juz = $total_users * 120; // 120 Juz per user
    
    $quran_progress = $target_qurans > 0 ? round(($amali_stats['total_qurans_completed'] / $target_qurans) * 100, 2) : 0;
    $juz_progress = $target_juz > 0 ? round(($amali_stats['total_juz_completed'] / $target_juz) * 100, 2) : 0;
    
    // Get books stats
    $sql_books = "SELECT 
                    COUNT(DISTINCT CASE WHEN status = 'completed' THEN CONCAT(user_id, '-', book_id) END) as total_completed,
                    COUNT(DISTINCT CASE WHEN status = 'selected' THEN CONCAT(user_id, '-', book_id) END) as total_in_progress
                  FROM book_transcription";
    $books_stats = $conn->query($sql_books)->fetch_assoc();
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Admin Dashboard</h1>
        <p>Welcome back! Here's an overview of the system.</p>
    </div>

    <?php if ($is_finance_admin): ?>
        <!-- FINANCE ADMIN SECTION -->
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Total Users</h4>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Registered users</div>
            </div>

            <div class="stat-card success">
                <div class="stat-card-header">
                    <h4>Total Collected</h4>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo format_currency($all_contributions['total_inr'], 'INR'); ?></div>
                <div class="stat-label"><?php echo format_currency($all_contributions['total_usd']); ?></div>
            </div>

            <div class="stat-card warning">
                <div class="stat-card-header">
                    <h4>Remaining Amount</h4>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo format_currency($remaining_inr, 'INR'); ?></div>
                <div class="stat-label"><?php echo format_currency($remaining_usd); ?></div>
            </div>

            <div class="stat-card danger">
                <div class="stat-card-header">
                    <h4>Collection Progress</h4>
                    <div class="stat-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $progress; ?>%</div>
                <div class="stat-label">of <?php echo format_currency($total_target_inr, 'INR'); ?> total</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Overall Collection Progress</h3>
            </div>
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">Total Progress</span>
                    <span class="progress-label-value"><?php echo $progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Year-wise Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Year-wise Collection Breakdown</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h4>Tasea (66th)</h4>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo format_currency($all_contributions['current_year_inr'], 'INR'); ?></div>
                    <div class="stat-label">Target: <?php echo format_currency(66000 * $total_users, 'INR'); ?></div>
                </div>

                <div class="stat-card success">
                    <div class="stat-card-header">
                        <h4>Ashera (97th)</h4>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo format_currency($all_contributions['next_year_inr'], 'INR'); ?></div>
                    <div class="stat-label">Target: <?php echo format_currency(97000 * $total_users, 'INR'); ?></div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-card-header">
                        <h4>Hadi Ashara (127th)</h4>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo format_currency($all_contributions['final_year_inr'], 'INR'); ?></div>
                    <div class="stat-label">Target: <?php echo format_currency(127000 * $total_users, 'INR'); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="action-buttons">
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
                <a href="add_contribution.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Contribution
                </a>
                <a href="view_users.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i> View All Users
                </a>
                <a href="reports.php" class="btn btn-warning">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            </div>
            <div class="table-container">
                <?php if ($recent_transactions->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>TR Number</th>
                                <th>User</th>
                                <th>ITS Number</th>
                                <th>Amount (INR)</th>
                                <th>Amount (USD)</th>
                                <th>Payment Year</th>
                                <th>Date</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            while ($transaction = $recent_transactions->fetch_assoc()): 
                                // Get payment year label based on payment date
                                $year_label = get_payment_year_from_date($transaction['payment_date']);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['tr_number']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['its_number']); ?></td>
                                    <td><?php echo format_currency($transaction['amount_inr'], 'INR'); ?></td>
                                    <td><?php echo format_currency($transaction['amount_usd']); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo $year_label; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($transaction['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['payment_method']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No transactions found.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($is_amali_admin): ?>
        <!-- AMALI COORDINATOR SECTION -->
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Total Users</h4>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Active Users</div>
            </div>

            <div class="stat-card success">
                <div class="stat-card-header">
                    <h4>Qurans Completed</h4>
                    <div class="stat-icon">
                        <i class="fas fa-quran"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $amali_stats['total_qurans_completed']; ?></div>
                <div class="stat-label"><?php echo $amali_stats['total_juz_completed']; ?> Juz Total</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-card-header">
                    <h4>Duas Recited</h4>
                    <div class="stat-icon">
                        <i class="fas fa-hands-praying"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_stats['dua']; ?></div>
                <div class="stat-label">Duas</div>
            </div>

            <div class="stat-card info">
                <div class="stat-card-header">
                    <h4>Tasbeeh</h4>
                    <div class="stat-icon">
                        <i class="fas fa-dharmachakra"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_stats['tasbeeh']; ?></div>
                <div class="stat-label">Tasbeeh Count</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-card-header">
                    <h4>Namaz</h4>
                    <div class="stat-icon">
                        <i class="fas fa-mosque"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $category_stats['namaz']; ?></div>
                <div class="stat-label">Namaz Count</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-card-header">
                    <h4>Books Completed</h4>
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $books_stats['total_completed']; ?></div>
                <div class="stat-label"><?php echo $books_stats['total_in_progress']; ?> In Progress</div>
            </div>
        </div>

        <!-- Overall Amali Progress -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Overall Amali Janib Progress</h3>
            </div>
            
            <!-- Quran Progress -->
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-quran"></i> Quran Recitation: 
                        <?php echo $amali_stats['total_qurans_completed']; ?> / <?php echo $target_qurans; ?> Qurans
                    </span>
                    <span class="progress-label-value"><?php echo $quran_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $quran_progress; ?>%"></div>
                </div>
                <p style="margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                    <?php echo $amali_stats['total_juz_completed']; ?> / <?php echo $target_juz; ?> Juz completed (<?php echo $juz_progress; ?>%)
                </p>
            </div>

            <!-- Dua Progress -->
            <?php
            $sql_dua_target = "SELECT SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 AND category = 'dua'";
            $dua_target = $conn->query($sql_dua_target)->fetch_assoc()['total_target'] * $total_users;
            $dua_progress_pct = $dua_target > 0 ? round(($category_stats['dua'] / $dua_target) * 100, 2) : 0;
            ?>
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-hands-praying"></i> Dua Recitation: 
                        <?php echo number_format($category_stats['dua']); ?> / <?php echo number_format($dua_target); ?>
                    </span>
                    <span class="progress-label-value"><?php echo $dua_progress_pct; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $dua_progress_pct; ?>%"></div>
                </div>
            </div>

            <!-- Tasbeeh Progress -->
            <?php
            $sql_tasbeeh_target = "SELECT SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 AND category = 'tasbeeh'";
            $tasbeeh_target = $conn->query($sql_tasbeeh_target)->fetch_assoc()['total_target'] * $total_users;
            $tasbeeh_progress_pct = $tasbeeh_target > 0 ? round(($category_stats['tasbeeh'] / $tasbeeh_target) * 100, 2) : 0;
            ?>
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-dharmachakra"></i> Tasbeeh: 
                        <?php echo number_format($category_stats['tasbeeh']); ?> / <?php echo number_format($tasbeeh_target); ?>
                    </span>
                    <span class="progress-label-value"><?php echo $tasbeeh_progress_pct; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $tasbeeh_progress_pct; ?>%; background: linear-gradient(90deg, #f59e0b, #d97706);"></div>
                </div>
            </div>

            <!-- Namaz Progress -->
            <?php
            $sql_namaz_target = "SELECT SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 AND category = 'namaz'";
            $namaz_target = $conn->query($sql_namaz_target)->fetch_assoc()['total_target'] * $total_users;
            $namaz_progress_pct = $namaz_target > 0 ? round(($category_stats['namaz'] / $namaz_target) * 100, 2) : 0;
            ?>
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-mosque"></i> Namaz: 
                        <?php echo number_format($category_stats['namaz']); ?> / <?php echo number_format($namaz_target); ?>
                    </span>
                    <span class="progress-label-value"><?php echo $namaz_progress_pct; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $namaz_progress_pct; ?>%; background: linear-gradient(90deg, #8b5cf6, #7c3aed);"></div>
                </div>
            </div>

            <!-- Books Progress -->
            <?php
            $total_books_activity = $books_stats['total_completed'] + $books_stats['total_in_progress'];
            ?>
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-book"></i> Book Transcription: 
                        <?php echo $books_stats['total_completed']; ?> Completed, 
                        <?php echo $books_stats['total_in_progress']; ?> In Progress
                    </span>
                    <span class="progress-label-value"><?php echo $total_books_activity; ?> Total</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Completed</div>
                        <div class="progress-bar" style="height: 8px;">
                            <div class="progress-fill" style="width: <?php echo $total_books_activity > 0 ? round(($books_stats['total_completed'] / $total_books_activity) * 100, 2) : 0; ?>%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">In Progress</div>
                        <div class="progress-bar" style="height: 8px;">
                            <div class="progress-fill" style="width: <?php echo $total_books_activity > 0 ? round(($books_stats['total_in_progress'] / $total_books_activity) * 100, 2) : 0; ?>%; background: linear-gradient(90deg, var(--secondary-500), var(--secondary-600));"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="action-buttons">
                <a href="amali_reports.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> View Amali Reports
                </a>
                <a href="manage_duas.php" class="btn btn-success">
                    <i class="fas fa-hands-praying"></i> Manage Duas
                </a>
                <a href="manage_books.php" class="btn btn-warning">
                    <i class="fas fa-book"></i> Manage Books
                </a>
                <a href="view_users.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i> View All Users
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- For other admin types, show basic info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Welcome</h3>
            </div>
            <p style="padding: var(--spacing-lg); text-align: center;">
                You don't have access to view dashboard statistics. Please contact the super admin for access.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>