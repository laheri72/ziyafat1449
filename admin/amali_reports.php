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
    /* Compact Report Styling */
    .card {
        margin-bottom: 1rem !important;
    }

    .card-header {
        padding: 0.75rem 1rem !important;
    }

    .card-header h3 {
        font-size: 1rem !important;
        margin: 0 !important;
    }

    .progress-container {
        padding: 0.75rem 1rem !important;
    }

    .progress-label {
        margin-bottom: 0.5rem !important;
    }

    .progress-label-text,
    .progress-label-value {
        font-size: 0.85rem !important;
    }

    .progress-bar {
        height: 14px !important;
    }

    .progress-fill {
        height: 14px !important;
        font-size: 0.75rem !important;
        line-height: 14px !important;
    }

    .form-group {
        margin-bottom: 0.75rem !important;
    }

    .form-group label {
        font-size: 0.85rem !important;
    }

    .action-buttons {
        padding: 0.75rem 1rem !important;
    }

    .btn {
        padding: 0.5rem 1rem !important;
        font-size: 0.85rem !important;
    }

    table {
        font-size: 0.85rem !important;
    }

    table thead tr {
        font-size: 0.8rem !important;
    }

    table th,
    table td {
        padding: 0.4rem 0.5rem !important;
    }

    table tbody tr {
        font-size: 0.8rem !important;
    }

    .badge {
        font-size: 0.7rem !important;
        padding: 0.25rem 0.5rem !important;
    }

    .alert {
        font-size: 0.85rem !important;
        padding: 0.75rem !important;
    }
</style>

<div class="container">
    <h1 class="mb-3" style="font-size: 1.5rem;"><i class="fas fa-chart-bar"></i> Amali Janib Reports</h1>

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
                WHERE 1=1" . $category_filter_sql . $classification_filter_sql . "
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

        // Get overall stats with category filter
        $sql = "SELECT 
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) as total_juz_completed,
                    FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.user_id, '-', qp.quran_number, '-', qp.juz_number) END) / 30) as total_qurans_completed,
                    COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN CONCAT(bt.user_id, '-', bt.book_id) END) as total_books_completed
                FROM users u
                LEFT JOIN quran_progress qp ON u.id = qp.user_id
                LEFT JOIN book_transcription bt ON u.id = bt.user_id
                WHERE 1=1" . $category_filter_sql . $classification_filter_sql;

        if (!empty($category_filter_params)) {
            $stmt = $conn->prepare($sql);
            $param_types = str_repeat('s', count($category_filter_params));
            $stmt->bind_param($param_types, ...$category_filter_params);
            $stmt->execute();
            $overall_stats = $stmt->get_result()->fetch_assoc();
        } else {
            $overall_stats = $conn->query($sql)->fetch_assoc();
        }

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

        <!-- Overall Amali Progress -->
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
                    <label><i class="fas fa-search"></i> Search by Name/ITS</label>
                    <input type="text" name="search_name" class="form-control" placeholder="Search by name or ITS number..." value="<?php echo htmlspecialchars($search_name); ?>">
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
                        <div class="form-group">
                            <label><i class="fas fa-tags"></i> Filter by Category</label>
                            <select name="filter_classification" class="form-control">
                                <option value="">-- Select Classification --</option>
                                <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                                <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                                <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                                <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                            </select>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Showing from <strong><?php echo htmlspecialchars($assigned_category); ?></strong> only.
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort_by" class="form-control" onchange="this.form.submit()">
                            <option value="">Name (A-Z)</option>
                            <option value="progress" <?php echo $sort_by === 'progress' ? 'selected' : ''; ?>>Overall Progress</option>
                            <option value="quran" <?php echo $sort_by === 'quran' ? 'selected' : ''; ?>>Qurans Completed</option>
                            <option value="books" <?php echo $sort_by === 'books' ? 'selected' : ''; ?>>Kutub Completed</option>
                        </select>
                    </div>
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
            <div class="table-container">
                <?php if ($users_summary->num_rows > 0): ?>
                    <table style="font-size: 0.85rem;">
                        <thead>
                            <tr style="font-size: 0.8rem;">
                                <th style="padding: 0.5rem;">ITS</th>
                                <th style="padding: 0.5rem;">Name</th>
                                <th style="padding: 0.5rem;">Contact</th>
                                <th style="padding: 0.5rem;">Jamea</th>
                                <th style="padding: 0.5rem;">Class.</th>
                                <th style="padding: 0.5rem; min-width: 120px;">Progress</th>
                                <th style="padding: 0.5rem;">Qurans</th>
                                <th style="padding: 0.5rem;">Juz</th>
                                <th style="padding: 0.5rem;">Duas</th>
                                <th style="padding: 0.5rem;">Tasb.</th>
                                <th style="padding: 0.5rem;">Namaz</th>
                                <th style="padding: 0.5rem;">Kutub</th>
                                <th style="padding: 0.5rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get target counts for each category
                            $sql_targets = "SELECT category, SUM(target_count) as total_target 
                                           FROM duas_master 
                                           WHERE is_active = 1 
                                           GROUP BY category";
                            $targets_result = $conn->query($sql_targets);
                            $category_targets = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                            while ($row = $targets_result->fetch_assoc()) {
                                $category_targets[$row['category']] = $row['total_target'];
                            }

                            // Get category-wise counts for each user with category filter
                            $sql_user_categories = "SELECT 
                                                    u.id as user_id,
                                                    dm.category,
                                                    COALESCE(SUM(de.count_added), 0) as count
                                                FROM users u
                                                CROSS JOIN duas_master dm
                                                LEFT JOIN dua_entries de ON u.id = de.user_id AND dm.id = de.dua_id
                                                WHERE dm.is_active = 1" . $category_filter_sql . $classification_filter_sql . "
                                                GROUP BY u.id, dm.category";

                            if (!empty($category_filter_params)) {
                                $stmt = $conn->prepare($sql_user_categories);
                                $param_types = str_repeat('s', count($category_filter_params));
                                $stmt->bind_param($param_types, ...$category_filter_params);
                                $stmt->execute();
                                $user_cat_result = $stmt->get_result();
                            } else {
                                $user_cat_result = $conn->query($sql_user_categories);
                            }
                            $user_category_data = [];
                            while ($row = $user_cat_result->fetch_assoc()) {
                                if (!isset($user_category_data[$row['user_id']])) {
                                    $user_category_data[$row['user_id']] = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                                }
                                $user_category_data[$row['user_id']][$row['category']] = $row['count'];
                            }

                            // Store all users in an array for potential sorting by overall progress
                            $users_array = [];
                            while ($user = $users_summary->fetch_assoc()) {
                                $users_array[] = $user;
                            }

                            // If sorting by progress, calculate overall progress and sort
                            if ($sort_by === 'progress') {
                                // Calculate overall progress for each user
                                foreach ($users_array as $key => $user) {
                                    $user_cats = isset($user_category_data[$user['user_id']]) ? $user_category_data[$user['user_id']] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                                    
                                    // Calculate individual progress metrics
                                    $quran_progress = round(($user['completed_juz'] / 120) * 100, 2);
                                    
                                    // Calculate dua category progress
                                    $dua_progress = $category_targets['dua'] > 0 ? round(($user_cats['dua'] / $category_targets['dua']) * 100, 2) : 0;
                                    $tasbeeh_progress = $category_targets['tasbeeh'] > 0 ? round(($user_cats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
                                    $namaz_progress = $category_targets['namaz'] > 0 ? round(($user_cats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;
                                    
                                    // Calculate overall progress
                                    $users_array[$key]['calculated_progress'] = round(($quran_progress + $dua_progress + $tasbeeh_progress + $namaz_progress) / 4, 2);
                                }
                                
                                // Sort by calculated progress
                                usort($users_array, function($a, $b) use ($sort_order) {
                                    if ($sort_order === 'asc') {
                                        return $a['calculated_progress'] <=> $b['calculated_progress'];
                                    } else {
                                        return $b['calculated_progress'] <=> $a['calculated_progress'];
                                    }
                                });
                            }

                            foreach ($users_array as $user):
                                if ($search_name && stripos($user['name'], $search_name) === false && stripos($user['its_number'], $search_name) === false) {
                                    continue;
                                }
                                $user_cats = isset($user_category_data[$user['user_id']]) ? $user_category_data[$user['user_id']] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
                            ?>
                                <?php
                                // Calculate individual progress metrics
                                $quran_progress = round(($user['completed_juz'] / 120) * 100, 2);
                                $juz_progress = round(($user['completed_juz'] / 120) * 100, 2);

                                // Calculate dua category progress
                                $dua_progress = $category_targets['dua'] > 0 ? round(($user_cats['dua'] / $category_targets['dua']) * 100, 2) : 0;
                                $tasbeeh_progress = $category_targets['tasbeeh'] > 0 ? round(($user_cats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
                                $namaz_progress = $category_targets['namaz'] > 0 ? round(($user_cats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;

                                // Calculate overall progress (average of Quran, Dua, Tasbeeh, Namaz - excluding Kutub as requested)
                                $overall_progress = round(($quran_progress + $dua_progress + $tasbeeh_progress + $namaz_progress) / 4, 2);
                                ?>
                                <tr style="font-size: 0.8rem;">
                                    <td style="padding: 0.4rem;"><?php echo htmlspecialchars($user['its_number']); ?></td>
                                    <td style="padding: 0.4rem; white-space: nowrap;"><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                    <td style="padding: 0.4rem; font-size: 0.75rem; line-height: 1.3;">
                                        <?php if ($user['email']): ?>
                                            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;" title="<?php echo htmlspecialchars($user['email']); ?>">
                                                <i class="fas fa-envelope" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($user['phone_number']): ?>
                                            <div style="white-space: nowrap;">
                                                <i class="fas fa-phone" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($user['phone_number']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$user['email'] && !$user['phone_number']): ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.4rem;"><?php echo htmlspecialchars($user['category'] ?? '-'); ?></td>
                                    <td style="padding: 0.4rem;"><?php echo htmlspecialchars($user['classification'] ?? '-'); ?></td>
                                    <td style="padding: 0.4rem;">
                                        <div style="min-width: 100px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; font-size: 0.75rem;">
                                                <span style="font-weight: bold;"><?php echo $overall_progress; ?>%</span>
                                            </div>
                                            <div class="progress-bar" style="height: 14px;">
                                                <div class="progress-fill" style="width: <?php echo min($overall_progress, 100); ?>%; height: 14px; font-size: 9px; line-height: 14px;">
                                                    <?php if ($overall_progress >= 25): ?><?php echo $overall_progress; ?>%<?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 0.4rem;">
                                        <div style="min-width: 70px;">
                                            <div style="font-size: 0.75rem; margin-bottom: 1px;">
                                                <strong><?php echo $user['completed_qurans']; ?></strong>/4
                                            </div>
                                            <div class="progress-bar" style="height: 6px;">
                                                <div class="progress-fill" style="width: <?php echo $quran_progress; ?>%; height: 6px;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 0.4rem;">
                                        <div style="min-width: 70px;">
                                            <div style="font-size: 0.75rem; margin-bottom: 1px;">
                                                <?php echo $user['completed_juz']; ?>/120
                                            </div>
                                            <div class="progress-bar" style="height: 6px;">
                                                <div class="progress-fill" style="width: <?php echo $juz_progress; ?>%; height: 6px;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 0.4rem; text-align: center;"><strong><?php echo $user_cats['dua']; ?></strong></td>
                                    <td style="padding: 0.4rem; text-align: center;"><strong><?php echo $user_cats['tasbeeh']; ?></strong></td>
                                    <td style="padding: 0.4rem; text-align: center;"><strong><?php echo $user_cats['namaz']; ?></strong></td>
                                    <td style="padding: 0.4rem; text-align: center; white-space: nowrap;">
                                        <strong><?php echo $user['books_completed']; ?></strong>/<?php echo $user['books_in_progress']; ?>
                                    </td>
                                    <td style="padding: 0.4rem; text-align: center;">
                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary" title="Edit User" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No users found.</p>
                <?php endif; ?>
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
                WHERE 1=1";

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
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Options</h3>
            </div>
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="quran">
                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-search"></i> Search by Name/ITS</label>
                    <input type="text" name="search_name" class="form-control" placeholder="Search by name or ITS number..." value="<?php echo htmlspecialchars($search_name); ?>">
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
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-tags"></i> Filter by Category</label>
                        <select name="filter_classification" class="form-control">
                            <option value="">-- Select Classification --</option>
                            <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                            <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                            <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                            <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                        </select>
                    </div>

                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Showing users from <strong><?php echo htmlspecialchars($assigned_category); ?></strong> category only.
                    </div>
                <?php endif; ?>
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
            <div class="table-container">
                <?php if ($quran_report->num_rows > 0): ?>
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
                            <?php while ($row = $quran_report->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['its_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['classification'] ?? '-'); ?></td>
                                    <td><strong>Quran <?php echo $row['quran_number']; ?></strong></td>
                                    <td><?php echo $row['completed_juz_in_quran']; ?> / 30</td>
                                    <td>
                                        <div class="progress-bar" style="height: 20px;">
                                            <div class="progress-fill" style="width: <?php echo $row['quran_percentage']; ?>%; height: 20px; font-size: 12px;">
                                                <?php echo $row['quran_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'Completed'): ?>
                                            <span class="badge badge-success"><?php echo $row['status']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><?php echo $row['status']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No Quran progress found.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'dua'): ?>
        <!-- Dua Report -->
        <?php
        // Get all duas for columns
        $duas_list = $conn->query("SELECT id, dua_name, target_count FROM duas_master WHERE is_active = 1 ORDER BY display_order");
        $duas = [];
        while ($dua = $duas_list->fetch_assoc()) {
            $duas[] = $dua;
        }

        // Get user dua data grouped by user with category filter
        $sql = "SELECT 
                    u.id as user_id,
                    u.name,
                    u.its_number,
                    u.category,
                    u.classification,
                    dm.id as dua_id,
                    dm.dua_name,
                    dm.target_count,
                    COALESCE(SUM(de.count_added), 0) as total_completed
                FROM users u
                CROSS JOIN duas_master dm
                LEFT JOIN dua_entries de ON u.id = de.user_id AND dm.id = de.dua_id
                WHERE dm.is_active = 1";

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

        $sql .= " GROUP BY u.id, u.name, u.its_number, u.category, u.classification, dm.id, dm.dua_name, dm.target_count
                  ORDER BY u.name, dm.display_order";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Organize data by user
        $user_dua_data = [];
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            if (!isset($user_dua_data[$user_id])) {
                $user_dua_data[$user_id] = [
                    'its_number' => $row['its_number'],
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'classification' => $row['classification'],
                    'duas' => []
                ];
            }
            $user_dua_data[$user_id]['duas'][$row['dua_id']] = $row['total_completed'];
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Options</h3>
            </div>
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="dua">
                <div class="form-group" style="margin-bottom: var(--spacing-md);">
                    <label><i class="fas fa-search"></i> Search by Name/ITS</label>
                    <input type="text" name="search_name" class="form-control" placeholder="Search by name or ITS number..." value="<?php echo htmlspecialchars($search_name); ?>">
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
                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label><i class="fas fa-tags"></i> Filter by Category</label>
                        <select name="filter_classification" class="form-control">
                            <option value="">-- Select Classification --</option>
                            <option value="Talabat" <?php echo ($filter_classification == 'Talabat') ? 'selected' : ''; ?>>Talabat</option>
                            <option value="Taalebaat" <?php echo ($filter_classification == 'Taalebaat') ? 'selected' : ''; ?>>Taalebaat</option>
                            <option value="Muntasebeen" <?php echo ($filter_classification == 'Muntasebeen') ? 'selected' : ''; ?>>Muntasebeen</option>
                            <option value="Muntasebaat" <?php echo ($filter_classification == 'Muntasebaat') ? 'selected' : ''; ?>>Muntasebaat</option>
                        </select>
                    </div>

                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Showing users from <strong><?php echo htmlspecialchars($assigned_category); ?></strong> category only.
                    </div>
                <?php endif; ?>
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
                                     // Clean dua name by removing suffix like "(100 Dana ni Tasbeeh = 1 Time"
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
                                        <td>
                                            <strong><?php echo isset($user['duas'][$dua['id']]) ? $user['duas'][$dua['id']] : 0; ?></strong>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No users found.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'books'): ?>
        <!-- Books Report -->
        <?php
        // Get all books for filter
        $books_list = $conn->query("SELECT id, book_name FROM books_master WHERE is_active = 1 ORDER BY display_order");

        // Build books report query with category filter
        $sql = "SELECT u.id, u.name, u.its_number, u.category, u.classification,
                    bm.id as book_id, bm.book_name, bm.author, bm.total_pages,
                    bt.status, bt.pages_completed, bt.started_date, bt.completed_date,
                    CASE 
                        WHEN bm.total_pages > 0 THEN ROUND((bt.pages_completed / bm.total_pages) * 100, 2)
                        ELSE 0
                    END as progress_percentage
                FROM users u
                LEFT JOIN book_transcription bt ON u.id = bt.user_id
                LEFT JOIN books_master bm ON bt.book_id = bm.id
                WHERE bt.status IN ('selected', 'completed')";

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
        if ($filter_book) {
            $sql .= " AND bt.book_id = ?";
            $params[] = $filter_book;
            $types .= 'i';
        }
        if ($filter_status) {
            $sql .= " AND bt.status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }
        $sql .= " ORDER BY u.name, bm.book_name";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $book_report = $stmt->get_result();
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Books Report</h3>
            </div>
            <form method="GET" action="" style="padding: var(--spacing-lg);">
                <input type="hidden" name="report_type" value="books">
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Search by Name/ITS</label>
                    <input type="text" name="search_name" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
                <?php if (!$is_category_coordinator): ?>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Filter by Category</label>
                        <select name="filter_category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                            <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                            <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                            <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            <option value="Muntasib" <?php echo $filter_category === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-bottom: var(--spacing-md);">
                        <i class="fas fa-info-circle"></i> Showing users from <strong><?php echo htmlspecialchars($assigned_category); ?></strong> category only.
                    </div>
                <?php endif; ?>
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
            <div class="table-container">
                <?php if ($book_report->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ITS Number</th>
                                <th>Name</th>
                                <th>Jamea</th>
                                <th>Classification</th>
                                <th>Book Name</th>
                                <th>Author</th>
                                <th>Total Pages</th>
                                <th>Pages Completed</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Started Date</th>
                                <th>Completed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $book_report->fetch_assoc()): ?>
                                <?php
                                $pages_completed = $row['pages_completed'] ?? 0;
                                $total_pages = $row['total_pages'] ?? 0;
                                $progress_percentage = $row['progress_percentage'] ?? 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['its_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['classification'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['author']); ?></td>
                                    <td><?php echo $total_pages; ?></td>
                                    <td><strong><?php echo $pages_completed; ?></strong></td>
                                    <td>
                                        <div style="min-width: 120px;">
                                            <div style="font-size: 12px; margin-bottom: 4px;"><?php echo $progress_percentage; ?>%</div>
                                            <div class="progress-bar" style="height: 12px;">
                                                <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%; height: 12px;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'completed'): ?>
                                            <span class="badge badge-success">Completed</span>
                                        <?php elseif ($row['status'] === 'selected'): ?>
                                            <span class="badge badge-warning">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Selected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['started_date'] ? date('M d, Y', strtotime($row['started_date'])) : '-'; ?></td>
                                    <td><?php echo $row['completed_date'] ? date('M d, Y', strtotime($row['completed_date'])) : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No book transcription data found.</p>
                <?php endif; ?>
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

<?php require_once '../includes/footer.php'; ?>