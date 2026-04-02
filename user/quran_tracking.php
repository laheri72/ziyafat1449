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
    .juz-item.completed {
        border-color: #4CAF50;
        background: #4CAF50;
        color: #fff;
        cursor: default;
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
        <h1><i class="fas fa-quran"></i> Quran Recitation Tracking</h1>
        <p>Select multiple Juz and save your progress without reloading.</p>
    </div>

    <div id="ajax-alert" style="display: none;"></div>

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
                <i class="fas fa-check-double"></i> Select Remaining
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
            <i class="fas fa-info-circle"></i> Click on multiple Juz to select them, then click the floating "Save Progress" button.
        </p>
    </div>
</div>

<!-- Floating Action Button -->
<div class="floating-actions" id="floating-actions">
    <button type="button" class="btn btn-warning btn-lg" onclick="uploadProgress()">
        <i class="fas fa-cloud-upload-alt"></i> Save Progress (<span id="selection-count">0</span>)
    </button>
</div>

<script>
    let selectedJuz = [];

    function toggleSelection(element) {
        if (element.classList.contains('completed')) return;

        const quran = element.getAttribute('data-quran');
        const juz = element.getAttribute('data-juz');
        
        element.classList.toggle('selected');
        
        if (element.classList.contains('selected')) {
            selectedJuz.push({quran_number: quran, juz_number: juz});
        } else {
            selectedJuz = selectedJuz.filter(item => !(item.quran_number == quran && item.juz_number == juz));
        }
        
        updateFloatingButton();
    }

    function selectAll(quranNumber) {
        const grid = document.querySelector(`#quran-card-${quranNumber} .juz-grid`);
        const items = grid.querySelectorAll('.juz-item:not(.completed):not(.selected)');
        
        items.forEach(item => {
            item.classList.add('selected');
            selectedJuz.push({
                quran_number: item.getAttribute('data-quran'), 
                juz_number: item.getAttribute('data-juz')
            });
        });
        
        updateFloatingButton();
    }

    function updateFloatingButton() {
        const btn = document.getElementById('floating-actions');
        const countSpan = document.getElementById('selection-count');
        
        if (selectedJuz.length > 0) {
            btn.style.display = 'block';
            countSpan.innerText = selectedJuz.length;
        } else {
            btn.style.display = 'none';
        }
    }

    async function uploadProgress() {
        if (selectedJuz.length === 0) return;
        
        const btn = document.querySelector('#floating-actions button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch('ajax_quran_tracking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ selections: selectedJuz })
            });

            const result = await response.json();

            if (result.success) {
                // Update UI for completed items
                selectedJuz.forEach(item => {
                    const el = document.querySelector(`.juz-item[data-quran="${item.quran_number}"][data-juz="${item.juz_number}"]`);
                    el.classList.remove('selected');
                    el.classList.add('completed');
                    el.innerHTML = `Juz ${item.juz_number}<br><i class="fas fa-check-circle"></i>`;
                });

                // Update Progress Bars
                updateProgressUI(result);

                // Show success alert
                showAlert('success', result.message);
                
                // Clear selection
                selectedJuz = [];
                updateFloatingButton();
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
