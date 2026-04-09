<?php
require_once '../admin/config.php';
require_once '../includes/qrcode_helper.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$employeeId = $input['employee_id'] ?? $_POST['employee_id'] ?? null;

if (!$employeeId) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM employee_attendance WHERE employee_id = ?");
    $stmt->execute([$employee['employee_id']]);
    $attendanceDeleted = $stmt->rowCount();

    $stmt = $pdo->prepare("DELETE FROM attendance_corrections WHERE employee_id = ?");
    $stmt->execute([$employee['employee_id']]);

    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);

    if ($stmt->rowCount() > 0) {
        deleteEmployeeQRCode($employeeId);
        $pdo->commit();

        logAdminActivity(
            'DELETE_EMPLOYEE',
            "Deleted employee: {$employee['first_name']} {$employee['last_name']} (Employee ID: {$employee['employee_id']}). Also deleted {$attendanceDeleted} attendance records."
        );

        echo json_encode([
            'success' => true,
            'message' => "Employee deleted successfully. {$attendanceDeleted} attendance records were also removed.",
            'employee_name' => $employee['first_name'] . ' ' . $employee['last_name']
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete employee']);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete employee error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
