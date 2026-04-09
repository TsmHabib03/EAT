<?php
/**
 * Get Attendance Report - Department-Based
 * Returns employee attendance records filtered by department, date range, and employee search
 */

header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $employeeTable = $db->query("SHOW TABLES LIKE 'employees'")->fetch();
    $employeeAttendanceTable = $db->query("SHOW TABLES LIKE 'employee_attendance'")->fetch();
    if (!$employeeTable || !$employeeAttendanceTable) {
        throw new Exception('Employee attendance tables are not available. Run database/migrations/2026_04_08_employee_tracker_phase1.sql first.');
    }

    $department = $_GET['department'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $employee_search = trim($_GET['employee_search'] ?? '');
    
    // Validate required dates
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Start date and end date are required');
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    $query = "SELECT
                ea.id,
                ea.employee_id,
                e.department_code,
                ea.date,
                ea.time_in,
                ea.time_out,
                ea.status,
                CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) AS employee_name,
                e.work_email
            FROM employee_attendance ea
            INNER JOIN employees e ON ea.employee_id = e.employee_id
            WHERE ea.date BETWEEN :start_date AND :end_date";
    
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    // Add department filter
    if (!empty($department)) {
        $query .= " AND e.department_code = :department";
        $params[':department'] = $department;
    }
    
    // Add employee search filter
    if (!empty($employee_search)) {
        $query .= " AND (
            e.employee_id LIKE :search
            OR e.first_name LIKE :search
            OR e.last_name LIKE :search
            OR e.work_email LIKE :search
            OR CONCAT(e.first_name, ' ', e.last_name) LIKE :search
        )";
        $params[':search'] = '%' . $employee_search . '%';
    }
    
    $query .= " ORDER BY ea.date DESC, e.department_code ASC, e.last_name ASC";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format records
    $formatted_records = [];
    $completed_count = 0;
    $incomplete_count = 0;
    $departmentsSet = [];
    
    foreach ($records as $record) {
        $time_in_obj = $record['time_in'] ? strtotime($record['time_in']) : null;
        $time_out_obj = $record['time_out'] ? strtotime($record['time_out']) : null;
        
        // Calculate duration
        $duration = '-';
        if ($time_in_obj && $time_out_obj) {
            $duration_seconds = $time_out_obj - $time_in_obj;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hrs %d mins', $hours, $minutes);
            $completed_count++;
        } elseif ($time_in_obj) {
            $duration = 'In Progress';
            $incomplete_count++;
        }
        
        // Track unique departments
        if (!in_array($record['department_code'], $departmentsSet, true)) {
            $departmentsSet[] = $record['department_code'];
        }
        
        $formatted_records[] = [
            'id' => $record['id'],
            'employee_id' => $record['employee_id'],
            'employee_name' => $record['employee_name'],
            'department' => $record['department_code'],
            'date' => $record['date'],
            'date_formatted' => date('F j, Y', strtotime($record['date'])),
            'time_in' => $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : null,
            'time_out' => $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : null,
            'duration' => $duration,
            'status' => $record['status'],
            'work_email' => $record['work_email']
        ];
    }
    
    // Calculate summary
    $summary = [
        'total_records' => count($formatted_records),
        'completed_count' => $completed_count,
        'incomplete_count' => $incomplete_count,
        'departments_count' => count($departmentsSet),
        'date_range' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)),
        'departments' => $departmentsSet
    ];
    
    echo json_encode([
        'success' => true,
        'records' => $formatted_records,
        'summary' => $summary,
        'filters' => [
            'department' => $department ?: 'All Departments',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'employee_search' => $employee_search
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
