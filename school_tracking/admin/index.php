<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('admin');

$pdo = require __DIR__ . '/../config/db.php';

$chartClassId = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$allClassesForFilter = $pdo->query(
    'SELECT class_id, class_name FROM classes ORDER BY class_name'
)->fetchAll();

$counts = [];
foreach ([
    'students'    => 'SELECT COUNT(*) FROM students',
    'teachers'    => 'SELECT COUNT(*) FROM teachers',
    'classes'     => 'SELECT COUNT(*) FROM classes',
    'assignments' => 'SELECT COUNT(*) FROM assignments',
    'parents'     => 'SELECT COUNT(*) FROM parents',
    'submissions' => 'SELECT COUNT(*) FROM assignment_submissions',
] as $key => $sql) {
    $counts[$key] = (int) $pdo->query($sql)->fetchColumn();
}

if ($chartClassId > 0) {
    $stmtAtt = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt FROM attendance WHERE class_id = ? GROUP BY status"
    );
    $stmtAtt->execute([$chartClassId]);
    $attStats = $stmtAtt->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    $attStats = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM attendance GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
}

$attPresent = (int)($attStats['present'] ?? 0);
$attAbsent  = (int)($attStats['absent']  ?? 0);
$attLate    = (int)($attStats['late']    ?? 0);

if ($chartClassId > 0) {
    $stmtGrade = $pdo->prepare(
        "SELECT
            SUM(g.grade_value >= 90)                          AS a_count,
            SUM(g.grade_value >= 70 AND g.grade_value < 90)  AS b_count,
            SUM(g.grade_value >= 50 AND g.grade_value < 70)  AS c_count,
            SUM(g.grade_value < 50)                           AS f_count
         FROM grades g
         JOIN students s ON s.student_id = g.student_id
         WHERE s.class_id = ?"
    );
    $stmtGrade->execute([$chartClassId]);
    $gradeDistrib = $stmtGrade->fetch();
} else {
    $gradeDistrib = $pdo->query(
        "SELECT
            SUM(grade_value >= 90)              AS a_count,
            SUM(grade_value >= 70 AND grade_value < 90) AS b_count,
            SUM(grade_value >= 50 AND grade_value < 70) AS c_count,
            SUM(grade_value < 50)               AS f_count
         FROM grades"
    )->fetch();
}

$recentUsers = $pdo->query(
    'SELECT user_id, full_name, role, email, created_at
     FROM users ORDER BY created_at DESC LIMIT 8'
)->fetchAll();

$roleStats = $pdo->query(
    "SELECT role, COUNT(*) AS cnt FROM users GROUP BY role"
)->fetchAll(PDO::FETCH_KEY_PAIR);

if ($chartClassId > 0) {
    $stmtRecentAtt = $pdo->prepare(
        "SELECT DATE(date) AS day,
                SUM(status='present') AS present,
                SUM(status='absent')  AS absent,
                SUM(status='late')    AS late
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND class_id = ?
         GROUP BY DATE(date)
         ORDER BY day ASC"
    );
    $stmtRecentAtt->execute([$chartClassId]);
    $recentAtt = $stmtRecentAtt->fetchAll();
} else {
    $recentAtt = $pdo->query(
        "SELECT DATE(date) AS day,
                SUM(status='present') AS present,
                SUM(status='absent')  AS absent,
                SUM(status='late')    AS late
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(date)
         ORDER BY day ASC"
    )->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Main Menu</span>
            <a href="/school_tracking/admin/index.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/admin/manage_users.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="/school_tracking/admin/manage_classes.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-school"></i></span> Manage Classes
            </a>
            <a href="/school_tracking/admin/reports.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span> Reports
            </a>
            <a href="/school_tracking/admin/notifications.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-bell"></i></span> Send Notifications
            </a>
            <a href="/school_tracking/admin/inbox.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-inbox"></i></span> Inbox
            </a>
        </div>
        <hr class="sidebar-divider">
        <div class="sidebar-section">
            <span class="sidebar-label">Account</span>
            <a href="/school_tracking/logout.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">

        <h1 class="page-title">
            <span class="page-title-icon"><i class="fas fa-tachometer-alt"></i></span>
            Admin Dashboard
            <span class="text-muted" style="font-size:.82rem;font-weight:400;margin-left:auto;">
                <?= date('l, F j, Y') ?>
            </span>
        </h1>

        <!-- Stat Cards -->
        <div class="dashboard-grid">
            <div class="card stat-card hover-lift">
                <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['teachers'] ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon navy"><i class="fas fa-school"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['classes'] ?></div>
                    <div class="stat-label">Active Classes</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon orange"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['assignments'] ?></div>
                    <div class="stat-label">Assignments</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon purple"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['parents'] ?></div>
                    <div class="stat-label">Parents</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon teal"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $counts['submissions'] ?></div>
                    <div class="stat-label">Submissions</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

            <!-- Attendance Trend Chart -->
            <div class="card">
                <div class="card-header" style="flex-wrap:wrap;gap:0.5rem;">
                    <span class="card-header-title">
                        <i class="fas fa-chart-line" style="color:var(--primary);"></i>
                        Attendance — Last 7 Days
                        <?php if ($chartClassId > 0): ?>
                            <?php $selCls = array_filter($allClassesForFilter, fn($c) => (int)$c['class_id'] === $chartClassId); $selCls = reset($selCls); ?>
                            <span class="badge badge-info" style="margin-left:0.4rem;font-size:0.72rem;">
                                <?= $selCls ? htmlspecialchars($selCls['class_name']) : '' ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <form method="GET" style="display:flex;align-items:center;gap:0.4rem;margin:0;">
                        <select name="class_id" onchange="this.form.submit()"
                                style="font-size:0.8rem;padding:0.3rem 0.6rem;border:1px solid #cdd5e0;border-radius:6px;">
                            <option value="">All Classes</option>
                            <?php foreach ($allClassesForFilter as $cls): ?>
                                <option value="<?= $cls['class_id'] ?>"
                                    <?= $chartClassId === (int)$cls['class_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($chartClassId > 0): ?>
                            <a href="index.php" class="btn-secondary btn-sm" style="padding:0.3rem 0.6rem;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="chart-container" style="min-height:240px;">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>
            </div>

            <!-- Attendance Donut -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                        Attendance Overview
                    </span>
                </div>
                <div class="chart-container" style="min-height:200px;padding:0.5rem;">
                    <canvas id="attDonutChart"></canvas>
                </div>
                <div class="chart-legend" style="margin-top:.5rem;">
                    <div class="legend-item"><div class="legend-dot" style="background:#10b981;"></div> Present (<?= $attPresent ?>)</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> Absent (<?= $attAbsent ?>)</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div> Late (<?= $attLate ?>)</div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

            <!-- Grade Distribution -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-star" style="color:var(--primary);"></i>
                        Grade Distribution
                    </span>
                </div>
                <div class="chart-container" style="min-height:220px;">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>

            <!-- User Roles -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-users" style="color:var(--primary);"></i>
                        Users by Role
                    </span>
                </div>
                <div class="chart-container" style="min-height:220px;padding:0.5rem;">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Users Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title">
                    <i class="fas fa-user-clock" style="color:var(--primary);"></i>
                    Recently Added Users
                </span>
                <a href="/school_tracking/admin/manage_users.php" class="btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View All
                </a>
            </div>

            <?php if (empty($recentUsers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                    <h3>No users yet</h3>
                    <p>Add users to get started.</p>
                </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $i => $u): ?>
                        <tr>
                            <td data-label="#"><?= $u['user_id'] ?></td>
                            <td data-label="Name">
                                <div style="display:flex;align-items:center;gap:.65rem;">
                                    <div class="avatar avatar-sm" style="background:<?= ['admin'=>'linear-gradient(135deg,#991b1b,#ef4444)','teacher'=>'linear-gradient(135deg,#065f46,#10b981)','student'=>'linear-gradient(135deg,#1e40af,#3b82f6)','parent'=>'linear-gradient(135deg,#78350f,#f59e0b)'][$u['role']] ?? 'var(--primary)' ?>;">
                                        <?= strtoupper(substr($u['full_name'], 0, 2)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                </div>
                            </td>
                            <td data-label="Email" class="text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                            <td data-label="Role">
                                <span class="role-badge role-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td data-label="Joined" class="text-muted text-sm">
                                <?= date('M j, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td data-label="Actions">
                                <a href="/school_tracking/admin/manage_users.php?edit=<?= $u['user_id'] ?>" class="btn-primary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const attTrendCtx = document.getElementById('attendanceTrendChart');
if (attTrendCtx) {
    const trendData = <?= json_encode(array_values($recentAtt)) ?>;
    const labels    = trendData.map(r => {
        const d = new Date(r.day);
        return d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
    });

    new Chart(attTrendCtx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Present',
                    data: trendData.map(r => parseInt(r.present) || 0),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.08)',
                    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6,
                    borderWidth: 2,
                },
                {
                    label: 'Absent',
                    data: trendData.map(r => parseInt(r.absent) || 0),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.06)',
                    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6,
                    borderWidth: 2,
                },
                {
                    label: 'Late',
                    data: trendData.map(r => parseInt(r.late) || 0),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.06)',
                    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6,
                    borderWidth: 2,
                },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20, font: { size: 12, family: 'Inter' } } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, stepSize: 1 } }
            }
        }
    });
}

const attDonutCtx = document.getElementById('attDonutChart');
if (attDonutCtx) {
    new Chart(attDonutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present','Absent','Late'],
            datasets: [{
                data: [<?= $attPresent ?>, <?= $attAbsent ?>, <?= $attLate ?>],
                backgroundColor: ['#10b981','#ef4444','#f59e0b'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });
}

const gradeCtx = document.getElementById('gradeChart');
if (gradeCtx) {
    const gd = <?= json_encode($gradeDistrib) ?>;
    new Chart(gradeCtx, {
        type: 'bar',
        data: {
            labels: ['A (≥90)', 'B (70-89)', 'C (50-69)', 'F (<50)'],
            datasets: [{
                label: 'Students',
                data: [parseInt(gd.a_count)||0, parseInt(gd.b_count)||0, parseInt(gd.c_count)||0, parseInt(gd.f_count)||0],
                backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ef4444'],
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 12 } } },
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { stepSize: 1, font: { size: 11 } } }
            }
        }
    });
}

const roleCtx = document.getElementById('roleChart');
if (roleCtx) {
    const rd = <?= json_encode($roleStats) ?>;
    new Chart(roleCtx, {
        type: 'pie',
        data: {
            labels: Object.keys(rd).map(r => r.charAt(0).toUpperCase() + r.slice(1)),
            datasets: [{
                data: Object.values(rd),
                backgroundColor: ['#ef4444','#10b981','#3b82f6','#f59e0b'],
                borderWidth: 2, borderColor: '#fff',
                hoverOffset: 5,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 12, family: 'Inter' }, usePointStyle: true } }
            }
        }
    });
}
</script>
