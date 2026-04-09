<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manage Departments';
$pageIcon = 'table-cells-large';

// Add departments CSS - New Modern Design with cache buster
$additionalCSS = ['../css/manage-sections-modern.css?v=' . time()];

// Initialize response array for AJAX
$response = ['success' => false, 'message' => ''];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            // Add new department
            $department_code = trim($_POST['department_code'] ?? '');
            
            if (empty($department_code)) {
                throw new Exception('Department code is required');
            }
            
            // Check if department already exists
            $check_stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code = ?");
            $check_stmt->execute([$department_code]);
            if ($check_stmt->rowCount() > 0) {
                throw new Exception('Department already exists');
            }
            
            $stmt = $pdo->prepare("INSERT INTO departments (department_code, department_name, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$department_code, $department_code]);
            
            logAdminActivity('ADD_DEPARTMENT', "Added department: $department_code");
            
            $response = ['success' => true, 'message' => 'Department added successfully!'];
            
        } elseif ($action === 'edit') {
            // Edit department
            $id = intval($_POST['id'] ?? 0);
            $department_code = trim($_POST['department_code'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $is_active = ($status === 'active') ? 1 : 0;
            
            if (empty($department_code)) {
                throw new Exception('Department code is required');
            }
            
            // Get old department code for updating employees
            $old_stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
            $old_stmt->execute([$id]);
            $old_department = $old_stmt->fetch();
            
            if (!$old_department) {
                throw new Exception('Department not found');
            }
            
            $pdo->beginTransaction();
            
            // Update department
            $stmt = $pdo->prepare("UPDATE departments SET department_code = ?, department_name = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$department_code, $department_code, $is_active, $id]);
            
            // Update employees' department code if department code changed
            if ($old_department['department_code'] !== $department_code) {
                $updateEmployees = $pdo->prepare("UPDATE employees SET department_code = ? WHERE department_code = ?");
                $updateEmployees->execute([$department_code, $old_department['department_code']]);
            }
            
            $pdo->commit();
            
            logAdminActivity('EDIT_DEPARTMENT', "Updated department: $department_code");
            
            $response = ['success' => true, 'message' => 'Department updated successfully!'];
            
        } elseif ($action === 'delete') {
            // Delete department
            $id = intval($_POST['id'] ?? 0);
            
            // Check if department has employees
            $stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $department = $stmt->fetch();
            
            if (!$department) {
                throw new Exception('Department not found');
            }
            
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE department_code = ?");
            $count_stmt->execute([$department['department_code']]);
            $count = $count_stmt->fetch()['count'];
            
            if ($count > 0) {
                throw new Exception("Cannot delete department. It has $count employee(s) assigned. Please reassign employees first.");
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $delete_stmt->execute([$id]);
            
            logAdminActivity('DELETE_DEPARTMENT', "Deleted department: {$department['department_code']}");
            
            $response = ['success' => true, 'message' => 'Department deleted successfully!'];
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Get all departments with employee count
try {
    $query = "SELECT d.id,
              d.department_code,
              d.is_active,
              (SELECT COUNT(*) FROM employees e WHERE e.department_code = d.department_code) AS employee_count,
              CASE WHEN d.is_active = 1 THEN 'active' ELSE 'inactive' END AS status
              FROM departments d
              ORDER BY d.department_code";
    $stmt = $pdo->query($query);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all departments have required keys to prevent undefined array key warnings
    foreach ($departments as &$department) {
        $department['status'] = $department['status'] ?? 'active';
        $department['is_active'] = $department['is_active'] ?? 1;
    }
    unset($department); // Break reference
} catch (Exception $e) {
    $departments = [];
    $message = 'Error loading departments: ' . $e->getMessage();
    $messageType = 'error';
}

$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php'],
    ['label' => 'Manage Departments', 'icon' => 'table-cells-large', 'url' => 'manage_departments.php']
];
$breadcrumbAction = ['label' => 'Add Department', 'icon' => 'circle-plus', 'url' => '#', 'target' => ''];
$pageDescription = 'Create and organize employee departments';

include 'includes/header_modern.php';
?>

<!-- Stats Overview - Enhanced -->
<div class="stats-grid stats-grid-enhanced">
    <div class="stat-card stat-card-animated" data-stat="total">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-table-cells-large"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count($departments); ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fa-solid fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Total Departments</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-green" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="employees" style="animation-delay: 0.1s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo array_sum(array_column($departments, 'employee_count')); ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fa-solid fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Total Employees</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-green" style="width: 85%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="active" style="animation-delay: 0.2s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count(array_filter($departments, fn($d) => $d['status'] === 'active')); ?>">0</h3>
                    <span class="stat-trend stat-trend-neutral">
                        <i class="fa-solid fa-minus"></i>
                    </span>
                </div>
                <p class="stat-label">Active Departments</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-green" style="width: 92%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card stat-card-animated" data-stat="active-ratio" style="animation-delay: 0.3s;">
        <div class="stat-card-inner">
            <div class="stat-icon-wrapper">
                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <div class="stat-icon-bg"></div>
            </div>
            <div class="stat-content">
                <div class="stat-value-wrapper">
                    <h3 class="stat-value" data-count="<?php echo count($departments) > 0 ? round((count(array_filter($departments, fn($d) => $d['status'] === 'active')) / count($departments)) * 100) : 0; ?>">0</h3>
                    <span class="stat-trend stat-trend-up">
                        <i class="fa-solid fa-arrow-up"></i>
                    </span>
                </div>
                <p class="stat-label">Active Rate %</p>
                <div class="stat-progress">
                    <div class="stat-progress-bar stat-progress-green" style="width: 78%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Departments List - Enhanced -->
<div class="content-card content-card-enhanced">
    <div class="card-header-modern card-header-enhanced">
        <div class="card-header-left">
            <div class="card-icon-badge">
                <i class="fa-solid fa-list"></i>
            </div>
            <div>
                <h2 class="card-title-modern">
                    All Departments
                </h2>
                <p class="card-subtitle-modern">
                    <i class="fa-solid fa-info-circle"></i>
                    View and manage all employee departments
                </p>
            </div>
        </div>
        <div class="card-actions card-actions-enhanced">
            <div class="filter-group">
                <div class="search-box-enhanced">
                    <i class="fa-solid fa-search search-icon"></i>
                    <input type="text" id="searchDepartments" placeholder="Search departments..." autocomplete="off">
                    <button class="search-clear" id="clearSearch" style="display: none;">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <div class="filter-dropdown">
                    <button class="filter-btn" id="filterBtn">
                        <i class="fa-solid fa-filter"></i>
                        <span>Filter</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="filter-menu" id="filterMenu">
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="all" checked>
                                <span>All Departments</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="active">
                                <span>Active Only</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="statusFilter" value="inactive">
                                <span>Inactive Only</span>
                            </label>
                        </div>
                        <hr class="filter-divider">
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="sortFilter" value="name">
                                <span>Sort by Name</span>
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                <input type="radio" name="sortFilter" value="employees">
                                <span>Sort by Employees</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body-modern">
        <?php if (empty($departments)): ?>
            <!-- BEAUTIFUL NEW EMPTY STATE DESIGN -->
            <div class="empty-state-modern">
                <div class="empty-state-animation">
                    <div class="empty-state-circle circle-1"></div>
                    <div class="empty-state-circle circle-2"></div>
                    <div class="empty-state-circle circle-3"></div>
                    <div class="empty-state-icon-wrapper">
                        <div class="empty-state-icon-modern">
                            <i class="fa-solid fa-table-cells-large"></i>
                        </div>
                    </div>
                </div>
                <div class="empty-state-content-modern">
                    <h3 class="empty-state-title-modern">No Departments Yet</h3>
                    <p class="empty-state-text-modern">
                        Departments help you organize employees by business function.<br>
                        Create your first department to get started!
                    </p>
                    <div class="empty-state-features">
                        <div class="empty-feature">
                            <div class="feature-icon">
                                <i class="fa-solid fa-user-group"></i>
                            </div>
                            <span>Organize Employees</span>
                        </div>
                        <div class="empty-feature">
                            <div class="feature-icon">
                                <i class="fa-solid fa-building"></i>
                            </div>
                            <span>Build Department Structure</span>
                        </div>
                    </div>
                    <button class="btn-empty-action" data-action="add-department">
                        <span class="btn-empty-icon">
                            <i class="fa-solid fa-plus"></i>
                        </span>
                        <span class="btn-empty-text">Create Your First Department</span>
                        <span class="btn-empty-shine"></span>
                    </button>
                    <p class="empty-state-help">
                        <i class="fa-solid fa-lightbulb"></i>
                        <strong>Pro Tip:</strong> Departments are automatically created when you add employees with new department codes
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-container table-container-enhanced">
                <div class="table-wrapper">
                    <table class="modern-table modern-table-enhanced" id="departmentsTable">
                        <thead>
                            <tr>
                                <th class="th-sortable" data-sort="name">
                                    <div class="th-content">
                                        <i class="fa-solid fa-table-cells-large th-icon"></i>
                                        <span>Department Code</span>
                                        <i class="fa-solid fa-sort sort-icon"></i>
                                    </div>
                                </th>
                                <th class="th-sortable" data-sort="employees">
                                    <div class="th-content">
                                        <i class="fa-solid fa-user-group th-icon"></i>
                                        <span>Employees</span>
                                        <i class="fa-solid fa-sort sort-icon"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <i class="fa-solid fa-circle-info th-icon"></i>
                                        <span>Status</span>
                                    </div>
                                </th>
                                <th class="th-actions">
                                    <div class="th-content">
                                        <i class="fa-solid fa-gear th-icon"></i>
                                        <span>Actions</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="departmentsTableBody">
                            <?php foreach ($departments as $index => $department): ?>
                            <tr data-department-id="<?php echo $department['id']; ?>" 
                                data-status="<?php echo $department['status']; ?>"
                                data-employees="<?php echo $department['employee_count']; ?>"
                                class="table-row-animated" 
                                style="animation-delay: <?php echo ($index * 0.05); ?>s;">
                                <td class="td-primary">
                                    <div class="table-cell-content">
                                        <div class="section-name-wrapper">
                                            <div class="section-icon">
                                                <i class="fa-solid fa-bookmark"></i>
                                            </div>
                                            <strong class="section-name"><?php echo htmlspecialchars($department['department_code']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td class="td-centered">
                                     <span class="badge badge-info badge-enhanced">
                                        <i class="fa-solid fa-user-group"></i>
                                        <span class="badge-text"><?php echo $department['employee_count']; ?> employee<?php echo $department['employee_count'] != 1 ? 's' : ''; ?></span>
                                     </span>
                                </td>
                                <td class="td-centered">
                                    <?php 
                                    $status = $department['status'];
                                    $statusClass = strtolower($status);
                                    $statusIcon = $status === 'active' ? 'check-circle' : 'times-circle';
                                    ?>
                                    <span class="status-badge status-badge-enhanced status-<?php echo $statusClass; ?>">
                                        <i class="fa-solid fa-<?php echo $statusIcon; ?> status-icon"></i>
                                        <span><?php echo ucfirst($status); ?></span>
                                    </span>
                                </td>
                                <td class="td-actions">
                                    <div class="action-buttons action-buttons-enhanced">
                                        <button 
                                            class="btn-action btn-action-edit" 
                                            data-action="edit"
                                            data-department='<?php echo json_encode($department); ?>'
                                            title="Edit Department">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            <span class="btn-tooltip">Edit</span>
                                        </button>
                                        <button 
                                            class="btn-action btn-action-delete" 
                                            data-action="delete"
                                            data-id="<?php echo $department['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($department['department_code']); ?>"
                                            data-count="<?php echo $department['employee_count']; ?>"
                                            title="Delete Department">
                                            <i class="fa-solid fa-trash"></i>
                                            <span class="btn-tooltip">Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Footer with Pagination Info -->
                <div class="table-footer">
                    <div class="table-info">
                        Showing <strong id="visibleRows"><?php echo count($departments); ?></strong> of <strong id="totalRows"><?php echo count($departments); ?></strong> departments
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Department Modal - Enhanced -->
<div class="modal-overlay modal-overlay-enhanced" id="departmentModal">
    <div class="modal-container modal-container-enhanced">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-header-enhanced">
                <div class="modal-header-content">
                    <div class="modal-icon-wrapper">
                        <i class="fa-solid fa-table-cells-large"></i>
                    </div>
                    <div>
                        <h3 class="modal-title" id="modalTitle">Add New Department</h3>
                        <p class="modal-subtitle">Fill in the department details below</p>
                    </div>
                </div>
                <button class="modal-close modal-close-enhanced" data-action="close-modal" data-modal="departmentModal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <form id="departmentForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="departmentId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="department_code" class="form-label">
                                Department Code
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="department_code" 
                                id="department_code" 
                                class="form-input" 
                                placeholder="e.g., HR, IT, FINANCE" 
                                required>
                            <small class="form-help">Use uppercase department identifiers for consistency.</small>
                        </div>
                    </div>
                    
                    <div class="form-group" id="statusGroup" style="display: none;">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="departmentModal">
                            <i class="fa-solid fa-times"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fa-solid fa-save"></i>
                            <span>Save Department</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal - Enhanced -->
<div class="modal-overlay modal-overlay-enhanced" id="deleteModal">
    <div class="modal-container modal-container-small">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-body modal-body-centered">
                <div class="delete-icon-wrapper delete-icon-animated">
                    <div class="delete-icon-circle">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="delete-icon-ripple"></div>
                </div>
                <h3 class="delete-title">Delete Department?</h3>
                <p class="delete-message">
                    Are you sure you want to permanently delete<br>
                    <strong class="delete-section-highlight" id="deleteDepartmentName"></strong>?
                </p>
                <p id="deleteWarning" class="delete-warning delete-warning-enhanced" style="display: none;">
                    <i class="fa-solid fa-building"></i>
                    <span></span>
                </p>
                
                <div class="modal-actions modal-actions-centered">
                    <button 
                        type="button" 
                        class="btn btn-secondary btn-modal" 
                        data-action="close-modal" 
                        data-modal="deleteModal">
                        <i class="fa-solid fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button 
                        type="button" 
                        class="btn btn-danger btn-modal" 
                        id="confirmDeleteBtn"
                        data-action="confirm-delete">
                        <i class="fa-solid fa-trash"></i>
                        <span>Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Manage Departments - Modern Implementation
 */

// State Management
const DepartmentManager = {
    currentDepartment: null,
    deleteId: null,
    deleteName: '',
    deleteCount: 0
};

// Notification System
function showNotification(message, type = 'info') {
    const container = document.createElement('div');
    container.className = 'notification-container';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = 'min-width: 300px; animation: slideInRight 0.3s ease;';
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fa-solid fa-${icons[type] || 'info-circle'}"></i>
        </div>
        <div class="alert-content">${message}</div>
    `;
    
    container.appendChild(notification);
    document.body.appendChild(container);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => container.remove(), 300);
    }, 3000);
}

// Modal Management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Prevent body scroll without layout shift
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = scrollbarWidth + 'px';
        
        modal.classList.add('active');
        
        // Mobile full-screen
        if (window.innerWidth < 480) {
            modal.classList.add('modal-mobile-fullscreen');
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        
        // Restore body scroll
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        if (window.innerWidth < 480) {
            modal.classList.remove('modal-mobile-fullscreen');
        }
    }
}

// Add Department
function openAddModal() {
    const titleElement = document.querySelector('#modalTitle');
    
    titleElement.innerHTML = '<i class="fa-solid fa-circle-plus"></i> Add New Department';
    
    document.getElementById('formAction').value = 'add';
    document.getElementById('departmentForm').reset();
    document.getElementById('statusGroup').style.display = 'none';
    
    openModal('departmentModal');
}

// Edit Department
function editDepartment(department) {
    const titleElement = document.querySelector('#modalTitle');
    titleElement.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Department';
    
    document.getElementById('formAction').value = 'edit';
    document.getElementById('departmentId').value = department.id;
    document.getElementById('department_code').value = department.department_code;
    document.getElementById('status').value = department.status || 'active';
    document.getElementById('statusGroup').style.display = 'block';
    
    DepartmentManager.currentDepartment = department;
    openModal('departmentModal');
}

// Delete Department
function deleteDepartment(id, name, employeeCount) {
    DepartmentManager.deleteId = id;
    DepartmentManager.deleteName = name;
    DepartmentManager.deleteCount = employeeCount;
    
    document.getElementById('deleteDepartmentName').textContent = name;
    
    const warning = document.getElementById('deleteWarning');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    if (employeeCount > 0) {
        warning.querySelector('span').textContent = `This department has ${employeeCount} employee(s) assigned. You must reassign or remove them first.`;
        warning.style.display = 'flex';
        confirmBtn.disabled = true;
        confirmBtn.style.opacity = '0.5';
        confirmBtn.style.cursor = 'not-allowed';
    } else {
        warning.style.display = 'none';
        confirmBtn.disabled = false;
        confirmBtn.style.opacity = '1';
        confirmBtn.style.cursor = 'pointer';
    }
    
    openModal('deleteModal');
}

// Confirm Delete
async function confirmDelete() {
    if (DepartmentManager.deleteCount > 0) {
        showNotification('Cannot delete department with assigned employees', 'error');
        return;
    }
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.classList.add('btn-loading');
    confirmBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', DepartmentManager.deleteId);
        
        const response = await fetch('manage_departments.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('deleteModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to delete department', 'error');
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.disabled = false;
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Network error. Please try again.', 'error');
        confirmBtn.classList.remove('btn-loading');
        confirmBtn.disabled = false;
    }
}

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const departmentForm = document.getElementById('departmentForm');
    
    if (departmentForm) {
        departmentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            const formData = new FormData(departmentForm);
            
            try {
                const response = await fetch('manage_departments.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('departmentModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to save department', 'error');
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Form submission error:', error);
                showNotification('Network error. Please try again.', 'error');
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
            }
        });
    }
    
    // Event Delegation for Buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.dataset.action;
        
        if (action === 'add-department') {
            openAddModal();
        } else if (action === 'edit') {
            const department = JSON.parse(target.dataset.department);
            editDepartment(department);
        } else if (action === 'delete') {
            const id = parseInt(target.dataset.id);
            const name = target.dataset.name;
            const count = parseInt(target.dataset.count);
            deleteDepartment(id, name, count);
        } else if (action === 'close-modal') {
            const modalId = target.dataset.modal;
            closeModal(modalId);
        } else if (action === 'confirm-delete') {
            confirmDelete();
        }
    });
    
    // Close modals on outside click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('departmentModal');
            closeModal('deleteModal');
        }
    });
    
    // Enhanced Search Functionality
    const searchInput = document.getElementById('searchDepartments');
    const clearSearchBtn = document.getElementById('clearSearch');
    const totalRowsSpan = document.getElementById('totalRows');
    const visibleRowsSpan = document.getElementById('visibleRows');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#departmentsTableBody tr');
            let visibleCount = 0;
            
            // Show/hide clear button
            clearSearchBtn.style.display = searchTerm ? 'flex' : 'none';
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const isVisible = text.includes(searchTerm);
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Update visible count
            visibleRowsSpan.textContent = visibleCount;
            
            // Show "no results" message if needed
            updateEmptyState(visibleCount === 0, searchTerm);
        });
        
        // Clear search
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
    }
    
    // Filter Functionality
    const filterBtn = document.getElementById('filterBtn');
    const filterMenu = document.getElementById('filterMenu');
    
    if (filterBtn && filterMenu) {
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterMenu.classList.toggle('active');
        });
        
        // Close filter menu when clicking outside
        document.addEventListener('click', function() {
            filterMenu.classList.remove('active');
        });
        
        filterMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Status filter
        const statusFilters = document.querySelectorAll('input[name="statusFilter"]');
        statusFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
    
    function applyFilters() {
        const statusFilter = document.querySelector('input[name="statusFilter"]:checked')?.value || 'all';
        const rows = document.querySelectorAll('#departmentsTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const status = row.dataset.status;
            let shouldShow = true;
            
            if (statusFilter !== 'all') {
                shouldShow = status === statusFilter;
            }
            
            // Also apply search filter
            const searchTerm = searchInput.value.toLowerCase().trim();
            if (searchTerm && shouldShow) {
                const text = row.textContent.toLowerCase();
                shouldShow = text.includes(searchTerm);
            }
            
            row.style.display = shouldShow ? '' : 'none';
            if (shouldShow) visibleCount++;
        });
        
        visibleRowsSpan.textContent = visibleCount;
        updateEmptyState(visibleCount === 0);
    }
    
    function updateEmptyState(isEmpty, searchTerm = '') {
        let emptyState = document.querySelector('.empty-state-search');
        
        if (isEmpty && !emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state-search';
            emptyState.innerHTML = `
                <div class="empty-state-icon">
                    <i class="fa-solid fa-search"></i>
                </div>
                <h3>No departments found</h3>
                <p>${searchTerm ? `No results for "${searchTerm}"` : 'Try adjusting your filters'}</p>
            `;
            document.querySelector('.table-wrapper').appendChild(emptyState);
        } else if (!isEmpty && emptyState) {
            emptyState.remove();
        }
    }
    
    // Animate stat cards on load
    function animateStats() {
        const statValues = document.querySelectorAll('.stat-value[data-count]');
        statValues.forEach(stat => {
            const target = parseInt(stat.dataset.count);
            let current = 0;
            const increment = Math.ceil(target / 30);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                stat.textContent = current;
            }, 30);
        });
    }
    
    // Run animations
    setTimeout(animateStats, 100);
    
    console.log(' Enhanced Department Management initialized');
});
</script>

<?php include 'includes/footer_modern.php'; ?>

