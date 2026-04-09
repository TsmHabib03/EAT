<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/database.php';

if (!isset($_GET['employee_id']) || trim($_GET['employee_id']) === '') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception('Database connection failed');
    }

    $employee_id = trim($_GET['employee_id']);

    $employeeQuery = 'SELECT * FROM employees WHERE employee_id = :employee_id LIMIT 1';
    $employeeStmt = $db->prepare($employeeQuery);
    $employeeStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $employeeStmt->execute();

    if ($employeeStmt->rowCount() === 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    $attendanceQuery = 'SELECT * FROM employee_attendance WHERE employee_id = :employee_id ORDER BY date DESC, time_in DESC LIMIT 30';
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $attendanceStmt->execute();
    $attendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    $statsQuery = 'SELECT COUNT(*) AS total_days, SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) AS with_time_in, SUM(CASE WHEN time_out IS NOT NULL THEN 1 ELSE 0 END) AS with_time_out, SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) AS late_days FROM employee_attendance WHERE employee_id = :employee_id';
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_STR);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'attendance' => $attendance,
        'stats' => $stats
    ]);
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
