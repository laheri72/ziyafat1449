<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/supabase_helper.php';

// Access check
if (!can_access_broadcast_center()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Ziyarat Broadcast Center';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Fetch Current Active Event Tag from Supabase app_settings
$current_event_tag = get_supabase_current_event();

// Handle New Campaign Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ziyarat_campaign'])) {
    $event_tag = clean_input($_POST['event_tag']);
    $campaign_type = clean_input($_POST['campaign_type'] ?? 'standard');
    $subject = clean_input($_POST['subject']);
    $target_count = max(1, min(1000, intval($_POST['target_count'] ?? 30)));
    $custom_message = clean_input($_POST['custom_message'] ?? '');

    if (empty($event_tag) || empty($subject)) {
        $error = 'Please enter an Email Subject.';
    } else {
        $existing_active = $conn->query("SELECT id, event_tag FROM ziyarat_mail_campaigns WHERE status = 'active' LIMIT 1")->fetch_assoc();

        if ($existing_active) {
            $error = "An active Ziyarat campaign already exists ('" . htmlspecialchars($existing_active['event_tag']) . "'). Please archive it before creating a new event.";
        } else {
            $stmt = $conn->prepare("INSERT INTO ziyarat_mail_campaigns (event_tag, campaign_type, subject, target_count, custom_message, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssis", $event_tag, $campaign_type, $subject, $target_count, $custom_message);
            if ($stmt->execute()) {
                $success = "New Ziyarat Broadcast Campaign created for active event '$event_tag' and activated!";
            } else {
                $error = "Failed to create campaign.";
            }
        }
    }
}

// Handle Archiving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_ziyarat_campaign'])) {
    $conn->query("UPDATE ziyarat_mail_campaigns SET status = 'completed' WHERE status = 'active'");
    $success = "Active Ziyarat campaign archived.";
}

// Get active campaign
$active_campaign = $conn->query("SELECT * FROM ziyarat_mail_campaigns WHERE status = 'active' LIMIT 1")->fetch_assoc();

// Get eligible user counts for active campaign
$total_students = 0;
$sent_students = 0;
$remaining_students = 0;
$progress_pct = 0;

if ($active_campaign) {
    // Count eligible students matching between Supabase and MySQL
    $supa_students = get_supabase_students();
    $supa_trs = array_map(function($s) { return "'" . $s['tr_number'] . "'"; }, $supa_students);
    
    if (!empty($supa_trs)) {
        $in_clause = implode(',', $supa_trs);
        $total_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE tr_number IN ($in_clause) AND is_subscribed = 1 AND email IS NOT NULL AND email != ''");
        $total_students = $total_res ? $total_res->fetch_assoc()['total'] : 0;
    }

    $sent_res = $conn->query("SELECT COUNT(DISTINCT user_id) as sent FROM ziyarat_mail_sent_logs WHERE campaign_id = " . $active_campaign['id'] . " AND status = 'success'");
    $sent_students = $sent_res ? $sent_res->fetch_assoc()['sent'] : 0;
    $remaining_students = max(0, $total_students - $sent_students);
    $progress_pct = calculate_percentage($sent_students, $total_students);
}

// Get sent in last 24 hours (Limit 100 per 24h across system)
$sent_today_z = $conn->query("SELECT COUNT(*) as today FROM ziyarat_mail_sent_logs WHERE sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['today'];
$sent_today_amali = $conn->query("SELECT COUNT(*) as today FROM mail_sent_logs WHERE sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['today'];
$total_sent_24h = $sent_today_z + $sent_today_amali;
$remaining_today = max(0, 100 - $total_sent_24h);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <h1><i class="fas fa-kaaba"></i> Ziyarat Raudat Tahera Broadcast Center</h1>
            <p>Send targeted reminders and Mumbai availability alerts to Talabat.</p>
        </div>
        <div style="margin-top: 10px;">
            <a href="broadcast_center.php" class="btn btn-secondary"><i class="fas fa-bullhorn"></i> Amali Broadcast Center</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 20px;">
        <div class="stat-card <?php echo $remaining_today > 0 ? 'success' : 'danger'; ?>">
            <div class="stat-card-header">
                <h4>Today's SMTP Capacity</h4>
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
            </div>
            <div class="stat-value"><?php echo $remaining_today; ?> / 100</div>
            <div class="stat-label">Mails remaining (24h limit)</div>
        </div>
        <div class="stat-card info">
            <div class="stat-card-header">
                <h4>Active Ziyarat Event</h4>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div class="stat-value" style="font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($current_event_tag); ?></div>
            <div class="stat-label">Locked from Ziyarat Flow</div>
        </div>
    </div>

    <?php if ($active_campaign): ?>
        <!-- ACTIVE CAMPAIGN DASHBOARD -->
        <div class="card" style="border-left: 5px solid #2563eb;">
            <div class="card-header">
                <h3><i class="fas fa-rocket"></i> Active Campaign: <?php echo htmlspecialchars($active_campaign['event_tag']); ?></h3>
                <div>
                    <?php
                    $type_labels = [
                        'standard' => ['badge' => 'badge-primary', 'label' => 'Standard Progress'],
                        'mumbai_prompt' => ['badge' => 'badge-warning', 'label' => 'Mumbai Availability Check'],
                        'mumbai_alert' => ['badge' => 'badge-danger', 'label' => 'Mumbai Urgent Alert']
                    ];
                    $t_info = $type_labels[$active_campaign['campaign_type'] ?? 'standard'] ?? $type_labels['standard'];
                    ?>
                    <span class="badge <?php echo $t_info['badge']; ?>"><?php echo $t_info['label']; ?></span>
                    <span class="badge badge-primary">ACTIVE</span>
                </div>
            </div>
            <div style="padding: var(--spacing-lg);">
                <div class="progress-container">
                    <div class="progress-label">
                        <span class="progress-label-text">Overall Student Reach Progress: <?php echo $sent_students; ?> / <?php echo $total_students; ?> Reached</span>
                        <span class="progress-label-value"><?php echo $progress_pct; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%; background: #2563eb;"></div>
                    </div>
                </div>

                <div class="stat-box" style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0;">
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($active_campaign['subject']); ?></p>
                    <p><strong>Campaign Type:</strong> <strong><?php echo $t_info['label']; ?></strong></p>
                    <p><strong>Target Mumineen Count per Student:</strong> <strong><?php echo intval($active_campaign['target_count']); ?> Mumineen</strong></p>
                    <?php if (!empty($active_campaign['custom_message'])): ?>
                        <p><strong>Admin Custom Note:</strong> <?php echo htmlspecialchars($active_campaign['custom_message']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="action-buttons" style="flex-wrap: wrap; align-items: flex-end; gap: 15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php if ($sent_students < $total_students): ?>
                        <div class="form-group" style="margin-bottom: 0; min-width: 170px;">
                            <label for="branch_filter"><i class="fas fa-building" style="color: #2563eb;"></i> Student Branch</label>
                            <select id="branch_filter" class="form-control" onchange="loadAudienceStats(<?php echo $active_campaign['id']; ?>)">
                                <option value="all">All Branches (Global)</option>
                                <option value="Marol">Marol</option>
                                <option value="Surat">Surat</option>
                                <option value="Nairobi">Nairobi</option>
                                <option value="Karachi">Karachi</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0; min-width: 250px;">
                            <label for="audience_filter"><i class="fas fa-filter" style="color: #2563eb;"></i> Audience Filter <span id="statsSpinner" style="display:none; font-size: 0.85rem; color: #2563eb;"><i class="fas fa-spinner fa-spin"></i></span></label>
                            <select id="audience_filter" class="form-control" onchange="updateRecommendationAnalysis()">
                                <option value="all">All Eligible Students</option>
                                <option value="mumbai_only" <?php echo ($active_campaign['campaign_type'] === 'mumbai_alert') ? 'selected' : ''; ?>>Available in Mumbai Only</option>
                                <option value="not_mumbai_only" <?php echo ($active_campaign['campaign_type'] === 'mumbai_prompt') ? 'selected' : ''; ?>>NOT in Mumbai / Unset Only</option>
                                <option value="pending_only">Pending Ziyarat Only</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
                            <label for="manual_batch_size"><i class="fas fa-calculator" style="color: #2563eb;"></i> Batch Size</label>
                            <input type="number" id="manual_batch_size" class="form-control" value="<?php echo min($remaining_today, 10); ?>" min="1" max="<?php echo $remaining_today; ?>">
                        </div>

                        <button id="sendBatchBtn" class="btn btn-primary btn-lg" <?php echo $remaining_today == 0 ? 'disabled' : ''; ?> onclick="startZiyaratBatchSend(<?php echo $active_campaign['id']; ?>)">
                            <i class="fas fa-paper-plane"></i> Send Batch
                        </button>
                    <?php else: ?>
                        <div class="alert alert-success" style="width: 100%;">
                            <i class="fas fa-check-double"></i> All eligible students have been reached for this campaign!
                        </div>
                    <?php endif; ?>

                    <button type="button" class="btn btn-warning" onclick="sendTestMail(<?php echo $active_campaign['id']; ?>)">
                        <i class="fas fa-vial"></i> Test Email (TR 25687)
                    </button>

                    <form method="POST" action="" onsubmit="return confirm('Archive this Ziyarat campaign and start fresh?')">
                        <button type="submit" name="archive_ziyarat_campaign" class="btn btn-secondary">Archive Campaign</button>
                    </form>
                </div>

                <!-- SENIOR DEVELOPER LIVE AUDIENCE ANALYSIS & BATCH SIZE RECOMMENDATION PANEL -->
                <div id="liveAnalysisPanel" style="margin-top: 15px; padding: 15px; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <h4 style="margin: 0; font-size: 1rem; color: #1e40af;"><i class="fas fa-chart-pie"></i> Audience & Daily SMTP Limit Analysis</h4>
                        <span class="badge badge-info" id="analysisTargetBadge" style="font-size: 0.85rem; padding: 5px 10px;">Target: Calculating...</span>
                    </div>
                    <p id="analysisText" style="margin: 10px 0 0 0; font-size: 0.95rem; line-height: 1.5; color: #1e3a8a;">
                        Fetching live audience stats from Ziyarat Flow & MySQL...
                    </p>
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
                                    <th>Jamea Branch</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Assignments (Done/Total)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="batchLogBody">
                                <!-- Live logs appear here -->
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
            <h3><i class="fas fa-plus-circle"></i> Create New Ziyarat Broadcast Event</h3>
        </div>
        <?php if ($active_campaign): ?>
            <div class="alert alert-warning" style="margin: 0 var(--spacing-lg) var(--spacing-md) var(--spacing-lg);">
                <i class="fas fa-lock"></i>
                New Ziyarat campaign creation is locked while an event is active.
                Please archive <strong><?php echo htmlspecialchars($active_campaign['event_tag']); ?></strong> first.
            </div>
        <?php endif; ?>
        <form method="POST" action="" style="padding: var(--spacing-lg);">
            <div class="form-group">
                <label for="event_tag_display"><i class="fas fa-lock" style="color: #2563eb;"></i> Current Event Tag (Locked from Ziyarat Flow)</label>
                <input type="text" id="event_tag_display" class="form-control" value="<?php echo htmlspecialchars($current_event_tag); ?>" readonly style="background-color: #f1f5f9; font-weight: bold; color: #1e293b; border-left: 4px solid #2563eb;">
                <input type="hidden" id="event_tag" name="event_tag" value="<?php echo htmlspecialchars($current_event_tag); ?>">
                <small class="form-text text-muted">Automatically locked to the active event set in Ziyarat Flow (<strong>"<?php echo htmlspecialchars($current_event_tag); ?>"</strong>).</small>
            </div>

            <div class="form-group">
                <label for="campaign_type"><i class="fas fa-bullhorn"></i> Campaign Type & Template</label>
                <select id="campaign_type" name="campaign_type" class="form-control" onchange="updateSubjectDefault(this.value)" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
                    <option value="standard">Standard Ziyarat Progress Reminder</option>
                    <option value="mumbai_prompt">Mumbai Presence Check (Ask Students to Mark Availability)</option>
                    <option value="mumbai_alert">Urgent Ziyarat Alert (Students Present in Mumbai Only)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="subject"><i class="fas fa-heading"></i> Email Subject Line</label>
                <input type="text" id="subject" name="subject" class="form-control" value="Ziyarat Raudat Tahera Reminder - <?php echo htmlspecialchars($current_event_tag); ?>" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
            </div>

            <div class="form-group">
                <label for="target_count"><i class="fas fa-bullseye"></i> Target Mumineen per Student</label>
                <input type="number" id="target_count" name="target_count" class="form-control" value="30" min="1" max="1000" <?php echo $active_campaign ? 'disabled' : ''; ?> required>
                <small class="form-text text-muted">Set the target number of Mumineen for students to perform Ziyarat on behalf of during this event.</small>
            </div>

            <div class="form-group">
                <label for="custom_message"><i class="fas fa-comment-alt"></i> Custom Note / Instructions (Optional)</label>
                <textarea id="custom_message" name="custom_message" class="form-control" rows="3" placeholder="e.g., Please ensure Ziyarat is completed before Friday..." <?php echo $active_campaign ? 'disabled' : ''; ?>></textarea>
            </div>

            <button type="submit" name="create_ziyarat_campaign" class="btn btn-primary" <?php echo $active_campaign ? 'disabled title="Archive current campaign first"' : ''; ?>>
                <i class="fas fa-save"></i> Create and Activate Ziyarat Campaign
            </button>
        </form>
    </div>

    <!-- TODAY'S LOGS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-check"></i> Today's Ziyarat Delivery Logs</h3>
        </div>
        <div class="table-container">
            <?php
            $today_logs_sql = "SELECT l.*, u.name, u.category, c.event_tag 
                              FROM ziyarat_mail_sent_logs l
                              JOIN users u ON l.user_id = u.id
                              JOIN ziyarat_mail_campaigns c ON l.campaign_id = c.id
                              WHERE DATE(l.sent_at) = CURDATE()
                              ORDER BY l.sent_at DESC";
            $today_logs = $conn->query($today_logs_sql);
            ?>
            <table class="responsive-table-stack">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event Tag</th>
                        <th>Student Name</th>
                        <th>TR Number</th>
                        <th>Branch</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($today_logs && $today_logs->num_rows > 0): ?>
                        <?php while ($log = $today_logs->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Time"><?php echo date('H:i', strtotime($log['sent_at'])); ?></td>
                                <td data-label="Event"><?php echo htmlspecialchars($log['event_tag']); ?></td>
                                <td data-label="Student"><strong><?php echo htmlspecialchars($log['name']); ?></strong></td>
                                <td data-label="TR"><?php echo htmlspecialchars($log['tr_number']); ?></td>
                                <td data-label="Branch"><?php echo htmlspecialchars($log['category']); ?></td>
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
                                No Ziyarat emails sent in the last 24 hours.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const activeEventTag = <?php echo json_encode($current_event_tag); ?>;
const activeCampaignId = <?php echo $active_campaign ? $active_campaign['id'] : 0; ?>;
const remainingTodayQuota = <?php echo $remaining_today; ?>;
let currentStatsData = null;

function updateSubjectDefault(type) {
    const subjectInput = document.getElementById('subject');
    if (!subjectInput) return;
    
    if (type === 'mumbai_prompt') {
        subjectInput.value = `Urgent: Update your Mumbai Availability for ${activeEventTag}`;
    } else if (type === 'mumbai_alert') {
        subjectInput.value = `Urgent: You are in Mumbai! Perform Ziyarat for ${activeEventTag}`;
    } else {
        subjectInput.value = `Ziyarat Raudat Tahera Reminder - ${activeEventTag}`;
    }
}

async function loadAudienceStats(campaignId) {
    if (!campaignId) return;
    const branchFilter = document.getElementById('branch_filter').value;
    const spinner = document.getElementById('statsSpinner');
    const audienceSelect = document.getElementById('audience_filter');
    const branchSelect = document.getElementById('branch_filter');

    if (spinner) spinner.style.display = 'inline-block';

    try {
        const response = await fetch('ajax_ziyarat_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `campaign_id=${campaignId}&action=get_audience_stats&branch_filter=${encodeURIComponent(branchFilter)}`
        });
        const res = await response.json();

        if (res.success && res.stats) {
            currentStatsData = res;

            // Update audience dropdown option labels with exact live counts
            const currentSelectedAudience = audienceSelect.value;
            audienceSelect.innerHTML = `
                <option value="all" ${currentSelectedAudience === 'all' ? 'selected' : ''}>All Eligible Students (${res.stats.all})</option>
                <option value="mumbai_only" ${currentSelectedAudience === 'mumbai_only' ? 'selected' : ''}>Available in Mumbai Only (${res.stats.mumbai_only})</option>
                <option value="not_mumbai_only" ${currentSelectedAudience === 'not_mumbai_only' ? 'selected' : ''}>NOT in Mumbai / Unset Only (${res.stats.not_mumbai_only})</option>
                <option value="pending_only" ${currentSelectedAudience === 'pending_only' ? 'selected' : ''}>Pending Ziyarat Only (${res.stats.pending_only})</option>
            `;

            // Update branch dropdown option labels if available
            if (res.branch_counts) {
                const bCounts = res.branch_counts;
                const currentBranchVal = branchSelect.value;
                branchSelect.innerHTML = `
                    <option value="all" ${currentBranchVal === 'all' ? 'selected' : ''}>All Branches (${bCounts.all || 0})</option>
                    <option value="Marol" ${currentBranchVal === 'Marol' ? 'selected' : ''}>Marol (${bCounts.Marol || 0})</option>
                    <option value="Surat" ${currentBranchVal === 'Surat' ? 'selected' : ''}>Surat (${bCounts.Surat || 0})</option>
                    <option value="Nairobi" ${currentBranchVal === 'Nairobi' ? 'selected' : ''}>Nairobi (${bCounts.Nairobi || 0})</option>
                    <option value="Karachi" ${currentBranchVal === 'Karachi' ? 'selected' : ''}>Karachi (${bCounts.Karachi || 0})</option>
                `;
            }

            updateRecommendationAnalysis();
        }
    } catch (e) {
        console.error('Error fetching audience stats:', e);
    } finally {
        if (spinner) spinner.style.display = 'none';
    }
}

function updateRecommendationAnalysis() {
    if (!currentStatsData) return;

    const audienceVal = document.getElementById('audience_filter').value;
    const branchSelect = document.getElementById('branch_filter');
    const branchText = branchSelect.options[branchSelect.selectedIndex] ? branchSelect.options[branchSelect.selectedIndex].text : 'Selected Branch';
    const batchInput = document.getElementById('manual_batch_size');
    const sendBtn = document.getElementById('sendBatchBtn');
    const analysisText = document.getElementById('analysisText');
    const targetBadge = document.getElementById('analysisTargetBadge');

    const targetCount = currentStatsData.stats[audienceVal] || 0;
    const quota = currentStatsData.remaining_today !== undefined ? currentStatsData.remaining_today : remainingTodayQuota;

    if (targetBadge) targetBadge.innerText = `Target: ${targetCount} Students`;

    let recommendationMsg = '';
    let recommendedBatch = 0;

    if (quota <= 0) {
        recommendationMsg = `<strong>⚠️ Daily SMTP Limit Reached (0/100 remaining in 24h).</strong> No more emails can be sent right now. Please wait until the 24-hour cycle resets before sending batch to <strong>${branchText}</strong>.`;
        recommendedBatch = 0;
        if (sendBtn) sendBtn.disabled = true;
    } else if (targetCount === 0) {
        recommendationMsg = `<strong>ℹ️ No Unsent Students Found.</strong> All eligible students in <strong>${branchText}</strong> matching this filter have already been reached or none fit the criteria.`;
        recommendedBatch = 0;
        if (sendBtn) sendBtn.disabled = true;
    } else if (targetCount <= quota) {
        recommendationMsg = `<strong>💡 Senior Developer Analysis & Recommendation:</strong> Target audience is <strong>${targetCount} students</strong> in <strong>${branchText}</strong>. Since you have <strong>${quota} remaining daily SMTP slots</strong>, you can safely send all <strong>${targetCount} emails</strong> in 1 batch.`;
        recommendedBatch = targetCount;
        if (sendBtn) sendBtn.disabled = false;
    } else {
        recommendationMsg = `<strong>⚠️ Senior Developer Analysis & Recommendation:</strong> Target audience is <strong>${targetCount} students</strong> in <strong>${branchText}</strong>, but today's remaining SMTP capacity is <strong>${quota} mails</strong> (capped at 100/24h). Recommended max batch size for today is <strong>${quota}</strong>. The remaining <strong>${targetCount - quota} students</strong> should be dispatched tomorrow after quota resets.`;
        recommendedBatch = quota;
        if (sendBtn) sendBtn.disabled = false;
    }

    if (analysisText) analysisText.innerHTML = recommendationMsg;
    if (batchInput && recommendedBatch > 0) {
        batchInput.value = recommendedBatch;
    } else if (batchInput && targetCount === 0) {
        batchInput.value = 0;
    }
}

async function startZiyaratBatchSend(campaignId) {
    const batchSizeInput = document.getElementById('manual_batch_size');
    const audienceFilter = document.getElementById('audience_filter').value;
    const branchFilter = document.getElementById('branch_filter').value;
    const totalToProcess = parseInt(batchSizeInput.value);
    
    if (isNaN(totalToProcess) || totalToProcess < 1) {
        showToast('Please enter a valid batch size.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to send up to ${totalToProcess} Ziyarat emails for branch [${branchFilter}] now?`)) return;

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
            const response = await fetch('ajax_ziyarat_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `campaign_id=${campaignId}&batch_size=1&audience_filter=${audienceFilter}&branch_filter=${encodeURIComponent(branchFilter)}`
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
                    <td data-label="Branch">${item.category}</td>
                    <td data-label="Name"><strong>${item.name}</strong></td>
                    <td data-label="Email">${item.email}</td>
                    <td data-label="Assignments">${item.completed} / ${item.assigned}</td>
                    <td data-label="Status">
                        <span class="badge ${statusClass}">${statusText}</span>
                        ${errorInfo}
                    </td>
                `;
                logBody.insertBefore(tr, logBody.firstChild);

                processed++;
                if (item.status === 'success') successful++; else failed++;
                batchCounter.innerText = `${processed} / ${totalToProcess} Processed`;
            } else {
                if (processed === 0) showToast(result.message || 'No more eligible students to process.', 'info');
                break;
            }
        } catch (e) {
            console.error('Ziyarat email processing error:', e);
            failed++;
            processed++;
            batchCounter.innerText = `${processed} / ${totalToProcess} Processed`;
        }
    }

    showToast(`Batch completed: ${successful} sent, ${failed} failed.`, failed > 0 ? 'warning' : 'success');
    
    // Refresh stats dynamically after batch send
    loadAudienceStats(campaignId);

    const reloadBtn = document.createElement('button');
    reloadBtn.className = "btn btn-primary mt-3";
    reloadBtn.innerHTML = "<i class='fas fa-sync'></i> Refresh Dashboard";
    reloadBtn.onclick = () => location.reload();
    progressDiv.appendChild(reloadBtn);
}

async function sendTestMail(campaignId) {
    if (!confirm('Send test mail to TR 25687 (Mulla Idris bhai Laheri) now?')) return;

    try {
        const response = await fetch('ajax_ziyarat_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `campaign_id=${campaignId}&batch_size=1&test_tr_number=25687`
        });

        const result = await response.json();
        if (!result.success) {
            showToast(result.message || 'Test send failed.', 'error');
            return;
        }

        const detail = (result.details && result.details.length) ? result.details[0] : null;
        if (detail && detail.status === 'success') {
            showToast(`Test mail sent successfully to ${detail.email} (TR 25687)`, 'success');
        } else if (detail) {
            showToast(`Test mail failed: ${detail.error || 'Unknown error'}`, 'error');
        } else {
            showToast(result.message || 'Test send completed.', 'info');
        }
    } catch (error) {
        showToast('Could not connect to server for test send.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (activeCampaignId > 0) {
        loadAudienceStats(activeCampaignId);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
