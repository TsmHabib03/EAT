<?php
require_once __DIR__ . '/../includes/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo "Database connection failed\n";
    exit;
}

$stmt = $db->query('SELECT employee_id, first_name, last_name, work_email, department_code FROM employees ORDER BY created_at DESC LIMIT 20');
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Registered Employees:\n\n";
foreach ($employees as $employee) {
    echo 'Employee ID: ' . $employee['employee_id'] . "\n";
    echo 'Name: ' . $employee['first_name'] . ' ' . $employee['last_name'] . "\n";
    echo 'Email: ' . $employee['work_email'] . "\n";
    echo 'Department: ' . $employee['department_code'] . "\n";
    echo "---\n";
}
