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

$action = clean_input($_POST['action'] ?? 'send_batch');
$campaign_id = intval($_POST['campaign_id'] ?? 0);
$batch_size = max(1, intval($_POST['batch_size'] ?? 10));
$test_tr_number = clean_input($_POST['test_tr_number'] ?? '');
$audience_filter = clean_input($_POST['audience_filter'] ?? 'all'); // 'all', 'pending_only', 'mumbai_only', 'not_mumbai_only'
$branch_filter = clean_input($_POST['branch_filter'] ?? 'all'); // 'all', 'Marol', 'Surat', 'Nairobi', 'Karachi'

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

// Fetch Active Students from Supabase
$supabase_students = get_supabase_students();
if (empty($supabase_students)) {
    echo json_encode(['success' => false, 'message' => 'Could not retrieve student list from Ziyarat Flow.']);
    exit();
}

// Helper function to normalize branch name
$normalize_branch = function($category, $supa_branch) {
    $c = strtolower(trim($category ?: ''));
    $sb = strtolower(trim($supa_branch ?: ''));
    if (strpos($c, 'marol') !== false || strpos($sb, 'marol') !== false) return 'Marol';
    if (strpos($c, 'surat') !== false || strpos($sb, 'surat') !== false) return 'Surat';
    if (strpos($c, 'nairobi') !== false || strpos($sb, 'nairobi') !== false) return 'Nairobi';
    if (strpos($c, 'karachi') !== false || strpos($sb, 'karachi') !== false) return 'Karachi';
    return !empty($category) ? ucfirst($category) : (!empty($supa_branch) ? ucfirst($supa_branch) : 'Other');
};

// Map Supabase students by TR
$supa_student_map = [];
foreach ($supabase_students as $s) {
    $supa_student_map[$s['tr_number']] = $s;
}

// Fetch unsent eligible MySQL users for this campaign
$unsent_sql = "SELECT u.* FROM users u
               WHERE u.is_subscribed = 1 
                 AND u.email IS NOT NULL 
                 AND u.email != ''
                 AND u.id NOT IN (SELECT user_id FROM ziyarat_mail_sent_logs WHERE campaign_id = $campaign_id AND status = 'success')
               ORDER BY u.id ASC";

// ----------------------------------------------------
// ACTION 1: FETCH AUDIENCE STATS (DYNAMIC LIVE STATS)
// ----------------------------------------------------
if ($action === 'get_audience_stats') {
    $sent_today_z = $conn->query("SELECT COUNT(*) as today FROM ziyarat_mail_sent_logs WHERE sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['today'];
    $sent_today_amali = $conn->query("SELECT COUNT(*) as today FROM mail_sent_logs WHERE sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['today'];
    $remaining_today = max(0, 100 - ($sent_today_z + $sent_today_amali));

    $users_res = $conn->query($unsent_sql);
    $unsent_candidates = [];
    $all_trs = [];

    if ($users_res) {
        while ($u = $users_res->fetch_assoc()) {
            $tr = $u['tr_number'];
            if (isset($supa_student_map[$tr])) {
                $supa = $supa_student_map[$tr];
                $b_name = $normalize_branch($u['category'], $supa['branch'] ?? '');
                $unsent_candidates[] = [
                    'user' => $u,
                    'tr_number' => $tr,
                    'branch' => $b_name,
                    'available_in_mumbai' => !empty($supa['available_in_mumbai'])
                ];
                $all_trs[] = $tr;
            }
        }
    }

    // Get event stats from Supabase for all unsent candidate TRs
    $event_stats_batch = !empty($all_trs) ? get_supabase_stats_for_trs($all_trs, $event_tag) : [];

    $branch_counts = ['all' => 0, 'Marol' => 0, 'Surat' => 0, 'Nairobi' => 0, 'Karachi' => 0];
    $filter_stats = ['all' => 0, 'mumbai_only' => 0, 'not_mumbai_only' => 0, 'pending_only' => 0];

    $selected_branch_lower = strtolower(trim($branch_filter));

    foreach ($unsent_candidates as $item) {
        $b_name = $item['branch'];
        $tr = $item['tr_number'];

        $branch_counts['all']++;
        if (isset($branch_counts[$b_name])) {
            $branch_counts[$b_name]++;
        } else {
            $branch_counts[$b_name] = 1;
        }

        $matches_branch = ($selected_branch_lower === 'all') || (strtolower($b_name) === $selected_branch_lower);

        if ($matches_branch) {
            $is_mumbai = $item['available_in_mumbai'];
            $st = $event_stats_batch[$tr] ?? ['pending' => 0];
            $has_pending = ($st['pending'] > 0);

            $filter_stats['all']++;
            if ($is_mumbai) {
                $filter_stats['mumbai_only']++;
            } else {
                $filter_stats['not_mumbai_only']++;
            }
            if ($has_pending) {
                $filter_stats['pending_only']++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'selected_branch' => $branch_filter,
        'stats' => $filter_stats,
        'branch_counts' => $branch_counts,
        'remaining_today' => $remaining_today
    ]);
    exit();
}

// ----------------------------------------------------
// ACTION 2: SEND BATCH / TEST EMAIL
// ----------------------------------------------------

$candidate_users = [];
$selected_branch_lower = strtolower(trim($branch_filter));

if (!empty($test_tr_number)) {
    // Single Test Mail Mode (e.g. TR 25687)
    $escaped_test_tr = $conn->real_escape_string($test_tr_number);
    $res = $conn->query("SELECT * FROM users WHERE tr_number = '$escaped_test_tr' LIMIT 1");
    if ($res && $u = $res->fetch_assoc()) {
        $candidate_users[] = $u;
    }
} else {
    // Batch Mode: filter candidate users matching branch & audience filter
    $users_res = $conn->query($unsent_sql);
    $pre_candidates = [];
    $candidate_trs = [];

    if ($users_res) {
        while ($u = $users_res->fetch_assoc()) {
            $tr = $u['tr_number'];
            if (!isset($supa_student_map[$tr])) continue;
            
            $supa = $supa_student_map[$tr];
            $b_name = $normalize_branch($u['category'], $supa['branch'] ?? '');
            $is_mumbai = !empty($supa['available_in_mumbai']);

            // Filter branch
            if ($selected_branch_lower !== 'all' && strtolower($b_name) !== $selected_branch_lower) {
                continue;
            }

            // Filter mumbai
            if ($audience_filter === 'mumbai_only' && !$is_mumbai) continue;
            if ($audience_filter === 'not_mumbai_only' && $is_mumbai) continue;

            $pre_candidates[] = $u;
            $candidate_trs[] = $tr;
        }
    }

    if (!empty($pre_candidates)) {
        // Fetch event stats for candidates to evaluate pending_only if selected
        $event_stats_pre = get_supabase_stats_for_trs($candidate_trs, $event_tag);

        foreach ($pre_candidates as $u) {
            $tr = $u['tr_number'];
            if ($audience_filter === 'pending_only') {
                $st = $event_stats_pre[$tr] ?? ['pending' => 0];
                if (($st['pending'] ?? 0) <= 0) continue;
            }

            $candidate_users[] = $u;
            if (count($candidate_users) >= $batch_size) {
                break;
            }
        }
    }
}

if (empty($candidate_users)) {
    echo json_encode([
        'success' => true,
        'message' => 'No remaining eligible users matching the selected filters.',
        'sent' => 0,
        'failed' => 0,
        'details' => []
    ]);
    exit();
}

// Fetch stats for exact candidate batch TR numbers
$batch_trs = array_map(function($u) { return $u['tr_number']; }, $candidate_users);
$event_stats_batch = get_supabase_stats_for_trs($batch_trs, $event_tag);
$overall_stats_batch = get_supabase_stats_for_trs($batch_trs);

$sent_count = 0;
$fail_count = 0;
$details = [];

foreach ($candidate_users as $user) {
    $tr_number = $user['tr_number'];
    $event_stats = $event_stats_batch[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];
    $overall_stats = $overall_stats_batch[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];

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
