<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mailer_helper.php';

header('Content-Type: application/json');

$default_subject = 'Your Amali Hadaya Report';

function get_campaign_goals($campaign) {
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

function build_goal_progress_block($label, $current, $baseTarget, $goalPct, $color) {
    $goalTarget = max(1, round(($baseTarget * $goalPct) / 100));
    $progressPct = round(($current / $goalTarget) * 100, 1);
    $barWidth = min(100, max(0, $progressPct));
    $delta = $current - $goalTarget;

    if ($delta > 0) {
        $deltaText = "Ahead by <strong>" . number_format($delta) . "</strong>";
        $deltaColor = '#166534';
    } elseif ($delta < 0) {
        $deltaText = "Behind by <strong>" . number_format(abs($delta)) . "</strong>";
        $deltaColor = '#991b1b';
    } else {
        $deltaText = "On target";
        $deltaColor = '#1e3a8a';
    }

    return "
    <div class='stat-box' style='margin-bottom: 12px;'>
        <div style='display:flex; justify-content:space-between; gap:8px; align-items:center;'>
            <strong>$label</strong>
            <span style='font-size:12px; color:#475569;'>Goal: $goalPct%</span>
        </div>
        <div style='font-size: 13px; margin-top: 4px;'>
            Achieved: <strong>" . number_format($current) . "</strong> / Target: <strong>" . number_format($goalTarget) . "</strong>
            (<strong>" . number_format($progressPct, 1) . "%</strong>)
        </div>
        <div class='progress-bar'><div class='progress-fill' style='width: {$barWidth}%; background: {$color};'></div></div>
        <div style='margin-top: 4px; font-size: 12px; color: {$deltaColor};'>{$deltaText}</div>
    </div>";
}

// 1. Security Check
if (!can_access_broadcast_center()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$campaign_id = intval($_POST['campaign_id'] ?? 0);
$batch_size = intval($_POST['batch_size'] ?? 10);
$test_user_id = intval($_POST['test_user_id'] ?? 0);

if ($campaign_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Campaign ID']);
    exit();
}

// 2. Get Campaign Info
$campaign = $conn->query("SELECT * FROM mail_campaigns WHERE id = $campaign_id")->fetch_assoc();
if (!$campaign) {
    echo json_encode(['success' => false, 'message' => 'Campaign not found']);
    exit();
}
$goals = get_campaign_goals($campaign);

// 3. Get Users not yet sent for this campaign (Include Admins as well)
if ($test_user_id > 0) {
    $sql = "SELECT * FROM users WHERE id = $test_user_id LIMIT 1";
} else {
    $sql = "SELECT * FROM users 
            WHERE (role = 'user' OR role = 'admin') AND its_number NOT LIKE '000000%'
            AND is_subscribed = 1 AND email IS NOT NULL AND email != ''
            AND id NOT IN (SELECT user_id FROM mail_sent_logs WHERE campaign_id = $campaign_id AND status = 'success')
            LIMIT $batch_size";
}

$users_res = $conn->query($sql);
$sent_count = 0;
$fail_count = 0;
$details = [];

while ($user = $users_res->fetch_assoc()) {
    $userId = $user['id'];
    $category = $user['category'] ?: 'General';
    $userName = $user['name'];
    $email = $user['email'];
    $tr_number = $user['tr_number'] ?: '-';

    // --- Generate Personalized Stats ---
    $quran = get_quran_progress($conn, $userId);

    $category_progress = [
        'dua' => ['completed' => 0, 'target' => 0],
        'tasbeeh' => ['completed' => 0, 'target' => 0],
        'namaz' => ['completed' => 0, 'target' => 0]
    ];
    $res = $conn->query("SELECT dm.category,
                                COALESCE(SUM(dm.target_count), 0) as base_target,
                                COALESCE(MAX(sub.completed), 0) as completed
                         FROM duas_master dm
                         LEFT JOIN (
                             SELECT dm2.category, SUM(de.count_added) as completed
                             FROM dua_entries de
                             JOIN duas_master dm2 ON de.dua_id = dm2.id
                             WHERE de.user_id = $userId
                             GROUP BY dm2.category
                         ) sub ON dm.category = sub.category
                         WHERE dm.is_active = 1
                         GROUP BY dm.category");
    while($row = $res->fetch_assoc()) {
        if (isset($category_progress[$row['category']])) {
            $category_progress[$row['category']]['completed'] = intval($row['completed']);
            $category_progress[$row['category']]['target'] = intval($row['base_target']);
        }
    }
    
    $books_res = get_book_progress($conn, $userId);
    $books = ['completed' => 0, 'in_progress' => 0];
    while($b_row = $books_res->fetch_assoc()) {
        if ($b_row['status'] === 'completed') $books['completed']++;
        else $books['in_progress']++;
    }
    
    // Jamea Insights
    $cat_insights = "";
    if ($user['category']) {
        $ucat = $user['category'];
        $avg_sql = "SELECT 
            (SELECT AVG(completed_juz) FROM (SELECT user_id, COUNT(*) as completed_juz FROM quran_progress WHERE is_completed = 1 GROUP BY user_id) t JOIN users u ON t.user_id = u.id WHERE u.category = '$ucat') as avg_juz";
        
        $avg_res = $conn->query($avg_sql);
        if ($avg_res) {
            $avgs = $avg_res->fetch_assoc();
            $avgJuz = round($avgs['avg_juz'] ?? 0, 1);
            
            $cat_insights = "<div class='category-insight'>
                <strong>Jamea Insight ($ucat):</strong><br>
                On average, Mumineen in your Jamea have completed <strong>$avgJuz Juz</strong> of Tilawat ul Quran.
            </div>";
        }
    }

    // Construct Email
    $goal_blocks = "";
    $goal_blocks .= build_goal_progress_block('Dua', $category_progress['dua']['completed'], $category_progress['dua']['target'], $goals['dua'], '#2563eb');
    $goal_blocks .= build_goal_progress_block('Tasbeeh', $category_progress['tasbeeh']['completed'], $category_progress['tasbeeh']['target'], $goals['tasbeeh'], '#d97706');
    $goal_blocks .= build_goal_progress_block('Namaz', $category_progress['namaz']['completed'], $category_progress['namaz']['target'], $goals['namaz'], '#7c3aed');

    $content = "
    <p><strong>Event:</strong> " . htmlspecialchars($campaign['event_name']) . "</p>
    <p>This report shows your current progress against the campaign goals set by Janib.</p>
    
    <div class='stat-box'>
        <strong>Quran Progress:</strong><br>
        • Completed: {$quran['completed_juz']} / 120 Juz
        <div class='progress-bar'><div class='progress-fill' style='width: {$quran['progress_percentage']}%; background: #10b981;'></div></div>
    </div>

    <div style='margin: 12px 0;'>
        <strong>Amali Goal Tracking (Personalized):</strong>
        $goal_blocks
    </div>

    <div class='stat-box'>
        <strong>Istinsakh ul Kutub:</strong><br>
        • {$books['completed']} Completed, {$books['in_progress']} In Progress
    </div>

    $cat_insights
    ";

    $body = get_email_template($default_subject, $content, $userName, $userId);

    $current_status = 'success';
    $error_msg = null;

    // --- Validate email format ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $current_status = 'failed';
        $error_msg = "Invalid email format.";
    } else {
        // --- EXECUTION WITH ERROR CATCHING ---
        try {
            if (!send_email($email, $default_subject, $body)) {
                $current_status = 'failed';
                $error_msg = "SMTP rejected delivery.";
            } else {
                // Throttle specifically on success to avoid rate limits
                usleep(300000); // 0.3 seconds
            }
        } catch (Exception $e) {
            $current_status = 'failed';
            $error_msg = $e->getMessage();
        }
    }

    // Log the result
    $stmt = $conn->prepare("INSERT INTO mail_sent_logs (campaign_id, user_id, status, error_message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $campaign_id, $userId, $current_status, $error_msg);
    $stmt->execute();

    if ($current_status === 'success') {
        $sent_count++;
    } else {
        $fail_count++;
    }

    // Store details for UI table
    $details[] = [
        'tr_number' => $tr_number,
        'category' => $category,
        'name' => $userName,
        'email' => $email,
        'status' => $current_status,
        'error' => $error_msg
    ];
}

echo json_encode([
    'success' => true,
    'message' => "Batch completed: $sent_count sent, $fail_count failed.",
    'sent' => $sent_count,
    'failed' => $fail_count,
    'details' => $details
]);
?>