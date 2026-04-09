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

    $query = 'SELECT department_code, department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC';
    $stmt = $db->prepare($query);
    $stmt->execute();

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($departments) === 0) {
        $fallback = $db->prepare('SELECT DISTINCT department_code FROM employees WHERE department_code IS NOT NULL AND department_code <> "" ORDER BY department_code ASC');
        $fallback->execute();

        while ($row = $fallback->fetch(PDO::FETCH_ASSOC)) {
            $departments[] = [
                'department_code' => $row['department_code'],
                'department_name' => $row['department_code']
            ];
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving departments: ' . $e->getMessage()
    ]);
}
