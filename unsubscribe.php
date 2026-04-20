<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = intval($_GET['u'] ?? 0);
$token = $_GET['t'] ?? '';

// Simple validation string hash: md5 of user_id + secret
$secret = 'ziyafat1449_bulk_mail_secret';
$expected_token = md5($user_id . $secret);

$success = false;
$error = '';

if ($user_id > 0 && hash_equals($expected_token, $token)) {
    $stmt = $conn->prepare("UPDATE users SET is_subscribed = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success = true;
    } else {
        $error = "Failed to update your preferences. Please contact administration.";
    }
} else {
    $error = "Invalid unsubscribe link securely signed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - Ziyafat Reminders</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); text-align: center; max-width: 400px; width: 100%; }
        h1 { font-size: 24px; margin-bottom: 20px; color: #1e293b; }
        p { color: #475569; line-height: 1.5; margin-bottom: 20px; }
        .success { color: #16a34a; }
        .error { color: #dc2626; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 500; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <h1 class="success">Unsubscribed successfully</h1>
            <p>You have been removed from the mailing list. You will no longer receive automated broadcast reminders.</p>
        <?php else: ?>
            <h1 class="error">Something went wrong</h1>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <a href="index.php" class="btn">Return to Home</a>
    </div>
</body>
</html>
