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

function build_compact_progress_block($label, $current, $baseTarget, $goalPct, $color) {
    $goalTarget = max(1, round(($baseTarget * $goalPct) / 100));
    $progressPct = round(($current / $goalTarget) * 100, 1);
    $barWidth = min(100, max(0, $progressPct));
    $delta = $current - $goalTarget;

    if ($delta > 0) {
        $deltaText = "Ahead by " . number_format($delta);
        $deltaColor = '#166534';
    } elseif ($delta < 0) {
        $deltaText = "Behind by " . number_format(abs($delta));
        $deltaColor = '#991b1b';
    } else {
        $deltaText = "On target";
        $deltaColor = '#1e3a8a';
    }

    return "
    <div style='margin-bottom: 8px; font-size: 13px; background: #f8fafc; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0;'>
        <div style='display:flex; justify-content:space-between; margin-bottom: 6px;'>
            <strong>" . htmlspecialchars($label) . "</strong>
            <span>" . number_format($current) . " / " . number_format($goalTarget) . "</span>
        </div>
        <div style='height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-bottom: 4px;'>
            <div style='height: 100%; width: {$barWidth}%; background: {$color};'></div>
        </div>
        <div style='display:flex; justify-content:space-between; font-size: 11px;'>
            <span style='color: #475569;'>{$progressPct}% achieved</span>
            <span style='color: {$deltaColor};'>{$deltaText}</span>
        </div>
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

    $grouped_duas = ['dua' => [], 'tasbeeh' => [], 'namaz' => []];
    $res = $conn->query("SELECT dm.id, dm.dua_name, dm.category, dm.target_count as base_target,
                                COALESCE(sub.completed, 0) as completed
                         FROM duas_master dm
                         LEFT JOIN (
                             SELECT dua_id, SUM(count_added) as completed
                             FROM dua_entries
                             WHERE user_id = $userId
                             GROUP BY dua_id
                         ) sub ON dm.id = sub.dua_id
                         WHERE dm.is_active = 1
                         ORDER BY dm.display_order, dm.id");
    while($row = $res->fetch_assoc()) {
        $cat = $row['category'];
        if (isset($grouped_duas[$cat])) {
            $grouped_duas[$cat][] = [
                'name' => $row['dua_name'],
                'completed' => intval($row['completed']),
                'target' => intval($row['base_target'])
            ];
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
    $category_colors = ['dua' => '#2563eb', 'tasbeeh' => '#d97706', 'namaz' => '#7c3aed'];
    foreach ($grouped_duas as $cat => $duas) {
        if (empty($duas)) continue;
        $cat_name = ucfirst($cat);
        $cat_goal = $goals[$cat];
        $color = $category_colors[$cat] ?? '#1e3a8a';
        
        $goal_blocks .= "<div class='stat-box' style='margin-bottom: 12px; padding: 12px;'>";
        $goal_blocks .= "<div style='display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;'>";
        $goal_blocks .= "<strong style='color: {$color}; font-size: 14px;'>{$cat_name}</strong>";
        $goal_blocks .= "<span style='font-size: 12px; color: #475569;'>Goal: {$cat_goal}%</span>";
        $goal_blocks .= "</div>";
        
        foreach ($duas as $dua) {
            $goal_blocks .= build_compact_progress_block($dua['name'], $dua['completed'], $dua['target'], $cat_goal, $color);
        }
        $goal_blocks .= "</div>";
    }

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