<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$attendanceMode = 'employee';
$isEmployeeMode = true;

$entitySingular = 'Employee';
$entityPlural = 'Employees';
$identifierLabel = 'Employee ID';
$primaryGroupLabel = 'Department';
$secondaryGroupLabel = 'Shift';
$modeQuery = '';

$pageTitle = $entitySingular . ' List';
$pageIcon = 'user-group';

// Get all entities with attendance stats
try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $departmentFilter = isset($_GET['department']) ? trim($_GET['department']) : '';
    $shiftFilter = isset($_GET['shift']) ? trim($_GET['shift']) : '';

        $sql = "SELECT e.id,
            e.employee_id,
            e.first_name,
            e.last_name,
            e.middle_name,
            e.gender,
            e.work_email AS email,
            e.department_code AS department,
            e.shift_code AS shift,
            e.qr_code,
            e.created_at,
            e.updated_at,
            COUNT(DISTINCT a.date) AS attendance_days,
            MAX(a.date) AS last_attendance
            FROM employees e
            LEFT JOIN employee_attendance a ON e.employee_id = a.employee_id
            WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.work_email LIKE ? OR e.shift_code LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 5, $searchTerm);
    }

    if (!empty($departmentFilter)) {
        $sql .= " AND e.department_code = ?";
        $params[] = $departmentFilter;
    }

    if (!empty($shiftFilter)) {
        $sql .= " AND e.shift_code = ?";
        $params[] = $shiftFilter;
    }

    $sql .= " GROUP BY e.id ORDER BY e.last_name, e.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique departments and shifts for filters
    $stmt = $pdo->query("SELECT DISTINCT department_code FROM employees WHERE department_code IS NOT NULL AND department_code != '' ORDER BY department_code");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT shift_code FROM employees WHERE shift_code IS NOT NULL AND shift_code != '' ORDER BY shift_code");
    $shifts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get total entity count
    $totalEmployees = count($employees);

} catch (Exception $e) {
    error_log("View " . strtolower($entityPlural) . " error: " . $e->getMessage());
    $employees = [];
    $departments = [];
    $shifts = [];
    $totalEmployees = 0;
}

$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php'],
    ['label' => $entityPlural, 'icon' => 'user-group', 'url' => 'view_employees.php']
];
$breadcrumbAction = ['label' => 'Add ' . $entitySingular, 'icon' => 'user-plus', 'url' => 'manage_employees.php'];
$pageDescription = 'View and manage all registered ' . strtolower($entityPlural);

include 'includes/header_modern.php';
?>

<style>
    /* ===== VIEW EMPLOYEES PAGE-SPECIFIC STYLES ===== */
    :root {
        /* Page-specific color aliases (mapped to new palette) */
        --asj-green-50: var(--green-050);
        --asj-green-100: var(--green-100);
        --asj-green-400: var(--green-500);
        --asj-green-500: var(--green-600);
        --asj-green-600: var(--green-600);
        --asj-green-700: var(--green-700);
        
        /* Semantic Colors */
        --success-light: #D1FAE5;
        --success: #10B981;
        --success-dark: #059669;
        --error-light: #FEE2E2;
        --error: #EF4444;
        --error-dark: #DC2626;
        --warning-light: #FEF3C7;
        --warning: #F59E0B;
        --warning-dark: #D97706;
        --info-light: #DBEAFE;
        --info: #3B82F6;
        --info-dark: #2563EB;
        
        /* Legacy compatibility */
        --primary-50: var(--green-050);
        --primary-100: var(--green-100);
        --primary-400: var(--green-500);
        --primary-500: var(--green-600);
        --primary-600: var(--green-600);
        --primary-700: var(--green-700);
        --accent-500: #FFC107;
        --accent-600: #FFB300;
        --blue-50: #DBEAFE;

        /* Transitions */
        --transition-fast: 150ms ease-in-out;
        --transition-base: 200ms ease-in-out;
        --transition-slow: 300ms ease-in-out;
    }

    /* Page Header Stats */
    .stats-bar {
        background: white;
        padding: var(--space-5);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-6);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--space-4);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3);
        border-radius: var(--radius-lg);
        transition: all var(--transition-base);
    }

    .stat-item:hover {
        background: var(--gray-50);
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-xl);
        background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: var(--text-xl);
        box-shadow: var(--shadow-md);
        flex-shrink: 0;
    }

    .stat-details {
        flex: 1;
        min-width: 0;
    }

    .stat-details h3 {
        font-size: var(--text-2xl);
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
        line-height: 1.2;
    }

    .stat-details p {
        font-size: var(--text-sm);
        color: var(--gray-600);
        margin: var(--space-1) 0 0;
        font-weight: 500;
    }

    /* Search and Filter Bar */
    .filter-bar {
        background: white;
        padding: var(--space-5);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
    }

    .filter-row {
        display: flex;
        gap: var(--space-3);
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-search {
        flex: 1;
        min-width: 280px;
        position: relative;
    }

    .filter-search input {
        padding-left: 48px;
        height: 44px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: var(--text-base);
        transition: all var(--transition-base);
    }

    .filter-search input:focus {
        outline: none;
        border-color: var(--asj-green-500);
        box-shadow: 0 0 0 3px rgba(30, 168, 91, 0.15);
    }

    .filter-search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
        font-size: var(--text-lg);
    }

    .filter-select {
        min-width: 180px;
    }

    .filter-select select {
        height: 44px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        font-size: var(--text-base);
        padding: 0 var(--space-4);
        transition: all var(--transition-base);
    }

    .filter-select select:focus {
        outline: none;
        border-color: var(--asj-green-500);
        box-shadow: 0 0 0 3px rgba(30, 168, 91, 0.15);
    }

    /* Responsive Table */
    .responsive-table-wrapper {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }

    .table-header {
        padding: var(--space-5);
        border-bottom: 2px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, var(--asj-green-50) 0%, var(--gray-50) 100%);
    }

    .table-title {
        font-size: var(--text-xl);
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .table-title i {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--asj-green-600);
        box-shadow: var(--shadow-sm);
    }

    /* Desktop Table */
    .desktop-table {
        width: 100%;
        display: table;
        table-layout: auto;
        min-width: 100%;
    }

    .desktop-table thead {
        background: var(--gray-50);
    }

    .desktop-table th {
        padding: var(--space-4);
        text-align: left;
        font-weight: 600;
        color: var(--gray-700);
        font-size: var(--text-sm);
        border-bottom: 2px solid var(--gray-200);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .desktop-table th:first-child {
        width: 130px;
    }

    .desktop-table th:nth-child(2) {
        width: auto;
        min-width: 180px;
    }

    .desktop-table th:nth-child(3) {
        width: 90px;
    }

    .desktop-table th:nth-child(4) {
        width: auto;
        min-width: 200px;
    }

    .desktop-table th:nth-child(5) {
        width: 120px;
    }

    .desktop-table th:last-child {
        width: 180px;
        text-align: center;
    }

    .desktop-table td {
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-100);
        font-size: var(--text-sm);
        vertical-align: middle;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .desktop-table tbody tr {
        transition: all 0.2s ease;
    }

    .desktop-table tbody tr:hover {
        background: var(--asj-green-50);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .desktop-table tr:last-child td {
        border-bottom: none;
    }

    /* Table wrapper with horizontal scroll on smaller screens */
    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 1200px) {
        .table-wrapper {
            margin: 0 calc(-1 * var(--space-4));
            padding: 0 var(--space-4);
        }
    }

    .employee-name {
        font-weight: 600;
        color: var(--gray-900);
        word-break: break-word;
    }

    .employee-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: var(--asj-green-600);
        white-space: nowrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: 4px 8px;
        border-radius: var(--radius-md);
        font-size: var(--text-xs);
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-primary {
        background: var(--asj-green-50);
        color: var(--asj-green-700);
    }

    .badge-error {
        background: var(--error-light);
        color: var(--error-dark);
    }

    .badge-info {
        background: var(--info-light);
        color: var(--info-dark);
    }

    .badge i {
        font-size: 10px;
    }

    /* Button Sizes */
    .btn-sm {
        padding: 6px 10px;
        font-size: var(--text-xs);
        border-radius: var(--radius-md);
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-1);
        border: none;
        cursor: pointer;
        transition: all var(--transition-base);
        text-decoration: none;
    }

    .btn-sm i {
        font-size: 12px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--asj-green-500), var(--asj-green-600));
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--asj-green-600), var(--asj-green-700));
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), var(--success-dark));
        color: white;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, var(--success-dark), #047857);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning), var(--warning-dark));
        color: white;
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, var(--warning-dark), var(--warning-dark));
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--error), var(--error-dark));
        color: white;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, var(--error-dark), #b91c1c);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    .btn-secondary:hover {
        background: var(--gray-300);
    }

    .btn-outline {
        background: white;
        border: 2px solid var(--gray-300);
        color: var(--gray-700);
    }

    .btn-outline:hover {
        background: var(--asj-green-50);
        border-color: var(--asj-green-500);
        color: var(--asj-green-700);
    }

    /* Mobile Cards */
    .mobile-cards {
        display: none;
    }

    .employee-card {
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-100);
        transition: all var(--transition-base);
    }

    .employee-card:last-child {
        border-bottom: none;
    }

    .employee-card:active {
        background: var(--gray-50);
    }

    .employee-card-header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }

    .employee-card-avatar {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-full);
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: var(--text-lg);
    }

    .employee-card-info h4 {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--gray-900);
        margin: 0 0 var(--space-1) 0;
    }

    .employee-card-info p {
        font-size: var(--text-sm);
        color: var(--gray-500);
        margin: 0;
    }

    .employee-card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }

    .employee-card-field {
        display: flex;
        flex-direction: column;
    }

    .employee-card-label {
        font-size: var(--text-xs);
        color: var(--gray-500);
        margin-bottom: var(--space-1);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .employee-card-value {
        font-size: var(--text-sm);
        color: var(--gray-900);
        font-weight: 500;
    }

    .employee-card-actions {
        display: flex;
        gap: var(--space-2);
    }

    .table-actions {
        display: flex;
        gap: var(--space-2);
        justify-content: center;
        flex-wrap: nowrap;
    }

    .table-actions .btn {
        min-width: 36px;
        padding: var(--space-2);
    }

    @media (max-width: 1200px) {
        .table-actions {
            gap: var(--space-1);
        }
        
        .table-actions .btn {
            min-width: 32px;
            padding: 6px;
            font-size: 12px;
        }
    }

    /* View Details Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: var(--space-4);
        animation: fadeIn 0.2s ease;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-container {
        width: 100%;
        max-width: 600px;
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-content {
        background: white;
        border-radius: var(--radius-2xl);
        box-shadow: var(--shadow-2xl);
        overflow: hidden;
    }

    .modal-header {
        padding: var(--space-5);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, var(--primary-50) 0%, var(--asj-green-100) 100%);
    }

    .modal-title {
        font-size: var(--text-xl);
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .modal-title i {
        color: var(--asj-green-600);
    }

    .modal-close {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-lg);
        border: none;
        background: white;
        color: var(--gray-600);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .modal-close:hover {
        background: var(--red-500);
        color: white;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: var(--space-6);
        max-height: 70vh;
        overflow-y: auto;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .detail-item.full-width {
        grid-column: 1 / -1;
    }

    .detail-label {
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--gray-900);
    }

    .qr-code-section {
        text-align: center;
        padding: var(--space-5);
        background: var(--gray-50);
        border-radius: var(--radius-xl);
        margin-bottom: var(--space-5);
    }

    .qr-code-container {
        display: inline-block;
        padding: var(--space-4);
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: var(--space-3);
    }

    .modal-actions {
        display: flex;
        gap: var(--space-3);
        padding-top: var(--space-4);
        border-top: 1px solid var(--gray-200);
    }

    /* Tablet and Mobile view */
    @media (max-width: 1024px) {
        .desktop-table th:nth-child(2),
        .desktop-table td:nth-child(2) {
            min-width: 150px;
        }
        
        .desktop-table th:nth-child(4),
        .desktop-table td:nth-child(4) {
            min-width: 180px;
        }
    }

    @media (max-width: 768px) {
        .desktop-table {
            display: none;
        }

        .table-wrapper {
            margin: 0;
            padding: 0;
        }

        .mobile-cards {
            display: block;
        }

        .filter-row {
            flex-direction: column;
        }

        .filter-search,
        .filter-select {
            width: 100%;
            min-width: 100%;
        }

        .stats-bar {
            grid-template-columns: 1fr;
        }

        .stat-item {
            padding: var(--space-4);
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .table-header {
            flex-direction: column;
            gap: var(--space-3);
            align-items: flex-start;
        }

        .table-header > div {
            width: 100%;
            justify-content: flex-start;
        }
    }

    @media (max-width: 480px) {
        .stats-bar {
            padding: var(--space-3);
            gap: var(--space-2);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            font-size: var(--text-lg);
        }

        .stat-details h3 {
            font-size: var(--text-xl);
        }

        .stat-details p {
            font-size: var(--text-xs);
        }
    }

    .empty-state {
        text-align: center;
        padding: var(--space-12);
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: var(--text-6xl);
        margin-bottom: var(--space-4);
        color: var(--gray-300);
    }

    .empty-state h3 {
        font-size: var(--text-xl);
        color: var(--gray-700);
        margin-bottom: var(--space-2);
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
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
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
    }

    @keyframes spin {
        to { transform: translateY(-50%) rotate(360deg); }
    }

    /* Notification System */
    .notification-container {
        position: fixed;
        top: var(--space-4);
        right: var(--space-4);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        max-width: 400px;
        pointer-events: none;
    }

    .alert {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        animation: slideInRight 0.3s ease;
        pointer-events: all;
        backdrop-filter: blur(10px);
        min-width: 300px;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
        color: white;
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
        color: white;
    }

    .alert-warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.95), rgba(217, 119, 6, 0.95));
        color: white;
    }

    .alert-info {
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.95), rgba(2, 132, 199, 0.95));
        color: white;
    }

    .alert-icon {
        font-size: var(--text-xl);
        flex-shrink: 0;
    }

    .alert-content {
        flex: 1;
        font-weight: 500;
    }

    .alert-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: var(--radius-md);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .alert-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }

    @media (max-width: 480px) {
        .notification-container {
            right: var(--space-2);
            left: var(--space-2);
            max-width: none;
        }

        .alert {
            min-width: auto;
        }
    }
</style>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $totalEmployees; ?></h3>
            <p>Total <?php echo $entityPlural; ?></p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fa-solid fa-chalkboard"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo count($departments); ?></h3>
            <p>Active Departments</p>
        </div>
    </div>
    <?php if (!empty($_GET['search']) || !empty($_GET['department'])): ?>
    <div class="stat-item">
        <div class="stat-icon">
            <i class="fa-solid fa-filter"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo count($employees); ?></h3>
            <p>Filtered Results</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="" id="filterForm">
        <?php if ($isEmployeeMode): ?>
            <input type="hidden" name="mode" value="employee">
        <?php endif; ?>
        <div class="filter-row">
            <div class="filter-search">
                <i class="fa-solid fa-search filter-search-icon"></i>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search by <?php echo $identifierLabel; ?>, name, email, or <?php echo strtolower($secondaryGroupLabel); ?>..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                >
            </div>
            <div class="filter-select">
                <select name="department" class="form-control">
                    <option value="">All <?php echo $primaryGroupLabel; ?></option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>" <?php echo (isset($_GET['department']) && $_GET['department'] === $department) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-select">
                <select name="shift" class="form-control">
                    <option value="">All <?php echo $secondaryGroupLabel; ?></option>
                    <?php foreach ($shifts as $shift): ?>
                        <option value="<?php echo htmlspecialchars($shift); ?>" <?php echo (isset($_GET['shift']) && $_GET['shift'] === $shift) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($shift); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-search"></i>
                <span>Search</span>
            </button>
            <?php if (!empty($_GET['search']) || !empty($_GET['department']) || !empty($_GET['shift'])): ?>
                <a href="view_employees.php" class="btn btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    <span>Clear</span>
                </a>
            <?php endif; ?>
            <a href="manage_employees.php" class="btn btn-success">
                <i class="fa-solid fa-plus"></i>
                <span>Add New <?php echo $entitySingular; ?></span>
            </a>
        </div>
    </form>
</div>

<!-- Table Wrapper -->
<div class="responsive-table-wrapper">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fa-solid fa-briefcase"></i>
            <?php echo $entityPlural; ?> Directory
        </h3>
        <div style="display: flex; gap: var(--space-2);">
            <button onclick="exportToCSV()" class="btn btn-outline">
                <i class="fa-solid fa-download"></i>
                <span>Export</span>
            </button>
        </div>
    </div>

    <?php if (empty($employees)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-user-slash"></i>
            <h3>No <?php echo $entityPlural; ?> Found</h3>
            <p>Try adjusting your search criteria or add a new <?php echo strtolower($entitySingular); ?>.</p>
            <a href="manage_employees.php" class="btn btn-primary" style="margin-top: var(--space-4);">
                <i class="fa-solid fa-plus"></i>
                <span>Add Your First <?php echo $entitySingular; ?></span>
            </a>
        </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="table-wrapper">
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th><?php echo $identifierLabel; ?></th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th><?php echo $primaryGroupLabel; ?></th>
                        <th><?php echo $secondaryGroupLabel; ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td>
                            <span class="employee-id"><?php echo sanitizeOutput($employee['employee_id']); ?></span>
                        </td>
                        <td>
                            <span class="employee-name"><?php echo sanitizeOutput(trim($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name'])); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $employee['gender'] === 'Male' ? 'primary' : 'error'; ?>">
                                <i class="fa-solid fa-<?php echo $employee['gender'] === 'Male' ? 'mars' : 'venus'; ?>"></i>
                                <?php echo sanitizeOutput($employee['gender']); ?>
                            </span>
                        </td>
                        <td><?php echo sanitizeOutput($employee['email']); ?></td>
                        <td>
                            <span class="badge badge-primary"><?php echo sanitizeOutput($employee['department'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo sanitizeOutput($employee['shift'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button 
                                    class="btn btn-sm btn-primary" 
                                    data-action="view"
                                    data-employee='<?php echo json_encode($employee); ?>'
                                    title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <a 
                                    href="manage_employees.php?id=<?php echo $employee['id']; ?>" 
                                    class="btn btn-sm btn-success" 
                                    title="Edit <?php echo $entitySingular; ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button 
                                    class="btn btn-sm btn-warning"
                                    data-action="qr"
                                    data-id="<?php echo $employee['id']; ?>"
                                    data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                    data-name="<?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?>"
                                    data-qrcode="<?php echo htmlspecialchars($employee['qr_code'] ?? ''); ?>"
                                    title="View QR Code">
                                    <i class="fa-solid fa-qrcode"></i>
                                </button>
                                <button 
                                    class="btn btn-sm btn-danger"
                                    data-action="delete"
                                    data-id="<?php echo $employee['id']; ?>"
                                    data-name="<?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?>"
                                    title="Delete <?php echo $entitySingular; ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-cards">
            <?php foreach ($employees as $employee): ?>
                <div class="employee-card">
                    <div class="employee-card-header">
                        <div class="employee-card-avatar">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                        </div>
                        <div class="employee-card-info">
                            <h4><?php echo sanitizeOutput(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?></h4>
                            <p><?php echo sanitizeOutput($employee['department']); ?></p>
                        </div>
                    </div>
                    <div class="employee-card-details">
                        <div class="employee-card-field">
                            <span class="employee-card-label"><?php echo $identifierLabel; ?></span>
                            <span class="employee-card-value"><?php echo sanitizeOutput($employee['employee_id']); ?></span>
                        </div>
                        <div class="employee-card-field">
                            <span class="employee-card-label">Gender</span>
                            <span class="employee-card-value">
                                <span class="badge badge-<?php echo $employee['gender'] === 'Male' ? 'primary' : 'error'; ?>">
                                    <i class="fa-solid fa-<?php echo $employee['gender'] === 'Male' ? 'mars' : 'venus'; ?>"></i>
                                    <?php echo sanitizeOutput($employee['gender']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="employee-card-field" style="grid-column: 1 / -1;">
                            <span class="employee-card-label">Email</span>
                            <span class="employee-card-value"><?php echo sanitizeOutput($employee['email']); ?></span>
                        </div>
                    </div>
                    <div class="employee-card-actions">
                        <button 
                            class="btn btn-sm btn-primary"
                            data-action="view"
                            data-employee='<?php echo json_encode($employee); ?>'
                            title="View Details">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <a 
                            href="manage_employees.php?id=<?php echo $employee['id']; ?>" 
                            class="btn btn-sm btn-success"
                            title="Edit <?php echo $entitySingular; ?>">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <button 
                            class="btn btn-sm btn-warning"
                            data-action="qr"
                            data-id="<?php echo $employee['id']; ?>"
                            data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                            data-name="<?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?>"
                            data-qrcode="<?php echo htmlspecialchars($employee['qr_code'] ?? ''); ?>"
                            title="View QR Code">
                            <i class="fa-solid fa-qrcode"></i>
                        </button>
                        <button 
                            class="btn btn-sm btn-danger"
                            data-action="delete"
                            data-id="<?php echo $employee['id']; ?>"
                            data-name="<?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?>"
                            title="Delete <?php echo $entitySingular; ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- View Entity Details Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-user-graduate"></i>
                    <?php echo $entitySingular; ?> Details
                </h3>
                <button class="modal-close" data-action="close-modal" data-modal="viewModal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo $entitySingular; ?> Record ID</span>
                        <span class="detail-value" id="view-id">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo $identifierLabel; ?></span>
                        <span class="detail-value" id="view-employee-id">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">First Name</span>
                        <span class="detail-value" id="view-firstname">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Middle Name</span>
                        <span class="detail-value" id="view-middlename">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Name</span>
                        <span class="detail-value" id="view-lastname">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Gender</span>
                        <span class="detail-value" id="view-gender">-</span>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">Email Address</span>
                        <span class="detail-value" id="view-email">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Department / Shift</span>
                        <span class="detail-value" id="view-department-shift">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Attendance Days</span>
                        <span class="detail-value" id="view-attendance">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Registration Date</span>
                        <span class="detail-value" id="view-created">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Attendance</span>
                        <span class="detail-value" id="view-last-attendance">-</span>
                    </div>
                </div>
                <div class="modal-actions">
                    <a href="#" id="editEmployeeLink" class="btn btn-primary">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Edit <?php echo $entitySingular; ?></span>
                    </a>
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="viewModal">
                        <i class="fa-solid fa-times"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-qrcode"></i>
                    <?php echo $entitySingular; ?> QR Code
                </h3>
                <button class="modal-close" data-action="close-modal" data-modal="qrModal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="qr-code-section">
                    <h4 id="qr-employee-name" style="margin-bottom: var(--space-3); color: var(--gray-900); text-align: center;">-</h4>
                    <div class="qr-code-container" style="text-align: center; padding: var(--space-6); background: var(--gray-50); border-radius: var(--radius-xl);">
                        <img id="qr-code-image" src="" alt="QR Code" style="max-width: 300px; width: 100%; height: auto; border: 4px solid white; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);">
                        <p style="color: var(--gray-600); margin-top: var(--space-4); font-size: var(--text-sm); font-style: italic;">
                            Scan this QR code for attendance
                        </p>
                    </div>
                    <p style="color: var(--gray-600); margin-top: var(--space-4); text-align: center;">
                        <?php echo $identifierLabel; ?>: <strong id="qr-employee-id" style="color: var(--primary-600); font-family: 'Courier New', monospace;">-</strong>
                    </p>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-warning" data-action="regenerate-qr" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Regenerate</span>
                    </button>
                    <button class="btn btn-success" data-action="download-qr">
                        <i class="fa-solid fa-download"></i>
                        <span>Download</span>
                    </button>
                    <button class="btn btn-primary" data-action="print-qr">
                        <i class="fa-solid fa-print"></i>
                        <span>Print</span>
                    </button>
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="qrModal">
                        <i class="fa-solid fa-times"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-body" style="text-align: center; padding: var(--space-8);">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-5);">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 32px; color: var(--red-500);"></i>
                </div>
                <h3 style="font-size: var(--text-2xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-3);">Delete <?php echo $entitySingular; ?></h3>
                <p style="color: var(--gray-600); margin-bottom: var(--space-2);">
                    Are you sure you want to delete <strong id="delete-employee-name">this <?php echo strtolower($entitySingular); ?></strong>?
                </p>
                <p style="color: var(--red-600); font-weight: 600; font-size: var(--text-sm); margin-bottom: var(--space-6);">
                     This will permanently delete the <?php echo strtolower($entitySingular); ?> and all attendance records.
                </p>
                <div class="modal-actions" style="justify-content: center;">
                    <button class="btn btn-secondary" data-action="close-modal" data-modal="deleteModal" style="min-width: 140px;">
                        <i class="fa-solid fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button class="btn btn-danger" data-action="confirm-delete" id="confirmDeleteBtn" style="min-width: 140px;">
                        <i class="fa-solid fa-trash"></i>
                        <span>Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * View Employees - Modal and Button Functionality
 */

// State management
const attendanceMode = '<?php echo $attendanceMode; ?>';
const isEmployeeMode = attendanceMode === 'employee';
const entitySingular = '<?php echo $entitySingular; ?>';
const entityPlural = '<?php echo $entityPlural; ?>';
const entityLower = entitySingular.toLowerCase();
const identifierLabel = '<?php echo $identifierLabel; ?>';

let currentDeleteId = null;
let currentDeleteName = '';

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// View Employee Details
function viewEmployeeDetails(employee) {
    // Populate modal fields
    document.getElementById('view-id').textContent = '#' + String(employee.id).padStart(5, '0');
    document.getElementById('view-employee-id').textContent = employee.employee_id || '-';
    document.getElementById('view-firstname').textContent = employee.first_name || '-';
    document.getElementById('view-middlename').textContent = employee.middle_name || '-';
    document.getElementById('view-lastname').textContent = employee.last_name || '-';
    document.getElementById('view-gender').textContent = employee.gender || '-';
    document.getElementById('view-email').textContent = employee.email || '-';
    document.getElementById('view-department-shift').textContent = `${employee.department || '-'} / ${employee.shift || '-'}`;
    document.getElementById('view-attendance').textContent = employee.attendance_days || '0';
    
    // Format dates
    if (employee.created_at) {
        const createdDate = new Date(employee.created_at);
        document.getElementById('view-created').textContent = createdDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } else {
        document.getElementById('view-created').textContent = '-';
    }
    
    if (employee.last_attendance) {
        const lastDate = new Date(employee.last_attendance);
        document.getElementById('view-last-attendance').textContent = lastDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } else {
        document.getElementById('view-last-attendance').textContent = 'Never';
    }
    
    // Update edit link
    document.getElementById('editEmployeeLink').href = 'manage_employees.php?id=' + employee.id;
    
    openModal('viewModal');
}

// State for QR operations
let currentQREmployeeRecordId = null;
let currentQREmployeeId = null;

function resolveQrImageUrl(qrPathOrUrl) {
    if (!qrPathOrUrl || typeof qrPathOrUrl !== 'string') {
        return null;
    }

    const normalized = qrPathOrUrl.trim();
    if (!normalized || normalized.includes('|')) {
        return null;
    }

    if (normalized.startsWith('http://') || normalized.startsWith('https://') || normalized.startsWith('data:')) {
        return normalized;
    }

    if (normalized.startsWith('../')) {
        return `${normalized.split('?')[0]}?v=${Date.now()}`;
    }

    if (normalized.startsWith('uploads/')) {
        return `../${normalized.split('?')[0]}?v=${Date.now()}`;
    }

    return `${normalized.split('?')[0]}?v=${Date.now()}`;
}

// Generate QR Code - Now displays the pre-generated image
function generateQRCode(employeeRecordId, employeeId, name, qrCodePath) {
    currentQREmployeeRecordId = employeeRecordId;
    currentQREmployeeId = employeeId;
    
    document.getElementById('qr-employee-name').textContent = name;
    document.getElementById('qr-employee-id').textContent = employeeId;
    
    const qrImage = document.getElementById('qr-code-image');
    const resolvedUrl = resolveQrImageUrl(qrCodePath);
    
    if (resolvedUrl) {
        // Display the pre-generated QR code
        qrImage.src = resolvedUrl;
        qrImage.style.display = 'block';
        qrImage.alt = 'QR Code for ' + name;
    } else {
        // No QR code available
        qrImage.style.display = 'none';
        showNotification('QR code not available for this ' + entityLower + '. Please regenerate.', 'warning');
    }
    
    openModal('qrModal');
}

// Regenerate QR Code
async function regenerateQRCode() {
    if (!currentQREmployeeRecordId) {
        showNotification('No ' + entityLower + ' selected', 'error');
        return;
    }
    
    const regenerateBtn = document.querySelector('[data-action="regenerate-qr"]');
    const originalText = regenerateBtn.innerHTML;
    regenerateBtn.disabled = true;
    regenerateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Regenerating...</span>';
    
    try {
        const response = await fetch('../api/regenerate_qrcode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ record_id: currentQREmployeeRecordId, mode: 'employee' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the QR code image
            const qrImage = document.getElementById('qr-code-image');
            const nextUrl = resolveQrImageUrl(data.qr_code_url || data.qr_code_path || '');

            if (nextUrl) {
                qrImage.src = nextUrl;
                qrImage.style.display = 'block';
            } else {
                qrImage.style.display = 'none';
                showNotification('QR regenerated, but image path could not be resolved.', 'warning');
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Failed to regenerate QR code', 'error');
        }
    } catch (error) {
        console.error('Regenerate QR error:', error);
        showNotification('Network error. Please try again.', 'error');
    } finally {
        regenerateBtn.disabled = false;
        regenerateBtn.innerHTML = originalText;
    }
}

// Download QR Code
function downloadQRCode() {
    const qrImage = document.getElementById('qr-code-image');
    if (qrImage && qrImage.src) {
        const employeeId = document.getElementById('qr-employee-id').textContent;
        const name = document.getElementById('qr-employee-name').textContent;
        
        // Create a temporary link to download the image
        const link = document.createElement('a');
        link.href = qrImage.src;
        link.download = `QR_${employeeId}_${name.replace(/\s+/g, '_')}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('QR code downloaded successfully!', 'success');
    } else {
        showNotification('No QR code available to download', 'error');
    }
}

// Print QR Code
function printQRCode() {
    const qrImage = document.getElementById('qr-code-image');
    if (qrImage && qrImage.src) {
        const employeeId = document.getElementById('qr-employee-id').textContent;
        const name = document.getElementById('qr-employee-name').textContent;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${entitySingular} QR Code - ${name}</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    h1 { font-size: 28px; margin-bottom: 10px; color: #333; }
                    p { font-size: 18px; color: #666; margin: 5px 0; }
                    .qr-container { 
                        margin: 20px 0; 
                        padding: 30px; 
                        border: 3px solid #10B981; 
                        border-radius: 15px;
                        background: #f9fafb;
                    }
                    .qr-container img {
                        max-width: 300px;
                        width: 100%;
                        height: auto;
                    }
                    .scan-text {
                        font-size: 14px;
                        color: #059669;
                        font-weight: bold;
                        margin-top: 15px;
                        font-style: italic;
                    }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <h1>${name}</h1>
                <p><strong>${identifierLabel}:</strong> ${employeeId}</p>
                <div class="qr-container">
                    <img src="${qrImage.src}" alt="QR Code" />
                </div>
                <p class="scan-text">Scan this QR code for attendance</p>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    } else {
        showNotification('No QR code available to print', 'error');
    }
}

// Show Delete Modal
function showDeleteModal(id, name) {
    currentDeleteId = id;
    currentDeleteName = name;
    document.getElementById('delete-employee-name').textContent = name;
    openModal('deleteModal');
}

// Confirm Delete
async function confirmDelete() {
    if (!currentDeleteId) return;
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.classList.add('btn-loading');
    confirmBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('employee_id', currentDeleteId);
        formData.append('mode', 'employee');
        
        const response = await fetch('../api/delete_employee.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(entitySingular + ' deleted successfully!', 'success');
            closeModal('deleteModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || ('Failed to delete ' + entityLower), 'error');
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.disabled = false;
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Network error. Please try again.', 'error');
        confirmBtn.classList.remove('btn-loading');
        confirmBtn.disabled = false;
    }
}

// Notification System
function showNotification(message, type = 'info') {
    let container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fa-solid fa-${icons[type] || 'info-circle'}"></i>
        </div>
        <div class="alert-content">${message}</div>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fa-solid fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Export to CSV
function exportToCSV() {
    const table = document.querySelector('.desktop-table');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach((col, index) => {
            // Skip the last column (Actions)
            if (index < cols.length - 1) {
                let text = col.textContent.trim().replace(/\s+/g, ' ');
                // Escape quotes and wrap in quotes if contains comma
                text = text.replace(/"/g, '""');
                if (text.includes(',') || text.includes('"')) {
                    text = `"${text}"`;
                }
                csvRow.push(text);
            }
        });
        csv.push(csvRow.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const date = new Date().toISOString().split('T')[0];
    link.setAttribute('href', url);
    link.setAttribute('download', `employees_list_${date}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification(entitySingular + ' list exported successfully!', 'success');
}

// Event Delegation
document.addEventListener('DOMContentLoaded', function() {
    // Button click handlers
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'view') {
            try {
                const employee = JSON.parse(target.dataset.employee);
                viewEmployeeDetails(employee);
            } catch (error) {
                console.error('Error parsing employee data:', error);
                showNotification('Error loading ' + entityLower + ' details', 'error');
            }
        } else if (action === 'qr') {
            const employeeRecordId = target.dataset.id;
            const employeeId = target.dataset.employeeId;
            const name = target.dataset.name;
            const qrCodePath = target.dataset.qrcode;
            generateQRCode(employeeRecordId, employeeId, name, qrCodePath);
        } else if (action === 'regenerate-qr') {
            regenerateQRCode();
        } else if (action === 'delete') {
            const id = target.dataset.id;
            const name = target.dataset.name;
            showDeleteModal(id, name);
        } else if (action === 'close-modal') {
            const modalId = target.dataset.modal;
            closeModal(modalId);
        } else if (action === 'confirm-delete') {
            confirmDelete();
        } else if (action === 'download-qr') {
            downloadQRCode();
        } else if (action === 'print-qr') {
            printQRCode();
        }
    });
    
    // Close modals on outside click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('viewModal');
            closeModal('qrModal');
            closeModal('deleteModal');
        }
    });
    
    console.log(' View ' + entityPlural + ' functionality initialized');
});
</script>

<?php include 'includes/footer_modern.php'; ?>

