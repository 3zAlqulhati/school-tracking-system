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
$viewMode = false;        // viewing submissions for a specific assignment
$viewData = null;
$editAssign = null;

if (isset($_GET['added'])) $success = 'Assignment created successfully.';

define('UPLOAD_DIR',     __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',     '/school_tracking/assets/uploads/');
define('MAX_FILE_BYTES', 10 * 1024 * 1024);   // 10 MB
$allowedMime = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$allowedExt  = ['pdf', 'doc', 'docx'];




if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    try {

        $stmt = $pdo->prepare(
            'SELECT file_path FROM assignments WHERE assignment_id = ? AND teacher_id = ?'
        );
        $stmt->execute([$deleteId, $teacherId]);
        $toDelete = $stmt->fetch();

        if (!$toDelete) {
            $error = 'Assignment not found or access denied.';
        } else {
            $pdo->prepare(
                'DELETE FROM assignments WHERE assignment_id = ? AND teacher_id = ?'
            )->execute([$deleteId, $teacherId]);


            if ($toDelete['file_path']) {
                $filePath = UPLOAD_DIR . basename($toDelete['file_path']);
                if (file_exists($filePath)) @unlink($filePath);
            }
            $success = 'Assignment deleted successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Delete failed: ' . $e->getMessage();
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_assignment') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $classId     = isset($_POST['class_id']) && is_numeric($_POST['class_id'])
                 ? (int) $_POST['class_id'] : 0;
    $deadline    = trim($_POST['deadline']    ?? '') ?: null;
    $filePath    = null;


    if ($title === '' || $classId === 0) {
        $error = 'Title and class are required.';
    } elseif ($deadline !== null && !strtotime($deadline)) {
        $error = 'Invalid deadline format.';
    } else {

        if (!empty($_FILES['assignment_file']['name'])) {
            $file     = $_FILES['assignment_file'];
            $origName = $file['name'];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $mime     = mime_content_type($file['tmp_name']);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload error (code ' . $file['error'] . ').';
            } elseif ($file['size'] > MAX_FILE_BYTES) {
                $error = 'File exceeds 10 MB limit.';
            } elseif (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                $error = 'Only PDF, DOC, and DOCX files are allowed.';
            } else {
                $uniqueName = uniqid('assign_', true) . '.' . $ext;
                $dest       = UPLOAD_DIR . $uniqueName;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $error = 'Could not save the uploaded file. Check folder permissions.';
                } else {
                    $filePath = $uniqueName;
                }
            }
        }

        if ($error === '') {
            try {
                $pdo->prepare(
                    'INSERT INTO assignments
                         (teacher_id, class_id, title, description, file_path, deadline)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$teacherId, $classId, $title, $description, $filePath, $deadline]);
                header('Location: assignments.php?added=1');
                exit;
            } catch (PDOException $e) {

                if ($filePath && file_exists(UPLOAD_DIR . $filePath)) {
                    @unlink(UPLOAD_DIR . $filePath);
                }
                $error = 'Could not save assignment: ' . $e->getMessage();
            }
        }
    }
}




if (isset($_GET['edit_assign']) && is_numeric($_GET['edit_assign'])) {
    $editId = (int) $_GET['edit_assign'];
    $stmtEdit = $pdo->prepare(
        'SELECT * FROM assignments WHERE assignment_id = ? AND teacher_id = ?'
    );
    $stmtEdit->execute([$editId, $teacherId]);
    $editAssign = $stmtEdit->fetch();
    if (!$editAssign) {
        $error      = 'Assignment not found or access denied.';
        $editAssign = null;
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_assignment') {
    $assignId    = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id'])
                 ? (int)$_POST['assignment_id'] : 0;
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $classId     = isset($_POST['class_id']) && is_numeric($_POST['class_id'])
                 ? (int)$_POST['class_id'] : 0;
    $deadline    = trim($_POST['deadline']    ?? '') ?: null;

    if ($assignId === 0 || $title === '' || $classId === 0) {
        $error = 'Title and class are required.';
    } elseif ($deadline !== null && !strtotime($deadline)) {
        $error = 'Invalid deadline format.';
    } else {

        $stmtCur = $pdo->prepare(
            'SELECT file_path FROM assignments WHERE assignment_id = ? AND teacher_id = ?'
        );
        $stmtCur->execute([$assignId, $teacherId]);
        $curAssign = $stmtCur->fetch();

        if (!$curAssign) {
            $error = 'Assignment not found or access denied.';
        } else {
            $filePath = $curAssign['file_path'];


            if (!empty($_FILES['assignment_file']['name'])) {
                $file     = $_FILES['assignment_file'];
                $origName = $file['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $mime     = mime_content_type($file['tmp_name']);
                $allowedMime = ['application/pdf', 'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $allowedExt  = ['pdf', 'doc', 'docx'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'File upload error (code ' . $file['error'] . ').';
                } elseif ($file['size'] > MAX_FILE_BYTES) {
                    $error = 'File exceeds 10 MB limit.';
                } elseif (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                    $error = 'Only PDF, DOC, and DOCX files are allowed.';
                } else {
                    $uniqueName = uniqid('assign_', true) . '.' . $ext;
                    $dest       = UPLOAD_DIR . $uniqueName;
                    if (!move_uploaded_file($file['tmp_name'], $dest)) {
                        $error = 'Could not save the uploaded file.';
                    } else {

                        if ($filePath && file_exists(UPLOAD_DIR . basename($filePath))) {
                            @unlink(UPLOAD_DIR . basename($filePath));
                        }
                        $filePath = $uniqueName;
                    }
                }
            }

            if ($error === '') {
                try {
                    $pdo->prepare(
                        'UPDATE assignments
                         SET title = ?, description = ?, class_id = ?, deadline = ?, file_path = ?
                         WHERE assignment_id = ? AND teacher_id = ?'
                    )->execute([$title, $description, $classId, $deadline, $filePath, $assignId, $teacherId]);
                    header('Location: assignments.php?updated=1');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Could not update assignment: ' . $e->getMessage();
                }
            }
        }
    }
}

if (isset($_GET['updated'])) $success = 'Assignment updated successfully.';




if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewId = (int) $_GET['view'];

    $stmtA = $pdo->prepare(
        'SELECT a.*, c.class_name
         FROM assignments a
         JOIN classes c ON c.class_id = a.class_id
         WHERE a.assignment_id = ? AND a.teacher_id = ?'
    );
    $stmtA->execute([$viewId, $teacherId]);
    $viewAssignment = $stmtA->fetch();

    if (!$viewAssignment) {
        $error    = 'Assignment not found or access denied.';
    } else {
        $viewMode = true;


        $stmtAllStudents = $pdo->prepare(
            'SELECT s.student_id, u.full_name
             FROM students s
             JOIN users u ON u.user_id = s.user_id
             WHERE s.class_id = ?
             ORDER BY u.full_name'
        );
        $stmtAllStudents->execute([$viewAssignment['class_id']]);
        $allStudents = $stmtAllStudents->fetchAll();


        $stmtSubs = $pdo->prepare(
            'SELECT sub.submission_id, sub.student_id, sub.file_path,
                    sub.submitted_at, sub.status,
                    u.full_name
             FROM assignment_submissions sub
             JOIN students s ON s.student_id = sub.student_id
             JOIN users    u ON u.user_id    = s.user_id
             WHERE sub.assignment_id = ?
             ORDER BY sub.submitted_at ASC'
        );
        $stmtSubs->execute([$viewId]);
        $submissions     = $stmtSubs->fetchAll();
        $submittedIds    = array_column($submissions, 'student_id');


        $notSubmitted = array_filter(
            $allStudents,
            fn($s) => !in_array($s['student_id'], $submittedIds)
        );

        $viewData = [
            'assignment'   => $viewAssignment,
            'submissions'  => $submissions,
            'notSubmitted' => $notSubmitted,
            'allStudents'  => $allStudents,
        ];
    }
}




$stmtClasses = $pdo->prepare(
    'SELECT DISTINCT c.class_id, c.class_name, c.academic_year
     FROM classes c
     WHERE c.class_id IN (
         SELECT class_id FROM assignments    WHERE teacher_id = ?
         UNION
         SELECT class_id FROM attendance     WHERE teacher_id = ?
         UNION
         SELECT class_id FROM class_teachers WHERE teacher_id = ?
     )
     ORDER BY c.class_name'
);
$stmtClasses->execute([$teacherId, $teacherId, $teacherId]);
$teacherClasses = $stmtClasses->fetchAll();




$stmtAssign = $pdo->prepare(
    'SELECT a.assignment_id, a.title, a.description, a.file_path,
            a.deadline, a.created_at,
            c.class_name,
            COUNT(sub.submission_id) AS submission_count
     FROM assignments a
     JOIN classes c ON c.class_id = a.class_id
     LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.assignment_id
     WHERE a.teacher_id = ?
     GROUP BY a.assignment_id
     ORDER BY a.created_at DESC'
);
$stmtAssign->execute([$teacherId]);
$assignments = $stmtAssign->fetchAll();

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
            <a href="/school_tracking/teacher/grades.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-star"></i></span> Manage Grades
            </a>
            <a href="/school_tracking/teacher/assignments.php" class="sidebar-link active">
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

        <?php if ($viewMode && $viewData): ?>
        <!-- ====================================================
             SUBMISSIONS VIEW
        ===================================================== -->
        <?php $assign = $viewData['assignment']; ?>

        <div class="d-flex align-center gap-2 mb-3">
            <a href="assignments.php" class="btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            <h1 class="page-title" style="margin:0; border:none; padding:0;">
                <i class="fas fa-inbox"></i> Submissions: <?= htmlspecialchars($assign['title']) ?>
            </h1>
        </div>

        <!-- Assignment meta -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-info-circle"></i> Assignment Details</div>
            <div style="padding:1rem; display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div>
                    <span class="text-muted" style="font-size:0.78rem;">Class</span><br>
                    <strong><?= htmlspecialchars($assign['class_name']) ?></strong>
                </div>
                <div>
                    <span class="text-muted" style="font-size:0.78rem;">Deadline</span><br>
                    <?php if ($assign['deadline']): ?>
                        <strong class="<?= strtotime($assign['deadline']) < time() ? 'text-danger' : '' ?>">
                            <?= date('M j, Y g:i A', strtotime($assign['deadline'])) ?>
                        </strong>
                    <?php else: ?>
                        <strong>No Deadline</strong>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-muted" style="font-size:0.78rem;">Attached File</span><br>
                    <?php if ($assign['file_path']): ?>
                        <a href="<?= UPLOAD_URL . htmlspecialchars($assign['file_path']) ?>"
                           target="_blank" class="btn-primary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    <?php else: ?>
                        <span class="text-muted">None</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($assign['description']): ?>
            <div style="padding:0 1rem 1rem;">
                <span class="text-muted" style="font-size:0.78rem;">Description</span><br>
                <?= nl2br(htmlspecialchars($assign['description'])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary badges -->
        <div class="d-flex gap-2 mb-3" style="flex-wrap:wrap;">
            <span class="badge badge-info">
                Total Students: <?= count($viewData['allStudents']) ?>
            </span>
            <span class="badge badge-present">
                Submitted: <?= count($viewData['submissions']) ?>
            </span>
            <span class="badge badge-absent">
                Not Submitted: <?= count($viewData['notSubmitted']) ?>
            </span>
        </div>

        <!-- Submissions table -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-check-circle"></i> Submitted</div>
            <?php if (empty($viewData['submissions'])): ?>
                <p class="text-muted text-center mt-3 mb-3">No submissions yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Submitted At</th>
                            <th>Status</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewData['submissions'] as $i => $sub): ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Student">
                                <strong><?= htmlspecialchars($sub['full_name']) ?></strong>
                            </td>
                            <td data-label="Submitted At">
                                <?= date('M j, Y g:i A', strtotime($sub['submitted_at'])) ?>
                            </td>
                            <td data-label="Status">
                                <span class="badge badge-<?= $sub['status'] === 'graded' ? 'info' : ($sub['status'] === 'late' ? 'late' : 'present') ?>">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                            </td>
                            <td data-label="File">
                                <?php if ($sub['file_path']): ?>
                                    <a href="<?= UPLOAD_URL . htmlspecialchars($sub['file_path']) ?>"
                                       target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No file</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Not submitted table -->
        <?php if (!empty($viewData['notSubmitted'])): ?>
        <div class="card">
            <div class="card-header" style="color:#ef4444;"><i class="fas fa-times-circle"></i> Not Yet Submitted</div>
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
                        <?php foreach (array_values($viewData['notSubmitted']) as $j => $ns): ?>
                        <tr>
                            <td><?= $j + 1 ?></td>
                            <td><?= htmlspecialchars($ns['full_name']) ?></td>
                            <td><span class="badge badge-absent">Not Submitted</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ====================================================
             DEFAULT VIEW — FORM + ASSIGNMENT LIST
        ===================================================== -->

        <h1 class="page-title">
            <span class="page-title-icon"><i class="fas fa-file-alt"></i></span>
            Assignments
        </h1>

        <!-- Flash Messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create / Edit Assignment Form -->
        <div class="card mb-4">
            <div class="card-header">
                <?= $editAssign
                    ? '<i class="fas fa-edit"></i> Edit Assignment'
                    : '<i class="fas fa-plus-circle"></i> Create New Assignment' ?>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editAssign ? 'edit_assignment' : 'add_assignment' ?>">
                <?php if ($editAssign): ?>
                    <input type="hidden" name="assignment_id" value="<?= $editAssign['assignment_id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title"
                               value="<?= htmlspecialchars($editAssign['title'] ?? ($_POST['title'] ?? '')) ?>"
                               placeholder="e.g. Chapter 3 Problem Set" required>
                    </div>
                    <div class="form-group">
                        <label for="class_id">Class <span class="text-danger">*</span></label>
                        <select id="class_id" name="class_id" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($teacherClasses as $cls): ?>
                                <?php $sel = $editAssign
                                    ? ($editAssign['class_id'] == $cls['class_id'])
                                    : (($_POST['class_id'] ?? '') == $cls['class_id']); ?>
                                <option value="<?= $cls['class_id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                    (<?= htmlspecialchars($cls['academic_year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Instructions, requirements, notes..."
                              rows="3"><?= htmlspecialchars($editAssign['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="deadline">Deadline</label>
                        <?php
                        $deadlineVal = '';
                        if ($editAssign && $editAssign['deadline']) {
                            $deadlineVal = date('Y-m-d\TH:i', strtotime($editAssign['deadline']));
                        } elseif (!empty($_POST['deadline'])) {
                            $deadlineVal = $_POST['deadline'];
                        }
                        ?>
                        <input type="datetime-local" id="deadline" name="deadline"
                               value="<?= htmlspecialchars($deadlineVal) ?>">
                        <span class="form-hint">Leave blank for no deadline.</span>
                    </div>
                    <div class="form-group">
                        <label for="assignment_file">
                            <?= $editAssign ? 'Replace File' : 'Attach File' ?>
                            <span class="text-muted">(PDF, DOC, DOCX — max 10 MB)</span>
                        </label>
                        <input type="file" id="assignment_file" name="assignment_file"
                               accept=".pdf,.doc,.docx">
                        <?php if ($editAssign && $editAssign['file_path']): ?>
                            <span class="form-hint">
                                Current file attached. Upload a new one to replace it.
                            </span>
                        <?php else: ?>
                            <span class="form-hint">Optional reference material for students.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($editAssign): ?>
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Update Assignment</button>
                        <a href="assignments.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php else: ?>
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Assignment</button>
                        <button type="reset"  class="btn-secondary">Reset</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-file-alt"></i> My Assignments</span>
                <span class="text-muted" style="font-weight:400; font-size:0.82rem;">
                    (<?= count($assignments) ?> total)
                </span>
            </div>

            <?php if (empty($assignments)): ?>
                <p class="text-muted text-center mt-3 mb-3">No assignments created yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Deadline</th>
                            <th>File</th>
                            <th>Submissions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $i => $a): ?>
                        <?php
                            $hasDeadline = !empty($a['deadline']);
                            $isOverdue   = $hasDeadline && strtotime($a['deadline']) < time();
                        ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Title">
                                <strong><?= htmlspecialchars($a['title']) ?></strong>
                                <?php if ($a['description']): ?>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars(mb_strimwidth($a['description'], 0, 60, '…')) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Class">
                                <?= htmlspecialchars($a['class_name']) ?>
                            </td>
                            <td data-label="Deadline">
                                <?php if ($hasDeadline): ?>
                                    <span class="<?= $isOverdue ? 'text-danger' : '' ?>">
                                        <?= date('M j, Y', strtotime($a['deadline'])) ?><br>
                                        <small><?= date('g:i A', strtotime($a['deadline'])) ?></small>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="File">
                                <?php if ($a['file_path']): ?>
                                    <a href="<?= UPLOAD_URL . htmlspecialchars($a['file_path']) ?>"
                                       target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-file"></i> File
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
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
                            <td data-label="Actions">
                                <div class="d-flex gap-1">
                                    <a href="?view=<?= $a['assignment_id'] ?>"
                                       class="btn-primary btn-sm"><i class="fas fa-inbox"></i> Submissions</a>
                                    <a href="?edit_assign=<?= $a['assignment_id'] ?>"
                                       class="btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?= $a['assignment_id'] ?>"
                                       class="btn-danger btn-sm"
                                       onclick="return confirmDelete('<?= htmlspecialchars(addslashes($a['title'])) ?>', <?= (int)$a['submission_count'] ?>)">
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

        <?php endif; // end default view ?>

    </div><!-- /.main-content -->

</div><!-- /.layout -->

<script>
function confirmDelete(title, submissions) {
    let msg = 'Delete assignment "' + title + '"?';
    if (submissions > 0) {
        msg += '\n\nWarning: ' + submissions + ' student submission(s) will also be deleted.';
    }
    msg += '\n\nThis cannot be undone.';
    return confirm(msg);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

