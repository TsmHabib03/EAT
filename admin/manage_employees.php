<?php
require_once 'config.php';
require_once __DIR__ . '/../includes/qrcode_helper.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = isset($_GET['id']) ? 'Edit Employee' : 'Add Employee';
$pageIcon = isset($_GET['id']) ? 'user-pen' : 'user-plus';
$additionalCSS = ['../css/manage-employees.css?v=' . time()];

$message = '';
$messageType = 'info';
$editMode = false;
$editEmployee = null;

function autoCreateDepartment(PDO $pdo, string $departmentCode): void {
    $stmt = $pdo->prepare(
        "INSERT INTO departments (department_code, department_name, is_active, created_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE department_name = VALUES(department_name), updated_at = NOW()"
    );
    $stmt->execute([$departmentCode, $departmentCode]);
}

function autoCreateShift(PDO $pdo, string $shiftCode): void {
    if ($shiftCode === '') {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO shifts (shift_code, shift_name, is_active, created_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE shift_name = VALUES(shift_name), updated_at = NOW()"
    );
    $stmt->execute([$shiftCode, $shiftCode]);
}

if (isset($_GET['id'])) {
    $editMode = true;
    $editId = (int) $_GET['id'];

    try {
        $stmt = $pdo->prepare(
            "SELECT id, employee_id, first_name, middle_name, last_name, gender, work_email, department_code, shift_code, qr_code, created_at, updated_at
             FROM employees
             WHERE id = ?"
        );
        $stmt->execute([$editId]);
        $editEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editEmployee) {
            $message = 'Employee not found.';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error loading employee details.';
        $messageType = 'error';
        error_log('Manage employees load error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $employeeId = strtoupper(trim($_POST['employee_id'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $workEmail = trim($_POST['work_email'] ?? '');
        $departmentCode = strtoupper(trim($_POST['department_code'] ?? ''));
        $shiftCode = strtoupper(trim($_POST['shift_code'] ?? ''));

        if (
            $employeeId === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $gender === '' ||
            $workEmail === '' ||
            $departmentCode === ''
        ) {
            $message = 'All required fields must be filled.';
            $messageType = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $employeeId)) {
            $message = 'Employee ID must be 3-20 characters using letters, numbers, underscore, or dash.';
            $messageType = 'error';
        } elseif (!in_array($gender, ['Male', 'Female'], true)) {
            $message = 'Please select a valid gender.';
            $messageType = 'error';
        } elseif (!filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid work email address.';
            $messageType = 'error';
        } else {
            try {
                if ($action === 'add') {
                    $check = $pdo->prepare('SELECT id FROM employees WHERE employee_id = ? OR work_email = ?');
                    $check->execute([$employeeId, $workEmail]);
                    if ($check->fetch()) {
                        throw new Exception('Employee ID or work email already exists.');
                    }

                    autoCreateDepartment($pdo, $departmentCode);
                    autoCreateShift($pdo, $shiftCode);

                    $stmt = $pdo->prepare(
                        "INSERT INTO employees (employee_id, first_name, middle_name, last_name, gender, work_email, department_code, shift_code, work_mode, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'WFH', NOW())"
                    );
                    $stmt->execute([
                        $employeeId,
                        $firstName,
                        $middleName !== '' ? $middleName : null,
                        $lastName,
                        $gender,
                        $workEmail,
                        $departmentCode,
                        $shiftCode !== '' ? $shiftCode : null
                    ]);

                    $newId = (int) $pdo->lastInsertId();
                    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                    $qrPath = generateEmployeeQRCode($newId, $employeeId, $fullName);

                    if ($qrPath) {
                        $upd = $pdo->prepare('UPDATE employees SET qr_code = ? WHERE id = ?');
                        $upd->execute([$qrPath, $newId]);
                        $message = 'Employee added successfully with QR code generated.';
                    } else {
                        $message = 'Employee added successfully, but QR code generation failed.';
                    }

                    $messageType = 'success';
                    $_POST = [];
                }

                if ($action === 'edit' && $editEmployee) {
                    $check = $pdo->prepare('SELECT id FROM employees WHERE (employee_id = ? OR work_email = ?) AND id != ?');
                    $check->execute([$employeeId, $workEmail, $editEmployee['id']]);
                    if ($check->fetch()) {
                        throw new Exception('Another employee already uses this ID or work email.');
                    }

                    autoCreateDepartment($pdo, $departmentCode);
                    autoCreateShift($pdo, $shiftCode);

                    $stmt = $pdo->prepare(
                        "UPDATE employees
                         SET employee_id = ?, first_name = ?, middle_name = ?, last_name = ?, gender = ?, work_email = ?, department_code = ?, shift_code = ?, updated_at = NOW()
                         WHERE id = ?"
                    );
                    $stmt->execute([
                        $employeeId,
                        $firstName,
                        $middleName !== '' ? $middleName : null,
                        $lastName,
                        $gender,
                        $workEmail,
                        $departmentCode,
                        $shiftCode !== '' ? $shiftCode : null,
                        $editEmployee['id']
                    ]);

                    if ($employeeId !== $editEmployee['employee_id']) {
                        $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                        $qrPath = regenerateEmployeeQRCode((int) $editEmployee['id'], $employeeId, $fullName);
                        if ($qrPath) {
                            $upd = $pdo->prepare('UPDATE employees SET qr_code = ? WHERE id = ?');
                            $upd->execute([$qrPath, $editEmployee['id']]);
                        }
                    }

                    $refresh = $pdo->prepare(
                        "SELECT id, employee_id, first_name, middle_name, last_name, gender, work_email, department_code, shift_code, qr_code, created_at, updated_at
                         FROM employees
                         WHERE id = ?"
                    );
                    $refresh->execute([$editEmployee['id']]);
                    $editEmployee = $refresh->fetch(PDO::FETCH_ASSOC);

                    $message = 'Employee updated successfully.';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = $e->getMessage() !== '' ? $e->getMessage() : 'Database error occurred. Please try again.';
                $messageType = 'error';
                error_log('Manage employees save error: ' . $e->getMessage());
            }
        }
    }
}

$departmentOptions = [];
$shiftOptions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department_code FROM employees WHERE department_code IS NOT NULL AND department_code <> '' ORDER BY department_code");
    $departmentOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT shift_code FROM employees WHERE shift_code IS NOT NULL AND shift_code <> '' ORDER BY shift_code");
    $shiftOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('Manage employees options error: ' . $e->getMessage());
}

$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php'],
    ['label' => 'Employees', 'icon' => 'user-group', 'url' => 'view_employees.php'],
    ['label' => $pageTitle, 'icon' => $pageIcon, 'url' => '#']
];
$pageDescription = $editMode ? 'Update employee information' : 'Register a new employee';

include 'includes/header_modern.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6);">
        <div class="alert-icon">
            <i class="fa-solid fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        </div>
        <div class="alert-content"><?php echo sanitizeOutput($message); ?></div>
    </div>
<?php endif; ?>

<div class="form-card">
    <div class="form-card-header">
        <h3 class="form-card-title">
            <i class="fa-solid fa-<?php echo $editMode ? 'user-edit' : 'user-plus'; ?>"></i>
            <?php echo $editMode ? 'Edit Employee' : 'Add New Employee'; ?>
        </h3>
        <div class="card-actions">
            <a href="view_employees.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to List</span>
            </a>
        </div>
    </div>

    <div class="form-card-body">
        <?php if ($editMode && !$editEmployee): ?>
            <div class="alert alert-error">Employee not found.</div>
        <?php else: ?>
            <form method="POST" action="" class="student-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">

                <div class="form-grid two-col">
                    <div class="form-group">
                        <label for="employee_id">Employee ID <span class="required">*</span></label>
                        <input
                            type="text"
                            id="employee_id"
                            name="employee_id"
                            class="form-input"
                            required
                            pattern="[A-Za-z0-9_-]{3,20}"
                            maxlength="20"
                            minlength="3"
                            placeholder="Enter employee ID (e.g., EMP-001)"
                            value="<?php echo sanitizeOutput($editEmployee['employee_id'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="work_email">Work Email <span class="required">*</span></label>
                        <input
                            type="email"
                            id="work_email"
                            name="work_email"
                            class="form-input"
                            required
                            maxlength="100"
                            placeholder="Enter work email"
                            value="<?php echo sanitizeOutput($editEmployee['work_email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid three-col">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-input" required maxlength="50" value="<?php echo sanitizeOutput($editEmployee['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-input" maxlength="50" value="<?php echo sanitizeOutput($editEmployee['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-input" required maxlength="50" value="<?php echo sanitizeOutput($editEmployee['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid three-col">
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (($editEmployee['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($editEmployee['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department_code">Department Code <span class="required">*</span></label>
                        <input type="text" id="department_code" name="department_code" class="form-input" required maxlength="50" list="department-suggestions" value="<?php echo sanitizeOutput($editEmployee['department_code'] ?? ''); ?>">
                        <datalist id="department-suggestions">
                            <?php foreach ($departmentOptions as $departmentCode): ?>
                                <option value="<?php echo sanitizeOutput($departmentCode); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="shift_code">Shift Code</label>
                        <input type="text" id="shift_code" name="shift_code" class="form-input" maxlength="50" list="shift-suggestions" value="<?php echo sanitizeOutput($editEmployee['shift_code'] ?? ''); ?>">
                        <datalist id="shift-suggestions">
                            <?php foreach ($shiftOptions as $shiftCode): ?>
                                <option value="<?php echo sanitizeOutput($shiftCode); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-<?php echo $editMode ? 'save' : 'plus'; ?>"></i>
                        <span><?php echo $editMode ? 'Update Employee' : 'Add Employee'; ?></span>
                    </button>
                    <a href="view_employees.php" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i>
                        <span>Cancel</span>
                    </a>
                </div>
            </form>

            <?php if ($editMode && $editEmployee): ?>
                <div class="quick-actions-section" style="margin-top: var(--space-6);">
                    <h4 class="quick-actions-title">Quick Actions</h4>
                    <div class="action-buttons">
                        <a href="attendance_reports_departments.php?employee_search=<?php echo urlencode($editEmployee['employee_id']); ?>&mode=employee" class="btn btn-success" target="_blank">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>View Attendance</span>
                        </a>
                        <form method="POST" action="../api/delete_employee.php" style="display: inline-block;">
                            <input type="hidden" name="employee_id" value="<?php echo (int) $editEmployee['id']; ?>">
                            <input type="hidden" name="mode" value="employee">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-trash"></i>
                                <span>Delete Employee</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer_modern.php'; ?>
