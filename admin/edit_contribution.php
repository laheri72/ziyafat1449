<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Edit Contribution';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Get contribution ID
$contribution_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($contribution_id === 0) {
    header('Location: view_users.php');
    exit();
}

// Get contribution details
$sql = "SELECT c.*, u.name, u.its_number, u.tr_number 
        FROM contributions c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contribution_id);
$stmt->execute();
$contribution = $stmt->get_result()->fetch_assoc();

if (!$contribution) {
    header('Location: view_users.php');
    exit();
}

// Get all users for dropdown
$sql = "SELECT id, its_number, tr_number, name FROM users WHERE role = 'user' ORDER BY tr_number ASC";
$users = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user_id = clean_input($_POST['user_id']);
    $amount_usd = clean_input($_POST['amount_usd']);
    $amount_inr = clean_input($_POST['amount_inr']);
    $payment_year = clean_input($_POST['payment_year']);
    $payment_date = clean_input($_POST['payment_date']);
    $payment_method = clean_input($_POST['payment_method']);
    $transaction_reference = clean_input($_POST['transaction_reference']);
    $notes = clean_input($_POST['notes']);

    if (empty($new_user_id) || empty($amount_usd) || empty($payment_year) || empty($payment_date)) {
        $error = 'Please fill in all required fields';
    } else {
        // Update contribution
        $sql = "UPDATE contributions 
                SET user_id = ?, amount_usd = ?, amount_inr = ?, payment_year = ?, 
                    payment_date = ?, payment_method = ?, transaction_reference = ?, notes = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iddsssssi", $new_user_id, $amount_usd, $amount_inr, $payment_year, 
                         $payment_date, $payment_method, $transaction_reference, $notes, $contribution_id);

        if ($stmt->execute()) {
            $success = 'Contribution updated successfully!';
            // Refresh contribution data
            $stmt = $conn->prepare("SELECT c.*, u.name, u.its_number, u.tr_number 
                                   FROM contributions c 
                                   JOIN users u ON c.user_id = u.id 
                                   WHERE c.id = ?");
            $stmt->bind_param("i", $contribution_id);
            $stmt->execute();
            $contribution = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update contribution. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-edit"></i> Edit Contribution</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Contribution Details</h3>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id"><i class="fas fa-user"></i> Select User *</label>
                <select id="user_id" name="user_id" class="form-control select2-user" required>
                    <option value="">-- Select User --</option>
                    <?php 
                    $users->data_seek(0); // Reset pointer
                    while ($user = $users->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo ($user['id'] == $contribution['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['tr_number']) . ' - ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['its_number']) . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount_usd"><i class="fas fa-dollar-sign"></i> Amount (USD) *</label>
                <input type="number" id="amount_usd" name="amount_usd" class="form-control" 
                       step="0.01" min="0" value="<?php echo $contribution['amount_usd']; ?>" required>
            </div>

            <div class="form-group">
                <label for="amount_inr"><i class="fas fa-rupee-sign"></i> Amount (INR) *</label>
                <input type="number" id="amount_inr" name="amount_inr" class="form-control" 
                       step="0.01" min="0" value="<?php echo $contribution['amount_inr']; ?>" required>
                <small>Auto-converts based on exchange rate (1 USD = 84.67 INR)</small>
            </div>

            <div class="form-group">
                <label for="payment_year"><i class="fas fa-calendar"></i> Payment Year *</label>
                <select id="payment_year" name="payment_year" class="form-control" required>
                    <option value="">-- Select Year --</option>
                    <option value="sabea" <?php echo ($contribution['payment_year'] == 'sabea') ? 'selected' : ''; ?>>
                        Sabea (Apr 2023 - Mar 2024)
                    </option>
                    <option value="samena" <?php echo ($contribution['payment_year'] == 'samena') ? 'selected' : ''; ?>>
                        Samena (Apr 2024 - Mar 2025)
                    </option>
                    <option value="current" <?php echo ($contribution['payment_year'] == 'current') ? 'selected' : ''; ?>>
                        Tasea (Apr 2025 - Mar 2026) - Target: ₹66,000
                    </option>
                    <option value="next" <?php echo ($contribution['payment_year'] == 'next') ? 'selected' : ''; ?>>
                        Ashera (Apr 2026 - Mar 2027) - Target: ₹97,000
                    </option>
                    <option value="final" <?php echo ($contribution['payment_year'] == 'final') ? 'selected' : ''; ?>>
                        Hadi Ashara (Apr 2027 onwards) - Target: ₹1,27,000
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_date"><i class="fas fa-calendar-day"></i> Payment Date *</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" 
                       value="<?php echo $contribution['payment_date']; ?>" required>
            </div>

            <div class="form-group">
                <label for="payment_method"><i class="fas fa-credit-card"></i> Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-control">
                    <option value="">-- Select Method --</option>
                    <option value="Cash" <?php echo ($contribution['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank Transfer" <?php echo ($contribution['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="UPI" <?php echo ($contribution['payment_method'] == 'UPI') ? 'selected' : ''; ?>>UPI</option>
                    <option value="Cheque" <?php echo ($contribution['payment_method'] == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                    <option value="Credit Card" <?php echo ($contribution['payment_method'] == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="Debit Card" <?php echo ($contribution['payment_method'] == 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                    <option value="Other" <?php echo ($contribution['payment_method'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="transaction_reference"><i class="fas fa-receipt"></i> Transaction Reference</label>
                <input type="text" id="transaction_reference" name="transaction_reference" class="form-control" 
                       value="<?php echo htmlspecialchars($contribution['transaction_reference']); ?>" 
                       placeholder="e.g., TXN123456">
            </div>

            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($contribution['notes']); ?></textarea>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Contribution
                </button>
                <?php if ($user_id > 0): ?>
                    <a href="user_details.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php else: ?>
                    <a href="view_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Contribution Information</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
            <div>
                <p><strong>Contribution ID:</strong> #<?php echo $contribution['id']; ?></p>
                <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($contribution['created_at'])); ?></p>
            </div>
            <div>
                <p><strong>Current User:</strong> <?php echo htmlspecialchars($contribution['name']); ?></p>
                <p><strong>ITS:</strong> <?php echo htmlspecialchars($contribution['its_number']); ?> | 
                   <strong>TR:</strong> <?php echo htmlspecialchars($contribution['tr_number']); ?></p>
            </div>
        </div>
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

// Auto-calculate INR from USD
document.getElementById('amount_usd').addEventListener('input', function() {
    const usd = parseFloat(this.value) || 0;
    const inr = (usd * 84.6667).toFixed(2);
    document.getElementById('amount_inr').value = inr;
});

// Auto-calculate USD from INR
document.getElementById('amount_inr').addEventListener('input', function() {
    const inr = parseFloat(this.value) || 0;
    const usd = (inr / 84.6667).toFixed(2);
    document.getElementById('amount_usd').value = usd;
});
</script>

<?php require_once '../includes/footer.php'; ?>