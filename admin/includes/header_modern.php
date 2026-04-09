<?php
// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}


$entityPluralNav = 'Employees';
$reportsLabelNav = 'Employee Reports';

$dashboardUrlNav = 'dashboard.php';
$employeesUrlNav = 'view_employees.php';
$departmentsUrlNav = 'manage_departments.php';
$manualUrlNav = 'manual_attendance.php';
$reportsUrlNav = 'attendance_reports_departments.php';
$scannerUrlNav = '../scan_attendance.php';
$manageEmployeesUrlNav = 'manage_employees.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#178a4a">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin - Employee Attendance System</title>
    
    <!-- Fonts: Manrope (UI) + Space Grotesk (numbers) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6.4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin Green Design System (replaces legacy CSS) -->
    <link rel="stylesheet" href="../css/admin-green.css?v=<?php echo filemtime(__DIR__ . '/../../css/admin-green.css'); ?>">
    
    <!-- Chart.js for dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="admin-body">

    <div class="admin-layout">
        <!-- Desktop Sidebar  Slim pill/rail -->
        <aside class="admin-sidebar" id="adminSidebar">
            <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar" title="Collapse sidebar">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon" aria-hidden="true">
                        <img src="../assets/image/logo.png" alt="Employee Attendance logo" class="sidebar-logo-img">
                    </div>
                    <div class="sidebar-logo-text">
                        <span class="sidebar-logo-title">EAT</span>
                        <span class="sidebar-logo-sub">Admin Panel</span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav" aria-label="Admin navigation">
                <div class="sidebar-nav-label">Main</div>
                <a href="<?php echo $dashboardUrlNav; ?>" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" aria-label="Dashboard">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-house"></i></div>
                    <span>Dashboard</span>
                </a>
                
                <div class="sidebar-nav-label">Management</div>
                <a href="<?php echo $employeesUrlNav; ?>" class="sidebar-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['view_employees.php', 'manage_employees.php'])) ? 'active' : ''; ?>" aria-label="<?php echo $entityPluralNav; ?>">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-user-group"></i></div>
                    <span><?php echo $entityPluralNav; ?></span>
                </a>
                <a href="<?php echo $departmentsUrlNav; ?>" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_departments.php') ? 'active' : ''; ?>" aria-label="Departments and Shifts">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-table-cells-large"></i></div>
                    <span>Departments and Shifts</span>
                </a>
                
                <div class="sidebar-nav-label">Attendance</div>
                <a href="<?php echo $manualUrlNav; ?>" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manual_attendance.php') ? 'active' : ''; ?>" aria-label="Manual Entry">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <span>Manual Entry</span>
                </a>
                <a href="<?php echo $reportsUrlNav; ?>" class="sidebar-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['attendance_reports_departments.php', 'attendance_reports_departments.php'])) ? 'active' : ''; ?>" aria-label="<?php echo $reportsLabelNav; ?>">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-chart-column"></i></div>
                    <span><?php echo $reportsLabelNav; ?></span>
                </a>
                
                <div class="sidebar-nav-label">Quick Actions</div>
                <a href="<?php echo $scannerUrlNav; ?>" class="sidebar-link" target="_blank" aria-label="QR Scanner">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-qrcode"></i></div>
                    <span>QR Scanner</span>
                </a>
                <a href="../index.php" class="sidebar-link" target="_blank" aria-label="View Site">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                    <span>View Site</span>
                </a>
                <a href="logout.php" class="sidebar-link sidebar-link-danger" aria-label="Logout">
                    <div class="sidebar-link-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                    <span>Logout</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="sidebar-profile">
                    <div class="sidebar-avatar">
                        <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                    </div>
                    <div class="sidebar-profile-info">
                        <span class="sidebar-profile-name"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['username']) : 'Admin'; ?></span>
                        <span class="sidebar-profile-role"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['role']) : 'Administrator'; ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Bar -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="topbar-menu-btn" onclick="toggleAdminMenu()" aria-label="Toggle navigation">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h1 class="topbar-title">
                        <i class="fa-solid fa-<?php echo isset($pageIcon) ? $pageIcon : 'house'; ?>"></i>
                        <?php echo isset($pageTitle) ? sanitizeOutput($pageTitle) : 'Dashboard'; ?>
                    </h1>
                    <div class="topbar-search">
                        <i class="fa-solid fa-magnifying-glass topbar-search-icon"></i>
                        <input type="text" class="topbar-search-input" placeholder="Search pages..." aria-label="Search pages" autocomplete="off">
                        <div class="topbar-search-results" role="listbox"></div>
                    </div>
                </div>
                <div class="topbar-right">
                    <a href="<?php echo $scannerUrlNav; ?>" class="topbar-action-btn" target="_blank" title="Open QR Scanner">
                        <i class="fa-solid fa-qrcode"></i>
                        <span>Scanner</span>
                    </a>
                    <div class="topbar-notif">
                        <button class="topbar-notif-btn" onclick="toggleNotifDropdown(event)" aria-label="Notifications">
                            <i class="fa-solid fa-bell"></i>
                            <span class="topbar-notif-badge">0</span>
                        </button>
                        <div class="topbar-notif-dropdown" role="menu">
                            <div class="topbar-notif-dropdown-header">
                                <span class="topbar-notif-dropdown-title">Notifications</span>
                                <button class="topbar-notif-mark-read">Mark all read</button>
                            </div>
                            <div class="topbar-notif-dropdown-body">
                                <div class="topbar-notif-empty">
                                    <i class="fa-solid fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="topbar-user-menu">
                        <button class="topbar-user-btn" onclick="toggleUserDropdown(event)" aria-label="User menu" aria-expanded="false">
                            <div class="topbar-avatar">
                                <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                            </div>
                            <div class="topbar-user-info">
                                <span class="topbar-user-name"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['username']) : 'Admin'; ?></span>
                                <span class="topbar-user-role"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['role']) : 'Administrator'; ?></span>
                            </div>
                            <i class="fa-solid fa-chevron-down topbar-user-chevron"></i>
                        </button>
                        <div class="topbar-dropdown" role="menu">
                            <div class="topbar-dropdown-header">
                                <div class="topbar-dropdown-avatar">
                                    <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                                </div>
                                <div>
                                    <div class="topbar-dropdown-name"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['username']) : 'Admin'; ?></div>
                                    <div class="topbar-dropdown-role"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['role']) : 'Administrator'; ?></div>
                                </div>
                            </div>
                            <div class="topbar-dropdown-divider"></div>
                            <a href="<?php echo $dashboardUrlNav; ?>" class="topbar-dropdown-item" role="menuitem">
                                <i class="fa-solid fa-gauge"></i> Dashboard
                            </a>
                            <div class="topbar-dropdown-divider"></div>
                            <a href="logout.php" class="topbar-dropdown-item topbar-dropdown-danger" role="menuitem">
                                <i class="fa-solid fa-right-from-bracket"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Hero Banner -->
            <?php if (isset($breadcrumb) && is_array($breadcrumb) && count($breadcrumb) > 0): ?>
            <section class="page-hero" id="breadcrumbBar">
                <div class="page-hero-inner">
                    <!-- Breadcrumb trail -->
                    <nav class="hero-breadcrumb" aria-label="Breadcrumb">
                        <ol role="list">
                            <?php foreach ($breadcrumb as $i => $crumb): ?>
                                <li>
                                    <?php if ($i === count($breadcrumb) - 1): ?>
                                        <span aria-current="page">
                                            <?php if (!empty($crumb['icon'])): ?><i class="fa-solid fa-<?php echo $crumb['icon']; ?>"></i><?php endif; ?>
                                            <?php echo sanitizeOutput($crumb['label']); ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo $crumb['url']; ?>">
                                            <?php if (!empty($crumb['icon'])): ?><i class="fa-solid fa-<?php echo $crumb['icon']; ?>"></i><?php endif; ?>
                                            <?php echo sanitizeOutput($crumb['label']); ?>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <!-- Hero main row -->
                    <div class="hero-main">
                        <div class="hero-left">
                            <div class="hero-icon">
                                <i class="fa-solid fa-<?php echo isset($pageIcon) ? $pageIcon : 'house'; ?>"></i>
                            </div>
                            <div>
                                <h1 class="hero-title"><?php echo isset($pageTitle) ? sanitizeOutput($pageTitle) : 'Dashboard'; ?></h1>
                                <?php if (isset($pageDescription)): ?>
                                    <p class="hero-desc"><i class="fa-solid fa-circle-info"></i> <?php echo sanitizeOutput($pageDescription); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (isset($breadcrumbAction)): ?>
                        <div class="hero-actions">
                            <a href="<?php echo $breadcrumbAction['url']; ?>" class="hero-action hero-action--primary" <?php echo !empty($breadcrumbAction['target']) ? 'target="' . $breadcrumbAction['target'] . '"' : ''; ?> <?php echo !empty($breadcrumbAction['onclick']) ? 'onclick="' . $breadcrumbAction['onclick'] . '"' : ''; ?>>
                                <?php if (!empty($breadcrumbAction['icon'])): ?><i class="fa-solid fa-<?php echo $breadcrumbAction['icon']; ?>"></i><?php endif; ?>
                                <?php echo sanitizeOutput($breadcrumbAction['label']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Content Wrapper -->
            <div class="content-wrapper">


