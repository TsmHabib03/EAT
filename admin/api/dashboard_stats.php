<?php
require_once '../../includes/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

header('Content-Type: application/json');

try {
    // Get admin user info if logged in
    session_start();
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    // Get basic dashboard statistics
    $stats = [
        'total_employees' => 0,
        'present_today' => 0,
        'attendance_rate' => 0,
        'total_records' => 0,
        'active_departments' => 0,
        'late_today' => 0
    ];
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE is_active = 1");
    $stats['total_employees'] = (int) $stmt->fetch()['total'];
    
    // Today's attendance
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as present FROM employee_attendance WHERE date = CURDATE() AND time_in IS NOT NULL");
    $stmt->execute();
    $stats['present_today'] = (int) $stmt->fetch()['present'];
    
    // Today's attendance rate
    $stats['attendance_rate'] = $stats['total_employees'] > 0 ? 
        round(($stats['present_today'] / $stats['total_employees']) * 100, 1) : 0;
    
    // Total attendance records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_attendance");
    $stats['total_records'] = (int) $stmt->fetch()['total'];
    
    // Active departments
    $stmt = $pdo->query("SELECT COUNT(DISTINCT department_code) as total FROM employees WHERE is_active = 1 AND department_code IS NOT NULL AND department_code != ''");
    $stats['active_departments'] = (int) $stmt->fetch()['total'];
    
    // Late employees today
    $stmt = $pdo->prepare("SELECT COUNT(*) as late_count FROM employee_attendance WHERE date = CURDATE() AND late_minutes > 0");
    $stmt->execute();
    $stats['late_today'] = (int) $stmt->fetch()['late_count'];
    
    // Recent attendance if admin
    $recent_attendance = [];
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT ea.employee_id, e.first_name, e.last_name, ea.status, ea.time_in, ea.time_out, ea.date
            FROM employee_attendance ea 
            JOIN employees e ON ea.employee_id = e.employee_id 
            ORDER BY ea.updated_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_attendance' => $recent_attendance,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
