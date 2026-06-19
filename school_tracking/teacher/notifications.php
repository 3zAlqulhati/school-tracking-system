<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('teacher');

$pdo = require __DIR__ . '/../config/db.php';


$stmtT = $pdo->prepare('SELECT teacher_id FROM teachers WHERE user_id = ?');
$stmtT->execute([$_SESSION['user_id']]);
$teacher = $stmtT->fetch();
if (!$teacher) {
    die('<p style="padding:2rem;font-family:sans-serif;">Teacher profile not found.</p>');
}
$teacherId  = (int) $teacher['teacher_id'];
$senderUid  = (int) $_SESSION['user_id'];

$success = '';
$error   = '';




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




$studentsByClass = [];
if (!empty($teacherClasses)) {
    $cids = array_column($teacherClasses, 'class_id');
    $ph   = implode(',', array_fill(0, count($cids), '?'));
    $stmtStu = $pdo->prepare(
        "SELECT s.student_id, u.user_id, u.full_name, c.class_name, sc.class_id
         FROM students s
         JOIN users          u  ON u.user_id  = s.user_id
         JOIN student_classes sc ON sc.student_id = s.student_id
         JOIN classes         c  ON c.class_id    = sc.class_id
         WHERE sc.class_id IN ({$ph})
         ORDER BY c.class_name, u.full_name"
    );
    $stmtStu->execute($cids);
    foreach ($stmtStu->fetchAll() as $stu) {
        $studentsByClass[$stu['class_id']][] = $stu;
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_notification') {
    $title     = trim($_POST['title']          ?? '');
    $message   = trim($_POST['message']        ?? '');
    $recepType = $_POST['recipient_type']       ?? '';
    $classId   = isset($_POST['class_id']) && is_numeric($_POST['class_id'])
               ? (int)$_POST['class_id'] : 0;
    $studentUid = isset($_POST['student_user_id']) && is_numeric($_POST['student_user_id'])
               ? (int)$_POST['student_user_id'] : 0;

    $validTypes = ['class_students', 'class_parents', 'specific_student', 'specific_parent'];

    if ($title === '' || $message === '') {
        $error = 'Title and message are required.';
    } elseif (!in_array($recepType, $validTypes, true)) {
        $error = 'Please select a valid recipient type.';
    } elseif (in_array($recepType, ['class_students', 'class_parents'], true) && $classId === 0) {
        $error = 'Please select a class for this recipient type.';
    } elseif (in_array($recepType, ['specific_student', 'specific_parent'], true) && $studentUid === 0) {
        $error = 'Please select a student.';
    } else {

        $recipientIds = [];

        if ($recepType === 'class_students') {
            $stmtR = $pdo->prepare(
                'SELECT u.user_id
                 FROM students s
                 JOIN users          u  ON u.user_id = s.user_id
                 JOIN student_classes sc ON sc.student_id = s.student_id
                 WHERE sc.class_id = ?'
            );
            $stmtR->execute([$classId]);
            $recipientIds = array_column($stmtR->fetchAll(), 'user_id');

        } elseif ($recepType === 'class_parents') {
            $stmtR = $pdo->prepare(
                'SELECT DISTINCT u.user_id
                 FROM parents p
                 JOIN students s        ON s.student_id = p.student_id
                 JOIN student_classes sc ON sc.student_id = s.student_id
                 JOIN users    u        ON u.user_id     = p.user_id
                 WHERE sc.class_id = ?'
            );
            $stmtR->execute([$classId]);
            $recipientIds = array_column($stmtR->fetchAll(), 'user_id');

        } elseif ($recepType === 'specific_student') {
            $recipientIds = [$studentUid];

        } elseif ($recepType === 'specific_parent') {
            $stmtR = $pdo->prepare(
                'SELECT DISTINCT u.user_id
                 FROM parents p
                 JOIN students s ON s.student_id = p.student_id
                 JOIN users    u ON u.user_id = p.user_id
                 WHERE s.user_id = ?'
            );
            $stmtR->execute([$studentUid]);
            $recipientIds = array_column($stmtR->fetchAll(), 'user_id');
        }

        if (empty($recipientIds)) {
            $error = 'No recipients found for the selected option.';
        } else {
            try {
                $stmtIns = $pdo->prepare(
                    'INSERT INTO notifications (sender_id, recipient_id, title, message)
                     VALUES (?, ?, ?, ?)'
                );
                $pdo->beginTransaction();
                foreach ($recipientIds as $rid) {
                    $stmtIns->execute([$senderUid, $rid, $title, $message]);
                }
                $pdo->commit();

                $count   = count($recipientIds);
                $label   = match ($recepType) {
                    'class_students'   => "{$count} student(s)",
                    'class_parents'    => "{$count} parent(s)",
                    'specific_student' => '1 student',
                    'specific_parent'  => "{$count} parent(s) of selected student",
                    default            => "{$count} recipient(s)",
                };
                $success = "Notification sent to {$label} successfully.";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Could not send notification: ' . $e->getMessage();
            }
        }
    }
}





$history = $pdo->prepare(
    'SELECT n.title,
            n.message,
            MIN(n.created_at)     AS sent_at,
            COUNT(*)              AS recipient_count,
            MIN(n.recipient_id)   AS sample_recipient_id,
            MIN(u.full_name)      AS sample_name,
            SUM(n.recipient_id IS NULL) AS is_broadcast
     FROM notifications n
     LEFT JOIN users u ON u.user_id = n.recipient_id
     WHERE n.sender_id = ?
     GROUP BY n.title, n.message,
              DATE(n.created_at),
              HOUR(n.created_at),
              MINUTE(n.created_at)
     ORDER BY sent_at DESC
     LIMIT 30'
);
$history->execute([$senderUid]);
$sentHistory = $history->fetchAll();

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
            <a href="/school_tracking/teacher/assignments.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> Assignments
            </a>
            <a href="/school_tracking/teacher/notifications.php" class="sidebar-link active">
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
            <span class="page-title-icon"><i class="fas fa-bell"></i></span>
            Send Notifications
        </h1>

        <!-- Flash Messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ================================================
             COMPOSE FORM
        ================================================= -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-pen"></i> Compose Notification</div>

            <form method="POST" action="" id="notifForm">
                <input type="hidden" name="action" value="send_notification">

                <!-- Recipient type -->
                <div class="form-group">
                    <label for="recipient_type">Send To <span class="text-danger">*</span></label>
                    <select id="recipient_type" name="recipient_type" required
                            onchange="handleRecipientType(this.value)">
                        <option value="">— Select Recipients —</option>
                        <option value="class_students"
                            <?= (($_POST['recipient_type'] ?? '') === 'class_students')  ? 'selected' : '' ?>>
                            All Students in a Class
                        </option>
                        <option value="class_parents"
                            <?= (($_POST['recipient_type'] ?? '') === 'class_parents')   ? 'selected' : '' ?>>
                            All Parents of a Class
                        </option>
                        <option value="specific_student"
                            <?= (($_POST['recipient_type'] ?? '') === 'specific_student') ? 'selected' : '' ?>>
                            Specific Student
                        </option>
                        <option value="specific_parent"
                            <?= (($_POST['recipient_type'] ?? '') === 'specific_parent') ? 'selected' : '' ?>>
                            Specific Student's Parent
                        </option>
                    </select>
                </div>

                <!-- Class selector (all types that need a class) -->
                <div class="form-group" id="field_class" style="display:none;">
                    <label for="class_id">Select Class <span class="text-danger">*</span></label>
                    <select id="class_id" name="class_id" onchange="filterStudentsByClass(this.value)">
                        <option value="">— Select Class —</option>
                        <?php foreach ($teacherClasses as $cls): ?>
                            <option value="<?= $cls['class_id'] ?>"
                                <?= (($_POST['class_id'] ?? '') == $cls['class_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name']) ?>
                                (<?= htmlspecialchars($cls['academic_year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Student selector — populated dynamically after class is chosen -->
                <div class="form-group" id="field_student" style="display:none;">
                    <label for="student_user_id">Select Student <span class="text-danger">*</span></label>
                    <select id="student_user_id" name="student_user_id">
                        <option value="">— Select Class First —</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label for="title">Title <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="e.g. Reminder: Assignment due tomorrow"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="message">Message <span class="text-danger">*</span></label>
                    <textarea id="message" name="message" rows="4" required
                              placeholder="Write your message here..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-bell"></i> Send Notification</button>
                    <button type="reset" class="btn-secondary"
                            onclick="handleRecipientType('')">Reset</button>
                </div>
            </form>
        </div>

        <!-- ================================================
             SENT HISTORY
        ================================================= -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-history"></i> Sent Notifications</span>
                <span class="text-muted" style="font-weight:400; font-size:0.82rem; margin-left:0.5rem;">
                    (last 30 batches)
                </span>
            </div>

            <?php if (empty($sentHistory)): ?>
                <p class="text-muted text-center mt-3 mb-3">No notifications sent yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Sent To</th>
                            <th>Recipients</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sentHistory as $i => $n): ?>
                        <?php
                            if ((int)$n['recipient_count'] === 1) {
                                $sentLabel = htmlspecialchars($n['sample_name'] ?? 'Unknown');
                            } else {
                                $sentLabel = htmlspecialchars($n['sample_name'] ?? 'Unknown')
                                           . ' <span class="text-muted">+ '
                                           . ((int)$n['recipient_count'] - 1) . ' more</span>';
                            }
                        ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Title">
                                <strong><?= htmlspecialchars($n['title']) ?></strong>
                            </td>
                            <td data-label="Message">
                                <span title="<?= htmlspecialchars($n['message']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($n['message'], 0, 70, '…')) ?>
                                </span>
                            </td>
                            <td data-label="Sent To"><?= $sentLabel ?></td>
                            <td data-label="Recipients" class="text-center">
                                <span class="badge badge-info"><?= $n['recipient_count'] ?></span>
                            </td>
                            <td data-label="Date">
                                <?= date('M j, Y g:i A', strtotime($n['sent_at'])) ?>
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

<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;

const NEEDS_CLASS   = ['class_students', 'class_parents', 'specific_student', 'specific_parent'];
const NEEDS_STUDENT = ['specific_student', 'specific_parent'];

function handleRecipientType(type) {
    document.getElementById('field_class').style.display   = NEEDS_CLASS.includes(type)   ? 'block' : 'none';
    document.getElementById('field_student').style.display = NEEDS_STUDENT.includes(type) ? 'block' : 'none';


    document.getElementById('student_user_id').innerHTML = '<option value="">— Select Class First —</option>';
    document.getElementById('class_id').value = '';
}

function filterStudentsByClass(classId) {
    const sel      = document.getElementById('student_user_id');
    const students = studentsByClass[classId] || [];
    sel.innerHTML  = students.length
        ? '<option value="">— Select Student —</option>'
        : '<option value="">No students in this class</option>';
    students.forEach(function(s) {
        const opt       = document.createElement('option');
        opt.value       = s.user_id;
        opt.textContent = s.full_name;
        sel.appendChild(opt);
    });
}


(function () {
    const sel = document.getElementById('recipient_type');
    if (sel && sel.value) {
        handleRecipientType(sel.value);

        const classId = document.getElementById('class_id').value;
        if (classId) filterStudentsByClass(classId);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
