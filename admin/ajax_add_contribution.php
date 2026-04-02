<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
init_session();
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $user_id = clean_input($_POST['user_id']);
    $amount_usd = clean_input($_POST['amount_usd']);
    $amount_inr = clean_input($_POST['amount_inr']);
    $payment_year = clean_input($_POST['payment_year']);
    $payment_date = clean_input($_POST['payment_date']);
    $payment_method = clean_input($_POST['payment_method']);
    $transaction_reference = clean_input($_POST['transaction_reference']);
    $notes = clean_input($_POST['notes']);

    if (empty($user_id) || empty($amount_usd) || empty($payment_year) || empty($payment_date)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }

    // Insert contribution
    $sql = "INSERT INTO contributions (user_id, amount_usd, amount_inr, payment_year, payment_date, payment_method, transaction_reference, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iddsssssi", $user_id, $amount_usd, $amount_inr, $payment_year, $payment_date, $payment_method, $transaction_reference, $notes, $_SESSION['user_id']);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Contribution added successfully!']);
    } else {
        throw new Exception('Failed to add contribution');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>