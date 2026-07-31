<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Check if user has amali access
if (!has_amali_access()) {
    header('Location: index.php');
    exit();
}

// Auto-ensure required master tables exist in database
$sql_check_mazars = "CREATE TABLE IF NOT EXISTS `mazars_master` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mazar_name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `display_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$sql_check_ziyarat = "CREATE TABLE IF NOT EXISTS `ziyarat_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `mazar_id` INT NOT NULL,
  `count_added` INT DEFAULT 0,
  INDEX idx_ziyarat_user (`user_id`),
  INDEX idx_ziyarat_mazar (`mazar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

mysqli_query($conn, $sql_check_mazars);
mysqli_query($conn, $sql_check_ziyarat);

$page_title = 'Enterprise Advanced Reports';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Role-Based Access Control (RBAC) Logic
$is_category_coordinator = is_category_amali_coordinator();
$assigned_category = get_assigned_category();

if ($is_category_coordinator && $assigned_category) {
    $filter_branch = $assigned_category;
} else {
    $filter_branch = isset($_GET['branch']) ? clean_input($_GET['branch']) : '';
}

// Request parameters
$report_type = isset($_GET['report_type']) ? clean_input($_GET['report_type']) : 'overall';
$threshold = isset($_GET['threshold']) && is_numeric($_GET['threshold']) ? floatval($_GET['threshold']) : 3.7;
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$classification = isset($_GET['classification']) ? clean_input($_GET['classification']) : '';
$item_ids = isset($_GET['item_ids']) && is_array($_GET['item_ids']) ? array_map('intval', $_GET['item_ids']) : [];

// Fetch item masters for multi-select dropdowns
$duas_master = [];
$res = mysqli_query($conn, "SELECT id, dua_name, target_count FROM duas_master WHERE category = 'dua' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC");
while ($r = mysqli_fetch_assoc($res)) { $duas_master[] = $r; }

$tasbeeh_master = [];
$res = mysqli_query($conn, "SELECT id, dua_name, target_count FROM duas_master WHERE category = 'tasbeeh' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC");
while ($r = mysqli_fetch_assoc($res)) { $tasbeeh_master[] = $r; }

$namaz_master = [];
$res = mysqli_query($conn, "SELECT id, dua_name, target_count FROM duas_master WHERE category = 'namaz' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC");
while ($r = mysqli_fetch_assoc($res)) { $namaz_master[] = $r; }

$books_master = [];
$res = mysqli_query($conn, "SELECT id, book_name, total_pages FROM books_master WHERE is_active = 1 ORDER BY display_order ASC, book_name ASC");
while ($r = mysqli_fetch_assoc($res)) { $books_master[] = $r; }

$mazars_master = [];
$res = mysqli_query($conn, "SELECT id, mazar_name FROM mazars_master WHERE is_active = 1 ORDER BY display_order ASC, mazar_name ASC");
while ($r = mysqli_fetch_assoc($res)) { $mazars_master[] = $r; }

// Build base user query (Excluding test accounts its_number NOT LIKE '000000%', including role 'user' and 'admin')
$user_sql = "SELECT u.id as user_id, u.name, u.its_number, u.category, u.classification FROM users u WHERE (u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%'";
$params = [];
$types = "";

if ($filter_branch !== '') {
    $user_sql .= " AND u.category = ?";
    $params[] = $filter_branch;
    $types .= "s";
}

if ($classification !== '') {
    $user_sql .= " AND u.classification = ?";
    $params[] = $classification;
    $types .= "s";
}

if ($search !== '') {
    $user_sql .= " AND (u.name LIKE ? OR u.its_number LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$user_sql .= " ORDER BY u.category ASC, u.name ASC";

$stmt = $conn->prepare($user_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Global target counts for overall calculation helper
$total_dua_target_all = 0;
foreach ($duas_master as $d) { $total_dua_target_all += $d['target_count']; }

$total_tasbeeh_target_all = 0;
foreach ($tasbeeh_master as $t) { $total_tasbeeh_target_all += $t['target_count']; }

$total_namaz_target_all = 0;
foreach ($namaz_master as $n) { $total_namaz_target_all += $n['target_count']; }

// ==========================================
// HIGH PERFORMANCE BATCH DATA PRE-FETCHING
// ==========================================
$quran_map = []; // [user_id] => completed_juz
$dua_entry_map = []; // [user_id][dua_id] => total_recited
$category_recited_sum = []; // [user_id][category] => total_recited
$book_map = []; // [user_id][book_id] => ['pages' => x, 'status' => y]
$ziyarat_map = []; // [user_id][mazar_id] => total_visits

// Batch Fetch Quran Progress
$qres = mysqli_query($conn, "SELECT user_id, COUNT(*) as completed_juz FROM quran_progress WHERE is_completed = 1 GROUP BY user_id");
while ($r = mysqli_fetch_assoc($qres)) {
    $quran_map[$r['user_id']] = intval($r['completed_juz']);
}

// Batch Fetch Dua / Tasbeeh / Namaz Entries
$dres = mysqli_query($conn, "SELECT de.user_id, dm.category, de.dua_id, SUM(de.count_added) as total_recited FROM dua_entries de JOIN duas_master dm ON de.dua_id = dm.id WHERE dm.is_active = 1 GROUP BY de.user_id, dm.category, de.dua_id");
while ($r = mysqli_fetch_assoc($dres)) {
    $uid = $r['user_id'];
    $cat = $r['category'];
    $did = $r['dua_id'];
    $cnt = intval($r['total_recited']);
    $dua_entry_map[$uid][$did] = $cnt;
    $category_recited_sum[$uid][$cat] = ($category_recited_sum[$uid][$cat] ?? 0) + $cnt;
}

// Batch Fetch Book Transcription
if ($report_type === 'book') {
    $bres = mysqli_query($conn, "SELECT user_id, book_id, pages_completed, status FROM book_transcription");
    while ($r = mysqli_fetch_assoc($bres)) {
        $book_map[$r['user_id']][$r['book_id']] = [
            'pages' => intval($r['pages_completed']),
            'status' => $r['status']
        ];
    }
}

// Batch Fetch Ziyarat Entries
if ($report_type === 'ziyarat') {
    $zres = mysqli_query($conn, "SELECT user_id, mazar_id, SUM(count_added) as total_visits FROM ziyarat_entries GROUP BY user_id, mazar_id");
    while ($r = mysqli_fetch_assoc($zres)) {
        $ziyarat_map[$r['user_id']][$r['mazar_id']] = intval($r['total_visits']);
    }
}

// ==========================================
// PROCESS CALCULATIONS PER USER IN-MEMORY
// ==========================================
$report_data = [];
$flagged_count = 0;
$total_overall_pct_sum = 0;

if ($report_type === 'overall') {
    foreach ($users as $user) {
        $uid = $user['user_id'];

        // 1. Quran %
        $completed_juz = $quran_map[$uid] ?? 0;
        $quran_pct = min(100.0, ($completed_juz / 120.0) * 100.0);

        // 2. Dua %
        $dua_recited = $category_recited_sum[$uid]['dua'] ?? 0;
        $dua_pct = $total_dua_target_all > 0 ? ($dua_recited / $total_dua_target_all) * 100.0 : 0.0;

        // 3. Tasbeeh %
        $tasbeeh_recited = $category_recited_sum[$uid]['tasbeeh'] ?? 0;
        $tasbeeh_pct = $total_tasbeeh_target_all > 0 ? ($tasbeeh_recited / $total_tasbeeh_target_all) * 100.0 : 0.0;

        // 4. Namaz %
        $namaz_recited = $category_recited_sum[$uid]['namaz'] ?? 0;
        $namaz_pct = $total_namaz_target_all > 0 ? ($namaz_recited / $total_namaz_target_all) * 100.0 : 0.0;

        // Overall Progress = (Quran% + Dua% + Tasbeeh% + Namaz%) / 4
        $overall_pct = ($quran_pct + $dua_pct + $tasbeeh_pct + $namaz_pct) / 4.0;
        $is_flagged = ($overall_pct < $threshold);

        if ($is_flagged) $flagged_count++;
        $total_overall_pct_sum += $overall_pct;

        $report_data[] = [
            'user' => $user,
            'quran_pct' => round($quran_pct, 2),
            'dua_pct' => round($dua_pct, 2),
            'tasbeeh_pct' => round($tasbeeh_pct, 2),
            'namaz_pct' => round($namaz_pct, 2),
            'overall_pct' => round($overall_pct, 2),
            'is_flagged' => $is_flagged
        ];
    }
} elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])) {
    $active_items = [];
    $source_master = ($report_type === 'dua') ? $duas_master : (($report_type === 'tasbeeh') ? $tasbeeh_master : $namaz_master);
    
    foreach ($source_master as $item) {
        if (empty($item_ids) || in_array($item['id'], $item_ids)) {
            $active_items[] = $item;
        }
    }

    $col_totals = array_fill_keys(array_column($active_items, 'id'), 0);
    $total_selected_target = 0;
    foreach ($active_items as $item) {
        $total_selected_target += $item['target_count'];
    }

    foreach ($users as $user) {
        $uid = $user['user_id'];
        $user_entries = [];
        $user_total_recited = 0;

        foreach ($active_items as $item) {
            $iid = $item['id'];
            $recited = $dua_entry_map[$uid][$iid] ?? 0;
            $user_entries[$iid] = $recited;
            $col_totals[$iid] += $recited;
            $user_total_recited += $recited;
        }

        $user_overall_pct = $total_selected_target > 0 ? ($user_total_recited / $total_selected_target) * 100.0 : 0.0;
        $is_flagged = ($user_overall_pct < $threshold);
        if ($is_flagged) $flagged_count++;
        $total_overall_pct_sum += $user_overall_pct;

        $report_data[] = [
            'user' => $user,
            'entries' => $user_entries,
            'total_recited' => $user_total_recited,
            'overall_pct' => round($user_overall_pct, 2),
            'is_flagged' => $is_flagged
        ];
    }
} elseif ($report_type === 'quran') {
    foreach ($users as $user) {
        $uid = $user['user_id'];
        $completed_juz = $quran_map[$uid] ?? 0;
        $completed_qurans = floor($completed_juz / 30);
        $quran_pct = min(100.0, ($completed_juz / 120.0) * 100.0);

        $is_flagged = ($quran_pct < $threshold);
        if ($is_flagged) $flagged_count++;
        $total_overall_pct_sum += $quran_pct;

        $report_data[] = [
            'user' => $user,
            'completed_juz' => $completed_juz,
            'completed_qurans' => $completed_qurans,
            'overall_pct' => round($quran_pct, 2),
            'is_flagged' => $is_flagged
        ];
    }
} elseif ($report_type === 'book') {
    $active_books = [];
    foreach ($books_master as $book) {
        if (empty($item_ids) || in_array($book['id'], $item_ids)) {
            $active_books[] = $book;
        }
    }

    $total_books_count = count($active_books);

    foreach ($users as $user) {
        $uid = $user['user_id'];
        $book_entries = [];
        $completed_books = 0;

        foreach ($active_books as $book) {
            $bid = $book['id'];
            $entry = $book_map[$uid][$bid] ?? ['pages' => 0, 'status' => 'not_started'];
            $pages = $entry['pages'];
            $status = $entry['status'];
            
            if ($status === 'completed' || ($book['total_pages'] > 0 && $pages >= $book['total_pages'])) {
                $completed_books++;
            }

            $book_entries[$bid] = [
                'pages' => $pages,
                'status' => $status
            ];
        }

        $overall_pct = $total_books_count > 0 ? ($completed_books / $total_books_count) * 100.0 : 0.0;
        $is_flagged = ($overall_pct < $threshold);
        if ($is_flagged) $flagged_count++;
        $total_overall_pct_sum += $overall_pct;

        $report_data[] = [
            'user' => $user,
            'book_entries' => $book_entries,
            'completed_books' => $completed_books,
            'overall_pct' => round($overall_pct, 2),
            'is_flagged' => $is_flagged
        ];
    }
} elseif ($report_type === 'ziyarat') {
    $active_mazars = [];
    foreach ($mazars_master as $m) {
        if (empty($item_ids) || in_array($m['id'], $item_ids)) {
            $active_mazars[] = $m;
        }
    }

    $col_totals = array_fill_keys(array_column($active_mazars, 'id'), 0);

    foreach ($users as $user) {
        $uid = $user['user_id'];
        $mazar_entries = [];
        $total_visits = 0;

        foreach ($active_mazars as $m) {
            $mid = $m['id'];
            $visits = $ziyarat_map[$uid][$mid] ?? 0;
            $mazar_entries[$mid] = $visits;
            $col_totals[$mid] += $visits;
            $total_visits += $visits;
        }

        $is_flagged = ($total_visits < 1 && $threshold > 0);
        if ($is_flagged) $flagged_count++;

        $report_data[] = [
            'user' => $user,
            'mazar_entries' => $mazar_entries,
            'total_visits' => $total_visits,
            'overall_pct' => $total_visits,
            'is_flagged' => $is_flagged
        ];
    }
}

// Section 6: Excel Export Architecture (?export=excel)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Amali_Advanced_Report_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 12px; }
            th { background-color: #243b53; color: #ffffff; padding: 8px; border: 1px solid #cbd5e1; font-weight: bold; }
            td { padding: 8px; border: 1px solid #cbd5e1; vertical-align: middle; }
            .flagged { color: #dc2626; background-color: #fee2e2; font-weight: bold; }
            .flagged-row { background-color: #fee2e2 !important; color: #dc2626; }
            .badge-flagged { background-color: #ef4444; color: #ffffff; padding: 2px 6px; font-weight: bold; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h2>Amali Enterprise Advanced Report - <?php echo strtoupper($report_type); ?></h2>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?> | Branch: <?php echo $filter_branch ?: 'All Branches'; ?> | Exception Threshold: <?php echo $threshold; ?>%</p>
        
        <table class="report-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ITS Number</th>
                    <th>Full Name</th>
                    <th>Branch / Category</th>
                    <th>Classification</th>
                    <?php if ($report_type === 'overall'): ?>
                        <th>Quran Progress (%)</th>
                        <th>Dua Progress (%)</th>
                        <th>Tasbeeh Progress (%)</th>
                        <th>Namaz Progress (%)</th>
                        <th>Overall Progress (%)</th>
                    <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                        <?php foreach ($active_items as $item): ?>
                            <th><?php echo htmlspecialchars($item['dua_name']) . ' (Target: ' . $item['target_count'] . ')'; ?></th>
                        <?php endforeach; ?>
                        <th>Total Recited</th>
                        <th>Overall %</th>
                    <?php elseif ($report_type === 'quran'): ?>
                        <th>Completed Juz (out of 120)</th>
                        <th>Completed Qurans</th>
                        <th>Progress (%)</th>
                    <?php elseif ($report_type === 'book'): ?>
                        <?php foreach ($active_books as $book): ?>
                            <th><?php echo htmlspecialchars($book['book_name']) . ' (' . $book['total_pages'] . ' pgs)'; ?></th>
                        <?php endforeach; ?>
                        <th>Completed Books</th>
                        <th>Overall %</th>
                    <?php elseif ($report_type === 'ziyarat'): ?>
                        <?php foreach ($active_mazars as $m): ?>
                            <th><?php echo htmlspecialchars($m['mazar_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Visits</th>
                    <?php endif; ?>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($report_data as $row): 
                    $u = $row['user'];
                    $tr_class = $row['is_flagged'] ? 'flagged-row flagged' : '';
                ?>
                    <tr class="<?php echo $tr_class; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($u['its_number']); ?></td>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['category'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($u['classification'] ?? '-'); ?></td>

                        <?php if ($report_type === 'overall'): ?>
                            <td><?php echo $row['quran_pct']; ?>%</td>
                            <td><?php echo $row['dua_pct']; ?>%</td>
                            <td><?php echo $row['tasbeeh_pct']; ?>%</td>
                            <td><?php echo $row['namaz_pct']; ?>%</td>
                            <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                        <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                            <?php foreach ($active_items as $item): ?>
                                <td><?php echo $row['entries'][$item['id']] . ' / ' . $item['target_count']; ?></td>
                            <?php endforeach; ?>
                            <td><?php echo $row['total_recited']; ?></td>
                            <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                        <?php elseif ($report_type === 'quran'): ?>
                            <td><?php echo $row['completed_juz']; ?></td>
                            <td><?php echo $row['completed_qurans']; ?></td>
                            <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                        <?php elseif ($report_type === 'book'): ?>
                            <?php foreach ($active_books as $b): ?>
                                <td><?php echo $row['book_entries'][$b['id']]['pages'] . ' / ' . $b['total_pages'] . ' (' . strtoupper($row['book_entries'][$b['id']]['status']) . ')'; ?></td>
                            <?php endforeach; ?>
                            <td><?php echo $row['completed_books']; ?></td>
                            <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                        <?php elseif ($report_type === 'ziyarat'): ?>
                            <?php foreach ($active_mazars as $m): ?>
                                <td><?php echo $row['mazar_entries'][$m['id']]; ?></td>
                            <?php endforeach; ?>
                            <td><strong><?php echo $row['total_visits']; ?></strong></td>
                        <?php endif; ?>

                        <td>
                            <?php if ($row['is_flagged']): ?>
                                <span class="badge-flagged flagged">FLAGGED</span>
                            <?php else: ?>
                                Normal
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Select2 & Custom Styles -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .enterprise-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .enterprise-title h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .enterprise-title p {
        color: var(--gray-500);
        font-size: 0.9rem;
        margin-top: 0.25rem;
    }

    .card-filters, .filter-card {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-sm);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
    }

    .form-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.4rem;
    }

    .form-control, .form-select {
        width: 100%;
        padding: 0.6rem 0.85rem;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        font-size: 0.9rem;
        background-color: #ffffff;
        color: var(--gray-800);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-500);
        outline: none;
        box-shadow: 0 0 0 3px rgba(98, 125, 152, 0.15);
    }

    .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--gray-300) !important;
        border-radius: 8px !important;
        min-height: 42px !important;
        padding: 2px 6px;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    .stat-icon.users { background-color: #e0f2fe; color: #0284c7; }
    .stat-icon.flagged { background-color: #fee2e2; color: #dc2626; }
    .stat-icon.avg { background-color: #ecfdf5; color: #059669; }
    .stat-icon.threshold { background-color: #fef3c7; color: #d97706; }

    .stat-details h4 { font-size: 1.35rem; font-weight: 700; color: var(--gray-900); }
    .stat-details p { font-size: 0.8rem; color: var(--gray-500); }

    /* Cross-Tabulation Matrix Table Styling */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-sm);
    }

    .report-table, .matrix-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        text-align: left;
    }

    .report-table th, .matrix-table th {
        background: var(--gray-800);
        color: #ffffff;
        font-weight: 600;
        padding: 12px 14px;
        border-bottom: 2px solid var(--gray-900);
        white-space: nowrap;
    }

    .report-table td, .matrix-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: middle;
        white-space: nowrap;
    }

    /* Exception Flagging Alert Styles */
    .report-table tr.flagged-row, .matrix-table tr.flagged-row {
        background-color: #fee2e2 !important;
    }

    .report-table tr.flagged-row td, .matrix-table tr.flagged-row td {
        color: #991b1b;
    }

    .badge-flagged {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .badge-normal {
        background-color: #dcfce7;
        color: #166534;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .matrix-table tfoot th, .matrix-table tfoot td, .report-table tfoot th, .report-table tfoot td {
        background: var(--gray-100);
        font-weight: 700;
        border-top: 2px solid var(--gray-300);
        color: var(--gray-900);
    }

    /* Progress bar snippet */
    .mini-progress {
        width: 100px;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 6px;
    }
    .mini-progress-fill {
        height: 100%;
        background: #10b981;
        border-radius: 4px;
    }
    .mini-progress-fill.flagged {
        background: #ef4444;
    }

    /* Section 6 Print / PDF Export Directives (@media print) */
    @media print {
        .sidebar, .topbar, .footer, .card-filters, .action-buttons-container, .no-print, header {
            display: none !important;
        }
        .main-wrapper, .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }
        .report-table th, .matrix-table th {
            background-color: #243b53 !important;
            color: white !important;
            border: 1px solid #cbd5e1 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .report-table td, .matrix-table td {
            border: 1px solid #cbd5e1 !important;
        }
        .report-table tr.flagged-row, .matrix-table tr.flagged-row {
            background-color: #fee2e2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="main-content main-wrapper">
    <div class="enterprise-header">
        <div class="enterprise-title">
            <h1><i class="fas fa-chart-line text-primary"></i> Enterprise Advanced Reports</h1>
            <p>Admin Analytics Console & Matrix Cross-Tabulation Progress Tracker</p>
        </div>
        <div class="action-buttons-container d-flex gap-2">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print me-1"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Filter Console Form -->
    <div class="card-filters filter-card">
        <form method="GET" action="advanced_reports.php" id="report_form">
            <div class="filter-grid">
                <!-- Branch / Category Filter -->
                <div class="form-group">
                    <label for="branch"><i class="fas fa-building me-1"></i> Branch / Category</label>
                    <?php if ($is_category_coordinator): ?>
                        <select id="branch" class="form-select" disabled>
                            <option value="<?php echo htmlspecialchars($assigned_category); ?>" selected>
                                <?php echo htmlspecialchars($assigned_category); ?> (Locked)
                            </option>
                        </select>
                        <input type="hidden" name="branch" value="<?php echo htmlspecialchars($assigned_category); ?>">
                    <?php else: ?>
                        <select name="branch" id="branch" class="form-select">
                            <option value="">All Branches</option>
                            <?php 
                            $branches = ['Surat', 'Marol', 'Karachi', 'Nairobi', 'Muntasib'];
                            foreach ($branches as $b): 
                            ?>
                                <option value="<?php echo $b; ?>" <?php echo $filter_branch === $b ? 'selected' : ''; ?>>
                                    <?php echo $b; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Report Module Filter -->
                <div class="form-group">
                    <label for="report_type"><i class="fas fa-list-check me-1"></i> Report Module</label>
                    <select name="report_type" id="report_type" class="form-select" onchange="handleReportTypeChange()">
                        <option value="overall" <?php echo $report_type === 'overall' ? 'selected' : ''; ?>>Overall Dashboard</option>
                        <option value="dua" <?php echo $report_type === 'dua' ? 'selected' : ''; ?>>Dua Recitation Matrix</option>
                        <option value="tasbeeh" <?php echo $report_type === 'tasbeeh' ? 'selected' : ''; ?>>Tasbeeh Count Matrix</option>
                        <option value="namaz" <?php echo $report_type === 'namaz' ? 'selected' : ''; ?>>Namaz Recitation Matrix</option>
                        <option value="quran" <?php echo $report_type === 'quran' ? 'selected' : ''; ?>>Quran Progress Tracking</option>
                        <option value="book" <?php echo $report_type === 'book' ? 'selected' : ''; ?>>Book Transcription Matrix</option>
                        <option value="ziyarat" <?php echo $report_type === 'ziyarat' ? 'selected' : ''; ?>>Mazar Visit Matrix</option>
                    </select>
                </div>

                <!-- Exception Threshold % -->
                <div class="form-group">
                    <label for="threshold"><i class="fas fa-triangle-exclamation me-1"></i> Exception Threshold (%)</label>
                    <input type="number" step="0.1" name="threshold" id="threshold" class="form-control" value="<?php echo htmlspecialchars($threshold); ?>" placeholder="3.7">
                </div>

                <!-- Classification Filter -->
                <div class="form-group">
                    <label for="classification"><i class="fas fa-tags me-1"></i> Classification</label>
                    <select name="classification" id="classification" class="form-select">
                        <option value="">All Classifications</option>
                        <?php 
                        $classifications = ['Talabat', 'Taalebaat', 'Muntasebeen', 'Muntasebaat'];
                        foreach ($classifications as $c): 
                        ?>
                            <option value="<?php echo $c; ?>" <?php echo $classification === $c ? 'selected' : ''; ?>>
                                <?php echo $c; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="form-group">
                    <label for="search"><i class="fas fa-search me-1"></i> Search User</label>
                    <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or ITS Number...">
                </div>
            </div>

            <!-- Dynamic Select2 Multi-Select Containers -->
            <div style="margin-top: 1.25rem;">
                <!-- Dua Items Multi-Select -->
                <div class="form-group" id="dua_items_group" style="display: <?php echo $report_type === 'dua' ? 'block' : 'none'; ?>;">
                    <label><i class="fas fa-hands-praying me-1"></i> Filter Specific Duas (Select Multi):</label>
                    <select name="item_ids[]" class="form-select select2-multiselect" multiple="multiple" style="width:100%;">
                        <?php foreach ($duas_master as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo (in_array($d['id'], $item_ids)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['dua_name']) . ' (Target: ' . $d['target_count'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tasbeeh Items Multi-Select -->
                <div class="form-group" id="tasbeeh_items_group" style="display: <?php echo $report_type === 'tasbeeh' ? 'block' : 'none'; ?>;">
                    <label><i class="fas fa-beads me-1"></i> Filter Specific Tasbeehs (Select Multi):</label>
                    <select name="item_ids[]" class="form-select select2-multiselect" multiple="multiple" style="width:100%;">
                        <?php foreach ($tasbeeh_master as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo (in_array($t['id'], $item_ids)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['dua_name']) . ' (Target: ' . $t['target_count'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Namaz Items Multi-Select -->
                <div class="form-group" id="namaz_items_group" style="display: <?php echo $report_type === 'namaz' ? 'block' : 'none'; ?>;">
                    <label><i class="fas fa-person-praying me-1"></i> Filter Specific Namaz (Select Multi):</label>
                    <select name="item_ids[]" class="form-select select2-multiselect" multiple="multiple" style="width:100%;">
                        <?php foreach ($namaz_master as $n): ?>
                            <option value="<?php echo $n['id']; ?>" <?php echo (in_array($n['id'], $item_ids)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($n['dua_name']) . ' (Target: ' . $n['target_count'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Book Items Multi-Select -->
                <div class="form-group" id="book_items_group" style="display: <?php echo $report_type === 'book' ? 'block' : 'none'; ?>;">
                    <label><i class="fas fa-book me-1"></i> Filter Specific Books (Select Multi):</label>
                    <select name="item_ids[]" class="form-select select2-multiselect" multiple="multiple" style="width:100%;">
                        <?php foreach ($books_master as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo (in_array($b['id'], $item_ids)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['book_name']) . ' (' . $b['total_pages'] . ' Pages)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ziyarat Items Multi-Select -->
                <div class="form-group" id="ziyarat_items_group" style="display: <?php echo $report_type === 'ziyarat' ? 'block' : 'none'; ?>;">
                    <label><i class="fas fa-kaaba me-1"></i> Filter Specific Mazars (Select Multi):</label>
                    <select name="item_ids[]" class="form-select select2-multiselect" multiple="multiple" style="width:100%;">
                        <?php foreach ($mazars_master as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo (in_array($m['id'], $item_ids)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['mazar_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 1.25rem;" class="d-flex gap-2 justify-content-end">
                <a href="advanced_reports.php" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i> Reset Filters
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <?php 
    $total_users_count = count($report_data);
    $avg_overall_pct = $total_users_count > 0 ? round($total_overall_pct_sum / $total_users_count, 2) : 0;
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon users"><i class="fas fa-users"></i></div>
            <div class="stat-details">
                <h4><?php echo $total_users_count; ?></h4>
                <p>Total Users Evaluated</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon flagged"><i class="fas fa-flag"></i></div>
            <div class="stat-details">
                <h4><?php echo $flagged_count; ?></h4>
                <p>Flagged (< <?php echo $threshold; ?>%)</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon avg"><i class="fas fa-chart-pie"></i></div>
            <div class="stat-details">
                <h4><?php echo $avg_overall_pct; ?>%</h4>
                <p>Average Overall Progress</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon threshold"><i class="fas fa-sliders"></i></div>
            <div class="stat-details">
                <h4><?php echo $threshold; ?>%</h4>
                <p>Current Flag Threshold</p>
            </div>
        </div>
    </div>

    <!-- Main Cross-Tabulation Matrix Table -->
    <div class="table-responsive">
        <table class="report-table matrix-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ITS Number</th>
                    <th>User Name</th>
                    <th>Branch</th>
                    <th>Classification</th>

                    <?php if ($report_type === 'overall'): ?>
                        <th>Quran Progress</th>
                        <th>Dua Progress</th>
                        <th>Tasbeeh Progress</th>
                        <th>Namaz Progress</th>
                        <th>Overall Progress</th>
                    <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                        <?php foreach ($active_items as $item): ?>
                            <th><?php echo htmlspecialchars($item['dua_name']) . '<br><small style="opacity:0.8;">Target: ' . $item['target_count'] . '</small>'; ?></th>
                        <?php endforeach; ?>
                        <th>Total Recited</th>
                        <th>Overall %</th>
                    <?php elseif ($report_type === 'quran'): ?>
                        <th>Completed Juz (of 120)</th>
                        <th>Completed Qurans</th>
                        <th>Progress %</th>
                    <?php elseif ($report_type === 'book'): ?>
                        <?php foreach ($active_books as $book): ?>
                            <th><?php echo htmlspecialchars($book['book_name']) . '<br><small style="opacity:0.8;">' . $book['total_pages'] . ' pgs</small>'; ?></th>
                        <?php endforeach; ?>
                        <th>Completed Books</th>
                        <th>Overall %</th>
                    <?php elseif ($report_type === 'ziyarat'): ?>
                        <?php foreach ($active_mazars as $m): ?>
                            <th><?php echo htmlspecialchars($m['mazar_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Visits</th>
                    <?php endif; ?>

                    <th>Status Flag</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="20" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>No user record found matching the specified filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $idx = 1;
                    foreach ($report_data as $row): 
                        $u = $row['user'];
                        $is_flagged = $row['is_flagged'];
                        $row_class = $is_flagged ? 'flagged-row' : '';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $idx++; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['its_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($u['category'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($u['classification'] ?? '-'); ?></td>

                            <?php if ($report_type === 'overall'): ?>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill" style="width:<?php echo min(100, $row['quran_pct']); ?>%;"></div></div>
                                    <?php echo $row['quran_pct']; ?>%
                                </td>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill" style="width:<?php echo min(100, $row['dua_pct']); ?>%;"></div></div>
                                    <?php echo $row['dua_pct']; ?>%
                                </td>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill" style="width:<?php echo min(100, $row['tasbeeh_pct']); ?>%;"></div></div>
                                    <?php echo $row['tasbeeh_pct']; ?>%
                                </td>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill" style="width:<?php echo min(100, $row['namaz_pct']); ?>%;"></div></div>
                                    <?php echo $row['namaz_pct']; ?>%
                                </td>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill <?php echo $is_flagged ? 'flagged' : ''; ?>" style="width:<?php echo min(100, $row['overall_pct']); ?>%;"></div></div>
                                    <strong><?php echo $row['overall_pct']; ?>%</strong>
                                </td>
                            <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                                <?php foreach ($active_items as $item): ?>
                                    <td>
                                        <?php 
                                        $val = $row['entries'][$item['id']];
                                        echo $val . ' / ' . $item['target_count'];
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><strong><?php echo $row['total_recited']; ?></strong></td>
                                <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                            <?php elseif ($report_type === 'quran'): ?>
                                <td><?php echo $row['completed_juz']; ?> / 120</td>
                                <td><?php echo $row['completed_qurans']; ?> Quran(s)</td>
                                <td>
                                    <div class="mini-progress"><div class="mini-progress-fill <?php echo $is_flagged ? 'flagged' : ''; ?>" style="width:<?php echo min(100, $row['overall_pct']); ?>%;"></div></div>
                                    <strong><?php echo $row['overall_pct']; ?>%</strong>
                                </td>
                            <?php elseif ($report_type === 'book'): ?>
                                <?php foreach ($active_books as $b): ?>
                                    <td>
                                        <?php 
                                        $entry = $row['book_entries'][$b['id']];
                                        echo $entry['pages'] . ' / ' . $b['total_pages'];
                                        if ($entry['status'] === 'completed') {
                                            echo ' <span class="badge bg-success" style="font-size:0.65rem;">Completed</span>';
                                        } elseif ($entry['pages'] > 0) {
                                            echo ' <span class="badge bg-warning text-dark" style="font-size:0.65rem;">In Progress</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?php echo $row['completed_books']; ?> / <?php echo count($active_books); ?></td>
                                <td><strong><?php echo $row['overall_pct']; ?>%</strong></td>
                            <?php elseif ($report_type === 'ziyarat'): ?>
                                <?php foreach ($active_mazars as $m): ?>
                                    <td><?php echo $row['mazar_entries'][$m['id']]; ?></td>
                                <?php endforeach; ?>
                                <td><strong><?php echo $row['total_visits']; ?></strong></td>
                            <?php endif; ?>

                            <td>
                                <?php if ($is_flagged): ?>
                                    <span class="badge-flagged"><i class="fas fa-exclamation-triangle"></i> Flagged (< <?php echo $threshold; ?>%)</span>
                                <?php else: ?>
                                    <span class="badge-normal"><i class="fas fa-check-circle me-1"></i> Satisfactory</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <!-- Column-wise Target Percentages & Totals Footer -->
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end"><strong>Summary / Aggregates:</strong></td>

                    <?php if ($report_type === 'overall'): ?>
                        <td colspan="4">Average Across Evaluated Users</td>
                        <td><strong><?php echo $avg_overall_pct; ?>%</strong></td>
                    <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                        <?php foreach ($active_items as $item): ?>
                            <td>
                                <strong>Sum: <?php echo $col_totals[$item['id']]; ?></strong>
                            </td>
                        <?php endforeach; ?>
                        <td colspan="2"><strong>Avg Overall: <?php echo $avg_overall_pct; ?>%</strong></td>
                    <?php elseif ($report_type === 'quran'): ?>
                        <td colspan="2">Total Overall Progress:</td>
                        <td><strong><?php echo $avg_overall_pct; ?>%</strong></td>
                    <?php elseif ($report_type === 'book'): ?>
                        <td colspan="<?php echo count($active_books) + 2; ?>"><strong>Avg Book Progress: <?php echo $avg_overall_pct; ?>%</strong></td>
                    <?php elseif ($report_type === 'ziyarat'): ?>
                        <?php foreach ($active_mazars as $m): ?>
                            <td><strong>Total: <?php echo $col_totals[$m['id']]; ?></strong></td>
                        <?php endforeach; ?>
                        <td>-</td>
                    <?php endif; ?>

                    <td>-</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- JavaScript Controls & Select2 Script -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 multi-select dropdowns
    $('.select2-multiselect').select2({
        placeholder: "Search and select items...",
        allowClear: true,
        closeOnSelect: false
    });

    // Ensure correct initial visibility & disabled states
    handleReportTypeChange();
});

function handleReportTypeChange() {
    const reportType = document.getElementById('report_type').value;
    const groups = ['dua', 'tasbeeh', 'namaz', 'book', 'ziyarat'];
    
    groups.forEach(group => {
        const elem = document.getElementById(group + '_items_group');
        if (elem) {
            elem.style.display = 'none';
            disableContainerInputs(group + '_items_group', true);
        }
    });

    const activeElem = document.getElementById(reportType + '_items_group');
    if (activeElem) {
        activeElem.style.display = 'block';
        disableContainerInputs(reportType + '_items_group', false);
    }
}

function disableContainerInputs(containerId, disable) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const selects = container.getElementsByTagName('select');
    for (let i = 0; i < selects.length; i++) {
        selects[i].disabled = disable;
    }
    const inputs = container.getElementsByTagName('input');
    for (let i = 0; i < inputs.length; i++) {
        inputs[i].disabled = disable;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
