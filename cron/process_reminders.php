<?php
/**
 * Automated Reminder System for Ziyafat us Shukr
 * This script is intended to be run via a Cron Job (e.g., every day at 8 AM).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer_helper.php';

// Prevent script timeout for large user bases
set_time_limit(0);

echo "Starting reminder process at " . date('Y-m-d H:i:s') . "\n";

/**
 * STRATEGY TO AVOID RATE LIMITS:
 * 1. Small batch size (Limit 5)
 * 2. Delay between sends (5 seconds)
 * 3. Rotate through users fairly (ORDER BY last_reminder_sent ASC)
 */

$sql = "SELECT * FROM users 
        WHERE role = 'user' 
        AND (
            last_reminder_sent IS NULL 
            OR DATEDIFF(NOW(), last_reminder_sent) >= 14 
            OR (DATEDIFF(NOW(), last_active) >= 15 AND DATEDIFF(NOW(), last_reminder_sent) >= 7)
        )
        ORDER BY last_reminder_sent ASC 
        LIMIT 5"; 

$users_result = $conn->query($sql);

if (!$users_result || $users_result->num_rows === 0) {
    echo "No users need reminders at this time.\n";
    exit();
}

while ($user = $users_result->fetch_assoc()) {
    $userId = $user['id'];
    $category = $user['category'];
    $userName = $user['name'];
    $email = $user['email'];

    echo "Processing $userName ($email)... ";

    // 1. Get User Progress
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
    $finance = get_user_contributions($conn, $userId);

    // 2. Get Category Insights (Averages)
    $cat_insights = "";
    if ($category) {
        $avg_sql = "SELECT 
            (SELECT AVG(completed_juz) FROM (SELECT user_id, COUNT(*) as completed_juz FROM quran_progress WHERE is_completed = 1 GROUP BY user_id) t JOIN users u ON t.user_id = u.id WHERE u.category = '$category') as avg_juz,
            (SELECT AVG(total_inr) FROM (SELECT user_id, SUM(amount_inr) as total_inr FROM contributions GROUP BY user_id) t JOIN users u ON t.user_id = u.id WHERE u.category = '$category') as avg_paid";
        
        $avg_res = $conn->query($avg_sql);
        if ($avg_res) {
            $avgs = $avg_res->fetch_assoc();
            $avgJuz = round($avgs['avg_juz'] ?? 0, 1);
            $avgPaid = round($avgs['avg_paid'] ?? 0);
            
            $cat_insights = "<div class='category-insight'>
                <strong>Jamea Insight ($category):</strong><br>
                On average, Mumineen in your Jamea have completed <strong>$avgJuz Juz</strong> and contributed <strong>" . format_currency($avgPaid, 'INR') . "</strong>. 
                Join them in reaching the goals together!
            </div>";
        }
    }

    // 3. Construct Email Content
    $content = "
    <p>We hope you are doing well. Here is a quick look at your progress in the 1449 H Ziyafat us Shukr goals:</p>
    
    <div class='stat-box'>
        <strong>Spiritual Progress (Amali Janib):</strong><br>
        • Quran: {$quran['completed_juz']} / 120 Juz
        <div class='progress-bar'><div class='progress-fill' style='width: {$quran['progress_percentage']}%; background: #10b981;'></div></div>
        
        • Duas Recited: " . number_format($dua_summary['dua'] ?? 0) . "<br>
        • Istinsakh (Books): {$books['completed']} Completed, {$books['in_progress']} In Progress
    </div>

    <div class='stat-box'>
        <strong>Financial Progress:</strong><br>
        • Total Contributed: " . format_currency($finance['total_inr'], 'INR') . "
        <div class='progress-bar'><div class='progress-fill' style='width: " . min(100, round(($finance['total_inr'] / 127000) * 100)) . "%; background: #2563eb;'></div></div>
        • Tasea (66k): " . ($finance['total_inr'] >= 66000 ? "✅ Completed" : "Pending") . "<br>
        • Ashera (97k): " . ($finance['total_inr'] >= 97000 ? "✅ Completed" : "Pending") . "
    </div>

    $cat_insights

    <p>Consistency is key to success. Even a small entry today takes you one step closer to your final target.</p>
    ";

    $subject = "Reminder: Your Ziyafat us Shukr Progress Update";
    $body = get_email_template("Your Progress Update", $content, $userName);

    // 4. Send Email
    $result = send_email($email, $subject, $body);

    if ($result) {
        echo "MAIL SENT\n";
        // Update last sent timestamp
        $conn->query("UPDATE users SET last_reminder_sent = NOW() WHERE id = $userId");
    } else {
        echo "MAIL FAILED\n";
    }

    // Small delay to prevent SMTP throttling
    sleep(5);
}

echo "Reminder process finished at " . date('Y-m-d H:i:s') . ".\n";
?>