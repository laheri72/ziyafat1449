<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Quran Recitation Tracking';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get all progress for this user
$sql = "SELECT * FROM quran_progress WHERE user_id = ? ORDER BY quran_number, juz_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress_result = $stmt->get_result();

// Create a map of completed juz
$completed_juz = [];
while ($row = $progress_result->fetch_assoc()) {
    if ($row['is_completed']) {
        $completed_juz[$row['quran_number']][$row['juz_number']] = true;
    }
}

// Get overall progress
$quran_progress = get_quran_progress($conn, $user_id);

require_once '../includes/header.php';
?>

<style>
    .tracking-mode-switch {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    .tracking-mode-btn {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid var(--border-color, #e0e0e0);
        border-radius: 8px;
        background: #fff;
        color: #555;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .tracking-mode-btn:hover {
        background: #f8f9fa;
    }
    .tracking-mode-btn.active#mode-complete {
        border-color: #10b981;
        background: #ecfdf5;
        color: #047857;
    }
    .tracking-mode-btn.active#mode-delete {
        border-color: #ef4444;
        background: #fef2f2;
        color: #b91c1c;
    }
    .juz-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 10px;
        padding: 20px;
    }
    .juz-item {
        width: 100%;
        padding: 15px 5px;
        border: 2px solid #ddd;
        border-radius: 8px;
        background: #fff;
        color: #333;
        font-weight: bold;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
        position: relative;
    }
    .juz-item:hover:not(.completed) {
        border-color: var(--primary-500);
        background: var(--primary-50);
        transform: translateY(-2px);
    }
    .juz-item.selected {
        border-color: var(--warning);
        background: #fffbeb;
        color: #92400e;
    }
    .juz-item.selected::after {
        content: '\f067';
        font-family: 'Font Awesome 6 Free';
        position: absolute;
        top: 2px;
        right: 5px;
        font-size: 0.7rem;
    }
    .juz-item.selected.delete-mode {
        border-color: #ef4444 !important;
        background: #fef2f2 !important;
        color: #b91c1c !important;
    }
    .juz-item.selected.delete-mode::after {
        content: '\f068' !important;
        color: #ef4444;
    }
    .juz-item.completed {
        border-color: #4CAF50;
        background: #4CAF50;
        color: #fff;
        cursor: pointer;
    }
    .floating-actions {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        display: none;
    }
    .select-all-btn {
        margin-left: 10px;
        font-size: 0.8rem;
        padding: 4px 10px;
    }
</style>

<div class="container">
    <div class="page-header">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; color: #666; text-decoration: none; font-weight: 500; font-size: 14px; padding: 6px 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e0e0e0; transition: all 0.2s;">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <h1><i class="fas fa-quran"></i> Quran Recitation Tracking</h1>
        <p>Select multiple Juz and save or delete your progress without reloading.</p>
    </div>

    <div id="ajax-alert" style="display: none;"></div>

    <!-- Mode Switcher -->
    <div class="card" style="padding: 15px; margin-bottom: 20px;">
        <div class="tracking-mode-switch">
            <button type="button" class="tracking-mode-btn active" id="mode-complete" onclick="setTrackingMode('complete')">
                <i class="fas fa-check-circle"></i> Add Progress
            </button>
            <button type="button" class="tracking-mode-btn" id="mode-delete" onclick="setTrackingMode('delete')">
                <i class="fas fa-trash-alt"></i> Delete Progress
            </button>
        </div>
        <p style="margin: 0; font-size: 0.85rem; color: #666; text-align: center;" id="mode-help-text">
            <i class="fas fa-info-circle"></i> <strong>Add Progress Mode:</strong> Click uncompleted Juz to mark them as completed.
        </p>
    </div>

    <!-- Overall Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Overall Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text" id="overall-label">Total Progress: <?php echo $quran_progress['completed_juz']; ?> / 120 Juz</span>
                <span class="progress-label-value" id="overall-percent"><?php echo $quran_progress['progress_percentage']; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="overall-bar" style="width: <?php echo $quran_progress['progress_percentage']; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Quran Progress Grid -->
    <?php for ($quran = 1; $quran <= 4; $quran++): 
        $completed_in_quran = 0;
        for ($juz = 1; $juz <= 30; $juz++) {
            if (isset($completed_juz[$quran][$juz])) {
                $completed_in_quran++;
            }
        }
        $quran_percentage = round(($completed_in_quran / 30) * 100, 2);
    ?>
    <div class="card" id="quran-card-<?php echo $quran; ?>">
        <div class="card-header">
            <h3>
                <i class="fas fa-book-quran"></i> Quran #<?php echo $quran; ?> - 
                <span id="quran-count-<?php echo $quran; ?>"><?php echo $completed_in_quran; ?></span>/30 Juz 
                (<span id="quran-percent-<?php echo $quran; ?>"><?php echo $quran_percentage; ?></span>%)
            </h3>
            <button type="button" class="btn btn-outline btn-sm select-all-btn" onclick="selectAll(<?php echo $quran; ?>)">
                <i class="fas fa-check-double"></i> <span class="select-all-label">Select Remaining</span>
            </button>
        </div>
        <div class="progress-container" style="padding: 0 20px;">
            <div class="progress-bar" style="height: 8px;">
                <div class="progress-fill" id="quran-bar-<?php echo $quran; ?>" style="width: <?php echo $quran_percentage; ?>%"></div>
            </div>
        </div>
        <div class="juz-grid">
            <?php for ($juz = 1; $juz <= 30; $juz++): 
                $is_completed = isset($completed_juz[$quran][$juz]);
            ?>
                <div class="juz-item <?php echo $is_completed ? 'completed' : ''; ?>" 
                     data-quran="<?php echo $quran; ?>" 
                     data-juz="<?php echo $juz; ?>"
                     onclick="toggleSelection(this)">
                    Juz <?php echo $juz; ?>
                    <?php if ($is_completed): ?>
                        <br><i class="fas fa-check-circle"></i>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endfor; ?>

    <div class="card">
        <p class="text-center" style="padding: 20px; color: var(--text-secondary);">
            <i class="fas fa-info-circle"></i> Click on multiple Juz to select them, then click the floating button below.
        </p>
    </div>
</div>

<!-- Floating Action Button -->
<div class="floating-actions" id="floating-actions">
    <button type="button" class="btn btn-warning btn-lg" onclick="uploadProgress()">
        <i class="fas fa-cloud-upload-alt"></i> <span id="action-label">Save Progress</span> (<span id="selection-count">0</span>)
    </button>
</div>

<script>
    let selectedJuz = [];
    let trackingMode = 'complete';

    function setTrackingMode(mode) {
        if (trackingMode === mode) return;

        clearSelectedItems();
        trackingMode = mode;
        syncModeUI();
    }

    function syncModeUI() {
        const isComplete = trackingMode === 'complete';
        document.getElementById('mode-complete').classList.toggle('active', isComplete);
        document.getElementById('mode-delete').classList.toggle('active', !isComplete);

        document.getElementById('mode-help-text').innerHTML = isComplete
            ? '<i class="fas fa-info-circle"></i> <strong>Add Progress Mode:</strong> Click uncompleted Juz to mark them as completed.'
            : '<i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> <strong>Delete Progress Mode:</strong> Click completed Juz to delete their completion logs.';

        document.querySelectorAll('.select-all-label').forEach(label => {
            label.innerText = isComplete ? 'Select Remaining' : 'Select Completed';
        });

        const floatingBtn = document.querySelector('#floating-actions button');
        if (floatingBtn) {
            floatingBtn.className = isComplete ? 'btn btn-warning btn-lg' : 'btn btn-danger btn-lg';
        }

        updateFloatingButton();
    }

    function clearSelectedItems() {
        document.querySelectorAll('.juz-item.selected').forEach(item => {
            item.classList.remove('selected', 'delete-mode');
        });
        selectedJuz = [];
    }

    function toggleSelection(element) {
        const isCompleted = element.classList.contains('completed');
        
        if ((trackingMode === 'complete' && isCompleted) || (trackingMode === 'delete' && !isCompleted)) {
            return;
        }

        const quran = element.getAttribute('data-quran');
        const juz = element.getAttribute('data-juz');
        
        element.classList.toggle('selected');
        element.classList.toggle('delete-mode', trackingMode === 'delete' && element.classList.contains('selected'));
        
        if (element.classList.contains('selected')) {
            selectedJuz.push({quran_number: quran, juz_number: juz});
        } else {
            selectedJuz = selectedJuz.filter(item => !(item.quran_number == quran && item.juz_number == juz));
        }
        
        updateFloatingButton();
    }

    function selectAll(quranNumber) {
        const grid = document.querySelector(`#quran-card-${quranNumber} .juz-grid`);
        const selector = trackingMode === 'complete'
            ? '.juz-item:not(.completed):not(.selected)'
            : '.juz-item.completed:not(.selected)';
        const items = grid.querySelectorAll(selector);
        
        items.forEach(item => {
            item.classList.add('selected');
            item.classList.toggle('delete-mode', trackingMode === 'delete');
            selectedJuz.push({
                quran_number: item.getAttribute('data-quran'), 
                juz_number: item.getAttribute('data-juz')
            });
        });
        
        updateFloatingButton();
    }

    function updateFloatingButton() {
        const btnContainer = document.getElementById('floating-actions');
        const countSpan = document.getElementById('selection-count');
        const actionLabel = document.getElementById('action-label');
        
        if (selectedJuz.length > 0) {
            btnContainer.style.display = 'block';
            countSpan.innerText = selectedJuz.length;
            actionLabel.innerText = trackingMode === 'complete' ? 'Save Progress' : 'Delete Progress';
        } else {
            btnContainer.style.display = 'none';
        }
    }

    async function uploadProgress() {
        if (selectedJuz.length === 0) return;

        if (trackingMode === 'delete') {
            const confirmed = window.confirm(`Delete ${selectedJuz.length} completed log(s)? This will restore the selected juz to incomplete.`);
            if (!confirmed) return;
        }
        
        const btn = document.querySelector('#floating-actions button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = trackingMode === 'complete'
            ? '<i class="fas fa-spinner fa-spin"></i> Saving...'
            : '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        try {
            const response = await fetch('ajax_quran_tracking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: trackingMode, selections: selectedJuz })
            });

            const result = await response.json();

            if (result.success) {
                // Update UI items
                selectedJuz.forEach(item => {
                    const el = document.querySelector(`.juz-item[data-quran="${item.quran_number}"][data-juz="${item.juz_number}"]`);
                    if (el) {
                        el.classList.remove('selected', 'delete-mode');
                        if (trackingMode === 'complete') {
                            el.classList.add('completed');
                            el.innerHTML = `Juz ${item.juz_number}<br><i class="fas fa-check-circle"></i>`;
                        } else {
                            el.classList.remove('completed');
                            el.innerHTML = `Juz ${item.juz_number}`;
                        }
                    }
                });

                // Update Progress Bars
                updateProgressUI(result);

                // Show success alert
                showAlert('success', result.message);
                
                // Clear selection and sync mode UI
                clearSelectedItems();
                syncModeUI();
            } else {
                showAlert('error', result.message || 'An error occurred.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'Failed to connect to the server.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    function updateProgressUI(result) {
        // Update Overall
        const overall = result.overall_progress;
        document.getElementById('overall-label').innerText = `Total Progress: ${overall.completed_juz} / 120 Juz`;
        document.getElementById('overall-percent').innerText = `${overall.progress_percentage}%`;
        document.getElementById('overall-bar').style.width = `${overall.progress_percentage}%`;

        // Update each Quran
        for (const [quran, count] of Object.entries(result.quran_counts)) {
            const percent = ((count / 30) * 100).toFixed(2);
            document.getElementById(`quran-count-${quran}`).innerText = count;
            document.getElementById(`quran-percent-${quran}`).innerText = percent;
            document.getElementById(`quran-bar-${quran}`).style.width = `${percent}%`;
        }
    }

    function showAlert(type, message) {
        const alertDiv = document.getElementById('ajax-alert');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'error'} fade-in`;
        alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        alertDiv.style.display = 'flex';
        
        window.scrollTo({ top: 0, behavior: 'smooth' });

        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    }
</script>

<?php require_once '../includes/header.php'; ?>
