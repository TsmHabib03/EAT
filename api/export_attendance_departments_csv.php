<?php
/**
 * Export Attendance to CSV - Department-Based
 * Generates CSV file with employee attendance records
 */

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
        die('Error: Start date and end date are required');
    }
    
    // Build filename
    $departmentName = !empty($department) ? $department : 'All_Departments';
    $start_formatted = date('Ymd', strtotime($start_date));
    $end_formatted = date('Ymd', strtotime($end_date));
    $filename = "Employee_Attendance_{$departmentName}_{$start_formatted}_to_{$end_formatted}.csv";
    
    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $query = "SELECT
                ea.employee_id,
                CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) AS employee_name,
                e.department_code,
                ea.date,
                ea.time_in,
                ea.time_out,
                ea.status,
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
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write report header
    fputcsv($output, ['EMPLOYEE ATTENDANCE REPORT']);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, ['Date Range:', date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date))]);
    fputcsv($output, ['Department:', !empty($department) ? $department : 'All Departments']);
    if (!empty($employee_search)) {
        fputcsv($output, ['Search Filter:', $employee_search]);
    }
    fputcsv($output, []); // Empty row
    
    // Write CSV headers
    $headers = [
        'Employee ID',
        'Employee Name',
        'Department',
        'Date',
        'Day',
        'Time In',
        'Time Out',
        'Duration',
        'Status',
        'Work Email'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    $total_records = 0;
    $completed_count = 0;
    $incomplete_count = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total_records++;
        
        $date_obj = strtotime($row['date']);
        $day_name = date('l', $date_obj);
        $date_formatted = date('M j, Y', $date_obj);
        
        $time_in = $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-';
        $time_out = $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-';
        
        // Calculate duration
        $duration = '-';
        if ($row['time_in'] && $row['time_out']) {
            $time_in_obj = strtotime($row['time_in']);
            $time_out_obj = strtotime($row['time_out']);
            $duration_seconds = $time_out_obj - $time_in_obj;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%d hrs %d mins', $hours, $minutes);
            $completed_count++;
        } elseif ($row['time_in']) {
            $duration = 'In Progress';
            $incomplete_count++;
        }
        
        $csv_row = [
            $row['employee_id'],
            $row['employee_name'],
            $row['department_code'],
            $date_formatted,
            $day_name,
            $time_in,
            $time_out,
            $duration,
            ucfirst($row['status']),
            $row['work_email']
        ];
        
        fputcsv($output, $csv_row);
    }
    
    // Write summary footer
    fputcsv($output, []); // Empty row
    fputcsv($output, ['=== SUMMARY ===']);
    fputcsv($output, ['Total Records:', $total_records]);
    fputcsv($output, ['Completed (Time In & Out):', $completed_count]);
    fputcsv($output, ['Incomplete (Time In Only):', $incomplete_count]);
    fputcsv($output, ['Completion Rate:', $total_records > 0 ? round(($completed_count / $total_records) * 100, 1) . '%' : '0%']);
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_export_error.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['EXPORT ERROR']);
    fputcsv($output, ['Database error: ' . $e->getMessage()]);
    fclose($output);
    exit;
} catch (Exception $e) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_export_error.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['EXPORT ERROR']);
    fputcsv($output, ['Error: ' . $e->getMessage()]);
    fclose($output);
    exit;
}
?>
