<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mailer_helper.php';

header('Content-Type: application/json');

// 1. Security Check
if (!is_super_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$campaign_id = intval($_POST['campaign_id'] ?? 0);
$batch_size = intval($_POST['batch_size'] ?? 10);

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

// 3. Get Users not yet sent for this campaign (Include Admins as well)
$sql = "SELECT * FROM users 
        WHERE (role = 'user' OR role = 'admin') AND its_number NOT LIKE '000000%'
        AND id NOT IN (SELECT user_id FROM mail_sent_logs WHERE campaign_id = $campaign_id AND status = 'success')
        LIMIT $batch_size";

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
    
    $dua_summary = [];
    $res = $conn->query("SELECT dm.category, COALESCE(SUM(de.count_added), 0) as count 
                         FROM duas_master dm 
                         LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = $userId 
                         WHERE dm.is_active = 1 GROUP BY dm.category");
    while($row = $res->fetch_assoc()) { $dua_summary[$row['category']] = $row['count']; }
    
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
    $custom_note = nl2br(htmlspecialchars($campaign['custom_message']));
    $content = "
    <p>$custom_note</p>
    
    <div class='stat-box'>
        <strong>Spiritual Progress (Amali Janib):</strong><br>
        • Quran: {$quran['completed_juz']} / 120 Juz
        <div class='progress-bar'><div class='progress-fill' style='width: {$quran['progress_percentage']}%; background: #10b981;'></div></div>
        
        • Duas Recited: " . number_format($dua_summary['dua'] ?? 0) . "<br>
        • Istinsakh (Books): {$books['completed']} Completed, {$books['in_progress']} In Progress
    </div>

    $cat_insights
    ";

    $body = get_email_template($campaign['subject'], $content, $userName);

    $current_status = 'success';
    $error_msg = null;

    // --- EXECUTION WITH ERROR CATCHING ---
    try {
        if (!send_email($email, $campaign['subject'], $body)) {
            $current_status = 'failed';
            $error_msg = "SMTP rejected delivery.";
        }
    } catch (Exception $e) {
        $current_status = 'failed';
        $error_msg = $e->getMessage();
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

    // Short sleep to prevent server strain
    usleep(500000); // 0.5 seconds
}

echo json_encode([
    'success' => true,
    'message' => "Batch completed: $sent_count sent, $fail_count failed.",
    'sent' => $sent_count,
    'failed' => $fail_count,
    'details' => $details
]);
?>