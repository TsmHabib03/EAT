<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception('Database connection failed');
    }

    $today = date('Y-m-d');

    $query = "SELECT
                ea.id,
                ea.employee_id,
                ea.date,
                ea.time_in,
                ea.time_out,
                ea.status,
                e.first_name,
                e.last_name,
                e.department_code,
                e.shift_code,
                COALESCE(ea.time_out, ea.time_in) AS display_time
              FROM employee_attendance ea
              INNER JOIN employees e ON e.employee_id = ea.employee_id
              WHERE ea.date = :today
              ORDER BY ea.updated_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':today', $today, PDO::PARAM_STR);
    $stmt->execute();

    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attendance as &$record) {
        $record['time'] = !empty($record['display_time'])
            ? date('h:i:s A', strtotime($record['display_time']))
            : null;
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'date' => $today,
        'total' => count($attendance)
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
