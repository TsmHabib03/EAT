<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manual Attendance';
$pageIcon = 'pen-to-square';

// Add external CSS - matching manage_sections design
$additionalCSS = ['../css/manual-attendance-modern.css'];

$attendanceMode = 'employee';
$isEmployeeMode = true;

$entitySingular = 'Employee';
$entityPlural = 'Employees';
$identifierLabel = 'Employee ID';
$identifierDescription = 'Employee ID';
$identifierRegex = '/^[A-Za-z0-9_-]{3,20}$/';
$identifierPattern = '[A-Za-z0-9_-]{3,20}';
$identifierPlaceholder = 'Enter employee ID (e.g., EMP-001)';
$identifierHelpText = 'Enter the employee identifier assigned by HR';
$bulkPlaceholder = "EMP-001&#10;EMP-002&#10;EMP-003";
$groupLabel = 'Department';
$groupActionLabel = 'department';
$groupIcon = 'building';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        $action = $_POST['action'] ?? '';
        $response = ['success' => false, 'message' => ''];
        
        switch ($action) {
            case 'mark_attendance':
                $identifier = trim($_POST['employee_id'] ?? '');
                $date = trim($_POST['date'] ?? '');
                $time = trim($_POST['time'] ?? '');
                $action_type = $_POST['action_type'] ?? 'time_in';
                
                if (empty($identifier) || empty($date) || empty($time)) {
                    throw new Exception('All fields are required.');
                }
                
                if (!preg_match($identifierRegex, $identifier)) {
                    throw new Exception('Invalid ' . $identifierDescription . ' format.');
                }

                $employee_stmt = $pdo->prepare("SELECT employee_id, first_name, last_name, department_code, shift_code FROM employees WHERE employee_id = ? AND is_active = 1");
                $employee_stmt->execute([$identifier]);
                $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$employee) {
                    throw new Exception('Employee with this ID was not found.');
                }

                $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];

                if ($action_type === 'time_in') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO employee_attendance (employee_id, date, time_in, shift_code, status, source)
                         VALUES (?, ?, ?, ?, 'clock_in', 'manual')
                         ON DUPLICATE KEY UPDATE
                         time_in = VALUES(time_in),
                         shift_code = VALUES(shift_code),
                         status = IF(time_out IS NULL, 'clock_in', 'clock_out'),
                         updated_at = CURRENT_TIMESTAMP"
                    );
                    $result = $stmt->execute([$identifier, $date, $time, $employee['shift_code']]);

                    if ($result) {
                        $response = [
                            'success' => true,
                            'message' => "Clock In marked for {$employee_name} at " . date('h:i A', strtotime($time)),
                            'employee_name' => $employee_name,
                            'employee_id' => $identifier,
                            'time' => date('h:i A', strtotime($time))
                        ];
                        logAdminActivity('MANUAL_ATTENDANCE', "Marked clock_in for Employee ID: $identifier on $date at $time");
                    }
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE employee_attendance
                         SET time_out = ?, status = 'clock_out', updated_at = CURRENT_TIMESTAMP
                         WHERE employee_id = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL"
                    );
                    $stmt->execute([$time, $identifier, $date]);

                    if ($stmt->rowCount() > 0) {
                        $response = [
                            'success' => true,
                            'message' => "Clock Out marked for {$employee_name} at " . date('h:i A', strtotime($time)),
                            'employee_name' => $employee_name,
                            'employee_id' => $identifier,
                            'time' => date('h:i A', strtotime($time))
                        ];
                        logAdminActivity('MANUAL_ATTENDANCE', "Marked clock_out for Employee ID: $identifier on $date at $time");
                    } else {
                        throw new Exception("No 'Clock In' record found for this employee on the selected date. Cannot mark Clock Out.");
                    }
                }
                break;
                
            case 'bulk_mark':
                $identifiersRaw = trim($_POST['bulk_employee_ids'] ?? '');
                $date = trim($_POST['bulk_date'] ?? '');
                $time = trim($_POST['bulk_time'] ?? '');
                $bulk_action_type = $_POST['bulk_action_type'] ?? 'time_in';
                
                if (!in_array($bulk_action_type, ['time_in', 'time_out'], true)) {
                    $bulk_action_type = 'time_in';
                }
                
                if (empty($identifiersRaw) || empty($date) || empty($time)) {
                    throw new Exception('All fields are required for bulk attendance.');
                }
                
                $identifierList = array_filter(array_map('trim', explode("\n", $identifiersRaw)));
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                foreach ($identifierList as $identifier) {
                    if (!preg_match($identifierRegex, $identifier)) {
                        $errors[] = "Invalid {$identifierDescription}: $identifier";
                        $errorCount++;
                        continue;
                    }
                    
                    try {
                        $employee_stmt = $pdo->prepare("SELECT employee_id, shift_code FROM employees WHERE employee_id = ? AND is_active = 1");
                        $employee_stmt->execute([$identifier]);
                        $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

                        if ($employee) {
                            if ($bulk_action_type === 'time_in') {
                                $stmt = $pdo->prepare(
                                    "INSERT INTO employee_attendance (employee_id, date, time_in, shift_code, status, source)
                                     VALUES (?, ?, ?, ?, 'clock_in', 'manual')
                                     ON DUPLICATE KEY UPDATE
                                     time_in = VALUES(time_in),
                                     shift_code = VALUES(shift_code),
                                     status = IF(time_out IS NULL, 'clock_in', 'clock_out'),
                                     updated_at = CURRENT_TIMESTAMP"
                                );
                                $stmt->execute([$identifier, $date, $time, $employee['shift_code']]);
                                $successCount++;
                            } else {
                                $stmt = $pdo->prepare(
                                    "UPDATE employee_attendance
                                     SET time_out = ?, status = 'clock_out', updated_at = CURRENT_TIMESTAMP
                                     WHERE employee_id = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL"
                                );
                                $stmt->execute([$time, $identifier, $date]);

                                if ($stmt->rowCount() > 0) {
                                    $successCount++;
                                } else {
                                    $errors[] = "No Clock In found for: $identifier";
                                    $errorCount++;
                                }
                            }
                        } else {
                            $errors[] = "Not found: $identifier";
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Error: $identifier";
                        $errorCount++;
                    }
                }
                
                $response = [
                    'success' => $successCount > 0,
                    'message' => "Bulk attendance: $successCount successful, $errorCount errors.",
                    'successCount' => $successCount,
                    'errorCount' => $errorCount,
                    'errors' => array_slice($errors, 0, 5)
                ];
                
                $bulkActionLabel = $bulk_action_type === 'time_in' ? 'time_in' : 'time_out';
                logAdminActivity('BULK_ATTENDANCE', "Bulk marked {$bulkActionLabel}: $successCount successful, $errorCount errors ({$attendanceMode} mode)");
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Get today's date and current time for defaults
$today = date('Y-m-d');
$currentTime = date('H:i');

// Get records for quick selection
try {
    $stmt = $pdo->prepare(
        "SELECT employee_id, first_name, last_name, department_code
         FROM employees
         WHERE is_active = 1
         ORDER BY department_code, last_name, first_name"
    );
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT ea.employee_id,
                e.first_name,
                e.last_name,
                e.department_code,
                ea.status,
                COALESCE(ea.time_out, ea.time_in) AS time,
                ea.date,
                ea.updated_at AS created_at
         FROM employee_attendance ea
         JOIN employees e ON e.employee_id = ea.employee_id
         WHERE ea.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
         ORDER BY ea.updated_at DESC
         LIMIT 20"
    );
    $stmt->execute();
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $todaysSchedule = [];
    
} catch (Exception $e) {
    error_log("Manual attendance query error: " . $e->getMessage());
    $employees = [];
    $todaysSchedule = [];
    $recentAttendance = [];
}

// Include the modern admin header
$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php'],
    ['label' => 'Manual Attendance', 'icon' => 'pen-to-square', 'url' => 'manual_attendance.php']
];
$pageDescription = 'Record ' . strtolower($entitySingular) . ' attendance entries manually';
include 'includes/header_modern.php';
?>

<!-- External CSS loaded via $additionalCSS array -->

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fa-solid fa-circle-info"></i>
    </div>
    <div class="alert-content">
        <strong>Manual Attendance System</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Use this feature to mark Time In and Time Out for <?php echo strtolower($entityPlural); ?> who may have forgotten their QR codes or need retroactive attendance entries. The system supports separate Time In and Time Out tracking similar to the main scanner.
        </p>
    </div>
</div>

<!-- Modern Tabs -->
<div class="modern-tabs">
    <button class="modern-tab active" data-tab="scanner">
        <i class="fa-solid fa-qrcode"></i>
        <span>QR Scanner</span>
    </button>
    <button class="modern-tab" data-tab="single">
        <i class="fa-solid fa-user-clock"></i>
        <span>Single Entry</span>
    </button>
    <button class="modern-tab" data-tab="bulk">
        <i class="fa-solid fa-user-group-gear"></i>
        <span>Bulk Entry</span>
    </button>
</div>

<!-- QR Scanner Tab -->
<div id="scanner-tab" class="modern-tab-content active">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">QR Code Scanner</h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-camera"></i>
                            <span>Scan <?php echo strtolower($entitySingular); ?> QR codes for instant attendance</span>
                        </p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="start-scan-btn" class="btn btn-primary">
                        <i class="fa-solid fa-play"></i>
                        <span>Start Scanner</span>
                    </button>
                    <button id="stop-scan-btn" class="btn btn-danger" style="display: none;">
                        <i class="fa-solid fa-stop"></i>
                        <span>Stop Scanner</span>
                    </button>
                </div>
            </div>
            <div class="card-body-modern">
                <div class="scanner-container">
                    <div class="scanner-overlay" style="display: none;">
                        <div id="qr-reader-container">
                            <div id="qr-reader"></div>
                        </div>
                    </div>
                    <div id="scanner-status" class="scanner-status">
                        <p><i class="fa-solid fa-qrcode"></i> Click "Start Scanner" to begin scanning QR codes</p>
                    </div>
                    
                    <!-- Performance Stats -->
                    <div id="scanner-stats" class="scanner-stats" style="display: none;">
                        <div class="stat-item">
                            <i class="fa-solid fa-qrcode"></i>
                            <span class="stat-label">Scans</span>
                            <span class="stat-value" id="scan-count">0</span>
                        </div>
                        <div class="stat-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <span class="stat-label">Success</span>
                            <span class="stat-value" id="success-count">0</span>
                        </div>
                        <div class="stat-item">
                            <i class="fa-solid fa-gauge"></i>
                            <span class="stat-label">Avg Time</span>
                            <span class="stat-value" id="avg-time">0ms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Today's Attendance</h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-calendar-day"></i>
                            <span>View scanned attendance records for today</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <div id="scan-result-container" class="scan-result-container" style="display: none;">
                    <div id="scan-result"></div>
                </div>
                
                <div class="today-attendance-section">
                    <div id="today-attendance-list" class="attendance-list">
                        <div class="empty-state">
                            <i class="fa-solid fa-clipboard-list"></i>
                            <h3>No attendance yet today</h3>
                            <p>Scanned attendance will appear here</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single Entry Tab -->
<div id="single-tab" class="modern-tab-content">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Single Entry</h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Mark time in/out for individual <?php echo strtolower($entityPlural); ?></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <!-- Action Type Selection -->
                <div class="action-type-buttons">
                    <button type="button" class="action-type-btn active" data-action-type="time_in">
                        <div class="action-type-icon">
                            <i class="fa-solid fa-right-to-bracket"></i>
                        </div>
                        <span class="action-type-label">Time In</span>
                        <span class="action-type-desc">Mark arrival</span>
                    </button>
                    <button type="button" class="action-type-btn" data-action-type="time_out">
                        <div class="action-type-icon">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </div>
                        <span class="action-type-label">Time Out</span>
                        <span class="action-type-desc">Mark departure</span>
                    </button>
                </div>
                
                <form id="single-attendance-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="mark_attendance">
                    <input type="hidden" name="action_type" id="action_type" value="time_in">
                    
                    <div class="form-group-modern">
                        <label for="employee_id" class="form-label-modern">
                            <i class="fa-solid fa-id-card"></i>
                            <span><?php echo htmlspecialchars($identifierLabel); ?></span>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="employee_id" 
                               name="employee_id" 
                               class="form-input-modern" 
                               placeholder="<?php echo htmlspecialchars($identifierPlaceholder); ?>"
                               pattern="<?php echo htmlspecialchars($identifierPattern); ?>"
                               required>
                        <span class="form-hint"><?php echo htmlspecialchars($identifierHelpText); ?></span>
                    </div>
                    
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="date" class="form-label-modern">
                                <i class="fa-solid fa-calendar"></i>
                                <span>Date</span>
                                <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   class="form-input-modern" 
                                   value="<?php echo $today; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="time" class="form-label-modern">
                                <i class="fa-solid fa-clock"></i>
                                <span>Time</span>
                                <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="time" 
                                   name="time" 
                                   class="form-input-modern" 
                                   value="<?php echo $currentTime; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="submit-btn-text">Mark Time In</span>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Quick Select <?php echo htmlspecialchars($entitySingular); ?></h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-arrow-pointer"></i>
                            <span>Click a <?php echo strtolower($entitySingular); ?> to auto-fill their <?php echo htmlspecialchars($identifierDescription); ?></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <div class="students-list-modern">
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): ?>
                            <div class="student-item-modern" 
                                 data-action="select-employee" 
                                 data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                <div class="student-info-modern">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="student-name">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        </p>
                                        <p class="student-class">
                                            <i class="fa-solid fa-<?php echo $groupIcon; ?>"></i>
                                            <?php echo htmlspecialchars($employee['department_code']); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="lrn-badge-modern"><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-user-slash"></i>
                            <h3>No <?php echo strtolower($entityPlural); ?> found</h3>
                            <p>Add <?php echo strtolower($entityPlural); ?> to see them here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Entry Tab -->
<div id="bulk-tab" class="modern-tab-content">
    <div class="dashboard-grid">
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-user-group-gear"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Bulk Entry</h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-list"></i>
                            <span>Mark time in/out for multiple <?php echo strtolower($entityPlural); ?> at once</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <!-- Action Type Selection for Bulk -->
                <div class="action-type-buttons">
                    <button type="button" class="action-type-btn active" data-bulk-action-type="time_in">
                        <div class="action-type-icon">
                            <i class="fa-solid fa-right-to-bracket"></i>
                        </div>
                        <span class="action-type-label">Bulk Time In</span>
                        <span class="action-type-desc">Mark multiple arrivals</span>
                    </button>
                    <button type="button" class="action-type-btn" data-bulk-action-type="time_out">
                        <div class="action-type-icon">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </div>
                        <span class="action-type-label">Bulk Time Out</span>
                        <span class="action-type-desc">Mark multiple departures</span>
                    </button>
                </div>
                
                <form id="bulk-attendance-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_mark">
                    <input type="hidden" name="bulk_action_type" id="bulk_action_type" value="time_in">
                    
                    <div class="form-group-modern">
                        <label for="bulk_employee_ids" class="form-label-modern">
                            <i class="fa-solid fa-list-ol"></i>
                            <span><?php echo htmlspecialchars($identifierDescription); ?> values (One per line)</span>
                            <span class="required">*</span>
                        </label>
                        <textarea 
                            id="bulk_employee_ids" 
                            name="bulk_employee_ids" 
                            class="form-textarea-modern" 
                            placeholder="<?php echo $bulkPlaceholder; ?>"
                            rows="8"
                            required></textarea>
                        <span class="form-hint">Enter one <?php echo htmlspecialchars($identifierDescription); ?> per line</span>
                    </div>
                    
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="bulk_date" class="form-label-modern">
                                <i class="fa-solid fa-calendar"></i>
                                <span>Date</span>
                                <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="bulk_date" 
                                   name="bulk_date" 
                                   class="form-input-modern" 
                                   value="<?php echo $today; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="bulk_time" class="form-label-modern">
                                <i class="fa-solid fa-clock"></i>
                                <span>Time</span>
                                <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="bulk_time" 
                                   name="bulk_time" 
                                   class="form-input-modern" 
                                   value="<?php echo $currentTime; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fa-solid fa-check-double"></i>
                        <span id="bulk-submit-btn-text">Mark Bulk Time In</span>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <i class="fa-solid fa-copy"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Export by <?php echo htmlspecialchars($groupLabel); ?></h3>
                        <p class="card-subtitle-modern">
                            <i class="fa-solid fa-download"></i>
                            <span>Copy <?php echo htmlspecialchars($identifierDescription); ?> values by <?php echo strtolower($groupLabel); ?> for bulk operations</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body-modern">
                <?php
                $employeesByDepartment = [];
                foreach ($employees as $employee) {
                    $departmentCode = $employee['department_code'] ?? 'Unassigned';
                    if (!isset($employeesByDepartment[$departmentCode])) {
                        $employeesByDepartment[$departmentCode] = [];
                    }
                    $employeesByDepartment[$departmentCode][] = $employee;
                }
                ksort($employeesByDepartment);
                ?>
                
                <div class="class-export-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-4);">
                    <?php foreach ($employeesByDepartment as $departmentCode => $departmentEmployees): ?>
                        <div class="class-export-card" style="padding: var(--space-4); background: var(--gray-50); border: 2px solid var(--gray-200); border-radius: var(--radius-lg);">
                            <div class="class-export-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3);">
                                <h4 class="class-export-title" style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.9375rem; font-weight: 600; color: var(--gray-800); margin: 0;">
                                    <i class="fa-solid fa-<?php echo $groupIcon; ?>"></i>
                                    <?php echo htmlspecialchars($departmentCode); ?>
                                </h4>
                                <span class="student-count-badge" style="padding: var(--space-1) var(--space-2); background: var(--primary-100); color: var(--primary-700); border-radius: var(--radius-md); font-size: 0.75rem; font-weight: 600;">
                                    <?php echo count($departmentEmployees); ?> <?php echo strtolower($entityPlural); ?>
                                </span>
                            </div>
                            <textarea 
                                id="department-<?php echo htmlspecialchars($departmentCode); ?>" 
                                class="form-textarea-modern" 
                                readonly 
                                rows="5"
                                style="font-family: 'Courier New', monospace; font-size: 0.75rem;"><?php foreach ($departmentEmployees as $employee): ?><?php echo $employee['employee_id'] . "\n"; ?><?php endforeach; ?></textarea>
                            <button 
                                class="btn btn-primary" 
                                data-action="copy-department-employee-ids" 
                                data-department="<?php echo htmlspecialchars($departmentCode); ?>"
                                style="width: 100%; margin-top: var(--space-3);">
                                <i class="fa-solid fa-copy"></i>
                                <span>Copy <?php echo htmlspecialchars($identifierDescription); ?></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include ZXing library for QR code scanning -->
<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>

<!-- JAVASCRIPT SECTION -->
<script>
    /* ===== VARIABLES ===== */
    const attendanceMode = 'employee';
    const isEmployeeMode = true;
    const scannerEndpoint = '../api/mark_employee_attendance.php';
    const todayAttendanceEndpoint = '../api/get_today_employee_attendance.php';
    const identityFieldName = 'employee_id';
    const identityPattern = /^[A-Za-z0-9_-]{3,20}$/;
    const identityLabel = 'Employee ID';
    const entityLabel = 'Employee';

    let codeReader = null;
    let selectedDeviceId = null;
    let isScanning = false;
    let scanCount = 0;
    let successCount = 0;
    let processingTimes = [];
    let isProcessing = false;
    
    /* ===== NOTIFICATION SYSTEM ===== */
    function showNotification(message, type = 'info') {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        const notification = document.createElement('div');
        notification.className = 'notification-container';
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.style.cssText = 'min-width: 320px; animation: slideInRight 0.3s ease;';
        
        alert.innerHTML = `
            <div class="alert-icon">
                <i class="fa-solid fa-${icons[type]}"></i>
            </div>
            <div class="alert-content">
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong>
                <p style="margin: var(--space-1) 0 0;">${message}</p>
            </div>
        `;
        
        notification.appendChild(alert);
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    /* ===== TAB SWITCHING ===== */
    document.addEventListener('click', function(e) {
        const tab = e.target.closest('.modern-tab');
        if (!tab) return;
        
        const tabName = tab.dataset.tab;
        
        // Remove active from all tabs
        document.querySelectorAll('.modern-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.modern-tab-content').forEach(t => t.classList.remove('active'));
        
        // Add active to clicked tab
        tab.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    });
    
    /* ===== ACTION TYPE SELECTION (Single Entry) ===== */
    document.querySelectorAll('.action-type-btn[data-action-type]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all
            document.querySelectorAll('.action-type-btn[data-action-type]').forEach(b => b.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Update hidden input and button text
            const actionType = this.dataset.actionType;
            document.getElementById('action_type').value = actionType;

            const submitBtnText = document.getElementById('submit-btn-text');
            if (actionType === 'time_in') {
                submitBtnText.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Mark Time In';
            } else {
                submitBtnText.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Mark Time Out';
            }
        });
    });

    /* ===== ACTION TYPE SELECTION (Bulk Entry) ===== */
    document.querySelectorAll('.action-type-btn[data-bulk-action-type]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all bulk buttons
            document.querySelectorAll('.action-type-btn[data-bulk-action-type]').forEach(b => b.classList.remove('active'));

            // Add active to clicked
            this.classList.add('active');

            // Update hidden input and button text
            const actionType = this.dataset.bulkActionType;
            document.getElementById('bulk_action_type').value = actionType;

            const submitBtnText = document.getElementById('bulk-submit-btn-text');
            if (actionType === 'time_in') {
                submitBtnText.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Mark Bulk Time In';
            } else {
                submitBtnText.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Mark Bulk Time Out';
            }
        });
    });
    
    /* ===== QR SCANNER FUNCTIONS ===== */
    function updateScannerStats() {
        const scannerStats = document.getElementById('scanner-stats');
        const avgTime = processingTimes.length
            ? Math.round(processingTimes.reduce((total, value) => total + value, 0) / processingTimes.length)
            : 0;

        document.getElementById('scan-count').textContent = String(scanCount);
        document.getElementById('success-count').textContent = String(successCount);
        document.getElementById('avg-time').textContent = avgTime + 'ms';

        scannerStats.style.display = scanCount > 0 ? 'grid' : 'none';
    }

    function resetScannerStats() {
        scanCount = 0;
        successCount = 0;
        processingTimes = [];
        updateScannerStats();
    }

    function updateScannerStatus(message, type = '') {
        const statusElement = document.getElementById('scanner-status');
        const icons = {
            scanning: 'spinner fa-spin',
            success: 'check-circle',
            error: 'exclamation-triangle',
            '': 'qrcode'
        };
        
        statusElement.innerHTML = `<p><i class="fa-solid fa-${icons[type] || icons['']}"></i> ${message}</p>`;
        statusElement.className = `scanner-status ${type}`;
    }
    
    async function initializeQRScanner() {
        try {
            console.log(' Initializing QR Scanner...');
            
            const hints = new Map();
            const formats = [ZXing.BarcodeFormat.QR_CODE];
            hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, formats);
            hints.set(ZXing.DecodeHintType.TRY_HARDER, false);
            
            codeReader = new ZXing.BrowserQRCodeReader(hints);
            const videoInputDevices = await codeReader.listVideoInputDevices();
            
            if (videoInputDevices && videoInputDevices.length > 0) {
                selectedDeviceId = videoInputDevices[0].deviceId;
                updateScannerStatus('Camera initialized successfully. Ready for scanning!', 'success');
            } else {
                updateScannerStatus('No camera devices found', 'error');
            }
        } catch (error) {
            console.error(' Error initializing scanner:', error);
            if (error.name === 'NotAllowedError') {
                updateScannerStatus('Camera access denied. Please allow camera permissions.', 'error');
            } else if (error.name === 'NotFoundError') {
                updateScannerStatus('No camera found on this device.', 'error');
            } else {
                updateScannerStatus('Error initializing camera: ' + error.message, 'error');
            }
        }
    }
    
    async function startQRScanning() {
        if (!codeReader) {
            await initializeQRScanner();
            if (!codeReader) return;
        }

        try {
            isScanning = true;
            resetScannerStats();
            document.getElementById('start-scan-btn').style.display = 'none';
            document.getElementById('stop-scan-btn').style.display = 'inline-flex';
            document.querySelector('.scanner-overlay').style.display = 'block';
            
            updateScannerStatus(' Starting scanner...', 'scanning');
            updateScannerStats();
            console.log(' Scanner starting...');

            let video = document.querySelector('#qr-reader video');
            if (!video) {
                video = document.createElement('video');
                video.setAttribute('playsinline', '');
                video.setAttribute('autoplay', '');
                video.setAttribute('muted', '');
                video.style.width = '100%';
                video.style.height = '100%';
                video.style.objectFit = 'cover';
                document.getElementById('qr-reader').appendChild(video);
            }

            video.style.display = 'block';

            const constraints = {
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    frameRate: { ideal: 60, min: 30 }
                }
            };

            try {
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                await video.play();
                
                const videoTrack = stream.getVideoTracks()[0];
                const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    try {
                        await videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                    } catch (e) {
                        console.log('Continuous focus mode not supported');
                    }
                }
            } catch (constraintError) {
                if (constraintError.name === 'OverconstrainedError') {
                    console.warn('Falling back to simpler constraints');
                    const fallbackConstraints = {
                        video: { facingMode: 'environment' }
                    };
                    const stream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                    video.srcObject = stream;
                    await video.play();
                } else {
                    throw constraintError;
                }
            }

            // Use decodeFromVideoDevice instead of decodeFromVideoElement
            await codeReader.decodeFromVideoDevice(undefined, video, (result, err) => {
                if (result && isScanning && !isProcessing) {
                    handleQRCodeScan(result.text);
                }
            });

            console.log(' Scanner active!');
            updateScannerStatus(' Camera active. Scan QR codes now!', 'scanning');

        } catch (error) {
            console.error(' Error starting scanner:', error);
            if (error.name === 'NotAllowedError') {
                updateScannerStatus('Camera access denied. Please allow camera permissions.', 'error');
            } else if (error.name === 'NotFoundError') {
                updateScannerStatus('No camera found on this device.', 'error');
            } else {
                updateScannerStatus('Error starting camera: ' + error.message, 'error');
            }
            stopQRScanning();
        }
    }

    function stopQRScanning() {
        try {
            if (codeReader) {
                codeReader.reset();
            }
        } catch (error) {
            console.error(' Error stopping scanner:', error);
        }
        
        isScanning = false;
        document.getElementById('start-scan-btn').style.display = 'inline-flex';
        document.getElementById('stop-scan-btn').style.display = 'none';
        document.querySelector('.scanner-overlay').style.display = 'none';
        
        const sessionSummary = scanCount > 0 ? ` (${scanCount} scan${scanCount !== 1 ? 's' : ''}, ${successCount} successful)` : '';
        updateScannerStatus(' Scanner stopped. Click "Start Scanner" to begin scanning.' + sessionSummary, '');
        console.log(` Session stats: ${scanCount} total scans, ${successCount} successful`);
        
        if (scanCount > 0) {
            showNotification(`Session complete: ${successCount}/${scanCount} successful scans`, 'info');
        }
    }

    async function handleQRCodeScan(qrData) {
        if (isProcessing) {
            console.log(' Already processing a scan, ignoring...');
            return;
        }
        
        isProcessing = true;
        scanCount++;
        console.log(` QR Code scanned #${scanCount}:`, qrData.substring(0, 20) + '...');
        
        showQuickFeedback();
        updateScannerStatus(`Processing scan #${scanCount}...`, 'scanning');

        try {
            const identityValue = qrData.split('|')[0].trim();
            console.log(' Sending attendance for ' + identityLabel + ':', identityValue);

            const startTime = Date.now();
            const response = await fetch(scannerEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `${identityFieldName}=${encodeURIComponent(identityValue)}&source=qr`
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            const processingTime = Date.now() - startTime;
            
            console.log(' Response:', data);
            console.log(` Processing completed in ${processingTime}ms`);
            
            processingTimes.push(processingTime);
            if (processingTimes.length > 10) {
                processingTimes.shift();
            }

            if (data.success) {
                successCount++;
                showScanResult(true, data);
                updateScannerStatus(` Success! Scan #${scanCount}: ${data.employee_name || entityLabel}`, 'success');
                playSuccessSound();
                loadTodayAttendance();
            } else {
                showScanResult(false, data);
                updateScannerStatus(` Error on scan #${scanCount}: ${data.message}`, 'error');
            }
            
            updateScannerStats();

        } catch (error) {
            console.error(' Error processing QR code:', error);
            
            let errorMessage = 'Network error. Please check your connection and try again.';
            if (error.message.includes('HTTP')) {
                errorMessage = `Server error: ${error.message}`;
            }
            
            showScanResult(false, { message: errorMessage });
            updateScannerStatus(` Error on scan #${scanCount}: ${errorMessage}`, 'error');
        } finally {
            setTimeout(() => {
                isProcessing = false;
            }, 2000);
        }
    }

    function showQuickFeedback() {
        const video = document.querySelector('#qr-reader video');
        if (video) {
            video.style.borderColor = '#22c55e';
            setTimeout(() => {
                video.style.borderColor = '#d1d5db';
            }, 300);
        }
    }

    function playSuccessSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUQ0MUqzn77BZGwtEoePy');
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch (e) {
            // Silent fail
        }
    }

    function showScanResult(success, result) {
        const container = document.getElementById('scan-result-container');
        const resultDiv = document.getElementById('scan-result');
        
        container.style.display = 'block';
        container.style.animation = 'fadeIn 0.3s ease';
        
        if (success) {
            const displayName = result.employee_name || entityLabel;
            const displayIdentity = result.employee_id || 'N/A';
            const displayStatus = (result.action || result.status || 'recorded').toString().replace(/_/g, ' ');
            const statusClass = (result.action || result.status || 'recorded').toString().replace(/[^a-zA-Z0-9_-]/g, '_');
            const displayTime = result.time || result.time_in || result.time_out || new Date().toLocaleTimeString();

            resultDiv.className = 'scan-result scan-result-success';
            resultDiv.innerHTML = `
                <h4><i class="fa-solid fa-circle-check"></i> Attendance Marked Successfully</h4>
                <div class="scan-result-details">
                    <p><strong>${entityLabel}:</strong> <span>${displayName}</span></p>
                    <p><strong>${identityLabel}:</strong> <span>${displayIdentity}</span></p>
                    <p><strong>Status:</strong> <span class="status-badge status-${statusClass}">${displayStatus}</span></p>
                    <p><strong>Time:</strong> <span>${displayTime}</span></p>
                </div>
            `;
        } else {
            resultDiv.className = 'scan-result scan-result-error';
            resultDiv.innerHTML = `
                <h4><i class="fa-solid fa-circle-exclamation"></i> Error</h4>
                <p>${result.message || 'Unknown error occurred'}</p>
            `;
        }

        setTimeout(() => {
            container.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                container.style.display = 'none';
            }, 300);
        }, 4000);
    }

    async function loadTodayAttendance() {
        try {
            const response = await fetch(todayAttendanceEndpoint);
            const result = await response.json();
            
            const listContainer = document.getElementById('today-attendance-list');
            
            if (result.success && result.attendance && result.attendance.length > 0) {
                let html = '';
                result.attendance.forEach((record, index) => {
                    const recordGroup = record.department_code || 'N/A';
                    const recordTime = record.time || record.time_out || record.time_in || 'N/A';
                    const groupIcon = isEmployeeMode ? 'building' : 'graduation-cap';
                    html += `
                        <div class="attendance-item" style="animation-delay: ${index * 0.05}s;">
                            <div class="attendance-avatar">
                                ${record.first_name ? record.first_name.charAt(0).toUpperCase() : entityLabel.charAt(0)}
                            </div>
                            <div class="attendance-info">
                                <p class="attendance-name">${record.first_name || ''} ${record.last_name || 'Unknown'}</p>
                                <p class="attendance-details">
                                    <i class="fa-solid fa-clock"></i> ${recordTime}
                                    <i class="fa-solid fa-${groupIcon}"></i> ${recordGroup}
                                </p>
                            </div>
                            <span class="status-badge status-${record.status}">${record.status}</span>
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <h3>No attendance yet today</h3>
                        <p>Scanned attendance will appear here</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading today\'s attendance:', error);
            document.getElementById('today-attendance-list').innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h3>Error loading attendance</h3>
                    <p>Please try again later</p>
                </div>
            `;
        }
    }

    /* ===== EVENT DELEGATION ===== */
    document.addEventListener('click', async function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'select-employee') {
            const employeeId = target.dataset.employeeId;
            document.getElementById('employee_id').value = employeeId;
            document.getElementById('employee_id').focus();
            showNotification(identityLabel + ' selected: ' + employeeId, 'success');
            
            // Switch to single entry tab
            document.querySelector('[data-tab="single"]').click();
        }
        else if (action === 'copy-department-employee-ids') {
            const departmentCode = target.dataset.department;
            const textarea = document.getElementById('department-' + departmentCode);
            
            try {
                await navigator.clipboard.writeText(textarea.value.trim());
                document.getElementById('bulk_employee_ids').value = textarea.value.trim();
                showNotification(identityLabel + ' values copied for ' + departmentCode + '!', 'success');
                
                // Switch to bulk tab
                document.querySelector('[data-tab="bulk"]').click();
            } catch (err) {
                // Fallback
                textarea.select();
                document.execCommand('copy');
                document.getElementById('bulk_employee_ids').value = textarea.value.trim();
                showNotification(identityLabel + ' values copied for ' + departmentCode + '!', 'success');
                document.querySelector('[data-tab="bulk"]').click();
            }
        }
    });

    /* ===== FORM SUBMISSIONS ===== */
    const singleForm = document.getElementById('single-attendance-form');
    if (singleForm) {
        singleForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('manual_attendance.php?mode=employee', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    this.reset();
                    document.getElementById('date').value = '<?php echo $today; ?>';
                    document.getElementById('time').value = '<?php echo $currentTime; ?>';
                    document.getElementById('employee_id').focus();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while marking attendance.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
                submitBtn.innerHTML = originalText;
            }
        });
    }

    const bulkForm = document.getElementById('bulk-attendance-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('manual_attendance.php?mode=employee', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const message = `Bulk attendance: ${data.successCount} successful, ${data.errorCount} errors.`;
                    showNotification(message, data.errorCount > 0 ? 'warning' : 'success');
                    
                    if (data.errors && data.errors.length > 0) {
                        console.log('Errors:', data.errors);
                    }
                    
                    this.reset();
                    document.getElementById('bulk_date').value = '<?php echo $today; ?>';
                    document.getElementById('bulk_time').value = '<?php echo $currentTime; ?>';
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while marking bulk attendance.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /* ===== INITIALIZATION ===== */
    document.addEventListener('DOMContentLoaded', function() {
        initializeQRScanner();
        loadTodayAttendance();
        
        document.getElementById('start-scan-btn').addEventListener('click', startQRScanning);
        document.getElementById('stop-scan-btn').addEventListener('click', stopQRScanning);
        
        const employeeIdField = document.getElementById('employee_id');
        if (employeeIdField) {
            employeeIdField.addEventListener('keypress', function(e) {
                const minLength = 3;
                if (e.key === 'Enter' && this.value.length >= minLength && identityPattern.test(this.value.trim())) {
                    e.preventDefault();
                    document.getElementById('single-attendance-form').requestSubmit();
                }
            });
        }
        
        console.log(' Manual Attendance System initialized in ' + attendanceMode + ' mode');
    });
</script>

<?php include 'includes/footer_modern.php'; ?>

