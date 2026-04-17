<?php
// Helper Functions

// Start session if not already started
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function is_logged_in() {
    init_session();
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is finance admin
function is_finance_admin() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin' 
           && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'finance_admin';
}

// Check if user is amali coordinator
function is_amali_coordinator() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin' 
           && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'amali_coordinator';
}

// Check if user is category-specific amali coordinator
function is_category_amali_coordinator() {
    init_session();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['admin_type'])) {
        return false;
    }
    
    $category_coordinators = [
        'surat_amali_coordinator',
        'marol_amali_coordinator',
        'karachi_amali_coordinator',
        'nairobi_amali_coordinator',
        'muntasib_amali_coordinator'
    ];
    
    return in_array($_SESSION['admin_type'], $category_coordinators);
}

// Check if user is super admin
function is_super_admin() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin' 
           && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'super_admin';
}

// Check if user has access to financial data
function has_finance_access() {
    return is_super_admin() || is_finance_admin();
}

// Check if user has access to amali data
function has_amali_access() {
    return is_super_admin() || is_amali_coordinator() || is_category_amali_coordinator();
}

// Check if user can access broadcast center
function can_access_broadcast_center() {
    init_session();

    if (is_super_admin() || is_amali_coordinator()) {
        return true;
    }

    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
        && isset($_SESSION['admin_type'])
        && in_array($_SESSION['admin_type'], ['amali_coordinator', 'amali_cordinator'], true);
}

// Check if user can manage amali masters (duas/books)
function can_manage_amali_masters() {
    return is_super_admin() || (has_amali_access() && !is_category_amali_coordinator());
}

// Get assigned category for category-specific amali coordinator
function get_assigned_category() {
    init_session();
    if (!isset($_SESSION['admin_type'])) {
        return null;
    }
    
    // Extract category from admin_type (e.g., 'surat_amali_coordinator' -> 'Surat')
    $admin_type = $_SESSION['admin_type'];
    $category_map = [
        'surat_amali_coordinator' => 'Surat',
        'marol_amali_coordinator' => 'Marol',
        'karachi_amali_coordinator' => 'Karachi',
        'nairobi_amali_coordinator' => 'Nairobi',
        'muntasib_amali_coordinator' => 'Muntasib'
    ];
    
    return isset($category_map[$admin_type]) ? $category_map[$admin_type] : null;
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Redirect if not admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: ../user/index.php');
        exit();
    }
}

// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format currency
function format_currency($amount, $currency = 'USD') {
    if ($currency === 'USD') {
        return '$' . number_format($amount, 2);
    } else {
        return '₹' . number_format($amount, 2);
    }
}

// Calculate percentage
function calculate_percentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

// Get user contributions with waterfall distribution logic
function get_user_contributions($conn, $user_id) {
    $sql = "SELECT 
                COALESCE(SUM(amount_usd), 0) as total_usd,
                COALESCE(SUM(amount_inr), 0) as total_inr
            FROM contributions 
            WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    // Year targets in INR (cumulative sequential order)
    // Total target is 290,000 INR split as: 66k + 97k + 127k
    $tasea_target = 66000;      // First 66k goes to Tasea
    $ashera_target = 97000;     // Next 97k goes to Ashera  
    $hadi_target = 127000;      // Next 127k goes to Hadi Ashara
    
    // Total paid in INR
    $total_paid_inr = $data['total_inr'];
    
    // Distribute payments sequentially (waterfall logic)
    // Fill Tasea first (0 to 66k)
    $tasea_paid = min($total_paid_inr, $tasea_target);
    $remaining_after_tasea = max(0, $total_paid_inr - $tasea_target);
    
    // Fill Ashera next (66k to 163k)
    $ashera_paid = min($remaining_after_tasea, $ashera_target);
    $remaining_after_ashera = max(0, $remaining_after_tasea - $ashera_target);
    
    // Fill Hadi Ashara last (163k to 290k)
    $hadi_paid = min($remaining_after_ashera, $hadi_target);
    
    // Convert to USD (approximate)
    $exchange_rate = 84.67;
    
    $data['current_year_inr'] = $tasea_paid;
    $data['current_year_usd'] = $tasea_paid / $exchange_rate;
    
    $data['next_year_inr'] = $ashera_paid;
    $data['next_year_usd'] = $ashera_paid / $exchange_rate;
    
    $data['final_year_inr'] = $hadi_paid;
    $data['final_year_usd'] = $hadi_paid / $exchange_rate;
    
    return $data;
}

// Get system settings
function get_system_settings($conn) {
    $sql = "SELECT * FROM system_settings LIMIT 1";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// Get all contributions for admin with waterfall distribution logic
function get_all_contributions($conn) {
    // Get total contributions
    $sql = "SELECT 
                COALESCE(SUM(amount_usd), 0) as total_usd,
                COALESCE(SUM(amount_inr), 0) as total_inr
            FROM contributions";
    
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    
    // Get total users count
    $sql_users = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
    $result_users = $conn->query($sql_users);
    $total_users = $result_users->fetch_assoc()['total'];
    
    // Year targets in INR per user
    $tasea_target_per_user = 66000;
    $ashera_target_per_user = 97000;
    $hadi_target_per_user = 127000;
    
    // Total targets for all users
    $tasea_target_total = $tasea_target_per_user * $total_users;
    $ashera_target_total = $ashera_target_per_user * $total_users;
    $hadi_target_total = $hadi_target_per_user * $total_users;
    
    // Total paid in INR
    $total_paid_inr = $data['total_inr'];
    
    // Distribute payments sequentially (waterfall logic)
    // Fill Tasea first
    $tasea_paid = min($total_paid_inr, $tasea_target_total);
    $remaining_after_tasea = max(0, $total_paid_inr - $tasea_target_total);
    
    // Fill Ashera next
    $ashera_paid = min($remaining_after_tasea, $ashera_target_total);
    $remaining_after_ashera = max(0, $remaining_after_tasea - $ashera_target_total);
    
    // Fill Hadi Ashara last
    $hadi_paid = min($remaining_after_ashera, $hadi_target_total);
    
    // Convert to USD (approximate)
    $exchange_rate = 84.67;
    
    $data['current_year_inr'] = $tasea_paid;
    $data['current_year_usd'] = $tasea_paid / $exchange_rate;
    
    $data['next_year_inr'] = $ashera_paid;
    $data['next_year_usd'] = $ashera_paid / $exchange_rate;
    
    $data['final_year_inr'] = $hadi_paid;
    $data['final_year_usd'] = $hadi_paid / $exchange_rate;
    
    return $data;
}

// Generate CSRF token
function generate_csrf_token() {
    init_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    init_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get user by ID
function get_user_by_id($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get Quran progress for a user
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

// Get Dua progress for a user
function get_dua_progress($conn, $user_id, $category = null) {
    $sql = "SELECT 
                dm.id,
                dm.dua_name,
                dm.dua_name_arabic,
                dm.category,
                dm.target_count,
                COALESCE(SUM(de.count_added), 0) as completed_count,
                ROUND((COALESCE(SUM(de.count_added), 0) / dm.target_count) * 100, 2) as progress_percentage,
                MAX(de.entry_date) as last_updated
            FROM duas_master dm
            LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
            WHERE dm.is_active = 1";
    
    if ($category) {
        $sql .= " AND dm.category = ?";
    }
    
    $sql .= " GROUP BY dm.id, dm.dua_name, dm.dua_name_arabic, dm.category, dm.target_count
              ORDER BY dm.display_order";
    
    $stmt = $conn->prepare($sql);
    if ($category) {
        $stmt->bind_param("is", $user_id, $category);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Get Dua entries history for a user
function get_dua_entries($conn, $user_id, $dua_id = null) {
    if ($dua_id) {
        $sql = "SELECT de.*, dm.dua_name 
                FROM dua_entries de
                JOIN duas_master dm ON de.dua_id = dm.id
                WHERE de.user_id = ? AND de.dua_id = ?
                ORDER BY de.entry_date DESC, de.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $dua_id);
    } else {
        $sql = "SELECT de.*, dm.dua_name 
                FROM dua_entries de
                JOIN duas_master dm ON de.dua_id = dm.id
                WHERE de.user_id = ?
                ORDER BY de.entry_date DESC, de.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Get Book transcription progress for a user (only selected/completed books)
function get_book_progress($conn, $user_id) {
    $sql = "SELECT 
                bm.id,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bt.status,
                bt.started_date,
                bt.completed_date,
                bt.notes
            FROM book_transcription bt
            JOIN books_master bm ON bt.book_id = bm.id
            WHERE bt.user_id = ? AND bm.is_active = 1
            ORDER BY bt.status DESC, bt.started_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get Book transcription progress with page tracking for a user
// Shows books even if deactivated, as long as they're in progress or completed
function get_book_progress_with_pages($conn, $user_id) {
    $sql = "SELECT 
                bm.id,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bm.total_pages,
                bm.is_active,
                bt.status,
                bt.pages_completed,
                bt.started_date,
                bt.completed_date,
                bt.notes
            FROM book_transcription bt
            JOIN books_master bm ON bt.book_id = bm.id
            WHERE bt.user_id = ?
            ORDER BY bt.status DESC, bt.started_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get all available books for selection
function get_available_books($conn) {
    $sql = "SELECT * FROM books_master WHERE is_active = 1 ORDER BY display_order";
    return $conn->query($sql);
}

// Get overall Amali progress summary
function get_amali_summary($conn, $user_id) {
    // Get Quran progress
    $sql_quran = "SELECT 
                    COUNT(DISTINCT CASE WHEN is_completed = 1 THEN CONCAT(quran_number, '-', juz_number) END) as completed_juz,
                    FLOOR(COUNT(DISTINCT CASE WHEN is_completed = 1 THEN CONCAT(quran_number, '-', juz_number) END) / 30) as completed_qurans
                  FROM quran_progress 
                  WHERE user_id = ?";
    $stmt = $conn->prepare($sql_quran);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $quran_data = $stmt->get_result()->fetch_assoc();
    
    // Get Dua count - sum all dua entries
    $sql_dua = "SELECT COALESCE(SUM(count_added), 0) as total_dua_count
                FROM dua_entries 
                WHERE user_id = ?";
    $stmt = $conn->prepare($sql_dua);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $dua_data = $stmt->get_result()->fetch_assoc();
    
    // Get Book progress
    $sql_books = "SELECT 
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as books_completed,
                    COUNT(CASE WHEN status = 'selected' THEN 1 END) as books_in_progress
                  FROM book_transcription 
                  WHERE user_id = ?";
    $stmt = $conn->prepare($sql_books);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $book_data = $stmt->get_result()->fetch_assoc();
    
    // Combine all data
    return array_merge($quran_data, $dua_data, $book_data);
}

// Get payment year label based on payment date
function get_payment_year_from_date($payment_date) {
    $date = new DateTime($payment_date);
    $year = (int)$date->format('Y');
    $month = (int)$date->format('m');
    
    // Determine which year period based on Apr-Mar fiscal year
    // Sabea: Apr 2023 - Mar 2024
    if (($year == 2023 && $month >= 4) || ($year == 2024 && $month <= 3)) {
        return 'Sabea (Apr 23 - Mar 24)';
    }
    // Samena: Apr 2024 - Mar 2025
    elseif (($year == 2024 && $month >= 4) || ($year == 2025 && $month <= 3)) {
        return 'Samena (Apr 24 - Mar 25)';
    }
    // Tasea: Apr 2025 - Mar 2026
    elseif (($year == 2025 && $month >= 4) || ($year == 2026 && $month <= 3)) {
        return 'Tasea (Apr 25 - Mar 26)';
    }
    // Ashera: Apr 2026 - Mar 2027
    elseif (($year == 2026 && $month >= 4) || ($year == 2027 && $month <= 3)) {
        return 'Ashera (Apr 26 - Mar 27)';
    }
    // Hadi Ashara: Apr 2027 onwards
    elseif ($year >= 2027 && $month >= 4) {
        return 'Hadi Ashara (Apr 27+)';
    }
    // Default fallback
    else {
        return 'Unknown Period';
    }
}
?>