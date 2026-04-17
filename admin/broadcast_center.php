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
$default_subject = 'Your Amali Hadaya Report';

function get_campaign_goal_settings($campaign) {
    $defaults = ['dua' => 100, 'tasbeeh' => 100, 'namaz' => 100];
    if (!$campaign || empty($campaign['custom_message'])) {
        return $defaults;
    }

    $decoded = json_decode($campaign['custom_message'], true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    foreach ($defaults as $key => $value) {
        if (isset($decoded[$key])) {
            $defaults[$key] = max(1, min(200, intval($decoded[$key])));
        }
    }

    return $defaults;
}

// Handle New Campaign Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $event_name = clean_input($_POST['event_name']);
    $goal_dua = max(1, min(200, intval($_POST['goal_dua'] ?? 100)));
    $goal_tasbeeh = max(1, min(200, intval($_POST['goal_tasbeeh'] ?? 100)));
    $goal_namaz = max(1, min(200, intval($_POST['goal_namaz'] ?? 100)));
    $goal_payload = json_encode([
        'dua' => $goal_dua,
        'tasbeeh' => $goal_tasbeeh,
        'namaz' => $goal_namaz
    ]);

    if (empty($event_name)) {
        $error = 'Please provide Event Name.';
    } else {
        $existing_active = $conn->query("SELECT id, event_name FROM mail_campaigns WHERE status = 'active' LIMIT 1")->fetch_assoc();

        if ($existing_active) {
            $error = "An active campaign already exists ('" . htmlspecialchars($existing_active['event_name']) . "'). Please archive it before creating a new event.";
        } else {
            $sql = "INSERT INTO mail_campaigns (event_name, subject, custom_message, status) VALUES (?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $event_name, $default_subject, $goal_payload);
            if ($stmt->execute()) {
                $success = "New campaign '$event_name' created with goal settings and set as Active.";
            } else {
                $error = "Failed to create campaign.";
            }
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
$campaign_goals = get_campaign_goal_settings($active_campaign);

// Get campaign stats if active
$total_users = 0;
$sent_users = 0;
if ($active_campaign) {
    $total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE (role = 'user' OR role = 'admin') AND its_number NOT LIKE '000000%'")->fetch_assoc()['total'];
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
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($default_subject); ?></p>
                    <p><strong>Goal Settings for This Campaign:</strong></p>
                    <p style="margin: 0.25rem 0;">Dua: <strong><?php echo $campaign_goals['dua']; ?>%</strong> | Tasbeeh: <strong><?php echo $campaign_goals['tasbeeh']; ?>%</strong> | Namaz: <strong><?php echo $campaign_goals['namaz']; ?>%</strong></p>
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

                    <button type="button" class="btn btn-info" onclick="sendTestMail(<?php echo $active_campaign['id']; ?>)">
                        <i class="fas fa-vial"></i> Test (25687)
                    </button>
                    
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
        <?php if ($active_campaign): ?>
            <div class="alert alert-warning" style="margin: 0 var(--spacing-lg) var(--spacing-md) var(--spacing-lg);">
                <i class="fas fa-lock"></i>
                New campaign creation is locked while an event is active.
                Please archive <strong><?php echo htmlspecialchars($active_campaign['event_name']); ?></strong> first.
            </div>
        <?php endif; ?>
        <form method="POST" action="" style="padding: var(--spacing-lg);">
            <div class="form-group">
                <label for="event_name">Event Name (Internal Reference)</label>
                <input type="text" id="event_name" name="event_name" class="form-control" placeholder="e.g., Ramadan Reminder 1449" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
            </div>
            <div class="form-group">
                <label>Default Email Subject</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($default_subject); ?>" readonly>
            </div>
            <div class="form-group">
                <label><i class="fas fa-bullseye"></i> Goal Setting (%)</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div>
                        <label for="goal_dua" style="font-size: 0.85rem;">Dua Goal %</label>
                        <input type="number" id="goal_dua" name="goal_dua" class="form-control" min="1" max="200" value="100" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
                    </div>
                    <div>
                        <label for="goal_tasbeeh" style="font-size: 0.85rem;">Tasbeeh Goal %</label>
                        <input type="number" id="goal_tasbeeh" name="goal_tasbeeh" class="form-control" min="1" max="200" value="100" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
                    </div>
                    <div>
                        <label for="goal_namaz" style="font-size: 0.85rem;">Namaz Goal %</label>
                        <input type="number" id="goal_namaz" name="goal_namaz" class="form-control" min="1" max="200" value="100" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
                    </div>
                </div>
                <small class="form-text text-muted">These goals define expected completion percentage versus each user's base target.</small>
            </div>
            <button type="submit" name="create_campaign" class="btn btn-primary" <?php echo $active_campaign ? 'disabled title="Archive current campaign first"' : ''; ?>>
                <i class="fas fa-save"></i> Create and Activate Campaign
            </button>
        </form>
    </div>

    <!-- TODAY'S LOGS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-check"></i> Today's Delivery Logs</h3>
        </div>
        <div class="table-container">
            <?php
            $today_logs_sql = "SELECT l.*, u.name, u.its_number, u.category, c.event_name 
                              FROM mail_sent_logs l
                              JOIN users u ON l.user_id = u.id
                              JOIN mail_campaigns c ON l.campaign_id = c.id
                              WHERE DATE(l.sent_at) = CURDATE()
                              ORDER BY l.sent_at DESC";
            $today_logs = $conn->query($today_logs_sql);
            ?>
            <table class="responsive-table-stack">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Campaign</th>
                        <th>User</th>
                        <th>ITS</th>
                        <th>Jamea</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($today_logs->num_rows > 0): ?>
                        <?php while ($log = $today_logs->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Time"><?php echo date('H:i', strtotime($log['sent_at'])); ?></td>
                                <td data-label="Campaign"><?php echo htmlspecialchars($log['event_name']); ?></td>
                                <td data-label="User"><strong><?php echo htmlspecialchars($log['name']); ?></strong></td>
                                <td data-label="ITS"><?php echo htmlspecialchars($log['its_number']); ?></td>
                                <td data-label="Jamea"><?php echo htmlspecialchars($log['category']); ?></td>
                                <td data-label="Status">
                                    <span class="badge <?php echo $log['status'] === 'success' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo strtoupper($log['status']); ?>
                                    </span>
                                    <?php if ($log['error_message']): ?>
                                        <i class="fas fa-info-circle text-danger" title="<?php echo htmlspecialchars($log['error_message']); ?>"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                                <i class="fas fa-envelope-open" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                                No emails sent in the last 24 hours.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
    const totalToProcess = parseInt(batchSizeInput.value);
    
    if (isNaN(totalToProcess) || totalToProcess < 1) {
        showToast('Please enter a valid batch size.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to send up to ${totalToProcess} emails now?`)) return;

    const btn = document.getElementById('sendBatchBtn');
    const progressDiv = document.getElementById('batchProgress');
    const batchCounter = document.getElementById('batchCounter');
    const logBody = document.getElementById('batchLogBody');

    btn.disabled = true;
    batchSizeInput.disabled = true;
    progressDiv.style.display = 'block';
    logBody.innerHTML = ''; 
    batchCounter.innerText = `0 / ${totalToProcess} Processed`;

    let processed = 0;
    let successful = 0;
    let failed = 0;

    for (let i = 0; i < totalToProcess; i++) {
        try {
            const response = await fetch('ajax_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `campaign_id=${campaignId}&batch_size=1`
            });
            
            const result = await response.json();
            
            if (result.success && result.details && result.details.length > 0) {
                const item = result.details[0];
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
                logBody.insertBefore(tr, logBody.firstChild); // Show newest at top

                processed++;
                if (item.status === 'success') successful++; else failed++;
                batchCounter.innerText = `${processed} / ${totalToProcess} Processed`;
            } else {
                // No more users to process or campaign completed
                if (processed === 0) showToast(result.message || 'No more users to process.', 'info');
                break;
            }
        } catch (e) {
            console.error('Email processing error:', e);
            failed++;
            processed++;
            batchCounter.innerText = `${processed} / ${totalToProcess} Processed`;
        }
    }

    showToast(`Batch completed: ${successful} sent, ${failed} failed.`, failed > 0 ? 'warning' : 'success');
    
    // Final UI updates
    const reloadBtn = document.createElement('button');
    reloadBtn.className = "btn btn-primary mt-3";
    reloadBtn.innerHTML = "<i class='fas fa-sync'></i> Refresh Dashboard";
    reloadBtn.onclick = () => location.reload();
    progressDiv.appendChild(reloadBtn);
}

async function sendTestMail(campaignId) {
    if (!confirm('Send one test mail to user ID 650 now?')) return;

    try {
        const response = await fetch('ajax_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `campaign_id=${campaignId}&batch_size=1&test_user_id=650`
        });

        const result = await response.json();
        if (!result.success) {
            showToast(result.message || 'Test send failed.', 'error');
            return;
        }

        const detail = (result.details && result.details.length) ? result.details[0] : null;
        if (detail && detail.status === 'success') {
            showToast(`Test mail sent to ${detail.email}`, 'success');
        } else if (detail) {
            showToast(`Test mail failed: ${detail.error || 'Unknown error'}`, 'error');
        } else {
            showToast(result.message || 'Test send completed.', 'info');
        }
    } catch (error) {
        showToast('Could not connect to server for test send.', 'error');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
