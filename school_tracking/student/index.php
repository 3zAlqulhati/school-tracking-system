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


$stmtClassIds = $pdo->prepare('SELECT class_id FROM student_classes WHERE student_id = ?');
$stmtClassIds->execute([$studentId]);
$classIds = array_column($stmtClassIds->fetchAll(), 'class_id');
$classId  = !empty($classIds) ? $classIds[0] : 0; // primary class for single-class queries


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


if (!empty($classIds)) {
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $stmtPend = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments
         WHERE class_id IN ({$ph})
           AND (deadline IS NULL OR deadline > NOW())
           AND assignment_id NOT IN (
               SELECT assignment_id FROM assignment_submissions WHERE student_id = ?
           )"
    );
    $stmtPend->execute(array_merge($classIds, [$studentId]));
    $pendingCount = (int) $stmtPend->fetchColumn();
} else {
    $pendingCount = 0;
}


$stmtGpa = $pdo->prepare('SELECT AVG(grade_value) FROM grades WHERE student_id = ?');
$stmtGpa->execute([$studentId]);
$gpa = $stmtGpa->fetchColumn();
$gpa = ($gpa !== null && $gpa !== false) ? round((float)$gpa, 2) : null;


if (!empty($classIds)) {
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $stmtUp = $pdo->prepare(
        "SELECT a.title, a.deadline, u.full_name AS teacher_name
         FROM assignments a
         JOIN teachers t ON t.teacher_id = a.teacher_id
         JOIN users    u ON u.user_id    = t.user_id
         WHERE a.class_id IN ({$ph})
           AND a.deadline > NOW()
           AND a.assignment_id NOT IN (
               SELECT assignment_id FROM assignment_submissions WHERE student_id = ?
           )
         ORDER BY a.deadline ASC LIMIT 5"
    );
    $stmtUp->execute(array_merge($classIds, [$studentId]));
    $upcoming = $stmtUp->fetchAll();
} else {
    $upcoming = [];
}


$stmtGr = $pdo->prepare(
    'SELECT subject, grade_value, exam_type, graded_at
     FROM grades WHERE student_id = ?
     ORDER BY graded_at DESC LIMIT 3'
);
$stmtGr->execute([$studentId]);
$recentGrades = $stmtGr->fetchAll();


$stmtN = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtN->execute([$_SESSION['user_id']]);
$unreadCount = (int) $stmtN->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Student Menu</span>
            <a href="/school_tracking/student/index.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/student/view_attendance.php" class="sidebar-link">
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

        <h1 class="page-title">
            <span class="page-title-icon"><i class="fas fa-user-graduate"></i></span>
            Student Dashboard
        </h1>

        <?php if ($unreadCount > 0): ?>
        <div class="alert alert-info">
            <i class="fas fa-bell alert-icon"></i>
            You have <strong><?= $unreadCount ?></strong> unread notification<?= $unreadCount > 1 ? 's' : '' ?>.
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card stat-card hover-lift">
                <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $attPct !== null ? $attPct . '%' : 'N/A' ?></div>
                    <div class="stat-label">Attendance This Month</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon orange"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $pendingCount ?></div>
                    <div class="stat-label">Pending Assignments</div>
                </div>
            </div>
            <div class="card stat-card hover-lift">
                <div class="stat-icon blue"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $gpa !== null ? $gpa : 'N/A' ?></div>
                    <div class="stat-label">Overall GPA</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Assignments -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Upcoming Deadlines</span>
                <a href="/school_tracking/student/assignments.php" class="btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View All
                </a>
            </div>
            <?php if (empty($upcoming)): ?>
                <p class="text-muted text-center mt-3 mb-3">No pending assignments.</p>
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

        <!-- Recent Grades -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-star" style="color:var(--primary);"></i> Recent Grades</span>
                <a href="/school_tracking/student/view_grades.php" class="btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View All
                </a>
            </div>
            <?php if (empty($recentGrades)): ?>
                <p class="text-muted text-center mt-3 mb-3">No grades recorded yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>Subject</th><th>Exam Type</th><th>Grade</th><th>Date</th></tr>
                    </thead>
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
                            <td><?= date('M j, Y', strtotime($g['graded_at'])) ?></td>
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
