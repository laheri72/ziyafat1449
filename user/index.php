<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/supabase_helper.php';

require_login();

$page_title = 'User Dashboard';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get user data
$user = get_user_by_id($conn, $user_id);
$tr_number = $user['tr_number'] ?? '';

// Get Ziyarat Flow data from Supabase
$ziyarat_event_tags = get_supabase_event_tags();
$active_ziyarat_event = $ziyarat_event_tags[0] ?? 'Active Event';
$supa_student = get_supabase_student_by_tr($tr_number);
$ziyarat_event_stats = get_supabase_stats_for_trs([$tr_number], $active_ziyarat_event)[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];
$ziyarat_overall_stats = get_supabase_stats_for_trs([$tr_number])[$tr_number] ?? ['assigned' => 0, 'completed' => 0, 'pending' => 0];

$z_event_assigned = $ziyarat_event_stats['assigned'];
$z_event_completed = $ziyarat_event_stats['completed'];
$z_event_pending = $ziyarat_event_stats['pending'];
$z_event_pct = ($z_event_assigned > 0) ? min(100, round(($z_event_completed / $z_event_assigned) * 100)) : 0;

$z_overall_assigned = $ziyarat_overall_stats['assigned'];
$z_overall_completed = $ziyarat_overall_stats['completed'];
$z_overall_pct = ($z_overall_assigned > 0) ? min(100, round(($z_overall_completed / $z_overall_assigned) * 100)) : 0;

$available_in_mumbai = !empty($supa_student['available_in_mumbai']);
$ziyarat_portal_url = "https://ziyarat1449.web.app/?tr=" . urlencode($tr_number);

// Get Quran progress
$quran_progress = get_quran_progress($conn, $user_id);

// Get category-wise totals
$sql_cat_totals = "SELECT 
                    dm.category,
                    COALESCE(SUM(de.count_added), 0) as total_count
                FROM duas_master dm
                LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
                WHERE dm.is_active = 1
                GROUP BY dm.category";
$stmt = $conn->prepare($sql_cat_totals);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cat_result = $stmt->get_result();
$category_totals = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
while ($row = $cat_result->fetch_assoc()) {
    $category_totals[$row['category']] = $row['total_count'];
}

// Get Book progress
$book_progress = get_book_progress($conn, $user_id);

// Get overall summary
$summary = get_amali_summary($conn, $user_id);

// Get detailed progress for each category
$dua_progress_detail = get_dua_progress($conn, $user_id, 'dua');
$tasbeeh_progress_detail = get_dua_progress($conn, $user_id, 'tasbeeh');
$namaz_progress_detail = get_dua_progress($conn, $user_id, 'namaz');

// Get finance data
$settings = get_system_settings($conn);
$contributions = get_user_contributions($conn, $user_id);
$finance_progress = calculate_percentage($contributions['total_inr'], $settings['target_amount_inr']); 
$remaining_inr = $settings['target_amount_inr'] - $contributions['total_inr'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        <p>Track your Amali Janib & Ziyarat Khidmat progress</p>
    </div>

    <!-- ZIYARAT RAUDAT TAHERA WIDGET CARD -->
    <div class="card" style="border-left: 5px solid #2563eb; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3><i class="fas fa-kaaba" style="color: #2563eb;"></i> Ziyarat Raudat Tahera Khidmat</h3>
            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 13px; padding: 6px 12px; border-radius: 20px;">
                Active Event: <strong><?php echo htmlspecialchars($active_ziyarat_event); ?></strong>
            </span>
        </div>
        <div style="padding: var(--spacing-lg);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; align-items: center;">
                <!-- Event Progress Box -->
                <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($active_ziyarat_event); ?> Status</span>
                        <span style="font-size: 13px; font-weight: bold; color: #2563eb;"><?php echo $z_event_completed; ?> / <?php echo $z_event_assigned; ?> Completed</span>
                    </div>
                    <div class="progress-bar" style="height: 10px; background: #e2e8f0; border-radius: 5px; margin-bottom: 8px;">
                        <div class="progress-fill" style="width: <?php echo $z_event_pct; ?>%; background: linear-gradient(90deg, #2563eb, #1d4ed8);"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                        <span style="color: #475569;"><?php echo $z_event_pct; ?>% Event Progress</span>
                        <span style="color: <?php echo $z_event_pending > 0 ? '#dc2626' : '#16a34a'; ?>; font-weight: 600;">
                            <?php echo $z_event_pending > 0 ? "$z_event_pending Pending" : "All Done!"; ?>
                        </span>
                    </div>
                </div>

                <!-- Overall Progress Box -->
                <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Overall Portal Summary</span>
                        <span style="font-size: 13px; font-weight: bold; color: #16a34a;"><?php echo $z_overall_completed; ?> / <?php echo $z_overall_assigned; ?> Total</span>
                    </div>
                    <div class="progress-bar" style="height: 10px; background: #e2e8f0; border-radius: 5px; margin-bottom: 8px;">
                        <div class="progress-fill" style="width: <?php echo $z_overall_pct; ?>%; background: #16a34a;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569;">
                        <span>Lifetime Completion Rate</span>
                        <span><strong><?php echo $z_overall_pct; ?>%</strong></span>
                    </div>
                </div>

                <!-- Mumbai Presence Toggle Box -->
                <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 8px;">Mumbai Presence Status</div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                        <span id="mumbaiStatusBadge" class="badge <?php echo $available_in_mumbai ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 13px; padding: 6px 10px;">
                            <i class="fas <?php echo $available_in_mumbai ? 'fa-map-marker-alt' : 'fa-location-crosshairs'; ?>"></i>
                            <span id="mumbaiStatusText"><?php echo $available_in_mumbai ? 'Available in Mumbai' : 'Not in Mumbai'; ?></span>
                        </span>
                        
                        <button id="toggleMumbaiBtn" class="btn btn-sm <?php echo $available_in_mumbai ? 'btn-secondary' : 'btn-primary'; ?>" onclick="toggleMumbaiPresence(<?php echo $available_in_mumbai ? 0 : 1; ?>)">
                            <?php echo $available_in_mumbai ? 'Mark Unavailable' : 'Mark Available'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                <a href="<?php echo $ziyarat_portal_url; ?>" target="_blank" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; box-shadow: var(--shadow-md);">
                    <i class="fas fa-external-link-alt"></i> Launch Ziyarat Portal
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Navigation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-compass"></i> Quick Navigation</h3>
        </div>
        <div style="padding: var(--spacing-xl);">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 0;">
                <a href="quran_tracking.php" class="btn btn-primary" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-quran" style="font-size: 1.5rem;"></i>
                    <span>Quran Hifzan</span>
                </a>
                <a href="dua_tracking.php" class="btn btn-success" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-hands-praying" style="font-size: 1.5rem;"></i>
                    <span>Dua Tracking</span>
                </a>
                <a href="book_transcription.php" class="btn btn-warning" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-book" style="font-size: 1.5rem;"></i>
                    <span>Istinsakh Kutub</span>
                </a>
                <a href="<?php echo $ziyarat_portal_url; ?>" target="_blank" class="btn" style="background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; flex-direction: column; padding: 1.5rem 1rem; gap: 10px; box-shadow: var(--shadow-md); border: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-md)';">
                    <i class="fas fa-mosque" style="font-size: 1.5rem;"></i>
                    <span>Ziyarat Portal</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <h4>Quran Completed</h4>
                <div class="stat-icon">
                    <i class="fas fa-quran"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['completed_qurans'] ?? 0; ?>/4</div>
            <div class="stat-label"><?php echo $quran_progress['completed_juz']; ?> Juz (<?php echo $quran_progress['progress_percentage']; ?>%)</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <h4>Duas Recited</h4>
                <div class="stat-icon">
                    <i class="fas fa-hands-praying"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['dua']; ?></div>
            <div class="stat-label">Duas</div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <h4>Tasbeeh</h4>
                <div class="stat-icon">
                    <i class="fas fa-dharmachakra"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['tasbeeh']; ?></div>
            <div class="stat-label">Tasbeeh Count</div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <h4>Namaz</h4>
                <div class="stat-icon">
                    <i class="fas fa-mosque"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $category_totals['namaz']; ?></div>
            <div class="stat-label">Namaz Count</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <h4>Kutub Completed</h4>
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['books_completed'] ?? 0; ?></div>
            <div class="stat-label">Istinsakh Completed</div>
        </div>

        <div class="stat-card purple">
            <div class="stat-card-header">
                <h4>Kutub In Progress</h4>
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['books_in_progress'] ?? 0; ?></div>
            <div class="stat-label">Current Istinsakh</div>
        </div>

        <?php if ($user['category'] === 'Surat'): ?>
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-card-header">
                <h4>Contribution Paid</h4>
                <div class="stat-icon" style="color: #10b981;">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo format_currency($contributions['total_inr'], 'INR'); ?></div>
            <div class="stat-label">Remaining: <?php echo format_currency($remaining_inr, 'INR'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($user['category'] === 'Surat'): ?>
    <!-- Finance Progress Overview -->
    <div class="card" style="border-top: 4px solid #10b981;">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding-usd"></i> Ziyafat Contribution Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text">Paid: <?php echo format_currency($contributions['total_inr'], 'INR'); ?> / <?php echo format_currency($settings['target_amount_inr'], 'INR'); ?></span>
                <span class="progress-label-value"><?php echo $finance_progress; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $finance_progress; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
            </div>
        </div>
        <p class="text-center mt-2">
            <a href="surat_finance_report.php" class="btn btn-success" style="background-color: #10b981; border-color: #059669;">
                <i class="fas fa-file-invoice-dollar"></i> View Detailed Finance Report
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Quran Progress Overview -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-quran"></i> Quran Recitation Progress</h3>
        </div>
        <div class="progress-container">
            <div class="progress-label">
                <span class="progress-label-text">Overall Progress: <?php echo $quran_progress['completed_juz']; ?> / 120 Juz</span>
                <span class="progress-label-value"><?php echo $quran_progress['progress_percentage']; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $quran_progress['progress_percentage']; ?>%"></div>
            </div>
        </div>
        <p class="text-center mt-2">
            <a href="quran_tracking.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> View Details & Update
            </a>
        </p>
    </div>

    <!-- Category Progress Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Dua, Tasbeeh & Namaz Detailed Progress</h3>
        </div>
        
        <!-- Duas -->
        <div style="padding: 1rem; border-bottom: 1px solid #eee;">
            <h4 style="margin-bottom: 1rem; color: #10b981;"><i class="fas fa-hands-praying"></i> Duas</h4>
            <div class="table-container">
                <?php if ($dua_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Dua Name</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dua = $dua_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dua['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $dua['progress_percentage']; ?>%; height: 12px;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $dua['completed_count']; ?></td>
                                    <td><?php echo $dua['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tasbeeh -->
        <div style="padding: 1rem; border-bottom: 1px solid #eee;">
            <h4 style="margin-bottom: 1rem; color: #f59e0b;"><i class="fas fa-dharmachakra"></i> Tasbeeh</h4>
            <div class="table-container">
                <?php if ($tasbeeh_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tasbeeh</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tasbeeh = $tasbeeh_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tasbeeh['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $tasbeeh['progress_percentage']; ?>%; height: 12px; background: #f59e0b;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $tasbeeh['completed_count']; ?></td>
                                    <td><?php echo $tasbeeh['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Namaz -->
        <div style="padding: 1rem;">
            <h4 style="margin-bottom: 1rem; color: #8b5cf6;"><i class="fas fa-mosque"></i> Namaz</h4>
            <div class="table-container">
                <?php if ($namaz_progress_detail->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Namaz</th>
                                <th>Progress</th>
                                <th>Count</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($namaz = $namaz_progress_detail->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($namaz['dua_name']); ?></td>
                                    <td>
                                        <div class="progress-bar" style="height: 12px;">
                                            <div class="progress-fill" style="width: <?php echo $namaz['progress_percentage']; ?>%; height: 12px; background: #8b5cf6;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $namaz['completed_count']; ?></td>
                                    <td><?php echo $namaz['target_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-center mt-2" style="padding-bottom: 1rem;">
            <a href="dua_tracking.php" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Add New Entry
            </a>
        </p>
    </div>

    <!-- Book Progress Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Istinsakh ul Kutub Progress</h3>
        </div>
        <div class="table-container">
            <?php if ($book_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $book_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td>
                                    <?php if ($book['status'] === 'completed'): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $book['started_date'] ? date('M d, Y', strtotime($book['started_date'])) : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center" style="padding: 1rem;">No books currently selected.</p>
            <?php endif; ?>
        </div>
        <p class="text-center mt-2" style="padding-bottom: 1rem;">
            <a href="book_transcription.php" class="btn btn-warning">
                <i class="fas fa-arrow-right"></i> Manage Books
            </a>
        </p>
    </div>
</div>

<script>
async function toggleMumbaiPresence(targetStatus) {
    const btn = document.getElementById('toggleMumbaiBtn');
    const badge = document.getElementById('mumbaiStatusBadge');
    const text = document.getElementById('mumbaiStatusText');

    btn.disabled = true;

    try {
        const response = await fetch('ajax_toggle_mumbai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `available_in_mumbai=${targetStatus}`
        });

        const result = await response.json();

        if (result.success) {
            const isAvail = result.available_in_mumbai;
            showToast(result.message, 'success');

            if (isAvail) {
                badge.className = 'badge badge-success';
                badge.innerHTML = '<i class="fas fa-map-marker-alt"></i> <span id="mumbaiStatusText">Available in Mumbai</span>';
                btn.className = 'btn btn-sm btn-secondary';
                btn.innerText = 'Mark Unavailable';
                btn.onclick = () => toggleMumbaiPresence(0);
            } else {
                badge.className = 'badge badge-secondary';
                badge.innerHTML = '<i class="fas fa-location-crosshairs"></i> <span id="mumbaiStatusText">Not in Mumbai</span>';
                btn.className = 'btn btn-sm btn-primary';
                btn.innerText = 'Mark Available';
                btn.onclick = () => toggleMumbaiPresence(1);
            }
        } else {
            showToast(result.message || 'Could not update Mumbai presence.', 'error');
        }
    } catch (e) {
        console.error('Error toggling Mumbai availability:', e);
        showToast('Connection error.', 'error');
    } finally {
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>