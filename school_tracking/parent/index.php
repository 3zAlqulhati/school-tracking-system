<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('parent');

$pdo = require __DIR__ . '/../config/db.php';


$stmtChildren = $pdo->prepare(
    'SELECT p.parent_id, p.student_id, s.class_id,
            u.full_name AS child_name, c.class_name, c.academic_year
     FROM parents p
     JOIN students s ON s.student_id = p.student_id
     JOIN users    u ON u.user_id    = s.user_id
     LEFT JOIN classes c ON c.class_id = s.class_id
     WHERE p.user_id = ?
     ORDER BY u.full_name'
);
$stmtChildren->execute([$_SESSION['user_id']]);
$children = $stmtChildren->fetchAll();

if (empty($children)) {
    die('<p style="padding:2rem;font-family:sans-serif;">No linked student found for this parent account.</p>');
}


$selectedStudentId = isset($_GET['student_id']) && is_numeric($_GET['student_id'])
                   ? (int)$_GET['student_id'] : 0;

$activeChild = null;
foreach ($children as $ch) {
    if ((int)$ch['student_id'] === $selectedStudentId) {
        $activeChild = $ch;
        break;
    }
}
if (!$activeChild) {
    $activeChild = $children[0];
}
$studentId = (int) $activeChild['student_id'];
$classId   = (int) $activeChild['class_id'];


$stmtAtt = $pdo->prepare(
    'SELECT COUNT(*) AS total, SUM(status = "present") AS present_count
     FROM attendance
     WHERE student_id = ?
       AND MONTH(date) = MONTH(CURDATE())
       AND YEAR(date)  = YEAR(CURDATE())'
);
$stmtAtt->execute([$studentId]);
$attRow = $stmtAtt->fetch();
$attPct = ($attRow['total'] > 0)
        ? round(($attRow['present_count'] / $attRow['total']) * 100, 1)
        : null;


$stmtGpa = $pdo->prepare('SELECT AVG(grade_value) FROM grades WHERE student_id = ?');
$stmtGpa->execute([$studentId]);
$gpa = $stmtGpa->fetchColumn();
$gpa = ($gpa !== null && $gpa !== false) ? round((float)$gpa, 2) : null;


$stmtUnread = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtUnread->execute([$_SESSION['user_id']]);
$unreadCount = (int) $stmtUnread->fetchColumn();


$stmtRecAtt = $pdo->prepare(
    'SELECT date, status FROM attendance
     WHERE student_id = ? ORDER BY date DESC LIMIT 5'
);
$stmtRecAtt->execute([$studentId]);
$recentAtt = $stmtRecAtt->fetchAll();


$stmtRecGr = $pdo->prepare(
    'SELECT subject, grade_value, exam_type, graded_at
     FROM grades WHERE student_id = ?
     ORDER BY graded_at DESC LIMIT 5'
);
$stmtRecGr->execute([$studentId]);
$recentGrades = $stmtRecGr->fetchAll();


$stmtUp = $pdo->prepare(
    'SELECT a.title, a.deadline, u.full_name AS teacher_name
     FROM assignments a
     JOIN teachers t ON t.teacher_id = a.teacher_id
     JOIN users    u ON u.user_id    = t.user_id
     WHERE a.class_id IN (
         SELECT class_id FROM student_classes WHERE student_id = ?
         UNION ALL
         SELECT class_id FROM students WHERE student_id = ? AND class_id IS NOT NULL
     ) AND a.deadline > NOW()
     ORDER BY a.deadline ASC LIMIT 5'
);
$stmtUp->execute([$studentId, $studentId]);
$upcoming = $stmtUp->fetchAll();


function childUrl(int $sid, string $page = 'index'): string {
    return "/school_tracking/parent/{$page}.php?student_id={$sid}";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Parent Menu</span>
            <a href="<?= childUrl($studentId) ?>" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="<?= childUrl($studentId, 'view_child') ?>" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-user-graduate"></i></span> Child Details
            </a>
            <a href="/school_tracking/parent/notifications.php" class="sidebar-link">
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

        <h1 class="page-title">
            <span class="page-title-icon"><i class="fas fa-home"></i></span>
            Parent Dashboard
        </h1>

        <!-- Child selector (if multiple children) -->
        <?php if (count($children) > 1): ?>
        <div class="card mb-4" style="padding:1rem 1.5rem;">
            <form method="GET" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <label style="font-weight:600;margin:0;">Viewing child:</label>
                <select name="student_id" onchange="this.form.submit()"
                        style="padding:0.45rem 0.75rem;border:1px solid #cdd5e0;border-radius:6px;min-width:200px;">
                    <?php foreach ($children as $ch): ?>
                        <option value="<?= $ch['student_id'] ?>"
                            <?= $ch['student_id'] == $studentId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ch['child_name']) ?>
                            (<?= htmlspecialchars($ch['class_name'] ?? 'No class') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- Child header -->
        <div class="card mb-4" style="padding:1.25rem 1.5rem;background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;">
            <div style="font-size:1.5rem;font-weight:700;">
                <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($activeChild['child_name']) ?>
            </div>
            <div style="font-size:0.9rem;opacity:.85;margin-top:0.3rem;">
                Class: <?= htmlspecialchars($activeChild['class_name'] ?? 'Not assigned') ?>
                <?php if ($activeChild['academic_year']): ?>
                    &nbsp;|&nbsp; Year: <?= htmlspecialchars($activeChild['academic_year']) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $attPct !== null ? $attPct . '%' : 'N/A' ?></div>
                    <div class="stat-label">Attendance This Month</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $gpa !== null ? $gpa : 'N/A' ?></div>
                    <div class="stat-label">Current GPA</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-bell"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $unreadCount ?></div>
                    <div class="stat-label">Unread Notifications</div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

            <!-- Recent Attendance -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title"><i class="fas fa-clipboard-check"></i> Recent Attendance</span>
                    <a href="<?= childUrl($studentId, 'view_child') ?>#attendance"
                       style="font-size:0.82rem;font-weight:400;">View All &rsaquo;</a>
                </div>
                <?php if (empty($recentAtt)): ?>
                    <p class="text-muted text-center mt-3 mb-3">No records yet.</p>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentAtt as $r):
                                $badge = match($r['status']) {
                                    'present' => 'badge-success',
                                    'absent'  => 'badge-danger',
                                    'late'    => 'badge-warning',
                                    default   => 'badge-info',
                                };
                            ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($r['date'])) ?></td>
                                <td><span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Grades -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title"><i class="fas fa-star"></i> Recent Grades</span>
                    <a href="<?= childUrl($studentId, 'view_child') ?>#grades"
                       style="font-size:0.82rem;font-weight:400;">View All &rsaquo;</a>
                </div>
                <?php if (empty($recentGrades)): ?>
                    <p class="text-muted text-center mt-3 mb-3">No grades yet.</p>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Subject</th><th>Type</th><th>Grade</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentGrades as $g):
                                $v   = (float) $g['grade_value'];
                                $cls = $v >= 90 ? 'badge-success'
                                     : ($v >= 70 ? 'badge-info'
                                     : ($v >= 50 ? 'badge-warning' : 'badge-danger'));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($g['subject']) ?></td>
                                <td><?= htmlspecialchars($g['exam_type'] ?? '—') ?></td>
                                <td><span class="badge <?= $cls ?>"><?= $v ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Upcoming Deadlines -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Upcoming Assignment Deadlines</span>
            </div>
            <?php if (empty($upcoming)): ?>
                <p class="text-muted text-center mt-3 mb-3">No upcoming assignments.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Assignment</th><th>Teacher</th><th>Deadline</th><th>Time Left</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $i => $a):
                            $diff  = strtotime($a['deadline']) - time();
                            $days  = (int) floor($diff / 86400);
                            $hours = (int) floor(($diff % 86400) / 3600);
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                            <td><?= htmlspecialchars($a['teacher_name']) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($a['deadline'])) ?></td>
                            <td>
                                <span class="badge <?= $days < 2 ? 'badge-danger' : 'badge-info' ?>">
                                    <?= $days > 0 ? "{$days}d {$hours}h" : "{$hours}h" ?>
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
