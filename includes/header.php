<?php
require_once __DIR__ . '/functions.php';
init_session();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Ziyafat us Shukr</title>
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '../assets/css/'; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Check sidebar state before page renders to prevent flicker
        (function() {
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed' && window.innerWidth > 1024) {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        })();
    </script>
</head>

<body>
    <?php if (is_logged_in()): ?>
        <div class="app-layout">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <script>
                    if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 1024) {
                        document.getElementById('sidebar').classList.add('collapsed');
                    }
                </script>
                <div class="sidebar-header">
                    <div class="sidebar-brand">
                        <div class="sidebar-brand-logo" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; background-color: BLUE; color: white; border-radius: 8px;">
                            11
                        </div>

                        <div class="sidebar-brand-text">
                            <h2>Ziyafat us Shukr</h2>
                            <p>Management System</p>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <?php if (is_admin()): ?>
                        <!-- Admin Navigation -->
                        <div class="nav-section">
                            <div class="nav-section-title">Main</div>
                            <div class="nav-item">
                                <a href="../admin/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i>
                                    <span>Dashboard</span>
                                </a>
                            </div>

                            <!-- User Portal Access for Admin -->
                            <div class="nav-item">
                                <a href="../user/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'user/index.php') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-user-circle"></i>
                                    <span>My Portal</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/amali_janib.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'user/amali_janib.php') !== false || strpos($_SERVER['PHP_SELF'], 'user/quran_tracking.php') !== false || strpos($_SERVER['PHP_SELF'], 'user/dua_tracking.php') !== false || strpos($_SERVER['PHP_SELF'], 'user/book_transcription.php') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-hands-praying"></i>
                                    <span>My Amali Janib</span>
                                </a>
                            </div>
                        </div>

                        <?php if (has_finance_access()): ?>
                            <div class="nav-section">
                                <div class="nav-section-title">Finance Management</div>
                                <div class="nav-item">
                                    <a href="../admin/view_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_users.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-users"></i>
                                        <span>Users</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/add_user.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_user.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-user-plus"></i>
                                        <span>Add User</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/add_contribution.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_contribution.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-plus-circle"></i>
                                        <span>Add Contribution</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Financial Reports</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (has_amali_access()): ?>
                            <div class="nav-section">
                                <div class="nav-section-title">Amali Janib</div>
                                <div class="nav-item">
                                    <a href="../admin/manage_duas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_duas.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-hands-praying"></i>
                                        <span>Manage Duas</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/manage_books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_books.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-book"></i>
                                        <span>Manage Kutub</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/amali_reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'amali_reports.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-chart-bar"></i>
                                        <span>Amali Reports</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- User Navigation -->
                        <div class="nav-section">
                            <div class="nav-section-title">Main</div>
                            <div class="nav-item">
                                <a href="../user/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i>
                                    <span>Dashboard</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </div>
                            <?php 
                            // Show Surat Finance Report only for Surat category users
                            $current_user = get_user_by_id($GLOBALS['conn'], $_SESSION['user_id']);
                            if ($current_user['category'] === 'Surat'): 
                            ?>
                            <div class="nav-item">
                                <a href="../user/surat_finance_report.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'surat_finance_report.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Finance Report</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="nav-section">
                            <div class="nav-section-title">Amali Janib</div>
                            <div class="nav-item">
                                <a href="../user/amali_janib.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'amali_janib.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-tasks"></i>
                                    <span>Overview</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/quran_tracking.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quran_tracking.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-quran"></i>
                                    <span>Quran Tracking</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/dua_tracking.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dua_tracking.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-hands-praying"></i>
                                    <span>Dua Tracking</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/book_transcription.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'book_transcription.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-book-open"></i>
                                    <span>Istinsakh Ul Kutub</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="nav-section">
                        <div class="nav-section-title">Account</div>
                        <div class="nav-item">
                            <a href="../auth/logout.php" class="nav-link">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </nav>
            </aside>

            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Toast Container -->
            <div id="toast-container" class="toast-container"></div>

            <!-- Main Wrapper -->
            <div class="main-wrapper">
                <!-- Topbar -->
                <header class="topbar">
                    <div class="topbar-left">
                        <button class="menu-toggle" id="menuToggle">
                            <i class="fas fa-bars"></i>
                        </button>

                        <div class="topbar-search">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search...">
                        </div>
                    </div>

                    <div class="topbar-right">
                        <div class="topbar-user">
                            <div class="user-avatar">
                                <?php
                                $logged_in_user = get_user_by_id($GLOBALS['conn'], $_SESSION['user_id']);
                                echo strtoupper(substr($logged_in_user['name'], 0, 2));
                                ?>
                            </div>
                            <div class="user-info">
                                <h4><?php echo htmlspecialchars($logged_in_user['name']); ?></h4>
                                <p><?php echo is_admin() ? 'Administrator' : 'User'; ?></p>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="main-content">
                <script>
                    /**
                     * Global Toast Notification System
                     * @param {string} message 
                     * @param {string} type - 'success', 'error', 'warning', 'info'
                     */
                    function showToast(message, type = 'info') {
                        const container = document.getElementById('toast-container');
                        if (!container) return;
                        
                        const toast = document.createElement('div');
                        toast.className = `toast-item ${type}`;
                        
                        let icon = 'info-circle';
                        if (type === 'success') icon = 'check-circle';
                        if (type === 'error') icon = 'exclamation-circle';
                        if (type === 'warning') icon = 'exclamation-triangle';

                        toast.innerHTML = `
                            <i class="fas fa-${icon}"></i>
                            <div class="toast-message">${message}</div>
                        `;
                        
                        container.appendChild(toast);
                        
                        // Auto remove after 4 seconds
                        setTimeout(() => {
                            toast.style.opacity = '0';
                            toast.style.transform = 'translateX(100%)';
                            toast.style.transition = 'all 0.5s ease';
                            setTimeout(() => toast.remove(), 500);
                        }, 4000);
                    }
                </script>
    <?php else: ?>
        <!-- Login page layout (no sidebar) -->
    <?php endif; ?>