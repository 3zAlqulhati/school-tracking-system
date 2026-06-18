<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('teacher');

$pdo = require __DIR__ . '/../config/db.php';




$stmt = $pdo->prepare('SELECT teacher_id FROM teachers WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch();

if (!$teacher) {

    die('<p style="padding:2rem;font-family:sans-serif;">Teacher profile not found. Please contact the administrator.</p>');
}
$teacherId = (int) $teacher['teacher_id'];
$today     = date('Y-m-d');






$myClassCount = (int) $pdo->prepare(
    'SELECT COUNT(DISTINCT class_id) FROM (
         SELECT class_id FROM assignments WHERE teacher_id = ?
         UNION
         SELECT class_id FROM attendance  WHERE teacher_id = ?
     ) AS combined'
)->execute([$teacherId, $teacherId]) ? $pdo->query(
    'SELECT COUNT(DISTINCT class_id) FROM (
         SELECT class_id FROM assignments WHERE teacher_id = ' . $teacherId . '
         UNION
         SELECT class_id FROM attendance  WHERE teacher_id = ' . $teacherId . '
     ) AS combined'
)->fetchColumn() : 0;

$stmtClasses = $pdo->prepare(
    'SELECT COUNT(DISTINCT class_id) FROM (
         SELECT class_id FROM assignments    WHERE teacher_id = ?
         UNION
         SELECT class_id FROM attendance     WHERE teacher_id = ?
         UNION
         SELECT class_id FROM class_teachers WHERE teacher_id = ?
     ) AS combined'
);
$stmtClasses->execute([$teacherId, $teacherId, $teacherId]);
$myClassCount = (int) $stmtClasses->fetchColumn();

$stmtAssign = $pdo->prepare(
    'SELECT COUNT(*) FROM assignments WHERE teacher_id = ?'
);
$stmtAssign->execute([$teacherId]);
$myAssignmentCount = (int) $stmtAssign->fetchColumn();


$stmtPending = $pdo->prepare(
    'SELECT COUNT(*)
     FROM assignment_submissions sub
     JOIN assignments a ON a.assignment_id = sub.assignment_id
     WHERE a.teacher_id = ? AND sub.status = \'submitted\''
);
$stmtPending->execute([$teacherId]);
$pendingCount = (int) $stmtPending->fetchColumn();






$stmtToday = $pdo->prepare(
    'SELECT DISTINCT c.class_id, c.class_name, c.academic_year, c.schedule,
            COUNT(DISTINCT s.student_id) AS enrolled
     FROM classes c
     LEFT JOIN students s ON s.class_id = c.class_id
     WHERE c.class_id IN (
         SELECT class_id FROM assignments    WHERE teacher_id = ?
         UNION
         SELECT class_id FROM attendance     WHERE teacher_id = ?
         UNION
         SELECT class_id FROM class_teachers WHERE teacher_id = ?
     )
     GROUP BY c.class_id
     ORDER BY c.class_name'
);
$stmtToday->execute([$teacherId, $teacherId, $teacherId]);
$myClasses = $stmtToday->fetchAll();


$stmtAttTaken = $pdo->prepare(
    'SELECT DISTINCT class_id FROM attendance WHERE teacher_id = ? AND date = ?'
);
$stmtAttTaken->execute([$teacherId, $today]);
$attendanceTakenToday = array_column($stmtAttTaken->fetchAll(), 'class_id');




$stmtRecent = $pdo->prepare(
    'SELECT a.assignment_id, a.title, a.deadline, a.created_at,
            c.class_name,
            COUNT(sub.submission_id) AS submission_count
     FROM assignments a
     JOIN classes c ON c.class_id = a.class_id
     LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.assignment_id
     WHERE a.teacher_id = ?
     GROUP BY a.assignment_id
     ORDER BY a.created_at DESC
     LIMIT 5'
);
$stmtRecent->execute([$teacherId]);
$recentAssignments = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Teacher Menu</span>
            <a href="/school_tracking/teacher/index.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/teacher/attendance.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-clipboard-check"></i></span> Record Attendance
            </a>
            <a href="/school_tracking/teacher/grades.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-star"></i></span> Manage Grades
            </a>
            <a href="/school_tracking/teacher/assignments.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> Assignments
            </a>
            <a href="/school_tracking/teacher/notifications.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-bell"></i></span> Send Notifications
            </a>
            <a href="/school_tracking/teacher/inbox.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-inbox"></i></span> My Inbox
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
            <span class="page-title-icon"><i class="fas fa-chalkboard-teacher"></i></span>
            Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>
            <span class="text-muted" style="font-size:.82rem;font-weight:400;margin-left:auto;">
                <?= date('l, F j, Y') ?>
            </span>
        </h1>

        <!-- ================================================
             STAT CARDS
        ================================================= -->
        <div class="dashboard-grid">
            <div class="card stat-card">
                <div class="stat-icon navy"><i class="fas fa-school"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $myClassCount ?></div>
                    <div class="stat-label">My Classes</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $myAssignmentCount ?></div>
                    <div class="stat-label">My Assignments</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $pendingCount ?></div>
                    <div class="stat-label">Pending Submissions</div>
                </div>
            </div>
        </div>

        <!-- ================================================
             MY CLASSES / TODAY'S OVERVIEW
        ================================================= -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-school" style="color:var(--primary);"></i> My Classes</span>
                <a href="/school_tracking/teacher/attendance.php" class="btn-primary btn-sm">
                    <i class="fas fa-clipboard-check"></i> Record Attendance
                </a>
            </div>

            <?php if (empty($myClasses)): ?>
                <p class="text-muted text-center mt-3 mb-3">
                    No classes yet. Classes appear here after you record attendance or create an assignment.
                </p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Academic Year</th>
                            <th>Schedule</th>
                            <th>Enrolled</th>
                            <th>Attendance Today</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myClasses as $cls): ?>
                        <tr>
                            <td data-label="Class">
                                <strong><?= htmlspecialchars($cls['class_name']) ?></strong>
                            </td>
                            <td data-label="Academic Year">
                                <?= htmlspecialchars($cls['academic_year']) ?>
                            </td>
                            <td data-label="Schedule">
                                <?= $cls['schedule']
                                    ? nl2br(htmlspecialchars($cls['schedule']))
                                    : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td data-label="Enrolled" class="text-center">
                                <?= $cls['enrolled'] ?>
                            </td>
                            <td data-label="Attendance Today" class="text-center">
                                <?php if (in_array($cls['class_id'], $attendanceTakenToday)): ?>
                                    <span class="badge badge-present"><i class="fas fa-check"></i> Done</span>
                                <?php else: ?>
                                    <a href="/school_tracking/teacher/attendance.php?class_id=<?= $cls['class_id'] ?>"
                                       class="badge badge-warning" style="text-decoration:none;">
                                        <i class="fas fa-edit"></i> Pending
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================================================
             RECENT ASSIGNMENTS
        ================================================= -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-file-alt" style="color:var(--primary);"></i> Recent Assignments</span>
                <a href="/school_tracking/teacher/assignments.php" class="btn-primary btn-sm">
                    <i class="fas fa-plus"></i> New Assignment
                </a>
            </div>

            <?php if (empty($recentAssignments)): ?>
                <p class="text-muted text-center mt-3 mb-3">No assignments yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Deadline</th>
                            <th>Submissions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAssignments as $a): ?>
                        <?php
                            $hasDeadline = !empty($a['deadline']);
                            $isOverdue   = $hasDeadline && strtotime($a['deadline']) < time();
                        ?>
                        <tr>
                            <td data-label="Title">
                                <strong><?= htmlspecialchars($a['title']) ?></strong>
                            </td>
                            <td data-label="Class">
                                <?= htmlspecialchars($a['class_name']) ?>
                            </td>
                            <td data-label="Deadline">
                                <?php if ($hasDeadline): ?>
                                    <span class="<?= $isOverdue ? 'text-danger' : '' ?>">
                                        <?= date('M j, Y', strtotime($a['deadline'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">No deadline</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Submissions" class="text-center">
                                <span class="badge badge-info">
                                    <?= $a['submission_count'] ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-absent">Closed</span>
                                <?php elseif ($hasDeadline): ?>
                                    <span class="badge badge-present">Open</span>
                                <?php else: ?>
                                    <span class="badge badge-info">No Deadline</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /.main-content -->

</div><!-- /.layout -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

