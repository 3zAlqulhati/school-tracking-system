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
$classId  = !empty($classIds) ? $classIds[0] : 0;

$stmtN = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtN->execute([$_SESSION['user_id']]);
$unreadCount = (int) $stmtN->fetchColumn();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_assignment') {
    $assignmentId = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id'])
                  ? (int)$_POST['assignment_id'] : 0;

    if ($assignmentId === 0) {
        $error = 'Invalid assignment.';
    } elseif (empty($_FILES['submission_file']['name'])) {
        $error = 'Please select a file to upload.';
    } else {
        $file     = $_FILES['submission_file'];
        $origName = $file['name'];
        $tmpPath  = $file['tmp_name'];
        $fileSize = $file['size'];

        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['pdf', 'doc', 'docx', 'zip'];
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ];
        $mime = mime_content_type($tmpPath);

        if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMimes, true)) {
            $error = 'Invalid file type. Allowed: PDF, DOC, DOCX, ZIP.';
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $error = 'File exceeds 10 MB limit.';
        } else {
            $stmtChk = $pdo->prepare(
                'SELECT assignment_id FROM assignments
                 WHERE assignment_id = ? AND class_id IN (SELECT class_id FROM student_classes WHERE student_id = ?)
                   AND (deadline IS NULL OR deadline > NOW())'
            );
            $stmtChk->execute([$assignmentId, $studentId]);
            if (!$stmtChk->fetch()) {
                $error = 'Assignment not found or deadline has passed.';
            } else {
                $uploadDir = __DIR__ . '/../assets/uploads/submissions/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newName  = uniqid('sub_', true) . '.' . $ext;
                $destPath = $uploadDir . $newName;

                if (!move_uploaded_file($tmpPath, $destPath)) {
                    $error = 'Failed to save the uploaded file.';
                } else {
                    try {
                        $stmtIns = $pdo->prepare(
                            'INSERT INTO assignment_submissions
                                (assignment_id, student_id, file_path, status)
                             VALUES (?, ?, ?, "submitted")'
                        );
                        $stmtIns->execute([$assignmentId, $studentId, 'assets/uploads/submissions/' . $newName]);
                        $success = 'Assignment submitted successfully.';
                    } catch (PDOException $e) {
                        @unlink($destPath);
                        if ($e->getCode() === '23000') {
                            $error = 'You have already submitted this assignment.';
                        } else {
                            $error = 'Could not save submission: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }

    if ($success !== '') {
        header('Location: assignments.php?tab=submitted&msg=submitted');
        exit;
    }
}

$flashMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'submitted') {
    $flashMsg = 'Assignment submitted successfully.';
}

$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'submitted') ? 'submitted' : 'pending';

$stmtAll = $pdo->prepare(
    'SELECT a.assignment_id, a.title, a.description, a.deadline,
            a.file_path AS teacher_file,
            u.full_name AS teacher_name,
            t.subject_specialty AS teacher_subject,
            sub.submission_id, sub.submitted_at, sub.status AS sub_status,
            sub.file_path AS sub_file
     FROM assignments a
     JOIN teachers t ON t.teacher_id = a.teacher_id
     JOIN users    u ON u.user_id    = t.user_id
     LEFT JOIN assignment_submissions sub
           ON sub.assignment_id = a.assignment_id
          AND sub.student_id    = ?
     WHERE a.class_id IN (SELECT class_id FROM student_classes WHERE student_id = ?)
     ORDER BY a.deadline ASC'
);
$stmtAll->execute([$studentId, $studentId]);
$allAssignments = $stmtAll->fetchAll();

$pending   = array_filter($allAssignments, fn($a) =>
    $a['submission_id'] === null &&
    ($a['deadline'] === null || strtotime($a['deadline']) > time())
);
$submitted = array_filter($allAssignments, fn($a) => $a['submission_id'] !== null);

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
            <a href="/school_tracking/student/view_grades.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-star"></i></span> My Grades
            </a>
            <a href="/school_tracking/student/assignments.php" class="sidebar-link active">
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

        <h1 class="page-title"><span class="page-title-icon"><i class="fas fa-file-alt"></i></span> My Assignments</h1>

        <?php if ($flashMsg !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div style="display:flex;gap:0;margin-bottom:1.5rem;border-bottom:2px solid #dee2e6;">
            <a href="assignments.php?tab=pending"
               style="padding:0.6rem 1.5rem;text-decoration:none;font-weight:600;
                      color:<?= $activeTab === 'pending' ? '#1a2b4a' : '#6c757d' ?>;
                      border-bottom:<?= $activeTab === 'pending' ? '2px solid #1a2b4a' : '2px solid transparent' ?>;
                      margin-bottom:-2px;">
                Pending
                <span class="badge badge-warning" style="margin-left:0.4rem;"><?= count($pending) ?></span>
            </a>
            <a href="assignments.php?tab=submitted"
               style="padding:0.6rem 1.5rem;text-decoration:none;font-weight:600;
                      color:<?= $activeTab === 'submitted' ? '#1a2b4a' : '#6c757d' ?>;
                      border-bottom:<?= $activeTab === 'submitted' ? '2px solid #1a2b4a' : '2px solid transparent' ?>;
                      margin-bottom:-2px;">
                Submitted
                <span class="badge badge-success" style="margin-left:0.4rem;"><?= count($submitted) ?></span>
            </a>
        </div>

        <!-- PENDING TAB -->
        <?php if ($activeTab === 'pending'): ?>

            <?php if (empty($pending)): ?>
                <div class="card">
                    <p class="text-muted text-center mt-3 mb-3">No pending assignments. Great job!</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $a):
                    $diff = $a['deadline'] ? strtotime($a['deadline']) - time() : null;
                    $days = $diff !== null ? (int) floor($diff / 86400) : null;
                ?>
                <div class="card mb-4">
                    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                        <span><strong><?= htmlspecialchars($a['title']) ?></strong></span>
                        <?php if ($a['deadline']): ?>
                            <span class="badge <?= $days !== null && $days < 2 ? 'badge-danger' : 'badge-warning' ?>">
                                Due: <?= date('M j, Y g:i A', strtotime($a['deadline'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-info">No deadline</span>
                        <?php endif; ?>
                    </div>
                    <div style="padding:1rem 1.5rem;">
                        <p style="margin:0 0 0.75rem;color:#495057;">
                            <?= nl2br(htmlspecialchars($a['description'] ?? '')) ?>
                        </p>
                        <div style="font-size:0.83rem;color:#6c757d;margin-bottom:1rem;">
                            <i class="fas fa-chalkboard-teacher" style="color:#6c757d;"></i>
                            <?= htmlspecialchars($a['teacher_name']) ?>
                            <?php if (!empty($a['teacher_subject'])): ?>
                                &nbsp;&mdash;&nbsp;
                                <span style="color:var(--primary);font-weight:600;">
                                    <?= htmlspecialchars($a['teacher_subject']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($a['teacher_file']): ?>
                                &nbsp;|&nbsp;
                                <a href="/school_tracking/assets/uploads/<?= htmlspecialchars($a['teacher_file']) ?>"
                                   target="_blank"><i class="fas fa-download"></i> Download Assignment File</a>
                            <?php endif; ?>
                        </div>

                        <!-- Submit form -->
                        <form method="POST" enctype="multipart/form-data"
                              style="display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap;">
                            <input type="hidden" name="action" value="submit_assignment">
                            <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                            <div class="form-group" style="flex:1;min-width:200px;margin:0;">
                                <label style="font-size:0.83rem;font-weight:600;margin-bottom:0.3rem;display:block;">
                                    Upload Your Submission
                                    <span style="color:#6c757d;font-weight:400;">(PDF / DOC / DOCX / ZIP, max 10 MB)</span>
                                </label>
                                <input type="file" name="submission_file"
                                       accept=".pdf,.doc,.docx,.zip"
                                       style="display:block;width:100%;padding:0.4rem;" required>
                            </div>
                            <button type="submit" class="btn-primary"
                                    style="white-space:nowrap;padding:0.55rem 1.4rem;">
                                <i class="fas fa-paper-plane"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <!-- SUBMITTED TAB -->
        <?php else: ?>

            <?php if (empty($submitted)): ?>
                <div class="card">
                    <p class="text-muted text-center mt-3 mb-3">No submitted assignments yet.</p>
                </div>
            <?php else: ?>
            <div class="card">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Assignment</th>
                                <th>Teacher</th>
                                <th>Deadline</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_values($submitted) as $i => $a):
                                $badge = match($a['sub_status']) {
                                    'graded'    => 'badge-success',
                                    'late'      => 'badge-warning',
                                    'submitted' => 'badge-info',
                                    default     => 'badge-info',
                                };
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($a['teacher_name']) ?>
                                    <?php if (!empty($a['teacher_subject'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($a['teacher_subject']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $a['deadline']
                                        ? date('M j, Y', strtotime($a['deadline']))
                                        : '—' ?>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($a['submitted_at'])) ?></td>
                                <td><span class="badge <?= $badge ?>"><?= ucfirst($a['sub_status']) ?></span></td>
                                <td>
                                    <?php if ($a['sub_file']): ?>
                                        <a href="/school_tracking/<?= htmlspecialchars($a['sub_file']) ?>"
                                           target="_blank"><i class="fas fa-download"></i> View</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
