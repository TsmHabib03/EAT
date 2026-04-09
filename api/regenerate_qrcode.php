<?php
/**
 * API Endpoint: Regenerate QR Code for Employee
 * Allows admin to manually regenerate QR code for an existing employee
 */

session_start();
require_once '../admin/config.php';
require_once '../includes/qrcode_helper.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get employee ID from request
$input = json_decode(file_get_contents('php://input'), true);
$employeeId = isset($input['employee_id']) ? intval($input['employee_id']) : 0;

if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, employee_id, first_name, middle_name, last_name FROM employees WHERE id = ? AND is_active = 1");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        exit;
    }

    $fullName = trim($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']);
    $qrCodePath = regenerateEmployeeQRCode($employee['id'], $employee['employee_id'], $fullName);

    if ($qrCodePath) {
        $updateStmt = $pdo->prepare("UPDATE employees SET qr_code = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$qrCodePath, $employee['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Employee QR code regenerated successfully',
            'qr_code_path' => '../' . $qrCodePath . '?v=' . time()
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to regenerate employee QR code. Please check server permissions.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("QR regeneration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
