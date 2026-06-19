<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('teacher');

$pdo = require __DIR__ . '/../config/db.php';


$stmt = $pdo->prepare('SELECT teacher_id FROM teachers WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('<p style="padding:2rem;font-family:sans-serif;">Teacher profile not found.</p>');
}
$teacherId = (int) $teacher['teacher_id'];

$success  = '';
$error    = '';
$summary  = [];

if (isset($_GET['saved'])) $success = 'Attendance saved successfully.';


$selectedClass = isset($_GET['class_id']) && is_numeric($_GET['class_id'])
               ? (int) $_GET['class_id'] : 0;
$selectedDate  = $_GET['date'] ?? date('Y-m-d');


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}




$stmtClasses = $pdo->prepare(
    'SELECT DISTINCT c.class_id, c.class_name, c.academic_year
     FROM classes c
     WHERE c.class_id IN (
         SELECT class_id FROM class_teachers WHERE teacher_id = ?
     )
     ORDER BY c.class_name'
);
$stmtClasses->execute([$teacherId]);
$teacherClasses = $stmtClasses->fetchAll();




$students = [];
if ($selectedClass > 0) {
    $stmtStudents = $pdo->prepare(
        'SELECT s.student_id, u.full_name
         FROM students s
         JOIN student_classes sc ON sc.student_id = s.student_id
         JOIN users u ON u.user_id = s.user_id
         WHERE sc.class_id = ?
         ORDER BY u.full_name ASC'
    );
    $stmtStudents->execute([$selectedClass]);
    $students = $stmtStudents->fetchAll();
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $postClassId = (int) ($_POST['class_id'] ?? 0);
    $postDate    = $_POST['date'] ?? '';
    $attendance  = $_POST['attendance'] ?? [];   // [student_id => 'present'|'absent'|'late']

    if ($postClassId === 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
        $error = 'Invalid class or date.';
    } elseif (empty($attendance)) {
        $error = 'No student attendance data received.';
    } else {
        $validStatuses = ['present', 'absent', 'late'];
        try {

            $stmtUpsert = $pdo->prepare(
                'INSERT INTO attendance (student_id, teacher_id, class_id, date, status)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     status     = VALUES(status),
                     teacher_id = VALUES(teacher_id)'
            );

            $summary = ['present' => 0, 'absent' => 0, 'late' => 0];

            foreach ($attendance as $studentId => $status) {
                $studentId = (int) $studentId;
                if (!in_array($status, $validStatuses, true)) continue;

                $stmtUpsert->execute([$studentId, $teacherId, $postClassId, $postDate, $status]);
                $summary[$status]++;
            }

            $selectedClass  = $postClassId;
            $selectedDate   = $postDate;
            header('Location: attendance.php?class_id=' . $postClassId . '&date=' . $postDate . '&saved=1');
            exit;

        } catch (PDOException $e) {
            $error = 'Could not save attendance: ' . $e->getMessage();
        }
    }
}




$existingAttendance = [];
if ($selectedClass > 0) {
    $stmtExisting = $pdo->prepare(
        'SELECT student_id, status FROM attendance
         WHERE class_id = ? AND date = ?'
    );
    $stmtExisting->execute([$selectedClass, $selectedDate]);
    foreach ($stmtExisting->fetchAll() as $row) {
        $existingAttendance[$row['student_id']] = $row['status'];
    }
}




$historyDates   = [];
$historyRecords = [];
if ($selectedClass > 0) {
    $stmtDates = $pdo->prepare(
        'SELECT DISTINCT date FROM attendance
         WHERE class_id = ? AND teacher_id = ?
         ORDER BY date DESC
         LIMIT 10'
    );
    $stmtDates->execute([$selectedClass, $teacherId]);
    $historyDates = array_column($stmtDates->fetchAll(), 'date');

    if (!empty($historyDates)) {
        $placeholders = implode(',', array_fill(0, count($historyDates), '?'));
        $stmtHistory  = $pdo->prepare(
            "SELECT a.date, u.full_name, a.status
             FROM attendance a
             JOIN students s ON s.student_id = a.student_id
             JOIN users    u ON u.user_id    = s.user_id
             WHERE a.class_id = ? AND a.date IN ({$placeholders})
             ORDER BY a.date DESC, u.full_name ASC"
        );
        $stmtHistory->execute(array_merge([$selectedClass], $historyDates));
        foreach ($stmtHistory->fetchAll() as $row) {
            $historyRecords[$row['date']][] = $row;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Teacher Menu</span>
            <a href="/school_tracking/teacher/index.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/teacher/attendance.php" class="sidebar-link active">
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
            <span class="page-title-icon"><i class="fas fa-clipboard-check"></i></span>
            Record Attendance
        </h1>

        <!-- Flash Messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ================================================
             SUMMARY AFTER SAVE
        ================================================= -->
        <?php if (!empty($summary)): ?>
        <div class="dashboard-grid mb-4" style="grid-template-columns: repeat(3,1fr);">
            <div class="card stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $summary['present'] ?></div>
                    <div class="stat-label">Present</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $summary['absent'] ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $summary['late'] ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================================================
             CLASS & DATE SELECTOR
        ================================================= -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-filter"></i> Select Class &amp; Date</div>

            <form method="GET" action="" class="form-row" style="align-items:flex-end;">
                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id" required
                            onchange="this.form.submit()">
                        <option value="">— Select Class —</option>
                        <?php foreach ($teacherClasses as $cls): ?>
                            <option value="<?= $cls['class_id'] ?>"
                                <?= $selectedClass === (int)$cls['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name']) ?>
                                (<?= htmlspecialchars($cls['academic_year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date"
                           value="<?= htmlspecialchars($selectedDate) ?>"
                           max="<?= date('Y-m-d') ?>"
                           onchange="this.form.submit()">
                </div>
                <div class="form-group" style="padding-bottom:0.05rem;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Load</button>
                </div>
            </form>

            <?php if (empty($teacherClasses)): ?>
                <p class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    No classes linked to your account yet. Create an assignment first
                    to link a class, or ask the admin.
                </p>
            <?php endif; ?>
        </div>

        <!-- ================================================
             ATTENDANCE FORM
        ================================================= -->
        <?php if ($selectedClass > 0 && !empty($students)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-between align-center">
                <span>
                    <i class="fas fa-clipboard-list"></i> Attendance for
                    <?php
                        $selCls = array_filter($teacherClasses, fn($c) => (int)$c['class_id'] === $selectedClass);
                        $selCls = reset($selCls);
                        echo $selCls ? htmlspecialchars($selCls['class_name']) : '';
                    ?>
                    &mdash; <?= date('F j, Y', strtotime($selectedDate)) ?>
                </span>
                <div class="d-flex gap-1">
                    <button type="button" class="btn-success btn-sm" onclick="markAll('present')">All Present</button>
                    <button type="button" class="btn-danger  btn-sm" onclick="markAll('absent')">All Absent</button>
                </div>
            </div>

            <form method="POST" action="" id="attendanceForm">
                <input type="hidden" name="save_attendance" value="1">
                <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                <input type="hidden" name="date"     value="<?= htmlspecialchars($selectedDate) ?>">

                <div class="table-wrapper">
                    <table class="table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th class="text-center" style="color:#a8e6b3;"><i class="fas fa-check"></i> Present</th>
                                <th class="text-center" style="color:#f5a9ae;"><i class="fas fa-times"></i> Absent</th>
                                <th class="text-center" style="color:#ffd5a8;"><i class="fas fa-clock"></i> Late</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $stu): ?>
                            <?php $current = $existingAttendance[$stu['student_id']] ?? 'present'; ?>
                            <tr id="row-<?= $stu['student_id'] ?>">
                                <td data-label="#"><?= $i + 1 ?></td>
                                <td data-label="Student">
                                    <strong><?= htmlspecialchars($stu['full_name']) ?></strong>
                                </td>
                                <td data-label="Present" class="text-center">
                                    <label class="radio-label">
                                        <input type="radio"
                                               name="attendance[<?= $stu['student_id'] ?>]"
                                               value="present"
                                               <?= $current === 'present' ? 'checked' : '' ?>
                                               onchange="highlightRow(<?= $stu['student_id'] ?>, 'present')">
                                    </label>
                                </td>
                                <td data-label="Absent" class="text-center">
                                    <label class="radio-label">
                                        <input type="radio"
                                               name="attendance[<?= $stu['student_id'] ?>]"
                                               value="absent"
                                               <?= $current === 'absent' ? 'checked' : '' ?>
                                               onchange="highlightRow(<?= $stu['student_id'] ?>, 'absent')">
                                    </label>
                                </td>
                                <td data-label="Late" class="text-center">
                                    <label class="radio-label">
                                        <input type="radio"
                                               name="attendance[<?= $stu['student_id'] ?>]"
                                               value="late"
                                               <?= $current === 'late' ? 'checked' : '' ?>
                                               onchange="highlightRow(<?= $stu['student_id'] ?>, 'late')">
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="padding-top:1rem;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                    <span class="text-muted" style="font-size:0.8rem; align-self:center;">
                        <?= count($students) ?> student(s)
                    </span>
                </div>
            </form>
        </div>

        <?php elseif ($selectedClass > 0 && empty($students)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                No students are enrolled in this class yet.
            </div>
        <?php endif; ?>

        <!-- ================================================
             ATTENDANCE HISTORY
        ================================================= -->
        <?php if ($selectedClass > 0 && !empty($historyDates)): ?>
        <div class="card mt-4">
            <div class="card-header"><i class="fas fa-history"></i> Recent Attendance History</div>

            <?php foreach ($historyDates as $hDate): ?>
            <?php $dayRows = $historyRecords[$hDate] ?? []; ?>

            <div style="margin-bottom:1.5rem;">
                <div style="padding:0.6rem 1rem; background:#f4f6f9; border-bottom:1px solid #dde3ec;
                            font-weight:600; font-size:0.85rem; color:#1a2b4a;">
                    <i class="fas fa-calendar-day"></i> <?= date('l, F j, Y', strtotime($hDate)) ?>
                    <?php
                        $p = count(array_filter($dayRows, fn($r) => $r['status'] === 'present'));
                        $a = count(array_filter($dayRows, fn($r) => $r['status'] === 'absent'));
                        $l = count(array_filter($dayRows, fn($r) => $r['status'] === 'late'));
                    ?>
                    &nbsp;
                    <span class="badge badge-present">P: <?= $p ?></span>
                    <span class="badge badge-absent">A: <?= $a ?></span>
                    <span class="badge badge-late">L: <?= $l ?></span>
                </div>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dayRows as $j => $hr): ?>
                            <tr>
                                <td><?= $j + 1 ?></td>
                                <td><?= htmlspecialchars($hr['full_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $hr['status'] ?>">
                                        <?= ucfirst($hr['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.main-content -->

</div><!-- /.layout -->

<style>

tr.row-present { background-color: #f0fdf4 !important; }
tr.row-absent  { background-color: #fff5f5 !important; }
tr.row-late    { background-color: #fff8f0 !important; }

.radio-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.radio-label input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #1a2b4a;
}
</style>

<script>
function highlightRow(studentId, status) {
    const row = document.getElementById('row-' + studentId);
    if (!row) return;
    row.className = row.className.replace(/\brow-\w+\b/g, '').trim();
    row.classList.add('row-' + status);
}

function markAll(status) {
    document.querySelectorAll('#attendanceTable tbody tr').forEach(function (row) {
        const radio = row.querySelector('input[value="' + status + '"]');
        if (radio) {
            radio.checked = true;
            row.className = row.className.replace(/\brow-\w+\b/g, '').trim();
            row.classList.add('row-' + status);
        }
    });
}


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#attendanceTable tbody tr').forEach(function (row) {
        const checked = row.querySelector('input[type="radio"]:checked');
        if (checked) {
            row.classList.add('row-' + checked.value);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

