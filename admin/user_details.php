<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'User Details';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    header('Location: view_users.php');
    exit();
}

// Get user data
$user = get_user_by_id($conn, $user_id);

if (!$user) {
    header('Location: view_users.php');
    exit();
}

// Get system settings
$settings = get_system_settings($conn);

// Get user contributions
$contributions = get_user_contributions($conn, $user_id);

// Calculate remaining amounts
$remaining_usd = $settings['target_amount_usd'] - $contributions['total_usd'];
$remaining_inr = $settings['target_amount_inr'] - $contributions['total_inr'];

// Calculate progress percentage
$progress = calculate_percentage($contributions['total_usd'], $settings['target_amount_usd']);

// Get transaction history
$sql = "SELECT * FROM contributions WHERE user_id = ? ORDER BY payment_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user"></i> User Details</p>
    </div>

    <!-- User Info Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle"></i> User Information</h3>
            <div style="margin-left: auto;">
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-edit"></i> Edit User Details
                </a>
            </div>
        </div>
        <div class="user-info-grid">
            <div class="user-info-item">
                <label><i class="fas fa-id-card"></i> ITS Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['its_number']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-id-badge"></i> TR Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['tr_number']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-mosque"></i> Jamea</label>
                <div class="user-info-value">
                    <?php if ($user['category']): ?>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($user['category']); ?></span>
                    <?php else: ?>
                        <span style="color: #999;">Not specified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-user"></i> Full Name</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-envelope"></i> Email</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['phone_number'] ?: 'Not provided'); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-shield-alt"></i> Role</label>
                <div class="user-info-value">
                    <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                </div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-calendar-plus"></i> Joined Date</label>
                <div class="user-info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Target</h4>
            <div class="stat-value"><?php echo format_currency($settings['target_amount_inr'], 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($settings['target_amount_usd']); ?></div>
        </div>

        <div class="stat-card success">
            <h4>Amount Paid</h4>
            <div class="stat-value"><?php echo format_currency($contributions['total_inr'], 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($contributions['total_usd']); ?></div>
        </div>

        <div class="stat-card warning">
            <h4>Amount Remaining</h4>
            <div class="stat-value"><?php echo format_currency($remaining_inr, 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($remaining_usd); ?></div>
        </div>

        <div class="stat-card danger">
            <h4>Progress</h4>
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
                <span>Total Progress</span>
                <span><?php echo $progress; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%">
                    <?php echo $progress; ?>%
                </div>
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
                <h4>Tasea (66th)</h4>
                <div class="stat-value"><?php echo format_currency($contributions['current_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹66,000</div>
            </div>

            <div class="stat-card success">
                <h4>Ashera (97th)</h4>
                <div class="stat-value"><?php echo format_currency($contributions['next_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹97,000</div>
            </div>

            <div class="stat-card warning">
                <h4>Hadi Ashara (127th)</h4>
                <div class="stat-value"><?php echo format_currency($contributions['final_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: ₹1,27,000</div>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-history"></i> Transaction History</h3>
                <a href="add_contribution.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Contribution
                </a>
            </div>
        </div>
        <div class="table-container">
            <?php if ($transactions->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount (INR)</th>
                            <th>Amount (USD)</th>
                            <th>Payment Year</th>
                            <th>Payment Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        while ($transaction = $transactions->fetch_assoc()): 
                            // Get payment year label based on payment date
                            $year_label = get_payment_year_from_date($transaction['payment_date']);
                        ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($transaction['payment_date'])); ?></td>
                                <td><?php echo format_currency($transaction['amount_inr'], 'INR'); ?></td>
                                <td><?php echo format_currency($transaction['amount_usd']); ?></td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo $year_label; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['transaction_reference']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['notes']); ?></td>
                                <td>
                                    <a href="edit_contribution.php?id=<?php echo $transaction['id']; ?>&user_id=<?php echo $user_id; ?>" 
                                       class="btn btn-primary btn-sm"
                                       title="Edit Contribution">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_contribution.php?id=<?php echo $transaction['id']; ?>&user_id=<?php echo $user_id; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this contribution?')"
                                       title="Delete Contribution">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No transactions found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="action-buttons">
        <a href="view_users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>