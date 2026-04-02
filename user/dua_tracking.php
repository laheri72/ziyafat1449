<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Dua Tracking';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get all categories data
$dua_data = get_dua_progress($conn, $user_id, 'dua');
$tasbeeh_data = get_dua_progress($conn, $user_id, 'tasbeeh');
$namaz_data = get_dua_progress($conn, $user_id, 'namaz');

require_once '../includes/header.php';
?>

<style>
/* Tab Navigation Styles */
.tab-navigation {
    display: flex;
    gap: 0;
    margin-bottom: 30px;
    border-bottom: 2px solid #e0e0e0;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}

.tab-button {
    flex: 1;
    padding: 16px 24px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab-button:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #333;
}

.tab-button.active {
    background: white;
    color: var(--primary-600);
    border-bottom: 3px solid var(--primary-600);
}

.tab-button i {
    font-size: 18px;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Toast Notification */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    display: none;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast.success {
    border-left: 4px solid #22c55e;
}

.toast.error {
    border-left: 4px solid #ef4444;
}

.toast i {
    font-size: 20px;
}

.toast.success i {
    color: #22c55e;
}

.toast.error i {
    color: #ef4444;
}

.toast-message {
    flex: 1;
    font-size: 14px;
    color: #333;
}

/* Loading State */
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .tab-button {
        padding: 12px 16px;
        font-size: 14px;
    }
    
    .tab-button span {
        display: none;
    }
    
    .toast {
        right: 10px;
        left: 10px;
        min-width: auto;
    }
}
</style>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-hands-praying"></i> Dua Tracking</h1>
        <p>Track your daily Duas, Tasbeeh, and Namaz</p>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" data-tab="dua">
            <i class="fas fa-book-open"></i>
            <span>Dua</span>
        </button>
        <button class="tab-button" data-tab="tasbeeh">
            <i class="fas fa-dharmachakra"></i>
            <span>Tasbeeh</span>
        </button>
        <button class="tab-button" data-tab="namaz">
            <i class="fas fa-mosque"></i>
            <span>Namaz</span>
        </button>
    </div>

    <!-- Dua Tab Content -->
    <div class="tab-content active" id="dua-content">
        <div class="stats-grid">
            <?php while ($item = $dua_data->fetch_assoc()): ?>
                <?php include 'partials/tracking_card.php'; ?>
            <?php endwhile; ?>
        </div>
        <?php if ($dua_data->num_rows === 0): ?>
            <div class="card">
                <p class="text-center">No duas available.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tasbeeh Tab Content -->
    <div class="tab-content" id="tasbeeh-content">
        <div class="stats-grid">
            <?php while ($item = $tasbeeh_data->fetch_assoc()): ?>
                <?php include 'partials/tracking_card.php'; ?>
            <?php endwhile; ?>
        </div>
        <?php if ($tasbeeh_data->num_rows === 0): ?>
            <div class="card">
                <p class="text-center">No tasbeeh available.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Namaz Tab Content -->
    <div class="tab-content" id="namaz-content">
        <div class="stats-grid">
            <?php while ($item = $namaz_data->fetch_assoc()): ?>
                <?php include 'partials/tracking_card.php'; ?>
            <?php endwhile; ?>
        </div>
        <?php if ($namaz_data->num_rows === 0): ?>
            <div class="card">
                <p class="text-center">No namaz tracking available.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <div class="toast-message"></div>
</div>

<script>
// Tab Switching
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        
        // Update active button
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        
        // Update active content
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(tabName + '-content').classList.add('active');
    });
});

// Toast Notification Function
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = toast.querySelector('i');
    const messageEl = toast.querySelector('.toast-message');
    
    // Update content
    messageEl.textContent = message;
    toast.className = 'toast ' + type;
    
    // Update icon
    if (type === 'success') {
        icon.className = 'fas fa-check-circle';
    } else {
        icon.className = 'fas fa-exclamation-circle';
    }
    
    // Show toast
    toast.style.display = 'flex';
    
    // Hide after 3 seconds
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// AJAX Form Submission
document.querySelectorAll('.tracking-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const duaId = formData.get('dua_id');
        
        // Add loading state
        submitBtn.classList.add('btn-loading');
        submitBtn.disabled = true;
        
        // Send AJAX request
        fetch('ajax_dua_entry.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading state
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            
            if (data.success) {
                // Show success message
                showToast(data.message, 'success');
                
                // Update progress bar and counts
                const card = document.querySelector(`[data-dua-id="${duaId}"]`);
                if (card) {
                    const progressBar = card.querySelector('.progress-fill');
                    const progressText = card.querySelector('.progress-label-text');
                    const progressValue = card.querySelector('.progress-label-value');
                    const countHelper = card.querySelector('.count-helper');
                    
                    // Update values
                    progressBar.style.width = Math.min(data.data.progress_percentage, 100) + '%';
                    progressText.textContent = `Total: ${data.data.completed_count} / ${data.data.target_count}`;
                    progressValue.textContent = data.data.progress_percentage + '%';
                    countHelper.textContent = `This will be added to your current total of ${data.data.completed_count}`;
                }
                
                // Reset form
                this.reset();
                this.querySelector('input[name="entry_date"]').value = new Date().toISOString().split('T')[0];
            } else {
                // Show error message
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            // Remove loading state
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            
            // Show error message
            showToast('An error occurred. Please try again.', 'error');
            console.error('Error:', error);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>