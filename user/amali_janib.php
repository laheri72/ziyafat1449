<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Amali Janib';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get Quran progress
$quran_progress = get_quran_progress($conn, $user_id);

// Get Dua progress by category
$dua_progress = get_dua_progress($conn, $user_id, 'dua');
$tasbeeh_progress = get_dua_progress($conn, $user_id, 'tasbeeh');
$namaz_progress = get_dua_progress($conn, $user_id, 'namaz');

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

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-hands-praying"></i>Hadayah Amaliyah</h1>
        <p>Track your Quran recitation, Dua progress, and Istinsakh</p>
    </div>

      <!-- Quick Navigation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-compass"></i> Quick Navigation</h3>
        </div>
        <div class="action-buttons">
            <a href="quran_tracking.php" class="btn btn-primary">
                <i class="fas fa-quran"></i> Tilawat Ul Quran Hifzan
            </a>
            <a href="dua_tracking.php" class="btn btn-success">
                <i class="fas fa-hands-praying"></i> Dua Tracking
            </a>
            <a href="book_transcription.php" class="btn btn-warning">
                <i class="fas fa-book"></i> Istinsakh Ul Kutub
            </a>
        </div>
    </div>

    <!-- Overall Summary -->
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
                <h4>Kutub In Progress</h4>
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $summary['books_in_progress'] ?? 0; ?></div>
            <div class="stat-label">Current Istinsakh</div>
        </div>
    </div>

  

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

    <!-- Dua Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hands-praying"></i> Dua Progress</h3>
        </div>
        <div class="table-container">
            <?php if ($dua_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dua Name</th>
                            <th>Arabic Name</th>
                            <th>Progress</th>
                            <th>Completed</th>
                            <th>Target</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($dua = $dua_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dua['dua_name']); ?></td>
                                <td dir="rtl"><?php echo htmlspecialchars($dua['dua_name_arabic']); ?></td>
                                <td>
                                    <div class="progress-bar" style="height: 20px;">
                                        <div class="progress-fill" style="width: <?php echo $dua['progress_percentage']; ?>%; height: 20px; font-size: 12px;">
                                            <?php echo $dua['progress_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $dua['completed_count']; ?></td>
                                <td><?php echo $dua['target_count']; ?></td>
                                <td><?php echo $dua['last_updated'] ? date('M d, Y', strtotime($dua['last_updated'])) : 'Not started'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No duas available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tasbeeh Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-dharmachakra"></i> Tasbeeh Progress</h3>
        </div>
        <div class="table-container">
            <?php if ($tasbeeh_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tasbeeh Name</th>
                            <th>Arabic Name</th>
                            <th>Progress</th>
                            <th>Completed</th>
                            <th>Target</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($tasbeeh = $tasbeeh_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tasbeeh['dua_name']); ?></td>
                                <td dir="rtl"><?php echo htmlspecialchars($tasbeeh['dua_name_arabic']); ?></td>
                                <td>
                                    <div class="progress-bar" style="height: 20px;">
                                        <div class="progress-fill" style="width: <?php echo $tasbeeh['progress_percentage']; ?>%; height: 20px; font-size: 12px; background: linear-gradient(90deg, #f59e0b, #d97706);">
                                            <?php echo $tasbeeh['progress_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $tasbeeh['completed_count']; ?></td>
                                <td><?php echo $tasbeeh['target_count']; ?></td>
                                <td><?php echo $tasbeeh['last_updated'] ? date('M d, Y', strtotime($tasbeeh['last_updated'])) : 'Not started'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No tasbeeh available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Namaz Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-mosque"></i> Namaz Progress</h3>
        </div>
        <div class="table-container">
            <?php if ($namaz_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Namaz</th>
                            <th>Arabic Name</th>
                            <th>Progress</th>
                            <th>Completed</th>
                            <th>Target</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($namaz = $namaz_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($namaz['dua_name']); ?></td>
                                <td dir="rtl"><?php echo htmlspecialchars($namaz['dua_name_arabic']); ?></td>
                                <td>
                                    <div class="progress-bar" style="height: 20px;">
                                        <div class="progress-fill" style="width: <?php echo $namaz['progress_percentage']; ?>%; height: 20px; font-size: 12px; background: linear-gradient(90deg, #8b5cf6, #7c3aed);">
                                            <?php echo $namaz['progress_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $namaz['completed_count']; ?></td>
                                <td><?php echo $namaz['target_count']; ?></td>
                                <td><?php echo $namaz['last_updated'] ? date('M d, Y', strtotime($namaz['last_updated'])) : 'Not started'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No namaz tracking available.</p>
            <?php endif; ?>
        </div>
        <p class="text-center mt-2">
            <a href="dua_tracking.php" class="btn btn-success">
                <i class="fas fa-arrow-right"></i> Update All Progress
            </a>
        </p>
    </div>

    <!-- Book Transcription Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i>Istinsakh ul Kutub</h3>
        </div>
        <div class="table-container">
            <?php if ($book_progress->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>Progress</th>
                            <th>Pages</th>
                            <th>Status</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $book_progress->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td>-</td>
                                <td>-</td>
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
                <p class="text-center">No books available.</p>
            <?php endif; ?>
        </div>
        <p class="text-center mt-2">
            <a href="book_transcription.php" class="btn btn-warning">
                <i class="fas fa-arrow-right"></i> Update Book Progress
            </a>
        </p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>