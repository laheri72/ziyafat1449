<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'My Finance Report';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Get user data
$user = get_user_by_id($conn, $_SESSION['user_id']);

// Check if user is from Surat category
if ($user['category'] !== 'Surat') {
    header('Location: index.php');
    exit();
}

// Get system settings
$settings = get_system_settings($conn);

// Get user contributions
$contributions = get_user_contributions($conn, $_SESSION['user_id']);

// Calculate remaining amounts
$remaining_usd = $settings['target_amount_usd'] - $contributions['total_usd'];
$remaining_inr = $settings['target_amount_inr'] - $contributions['total_inr'];

// Calculate progress percentage
$progress = calculate_percentage($contributions['total_usd'], $settings['target_amount_usd']);

// Get transaction history
$sql = "SELECT * FROM contributions WHERE user_id = ? ORDER BY payment_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->get_result();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-mosque"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        <p>Financial report for Surat Jamea - Track your contributions and progress</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <h4>Total Target</h4>
                <div class="stat-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo format_currency($settings['target_amount_inr'], 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($settings['target_amount_usd']); ?></div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <h4>Amount Paid</h4>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo format_currency($contributions['total_inr'], 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($contributions['total_usd']); ?></div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <h4>Amount Remaining</h4>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo format_currency($remaining_inr, 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($remaining_usd); ?></div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <h4>Progress</h4>
                <div class="stat-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $progress; ?>%</div>
            <div class="stat-label">of total target</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Contribution Progress</h3>
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
            <h3><i class="fas fa-calendar-alt"></i> Year-wise Contribution Breakdown</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Tasea (66th)</h4>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo format_currency($contributions['current_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹66,000</div>
            </div>

            <div class="stat-card success">
                <div class="stat-card-header">
                    <h4>Ashera (97th)</h4>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo format_currency($contributions['next_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹97,000</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-card-header">
                    <h4>Hadi Ashara (127th)</h4>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo format_currency($contributions['final_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹1,27,000</div>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Transaction History</h3>
        </div>
        <div class="table-container">
            <?php if ($transactions->num_rows > 0): ?>
                <table class="responsive-table-stack">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>INR Amount</th>
                            <th>USD Amount</th>
                            <th>Target Year</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        while ($transaction = $transactions->fetch_assoc()): 
                            $year_label = get_payment_year_from_date($transaction['payment_date']);
                        ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M d, Y', strtotime($transaction['payment_date'])); ?></td>
                                <td data-label="INR"><?php echo format_currency($transaction['amount_inr'], 'INR'); ?></td>
                                <td data-label="USD"><?php echo format_currency($transaction['amount_usd']); ?></td>
                                <td data-label="Target">
                                    <span class="badge badge-primary">
                                        <?php echo $year_label; ?>
                                    </span>
                                </td>
                                <td data-label="Method"><?php echo htmlspecialchars($transaction['payment_method']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
<?php else: ?>
                <p class="text-center">No transactions found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>