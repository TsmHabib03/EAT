<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$attendanceMode = 'employee';
$isEmployeeMode = true;

$entitySingular = 'Employee';
$entityPlural = 'Employees';
$groupLabel = 'Department';
$groupPlural = 'Departments';
$identifierLabel = 'Employee ID';
$searchLabel = 'Employee Name / ID';
$searchPlaceholder = 'Search by employee name or ID...';
$modeQuery = '';

$pageTitle = 'Employee Attendance Reports';
$pageIcon = 'chart-column';

$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php'],
    ['label' => $pageTitle, 'icon' => 'chart-column', 'url' => 'attendance_reports_departments.php' . $modeQuery]
];
$pageDescription = 'Generate and analyze attendance data by ' . strtolower($groupLabel);

// Include the modern admin header
include 'includes/header_modern.php';

// Fetch departments for the filter
$departmentOptions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department_code FROM employees WHERE is_active = 1 AND department_code IS NOT NULL AND department_code <> '' ORDER BY department_code");
    $departmentOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departmentOptions = [];
}
?>

<!-- ===== Report Stepper ===== -->
<div class="stepper" id="reportStepper">
    <div class="stepper-step active" data-step="1">
        <div class="stepper-circle">1</div>
        <span class="stepper-label">Select Range</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step="2">
        <div class="stepper-circle">2</div>
        <span class="stepper-label">Preview</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step="3">
        <div class="stepper-circle">3</div>
        <span class="stepper-label">Export</span>
    </div>
</div>

<!-- ===== Step 1: Select Range ===== -->
<div class="stepper-panel active" id="stepPanel1">
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
            <i class="fa-solid fa-filter" style="color:var(--green-600);"></i>
            <h2 style="margin:0;font-size:1.125rem;font-weight:700;">Report Filters</h2>
        </div>
        <div class="card-body">
            <form id="reportFilters" autocomplete="off">
                <?php if ($isEmployeeMode): ?>
                    <input type="hidden" name="mode" value="employee">
                <?php endif; ?>
                <div class="filters-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
                    <!-- Department Filter -->
                    <div class="form-group">
                        <label for="department_filter" class="form-label">
                            <i class="fa-solid fa-table-cells-large"></i> <?php echo $groupLabel; ?>
                        </label>
                        <select id="department_filter" name="department" class="form-input">
                            <option value="">All <?php echo $groupPlural; ?></option>
                            <?php foreach ($departmentOptions as $departmentCode): ?>
                            <option value="<?php echo htmlspecialchars($departmentCode); ?>"><?php echo htmlspecialchars($departmentCode); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div class="form-group">
                        <label for="start_date" class="form-label">
                            <i class="fa-solid fa-calendar-days"></i> Start Date
                        </label>
                        <input type="date" id="start_date" name="start_date" class="form-input" value="<?= date('Y-m-01') ?>">
                    </div>

                    <!-- End Date -->
                    <div class="form-group">
                        <label for="end_date" class="form-label">
                            <i class="fa-solid fa-calendar-check"></i> End Date
                        </label>
                        <input type="date" id="end_date" name="end_date" class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Employee Search -->
                    <div class="form-group">
                        <label for="employee_search" class="form-label">
                            <i class="fa-solid fa-magnifying-glass"></i> <?php echo $searchLabel; ?>
                        </label>
                        <input type="text" id="employee_search" name="employee_search" class="form-input" placeholder="<?php echo htmlspecialchars($searchPlaceholder); ?>">
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" id="resetFilters" class="btn btn-outline">
                        <i class="fa-solid fa-arrows-rotate"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-solid">
                        <i class="fa-solid fa-chart-line"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Step 2: Preview ===== -->
<div class="stepper-panel" id="stepPanel2">
    <!-- KPI Chips -->
    <div id="summarySection" style="display:none;margin-bottom:1.5rem;">
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
            <div class="kpi-chip">
                <i class="fa-solid fa-clipboard-list"></i>
                <span class="kpi-chip-value" id="total_records">0</span>
                <span class="kpi-chip-label">Total Records</span>
            </div>
            <div class="kpi-chip">
                <i class="fa-solid fa-circle-check"></i>
                <span class="kpi-chip-value" id="completed_count">0</span>
                <span class="kpi-chip-label">Completed</span>
            </div>
            <div class="kpi-chip">
                <i class="fa-solid fa-clock"></i>
                <span class="kpi-chip-value" id="incomplete_count">0</span>
                <span class="kpi-chip-label">Incomplete</span>
            </div>
            <div class="kpi-chip">
                <i class="fa-solid fa-table-cells-large"></i>
                <span class="kpi-chip-value" id="departments_count">0</span>
                <span class="kpi-chip-label"><?php echo $groupPlural; ?></span>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div id="resultsCard" class="card" style="display:none;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
            <h2 style="margin:0;font-size:1.125rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
                <i class="fa-solid fa-table" style="color:var(--green-600);"></i> Attendance Records
            </h2>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                <div class="density-toggle">
                    <button type="button" class="density-option active" data-density="normal" title="Normal density">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <button type="button" class="density-option" data-density="compact" title="Compact density">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="backToFilters">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive" id="tableContainer">
                <table class="table" id="reportTable">
                    <thead>
                        <tr>
                            <th><?php echo $identifierLabel; ?></th>
                            <th><?php echo $entitySingular; ?> Name</th>
                            <th><?php echo $groupLabel; ?></th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="noResults" class="empty-state" style="display:none;">
                <i class="fa-solid fa-inbox"></i>
                <h3>No Records Found</h3>
                <p>No attendance records match your selected filters. Try adjusting your <?php echo strtolower($groupLabel); ?> or search criteria.</p>
            </div>

            <!-- Pagination -->
            <div id="paginationContainer" class="pagination" style="display:none;"></div>
        </div>
    </div>

    <!-- Step navigation -->
    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;" id="step2Nav" class="step-nav-buttons">
        <button type="button" class="btn btn-outline" id="prevToStep1">
            <i class="fa-solid fa-arrow-left"></i> Back to Filters
        </button>
        <button type="button" class="btn btn-solid" id="nextToStep3">
            <i class="fa-solid fa-arrow-right"></i> Continue to Export
        </button>
    </div>
</div>

<!-- ===== Step 3: Export ===== -->
<div class="stepper-panel" id="stepPanel3">
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
            <i class="fa-solid fa-download" style="color:var(--green-600);"></i>
            <h2 style="margin:0;font-size:1.125rem;font-weight:700;">Export Report</h2>
        </div>
        <div class="card-body">
            <p style="color:var(--gray-500);margin-bottom:1.5rem;">Choose an export format below. The report includes <strong id="exportRecordCount">0</strong> records.</p>

            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
                <button type="button" class="btn btn-solid" id="exportCSV">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </button>
                <button type="button" class="btn btn-outline" id="printReport">
                    <i class="fa-solid fa-print"></i> Print Report
                </button>
            </div>

            <!-- Progress bar (shown during export) -->
            <div id="exportProgress" style="display:none;margin-bottom:1rem;">
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="exportProgressFill" style="width:0%;"></div>
                </div>
                <p style="font-size:.875rem;color:var(--gray-500);margin-top:.5rem;" id="exportProgressText">Preparing export</p>
            </div>

            <!-- Toast area -->
            <div id="exportToastArea"></div>

            <div style="display:flex;gap:.75rem;justify-content:flex-start;margin-top:1.5rem;">
                <button type="button" class="btn btn-outline" id="prevToStep2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Preview
                </button>
                <button type="button" class="btn btn-ghost" id="newReport">
                    <i class="fa-solid fa-plus"></i> New Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Attendance Reports  Stepper-based UI
 */
(function() {
    'use strict';

    const attendanceMode = '<?php echo $attendanceMode; ?>';

    let currentStep = 1;
    let currentPage = 1;
    const rowsPerPage = 20;
    let allRecords = [];
    let currentFilters = {};

    // DOM refs
    const stepperSteps = document.querySelectorAll('.stepper-step');
    const stepPanels = [
        document.getElementById('stepPanel1'),
        document.getElementById('stepPanel2'),
        document.getElementById('stepPanel3')
    ];

    // ---- Stepper Navigation ----
    function goToStep(step) {
        if (step < 1 || step > 3) return;
        currentStep = step;

        stepperSteps.forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.toggle('active', s === step);
            el.classList.toggle('completed', s < step);
        });

        // Update connectors
        document.querySelectorAll('.stepper-connector').forEach((c, i) => {
            c.classList.toggle('active', (i + 1) < step);
        });

        stepPanels.forEach((p, i) => {
            if (p) {
                p.classList.toggle('active', i + 1 === step);
            }
        });
    }

    // ---- Form Submit ----
    document.getElementById('reportFilters').addEventListener('submit', async function(e) {
        e.preventDefault();
        await generateReport();
    });

    // ---- Navigation Buttons ----
    document.getElementById('prevToStep1').addEventListener('click', () => goToStep(1));
    document.getElementById('nextToStep3').addEventListener('click', () => {
        if (allRecords.length === 0) {
            showToast('Generate a report first before exporting.', 'warning');
            return;
        }
        document.getElementById('exportRecordCount').textContent = allRecords.length;
        goToStep(3);
    });
    document.getElementById('prevToStep2').addEventListener('click', () => goToStep(2));
    document.getElementById('backToFilters').addEventListener('click', () => goToStep(1));
    document.getElementById('newReport').addEventListener('click', () => {
        resetFilters();
        goToStep(1);
    });

    // ---- Reset ----
    document.getElementById('resetFilters').addEventListener('click', resetFilters);

    function resetFilters() {
        document.getElementById('reportFilters').reset();
        document.getElementById('start_date').value = '<?= date("Y-m-01") ?>';
        document.getElementById('end_date').value = '<?= date("Y-m-d") ?>';
        document.getElementById('summarySection').style.display = 'none';
        document.getElementById('resultsCard').style.display = 'none';
        allRecords = [];
        currentFilters = {};
        currentPage = 1;
    }

    // ---- Generate Report ----
    async function generateReport() {
        const formData = new FormData(document.getElementById('reportFilters'));
        const params = new URLSearchParams(formData);

        currentFilters = {
            department: formData.get('department'),
            start_date: formData.get('start_date'),
            end_date: formData.get('end_date'),
            employee_search: formData.get('employee_search'),
            mode: attendanceMode
        };

        try {
            showLoading('Generating report');

            const response = await fetch('../api/get_attendance_report_departments.php?' + params.toString());
            let data = null;
            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }
            hideLoading();

            if (response.ok && data && data.success) {
                allRecords = data.records || [];
                displaySummary(data.summary || {});
                currentPage = 1;
                displayRecords();

                document.getElementById('summarySection').style.display = 'block';
                document.getElementById('resultsCard').style.display = 'block';

                // Move to step 2
                goToStep(2);
                showToast('Report generated  ' + allRecords.length + ' records found.', 'success');
            } else {
                const errMsg = (data && data.message) ? data.message : 'Error generating report.';
                showToast(errMsg, 'error');
            }
        } catch (err) {
            hideLoading();
            console.error(err);
            showToast('Error fetching report data. Please try again.', 'error');
        }
    }

    // ---- Summary ----
    function displaySummary(summary) {
        document.getElementById('total_records').textContent = summary.total_records || 0;
        document.getElementById('completed_count').textContent = summary.completed_count || 0;
        document.getElementById('incomplete_count').textContent = summary.incomplete_count || 0;
        document.getElementById('departments_count').textContent = summary.departments_count || 0;
    }

    // ---- Table ----
    function displayRecords() {
        const tbody = document.getElementById('attendanceTableBody');
        const tableContainer = document.getElementById('tableContainer');
        const noResults = document.getElementById('noResults');
        const paginationContainer = document.getElementById('paginationContainer');

        tbody.innerHTML = '';

        if (allRecords.length === 0) {
            tableContainer.style.display = 'none';
            noResults.style.display = 'flex';
            paginationContainer.style.display = 'none';
            return;
        }

        tableContainer.style.display = 'block';
        noResults.style.display = 'none';

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const pageRecords = allRecords.slice(start, end);

        pageRecords.forEach((rec) => {
            const row = document.createElement('tr');
            const completed = !!rec.time_out;
            const displayName = rec.employee_name || '-';
            const displayDepartment = rec.department || '-';
            row.innerHTML = `
                <td><strong>${esc(rec.employee_id)}</strong></td>
                <td>${esc(displayName)}</td>
                <td><span class="badge badge-green">${esc(displayDepartment)}</span></td>
                <td>${esc(rec.date_formatted)}</td>
                <td>${esc(rec.time_in || '-')}</td>
                <td>${esc(rec.time_out || '-')}</td>
                <td>${esc(rec.duration)}</td>
                <td>
                    <span class="badge ${completed ? 'badge-green' : 'badge-amber'}">
                        <i class="fa-solid ${completed ? 'fa-circle-check' : 'fa-clock'}"></i>
                        ${completed ? 'Completed' : 'Incomplete'}
                    </span>
                </td>`;
            tbody.appendChild(row);
        });

        renderPagination();
    }

    // ---- Pagination ----
    function renderPagination() {
        const totalPages = Math.ceil(allRecords.length / rowsPerPage);
        const container = document.getElementById('paginationContainer');
        container.innerHTML = '';

        if (totalPages <= 1) { container.style.display = 'none'; return; }
        container.style.display = 'flex';

        addPageBtn(container, '<', currentPage === 1, () => { currentPage--; displayRecords(); });

        const pages = genPages(currentPage, totalPages);
        pages.forEach(p => {
            if (p === '...') {
                const dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.textContent = '';
                container.appendChild(dots);
            } else {
                addPageBtn(container, p, false, () => { currentPage = p; displayRecords(); }, p === currentPage);
            }
        });

        addPageBtn(container, '>', currentPage === totalPages, () => { currentPage++; displayRecords(); });
    }

    function addPageBtn(parent, text, disabled, onClick, active) {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = 'pagination-btn' + (active ? ' active' : '');
        btn.disabled = disabled;
        if (!disabled) btn.onclick = onClick;
        parent.appendChild(btn);
    }

    function genPages(cur, total) {
        if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
        const p = [];
        if (cur <= 4) { for (let i = 1; i <= 5; i++) p.push(i); p.push('...'); p.push(total); }
        else if (cur >= total - 3) { p.push(1); p.push('...'); for (let i = total - 4; i <= total; i++) p.push(i); }
        else { p.push(1); p.push('...'); p.push(cur - 1); p.push(cur); p.push(cur + 1); p.push('...'); p.push(total); }
        return p;
    }

    // ---- Export CSV ----
    document.getElementById('exportCSV').addEventListener('click', function() {
        if (allRecords.length === 0) {
            showToast('No data to export.', 'warning');
            return;
        }

        const prog = document.getElementById('exportProgress');
        const fill = document.getElementById('exportProgressFill');
        const text = document.getElementById('exportProgressText');
        prog.style.display = 'block';
        fill.style.width = '0%';
        text.textContent = 'Preparing export';

        // Animate progress bar
        let pct = 0;
        const iv = setInterval(() => {
            pct += 10 + Math.random() * 15;
            if (pct > 90) pct = 90;
            fill.style.width = pct + '%';
            text.textContent = 'Exporting ' + Math.round(pct) + '%';
        }, 200);

        const params = new URLSearchParams(currentFilters);
        window.location.href = '../api/export_attendance_departments_csv.php?' + params.toString();

        setTimeout(() => {
            clearInterval(iv);
            fill.style.width = '100%';
            text.textContent = 'Export complete!';
            showToast('CSV exported successfully.', 'success');
            setTimeout(() => { prog.style.display = 'none'; }, 2000);
        }, 1500);
    });

    // ---- Print ----
    document.getElementById('printReport').addEventListener('click', () => window.print());

    // ---- Density Toggle ----
    document.querySelectorAll('.density-option').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.density-option').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const table = document.getElementById('reportTable');
            if (this.dataset.density === 'compact') {
                table.classList.add('table-compact');
            } else {
                table.classList.remove('table-compact');
            }
        });
    });

    // ---- Helpers ----
    function esc(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function showLoading(msg) {
        const ov = document.createElement('div');
        ov.id = 'loadingOverlay';
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(255,255,255,.7);backdrop-filter:blur(4px);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;';
        ov.innerHTML = '<div class="spinner"></div><div style="font-weight:600;color:var(--gray-700);">' + msg + '</div>';
        document.body.appendChild(ov);
    }

    function hideLoading() {
        const ov = document.getElementById('loadingOverlay');
        if (ov) { ov.style.opacity = '0'; setTimeout(() => ov.remove(), 300); }
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
        toast.innerHTML = '<i class="fa-solid ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span>';
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

})();
</script>

<?php include 'includes/footer_modern.php'; ?>

