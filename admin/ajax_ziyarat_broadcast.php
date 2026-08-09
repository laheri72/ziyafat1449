<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer_helper.php';
require_once __DIR__ . '/../includes/supabase_helper.php';
require_once __DIR__ . '/../includes/ziyarat_mailer_helper.php';

header('Content-Type: application/json');

// 1. Security Check
if (!can_access_broadcast_center()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$campaign_id = intval($_POST['campaign_id'] ?? 0);
$batch_size = max(1, intval($_POST['batch_size'] ?? 10));
$test_tr_number = clean_input($_POST['test_tr_number'] ?? '');
$audience_filter = clean_input($_POST['audience_filter'] ?? 'all'); // 'all', 'pending_only', 'mumbai_only', 'not_mumbai_only'

if ($campaign_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Campaign ID']);
    exit();
}

// 2. Get Campaign Info
$stmt = $conn->prepare("SELECT * FROM ziyarat_mail_campaigns WHERE id = ?");
$stmt->bind_param("i", $campaign_id);
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();

if (!$campaign) {
    echo json_encode(['success' => false, 'message' => 'Ziyarat Campaign not found']);
    exit();
}

$event_tag = $campaign['event_tag'];
$campaign_type = $campaign['campaign_type'] ?? 'standard';
$subject = $campaign['subject'];
$target_count = intval($campaign['target_count'] ?: 30);
$custom_message = $campaign['custom_message'];

// 3. Fetch Active Students from Supabase
$supabase_students = get_supabase_students();
if (empty($supabase_students)) {
    echo json_encode(['success' => false, 'message' => 'Could not retrieve student list from Ziyarat Flow.']);
    exit();
}

$student_map = [];
foreach ($supabase_students as $s) {
    $tr = $s['tr_number'];
    $is_mumbai = !empty($s['available_in_mumbai']) && $s['available_in_mumbai'];

    // Pre-filter student map based on audience filter when in batch mode
    if (empty($test_tr_number)) {
        if ($audience_filter === 'mumbai_only' && !$is_mumbai) continue;
        if ($audience_filter === 'not_mumbai_only' && $is_mumbai) continue;
    }

    $student_map[$tr] = $s;
}

// 4. Determine Target Users in MySQL
$tr_list = array_keys($student_map);
if (empty($tr_list)) {
    echo json_encode([
        'success' => true,
        'message' => 'No remaining eligible students matching the selected filter (' . htmlspecialchars($audience_filter) . ').',
        'sent' => 0,
        'failed' => 0,
        'details' => []
    ]);
    exit();
}

$escaped_trs = array_map(function($tr) use ($conn) {
    return "'" . $conn->real_escape_string($tr) . "'";
}, $tr_list);

$in_clause = implode(',', $escaped_trs);

if (!empty($test_tr_number)) {
    // Single Test Mail Mode (e.g. TR 25687)
    $escaped_test_tr = $conn->real_escape_string($test_tr_number);
    $sql = "SELECT * FROM users WHERE tr_number = '$escaped_test_tr' LIMIT 1";
} else {
    // Batch Mode: Only users matching pre-filtered TRs and not yet sent for this campaign
    $sql = "SELECT u.* FROM users u
            WHERE u.tr_number IN ($in_clause)
              AND u.is_subscribed = 1 
              AND u.email IS NOT NULL 
              AND u.email != ''
              AND u.id NOT IN (SELECT user_id FROM ziyarat_mail_sent_logs WHERE campaign_id = $campaign_id AND status = 'success')
            ORDER BY u.id ASC";
}

$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
    exit();
}

$candidate_users = [];
while ($user = $res->fetch_assoc()) {
    $candidate_users[] = $user;
    if (count($candidate_users) >= $batch_size && empty($test_tr_number)) {
        break;
    }
}

if (empty($candidate_users)) {
    echo json_encode([
        'success' => true,
        'message' => 'No remaining eligible users to send in this batch.',
        'sent' => 0,
        'failed' => 0,
        'details' => []
    ]);
    exit();
}

// 5. Query Targeted Stats from Supabase for exact batch TR numbers
$batch_trs = array_map(function($u) { return $u['tr_number']; }, $candidate_users);

// Event-specific stats
$event_stats_batch = get_supabase_stats_for_trs($batch_trs, $event_tag);

// Overall lifetime stats
$overall_stats_batch = get_supabase_stats_for_trs($batch_trs);

// 6. Filter & Send Emails
$sent_count = 0;
$fail_count = 0;
$details = [];

foreach ($candidate_users as $user) {
    $tr_number = $user['tr_number'];
    $supa_student = $student_map[$tr_number] ?? null;
    $event_stats = $event_stats_batch[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];
    $overall_stats = $overall_stats_batch[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];

    // Filter pending_only if selected
    if (empty($test_tr_number) && $audience_filter === 'pending_only' && $event_stats['pending'] == 0) {
        continue;
    }

    $userId = $user['id'];
    $userName = $user['name'];
    $email = $user['email'];
    $category = $user['category'] ?: 'General';

    $email_body = get_ziyarat_email_template($event_tag, $userName, $userId, $tr_number, $event_stats, $overall_stats, $target_count, $custom_message, $campaign_type);

    $current_status = 'success';
    $error_msg = null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $current_status = 'failed';
        $error_msg = "Invalid email format ($email).";
    } else {
        try {
            if (!send_email($email, $subject, $email_body)) {
                $current_status = 'failed';
                $error_msg = "SMTP delivery rejected.";
            } else {
                usleep(300000); // 0.3 seconds throttle
            }
        } catch (Exception $e) {
            $current_status = 'failed';
            $error_msg = $e->getMessage();
        }
    }

    // Log execution in MySQL (only if not a test email)
    if (empty($test_tr_number)) {
        $log_stmt = $conn->prepare("INSERT INTO ziyarat_mail_sent_logs (campaign_id, user_id, tr_number, status, error_message) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->bind_param("iisss", $campaign_id, $userId, $tr_number, $current_status, $error_msg);
        $log_stmt->execute();
    }

    if ($current_status === 'success') {
        $sent_count++;
    } else {
        $fail_count++;
    }

    $details[] = [
        'tr_number' => $tr_number,
        'category' => $category,
        'name' => $userName,
        'email' => $email,
        'assigned' => $event_stats['assigned'],
        'completed' => $event_stats['completed'],
        'pending' => $event_stats['pending'],
        'overall_assigned' => $overall_stats['assigned'],
        'overall_completed' => $overall_stats['completed'],
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
