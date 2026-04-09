<?php
/**
 * Employee attendance endpoint
 * - First mark of day = Clock In
 * - Second mark of day = Clock Out
 * - Third mark and beyond = Rejected
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

date_default_timezone_set('Asia/Manila');

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

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
    $source = trim($_POST['source'] ?? 'qr');

    if ($employee_id === '') {
        throw new Exception('Employee ID is required');
    }

    if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $employee_id)) {
        throw new Exception('Invalid Employee ID format');
    }

    if (!in_array($source, ['qr', 'web', 'manual'], true)) {
        $source = 'qr';
    }

    $today = date('Y-m-d');
    $currentTime = date('H:i:s');

    $db->beginTransaction();

    $employeeStmt = $db->prepare('SELECT e.employee_id, e.first_name, e.middle_name, e.last_name, e.shift_code, s.start_time, s.grace_minutes FROM employees e LEFT JOIN shifts s ON s.shift_code = e.shift_code WHERE e.employee_id = :employee_id AND e.is_active = 1 LIMIT 1 FOR UPDATE');
    $employeeStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $employeeStmt->execute();

    if ($employeeStmt->rowCount() === 0) {
        throw new Exception('Employee not found or inactive');
    }

    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    $attendanceStmt = $db->prepare('SELECT * FROM employee_attendance WHERE employee_id = :employee_id AND date = :attendance_date LIMIT 1 FOR UPDATE');
    $attendanceStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $attendanceStmt->bindParam(':attendance_date', $today, PDO::PARAM_STR);
    $attendanceStmt->execute();
    $existing = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        $lateMinutes = 0;

        if (!empty($employee['start_time'])) {
            $startAt = new DateTime($today . ' ' . $employee['start_time']);
            $graceMinutes = isset($employee['grace_minutes']) ? (int) $employee['grace_minutes'] : 0;
            if ($graceMinutes > 0) {
                $startAt->modify('+' . $graceMinutes . ' minutes');
            }

            $currentAt = new DateTime($today . ' ' . $currentTime);
            if ($currentAt > $startAt) {
                $lateMinutes = (int) floor(($currentAt->getTimestamp() - $startAt->getTimestamp()) / 60);
            }
        }

        $insertStmt = $db->prepare('INSERT INTO employee_attendance (employee_id, date, time_in, shift_code, status, late_minutes, source) VALUES (:employee_id, :attendance_date, :time_in, :shift_code, :status, :late_minutes, :source)');
        $insertStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
        $insertStmt->bindParam(':attendance_date', $today, PDO::PARAM_STR);
        $insertStmt->bindParam(':time_in', $currentTime, PDO::PARAM_STR);
        $insertStmt->bindParam(':shift_code', $employee['shift_code'], PDO::PARAM_STR);

        $status = 'clock_in';
        $insertStmt->bindParam(':status', $status, PDO::PARAM_STR);
        $insertStmt->bindParam(':late_minutes', $lateMinutes, PDO::PARAM_INT);
        $insertStmt->bindParam(':source', $source, PDO::PARAM_STR);

        if (!$insertStmt->execute()) {
            throw new Exception('Failed to record clock in');
        }

        $db->commit();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'action' => 'clock_in',
            'message' => 'Clock in recorded successfully',
            'employee_id' => $employee_id,
            'employee_name' => trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']),
            'attendance_date' => $today,
            'time' => $currentTime,
            'late_minutes' => $lateMinutes
        ]);
        exit;
    }

    if (!empty($existing['time_out'])) {
        $db->rollBack();
        throw new Exception('Attendance already completed for today');
    }

    $updateStmt = $db->prepare('UPDATE employee_attendance SET time_out = :time_out, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $updateStmt->bindParam(':time_out', $currentTime, PDO::PARAM_STR);
    $status = 'clock_out';
    $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
    $updateStmt->bindParam(':id', $existing['id'], PDO::PARAM_INT);

    if (!$updateStmt->execute()) {
        throw new Exception('Failed to record clock out');
    }

    $db->commit();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'action' => 'clock_out',
        'message' => 'Clock out recorded successfully',
        'employee_id' => $employee_id,
        'employee_name' => trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']),
        'attendance_date' => $today,
        'time' => $currentTime
    ]);
} catch (PDOException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    ob_end_clean();
    error_log('Employee attendance DB error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    ob_end_clean();
    error_log('Employee attendance error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit;
