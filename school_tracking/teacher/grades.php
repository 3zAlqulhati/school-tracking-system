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

$success = '';
$error   = '';

if (isset($_GET['added'])) $success = 'Grade recorded successfully.';

$examTypes = ['Quiz', 'Midterm', 'Final', 'Assignment', 'Project', 'Recitation'];




if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    try {

        $stmt = $pdo->prepare(
            'DELETE FROM grades WHERE grade_id = ? AND teacher_id = ?'
        );
        $stmt->execute([$deleteId, $teacherId]);
        if ($stmt->rowCount()) {
            $success = 'Grade deleted successfully.';
        } else {
            $error = 'Grade not found or you do not have permission to delete it.';
        }
    } catch (PDOException $e) {
        $error = 'Delete failed: ' . $e->getMessage();
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_grade') {
    $classId   = isset($_POST['class_id'])   && is_numeric($_POST['class_id'])   ? (int)$_POST['class_id']   : 0;
    $studentId = isset($_POST['student_id']) && is_numeric($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $subject   = trim($_POST['subject']     ?? '');
    $gradeVal  = $_POST['grade_value'] ?? '';
    $examType  = trim($_POST['exam_type']   ?? '');
    $remarks   = trim($_POST['remarks']     ?? '') ?: null;

    if ($classId === 0 || $studentId === 0 || $subject === '' || $gradeVal === '' || $examType === '') {
        $error = 'Class, student, subject, grade value, and exam type are required.';
    } elseif (!is_numeric($gradeVal) || (float)$gradeVal < 0 || (float)$gradeVal > 100) {
        $error = 'Grade value must be a number between 0 and 100.';
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO grades
                     (student_id, teacher_id, class_id, subject, grade_value, exam_type, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$studentId, $teacherId, $classId, $subject, (float)$gradeVal, $examType, $remarks]);
            header('Location: grades.php?added=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not save grade: ' . $e->getMessage();
        }
    }
}




$editGrade = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT * FROM grades WHERE grade_id = ? AND teacher_id = ?'
    );
    $stmt->execute([(int)$_GET['edit'], $teacherId]);
    $editGrade = $stmt->fetch();
    if (!$editGrade) {
        $error     = 'Grade not found or access denied.';
        $editGrade = null;
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_grade') {
    $gradeId  = isset($_POST['grade_id']) && is_numeric($_POST['grade_id']) ? (int)$_POST['grade_id'] : 0;
    $classId  = isset($_POST['class_id'])  && is_numeric($_POST['class_id'])  ? (int)$_POST['class_id']  : 0;
    $studentId = isset($_POST['student_id']) && is_numeric($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $subject  = trim($_POST['subject']     ?? '');
    $gradeVal = $_POST['grade_value'] ?? '';
    $examType = trim($_POST['exam_type']   ?? '');
    $remarks  = trim($_POST['remarks']     ?? '') ?: null;

    if ($gradeId === 0 || $classId === 0 || $studentId === 0 || $subject === '' || $gradeVal === '' || $examType === '') {
        $error = 'All fields are required.';
    } elseif (!is_numeric($gradeVal) || (float)$gradeVal < 0 || (float)$gradeVal > 100) {
        $error = 'Grade value must be between 0 and 100.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE grades
                 SET student_id  = ?,
                     class_id    = ?,
                     subject     = ?,
                     grade_value = ?,
                     exam_type   = ?,
                     remarks     = ?
                 WHERE grade_id = ? AND teacher_id = ?'
            );
            $stmt->execute([
                $studentId, $classId, $subject,
                (float)$gradeVal, $examType, $remarks,
                $gradeId, $teacherId,
            ]);
            $success   = 'Grade updated successfully.';
            $editGrade = null;
            header('Location: grades.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update grade: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['updated'])) {
    $success = 'Grade updated successfully.';
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




$studentsByClass = [];
if (!empty($teacherClasses)) {
    $classIds     = array_column($teacherClasses, 'class_id');
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $stmtStu      = $pdo->prepare(
        "SELECT DISTINCT s.student_id, sc.class_id, u.full_name
         FROM students s
         JOIN student_classes sc ON sc.student_id = s.student_id
         JOIN users u ON u.user_id = s.user_id
         WHERE sc.class_id IN ({$placeholders})
         ORDER BY u.full_name"
    );
    $stmtStu->execute($classIds);
    foreach ($stmtStu->fetchAll() as $stu) {
        $studentsByClass[$stu['class_id']][] = $stu;
    }
}




$allGrades = $pdo->prepare(
    'SELECT g.grade_id,
            g.subject, g.grade_value, g.exam_type, g.remarks, g.graded_at,
            g.student_id, g.class_id,
            u.full_name  AS student_name,
            c.class_name
     FROM grades g
     JOIN students s ON s.student_id = g.student_id
     JOIN users    u ON u.user_id    = s.user_id
     JOIN classes  c ON c.class_id   = g.class_id
     WHERE g.teacher_id = ?
     ORDER BY g.graded_at DESC'
);
$allGrades->execute([$teacherId]);
$grades = $allGrades->fetchAll();

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
            <a href="/school_tracking/teacher/attendance.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-clipboard-check"></i></span> Record Attendance
            </a>
            <a href="/school_tracking/teacher/grades.php" class="sidebar-link active">
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
            <span class="page-title-icon"><i class="fas fa-star"></i></span>
            Manage Grades
        </h1>

        <!-- Flash Messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ================================================
             ADD / EDIT GRADE FORM
        ================================================= -->
        <div class="card mb-4">
            <div class="card-header">
                <?= $editGrade ? '<i class="fas fa-edit"></i> Edit Grade' : '<i class="fas fa-plus-circle"></i> Record New Grade' ?>
            </div>

            <form method="POST" action="" id="gradeForm">
                <input type="hidden" name="action"
                       value="<?= $editGrade ? 'edit_grade' : 'add_grade' ?>">
                <?php if ($editGrade): ?>
                    <input type="hidden" name="grade_id" value="<?= $editGrade['grade_id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <!-- Class -->
                    <div class="form-group">
                        <label for="class_id">Class <span class="text-danger">*</span></label>
                        <select id="class_id" name="class_id" required
                                onchange="filterStudents(this.value)">
                            <option value="">— Select Class —</option>
                            <?php foreach ($teacherClasses as $cls): ?>
                                <?php $sel = $editGrade
                                    ? ($editGrade['class_id'] == $cls['class_id'])
                                    : (($_POST['class_id'] ?? '') == $cls['class_id']); ?>
                                <option value="<?= $cls['class_id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                    (<?= htmlspecialchars($cls['academic_year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student (filtered by class via JS) -->
                    <div class="form-group">
                        <label for="student_id">Student <span class="text-danger">*</span></label>
                        <select id="student_id" name="student_id" required>
                            <option value="">— Select Class First —</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Subject -->
                    <div class="form-group">
                        <label for="subject">Subject <span class="text-danger">*</span></label>
                        <input type="text" id="subject" name="subject"
                               value="<?= htmlspecialchars($editGrade['subject'] ?? ($_POST['subject'] ?? '')) ?>"
                               placeholder="e.g. Mathematics" required>
                    </div>

                    <!-- Exam Type -->
                    <div class="form-group">
                        <label for="exam_type">Exam Type <span class="text-danger">*</span></label>
                        <select id="exam_type" name="exam_type" required>
                            <option value="">— Select Type —</option>
                            <?php foreach ($examTypes as $et): ?>
                                <?php $sel = $editGrade
                                    ? ($editGrade['exam_type'] === $et)
                                    : (($_POST['exam_type'] ?? '') === $et); ?>
                                <option value="<?= $et ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= $et ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Grade Value -->
                    <div class="form-group">
                        <label for="grade_value">Grade Value (0–100) <span class="text-danger">*</span></label>
                        <input type="number" id="grade_value" name="grade_value"
                               value="<?= htmlspecialchars($editGrade['grade_value'] ?? ($_POST['grade_value'] ?? '')) ?>"
                               min="0" max="100" step="0.01"
                               placeholder="e.g. 87.50" required>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label for="remarks">Remarks <span class="text-muted">(optional)</span></label>
                        <input type="text" id="remarks" name="remarks"
                               value="<?= htmlspecialchars($editGrade['remarks'] ?? ($_POST['remarks'] ?? '')) ?>"
                               placeholder="e.g. Needs improvement in fractions">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <?= $editGrade ? '<i class="fas fa-check"></i> Update Grade' : '<i class="fas fa-save"></i> Save Grade' ?>
                    </button>
                    <?php if ($editGrade): ?>
                        <a href="grades.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php else: ?>
                        <button type="reset" class="btn-secondary"
                                onclick="document.getElementById('student_id').innerHTML='<option value=\'\'>— Select Class First —</option>'">
                            Reset
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ================================================
             GRADES TABLE
        ================================================= -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-list"></i> All Grades Entered</span>
                <span class="text-muted" style="font-weight:400; font-size:0.82rem;">
                    (<?= count($grades) ?> record<?= count($grades) !== 1 ? 's' : '' ?>)
                </span>
            </div>

            <!-- Filter bar -->
            <?php if (!empty($grades)): ?>
            <div style="padding:0.75rem 1rem; border-bottom:1px solid #dde3ec;">
                <input type="text" id="gradeSearch"
                       placeholder="Filter by student name, subject or exam type..."
                       oninput="filterGradeTable(this.value)"
                       style="max-width:420px;">
            </div>
            <?php endif; ?>

            <?php if (empty($grades)): ?>
                <p class="text-muted text-center mt-3 mb-3">No grades recorded yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table" id="gradesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $i => $g): ?>
                        <?php
                            $gv       = (float) $g['grade_value'];
                            $passing  = $gv >= 75;
                            $gBadge   = $passing ? 'badge-present' : 'badge-absent';
                        ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Student">
                                <strong><?= htmlspecialchars($g['student_name']) ?></strong>
                            </td>
                            <td data-label="Class">
                                <?= htmlspecialchars($g['class_name']) ?>
                            </td>
                            <td data-label="Subject">
                                <?= htmlspecialchars($g['subject']) ?>
                            </td>
                            <td data-label="Exam Type">
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($g['exam_type']) ?>
                                </span>
                            </td>
                            <td data-label="Grade">
                                <span class="badge <?= $gBadge ?>">
                                    <?= number_format($gv, 2) ?>
                                </span>
                            </td>
                            <td data-label="Remarks">
                                <?= htmlspecialchars($g['remarks'] ?? '—') ?>
                            </td>
                            <td data-label="Date">
                                <?= date('M j, Y', strtotime($g['graded_at'])) ?>
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-1">
                                    <a href="?edit=<?= $g['grade_id'] ?>"
                                       class="btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?= $g['grade_id'] ?>"
                                       class="btn-danger btn-sm"
                                       onclick="return confirm('Delete this grade for <?= htmlspecialchars(addslashes($g['student_name'])) ?>?\n\nThis cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
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

<!-- Students data for JS class→student filtering -->
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_HEX_TAG) ?>;


const preselectedClass   = <?= json_encode($editGrade['class_id']   ?? ($_POST['class_id']   ?? '')) ?>;
const preselectedStudent = <?= json_encode($editGrade['student_id'] ?? ($_POST['student_id'] ?? '')) ?>;

function filterStudents(classId) {
    const sel      = document.getElementById('student_id');
    const students = studentsByClass[classId] || [];
    sel.innerHTML  = students.length
        ? '<option value="">— Select Student —</option>'
        : '<option value="">No students in this class</option>';

    students.forEach(function (s) {
        const opt      = document.createElement('option');
        opt.value      = s.student_id;
        opt.textContent = s.full_name;
        if (String(s.student_id) === String(preselectedStudent)) opt.selected = true;
        sel.appendChild(opt);
    });
}

function filterGradeTable(query) {
    const q    = query.toLowerCase();
    const rows = document.querySelectorAll('#gradesTable tbody tr');
    rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}


document.addEventListener('DOMContentLoaded', function () {
    if (preselectedClass) filterStudents(preselectedClass);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

