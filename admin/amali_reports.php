<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

// Check if user has amali access
if (!has_amali_access()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Amali Janib Reports';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';
$search_name = isset($_GET['search_name']) ? clean_input($_GET['search_name']) : '';
$filter_status = isset($_GET['filter_status']) ? clean_input($_GET['filter_status']) : '';
$filter_dua = isset($_GET['filter_dua']) ? intval($_GET['filter_dua']) : 0;
$filter_book = isset($_GET['filter_book']) ? intval($_GET['filter_book']) : 0;
$filter_category = isset($_GET['filter_category']) ? clean_input($_GET['filter_category']) : '';
$filter_classification = isset($_GET['filter_classification']) ? clean_input($_GET['filter_classification']) : '';
$sort_by = isset($_GET['sort_by']) ? clean_input($_GET['sort_by']) : '';
$sort_order = isset($_GET['sort_order']) ? clean_input($_GET['sort_order']) : 'desc';

// Check admin type and set category restrictions
$is_category_coordinator = is_category_amali_coordinator();
$assigned_category = get_assigned_category();

// If category amali coordinator, force filter to their assigned category
if ($is_category_coordinator && $assigned_category) {
    $filter_category = $assigned_category;
}

// Build category filter SQL
$category_filter_sql = '';
$category_filter_params = [];
if ($filter_category) {
    $category_filter_sql = " AND u.category = ?";
    $category_filter_params[] = $filter_category;
}

// Build classification filter SQL
$classification_filter_sql = '';
if ($filter_classification) {
    $classification_filter_sql = " AND u.classification = ?";
    $category_filter_params[] = $filter_classification;
}

require_once '../includes/header.php';
?>

<style>
    /* Professional Report Styling */
    .report-wrapper {
        min-height: 400px;
        position: relative;
        width: 100%;
        overflow: hidden; /* Prevent child elements from pushing width */
    }

    .table-container {
        width: 100%;
        background: #fff;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
    }

    /* Desktop Table Styles */
    @media (min-width: 769px) {
        .mobile-report-cards { display: none !important; }
        .table-container { display: block !important; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: var(--bg-secondary); color: var(--text-secondary); font-weight: 600; text-align: left; padding: 12px 15px; border-bottom: 2px solid var(--border-color); }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    }

    /* Mobile Card Styles (Replaces Table) */
    @media (max-width: 768px) {
        .table-container { display: none !important; } 
        .mobile-report-cards { display: block !important; width: 100%; }
        
        .report-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            width: 100%;
            box-sizing: border-box;
        }

        .report-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--border-color);
        }

        .report-card-title { font-weight: 700; color: var(--text-primary); font-size: 1rem; line-height: 1.2; }
        .report-card-subtitle { font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; }
        
        .report-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .report-data-item { display: flex; flex-direction: column; gap: 2px; }
        .report-data-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-tertiary); font-weight: 600; }
        .report-data-value { font-size: 0.85rem; font-weight: 500; color: var(--text-primary); }
        
        .report-card-full { grid-column: 1 / -1; margin-top: 0.5rem; }
        
        /* Adjust page header for mobile */
        .container { padding-left: 10px; padding-right: 10px; overflow-x: hidden; }
        .action-buttons { overflow-x: auto; white-space: nowrap; padding-bottom: 5px; -webkit-overflow-scrolling: touch; display: flex; gap: 5px; }
        .action-buttons .btn { flex: 0 0 auto; padding: 0.5rem 0.75rem; font-size: 0.75rem; }
    }
</style>

<div class="container">
    <h1 class="mb-3" style="font-size: 1.5rem;"><i class="fas fa-chart-bar"></i> Amali Janib Reports 
        <?php if ($filter_category): ?>
            <span class="badge badge-primary" style="font-size: 0.9rem; vertical-align: middle; background-color: #6366f1;">
                <?php echo htmlspecialchars($filter_category); ?> Branch
            </span>
        <?php else: ?>
            <span class="badge badge-secondary" style="font-size: 0.9rem; vertical-align: middle;">Global</span>
        <?php endif; ?>
    </h1>

    <!-- Report Type Selector -->
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header" style="padding: 0.75rem 1rem;">
            <h3 style="font-size: 1rem; margin: 0;"><i class="fas fa-filter"></i> Select Report Type</h3>
        </div>
        <div class="action-buttons" style="padding: 0.75rem 1rem;">
            <a href="?report_type=summary" class="btn <?php echo $report_type === 'summary' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fas fa-chart-pie"></i> Summary
            </a>
            <a href="?report_type=quran" class="btn <?php echo $report_type === 'quran' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fas fa-quran"></i> Quran
            </a>
            <a href="?report_type=dua" class="btn <?php echo $report_type === 'dua' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fas fa-hands-praying"></i> Dua
            </a>
            <a href="?report_type=books" class="btn <?php echo $report_type === 'books' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fas fa-book"></i> Kutub
            </a>
            <?php if (is_super_admin()): ?>
            <a href="bulk_amali_entry.php" class="btn btn-dark" style="padding: 0.5rem 1rem; font-size: 0.85rem; background-color: #1e293b; color: white;">
                <i class="fas fa-layer-group"></i> Bulk Collective Entry
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($report_type === 'summary'): ?>
        <!-- Summary Report -->
        <?php
        // Build SQL with category filter and sorting
        $sql = "SELECT u.id as user_id, u.name, u.its_number, u.category, u.classification, u.email, u.phone_number,
                    COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) as completed_juz,
                    FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) / 30) as completed_qurans,
                    COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN bt.book_id END) as books_completed,
                    COUNT(DISTINCT CASE WHEN bt.status = 'selected' THEN bt.book_id END) as books_in_progress
                FROM users u
                LEFT JOIN quran_progress qp ON u.id = qp.user_id
                LEFT JOIN book_transcription bt ON u.id = bt.user_id
                WHERE (u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%'" . $category_filter_sql . $classification_filter_sql . "
                GROUP BY u.id, u.name, u.its_number, u.category, u.classification, u.email, u.phone_number";

        // Add sorting (Note: overall_progress is calculated in PHP, so we'll sort that later)
        $order_by = "u.name";
        if ($sort_by === 'quran') {
            $order_by = "completed_qurans " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        } elseif ($sort_by === 'books') {
            $order_by = "books_completed " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        }
        $sql .= " ORDER BY " . $order_by;

        if (!empty($category_filter_params)) {
            $stmt = $conn->prepare($sql);
            $param_types = str_repeat('s', count($category_filter_params));
            $stmt->bind_param($param_types, ...$category_filter_params);
            $stmt->execute();
            $users_summary = $stmt->get_result();
        } else {
            $users_summary = $conn->query($sql);
        }

        // Logic: For total counts, include role='system' but handle category filter properly
        // Community users are filtered by branch, but System users (bulk storage) are always included in totals
        $where_clause = "(((u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%'";
        if ($filter_category) {
            $where_clause .= " AND u.category = '$filter_category'";
        }
        if ($filter_classification) {
            $where_clause .= " AND u.classification = '$filter_classification'";
        }
        $where_clause .= ") OR (u.role = 'system'))";

        $sql = "SELECT 
                    COUNT(DISTINCT CASE WHEN (u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%' THEN u.id END) as total_users,
                    COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) as total_juz_completed,
                    FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) / 30) as total_qurans_completed,
                    COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN CONCAT(bt.user_id, '-', bt.book_id) END) as total_books_completed
                FROM users u
                LEFT JOIN quran_progress qp ON u.id = qp.user_id
                LEFT JOIN book_transcription bt ON u.id = bt.user_id
                WHERE $where_clause";

        $overall_stats = $conn->query($sql)->fetch_assoc();

        // Get category-wise dua counts with user category filter
        $where_conditions = [];
        $filter_params = [];
        if ($filter_category) {
            $where_conditions[] = "u.category = ?";
            $filter_params[] = $filter_category;
        }
        if ($filter_classification) {
            $where_conditions[] = "u.classification = ?";
            $filter_params[] = $filter_classification;
        }

        $sql_categories = "SELECT 
                            dm.category,
                            COALESCE(SUM(de.count_added), 0) as total_count
                        FROM duas_master dm
                        LEFT JOIN dua_entries de ON dm.id = de.dua_id
                        LEFT JOIN users u ON de.user_id = u.id
                        WHERE dm.is_active = 1" . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "") . "
                        GROUP BY dm.category";

        if (!empty($filter_params)) {
            $stmt = $conn->prepare($sql_categories);
            $param_types = str_repeat('s', count($filter_params));
            $stmt->bind_param($param_types, ...$filter_params);
            $stmt->execute();
            $category_result = $stmt->get_result();
        } else {
            $category_result = $conn->query($sql_categories);
        }
        $category_stats = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
        while ($row = $category_result->fetch_assoc()) {
            $category_stats[$row['category']] = $row['total_count'];
        }
        ?>

        <div class="stats-grid" style="gap: 0.75rem; margin-bottom: 1rem;">
            <div class="stat-card" style="padding: 0.75rem;">
                <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-users"></i> Users</h4>
                <div class="stat-value" style="font-size: 1.75rem;"><?php echo $overall_stats['total_users']; ?></div>
                <div class="stat-label" style="font-size: 0.75rem;">Active</div>
            </div>

            <div class="stat-card success" style="padding: 0.75rem;">
                <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-quran"></i> Qurans</h4>
                <div class="stat-value" style="font-size: 1.75rem;"><?php echo $overall_stats['total_qurans_completed']; ?></div>
                <div class="stat-label" style="font-size: 0.75rem;"><?php echo $overall_stats['total_juz_completed']; ?> Juz</div>
            </div>

            <div class="stat-card warning" style="padding: 0.75rem;">
                <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-hands-praying"></i> Duas</h4>
                <div class="stat-value" style="font-size: 1.75rem;"><?php echo $category_stats['dua']; ?></div>
                <div class="stat-label" style="font-size: 0.75rem;">Recited</div>
            </div>

            <div class="stat-card info" style="padding: 0.75rem;">
                <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-dharmachakra"></i> Tasbeeh</h4>
                <div class="stat-value" style="font-size: 1.75rem;"><?php echo $category_stats['tasbeeh']; ?></div>
                <div class="stat-label" style="font-size: 0.75rem;">Count</div>
            </div>

            <div class="stat-card purple" style="padding: 0.75rem;">
                <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-mosque"></i> Namaz</h4>
                <div class="stat-value" style="font-size: 1.75rem;"><?php echo $category_stats['namaz']; ?></div>
                <div class="stat-label" style="font-size: 0.75rem;">Count</div>
            </div>
<div class="stat-card danger" style="padding: 0.75rem;">
    <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem;"><i class="fas fa-book"></i> Kutub</h4>
    <div class="stat-value" style="font-size: 1.75rem;"><?php echo $overall_stats['total_books_completed']; ?></div>
    <div class="stat-label" style="font-size: 0.75rem;">Done</div>
</div>
</div>

        <div class="card" id="bulkAssignCard" style="border-left: 5px solid var(--accent-purple); position: sticky; top: 10px; z-index: 100; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <div class="card-header" style="background-color: #f5f3ff;">
                <h3 style="color: var(--accent-purple);"><i class="fas fa-layer-group"></i> Bulk Assign Amali (<span id="selectedCount">0</span> users selected)</h3>
            </div>
            <div style="padding: 1rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <?php
                $all_items = $conn->query("SELECT id, dua_name, category FROM duas_master WHERE is_active = 1 ORDER BY category, display_order");
                ?>
                <div id="bulkHint" style="width: 100%; color: #6366f1; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 500;">
                    <i class="fas fa-info-circle"></i> <span id="hintText">Please scroll down and select users from the list below to begin.</span>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Select Amali Item</label>
                    <select id="bulk_dua_id" class="form-control">
                        <option value="">-- Select Item --</option>
                        <?php while($item = $all_items->fetch_assoc()): ?>
                            <option value="<?php echo $item['id']; ?>">[<?php echo ucfirst($item['category']); ?>] <?php echo htmlspecialchars($item['dua_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="width: 100px; margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Count</label>
                    <input type="number" id="bulk_count" class="form-control" value="1" min="1">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-primary" onclick="submitBulkAmali()" id="bulkSubmitBtn" disabled>
                        <i class="fas fa-check-double"></i> Assign
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleVisibleSelection(true)" style="border: 1px solid var(--accent-purple); color: var(--accent-purple);">
                        <i class="fas fa-check-square"></i> Select All Visible
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearBulkSelection()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
        </div>        <!-- Overall Amali Progress -->
        <?php
        // Calculate overall progress
        $total_users = $overall_stats['total_users'];
        $target_qurans = $total_users * 4; // 4 Qurans per user
        $target_juz = $total_users * 120; // 120 Juz per user

        $quran_progress = $target_qurans > 0 ? round(($overall_stats['total_qurans_completed'] / $target_qurans) * 100, 2) : 0;
        $juz_progress = $target_juz > 0 ? round(($overall_stats['total_juz_completed'] / $target_juz) * 100, 2) : 0;
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Overall Amali Janib Progress</h3>
            </div>

            <!-- Quran Progress -->
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-quran"></i> Quran Recitation:
                        <?php echo $overall_stats['total_qurans_completed']; ?> / <?php echo $target_qurans; ?> Qurans
                    </span>
                    <span class="progress-label-value"><?php echo $quran_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $quran_progress; ?>%"></div>
                </div>
                <p style="margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                    <?php echo $overall_stats['total_juz_completed']; ?> / <?php echo $target_juz; ?> Juz completed (<?php echo $juz_progress; ?>%)
                </p>
            </div>

            <!-- Books Progress -->
            <?php
            // Get total books in progress and completed
            $sql_books = "SELECT 
                            COUNT(DISTINCT CASE WHEN status = 'completed' THEN CONCAT(user_id, '-', book_id) END) as total_completed,
                            COUNT(DISTINCT CASE WHEN status = 'selected' THEN CONCAT(user_id, '-', book_id) END) as total_in_progress
                          FROM book_transcription";
            $books_stats = $conn->query($sql_books)->fetch_assoc();
            $total_books_activity = $books_stats['total_completed'] + $books_stats['total_in_progress'];
            ?>

            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-book"></i> Istinsakh ul Kutub:
                        <?php echo $books_stats['total_completed']; ?> Completed,
                        <?php echo $books_stats['total_in_progress']; ?> In Progress
                    </span>
                    <span class="progress-label-value"><?php echo $total_books_activity; ?> Total</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Completed</div>
                        <div class="progress-bar" style="height: 8px;">
                            <div class="progress-fill" style="width: <?php echo $total_books_activity > 0 ? round(($books_stats['total_completed'] / $total_books_activity) * 100, 2) : 0; ?>%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">In Progress</div>
                        <div class="progress-bar" style="height: 8px;">
                            <div class="progress-fill" style="width: <?php echo $total_books_activity > 0 ? round(($books_stats['total_in_progress'] / $total_books_activity) * 100, 2) : 0; ?>%; background: linear-gradient(90deg, var(--secondary-500), var(--secondary-600));"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duas Progress by Category -->
            <?php
            // Get target counts by category
            $sql_targets = "SELECT category, SUM(target_count) as total_target 
                           FROM duas_master 
                           WHERE is_active = 1 
                           GROUP BY category";
            $targets_result = $conn->query($sql_targets);
            $category_targets = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
            while ($row = $targets_result->fetch_assoc()) {
                $category_targets[$row['category']] = $row['total_target'] * $total_users;
            }

            // Calculate progress for each category
            $dua_progress = $category_targets['dua'] > 0 ? round(($category_stats['dua'] / $category_targets['dua']) * 100, 2) : 0;
            $tasbeeh_progress = $category_targets['tasbeeh'] > 0 ? round(($category_stats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
            $namaz_progress = $category_targets['namaz'] > 0 ? round(($category_stats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;
            ?>

            <!-- Dua Progress -->
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-hands-praying"></i> Dua Recitation:
                        <?php echo number_format($category_stats['dua']); ?> / <?php echo number_format($category_targets['dua']); ?> Total
                    </span>
                    <span class="progress-label-value"><?php echo $dua_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $dua_progress; ?>%"></div>
                </div>
            </div>

            <!-- Tasbeeh Progress -->
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-dharmachakra"></i> Tasbeeh:
                        <?php echo number_format($category_stats['tasbeeh']); ?> / <?php echo number_format($category_targets['tasbeeh']); ?> Total
                    </span>
                    <span class="progress-label-value"><?php echo $tasbeeh_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $tasbeeh_progress; ?>%; background: linear-gradient(90deg, #f59e0b, #d97706);"></div>
                </div>
            </div>

            <!-- Namaz Progress -->
            <div class="progress-container">
                <div class="progress-label">
                    <span class="progress-label-text">
                        <i class="fas fa-mosque"></i> Namaz:
                        <?php echo number_format($category_stats['namaz']); ?> / <?php echo number_format($category_targets['namaz']); ?> Total
                    </span>
                    <span class="progress-label-value"><?php echo $namaz_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $namaz_progress; ?>%; background: linear-gradient(90deg, #8b5cf6, #7c3aed);"></div>
                </div>
            </div>
        </div>

        <!-- Search and Category Filter -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Options</h3>
            </div>
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="summary">
                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-search"></i> Search by Name/ITS (Instant Filter)</label>
                    <input type="text" id="instantSearch" name="search_name" class="form-control" placeholder="Type here to filter list instantly..." value="<?php echo htmlspecialchars($search_name); ?>" onkeyup="performInstantSearch()">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                    <?php if (!$is_category_coordinator): ?>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Filter by Jamea</label>
                            <select name="filter_category" class="form-control">
                                <option value="">All Jamea</option>
                                <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                                <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                                <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                                <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Jamea (Fixed)</label>
                            <div class="alert alert-info" style="padding: 0.5rem 0.75rem; margin-bottom: 0;">
                                <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($assigned_category); ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> Filter by Classification</label>
                        <select name="filter_classification" class="form-control">
                            <option value="">-- All Classifications --</option>
                            <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                            <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                            <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                            <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                        </select>
                    </div>
                </div>
                    <div class="form-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort_by" class="form-control" onchange="this.form.submit()">
                            <option value="">Name (A-Z)</option>
                            <option value="progress" <?php echo $sort_by === 'progress' ? 'selected' : ''; ?>>Overall Progress</option>
                            <option value="quran" <?php echo $sort_by === 'quran' ? 'selected' : ''; ?>>Qurans Completed</option>
                            <option value="books" <?php echo $sort_by === 'books' ? 'selected' : ''; ?>>Kutub Completed</option>
                        </select>
                    </div>
                <?php if ($sort_by): ?>
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-arrow-down-up-across-line"></i> Sort Order</label>
                        <select name="sort_order" class="form-control" onchange="this.form.submit()">
                            <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Highest to Lowest</option>
                            <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Lowest to Highest</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <?php if ($search_name || ($filter_category && !$is_category_coordinator) || $filter_classification): ?>
                        <a href="?report_type=summary" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> User-wise Amali Progress</h3>
            </div>
            
            <div class="report-wrapper">
                <!-- Desktop Table View -->
                <div class="table-container">
                    <?php if ($users_summary->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="selectAllUsers" onclick="toggleAllUsers(this)" style="cursor: pointer;">
                                    </th>
                                    <th>ITS</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Jamea</th>
                                    <th>Class.</th>
                                    <th>Progress</th>
                                    <th>Qurans</th>
                                    <th>Juz</th>
                                    <th>Duas</th>
                                    <th>Tasb.</th>
                                    <th>Namaz</th>
                                    <th>Kutub</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $users_array = [];
                                while ($user = $users_summary->fetch_assoc()) {
                                    $users_array[] = $user;
                                }

                                // Get target counts for calculations
                                $sql_targets = "SELECT category, SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 GROUP BY category";
                                $targets_result = $conn->query($sql_targets);
                                $category_targets = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                                while ($row = $targets_result->fetch_assoc()) {
                                    $category_targets[$row['category']] = $row['total_target'];
                                }

                                // Get user category data
                                $sql_user_categories = "SELECT u.id as user_id, dm.category, COALESCE(SUM(de.count_added), 0) as count
                                                        FROM users u CROSS JOIN duas_master dm
                                                        LEFT JOIN dua_entries de ON u.id = de.user_id AND dm.id = de.dua_id
                                                        WHERE dm.is_active = 1" . $category_filter_sql . $classification_filter_sql . "
                                                        GROUP BY u.id, dm.category";
                                
                                if (!empty($category_filter_params)) {
                                    $uc_stmt = $conn->prepare($sql_user_categories);
                                    $uc_stmt->bind_param(str_repeat('s', count($category_filter_params)), ...$category_filter_params);
                                    $uc_stmt->execute();
                                    $uc_result = $uc_stmt->get_result();
                                } else {
                                    $uc_result = $conn->query($sql_user_categories);
                                }
                                
                                $user_category_data = [];
                                while($row = $uc_result->fetch_assoc()) {
                                    $user_category_data[$row['user_id']][$row['category']] = $row['count'];
                                }

                                foreach ($users_array as $user):
                                    $user_cats = isset($user_category_data[$user['user_id']]) ? $user_category_data[$user['user_id']] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                                    $quran_pct = round(($user['completed_juz'] / 120) * 100, 2);
                                    $dua_pct = $category_targets['dua'] > 0 ? round(($user_cats['dua'] / $category_targets['dua']) * 100, 2) : 0;
                                    $tasbeeh_pct = $category_targets['tasbeeh'] > 0 ? round(($user_cats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
                                    $namaz_pct = $category_targets['namaz'] > 0 ? round(($user_cats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;
                                    $overall_progress = round(($quran_pct + $dua_pct + $tasbeeh_pct + $namaz_pct) / 4, 2);
                                ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <input type="checkbox" class="user-checkbox" value="<?php echo $user['user_id']; ?>" onchange="updateBulkUI()" style="cursor: pointer;">
                                        </td>
                                        <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                        <td><small><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></small></td>
                                        <td><?php echo htmlspecialchars($user['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['classification'] ?? '-'); ?></td>
                                        <td><strong><?php echo $overall_progress; ?>%</strong></td>
                                        <td><?php echo $user['completed_qurans']; ?></td>
                                        <td><?php echo $user['completed_juz']; ?></td>
                                        <td><?php echo $user_cats['dua']; ?></td>
                                        <td><?php echo $user_cats['tasbeeh']; ?></td>
                                        <td><?php echo $user_cats['namaz']; ?></td>
                                        <td><?php echo $user['books_completed']; ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center" style="padding: 2rem;">No users found.</p>
                    <?php endif; ?>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-report-cards" style="padding: 1rem;">
                    <?php foreach ($users_array as $user): 
                        $user_cats = isset($user_category_data[$user['user_id']]) ? $user_category_data[$user['user_id']] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                        $quran_pct = round(($user['completed_juz'] / 120) * 100, 2);
                        $dua_pct = $category_targets['dua'] > 0 ? round(($user_cats['dua'] / $category_targets['dua']) * 100, 2) : 0;
                        $tasbeeh_pct = $category_targets['tasbeeh'] > 0 ? round(($user_cats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
                        $namaz_pct = $category_targets['namaz'] > 0 ? round(($user_cats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;
                        $overall_progress = round(($quran_pct + $dua_pct + $tasbeeh_pct + $namaz_pct) / 4, 2);
                    ?>
                        <div class="report-card">
                            <div class="report-card-header" style="position: relative;">
                                <div style="position: absolute; left: 0; top: 0;">
                                    <input type="checkbox" class="user-checkbox" value="<?php echo $user['user_id']; ?>" onchange="updateBulkUI()" style="width: 20px; height: 20px;">
                                </div>
                                <div style="margin-left: 30px;">
                                    <div class="report-card-title"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="report-card-subtitle">ITS: <?php echo htmlspecialchars($user['its_number']); ?> | <?php echo htmlspecialchars($user['category'] ?: 'N/A'); ?></div>
                                </div>
                                <div class="badge badge-primary"><?php echo $overall_progress; ?>%</div>
                            </div>
                            <div class="report-card-grid">
                                <div class="report-data-item">
                                    <span class="report-data-label">Quran</span>
                                    <span class="report-data-value"><?php echo $user['completed_qurans']; ?> (<?php echo $user['completed_juz']; ?> Juz)</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Duas</span>
                                    <span class="report-data-value"><?php echo $user_cats['dua']; ?> recited</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Tasbeeh</span>
                                    <span class="report-data-value"><?php echo $user_cats['tasbeeh']; ?> count</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Kutub</span>
                                    <span class="report-data-value"><?php echo $user['books_completed']; ?> done</span>
                                </div>
                                <div class="report-card-full">
                                    <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary w-100" style="padding: 0.75rem;">
                                        <i class="fas fa-user-edit"></i> View & Edit Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'quran'): ?>
        <!-- Quran Report -->
        <?php
        $sql = "SELECT u.id, u.name, u.its_number, u.category, u.classification,
                    qp.quran_number,
                    COUNT(CASE WHEN qp.is_completed = 1 THEN 1 END) as completed_juz_in_quran,
                    ROUND((COUNT(CASE WHEN qp.is_completed = 1 THEN 1 END) / 30) * 100, 2) as quran_percentage,
                    CASE 
                        WHEN COUNT(CASE WHEN qp.is_completed = 1 THEN 1 END) = 30 THEN 'Completed'
                        ELSE 'In Progress'
                    END as status
                FROM users u
                LEFT JOIN quran_progress qp ON u.id = qp.user_id
                WHERE u.role = 'user' OR u.role = 'admin'"; // Include admins

        $params = [];
        $types = '';

        if ($filter_category) {
            $sql .= " AND u.category = ?";
            $params[] = $filter_category;
            $types .= 's';
        }

        if ($filter_classification) {
            $sql .= " AND u.classification = ?";
            $params[] = $filter_classification;
            $types .= 's';
        }

        if ($search_name) {
            $sql .= " AND (u.name LIKE ? OR u.its_number LIKE ?)";
            $search_param = "%$search_name%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }

        $sql .= " GROUP BY u.id, u.name, u.its_number, u.category, u.classification, qp.quran_number
                  HAVING qp.quran_number IS NOT NULL
                  ORDER BY u.name, qp.quran_number";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $quran_report = $stmt->get_result();
        $quran_array = [];
        while ($row = $quran_report->fetch_assoc()) { $quran_array[] = $row; }
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Options</h3>
            </div>
            <!-- ... [Form remains the same] ... -->
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="quran">
                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-search"></i> Search by Name/ITS (Instant Filter)</label>
                    <input type="text" id="instantSearch" name="search_name" class="form-control" placeholder="Type here to filter list instantly..." value="<?php echo htmlspecialchars($search_name); ?>" onkeyup="performInstantSearch()">
                </div>
                <?php if (!$is_category_coordinator): ?>
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-map-marker-alt"></i> Filter by Jamea</label>
                        <select name="filter_category" class="form-control">
                            <option value="">All Jamea</option>
                            <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                            <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                            <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                            <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            <option value="Muntasib" <?php echo $filter_category === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-map-marker-alt"></i> Jamea (Fixed)</label>
                        <div class="alert alert-info" style="padding: 0.5rem 0.75rem; margin-bottom: 0;">
                            <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($assigned_category); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-tags"></i> Filter by Classification</label>
                    <select name="filter_classification" class="form-control">
                        <option value="">-- All Classifications --</option>
                        <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                        <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                        <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                        <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                    </select>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <?php if ($search_name || ($filter_category && !$is_category_coordinator) || $filter_classification): ?>
                        <a href="?report_type=quran" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-quran"></i> Quran Completion Report</h3>
            </div>
            <div class="report-wrapper">
                <div class="table-container">
                    <?php if (!empty($quran_array)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ITS Number</th>
                                    <th>Name</th>
                                    <th>Jamea</th>
                                    <th>Classification</th>
                                    <th>Quran #</th>
                                    <th>Completed Juz</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quran_array as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['its_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['classification'] ?? '-'); ?></td>
                                        <td><strong>Quran <?php echo $row['quran_number']; ?></strong></td>
                                        <td><?php echo $row['completed_juz_in_quran']; ?> / 30</td>
                                        <td><strong><?php echo $row['quran_percentage']; ?>%</strong></td>
                                        <td><span class="badge badge-<?php echo $row['status'] === 'Completed' ? 'success' : 'warning'; ?>"><?php echo $row['status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center" style="padding: 2rem;">No progress found.</p>
                    <?php endif; ?>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-report-cards" style="padding: 1rem;">
                    <?php foreach ($quran_array as $row): ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="report-card-subtitle">Quran #<?php echo $row['quran_number']; ?> | ITS: <?php echo htmlspecialchars($row['its_number']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $row['status'] === 'Completed' ? 'success' : 'warning'; ?>"><?php echo $row['status']; ?></span>
                            </div>
                            <div class="report-card-grid">
                                <div class="report-data-item">
                                    <span class="report-data-label">Juz Completed</span>
                                    <span class="report-data-value"><?php echo $row['completed_juz_in_quran']; ?> / 30</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Percentage</span>
                                    <span class="report-data-value"><?php echo $row['quran_percentage']; ?>%</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Jamea</span>
                                    <span class="report-data-value"><?php echo htmlspecialchars($row['category'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Class</span>
                                    <span class="report-data-value"><?php echo htmlspecialchars($row['classification'] ?: 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'dua'): ?>
        <!-- Dua Report -->
        <?php
        // Get all duas for columns
        $duas_list = $conn->query("SELECT id, dua_name, target_count FROM duas_master WHERE is_active = 1 ORDER BY display_order");
        $duas = [];
        while ($dua = $duas_list->fetch_assoc()) { $duas[] = $dua; }

        $sql = "SELECT u.id as user_id, u.name, u.its_number, u.category, u.classification,
                    dm.id as dua_id, dm.dua_name, dm.target_count,
                    COALESCE(SUM(de.count_added), 0) as total_completed
                FROM users u
                CROSS JOIN duas_master dm
                LEFT JOIN dua_entries de ON u.id = de.user_id AND dm.id = de.dua_id
                WHERE dm.is_active = 1 AND (u.role = 'user' OR u.role = 'admin')";

        $params = []; $types = '';
        if ($filter_category) { $sql .= " AND u.category = ?"; $params[] = $filter_category; $types .= 's'; }
        if ($filter_classification) { $sql .= " AND u.classification = ?"; $params[] = $filter_classification; $types .= 's'; }
        if ($search_name) { $sql .= " AND (u.name LIKE ? OR u.its_number LIKE ?)"; $search_param = "%$search_name%"; $params[] = $search_param; $params[] = $search_param; $types .= 'ss'; }

        $sql .= " GROUP BY u.id, u.name, u.its_number, u.category, u.classification, dm.id, dm.dua_name, dm.target_count
                  ORDER BY u.name, dm.display_order";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $result = $stmt->get_result();

        $user_dua_data = [];
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            if (!isset($user_dua_data[$user_id])) {
                $user_dua_data[$user_id] = [
                    'its_number' => $row['its_number'], 'name' => $row['name'],
                    'category' => $row['category'], 'classification' => $row['classification'], 'duas' => []
                ];
            }
            $user_dua_data[$user_id]['duas'][$row['dua_id']] = $row['total_completed'];
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Options</h3>
            </div>
            <!-- ... [Filter Form] ... -->
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="dua">
                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-search"></i> Search by Name/ITS (Instant Filter)</label>
                    <input type="text" id="instantSearch" name="search_name" class="form-control" placeholder="Type here to filter list instantly..." value="<?php echo htmlspecialchars($search_name); ?>" onkeyup="performInstantSearch()">
                </div>
                <?php if (!$is_category_coordinator): ?>
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-map-marker-alt"></i> Filter by Jamea</label>
                        <select name="filter_category" class="form-control">
                            <option value="">All Jamea</option>
                            <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                            <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                            <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                            <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            <option value="Muntasib" <?php echo $filter_category === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-map-marker-alt"></i> Jamea (Fixed)</label>
                        <div class="alert alert-info" style="padding: 0.5rem 0.75rem; margin-bottom: 0;">
                            <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($assigned_category); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-tags"></i> Filter by Classification</label>
                    <select name="filter_classification" class="form-control">
                        <option value="">-- All Classifications --</option>
                        <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                        <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                        <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                        <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                    </select>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <?php if ($search_name || ($filter_category && !$is_category_coordinator) || $filter_classification): ?>
                        <a href="?report_type=dua" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hands-praying"></i> Dua Recitation Report</h3>
            </div>
            <div class="report-wrapper">
                <div class="table-container">
                    <?php if (!empty($user_dua_data)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ITS Number</th>
                                    <th>Name</th>
                                    <th>Jamea</th>
                                    <th>Classification</th>
                                     <?php foreach ($duas as $dua): 
                                         $clean_dua_name = preg_replace('/\s*\([^)]*\)\s*$/', '', $dua['dua_name']);
                                     ?>
                                        <th><?php echo htmlspecialchars($clean_dua_name); ?><br><small>(Target: <?php echo $dua['target_count']; ?>)</small></th>
                                     <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_dua_data as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['classification'] ?? '-'); ?></td>
                                        <?php foreach ($duas as $dua): ?>
                                            <td><strong><?php echo isset($user['duas'][$dua['id']]) ? $user['duas'][$dua['id']] : 0; ?></strong></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center" style="padding: 2rem;">No users found.</p>
                    <?php endif; ?>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-report-cards" style="padding: 1rem;">
                    <?php foreach ($user_dua_data as $user): ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="report-card-subtitle">ITS: <?php echo htmlspecialchars($user['its_number']); ?> | <?php echo htmlspecialchars($user['category'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                            <div class="report-card-grid" style="grid-template-columns: 1fr;">
                                <?php foreach ($duas as $dua): 
                                    $count = isset($user['duas'][$dua['id']]) ? $user['duas'][$dua['id']] : 0;
                                    if($count > 0): // Only show active progress on mobile cards to save space
                                ?>
                                    <div class="report-data-item" style="flex-direction: row; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding: 4px 0;">
                                        <span class="report-data-label" style="margin: 0;"><?php echo htmlspecialchars($dua['dua_name']); ?></span>
                                        <span class="report-data-value"><?php echo $count; ?> / <?php echo $dua['target_count']; ?></span>
                                    </div>
                                <?php endif; endforeach; ?>
                                <?php if(empty(array_filter($user['duas']))): ?>
                                    <div class="text-center text-muted" style="font-size: 0.8rem; padding: 1rem;">No recitation data recorded yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'books'): ?>
        <!-- Books Report -->
        <?php
        $books_list = $conn->query("SELECT id, book_name FROM books_master WHERE is_active = 1 ORDER BY display_order");
        $sql = "SELECT u.id, u.name, u.its_number, u.category, u.classification,
                    bm.id as book_id, bm.book_name, bm.author, bm.total_pages,
                    bt.status, bt.pages_completed, bt.started_date, bt.completed_date,
                    CASE WHEN bm.total_pages > 0 THEN ROUND((bt.pages_completed / bm.total_pages) * 100, 2) ELSE 0 END as progress_percentage
                FROM users u
                LEFT JOIN book_transcription bt ON u.id = bt.user_id
                LEFT JOIN books_master bm ON bt.book_id = bm.id
                WHERE bt.status IN ('selected', 'completed') AND (u.role = 'user' OR u.role = 'admin')";

        $params = []; $types = '';
        if ($filter_category) { $sql .= " AND u.category = ?"; $params[] = $filter_category; $types .= 's'; }
        if ($filter_classification) { $sql .= " AND u.classification = ?"; $params[] = $filter_classification; $types .= 's'; }
        if ($search_name) { $sql .= " AND (u.name LIKE ? OR u.its_number LIKE ?)"; $search_param = "%$search_name%"; $params[] = $search_param; $params[] = $search_param; $types .= 'ss'; }
        if ($filter_book) { $sql .= " AND bt.book_id = ?"; $params[] = $filter_book; $types .= 'i'; }
        if ($filter_status) { $sql .= " AND bt.status = ?"; $params[] = $filter_status; $types .= 's'; }
        
        $sql .= " ORDER BY u.name, bm.book_name";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $book_report = $stmt->get_result();
        $book_array = [];
        while ($row = $book_report->fetch_assoc()) { $book_array[] = $row; }
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Books Report</h3>
            </div>
            <!-- ... [Filter Form] ... -->
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="books">
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Search by Name/ITS</label>
                    <input type="text" name="search_name" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
                <?php if (!$is_category_coordinator): ?>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Filter by Jamea</label>
                        <select name="filter_category" class="form-control">
                            <option value="">All Jamea</option>
                            <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                            <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                            <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                            <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            <option value="Muntasib" <?php echo $filter_category === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Jamea (Fixed)</label>
                        <div class="alert alert-info" style="padding: 0.5rem 0.75rem; margin-bottom: 0;">
                            <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($assigned_category); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-tags"></i> Filter by Classification</label>
                    <select name="filter_classification" class="form-control">
                        <option value="">-- All Classifications --</option>
                        <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                        <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                        <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                        <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Filter by Book</label>
                    <select name="filter_book" class="form-control">
                        <option value="">All Books</option>
                        <?php
                        $books_list->data_seek(0); // Reset pointer
                        while ($book = $books_list->fetch_assoc()): ?>
                            <option value="<?php echo $book['id']; ?>" <?php echo $filter_book == $book['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($book['book_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-flag"></i> Filter by Status</label>
                    <select name="filter_status" class="form-control">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="selected" <?php echo $filter_status === 'selected' ? 'selected' : ''; ?>>In Progress</option>
                    </select>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="?report_type=books" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Book Transcription Report</h3>
            </div>
            <div class="report-wrapper">
                <div class="table-container">
                    <?php if (!empty($book_array)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ITS Number</th>
                                    <th>Name</th>
                                    <th>Jamea</th>
                                    <th>Classification</th>
                                    <th>Book Name</th>
                                    <th>Pages</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($book_array as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['its_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['classification'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                                        <td><?php echo $row['pages_completed']; ?> / <?php echo $row['total_pages']; ?></td>
                                        <td><strong><?php echo $row['progress_percentage']; ?>%</strong></td>
                                        <td><span class="badge badge-<?php echo $row['status'] === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center" style="padding: 2rem;">No data found.</p>
                    <?php endif; ?>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-report-cards" style="padding: 1rem;">
                    <?php foreach ($book_array as $row): ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="report-card-subtitle"><?php echo htmlspecialchars($row['book_name']); ?> | ITS: <?php echo htmlspecialchars($row['its_number']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $row['status'] === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($row['status']); ?></span>
                            </div>
                            <div class="report-card-grid">
                                <div class="report-data-item">
                                    <span class="report-data-label">Pages Done</span>
                                    <span class="report-data-value"><?php echo $row['pages_completed']; ?> / <?php echo $row['total_pages']; ?></span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Percentage</span>
                                    <span class="report-data-value"><?php echo $row['progress_percentage']; ?>%</span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Started Date</span>
                                    <span class="report-data-value"><?php echo $row['started_date'] ? date('M d, Y', strtotime($row['started_date'])) : '-'; ?></span>
                                </div>
                                <div class="report-data-item">
                                    <span class="report-data-label">Jamea</span>
                                    <span class="report-data-value"><?php echo htmlspecialchars($row['category'] ?: 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Export Options -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-download"></i> Export Options</h3>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
</div>

<script>
    function performInstantSearch() {
        const input = document.getElementById('instantSearch');
        const filter = input.value.toLowerCase();
        
        // Filter Table Rows
        const tableRows = document.querySelectorAll('.table-container tbody tr');
        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? "" : "none";
        });

        // Filter Mobile Cards
        const mobileCards = document.querySelectorAll('.report-card');
        mobileCards.forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? "" : "none";
        });
    }

    function toggleVisibleSelection(check) {
        // Only select checkboxes that are currently visible (not hidden by search)
        const allCheckboxes = document.querySelectorAll('.user-checkbox');
        allCheckboxes.forEach(cb => {
            // Find parent row or card
            const container = cb.closest('tr') || cb.closest('.report-card');
            if (container && container.style.display !== 'none') {
                cb.checked = check;
            }
        });
        updateBulkUI();
    }

    function toggleAllUsers(source) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = source.checked;
        });
        updateBulkUI();
    }

    function updateBulkUI() {
        const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
        const uniqueIds = new Set();
        selectedCheckboxes.forEach(cb => uniqueIds.add(cb.value));
        
        const countSpan = document.getElementById('selectedCount');
        const submitBtn = document.getElementById('bulkSubmitBtn');
        const hintText = document.getElementById('hintText');
        
        const count = uniqueIds.size;
        
        if (count > 0) {
            submitBtn.disabled = false;
            countSpan.innerText = count;
            hintText.innerHTML = "Great! Now select an Amali item and enter the count to assign to these Mumineen.";
            hintText.parentElement.style.color = "var(--accent-purple)";
        } else {
            submitBtn.disabled = true;
            countSpan.innerText = "0";
            hintText.innerHTML = "Please scroll down and select users from the list below to begin.";
            hintText.parentElement.style.color = "#6366f1";
            const selectAll = document.getElementById('selectAllUsers');
            if (selectAll) selectAll.checked = false;
        }
    }

    // Synchronize checkboxes between Table and Card views
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('user-checkbox')) {
            const userId = e.target.value;
            const isChecked = e.target.checked;
            document.querySelectorAll(`.user-checkbox[value="${userId}"]`).forEach(cb => {
                cb.checked = isChecked;
            });
            updateBulkUI();
        }
    });

    function clearBulkSelection() {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllUsers').checked = false;
        updateBulkUI();
    }

    async function submitBulkAmali() {
        const duaId = document.getElementById('bulk_dua_id').value;
        const count = document.getElementById('bulk_count').value;
        const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
        
        if (!duaId) return showToast('Please select an Amali item.', 'error');
        if (!count || count <= 0) return showToast('Please enter a valid count.', 'error');
        
        const userIds = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        const btn = document.getElementById('bulkSubmitBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

        try {
            const response = await fetch('ajax_bulk_amali.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_ids: userIds,
                    dua_id: duaId,
                    count: count
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message || 'An error occurred', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (error) {
            showToast('Failed to connect to the server.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>