<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('admin');

$pdo = require __DIR__ . '/../config/db.php';

$reportType = $_GET['report']   ?? '';
$classId    = isset($_GET['class_id'])  && is_numeric($_GET['class_id'])  ? (int)$_GET['class_id']  : '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to']   ?? '';

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = '';

$classes = $pdo->query(
    'SELECT class_id, class_name, academic_year FROM classes ORDER BY academic_year DESC, class_name'
)->fetchAll();

$rows        = [];
$reportTitle = '';
$reportError = '';

if ($reportType === 'attendance') {
    $reportTitle = 'Attendance Report';

    $sql    = 'SELECT u.full_name  AS student_name,
                      c.class_name,
                      c.academic_year,
                      a.date,
                      a.status
               FROM attendance a
               JOIN students  s ON s.student_id = a.student_id
               JOIN users     u ON u.user_id     = s.user_id
               JOIN classes   c ON c.class_id    = a.class_id
               WHERE 1=1';
    $params = [];

    if ($classId !== '') {
        $sql    .= ' AND a.class_id = ?';
        $params[] = $classId;
    }
    if ($dateFrom !== '') {
        $sql    .= ' AND a.date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql    .= ' AND a.date <= ?';
        $params[] = $dateTo;
    }

    $sql .= ' ORDER BY a.date DESC, u.full_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($reportType === 'grades') {
    $reportTitle = 'Grades Report';

    $sql    = 'SELECT u.full_name   AS student_name,
                      c.class_name,
                      c.academic_year,
                      g.subject,
                      g.grade_value,
                      g.exam_type,
                      g.remarks,
                      tu.full_name  AS teacher_name,
                      g.graded_at
               FROM grades g
               JOIN students s  ON s.student_id = g.student_id
               JOIN users    u  ON u.user_id     = s.user_id
               JOIN classes  c  ON c.class_id    = g.class_id
               JOIN teachers t  ON t.teacher_id  = g.teacher_id
               JOIN users    tu ON tu.user_id     = t.user_id
               WHERE 1=1';
    $params = [];

    if ($classId !== '') {
        $sql    .= ' AND g.class_id = ?';
        $params[] = $classId;
    }

    $sql .= ' ORDER BY u.full_name ASC, g.graded_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($reportType === 'submissions') {
    $reportTitle = 'Assignment Submission Report';

    $sql    = 'SELECT a.assignment_id,
                      a.title,
                      c.class_name,
                      c.academic_year,
                      tu.full_name       AS teacher_name,
                      a.deadline,
                      COUNT(DISTINCT s.student_id)          AS total_students,
                      COUNT(DISTINCT sub.submission_id)      AS total_submitted,
                      SUM(sub.status = \'submitted\')        AS count_submitted,
                      SUM(sub.status = \'graded\')           AS count_graded,
                      SUM(sub.status = \'late\')             AS count_late
               FROM assignments a
               JOIN classes   c   ON c.class_id   = a.class_id
               JOIN teachers  t   ON t.teacher_id = a.teacher_id
               JOIN users     tu  ON tu.user_id    = t.user_id
               LEFT JOIN student_classes sc
                   ON sc.class_id = a.class_id
               LEFT JOIN students s
                   ON s.student_id = sc.student_id
               LEFT JOIN assignment_submissions sub
                   ON sub.assignment_id = a.assignment_id
               WHERE 1=1';
    $params = [];

    if ($classId !== '') {
        $sql    .= ' AND a.class_id = ?';
        $params[] = $classId;
    }

    $sql .= ' GROUP BY a.assignment_id ORDER BY a.deadline DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
    .navbar, .sidebar, .filter-section, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .layout       { display: block !important; }
    .table tbody tr:nth-child(even) { background-color: #f5f5f5 !important; -webkit-print-color-adjust: exact; }
    .report-header { display: block !important; }
}
.report-header { display: none; }
</style>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar no-print">
        <div class="sidebar-section">
            <span class="sidebar-label">Main Menu</span>
            <a href="/school_tracking/admin/index.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/admin/manage_users.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="/school_tracking/admin/manage_classes.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-school"></i></span> Manage Classes
            </a>
            <a href="/school_tracking/admin/reports.php" class="sidebar-link active">
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
            <span class="page-title-icon"><i class="fas fa-chart-bar"></i></span>
            Reports &amp; Analytics
        </h1>

        <!-- FILTER FORM -->
        <div class="card mb-4 filter-section no-print">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-filter" style="color:var(--primary);"></i> Report Filters</span>
            </div>

            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="report">Report Type <span class="text-danger">*</span></label>
                        <select id="report" name="report" required onchange="toggleFilters(this.value)">
                            <option value="">— Select Report —</option>
                            <option value="attendance"
                                <?= $reportType === 'attendance'  ? 'selected' : '' ?>>
                                Attendance Report
                            </option>
                            <option value="grades"
                                <?= $reportType === 'grades'      ? 'selected' : '' ?>>
                                Grades Report
                            </option>
                            <option value="submissions"
                                <?= $reportType === 'submissions' ? 'selected' : '' ?>>
                                Assignment Submission Report
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="class_id">Filter by Class</label>
                        <select id="class_id" name="class_id">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?= $cls['class_id'] ?>"
                                    <?= ($classId === $cls['class_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                    (<?= htmlspecialchars($cls['academic_year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Date range — attendance only -->
                <div class="form-row" id="date_filters"
                     style="<?= $reportType !== 'attendance' ? 'display:none' : '' ?>">
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from"
                               value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to"
                               value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Generate Report</button>
                    <a href="reports.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>

        <?php if ($reportType !== '' && empty($reportError)): ?>

        <!-- Print-only header -->
        <div class="report-header mb-3">
            <h2 style="color:#1a2b4a; margin-bottom:0.2rem;">School Tracking System</h2>
            <p style="color:#6c757d; font-size:0.85rem;">
                <?= htmlspecialchars($reportTitle) ?> &mdash; Generated: <?= date('F j, Y g:i A') ?>
                <?php if ($classId !== ''): ?>
                    <?php $sel = array_filter($classes, fn($c) => $c['class_id'] == $classId); ?>
                    <?php if ($sel): $sc = reset($sel); ?>
                        &mdash; Class: <?= htmlspecialchars($sc['class_name']) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <hr>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <span>
                    <?= htmlspecialchars($reportTitle) ?>
                    <span class="text-muted" style="font-weight:400; font-size:0.82rem; margin-left:0.5rem;">
                        (<?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>)
                    </span>
                </span>
                <?php if (!empty($rows)): ?>
                <button class="btn-primary btn-sm no-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($rows)): ?>
                <p class="text-muted text-center mt-3 mb-3">
                    No records found for the selected filters.
                </p>

            <?php elseif ($reportType === 'attendance'): ?>

            <?php
                $totals = ['present' => 0, 'absent' => 0, 'late' => 0];
                foreach ($rows as $r) $totals[$r['status']]++;
            ?>

            <div class="d-flex gap-2 mb-3 no-print" style="padding:0 0 0 0.25rem; flex-wrap:wrap;">
                <span class="badge badge-present">Present: <?= $totals['present'] ?></span>
                <span class="badge badge-absent">Absent: <?= $totals['absent'] ?></span>
                <span class="badge badge-late">Late: <?= $totals['late'] ?></span>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Academic Year</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Student"><?= htmlspecialchars($r['student_name']) ?></td>
                            <td data-label="Class"><?= htmlspecialchars($r['class_name']) ?></td>
                            <td data-label="Academic Year"><?= htmlspecialchars($r['academic_year']) ?></td>
                            <td data-label="Date"><?= date('M j, Y', strtotime($r['date'])) ?></td>
                            <td data-label="Status">
                                <span class="badge badge-<?= $r['status'] ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Total Records:</strong></td>
                            <td><strong><?= count($rows) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php elseif ($reportType === 'grades'): ?>

            <?php
                $gradeSum   = array_sum(array_column($rows, 'grade_value'));
                $gradeAvg   = count($rows) ? round($gradeSum / count($rows), 2) : 0;
                $gradeMax   = count($rows) ? max(array_column($rows, 'grade_value')) : 0;
                $gradeMin   = count($rows) ? min(array_column($rows, 'grade_value')) : 0;
            ?>

            <div class="d-flex gap-2 mb-3 no-print" style="padding:0 0 0 0.25rem; flex-wrap:wrap;">
                <span class="badge badge-info">Average: <?= $gradeAvg ?></span>
                <span class="badge badge-present">Highest: <?= $gradeMax ?></span>
                <span class="badge badge-absent">Lowest: <?= $gradeMin ?></span>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                            <th>Remarks</th>
                            <th>Graded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                        <?php
                            $gv    = (float) $r['grade_value'];
                            $gClass = $gv >= 75 ? 'badge-present' : 'badge-absent';
                        ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Student"><?= htmlspecialchars($r['student_name']) ?></td>
                            <td data-label="Class"><?= htmlspecialchars($r['class_name']) ?></td>
                            <td data-label="Subject"><?= htmlspecialchars($r['subject']) ?></td>
                            <td data-label="Exam Type">
                                <?= htmlspecialchars($r['exam_type'] ?? '—') ?>
                            </td>
                            <td data-label="Grade">
                                <span class="badge <?= $gClass ?>">
                                    <?= number_format($gv, 2) ?>
                                </span>
                            </td>
                            <td data-label="Teacher"><?= htmlspecialchars($r['teacher_name']) ?></td>
                            <td data-label="Remarks">
                                <?= htmlspecialchars($r['remarks'] ?? '—') ?>
                            </td>
                            <td data-label="Graded At">
                                <?= date('M j, Y', strtotime($r['graded_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Class Average:</strong></td>
                            <td><strong><?= $gradeAvg ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php elseif ($reportType === 'submissions'): ?>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Assignment Title</th>
                            <th>Class</th>
                            <th>Teacher</th>
                            <th>Deadline</th>
                            <th>Enrolled</th>
                            <th>Submitted</th>
                            <th>Graded</th>
                            <th>Late</th>
                            <th>Not Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                        <?php
                            $notSubmitted = max(0, (int)$r['total_students'] - (int)$r['total_submitted']);
                            $pct = $r['total_students'] > 0
                                 ? round(($r['total_submitted'] / $r['total_students']) * 100)
                                 : 0;
                            $overdue = $r['deadline'] && strtotime($r['deadline']) < time();
                        ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Title">
                                <strong><?= htmlspecialchars($r['title']) ?></strong>
                            </td>
                            <td data-label="Class"><?= htmlspecialchars($r['class_name']) ?></td>
                            <td data-label="Teacher"><?= htmlspecialchars($r['teacher_name']) ?></td>
                            <td data-label="Deadline">
                                <?php if ($r['deadline']): ?>
                                    <span class="<?= $overdue ? 'text-danger' : '' ?>">
                                        <?= date('M j, Y', strtotime($r['deadline'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Enrolled" class="text-center">
                                <?= $r['total_students'] ?>
                            </td>
                            <td data-label="Submitted" class="text-center">
                                <span class="badge badge-present">
                                    <?= $r['count_submitted'] ?>
                                </span>
                            </td>
                            <td data-label="Graded" class="text-center">
                                <span class="badge badge-info">
                                    <?= $r['count_graded'] ?>
                                </span>
                            </td>
                            <td data-label="Late" class="text-center">
                                <span class="badge badge-late">
                                    <?= $r['count_late'] ?>
                                </span>
                            </td>
                            <td data-label="Not Submitted" class="text-center">
                                <span class="badge <?= $notSubmitted > 0 ? 'badge-absent' : 'badge-present' ?>">
                                    <?= $notSubmitted ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Total Assignments:</strong></td>
                            <td colspan="5"><strong><?= count($rows) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>

        </div><!-- /.card -->

        <?php elseif ($reportType !== ''): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($reportError) ?>
            </div>
        <?php endif; ?>

    </div><!-- /.main-content -->

</div><!-- /.layout -->

<script>
function toggleFilters(type) {
    document.getElementById('date_filters').style.display =
        (type === 'attendance') ? 'grid' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
