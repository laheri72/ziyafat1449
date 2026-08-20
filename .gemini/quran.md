# Quran Recitation Tracking & Deletion System Specification

This document contains the complete logic, database schema, SQL queries, PHP backend API, and JavaScript frontend state machine for replicating the **Quran Recitation Tracking & Deletion System** in another repository.

---

## 1. System Overview & Architecture

- **Scope**: Tracks Quran recitation completion by **Juz** (Para/Part, 1-30) across multiple Quran copies/rounds (Quran #1 to Quran #4).
- **Capacity**: 4 Qurans × 30 Juz = 120 Total Juz.
- **Dual-Mode User Interface**:
  - **Add Progress Mode (`complete`)**: Allows users to select uncompleted Juz items and mark them as completed.
  - **Delete Progress Mode (`delete`)**: Allows users to select completed Juz items and delete their completion logs (reverting the Juz to incomplete state).
- **Transaction Safety**: All bulk updates or deletions execute within database transactions (`begin_transaction`, `commit`, `rollback`) to ensure atomic execution.
- **Dynamic Updates**: UI updates instantly via AJAX without page reloads.

---

## 2. Database Schema (`quran_progress`)

The module uses a single relational table to record every completed Juz entry per user and per Quran round/copy.

```sql
CREATE TABLE IF NOT EXISTS `quran_progress` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `quran_number` INT(11) NOT NULL, -- Ranges 1 to 4 (Total 4 Qurans)
  `juz_number` INT(11) NOT NULL,   -- Ranges 1 to 30 (Total 30 Juz per Quran)
  `is_completed` TINYINT(1) DEFAULT 0,
  `completed_date` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Database Flow & SQL Queries

### A. Initializing User Page Progress (Read Queries)
- **Get all user progress map**:
  ```sql
  SELECT * FROM quran_progress 
  WHERE user_id = ? 
  ORDER BY quran_number, juz_number;
  ```

- **Calculate overall completion summary**:
  ```sql
  SELECT 
      COUNT(*) AS completed_juz,
      120 AS total_juz,
      ROUND((COUNT(*) / 120) * 100, 2) AS progress_percentage
  FROM quran_progress 
  WHERE user_id = ? AND is_completed = 1;
  ```

- **Count per Quran round (1 through 4)**:
  ```sql
  SELECT COUNT(*) AS count 
  FROM quran_progress 
  WHERE user_id = ? AND quran_number = ? AND is_completed = 1;
  ```

---

### B. Adding Progress (`action = 'complete'`)
1. **Duplicate Check Query**:
   ```sql
   SELECT id FROM quran_progress 
   WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1;
   ```

2. **Insert Query** (if non-existent):
   ```sql
   INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) 
   VALUES (?, ?, ?, 1, CURDATE());
   ```

---

### C. Deleting Progress (`action = 'delete'`)
1. **Locate Target Row Query** (Finds the newest matching completion entry):
   ```sql
   SELECT id FROM quran_progress 
   WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1 
   ORDER BY completed_date DESC, created_at DESC, id DESC 
   LIMIT 1;
   ```

2. **Deletion Query by Primary Key**:
   ```sql
   DELETE FROM quran_progress WHERE id = ?;
   ```

---

## 4. Backend Helper Functions (`includes/functions.php`)

```php
/**
 * Calculate Quran recitation progress metrics for a specific user
 */
function get_quran_progress($conn, $user_id) {
    $sql = "SELECT 
                COUNT(*) as completed_juz,
                120 as total_juz,
                ROUND((COUNT(*) / 120) * 100, 2) as progress_percentage
            FROM quran_progress 
            WHERE user_id = ? AND is_completed = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
```

---

## 5. Backend AJAX Endpoint API (`user/ajax_quran_tracking.php`)

Expects a `POST` request with a JSON body containing `action` (`"complete"` or `"delete"`) and an array of `selections`.

```php
<?php
ob_start();

function send_json_response($payload, $status_code = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

register_shutdown_function(function () {
    $error = error_get_last();
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if ($error && in_array($error['type'], $fatal_types, true)) {
        send_json_response([
            'success' => false,
            'message' => 'Server error: ' . $error['message']
        ], 500);
    }
});

require_once '../config/database.php';
require_once '../includes/functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

init_session();
if (!is_logged_in()) {
    send_json_response(['success' => false, 'message' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['selections']) || !is_array($data['selections'])) {
    send_json_response(['success' => false, 'message' => 'Invalid input data'], 400);
}

$user_id = $_SESSION['user_id'];
// Optional: Allow admins to update on behalf of target user
if (isset($data['target_user_id']) && has_amali_access()) {
    $user_id = intval($data['target_user_id']);
}

$action = isset($data['action']) ? strtolower(trim($data['action'])) : 'complete';
if (!in_array($action, ['complete', 'delete'], true)) {
    send_json_response(['success' => false, 'message' => 'Invalid action requested'], 400);
}

$selections = $data['selections'];
$success_count = 0;
$errors = [];
$transaction_started = false;

try {
    // Start Transaction
    $conn->begin_transaction();
    $transaction_started = true;
    
    foreach ($selections as $selection) {
        $quran_number = intval($selection['quran_number']);
        $juz_number = intval($selection['juz_number']);
        
        if ($quran_number < 1 || $quran_number > 4 || $juz_number < 1 || $juz_number > 30) {
            continue;
        }

        if ($action === 'complete') {
            // Check if already completed
            $check_sql = "SELECT id FROM quran_progress WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                $sql = "INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) 
                        VALUES (?, ?, ?, 1, CURDATE())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $user_id, $quran_number, $juz_number);

                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to update progress for Quran $quran_number Juz $juz_number";
                }
            }
        } else {
            // Delete mode logic
            $delete_sql = "SELECT id FROM quran_progress 
                           WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1 
                           ORDER BY completed_date DESC, created_at DESC, id DESC 
                           LIMIT 1";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
            $delete_stmt->execute();
            $delete_result = $delete_stmt->get_result();

            if ($delete_result->num_rows > 0) {
                $row = $delete_result->fetch_assoc();
                $remove_sql = "DELETE FROM quran_progress WHERE id = ?";
                $remove_stmt = $conn->prepare($remove_sql);
                $remove_stmt->bind_param("i", $row['id']);

                if ($remove_stmt->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to delete progress for Quran $quran_number Juz $juz_number";
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    $transaction_started = false;
    
    // Recalculate metrics for response payload
    $quran_progress = get_quran_progress($conn, $user_id);
    
    $quran_counts = [];
    for ($q = 1; $q <= 4; $q++) {
        $q_sql = "SELECT COUNT(*) as count FROM quran_progress WHERE user_id = ? AND quran_number = ? AND is_completed = 1";
        $q_stmt = $conn->prepare($q_sql);
        $q_stmt->bind_param("ii", $user_id, $q);
        $q_stmt->execute();
        $quran_counts[$q] = $q_stmt->get_result()->fetch_assoc()['count'];
    }
    
    send_json_response([
        'success' => true,
        'message' => $action === 'complete'
            ? ($success_count > 0 ? "$success_count Juz marked as completed!" : "No new progress to update.")
            : ($success_count > 0 ? "$success_count completed log(s) deleted successfully!" : "No matching completed progress found to delete."),
        'overall_progress' => $quran_progress,
        'quran_counts' => $quran_counts,
        'action' => $action,
        'errors' => $errors
    ]);
    
} catch (Throwable $e) {
    if ($transaction_started) {
        $conn->rollback();
    }
    send_json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>
```

---

## 6. Frontend UI & Interactive Logic (`user/quran_tracking.php`)

### A. HTML Grid Template Structure
```html
<div class="tracking-mode-switch">
    <button type="button" class="tracking-mode-btn active" id="mode-complete" onclick="setTrackingMode('complete')">
        <i class="fas fa-check-circle"></i> Add Progress
    </button>
    <button type="button" class="tracking-mode-btn delete" id="mode-delete" onclick="setTrackingMode('delete')">
        <i class="fas fa-trash-alt"></i> Delete Progress
    </button>
</div>

<!-- Quran Card Example (Repeat for Quran 1 to 4) -->
<div class="card" id="quran-card-1">
    <div class="card-header">
        <h3>Quran #1 - <span id="quran-count-1">0</span>/30 Juz (<span id="quran-percent-1">0</span>%)</h3>
        <button type="button" class="btn btn-outline btn-sm select-all-btn" onclick="selectAll(1)">
            <span class="select-all-label">Select Remaining</span>
        </button>
    </div>
    <div class="juz-grid">
        <!-- Juz 1 to 30 items -->
        <div class="juz-item" data-quran="1" data-juz="1" onclick="toggleSelection(this)">
            Juz 1
        </div>
    </div>
</div>

<!-- Floating Action Bar -->
<div class="floating-actions" id="floating-actions" style="display: none;">
    <button type="button" class="btn btn-warning" onclick="uploadProgress()">
        <span id="action-label">Save Progress</span> (<span id="selection-count">0</span>)
    </button>
</div>
```

---

### B. JavaScript State Machine & AJAX Controller
```javascript
let selectedJuz = [];
let trackingMode = 'complete'; // Modes: 'complete' | 'delete'

function setTrackingMode(mode) {
    if (trackingMode === mode) return;

    clearSelectedItems();
    trackingMode = mode;
    syncModeUI();
    updateFloatingButton();
}

function syncModeUI() {
    document.getElementById('mode-complete').classList.toggle('active', trackingMode === 'complete');
    document.getElementById('mode-delete').classList.toggle('active', trackingMode === 'delete');

    document.querySelectorAll('.select-all-label').forEach(label => {
        label.innerText = trackingMode === 'complete' ? 'Select Remaining' : 'Select Completed';
    });

    document.querySelectorAll('.juz-item.selected').forEach(item => {
        item.classList.toggle('delete-mode', trackingMode === 'delete');
    });

    document.getElementById('action-label').innerText = trackingMode === 'complete' ? 'Save Progress' : 'Delete Progress';
}

function clearSelectedItems() {
    document.querySelectorAll('.juz-item.selected').forEach(item => item.classList.remove('selected', 'delete-mode'));
    selectedJuz = [];
}

function toggleSelection(element) {
    const isCompleted = element.classList.contains('completed');
    
    // Complete mode allows selecting uncompleted items only
    // Delete mode allows selecting completed items only
    if ((trackingMode === 'complete' && isCompleted) || (trackingMode === 'delete' && !isCompleted)) return;

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
    const btn = document.getElementById('floating-actions');
    const countSpan = document.getElementById('selection-count');
    
    if (selectedJuz.length > 0) {
        btn.style.display = 'block';
        countSpan.innerText = selectedJuz.length;
        document.getElementById('action-label').innerText = trackingMode === 'complete' ? 'Save Progress' : 'Delete Progress';
    } else {
        btn.style.display = 'none';
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: trackingMode, selections: selectedJuz })
        });

        const responseText = await response.text();
        let result;

        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('Server returned an invalid JSON response.');
        }

        if (!response.ok) {
            throw new Error(result.message || `Server request failed (${response.status}).`);
        }

        if (result.success) {
            // Update item states directly without full reload
            selectedJuz.forEach(item => {
                const el = document.querySelector(`.juz-item[data-quran="${item.quran_number}"][data-juz="${item.juz_number}"]`);
                el.classList.remove('selected', 'delete-mode');

                if (trackingMode === 'complete') {
                    el.classList.add('completed');
                    el.innerHTML = `Juz ${item.juz_number}<br><i class="fas fa-check-circle"></i>`;
                } else {
                    el.classList.remove('completed');
                    el.innerHTML = `Juz ${item.juz_number}`;
                }
            });

            // Update Progress Bars
            updateProgressUI(result);

            showAlert('success', result.message);
            clearSelectedItems();
            syncModeUI();
            updateFloatingButton();
        } else {
            showAlert('error', result.message || 'An error occurred.');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', error.message || 'Failed to connect to the server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

function updateProgressUI(result) {
    // Update Overall Bar
    const overall = result.overall_progress;
    document.getElementById('overall-label').innerText = `Total Progress: ${overall.completed_juz} / 120 Juz`;
    document.getElementById('overall-percent').innerText = `${overall.progress_percentage}%`;
    document.getElementById('overall-bar').style.width = `${overall.progress_percentage}%`;

    // Update each Quran Bar
    for (const [quran, count] of Object.entries(result.quran_counts)) {
        const percent = ((count / 30) * 100).toFixed(2);
        document.getElementById(`quran-count-${quran}`).innerText = count;
        document.getElementById(`quran-percent-${quran}`).innerText = percent;
        document.getElementById(`quran-bar-${quran}`).style.width = `${percent}%`;
    }
}

function showAlert(type, message) {
    const alertDiv = document.getElementById('ajax-alert');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
    alertDiv.innerHTML = message;
    alertDiv.style.display = 'block';
    setTimeout(() => { alertDiv.style.display = 'none'; }, 5000);
}
```
