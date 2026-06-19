<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('parent');

$pdo = require __DIR__ . '/../config/db.php';


$stmtChildren = $pdo->prepare(
    'SELECT p.student_id, s.class_id,
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
    die('<p style="padding:2rem;font-family:sans-serif;">No linked student found.</p>');
}

$selectedStudentId = isset($_GET['student_id']) && is_numeric($_GET['student_id'])
                   ? (int)$_GET['student_id'] : 0;

$activeChild = null;
foreach ($children as $ch) {
    if ((int)$ch['student_id'] === $selectedStudentId) { $activeChild = $ch; break; }
}
if (!$activeChild) $activeChild = $children[0];

$studentId = (int) $activeChild['student_id'];
$classId   = (int) $activeChild['class_id'];


$filterMonth = '';
if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $filterMonth = $_GET['month'];
}


$attWhere  = 'WHERE a.student_id = ?';
$attParams = [$studentId];
if ($filterMonth !== '') {
    $attWhere  .= ' AND DATE_FORMAT(a.date, "%Y-%m") = ?';
    $attParams[] = $filterMonth;
}
$stmtAttSum = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status='present') AS present_count,
            SUM(status='absent')  AS absent_count,
            SUM(status='late')    AS late_count
     FROM attendance a {$attWhere}"
);
$stmtAttSum->execute($attParams);
$attSum = $stmtAttSum->fetch();

$attTotal   = (int) $attSum['total'];
$attPresent = (int) $attSum['present_count'];
$attAbsent  = (int) $attSum['absent_count'];
$attLate    = (int) $attSum['late_count'];
$attPct     = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 0;

$stmtAttRec = $pdo->prepare(
    "SELECT a.date, a.status, c.class_name
     FROM attendance a
     JOIN classes c ON c.class_id = a.class_id
     {$attWhere}
     ORDER BY a.date DESC, a.attendance_id DESC"
);
$stmtAttRec->execute($attParams);
$attRecords = $stmtAttRec->fetchAll();


$stmtGr = $pdo->prepare(
    'SELECT subject, exam_type, grade_value, remarks, graded_at
     FROM grades WHERE student_id = ? ORDER BY subject ASC, graded_at DESC'
);
$stmtGr->execute([$studentId]);
$allGrades = $stmtGr->fetchAll();

$bySubject = [];
foreach ($allGrades as $g) $bySubject[$g['subject']][] = $g;

$gpa = count($allGrades) > 0
     ? round(array_sum(array_column($allGrades, 'grade_value')) / count($allGrades), 2)
     : null;


$stmtAssign = $pdo->prepare(
    'SELECT a.assignment_id, a.title, a.deadline, u.full_name AS teacher_name,
            sub.submission_id, sub.submitted_at, sub.status AS sub_status
     FROM assignments a
     JOIN teachers t ON t.teacher_id = a.teacher_id
     JOIN users    u ON u.user_id    = t.user_id
     LEFT JOIN assignment_submissions sub
           ON sub.assignment_id = a.assignment_id AND sub.student_id = ?
     WHERE a.class_id IN (
         SELECT class_id FROM student_classes WHERE student_id = ?
         UNION ALL
         SELECT class_id FROM students WHERE student_id = ? AND class_id IS NOT NULL
     )
     ORDER BY a.deadline ASC'
);
$stmtAssign->execute([$studentId, $studentId, $studentId]);
$allAssignments = $stmtAssign->fetchAll();

$pending   = array_filter($allAssignments, fn($a) =>
    $a['submission_id'] === null &&
    ($a['deadline'] === null || strtotime($a['deadline']) > time())
);
$submitted = array_filter($allAssignments, fn($a) => $a['submission_id'] !== null);


$stmtN = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtN->execute([$_SESSION['user_id']]);
$unreadCount = (int) $stmtN->fetchColumn();

function gradeLabel(float $v): array {
    if ($v >= 90) return ['A', 'badge-success'];
    if ($v >= 70) return ['B', 'badge-info'];
    if ($v >= 50) return ['C', 'badge-warning'];
    return              ['F', 'badge-danger'];
}

function childUrl(int $sid, string $page = 'index'): string {
    return "/school_tracking/parent/{$page}.php?student_id={$sid}";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Parent Menu</span>
            <a href="<?= childUrl($studentId) ?>" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="<?= childUrl($studentId, 'view_child') ?>" class="sidebar-link active">
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

        <h1 class="page-title"><span class="page-title-icon"><i class="fas fa-user-graduate"></i></span> Child Details</h1>

        <!-- Child selector -->
        <?php if (count($children) > 1): ?>
        <div class="card mb-4" style="padding:1rem 1.5rem;">
            <form method="GET" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <label style="font-weight:600;margin:0;">Viewing child:</label>
                <select name="student_id" onchange="this.form.submit()"
                        style="padding:0.45rem 0.75rem;border:1px solid #cdd5e0;border-radius:6px;min-width:200px;">
                    <?php foreach ($children as $ch): ?>
                        <option value="<?= $ch['student_id'] ?>" <?= $ch['student_id'] == $studentId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ch['child_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- Child header -->
        <div class="card mb-4" style="padding:1.25rem 1.5rem;background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;">
            <div style="font-size:1.4rem;font-weight:700;">
                <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($activeChild['child_name']) ?>
            </div>
            <div style="font-size:0.88rem;opacity:.85;margin-top:0.25rem;">
                Class: <?= htmlspecialchars($activeChild['class_name'] ?? 'Not assigned') ?>
                <?php if ($activeChild['academic_year']): ?>
                    &nbsp;|&nbsp; Year: <?= htmlspecialchars($activeChild['academic_year']) ?>
                <?php endif; ?>
                &nbsp;|&nbsp; GPA: <?= $gpa !== null ? $gpa : 'N/A' ?>
            </div>
        </div>

        <!-- ============ ATTENDANCE ============ -->
        <div id="attendance" class="card mb-4">
            <div class="card-header"><i class="fas fa-clipboard-check"></i> Attendance History</div>
            <div style="padding:1rem 1.5rem;border-bottom:1px solid #e9ecef;">
                <form method="GET" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <label style="font-weight:600;margin:0;">Month:</label>
                    <input type="month" name="month"
                           value="<?= htmlspecialchars($filterMonth) ?>"
                           max="<?= date('Y-m') ?>"
                           style="padding:0.4rem 0.75rem;border:1px solid #cdd5e0;border-radius:6px;">
                    <button type="submit" class="btn-primary" style="padding:0.4rem 1rem;">Filter</button>
                    <?php if ($filterMonth): ?>
                        <a href="view_child.php?student_id=<?= $studentId ?>"
                           class="btn-secondary" style="padding:0.4rem 1rem;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Attendance stats bar -->
            <div style="padding:1rem 1.5rem;display:flex;gap:1.5rem;flex-wrap:wrap;border-bottom:1px solid #e9ecef;">
                <div style="text-align:center;">
                    <div style="font-size:1.4rem;font-weight:700;color:#28a745;"><?= $attPresent ?></div>
                    <div style="font-size:0.75rem;color:#6c757d;">Present</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:1.4rem;font-weight:700;color:#dc3545;"><?= $attAbsent ?></div>
                    <div style="font-size:0.75rem;color:#6c757d;">Absent</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:1.4rem;font-weight:700;color:#fd7e14;"><?= $attLate ?></div>
                    <div style="font-size:0.75rem;color:#6c757d;">Late</div>
                </div>
                <div style="flex:1;min-width:200px;align-self:center;">
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:0.3rem;">
                        <span>Attendance Rate</span>
                        <?php $attBarColor = $attPct >= 75 ? '#10b981' : ($attPct >= 50 ? '#f59e0b' : '#ef4444'); ?>
                        <strong style="color:<?= $attPct >= 75 ? '#065f46' : '#991b1b' ?>;">
                            <?= $attPct ?>%
                        </strong>
                    </div>
                    <div style="background:#e9ecef;border-radius:999px;height:10px;overflow:hidden;">
                        <div style="width:<?= $attPct ?>%;height:100%;background:<?= $attBarColor ?>;border-radius:999px;"></div>
                    </div>
                </div>
            </div>

            <?php if (empty($attRecords)): ?>
                <p class="text-muted text-center mt-3 mb-3">No records found.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>#</th><th>Date</th><th>Class</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($attRecords as $i => $r):
                            $b = match($r['status']) {
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
                            <td><span class="badge <?= $b ?>"><?= ucfirst($r['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============ GRADES ============ -->
        <div id="grades" class="card mb-4">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-star"></i> Grades History</span>
                <span style="font-size:0.82rem;font-weight:400;">
                    GPA: <strong><?= $gpa !== null ? $gpa : 'N/A' ?></strong>
                </span>
            </div>

            <?php if (empty($bySubject)): ?>
                <p class="text-muted text-center mt-3 mb-3">No grades recorded yet.</p>
            <?php else: ?>
                <?php foreach ($bySubject as $subject => $entries):
                    $subAvg = round(array_sum(array_column($entries, 'grade_value')) / count($entries), 2);
                    [$subLbl, $subBadge] = gradeLabel($subAvg);
                ?>
                <div style="border-bottom:1px solid #e9ecef;">
                    <div style="padding:0.75rem 1.5rem;background:#f8f9fa;font-weight:600;
                                display:flex;justify-content:space-between;align-items:center;">
                        <span><i class="fas fa-book"></i> <?= htmlspecialchars($subject) ?></span>
                        <span>Avg: <?= $subAvg ?>
                            <span class="badge <?= $subBadge ?>" style="margin-left:0.3rem;"><?= $subLbl ?></span>
                        </span>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr><th>#</th><th>Exam Type</th><th>Grade</th><th>Remarks</th><th>Date</th></tr>
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
                                        <span style="font-size:0.78rem;color:#6c757d;margin-left:0.25rem;"><?= $lbl ?></span>
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

        <!-- ============ ASSIGNMENTS ============ -->
        <div id="assignments" class="card">
            <div class="card-header"><i class="fas fa-file-alt"></i> Assignments</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">

                <!-- Pending -->
                <div style="border-right:1px solid #e9ecef;">
                    <div style="padding:0.65rem 1.5rem;background:#fff8e1;font-weight:600;font-size:0.85rem;
                                border-bottom:1px solid #e9ecef;">
                        <i class="fas fa-hourglass-half"></i> Pending (<?= count($pending) ?>)
                    </div>
                    <?php if (empty($pending)): ?>
                        <p class="text-muted text-center mt-3 mb-3" style="font-size:0.85rem;">None pending.</p>
                    <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table" style="font-size:0.85rem;">
                            <thead><tr><th>Title</th><th>Deadline</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending as $a):
                                    $diff = $a['deadline'] ? strtotime($a['deadline']) - time() : null;
                                    $days = $diff !== null ? (int)floor($diff / 86400) : null;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['title']) ?></td>
                                    <td>
                                        <?php if ($a['deadline']): ?>
                                            <span class="badge <?= $days !== null && $days < 2 ? 'badge-danger' : 'badge-warning' ?>">
                                                <?= date('M j', strtotime($a['deadline'])) ?>
                                            </span>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Submitted -->
                <div>
                    <div style="padding:0.65rem 1.5rem;background:#e8f5e9;font-weight:600;font-size:0.85rem;
                                border-bottom:1px solid #e9ecef;">
                        <i class="fas fa-check"></i> Submitted (<?= count($submitted) ?>)
                    </div>
                    <?php if (empty($submitted)): ?>
                        <p class="text-muted text-center mt-3 mb-3" style="font-size:0.85rem;">None submitted.</p>
                    <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table" style="font-size:0.85rem;">
                            <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($submitted as $a):
                                    $b = match($a['sub_status']) {
                                        'graded'    => 'badge-success',
                                        'late'      => 'badge-warning',
                                        default     => 'badge-info',
                                    };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['title']) ?></td>
                                    <td><span class="badge <?= $b ?>"><?= ucfirst($a['sub_status']) ?></span></td>
                                    <td><?= date('M j', strtotime($a['submitted_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
