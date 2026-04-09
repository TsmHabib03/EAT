<?php
/**
 * Employee Registration API - ADMIN ONLY
 * Returns JSON responses only
 */

session_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only administrators can register employees.'
    ]);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

ob_end_clean();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception('Database connection failed');
    }

    $employee_id = trim($_POST['employee_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $work_email = trim($_POST['work_email'] ?? '');
    $department_code = trim($_POST['department_code'] ?? '');
    $shift_code = trim($_POST['shift_code'] ?? '');
    $work_mode = trim($_POST['work_mode'] ?? 'WFH');
    $hire_date = trim($_POST['hire_date'] ?? '');

    if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($gender) || empty($work_email) || empty($department_code)) {
        throw new Exception('All required fields must be filled');
    }

    if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $employee_id)) {
        throw new Exception('Employee ID must be 3-20 characters using letters, numbers, underscore, or dash');
    }

    if (!in_array($gender, ['Male', 'Female', 'M', 'F'], true)) {
        throw new Exception('Please select a valid gender');
    }

    if (!filter_var($work_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid work email format');
    }

    if (!in_array($work_mode, ['WFH', 'Hybrid', 'Onsite'], true)) {
        throw new Exception('Invalid work mode');
    }

    if (!empty($hire_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
        throw new Exception('Invalid hire date format. Use YYYY-MM-DD');
    }

    $check = $db->prepare('SELECT id FROM employees WHERE employee_id = :employee_id OR work_email = :work_email');
    $check->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $check->bindParam(':work_email', $work_email, PDO::PARAM_STR);
    $check->execute();

    if ($check->rowCount() > 0) {
        throw new Exception('Employee ID or work email already exists in the system');
    }

    $dept = $db->prepare('SELECT id FROM departments WHERE department_code = :department_code LIMIT 1');
    $dept->bindParam(':department_code', $department_code, PDO::PARAM_STR);
    $dept->execute();

    if ($dept->rowCount() === 0) {
        $insertDept = $db->prepare('INSERT INTO departments (department_code, department_name, is_active) VALUES (:department_code, :department_name, 1)');
        $insertDept->bindParam(':department_code', $department_code, PDO::PARAM_STR);
        $insertDept->bindParam(':department_name', $department_code, PDO::PARAM_STR);
        $insertDept->execute();
    }

    if (!empty($shift_code)) {
        $shift = $db->prepare('SELECT id FROM shifts WHERE shift_code = :shift_code LIMIT 1');
        $shift->bindParam(':shift_code', $shift_code, PDO::PARAM_STR);
        $shift->execute();

        if ($shift->rowCount() === 0) {
            $insertShift = $db->prepare('INSERT INTO shifts (shift_code, shift_name, is_active) VALUES (:shift_code, :shift_name, 1)');
            $insertShift->bindParam(':shift_code', $shift_code, PDO::PARAM_STR);
            $insertShift->bindParam(':shift_name', $shift_code, PDO::PARAM_STR);
            $insertShift->execute();
        }
    }

    $qr_code = $employee_id . '|' . time();

    $insert = $db->prepare('INSERT INTO employees (employee_id, first_name, middle_name, last_name, gender, work_email, department_code, shift_code, work_mode, hire_date, qr_code) VALUES (:employee_id, :first_name, :middle_name, :last_name, :gender, :work_email, :department_code, :shift_code, :work_mode, :hire_date, :qr_code)');
    $insert->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $insert->bindParam(':first_name', $first_name, PDO::PARAM_STR);
    $insert->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
    $insert->bindParam(':last_name', $last_name, PDO::PARAM_STR);
    $insert->bindParam(':gender', $gender, PDO::PARAM_STR);
    $insert->bindParam(':work_email', $work_email, PDO::PARAM_STR);
    $insert->bindParam(':department_code', $department_code, PDO::PARAM_STR);

    if ($shift_code === '') {
        $shiftCodeNull = null;
        $insert->bindParam(':shift_code', $shiftCodeNull, PDO::PARAM_NULL);
    } else {
        $insert->bindParam(':shift_code', $shift_code, PDO::PARAM_STR);
    }

    $insert->bindParam(':work_mode', $work_mode, PDO::PARAM_STR);

    if ($hire_date === '') {
        $hireDateNull = null;
        $insert->bindParam(':hire_date', $hireDateNull, PDO::PARAM_NULL);
    } else {
        $insert->bindParam(':hire_date', $hire_date, PDO::PARAM_STR);
    }

    $insert->bindParam(':qr_code', $qr_code, PDO::PARAM_STR);

    if (!$insert->execute()) {
        throw new Exception('Failed to register employee. Please try again.');
    }

    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_code);
    $qr_code_html = '<img src="' . htmlspecialchars($qr_code_url, ENT_QUOTES, 'UTF-8') . '" alt="QR Code for Employee ID ' . htmlspecialchars($employee_id, ENT_QUOTES, 'UTF-8') . '">';

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Employee registered successfully!',
        'qr_code' => $qr_code_html,
        'employee_id' => $employee_id,
        'employee_name' => $first_name . ' ' . $last_name
    ]);
} catch (PDOException $e) {
    ob_end_clean();
    error_log('Employee registration DB error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    ob_end_clean();
    error_log('Employee registration error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit;
