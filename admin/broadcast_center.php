<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Strict Super Admin check
if (!is_super_admin()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Super Admin: Broadcast Center';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Handle New Campaign Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $event_name = clean_input($_POST['event_name']);
    $subject = clean_input($_POST['subject']);
    $custom_message = $_POST['custom_message']; // Keep HTML formatting if any

    if (empty($event_name) || empty($subject)) {
        $error = 'Please provide Event Name and Subject.';
    } else {
        // Set all other active campaigns to completed to focus on the new one
        $conn->query("UPDATE mail_campaigns SET status = 'completed' WHERE status = 'active'");
        
        $sql = "INSERT INTO mail_campaigns (event_name, subject, custom_message, status) VALUES (?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $event_name, $subject, $custom_message);
        if ($stmt->execute()) {
            $success = "New campaign '$event_name' created and set as Active.";
        } else {
            $error = "Failed to create campaign.";
        }
    }
}

// Handle Archiving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_campaign'])) {
    $conn->query("UPDATE mail_campaigns SET status = 'completed' WHERE status = 'active'");
    $success = "Active campaign archived.";
}

// Get active campaign
$active_campaign = $conn->query("SELECT * FROM mail_campaigns WHERE status = 'active' LIMIT 1")->fetch_assoc();

// Get campaign stats if active
$total_users = 0;
$sent_users = 0;
if ($active_campaign) {
    $total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'")->fetch_assoc()['total'];
    $sent_users = $conn->query("SELECT COUNT(*) as sent FROM mail_sent_logs WHERE campaign_id = " . $active_campaign['id'])->fetch_assoc()['sent'];
    $remaining_users = $total_users - $sent_users;
    $progress_pct = calculate_percentage($sent_users, $total_users);
}

// Get sent in last 24 hours (Limit 100 per 24h)
$sent_today = $conn->query("SELECT COUNT(*) as today FROM mail_sent_logs WHERE sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['today'];
$remaining_today = max(0, 100 - $sent_today);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Broadcast Center</h1>
        <p>Send personalized reminders and custom updates to all Mumineen.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="stat-card <?php echo $remaining_today > 0 ? 'success' : 'danger'; ?>">
            <div class="stat-card-header">
                <h4>Today's Capacity</h4>
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
            </div>
            <div class="stat-value"><?php echo $remaining_today; ?> / 100</div>
            <div class="stat-label">Mails left for today (24h)</div>
        </div>
    </div>

    <?php if ($active_campaign): ?>
        <!-- ACTIVE CAMPAIGN DASHBOARD -->
        <div class="card" style="border-left: 5px solid var(--primary-500);">
            <div class="card-header">
                <h3><i class="fas fa-rocket"></i> Active Campaign: <?php echo htmlspecialchars($active_campaign['event_name']); ?></h3>
                <span class="badge badge-primary">ACTIVE</span>
            </div>
            <div style="padding: var(--spacing-lg);">
                <div class="progress-container">
                    <div class="progress-label">
                        <span class="progress-label-text">Overall Campaign Progress: <?php echo $sent_users; ?> / <?php echo $total_users; ?> Sent</span>
                        <span class="progress-label-value"><?php echo $progress_pct; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%"></div>
                    </div>
                </div>

                <div class="stat-box" style="background: var(--bg-tertiary); padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($active_campaign['subject']); ?></p>
                    <p><strong>Custom Note:</strong> <?php echo nl2br(htmlspecialchars($active_campaign['custom_message'])); ?></p>
                </div>

                <div class="action-buttons" style="flex-wrap: wrap; align-items: flex-end;">
                    <?php if ($sent_users < $total_users): ?>
                        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                            <label for="manual_batch_size">Batch Size</label>
                            <input type="number" id="manual_batch_size" class="form-control" value="<?php echo min($remaining_today, 25); ?>" min="1" max="<?php echo $remaining_today; ?>">
                        </div>
                        <button id="sendBatchBtn" class="btn btn-warning btn-lg" <?php echo $remaining_today == 0 ? 'disabled' : ''; ?> onclick="startBatchSend(<?php echo $active_campaign['id']; ?>)">
                            <i class="fas fa-paper-plane"></i> Send Batch
                        </button>
                    <?php else: ?>
                        <div class="alert alert-success" style="width: 100%;">
                            <i class="fas fa-check-double"></i> All users have been reached for this campaign!
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" onsubmit="return confirm('Archive this campaign and start fresh?')">
                        <button type="submit" name="archive_campaign" class="btn btn-secondary">Archive Campaign</button>
                    </form>
                </div>

                <div id="batchProgress" style="display:none; margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin:0;"><i class="fas fa-sync fa-spin"></i> Batch Activity Log</h4>
                        <span class="badge badge-info" id="batchCounter">0 / 0 Processed</span>
                    </div>
                    
                    <div class="table-container" style="max-height: 400px; overflow-y: auto; background: #f8fafc;">
                        <table class="table" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>TR Number</th>
                                    <th>Jamea</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="batchLogBody">
                                <!-- Logs will appear here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CREATE NEW CAMPAIGN -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Create New Broadcast Event</h3>
        </div>
        <form method="POST" action="" style="padding: var(--spacing-lg);">
            <div class="form-group">
                <label for="event_name">Event Name (Internal Reference)</label>
                <input type="text" id="event_name" name="event_name" class="form-control" placeholder="e.g., Ramadan Reminder 1449" required>
            </div>
            <div class="form-group">
                <label for="subject">Email Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g., Your Spiritual Progress Update" required>
            </div>
            <div class="form-group">
                <label for="custom_message">Personalized Header Note</label>
                <textarea id="custom_message" name="custom_message" class="form-control" rows="4" placeholder="This note will appear at the top of the email before their stats..."></textarea>
            </div>
            <button type="submit" name="create_campaign" class="btn btn-primary">
                <i class="fas fa-save"></i> Create and Activate Campaign
            </button>
        </form>
    </div>

    <!-- CAMPAIGN HISTORY -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Campaign History</h3>
        </div>
        <div class="table-container">
            <table class="responsive-table-stack">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event Name</th>
                        <th>Subject</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $conn->query("SELECT * FROM mail_campaigns ORDER BY created_at DESC LIMIT 10");
                    while ($h = $history->fetch_assoc()):
                    ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></td>
                            <td data-label="Event"><?php echo htmlspecialchars($h['event_name']); ?></td>
                            <td data-label="Subject"><?php echo htmlspecialchars($h['subject']); ?></td>
                            <td data-label="Status">
                                <span class="badge <?php echo $h['status'] === 'active' ? 'badge-primary' : 'badge-secondary'; ?>">
                                    <?php echo strtoupper($h['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function startBatchSend(campaignId) {
    const batchSizeInput = document.getElementById('manual_batch_size');
    const batchSize = parseInt(batchSizeInput.value);
    
    if (isNaN(batchSize) || batchSize < 1) {
        showToast('Please enter a valid batch size.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to send up to ${batchSize} emails now?`)) return;

    const btn = document.getElementById('sendBatchBtn');
    const progressDiv = document.getElementById('batchProgress');
    const batchCounter = document.getElementById('batchCounter');
    const logBody = document.getElementById('batchLogBody');

    btn.disabled = true;
    batchSizeInput.disabled = true;
    progressDiv.style.display = 'block';
    logBody.innerHTML = ''; // Clear previous logs
    batchCounter.innerText = `0 / ${batchSize} Processed`;

    try {
        const response = await fetch('ajax_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `campaign_id=${campaignId}&batch_size=${batchSize}`
        });
        
        // We expect a streamed or multi-line JSON response if we want real-time, 
        // but for now let's handle the final array of results.
        const result = await response.json();
        
        if (result.success) {
            // Populate the log table with results
            result.details.forEach(item => {
                const tr = document.createElement('tr');
                const statusClass = item.status === 'success' ? 'badge-success' : 'badge-danger';
                const statusText = item.status === 'success' ? 'SENT' : 'FAILED';
                const errorInfo = item.error ? `<br><small style="color:red;">${item.error}</small>` : '';

                tr.innerHTML = `
                    <td data-label="TR">${item.tr_number}</td>
                    <td data-label="Jamea">${item.category}</td>
                    <td data-label="Name"><strong>${item.name}</strong></td>
                    <td data-label="Email">${item.email}</td>
                    <td data-label="Status">
                        <span class="badge ${statusClass}">${statusText}</span>
                        ${errorInfo}
                    </td>
                `;
                logBody.appendChild(tr);
            });

            batchCounter.innerText = `${result.sent + result.failed} / ${batchSize} Processed`;
            showToast(result.message, result.failed > 0 ? 'warning' : 'success');
            
            // Allow user to see logs before reload
            const reloadBtn = document.createElement('button');
            reloadBtn.className = "btn btn-primary mt-3";
            reloadBtn.innerHTML = "<i class='fas fa-sync'></i> Refresh Dashboard";
            reloadBtn.onclick = () => location.reload();
            progressDiv.appendChild(reloadBtn);

        } else {
            showToast(result.message, 'error');
            btn.disabled = false;
            batchSizeInput.disabled = false;
        }
    } catch (e) {
        showToast('Batch processing failed or timed out.', 'error');
        console.error(e);
        btn.disabled = false;
        batchSizeInput.disabled = false;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
