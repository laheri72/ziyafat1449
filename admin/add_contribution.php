<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'Add Contribution';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Finance access can add contributions for both users and admins.
if (has_finance_access()) {
    $sql = "SELECT id, its_number, tr_number, name, role FROM users WHERE role IN ('user', 'admin') ORDER BY role DESC, tr_number ASC";
} else {
    $sql = "SELECT id, its_number, tr_number, name, role FROM users WHERE role = 'user' ORDER BY tr_number ASC";
}
$users = $conn->query($sql);

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-plus"></i> Add Contribution</h1>

    <div class="card">
        <form id="addContributionForm">
            <div class="form-group">
                <label for="user_id"><i class="fas fa-user"></i> Select Member *</label>
                <select id="user_id" name="user_id" class="form-control select2-user" required>
                    <option value="">-- Select Member --</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['tr_number']) . ' - ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['its_number']) . ') [' . strtoupper($user['role']) . ']'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="amount_usd"><i class="fas fa-dollar-sign"></i> Amount (USD) *</label>
                    <input type="number" id="amount_usd" name="amount_usd" class="form-control" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="amount_inr"><i class="fas fa-rupee-sign"></i> Amount (INR) *</label>
                    <input type="number" id="amount_inr" name="amount_inr" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            <small style="display: block; margin-top: -10px; margin-bottom: 15px; color: var(--text-secondary);">Rate: 1 USD = 84.67 INR (Auto-converts)</small>

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

            <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="payment_date"><i class="fas fa-calendar-day"></i> Payment Date *</label>
                    <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="payment_method"><i class="fas fa-credit-card"></i> Method</label>
                    <select id="payment_method" name="payment_method" class="form-control">
                        <option value="">-- Select --</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="transaction_reference"><i class="fas fa-receipt"></i> Transaction Reference</label>
                <input type="text" id="transaction_reference" name="transaction_reference" class="form-control" placeholder="e.g., TXN123456">
            </div>

            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Contribution
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
        height: 45px;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 12px;
        color: var(--text-primary);
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 43px;
    }
</style>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-user').select2({
        placeholder: '-- Select Member --',
        width: '100%'
    });

    const amountUSD = document.getElementById('amount_usd');
    const amountINR = document.getElementById('amount_inr');
    const exchangeRate = 84.67;

    amountUSD.addEventListener('input', function() {
        if (this.value) {
            amountINR.value = (parseFloat(this.value) * exchangeRate).toFixed(2);
        }
    });

    amountINR.addEventListener('input', function() {
        if (this.value) {
            amountUSD.value = (parseFloat(this.value) / exchangeRate).toFixed(2);
        }
    });

    document.getElementById('addContributionForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const formData = new FormData(this);
            const response = await fetch('ajax_add_contribution.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                this.reset();
                $('.select2-user').val(null).trigger('change');
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Connection error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
