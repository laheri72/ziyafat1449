<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Reports';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Get system settings
$settings = get_system_settings($conn);

// Get total members count (users + admins)
$sql = "SELECT COUNT(*) as total FROM users WHERE role IN ('user', 'admin')";
$result = $conn->query($sql);
$total_users = $result->fetch_assoc()['total'];

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

// Get member-wise contribution report
$sql = "SELECT 
            u.id,
            u.its_number,
            u.tr_number,
            u.name,
            u.role,
            COALESCE(SUM(c.amount_usd), 0) as total_usd,
            COALESCE(SUM(c.amount_inr), 0) as total_inr,
            COUNT(c.id) as transaction_count
        FROM users u
        LEFT JOIN contributions c ON u.id = c.user_id
        WHERE u.role IN ('user', 'admin')
        GROUP BY u.id
        ORDER BY u.tr_number ASC";

$user_report = $conn->query($sql);

// Get members with pending payments
$sql = "SELECT 
            u.id,
            u.its_number,
            u.tr_number,
            u.name,
            u.email,
            u.role,
            COALESCE(SUM(c.amount_usd), 0) as paid_usd,
            (? - COALESCE(SUM(c.amount_usd), 0)) as pending_usd
        FROM users u
        LEFT JOIN contributions c ON u.id = c.user_id
        WHERE u.role IN ('user', 'admin')
        GROUP BY u.id
        HAVING pending_usd > 0
        ORDER BY u.tr_number ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("d", $settings['target_amount_usd']);
$stmt->execute();
$pending_report = $stmt->get_result();

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-chart-bar"></i> Reports</h1>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Members</h4>
            <div class="stat-value"><?php echo $total_users; ?></div>
            <div class="stat-label">registered users + admins</div>
        </div>

        <div class="stat-card success">
            <h4>Total Collected</h4>
            <div class="stat-value"><?php echo format_currency($all_contributions['total_inr'], 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($all_contributions['total_usd']); ?></div>
        </div>

        <div class="stat-card warning">
            <h4>Remaining Amount</h4>
            <div class="stat-value"><?php echo format_currency($remaining_inr, 'INR'); ?></div>
            <div class="stat-label"><?php echo format_currency($remaining_usd); ?></div>
        </div>

        <div class="stat-card danger">
            <h4>Collection Progress</h4>
            <div class="stat-value"><?php echo $progress; ?>%</div>
            <div class="stat-label">of <?php echo format_currency($total_target_usd); ?> total</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Overall Collection Progress</h3>
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
            <h3><i class="fas fa-calendar-alt"></i> Year-wise Collection Breakdown (All Members)</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Tasea (66th)</h4>
                <div class="stat-value"><?php echo format_currency($all_contributions['current_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: <?php echo format_currency(66000 * $total_users, 'INR'); ?></div>
            </div>

            <div class="stat-card success">
                <h4>Ashera (97th)</h4>
                <div class="stat-value"><?php echo format_currency($all_contributions['next_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: <?php echo format_currency(97000 * $total_users, 'INR'); ?></div>
            </div>

            <div class="stat-card warning">
                <h4>Hadi Ashara (127th)</h4>
                <div class="stat-value"><?php echo format_currency($all_contributions['final_year_inr'], 'INR'); ?></div>
                <div class="stat-label">Target: <?php echo format_currency(127000 * $total_users, 'INR'); ?></div>
            </div>
        </div>
    </div>

    <!-- Year-wise Breakdown Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Year-wise Collection Chart</h3>
        </div>
        <canvas id="yearChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Member-wise Contribution Report -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h3><i class="fas fa-users"></i> Member-wise Contribution Report</h3>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <select id="filterAmount" class="form-control" style="width: 200px;">
                        <option value="all">All Members</option>
                        <option value="zero">No Payment (₹0)</option>
                        <option value="partial">Partial Payment</option>
                        <option value="complete">Complete Payment</option>
                    </select>
                    <input type="text" id="searchUser" class="form-control" placeholder="Search by name/ITS/TR..." style="width: 250px;">
                    <button onclick="exportTableToCSV('userReportTable', 'user_contributions.csv')" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
        <div class="table-container">
            <?php if ($user_report->num_rows > 0): ?>
                <table id="userReportTable">
                    <thead>
                        <tr>
                            <th>TR Number</th>
                            <th>ITS Number</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Total Contributed (INR)</th>
                            <th>Total Contributed (USD)</th>
                            <th>Remaining (INR)</th>
                            <th>Progress</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $user_report->fetch_assoc()): 
                            $user_remaining = $settings['target_amount_usd'] - $user['total_usd'];
                            $user_progress = calculate_percentage($user['total_usd'], $settings['target_amount_usd']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['tr_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-danger' : 'badge-primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo format_currency($user['total_inr'], 'INR'); ?></td>
                                <td><?php echo format_currency($user['total_usd']); ?></td>
                                <td><?php echo format_currency($settings['target_amount_inr'] - $user['total_inr'], 'INR'); ?></td>
                                <td>
                                    <div class="progress-bar" style="height: 20px; width: 100px;">
                                        <div class="progress-fill" style="width: <?php echo $user_progress; ?>%; font-size: 0.7rem;">
                                            <?php echo $user_progress; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $user['transaction_count']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No member data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Payments Report -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Pending Payments</h3>
        </div>
        <div class="table-container">
            <?php if ($pending_report->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>TR Number</th>
                            <th>ITS Number</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Paid (INR)</th>
                            <th>Pending (INR)</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $pending_report->fetch_assoc()): 
                            $user_progress = calculate_percentage($user['paid_usd'], $settings['target_amount_usd']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['tr_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-danger' : 'badge-primary'; ?>" style="margin-left: 0.35rem;">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo format_currency($user['paid_usd'] * 84.67, 'INR'); ?></td>
                                <td class="text-danger"><strong><?php echo format_currency($user['pending_usd'] * 84.67, 'INR'); ?></strong></td>
                                <td>
                                    <div class="progress-bar" style="height: 20px; width: 100px;">
                                        <div class="progress-fill" style="width: <?php echo $user_progress; ?>%; font-size: 0.7rem;">
                                            <?php echo $user_progress; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-success"><i class="fas fa-check-circle"></i> All members have completed their payments!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Filter and search functionality
document.getElementById('filterAmount').addEventListener('change', filterTable);
document.getElementById('searchUser').addEventListener('input', filterTable);

function filterTable() {
    const filterValue = document.getElementById('filterAmount').value;
    const searchValue = document.getElementById('searchUser').value.toLowerCase();
    const table = document.getElementById('userReportTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        
        // Get values
        const trNumber = cells[0].textContent.toLowerCase();
        const itsNumber = cells[1].textContent.toLowerCase();
        const name = cells[2].textContent.toLowerCase();
        const totalINR = parseFloat(cells[4].textContent.replace(/[₹,]/g, ''));
        const progressText = cells[7].textContent.trim();
        const progress = parseInt(progressText.replace('%', ''));
        
        // Search filter
        const matchesSearch = searchValue === '' || 
                            trNumber.includes(searchValue) || 
                            itsNumber.includes(searchValue) || 
                            name.includes(searchValue);
        
        // Amount filter
        let matchesAmount = true;
        if (filterValue === 'zero') {
            matchesAmount = totalINR === 0;
        } else if (filterValue === 'partial') {
            matchesAmount = totalINR > 0 && progress < 100;
        } else if (filterValue === 'complete') {
            matchesAmount = progress >= 100;
        }
        
        // Show/hide row
        if (matchesSearch && matchesAmount) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Year-wise breakdown chart
const ctx = document.getElementById('yearChart').getContext('2d');
const yearChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Tasea (66th)', 'Ashera (97th)', 'Hadi Ashara (127th)'],
        datasets: [{
            label: 'Amount Collected (INR)',
            data: [
                <?php echo $all_contributions['current_year_inr']; ?>,
                <?php echo $all_contributions['next_year_inr']; ?>,
                <?php echo $all_contributions['final_year_inr']; ?>
            ],
            backgroundColor: [
                'rgba(37, 99, 235, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)'
            ],
            borderColor: [
                'rgba(37, 99, 235, 1)',
                'rgba(16, 185, 129, 1)',
                'rgba(245, 158, 11, 1)'
            ],
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Collected: ₹' + context.parsed.y.toLocaleString('en-IN', {maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value.toLocaleString('en-IN', {maximumFractionDigits: 2});
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>