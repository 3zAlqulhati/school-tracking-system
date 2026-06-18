<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('student');

$pdo = require __DIR__ . '/../config/db.php';

$stmtS = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ?');
$stmtS->execute([$_SESSION['user_id']]);
$student = $stmtS->fetch();
if (!$student) {
    die('<p style="padding:2rem;font-family:sans-serif;">Student profile not found.</p>');
}
$studentId = (int) $student['student_id'];


$stmtN = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtN->execute([$_SESSION['user_id']]);
$unreadCount = (int) $stmtN->fetchColumn();


$filterMonth = '';
if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $filterMonth = $_GET['month'];
}


$where  = 'WHERE student_id = ?';
$params = [$studentId];
if ($filterMonth !== '') {
    $where  .= ' AND DATE_FORMAT(date, "%Y-%m") = ?';
    $params[] = $filterMonth;
}


$stmtSum = $pdo->prepare(
    "SELECT
        COUNT(*)                      AS total,
        SUM(status = 'present')       AS present_count,
        SUM(status = 'absent')        AS absent_count,
        SUM(status = 'late')          AS late_count
     FROM attendance {$where}"
);
$stmtSum->execute($params);
$summary = $stmtSum->fetch();

$total   = (int) $summary['total'];
$present = (int) $summary['present_count'];
$absent  = (int) $summary['absent_count'];
$late    = (int) $summary['late_count'];
$pct     = $total > 0 ? round(($present / $total) * 100, 1) : 0;


$stmtRec = $pdo->prepare(
    "SELECT date, status, c.class_name
     FROM attendance a
     JOIN classes c ON c.class_id = a.class_id
     {$where}
     ORDER BY a.date DESC"
);
$stmtRec->execute($params);
$records = $stmtRec->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Student Menu</span>
            <a href="/school_tracking/student/index.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/student/view_attendance.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-clipboard-check"></i></span> My Attendance
            </a>
            <a href="/school_tracking/student/view_grades.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-star"></i></span> My Grades
            </a>
            <a href="/school_tracking/student/assignments.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> My Assignments
            </a>
            <a href="/school_tracking/student/notifications.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-bell"></i></span> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-danger" style="margin-left:auto;"><?= $unreadCount ?></span>
                <?php endif; ?>
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

    <div class="main-content">

        <h1 class="page-title"><span class="page-title-icon"><i class="fas fa-clipboard-check"></i></span> My Attendance</h1>

        <!-- Month Filter -->
        <div class="card mb-4" style="padding:1rem 1.5rem;">
            <form method="GET" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <label for="month" style="font-weight:600;margin:0;">Filter by Month:</label>
                <input type="month" id="month" name="month"
                       value="<?= htmlspecialchars($filterMonth) ?>"
                       max="<?= date('Y-m') ?>"
                       style="padding:0.45rem 0.75rem;border:1px solid #cdd5e0;border-radius:6px;">
                <button type="submit" class="btn-primary" style="padding:0.45rem 1.2rem;">Apply</button>
                <?php if ($filterMonth !== ''): ?>
                    <a href="view_attendance.php" class="btn-secondary" style="padding:0.45rem 1.2rem;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:1.5rem;">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $present ?></div>
                    <div class="stat-label">Present</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $absent ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $late ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $pct ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>
        </div>

        <!-- Attendance Rate Bar -->
        <?php if ($total > 0):
            $barColor   = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
            $labelColor = $pct >= 75 ? '#065f46' : '#991b1b';
        ?>
        <div class="card mb-4" style="padding:1.25rem 1.5rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                <span style="font-weight:600;">Attendance Rate</span>
                <span style="font-weight:700;color:<?= $labelColor ?>;">
                    <?= $pct ?>%
                </span>
            </div>
            <div style="background:#e2e8f0;border-radius:999px;height:14px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:999px;transition:width .4s;"></div>
            </div>
            <div style="font-size:0.78rem;color:#6c757d;margin-top:0.4rem;">
                <?= $present ?> present out of <?= $total ?> recorded days
                <?= $pct < 75 ? ' — <span style="color:#dc3545;">Below 75% minimum requirement</span>' : '' ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full Table -->
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-list"></i> Attendance Records</div>
            <?php if (empty($records)): ?>
                <p class="text-muted text-center mt-3 mb-3">No attendance records found.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Date</th><th>Class</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $i => $r):
                            $badge = match($r['status']) {
                                'present' => 'badge-success',
                                'absent'  => 'badge-danger',
                                'late'    => 'badge-warning',
                                default   => 'badge-info',
                            };
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= date('M j, Y (D)', strtotime($r['date'])) ?></td>
                            <td><?= htmlspecialchars($r['class_name']) ?></td>
                            <td>
                                <span class="badge <?= $badge ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
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
