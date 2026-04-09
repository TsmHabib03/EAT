<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $today = date('Y-m-d');
    
    // Get total employees
    $totalEmployeesQuery = "SELECT COUNT(*) as total FROM employees WHERE is_active = 1";
    $totalEmployeesStmt = $db->prepare($totalEmployeesQuery);
    $totalEmployeesStmt->execute();
    $totalEmployees = (int)$totalEmployeesStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get present today
    $presentTodayQuery = "SELECT COUNT(*) as present FROM employee_attendance WHERE date = :today AND time_in IS NOT NULL";
    $presentTodayStmt = $db->prepare($presentTodayQuery);
    $presentTodayStmt->bindParam(':today', $today);
    $presentTodayStmt->execute();
    $presentToday = (int)$presentTodayStmt->fetch(PDO::FETCH_ASSOC)['present'];
    
    // Calculate attendance rate
    $attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_employees' => $totalEmployees,
            'present_today' => $presentToday,
            'attendance_rate' => $attendanceRate
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
