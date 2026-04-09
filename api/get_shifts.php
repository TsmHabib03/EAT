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

    $query = 'SELECT shift_code, shift_name, start_time, end_time, grace_minutes FROM shifts WHERE is_active = 1 ORDER BY shift_name ASC';
    $stmt = $db->prepare($query);
    $stmt->execute();

    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($shifts) === 0) {
        $fallback = $db->prepare('SELECT DISTINCT shift_code FROM employees WHERE shift_code IS NOT NULL AND shift_code <> "" ORDER BY shift_code ASC');
        $fallback->execute();

        while ($row = $fallback->fetch(PDO::FETCH_ASSOC)) {
            $shifts[] = [
                'shift_code' => $row['shift_code'],
                'shift_name' => $row['shift_code'],
                'start_time' => null,
                'end_time' => null,
                'grace_minutes' => 15
            ];
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'shifts' => $shifts
    ]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving shifts: ' . $e->getMessage()
    ]);
}
