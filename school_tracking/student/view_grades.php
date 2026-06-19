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


$stmtGpa = $pdo->prepare('SELECT AVG(grade_value) FROM grades WHERE student_id = ?');
$stmtGpa->execute([$studentId]);
$gpa = $stmtGpa->fetchColumn();
$gpa = ($gpa !== null && $gpa !== false) ? round((float)$gpa, 2) : null;


$stmtGr = $pdo->prepare(
    'SELECT subject, exam_type, grade_value, remarks, graded_at
     FROM grades
     WHERE student_id = ?
     ORDER BY subject ASC, graded_at DESC'
);
$stmtGr->execute([$studentId]);
$allGrades = $stmtGr->fetchAll();


$bySubject = [];
foreach ($allGrades as $g) {
    $bySubject[$g['subject']][] = $g;
}


function gradeLabel(float $v): array {
    if ($v >= 90) return ['A',  'badge-success', '#28a745'];
    if ($v >= 70) return ['B',  'badge-info',    '#17a2b8'];
    if ($v >= 50) return ['C',  'badge-warning',  '#fd7e14'];
    return              ['F',  'badge-danger',   '#dc3545'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Student Menu</span>
            <a href="/school_tracking/student/index.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/student/view_attendance.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-clipboard-check"></i></span> My Attendance
            </a>
            <a href="/school_tracking/student/view_grades.php" class="sidebar-link active">
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

        <h1 class="page-title"><span class="page-title-icon"><i class="fas fa-star"></i></span> My Grades</h1>

        <!-- GPA Banner -->
        <div class="card mb-4" style="padding:1.25rem 1.5rem;">
            <?php if ($gpa !== null):
                [$lbl, $badgeCls] = gradeLabel($gpa);
            ?>
            <div style="display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;">
                <div>
                    <div style="font-size:0.82rem;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;">
                        Overall GPA
                    </div>
                    <div style="font-size:2.2rem;font-weight:700;line-height:1.1;">
                        <?= $gpa ?>
                        <span class="badge <?= $badgeCls ?>" style="font-size:1rem;vertical-align:middle;">
                            <?= $lbl ?>
                        </span>
                    </div>
                </div>
                <div style="flex:1;min-width:180px;">
                    <div style="background:#e9ecef;border-radius:999px;height:12px;overflow:hidden;">
                        <div style="width:<?= min($gpa, 100) ?>%;height:100%;
                             background:<?= gradeLabel($gpa)[2] ?>;border-radius:999px;"></div>
                    </div>
                    <div style="font-size:0.75rem;color:#6c757d;margin-top:0.3rem;">
                        Based on <?= count($allGrades) ?> grade record<?= count($allGrades) !== 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <p class="text-muted" style="margin:0;">No grades recorded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Grade Legend -->
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;font-size:0.82rem;">
            <span class="badge badge-success">A &nbsp;90–100</span>
            <span class="badge badge-info">B &nbsp;70–89</span>
            <span class="badge badge-warning">C &nbsp;50–69</span>
            <span class="badge badge-danger">F &nbsp;Below 50</span>
        </div>

        <?php if (empty($bySubject)): ?>
            <div class="card">
                <p class="text-muted text-center mt-3 mb-3">No grades recorded yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($bySubject as $subject => $entries):
                $subAvg = round(array_sum(array_column($entries, 'grade_value')) / count($entries), 2);
                [$subLbl, $subBadge] = gradeLabel($subAvg);
            ?>
            <div class="card mb-4">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span><i class="fas fa-book"></i> <?= htmlspecialchars($subject) ?></span>
                    <span>
                        Avg: <strong><?= $subAvg ?></strong>
                        <span class="badge <?= $subBadge ?>" style="margin-left:0.4rem;"><?= $subLbl ?></span>
                    </span>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Exam Type</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $j => $g):
                                $v = (float) $g['grade_value'];
                                [$lbl, $cls] = gradeLabel($v);
                            ?>
                            <tr>
                                <td><?= $j + 1 ?></td>
                                <td><?= htmlspecialchars($g['exam_type'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $cls ?>"><?= $v ?></span>
                                    <span style="font-size:0.78rem;color:#6c757d;margin-left:0.3rem;"><?= $lbl ?></span>
                                </td>
                                <td><?= htmlspecialchars($g['remarks'] ?? '—') ?></td>
                                <td><?= date('M j, Y', strtotime($g['graded_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
