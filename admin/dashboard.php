<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$attendanceMode = 'employee';
$isEmployeeMode = true;
$entitySingular = 'Employee';
$entityPlural = 'Employees';
$modeQuery = '';
$reportsUrl = 'attendance_reports_departments.php';

$pageTitle = 'Dashboard';
$pageIcon = 'home';

/**
 * Fetch all dashboard data in optimized queries
 * Returns comprehensive dashboard statistics
 */
function getDashboardData($pdo) {
    $data = [];
    
    try {
        // 1. STAT CARDS DATA
        // Total employees
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE is_active = 1");
        $data['totalEmployees'] = (int)$stmt->fetch()['total'];
        
        // Today's attendance
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT employee_id) as present
            FROM employee_attendance 
            WHERE date = CURDATE() AND time_in IS NOT NULL
        ");
        $stmt->execute();
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['presentToday'] = (int)$todayStats['present'];
        
        // Absent employees today
        $data['absentToday'] = $data['totalEmployees'] - $data['presentToday'];
        
        // Today's attendance rate
        $data['attendanceRate'] = $data['totalEmployees'] > 0 
            ? round(($data['presentToday'] / $data['totalEmployees']) * 100, 1) 
            : 0;
        
        // 2. WEEKLY ATTENDANCE TREND (Last 7 days - Present vs Absent)
        $stmt = $pdo->prepare("
            WITH RECURSIVE dates AS (
                SELECT DATE_SUB(CURDATE(), INTERVAL 6 DAY) as date
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY)
                FROM dates
                WHERE date < CURDATE()
            )
            SELECT 
                dates.date,
                COALESCE(COUNT(DISTINCT a.employee_id), 0) as present,
                (SELECT COUNT(*) FROM employees WHERE is_active = 1) - COALESCE(COUNT(DISTINCT a.employee_id), 0) as absent
            FROM dates
            LEFT JOIN employee_attendance a ON dates.date = a.date
            GROUP BY dates.date
            ORDER BY dates.date ASC
        ");
        $stmt->execute();
        $data['weeklyTrend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. ATTENDANCE BY DEPARTMENT (Today)
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(s.department_code, 'No Department') as department,
                COUNT(DISTINCT CASE WHEN a.date = CURDATE() AND a.time_in IS NOT NULL THEN a.employee_id END) as present,
                COUNT(DISTINCT s.employee_id) as total
            FROM employees s
            LEFT JOIN employee_attendance a ON s.employee_id = a.employee_id
            WHERE s.is_active = 1
            GROUP BY s.department_code
            HAVING total > 0
            ORDER BY department
        ");
        $stmt->execute();
        $data['departmentAttendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. RECENT ACTIVITY (Last 10 records with time_out info)
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.employee_id,
                s.first_name,
                s.last_name,
                COALESCE(s.department_code, 'N/A') as department,
                a.time_in,
                a.time_out,
                a.date,
                CASE 
                    WHEN a.time_out IS NULL AND a.date < CURDATE() THEN 'incomplete'
                    WHEN a.time_out IS NOT NULL THEN 'complete'
                    ELSE 'present'
                END as status,
                a.created_at
            FROM employee_attendance a
            JOIN employees s ON a.employee_id = s.employee_id
            ORDER BY a.updated_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $data['recentActivity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. NEEDS ATTENTION (Incomplete attendance records)
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.employee_id,
                s.first_name,
                s.last_name,
                COALESCE(s.department_code, 'N/A') as department,
                a.date,
                a.time_in,
                DATEDIFF(CURDATE(), a.date) as days_ago
            FROM employee_attendance a
            JOIN employees s ON a.employee_id = s.employee_id
            WHERE a.time_out IS NULL 
            AND a.date < CURDATE()
            ORDER BY a.date DESC
            LIMIT 15
        ");
        $stmt->execute();
        $data['needsAttention'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 6. ADDITIONAL STATS
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_attendance");
        $data['totalRecords'] = (int)$stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT COALESCE(department_code, 'default')) as total FROM employees WHERE is_active = 1");
        $data['activeDepartments'] = (int)$stmt->fetch()['total'];
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Dashboard data fetch error: " . $e->getMessage());
        // Return safe defaults
        return [
            'totalEmployees' => 0,
            'presentToday' => 0,
            'absentToday' => 0,
            'attendanceRate' => 0,
            'weeklyTrend' => [],
            'departmentAttendance' => [],
            'recentActivity' => [],
            'needsAttention' => [],
            'totalRecords' => 0,
            'activeDepartments' => 0
        ];
    }
}

// Fetch all dashboard data
$dashboardData = getDashboardData($pdo);

// Extract for easy access
$totalEmployees = $dashboardData['totalEmployees'];
$presentToday = $dashboardData['presentToday'];
$absentToday = $dashboardData['absentToday'];
$attendanceRate = $dashboardData['attendanceRate'];
$totalRecords = $dashboardData['totalRecords'];
$activeDepartments = $dashboardData['activeDepartments'];
$recentAttendance = $dashboardData['recentActivity'];

// Include the modern admin header
$breadcrumb = [
    ['label' => 'Dashboard', 'icon' => 'house', 'url' => 'dashboard.php']
];
$pageDescription = 'Monitor attendance activity and workforce statistics';
include 'includes/header_modern.php';
?>

<!-- Dashboard Data (JSON) -->
<script>
    window.dashboardData = <?php echo json_encode($dashboardData); ?>;
</script>

<!-- Loading Overlay -->
<div class="dash-loader" id="dashboardLoader">
    <div class="dash-loader-content">
        <div class="dash-loader-spinner"></div>
        <p>Loading Dashboard...</p>
    </div>
</div>

<!--  Top Stats Row  -->
<div class="dash-stats-row">
    <!-- Attendance Rate Ring (feature card) -->
    <div class="dash-ring-card">
        <div class="dash-ring-wrap">
            <svg class="dash-ring-svg" viewBox="0 0 120 120">
                <circle class="dash-ring-track" cx="60" cy="60" r="52" />
                <circle class="dash-ring-fill" cx="60" cy="60" r="52"
                    stroke-dasharray="326.73"
                    stroke-dashoffset="<?php echo 326.73 - (326.73 * $attendanceRate / 100); ?>" />
            </svg>
            <div class="dash-ring-label">
                <span class="dash-ring-value"><?php echo $attendanceRate; ?></span>
                <span class="dash-ring-unit">%</span>
            </div>
        </div>
        <div class="dash-ring-info">
            <span class="dash-ring-title">Attendance Rate</span>
            <span class="dash-ring-sub">Today's overview</span>
        </div>
    </div>

    <!-- Mini stat cards -->
    <div class="dash-mini-stat">
        <div class="dash-mini-icon dash-mini-icon--green"><i class="fa-solid fa-user-group"></i></div>
        <div class="dash-mini-body">
            <span class="dash-mini-value"><?php echo number_format($totalEmployees); ?></span>
            <span class="dash-mini-label">Total Employees</span>
        </div>
        <div class="dash-mini-chip"><i class="fa-solid fa-table-cells-large"></i> <?php echo $activeDepartments; ?> departments</div>
    </div>

    <div class="dash-mini-stat">
        <div class="dash-mini-icon dash-mini-icon--green"><i class="fa-solid fa-user-check"></i></div>
        <div class="dash-mini-body">
            <span class="dash-mini-value"><?php echo number_format($presentToday); ?></span>
            <span class="dash-mini-label">Present Today</span>
        </div>
        <div class="dash-mini-chip dash-mini-chip--green"><i class="fa-solid fa-arrow-trend-up"></i> <?php echo $attendanceRate; ?>%</div>
    </div>

    <div class="dash-mini-stat">
        <div class="dash-mini-icon dash-mini-icon--amber"><i class="fa-solid fa-user-xmark"></i></div>
        <div class="dash-mini-body">
            <span class="dash-mini-value"><?php echo number_format($absentToday); ?></span>
            <span class="dash-mini-label">Absent Today</span>
        </div>
        <div class="dash-mini-chip dash-mini-chip--amber"><i class="fa-solid fa-arrow-trend-down"></i> <?php echo number_format(100 - $attendanceRate, 1); ?>%</div>
    </div>

    <div class="dash-mini-stat">
        <div class="dash-mini-icon dash-mini-icon--green"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="dash-mini-body">
            <span class="dash-mini-value"><?php echo number_format($totalRecords); ?></span>
            <span class="dash-mini-label">Total Records</span>
        </div>
        <div class="dash-mini-chip"><i class="fa-solid fa-database"></i> All time</div>
    </div>
</div>

<!--  Main Bento Grid  -->
<div class="dash-bento">
    <!-- Weekly Trend  wide card -->
    <div class="dash-card dash-bento-wide">
        <div class="dash-card-header">
            <h3 class="dash-card-title"><i class="fa-solid fa-chart-area"></i> Weekly Attendance Trend</h3>
            <span class="dash-card-subtitle">Last 7 days</span>
        </div>
        <div class="dash-card-body">
            <div class="dash-chart-wrap">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Department Doughnut -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title"><i class="fa-solid fa-chart-pie"></i> By Department</h3>
        </div>
        <div class="dash-card-body">
            <div class="dash-chart-wrap dash-chart-wrap--sm">
                <canvas id="departmentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title"><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="dash-card-body">
            <div class="dash-actions">
                <a href="manage_employees.php<?php echo $modeQuery; ?>" class="dash-action">
                    <div class="dash-action-icon"><i class="fa-solid fa-user-plus"></i></div>
                    <span>Add <?php echo $entitySingular; ?></span>
                </a>
                <a href="manual_attendance.php<?php echo $modeQuery; ?>" class="dash-action">
                    <div class="dash-action-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <span>Manual Entry</span>
                </a>
                <a href="../scan_attendance.php<?php echo $modeQuery; ?>" class="dash-action" target="_blank">
                    <div class="dash-action-icon"><i class="fa-solid fa-qrcode"></i></div>
                    <span>QR Scanner</span>
                </a>
                <a href="<?php echo $reportsUrl; ?>" class="dash-action">
                    <div class="dash-action-icon"><i class="fa-solid fa-chart-column"></i></div>
                    <span>Reports</span>
                </a>
                <a href="view_employees.php<?php echo $modeQuery; ?>" class="dash-action">
                    <div class="dash-action-icon"><i class="fa-solid fa-list-check"></i></div>
                    <span><?php echo $entitySingular; ?> List</span>
                </a>
                <a href="manage_departments.php<?php echo $modeQuery; ?>" class="dash-action">
                    <div class="dash-action-icon"><i class="fa-solid fa-table-cells-large"></i></div>
                    <span>Departments</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Attendance  wide card -->
    <div class="dash-card dash-bento-wide">
        <div class="dash-card-header">
            <h3 class="dash-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Attendance</h3>
            <a href="<?php echo $reportsUrl; ?>" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="dash-card-body-flush">
            <?php if (!empty($recentAttendance)): ?>
                <?php foreach ($recentAttendance as $record): ?>
                    <div class="dash-activity-item">
                        <div class="dash-activity-left">
                            <div class="dash-activity-avatar">
                                <?php echo strtoupper(substr($record['first_name'], 0, 1)); ?>
                            </div>
                            <div class="dash-activity-info">
                                <div class="dash-activity-name"><?php echo sanitizeOutput($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                <div class="dash-activity-meta"><?php echo sanitizeOutput($record['department']); ?> &bull; In: <?php echo date('g:i A', strtotime($record['time_in'])); ?><?php echo $record['time_out'] ? ' &bull; Out: ' . date('g:i A', strtotime($record['time_out'])) : ''; ?></div>
                            </div>
                        </div>
                        <span class="dash-badge dash-badge-<?php echo $record['status'] === 'incomplete' ? 'warning' : ($record['status'] === 'complete' ? 'success' : 'primary'); ?>">
                            <?php echo $record['status'] === 'complete' ? 'Complete' : ($record['status'] === 'incomplete' ? 'Incomplete' : 'Present'); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dash-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No attendance records yet today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Needs Attention -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">
                <i class="fa-solid fa-triangle-exclamation" style="color: var(--amber-600);"></i>
                Needs Attention
            </h3>
            <a href="manual_attendance.php<?php echo $modeQuery; ?>" class="btn btn-sm btn-outline">Fix</a>
        </div>
        <div class="dash-card-body-flush" style="max-height: 380px; overflow-y: auto;">
            <div id="needsAttentionList">
                <div class="dash-empty">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Employee Dashboard - Enhanced Charts & Interactions
 */
(function() {
    'use strict';
    const data = window.dashboardData;
    if (!data) { console.error('Dashboard data not available'); return; }

    let weeklyChart = null, departmentChart = null;

    const tooltipStyle = {
        backgroundColor: 'rgba(255,255,255,0.94)',
        titleColor: '#0f172a',
        bodyColor: '#475569',
        borderColor: 'rgba(0,0,0,0.06)',
        borderWidth: 1,
        padding: 14,
        titleFont: { size: 13, weight: '700', family: "'Manrope', sans-serif" },
        bodyFont: { size: 12, family: "'Manrope', sans-serif" },
        bodySpacing: 6,
        cornerRadius: 12,
        displayColors: true,
        boxPadding: 5,
        caretSize: 6,
        boxWidth: 10,
        boxHeight: 10,
        usePointStyle: true
    };

    /*  Weekly Attendance: stacked area chart  */
    function initWeeklyChart() {
        const ctx = document.getElementById('weeklyChart');
        if (!ctx) return;
        const weekly = data.weeklyTrend || [];
        const labels = weekly.map(d => {
            const dt = new Date(d.date);
            return dt.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        });
        const present = weekly.map(d => parseInt(d.present) || 0);
        const absent  = weekly.map(d => parseInt(d.absent)  || 0);

        weeklyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Present',
                        data: present,
                        borderColor: '#1ea85b',
                        backgroundColor: function(context) {
                            const chart = context.chart;
                            const {ctx: c, chartArea} = chart;
                            if (!chartArea) return 'rgba(30,168,91,0.15)';
                            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(30,168,91,0.28)');
                            g.addColorStop(1, 'rgba(30,168,91,0.02)');
                            return g;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#1ea85b',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Absent',
                        data: absent,
                        borderColor: '#ef4444',
                        backgroundColor: function(context) {
                            const chart = context.chart;
                            const {ctx: c, chartArea} = chart;
                            if (!chartArea) return 'rgba(239,68,68,0.10)';
                            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(239,68,68,0.18)');
                            g.addColorStop(1, 'rgba(239,68,68,0.01)');
                            return g;
                        },
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#ef4444',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top', align: 'end',
                        labels: {
                            boxWidth: 10, boxHeight: 10, padding: 16,
                            color: '#64748b',
                            font: { size: 12, weight: '600' },
                            usePointStyle: true, pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        ...tooltipStyle,
                        callbacks: {
                            label: function(ctx) {
                                const v = ctx.parsed.y || 0;
                                const total = present[ctx.dataIndex] + absent[ctx.dataIndex];
                                const pct = total > 0 ? ((v / total) * 100).toFixed(1) : 0;
                                return ` ${ctx.dataset.label}: ${v} (${pct}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 11, weight: '500' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                        ticks: { color: '#94a3b8', precision: 0, font: { size: 11 } }
                    }
                }
            }
        });
    }

    /*  Department Attendance: doughnut  */
    function initDepartmentChart() {
        const ctx = document.getElementById('departmentChart');
        if (!ctx) return;
        const departments = data.departmentAttendance || [];
        const labels    = departments.map(d => d.department || 'Unknown');
        const presData  = departments.map(d => parseInt(d.present) || 0);
        const totData   = departments.map(d => parseInt(d.total)   || 0);
        const palette = [
            '#178a4a','#1ea85b','#27c36a','#34b868','#5cc885',
            '#8fd9ab','#64748b','#94a3b8','#0e6e3c','#b4f0d2'
        ];

        departmentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: presData,
                    backgroundColor: palette.slice(0, labels.length),
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 8,
                    spacing: 3
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10, boxHeight: 10, padding: 12,
                            color: '#64748b',
                            font: { size: 11, weight: '600' },
                            usePointStyle: true, pointStyle: 'circle',
                            generateLabels: function(chart) {
                                return chart.data.labels.map((l, i) => {
                                    const v = chart.data.datasets[0].data[i];
                                    const t = totData[i];
                                    const p = t ? ((v/t)*100).toFixed(0) : 0;
                                    return {
                                        text: `${l}: ${v}/${t} (${p}%)`,
                                        fillStyle: palette[i],
                                        strokeStyle: '#fff',
                                        lineWidth: 2, hidden: false, index: i
                                    };
                                });
                            }
                        },
                        onClick: function(e, item, legend) {
                            const meta = legend.chart.getDatasetMeta(0);
                            meta.data[item.index].hidden = !meta.data[item.index].hidden;
                            legend.chart.update();
                        }
                    },
                    tooltip: {
                        ...tooltipStyle,
                        callbacks: {
                            label: function(ctx) {
                                const v = ctx.parsed || 0;
                                const t = totData[ctx.dataIndex] || 0;
                                const p = t ? ((v/t)*100).toFixed(1) : 0;
                                return [` Present: ${v}`, ` Absent: ${t-v}`, ` Rate: ${p}%`];
                            }
                        }
                    }
                }
            }
        });
    }

    function populateRecentActivity() {
        document.querySelectorAll('.dash-activity-item').forEach((el, i) => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(-16px)';
            setTimeout(() => {
                el.style.transition = 'all 0.3s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateX(0)';
            }, i * 40);
        });
    }

    function populateNeedsAttention() {
        const c = document.getElementById('needsAttentionList');
        if (!c) return;
        const items = data.needsAttention || [];
        if (!items.length) {
            c.innerHTML = '<div class="dash-empty"><i class="fa-solid fa-circle-check"></i><p>All records complete!</p></div>';
            return;
        }
        c.innerHTML = items.map(r => {
            const days = parseInt(r.days_ago) || 0;
            const dt = new Date(r.date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
            const ti = r.time_in ? new Date('2000-01-01 '+r.time_in).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}) : 'N/A';
            return `<div class="dash-attention-item">
                <div class="dash-attention-left">
                    <div class="dash-attention-icon"><i class="fa-solid fa-exclamation"></i></div>
                    <div class="dash-attention-info">
                        <h4>${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}</h4>
                        <p>${escapeHtml(r.department || 'N/A')} &bull; ${dt} &bull; In: ${ti}</p>
                    </div>
                </div>
                <span class="dash-badge dash-badge-error">${days === 1 ? '1 day ago' : days+' days ago'}</span>
            </div>`;
        }).join('');
        c.querySelectorAll('.dash-attention-item').forEach((el, i) => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(16px)';
            setTimeout(() => {
                el.style.transition = 'all 0.3s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateX(0)';
            }, i * 40);
        });
    }

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function hideLoader() {
        const l = document.getElementById('dashboardLoader');
        if (l) setTimeout(() => l.classList.add('hidden'), 400);
    }

    function init() {
        initWeeklyChart();
        initDepartmentChart();
        populateRecentActivity();
        populateNeedsAttention();
        hideLoader();
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();

    setInterval(() => window.location.reload(), 5 * 60 * 1000);
})();
</script>

<?php include 'includes/footer_modern.php'; ?>

