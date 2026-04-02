<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Add Contribution';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Get all users
$sql = "SELECT id, its_number, tr_number, name FROM users WHERE role = 'user' ORDER BY tr_number ASC";
$users = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = clean_input($_POST['user_id']);
    $amount_usd = clean_input($_POST['amount_usd']);
    $amount_inr = clean_input($_POST['amount_inr']);
    $payment_year = clean_input($_POST['payment_year']);
    $payment_date = clean_input($_POST['payment_date']);
    $payment_method = clean_input($_POST['payment_method']);
    $transaction_reference = clean_input($_POST['transaction_reference']);
    $notes = clean_input($_POST['notes']);

    if (empty($user_id) || empty($amount_usd) || empty($payment_year) || empty($payment_date)) {
        $error = 'Please fill in all required fields';
    } else {
        // Insert contribution
        $sql = "INSERT INTO contributions (user_id, amount_usd, amount_inr, payment_year, payment_date, payment_method, transaction_reference, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iddsssssi", $user_id, $amount_usd, $amount_inr, $payment_year, $payment_date, $payment_method, $transaction_reference, $notes, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $success = 'Contribution added successfully!';
        } else {
            $error = 'Failed to add contribution. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-plus"></i> Add Contribution</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id"><i class="fas fa-user"></i> Select User *</label>
                <select id="user_id" name="user_id" class="form-control select2-user" required>
                    <option value="">-- Select User --</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['tr_number']) . ' - ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['its_number']) . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount_usd"><i class="fas fa-dollar-sign"></i> Amount (USD) *</label>
                <input type="number" id="amount_usd" name="amount_usd" class="form-control" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="amount_inr"><i class="fas fa-rupee-sign"></i> Amount (INR) *</label>
                <input type="number" id="amount_inr" name="amount_inr" class="form-control" step="0.01" min="0" required>
                <small>Auto-converts based on exchange rate (1 USD = 84.67 INR)</small>
            </div>

            <div class="form-group">
                <label for="payment_year"><i class="fas fa-calendar"></i> Payment Year *</label>
                <select id="payment_year" name="payment_year" class="form-control" required>
                    <option value="">-- Select Year --</option>
                    <option value="sabea">Sabea (Apr 2023 - Mar 2024)</option>
                    <option value="samena">Samena (Apr 2024 - Mar 2025)</option>
                    <option value="current">Tasea (Apr 2025 - Mar 2026) - Target: ₹66,000</option>
                    <option value="next">Ashera (Apr 2026 - Mar 2027) - Target: ₹97,000</option>
                    <option value="final">Hadi Ashara (Apr 2027 onwards) - Target: ₹1,27,000</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_date"><i class="fas fa-calendar-day"></i> Payment Date *</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="payment_method"><i class="fas fa-credit-card"></i> Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-control">
                    <option value="">-- Select Method --</option>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="UPI">UPI</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Debit Card">Debit Card</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="transaction_reference"><i class="fas fa-receipt"></i> Transaction Reference</label>
                <input type="text" id="transaction_reference" name="transaction_reference" class="form-control" placeholder="e.g., TXN123456">
            </div>

            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Contribution
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 12px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    .select2-container {
        width: 100% !important;
    }
</style>

<!-- Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2 for user selection with search in dropdown
    $('.select2-user').select2({
        placeholder: '-- Select User --',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        minimumResultsForSearch: 0, // Always show search box
        matcher: function(params, data) {
            // If there are no search terms, return all data
            if ($.trim(params.term) === '') {
                return data;
            }

            // Search in the text (which contains TR, name, and ITS)
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return data;
            }

            // Return null if no match
            return null;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>