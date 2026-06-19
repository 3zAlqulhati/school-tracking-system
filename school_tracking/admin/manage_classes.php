<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('admin');

$pdo = require __DIR__ . '/../config/db.php';

$success = '';
$error   = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo->prepare('DELETE FROM classes WHERE class_id = ?')->execute([(int)$_GET['delete']]);
        header('Location: manage_classes.php?msg=deleted');
        exit;
    } catch (PDOException $e) {
        $error = 'Delete failed: ' . $e->getMessage();
    }
}

if (isset($_GET['msg'])) {
    $success = match($_GET['msg']) {
        'deleted' => 'Class deleted successfully.',
        'saved'   => 'Class updated successfully.',
        'added'   => 'Class added successfully.',
        default   => '',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_class') {
    $className    = trim($_POST['class_name']    ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $schedule     = trim($_POST['schedule']      ?? '') ?: null;
    $capacity     = ($_POST['capacity'] ?? '') !== '' ? (int)$_POST['capacity'] : null;
    $teacherIds   = array_filter(array_map('intval', (array)($_POST['teacher_ids'] ?? [])));
    $studentIds   = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));

    if ($className === '' || $academicYear === '') {
        $error = 'Class name and academic year are required.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO classes (class_name, academic_year, schedule, capacity) VALUES (?, ?, ?, ?)'
            )->execute([$className, $academicYear, $schedule, $capacity]);
            $newClassId = (int) $pdo->lastInsertId();

            if (!empty($teacherIds)) {
                $stmtCT = $pdo->prepare('INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES (?, ?)');
                foreach ($teacherIds as $tid) { $stmtCT->execute([$newClassId, $tid]); }
            }
            if (!empty($studentIds)) {
                $stmtSC = $pdo->prepare('INSERT IGNORE INTO student_classes (student_id, class_id) VALUES (?, ?)');
                foreach ($studentIds as $sid) { $stmtSC->execute([$sid, $newClassId]); }
            }
            $pdo->commit();
            header('Location: manage_classes.php?msg=added');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not add class: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_class') {
    $editId       = (int)($_POST['class_id']      ?? 0);
    $className    = trim($_POST['class_name']    ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $schedule     = trim($_POST['schedule']      ?? '') ?: null;
    $capacity     = ($_POST['capacity'] ?? '') !== '' ? (int)$_POST['capacity'] : null;
    $teacherIds   = array_filter(array_map('intval', (array)($_POST['teacher_ids'] ?? [])));
    $studentIds   = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));

    if ($editId === 0 || $className === '' || $academicYear === '') {
        $error = 'Class name and academic year are required.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE classes SET class_name=?, academic_year=?, schedule=?, capacity=? WHERE class_id=?'
            )->execute([$className, $academicYear, $schedule, $capacity, $editId]);

            $pdo->prepare('DELETE FROM class_teachers WHERE class_id = ?')->execute([$editId]);
            if (!empty($teacherIds)) {
                $stmtCT = $pdo->prepare('INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)');
                foreach ($teacherIds as $tid) { $stmtCT->execute([$editId, $tid]); }
            }

            $pdo->prepare('DELETE FROM student_classes WHERE class_id = ?')->execute([$editId]);
            if (!empty($studentIds)) {
                $stmtSC = $pdo->prepare('INSERT IGNORE INTO student_classes (student_id, class_id) VALUES (?, ?)');
                foreach ($studentIds as $sid) { $stmtSC->execute([$sid, $editId]); }
            }

            $pdo->commit();
            header('Location: manage_classes.php?saved=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not update class: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['saved'])) $success = 'Class updated successfully.';

$editClass      = null;
$editTeacherIds = [];
$editStudentIds = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE class_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editClass = $stmt->fetch();
    if ($editClass) {
        $stmtT = $pdo->prepare('SELECT teacher_id FROM class_teachers WHERE class_id = ?');
        $stmtT->execute([$editClass['class_id']]);
        $editTeacherIds = array_column($stmtT->fetchAll(), 'teacher_id');

        $stmtS = $pdo->prepare('SELECT student_id FROM student_classes WHERE class_id = ?');
        $stmtS->execute([$editClass['class_id']]);
        $editStudentIds = array_column($stmtS->fetchAll(), 'student_id');
    } else {
        $error = 'Class not found.';
        $editClass = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    $editTeacherIds = isset($_POST['teacher_ids']) ? array_map('intval', (array)$_POST['teacher_ids']) : [];
    $editStudentIds = isset($_POST['student_ids']) ? array_map('intval', (array)$_POST['student_ids']) : [];
}

$allTeachers = $pdo->query(
    'SELECT t.teacher_id, u.full_name, t.subject_specialty
     FROM teachers t JOIN users u ON u.user_id = t.user_id ORDER BY u.full_name'
)->fetchAll();

$allStudents = $pdo->query(
    'SELECT s.student_id, u.full_name FROM students s
     JOIN users u ON u.user_id = s.user_id ORDER BY u.full_name'
)->fetchAll();

$classes = $pdo->query(
    'SELECT c.class_id, c.class_name, c.academic_year, c.schedule, c.capacity,
            COUNT(DISTINCT sc.student_id) AS enrolled,
            GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ", ") AS teacher_names
     FROM classes c
     LEFT JOIN student_classes sc ON sc.class_id = c.class_id
     LEFT JOIN class_teachers ct  ON ct.class_id  = c.class_id
     LEFT JOIN teachers t ON t.teacher_id = ct.teacher_id
     LEFT JOIN users u ON u.user_id = t.user_id
     GROUP BY c.class_id ORDER BY c.academic_year DESC, c.class_name ASC'
)->fetchAll();

$classTeacherMap = [];
foreach ($pdo->query(
    'SELECT ct.class_id, u.full_name, t.subject_specialty
     FROM class_teachers ct
     JOIN teachers t ON t.teacher_id = ct.teacher_id
     JOIN users    u ON u.user_id    = t.user_id ORDER BY u.full_name'
)->fetchAll() as $row) {
    $classTeacherMap[$row['class_id']][] = ['name' => $row['full_name'], 'subject' => $row['subject_specialty'] ?? ''];
}

$classStudentMap = [];
foreach ($pdo->query(
    'SELECT sc.class_id, u.full_name, s.gender, s.date_of_birth
     FROM student_classes sc
     JOIN students s ON s.student_id = sc.student_id
     JOIN users    u ON u.user_id    = s.user_id ORDER BY u.full_name'
)->fetchAll() as $row) {
    $classStudentMap[$row['class_id']][] = ['name' => $row['full_name'], 'gender' => $row['gender'] ?? '', 'dob' => $row['date_of_birth'] ?? ''];
}

$classesDetailJson = [];
foreach ($classes as $cls) {
    $classesDetailJson[$cls['class_id']] = [
        'class_id'      => $cls['class_id'],
        'class_name'    => $cls['class_name'],
        'academic_year' => $cls['academic_year'],
        'schedule'      => $cls['schedule']  ?? '',
        'capacity'      => $cls['capacity'],
        'enrolled'      => $cls['enrolled'],
        'teachers'      => $classTeacherMap[$cls['class_id']] ?? [],
        'students'      => $classStudentMap[$cls['class_id']] ?? [],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Main Menu</span>
            <a href="/school_tracking/admin/index.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard</a>
            <a href="/school_tracking/admin/manage_users.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Manage Users</a>
            <a href="/school_tracking/admin/manage_classes.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-school"></i></span> Manage Classes</a>
            <a href="/school_tracking/admin/reports.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
            <a href="/school_tracking/admin/notifications.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-bell"></i></span> Send Notifications</a>
            <a href="/school_tracking/admin/inbox.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-inbox"></i></span> Inbox</a>
        </div>
        <hr class="sidebar-divider">
        <div class="sidebar-section">
            <span class="sidebar-label">Account</span>
            <a href="/school_tracking/logout.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-sign-out-alt"></i></span> Logout</a>
        </div>
    </aside>

    <div class="main-content">

        <h1 class="page-title"><span class="page-title-icon"><i class="fas fa-school"></i></span> Manage Classes</h1>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle alert-icon"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle alert-icon"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <span class="card-header-title">
                    <i class="fas <?= $editClass ? 'fa-edit' : 'fa-plus-circle' ?>" style="color:var(--primary);"></i>
                    <?= $editClass ? 'Edit Class' : 'Add New Class' ?>
                </span>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $editClass ? 'edit_class' : 'add_class' ?>">
                <?php if ($editClass): ?>
                    <input type="hidden" name="class_id" value="<?= $editClass['class_id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="class_name">Class Name <span class="text-danger">*</span></label>
                        <input type="text" id="class_name" name="class_name"
                               value="<?= htmlspecialchars($editClass['class_name'] ?? '') ?>"
                               placeholder="e.g. Grade 10 — Section A" required>
                    </div>
                    <div class="form-group">
                        <label for="academic_year">Academic Year <span class="text-danger">*</span></label>
                        <input type="text" id="academic_year" name="academic_year"
                               value="<?= htmlspecialchars($editClass['academic_year'] ?? '') ?>"
                               placeholder="e.g. 2025-2026" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity"
                               value="<?= htmlspecialchars($editClass['capacity'] ?? '') ?>"
                               placeholder="e.g. 40" min="1" max="200">
                    </div>
                    <div class="form-group">
                        <label for="schedule">Schedule</label>
                        <textarea id="schedule" name="schedule" rows="2"
                                  placeholder="e.g. Mon/Wed/Fri 8:00–10:00 AM"><?= htmlspecialchars($editClass['schedule'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign Teacher(s)</label>
                    <?php if (empty($allTeachers)): ?>
                        <div style="padding:0.75rem;border:1px solid #cdd5e0;border-radius:8px;background:#fafbfc;">
                            <span class="text-muted" style="font-size:0.85rem;">No teachers found. Add teachers in Manage Users first.</span>
                        </div>
                    <?php else: ?>
                    <div style="border:1px solid #cdd5e0;border-radius:8px;overflow:hidden;">
                        <div style="padding:0.5rem 0.75rem;background:#f0f2f5;border-bottom:1px solid #cdd5e0;display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.82rem;color:#6c757d;"><?= count($allTeachers) ?> teacher(s) available</span>
                            <div style="display:flex;gap:0.5rem;">
                                <button type="button" onclick="toggleAllTeachers(true)" style="font-size:0.75rem;padding:0.25rem 0.6rem;border:none;background:#1a2b4a;color:#fff;border-radius:4px;cursor:pointer;">Select All</button>
                                <button type="button" onclick="toggleAllTeachers(false)" style="font-size:0.75rem;padding:0.25rem 0.6rem;border:none;background:#6c757d;color:#fff;border-radius:4px;cursor:pointer;">Clear All</button>
                            </div>
                        </div>
                        <div style="max-height:180px;overflow-y:auto;padding:0.5rem 0;">
                            <?php foreach ($allTeachers as $t):
                                $isChecked = in_array((int)$t['teacher_id'], $editTeacherIds); ?>
                            <label style="display:flex;align-items:center;gap:0.6rem;padding:0.45rem 1rem;cursor:pointer;"
                                   onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="teacher_ids[]" value="<?= $t['teacher_id'] ?>"
                                       class="teacher-checkbox" <?= $isChecked ? 'checked' : '' ?>>
                                <span>
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?= htmlspecialchars($t['full_name']) ?>
                                    <?php if ($t['subject_specialty']): ?>
                                        <span style="color:#6c757d;font-size:0.8rem;">— <?= htmlspecialchars($t['subject_specialty']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <span class="form-hint">Tick the checkbox(es) to assign teachers to this class.</span>
                </div>

                <div class="form-group">
                    <label>Assign Students</label>
                    <?php if (empty($allStudents)): ?>
                        <div style="padding:0.75rem;border:1px solid #cdd5e0;border-radius:8px;background:#fafbfc;">
                            <span class="text-muted" style="font-size:0.85rem;">No students found.</span>
                        </div>
                    <?php else: ?>
                    <div style="border:1px solid #cdd5e0;border-radius:8px;overflow:hidden;">
                        <div style="padding:0.5rem 0.75rem;background:#f0f2f5;border-bottom:1px solid #cdd5e0;display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.82rem;color:#6c757d;"><?= count($allStudents) ?> student(s)</span>
                            <div style="display:flex;gap:0.5rem;">
                                <button type="button" onclick="toggleAllStudents(true)" style="font-size:0.75rem;padding:0.25rem 0.6rem;border:none;background:#1a2b4a;color:#fff;border-radius:4px;cursor:pointer;">Select All</button>
                                <button type="button" onclick="toggleAllStudents(false)" style="font-size:0.75rem;padding:0.25rem 0.6rem;border:none;background:#6c757d;color:#fff;border-radius:4px;cursor:pointer;">Clear All</button>
                            </div>
                        </div>
                        <div style="max-height:220px;overflow-y:auto;padding:0.5rem 0;">
                            <?php foreach ($allStudents as $stu): ?>
                            <label style="display:flex;align-items:center;gap:0.6rem;padding:0.45rem 1rem;cursor:pointer;"
                                   onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="student_ids[]" value="<?= $stu['student_id'] ?>"
                                       class="student-checkbox"
                                       <?= in_array((int)$stu['student_id'], $editStudentIds) ? 'checked' : '' ?>>
                                <span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($stu['full_name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <span class="form-hint">Students can be enrolled in multiple classes.</span>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <?= $editClass ? '<i class="fas fa-check"></i> Save Changes' : '<i class="fas fa-plus"></i> Add Class' ?>
                    </button>
                    <?php if ($editClass): ?>
                        <a href="manage_classes.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php else: ?>
                        <button type="reset" class="btn-secondary">Reset</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="fas fa-school"></i> All Classes</span>
                <span class="text-muted" style="font-weight:400;font-size:0.82rem;">(<?= count($classes) ?> total)</span>
            </div>

            <?php if (empty($classes)): ?>
                <p class="text-muted text-center mt-3 mb-3">No classes found. Add one above.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class Name</th><th>Year</th><th>Teacher(s)</th>
                            <th>Enrolled</th><th>Capacity</th><th>Schedule</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $cls):
                            $atCapacity = $cls['capacity'] !== null && (int)$cls['enrolled'] >= (int)$cls['capacity']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cls['class_name']) ?></strong></td>
                            <td><?= htmlspecialchars($cls['academic_year']) ?></td>
                            <td><?= $cls['teacher_names'] ? htmlspecialchars($cls['teacher_names']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center">
                                <span class="badge <?= $atCapacity ? 'badge-danger' : 'badge-info' ?>">
                                    <?= $cls['enrolled'] ?><?= $cls['capacity'] !== null ? ' / ' . $cls['capacity'] : '' ?>
                                </span>
                            </td>
                            <td class="text-center"><?= $cls['capacity'] ?? '<span class="text-muted">—</span>' ?></td>
                            <td><?= $cls['schedule'] ? htmlspecialchars($cls['schedule']) : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <div class="d-flex gap-1" style="flex-wrap:wrap;">
                                    <button type="button" class="btn-secondary btn-sm" onclick="showClassDetails(<?= $cls['class_id'] ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    <a href="?edit=<?= $cls['class_id'] ?>" class="btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?= $cls['class_id'] ?>" class="btn-danger btn-sm"
                                       onclick="return confirmDelete('<?= htmlspecialchars(addslashes($cls['class_name'])) ?>', <?= (int)$cls['enrolled'] ?>)">
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

    </div>
</div>

<div id="classDetailModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:100%;max-width:580px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;flex-shrink:0;background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;">
            <span style="font-size:1.05rem;font-weight:700;"><i class="fas fa-school" style="margin-right:.5rem;"></i><span id="classDetailTitle">Class Details</span></span>
            <button onclick="closeClassDetails()" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div id="classDetailBody" style="padding:1.3rem 1.5rem;overflow-y:auto;flex:1;"></div>
        <div style="padding:.9rem 1.5rem;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:.5rem;flex-shrink:0;">
            <span id="classDetailEditLink"></span>
            <button onclick="closeClassDetails()" class="btn-secondary btn-sm"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<script>
const CLASSES_DATA = <?= json_encode(array_values($classesDetailJson), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
const CLASSES_MAP  = {};
CLASSES_DATA.forEach(c => { CLASSES_MAP[c.class_id] = c; });

function showClassDetails(cid) {
    const c = CLASSES_MAP[cid];
    if (!c) return;
    document.getElementById('classDetailTitle').textContent = c.class_name;
    const teachersHtml = c.teachers.length === 0
        ? '<span style="color:#aaa;font-size:.88rem;">No teachers assigned</span>'
        : c.teachers.map(t =>
            `<div style="display:flex;align-items:center;gap:.5rem;padding:.3rem 0;">
                <span style="width:28px;height:28px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-chalkboard-teacher" style="color:#fff;font-size:.7rem;"></i></span>
                <div><strong>${escHtml(t.name)}</strong>${t.subject ? `<span style="color:#888;font-size:.82rem;margin-left:.3rem;">(${escHtml(t.subject)})</span>` : ''}</div>
             </div>`).join('');
    const studentsHtml = c.students.length === 0
        ? '<span style="color:#aaa;font-size:.88rem;">No students enrolled</span>'
        : c.students.map((s, i) =>
            `<div style="display:flex;align-items:center;gap:.5rem;padding:.28rem 0;border-bottom:${i < c.students.length-1 ? '1px solid #f5f5f5' : 'none'}">
                <span style="font-size:.75rem;color:#aaa;width:22px;text-align:right;">${i+1}.</span>
                <i class="fas fa-user-graduate" style="color:var(--primary);font-size:.8rem;"></i>
                <span>${escHtml(s.name)}</span>
                ${s.gender ? `<span style="font-size:.78rem;color:#aaa;">(${escHtml(s.gender)})</span>` : ''}
             </div>`).join('');
    document.getElementById('classDetailBody').innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:.88rem;margin-bottom:1rem;">
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:.5rem .3rem;color:#6c757d;width:36%;"><i class="fas fa-graduation-cap" style="color:var(--primary);width:16px;"></i> Academic Year</td>
                <td style="padding:.5rem .3rem;"><strong>${escHtml(c.academic_year)}</strong></td>
            </tr>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:.5rem .3rem;color:#6c757d;"><i class="fas fa-users" style="color:var(--primary);width:16px;"></i> Enrolled / Capacity</td>
                <td style="padding:.5rem .3rem;"><strong>${c.enrolled}</strong>${c.capacity ? ' / ' + c.capacity : ' <span style="color:#aaa;">(no limit)</span>'}</td>
            </tr>
            <tr>
                <td style="padding:.5rem .3rem;color:#6c757d;"><i class="fas fa-calendar-alt" style="color:var(--primary);width:16px;"></i> Schedule</td>
                <td style="padding:.5rem .3rem;">${c.schedule ? escHtml(c.schedule) : '<span style="color:#aaa;">—</span>'}</td>
            </tr>
        </table>
        <div style="margin-bottom:1rem;">
            <div style="font-size:.75rem;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">
                <i class="fas fa-chalkboard-teacher" style="color:var(--primary);"></i> Teachers (${c.teachers.length})</div>
            <div style="padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">${teachersHtml}</div>
        </div>
        <div>
            <div style="font-size:.75rem;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">
                <i class="fas fa-user-graduate" style="color:var(--primary);"></i> Students (${c.students.length})</div>
            <div style="padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;max-height:200px;overflow-y:auto;">${studentsHtml}</div>
        </div>`;
    document.getElementById('classDetailEditLink').innerHTML = `<a href="?edit=${c.class_id}" class="btn-primary btn-sm"><i class="fas fa-edit"></i> Edit Class</a>`;
    document.getElementById('classDetailModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeClassDetails() {
    document.getElementById('classDetailModal').style.display = 'none';
    document.body.style.overflow = '';
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('classDetailModal').addEventListener('click', function(e) { if (e.target === this) closeClassDetails(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeClassDetails(); });
function toggleAllTeachers(c) { document.querySelectorAll('.teacher-checkbox').forEach(cb => cb.checked = c); }
function toggleAllStudents(c)  { document.querySelectorAll('.student-checkbox').forEach(cb  => cb.checked = c); }
function confirmDelete(name, enrolled) {
    let msg = 'Delete class "' + name + '"?';
    if (enrolled > 0) msg += '\n\nWarning: ' + enrolled + ' student(s) will lose their class assignment.';
    return confirm(msg + '\n\nThis cannot be undone.');
}
<?php if ($editClass): ?>
document.addEventListener('DOMContentLoaded', function () { document.querySelector('.card').scrollIntoView({ behavior: 'smooth' }); });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
