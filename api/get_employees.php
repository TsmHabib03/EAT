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

    $department_code = trim($_GET['department_code'] ?? '');
    $shift_code = trim($_GET['shift_code'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $where = ['1=1'];
    $params = [];

    if ($department_code !== '') {
        $where[] = 'e.department_code = :department_code';
        $params[':department_code'] = $department_code;
    }

    if ($shift_code !== '') {
        $where[] = 'e.shift_code = :shift_code';
        $params[':shift_code'] = $shift_code;
    }

    if ($search !== '') {
        $where[] = '(e.employee_id LIKE :search OR e.first_name LIKE :search OR e.last_name LIKE :search OR e.work_email LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $sql = 'SELECT e.* FROM employees e WHERE ' . implode(' AND ', $where) . ' ORDER BY e.created_at DESC';
    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'total' => count($employees)
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
