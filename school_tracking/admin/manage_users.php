<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('admin');

$pdo = require __DIR__ . '/../config/db.php';

$success = '';
$error   = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId === (int) $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            $pdo->prepare('DELETE FROM users WHERE user_id = ?')
                ->execute([$deleteId]);
            $success = 'User deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Delete failed: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {

    $fullName  = trim($_POST['full_name']  ?? '');
    $username  = trim($_POST['username']   ?? '');
    $email     = trim($_POST['email']      ?? '') ?: null;
    $phone     = trim($_POST['phone']      ?? '') ?: null;
    $password  = $_POST['password']         ?? '';
    $role      = $_POST['role']             ?? '';
    $classId    = $_POST['class_id']         ?? null;
    $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids'])
                  ? array_filter(array_map('intval', $_POST['student_ids']))
                  : [];
    $subject    = trim($_POST['subject_specialty'] ?? '') ?: null;
    $relation   = trim($_POST['relationship']       ?? '') ?: null;

    $validRoles = ['student', 'teacher', 'parent', 'admin'];

    if ($fullName === '' || $username === '' || $password === '' || !in_array($role, $validRoles, true)) {
        $error = 'Full name, username, password, and role are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($role === 'student' && empty($classId)) {
        $error = 'Please select a class for the student.';
    } elseif ($role === 'parent' && empty($studentIds)) {
        $error = 'Please select at least one student to link to this parent.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username '{$username}' is already taken.";
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password, role, full_name, email, phone)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$username, $hashed, $role, $fullName, $email, $phone]);
            $newUserId = (int) $pdo->lastInsertId();

            match ($role) {
                'student' => (function() use ($pdo, $newUserId, $classId) {
                    $pdo->prepare(
                        'INSERT INTO students (user_id, class_id) VALUES (?, ?)'
                    )->execute([$newUserId, $classId ?: null]);
                    if ($classId) {
                        $newStudentId = (int) $pdo->lastInsertId();
                        $pdo->prepare(
                            'INSERT IGNORE INTO student_classes (student_id, class_id) VALUES (?, ?)'
                        )->execute([$newStudentId, (int) $classId]);
                    }
                })(),

                'teacher' => $pdo->prepare(
                    'INSERT INTO teachers (user_id, subject_specialty) VALUES (?, ?)'
                )->execute([$newUserId, $subject]),

                'parent'  => (function() use ($pdo, $newUserId, $studentIds, $relation) {
                    $stmtP = $pdo->prepare(
                        'INSERT IGNORE INTO parents (user_id, student_id, relationship) VALUES (?, ?, ?)'
                    );
                    foreach ($studentIds as $sid) {
                        $stmtP->execute([$newUserId, $sid, $relation]);
                    }
                })(),

                default   => null,   // admin — no role-specific table
            };

            $pdo->commit();
            $success = "User '{$username}' added successfully.";

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $error = 'That username or email is already registered.';
            } else {
                $error = 'Could not add user: ' . $e->getMessage();
            }
        }
    }
}

$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editUid  = (int) $_GET['edit'];
    $stmtEdit = $pdo->prepare(
        'SELECT u.user_id, u.username, u.full_name, u.email, u.phone, u.role,
                s.class_id, s.student_id AS student_row_id,
                t.subject_specialty,
                p.student_id AS parent_student_id, p.relationship,
                GROUP_CONCAT(p.student_id) AS parent_all_student_ids
         FROM users u
         LEFT JOIN students s ON s.user_id = u.user_id
         LEFT JOIN teachers t ON t.user_id = u.user_id
         LEFT JOIN parents  p ON p.user_id = u.user_id
         WHERE u.user_id = ?'
    );
    $stmtEdit->execute([$editUid]);
    $editUser = $stmtEdit->fetch();
    if (!$editUser) { $error = 'User not found.'; $editUser = null; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {
    $editUid  = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '') ?: null;
    $phone    = trim($_POST['phone']     ?? '') ?: null;
    $newPwd   = $_POST['new_password']   ?? '';
    $classId    = $_POST['class_id']       ?? null;
    $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids'])
                  ? array_filter(array_map('intval', $_POST['student_ids']))
                  : [];
    $subject    = trim($_POST['subject_specialty'] ?? '') ?: null;
    $relation   = trim($_POST['relationship']       ?? '') ?: null;

    if ($editUid === 0 || $fullName === '') {
        $error = 'User ID and full name are required.';
    } elseif ($newPwd !== '' && strlen($newPwd) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($newPwd !== '') {
                $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE user_id=?'
                )->execute([$fullName, $email, $phone, $hashed, $editUid]);
            } else {
                $pdo->prepare(
                    'UPDATE users SET full_name=?, email=?, phone=? WHERE user_id=?'
                )->execute([$fullName, $email, $phone, $editUid]);
            }

            $role = $pdo->prepare('SELECT role FROM users WHERE user_id=?');
            $role->execute([$editUid]);
            $userRole = $role->fetchColumn();

            if ($userRole === 'student') {
                $pdo->prepare('UPDATE students SET class_id=? WHERE user_id=?')
                    ->execute([$classId ?: null, $editUid]);
                if ($classId) {
                    $stmtSid = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ?');
                    $stmtSid->execute([$editUid]);
                    $sid = $stmtSid->fetchColumn();
                    if ($sid) {
                        $pdo->prepare(
                            'INSERT IGNORE INTO student_classes (student_id, class_id) VALUES (?, ?)'
                        )->execute([(int) $sid, (int) $classId]);
                    }
                }
            } elseif ($userRole === 'teacher') {
                $pdo->prepare('UPDATE teachers SET subject_specialty=? WHERE user_id=?')
                    ->execute([$subject, $editUid]);
            } elseif ($userRole === 'parent') {
                $pdo->prepare('DELETE FROM parents WHERE user_id = ?')->execute([$editUid]);
                $stmtP = $pdo->prepare(
                    'INSERT IGNORE INTO parents (user_id, student_id, relationship) VALUES (?, ?, ?)'
                );
                foreach ($studentIds as $sid) {
                    $stmtP->execute([$editUid, $sid, $relation]);
                }
            }

            $pdo->commit();
            header('Location: manage_users.php?updated=1');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not update user: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['updated'])) $success = 'User updated successfully.';



$filterRole = isset($_GET['filter_role']) && in_array($_GET['filter_role'], ['admin','teacher','student','parent'], true)
            ? $_GET['filter_role'] : '';

if ($filterRole !== '') {
    $stmtUsers = $pdo->prepare(
        'SELECT user_id, full_name, username, email, phone, role, created_at
         FROM users WHERE role = ? ORDER BY created_at DESC'
    );
    $stmtUsers->execute([$filterRole]);
    $users = $stmtUsers->fetchAll();
} else {
    $users = $pdo->query(
        'SELECT user_id, full_name, username, email, phone, role, created_at
         FROM users ORDER BY created_at DESC'
    )->fetchAll();
}

$classes = $pdo->query(
    'SELECT class_id, class_name, academic_year FROM classes ORDER BY class_name'
)->fetchAll();



if ($editUser && $editUser['role'] === 'parent') {
    $editingParentUid = (int) $editUser['user_id'];
    $stmtStu = $pdo->prepare(
        'SELECT s.student_id, u.full_name
         FROM students s
         JOIN users u ON u.user_id = s.user_id
         WHERE s.student_id NOT IN (
             SELECT student_id FROM parents WHERE user_id != ?
         )
         ORDER BY u.full_name'
    );
    $stmtStu->execute([$editingParentUid]);
    $students = $stmtStu->fetchAll();
} else {
    $stmtStu = $pdo->prepare(
        'SELECT s.student_id, u.full_name
         FROM students s
         JOIN users u ON u.user_id = s.user_id
         WHERE s.student_id NOT IN (
             SELECT student_id FROM parents
         )
         ORDER BY u.full_name'
    );
    $stmtStu->execute([]);
    $students = $stmtStu->fetchAll();
}

$extraDetails = [];
$userIds = array_column($users, 'user_id');
if (!empty($userIds)) {
    $ph = implode(',', array_fill(0, count($userIds), '?'));


    $stmtStuD = $pdo->prepare(
        "SELECT s.user_id, c.class_name, c.academic_year, s.date_of_birth, s.gender
         FROM students s LEFT JOIN classes c ON c.class_id = s.class_id
         WHERE s.user_id IN ({$ph})"
    );
    $stmtStuD->execute($userIds);
    foreach ($stmtStuD->fetchAll() as $row) {
        $extraDetails[$row['user_id']] = [
            'class_name'    => $row['class_name']    ?? '',
            'academic_year' => $row['academic_year'] ?? '',
            'dob'           => $row['date_of_birth'] ?? '',
            'gender'        => $row['gender']        ?? '',
        ];
    }


    $stmtTchD = $pdo->prepare(
        "SELECT user_id, subject_specialty FROM teachers WHERE user_id IN ({$ph})"
    );
    $stmtTchD->execute($userIds);
    foreach ($stmtTchD->fetchAll() as $row) {
        $extraDetails[$row['user_id']] = ['subject' => $row['subject_specialty'] ?? ''];
    }


    $stmtParD = $pdo->prepare(
        "SELECT p.user_id, u2.full_name AS child_name, p.relationship, c.class_name
         FROM parents p
         JOIN students s  ON s.student_id = p.student_id
         JOIN users   u2  ON u2.user_id   = s.user_id
         LEFT JOIN classes c ON c.class_id = s.class_id
         WHERE p.user_id IN ({$ph})"
    );
    $stmtParD->execute($userIds);
    $parentChildren = [];
    foreach ($stmtParD->fetchAll() as $row) {
        $parentChildren[$row['user_id']][] = [
            'name'         => $row['child_name'],
            'relationship' => $row['relationship'],
            'class'        => $row['class_name'] ?? '',
        ];
    }
    foreach ($parentChildren as $uid => $children) {
        $extraDetails[$uid] = ['children' => $children];
    }
}


$usersDetailJson = [];
foreach ($users as $u) {
    $usersDetailJson[$u['user_id']] = [
        'user_id'    => $u['user_id'],
        'full_name'  => $u['full_name'],
        'username'   => $u['username'],
        'email'      => $u['email']   ?? '',
        'phone'      => $u['phone']   ?? '',
        'role'       => $u['role'],
        'created_at' => $u['created_at'],
        'extra'      => $extraDetails[$u['user_id']] ?? [],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Main Menu</span>
            <a href="/school_tracking/admin/index.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/admin/manage_users.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="/school_tracking/admin/manage_classes.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-school"></i></span> Manage Classes
            </a>
            <a href="/school_tracking/admin/reports.php" class="sidebar-link">
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
            <span class="page-title-icon"><i class="fas fa-users"></i></span>
            Manage Users
        </h1>

        <!-- Flash Messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle alert-icon"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle alert-icon"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ADD / EDIT USER FORM -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-header-title">
                    <?= $editUser
                        ? '<i class="fas fa-user-edit" style="color:var(--primary);"></i> Edit User'
                        : '<i class="fas fa-user-plus" style="color:var(--primary);"></i> Add New User' ?>
                </span>
                <?php if ($editUser): ?>
                    <a href="manage_users.php" class="btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="" id="addUserForm">
                <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" name="full_name"
                               value="<?= htmlspecialchars($editUser['full_name'] ?? ($_POST['full_name'] ?? '')) ?>"
                               placeholder="e.g. Maria Santos" required>
                    </div>
                    <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="e.g. maria.santos" required>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($editUser['username']) ?>"
                               disabled style="background:#f8f9fa;color:#6c757d;">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($editUser['email'] ?? ($_POST['email'] ?? '')) ?>"
                               placeholder="email@school.edu">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone"
                               value="<?= htmlspecialchars($editUser['phone'] ?? ($_POST['phone'] ?? '')) ?>"
                               placeholder="+60 12 345 6789">
                    </div>
                </div>

                <div class="form-row">
                    <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label for="password">Password <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password"
                               placeholder="Minimum 6 characters" required>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="new_password">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                        <input type="password" id="new_password" name="new_password"
                               placeholder="Enter new password or leave blank">
                    </div>
                    <?php endif; ?>
                    <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label for="role">Role <span class="text-danger">*</span></label>
                        <select id="role" name="role" required onchange="handleRoleChange(this.value)">
                            <option value="">— Select Role —</option>
                            <?php foreach (['student','teacher','parent','admin'] as $r): ?>
                                <option value="<?= $r ?>"
                                    <?= (($_POST['role'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= ucfirst($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?= ucfirst($editUser['role']) ?>"
                               disabled style="background:#f8f9fa;color:#6c757d;">
                    </div>
                    <?php endif; ?>
                </div>

                <?php

                $showRole = $editUser ? $editUser['role'] : ($_POST['role'] ?? '');
                ?>

                <!-- Student: class selection -->
                <div class="form-row" id="field_class"
                     style="display:<?= $showRole === 'student' ? 'grid' : 'none' ?>;">
                    <div class="form-group">
                        <label for="class_id">Assign to Class <?= !$editUser ? '<span class="text-danger">*</span>' : '' ?></label>
                        <select id="class_id" name="class_id">
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $cls): ?>
                                <?php $selCls = $editUser
                                    ? ($editUser['class_id'] == $cls['class_id'])
                                    : (($_POST['class_id'] ?? '') == $cls['class_id']); ?>
                                <option value="<?= $cls['class_id'] ?>" <?= $selCls ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['class_name']) ?>
                                    (<?= htmlspecialchars($cls['academic_year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Teacher: subject specialty -->
                <div class="form-row" id="field_subject"
                     style="display:<?= $showRole === 'teacher' ? 'grid' : 'none' ?>;">
                    <div class="form-group">
                        <label for="subject_specialty">Subject Specialty</label>
                        <input type="text" id="subject_specialty" name="subject_specialty"
                               value="<?= htmlspecialchars($editUser['subject_specialty'] ?? ($_POST['subject_specialty'] ?? '')) ?>"
                               placeholder="e.g. Mathematics">
                    </div>
                </div>

                <!-- Parent: multi-student links + relationship -->
                <div id="field_parent"
                     style="display:<?= $showRole === 'parent' ? 'block' : 'none' ?>;margin:0 1.5rem 1rem;">
                    <div class="form-group">
                        <label>Linked Children <?= !$editUser ? '<span class="text-danger">*</span>' : '' ?>
                            <span style="font-weight:400;font-size:.82rem;color:#6c757d;">(select one or more)</span>
                        </label>
                        <?php
                        $editParentIds = [];
                        if ($editUser && !empty($editUser['parent_all_student_ids'])) {
                            $editParentIds = array_map('intval', explode(',', $editUser['parent_all_student_ids']));
                        } elseif (isset($_POST['student_ids'])) {
                            $editParentIds = array_map('intval', (array)$_POST['student_ids']);
                        }
                        ?>
                        <?php if (empty($students)): ?>
                            <div style="padding:.75rem;border:1px solid #cdd5e0;border-radius:8px;background:#fff8e1;">
                                <span style="font-size:.85rem;color:#856404;">
                                    <i class="fas fa-info-circle"></i>
                                    All students are already linked to a parent.
                                </span>
                            </div>
                        <?php else: ?>
                        <div style="border:1px solid #cdd5e0;border-radius:8px;overflow:hidden;">
                            <div style="padding:.5rem .75rem;background:#f0f2f5;border-bottom:1px solid #cdd5e0;
                                        display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:.82rem;color:#6c757d;"><?= count($students) ?> student(s) available</span>
                                <div style="display:flex;gap:.5rem;">
                                    <button type="button" onclick="toggleParentStudents(true)"
                                            style="font-size:.75rem;padding:.25rem .6rem;border:none;background:#1a2b4a;color:#fff;border-radius:4px;cursor:pointer;">Select All</button>
                                    <button type="button" onclick="toggleParentStudents(false)"
                                            style="font-size:.75rem;padding:.25rem .6rem;border:none;background:#6c757d;color:#fff;border-radius:4px;cursor:pointer;">Clear All</button>
                                </div>
                            </div>
                            <div style="max-height:200px;overflow-y:auto;padding:.5rem 0;">
                                <?php foreach ($students as $stu): ?>
                                <label style="display:flex;align-items:center;gap:.6rem;padding:.4rem 1rem;cursor:pointer;"
                                       onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" name="student_ids[]"
                                           class="parent-student-checkbox"
                                           value="<?= $stu['student_id'] ?>"
                                           <?= in_array((int)$stu['student_id'], $editParentIds) ? 'checked' : '' ?>>
                                    <span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($stu['full_name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="margin-top:.75rem;">
                        <label for="relationship">Relationship</label>
                        <input type="text" id="relationship" name="relationship"
                               value="<?= htmlspecialchars($editUser['relationship'] ?? ($_POST['relationship'] ?? '')) ?>"
                               placeholder="e.g. Mother, Father, Guardian">
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($editUser): ?>
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Update User</button>
                        <a href="manage_users.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php else: ?>
                        <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add User</button>
                        <button type="reset" class="btn-secondary" onclick="handleRoleChange('')"><i class="fas fa-undo"></i> Reset</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- USERS TABLE -->
        <div class="card">
            <div class="card-header">
                <span class="card-header-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i>
                    All Users
                    <span class="badge badge-secondary" style="font-size:.72rem;margin-left:.4rem;"><?= count($users) ?></span>
                </span>
                <div class="d-flex gap-1 align-center">
                    <!-- Role filter -->
                    <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
                        <select name="filter_role" onchange="this.form.submit()"
                                style="font-size:0.82rem;padding:0.35rem 0.65rem;border:1px solid #cdd5e0;border-radius:6px;">
                            <option value="">All Roles</option>
                            <?php foreach (['admin','teacher','student','parent'] as $r): ?>
                                <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>>
                                    <?= ucfirst($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filterRole !== ''): ?>
                            <a href="manage_users.php" class="btn-secondary btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                    <div class="search-input-wrapper" style="max-width:200px;">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search users…" data-search="usersTable"
                               style="font-size:.82rem;padding:.4rem .75rem .4rem 2.1rem;">
                    </div>
                </div>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                    <h3>No users found</h3>
                    <p><?= $filterRole ? "No {$filterRole}s found." : 'Add the first user using the form above.' ?></p>
                </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td data-label="ID"><?= $u['user_id'] ?></td>
                            <td data-label="Full Name"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td data-label="Username"><?= htmlspecialchars($u['username']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                            <td data-label="Role">
                                <span class="role-badge role-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td data-label="Created">
                                <?= date('M j, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-1" style="flex-wrap:wrap;">
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="showUserDetails(<?= $u['user_id'] ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    <a href="?edit=<?= $u['user_id'] ?>"
                                       class="btn-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $u['user_id'] ?>"
                                           class="btn-danger btn-sm"
                                           onclick="return confirmDelete('<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-primary">You</span>
                                    <?php endif; ?>
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

<!-- User Details Modal -->
<div id="userDetailModal" style="
        display:none;position:fixed;inset:0;z-index:9999;
        background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="
            background:#fff;border-radius:14px;width:100%;max-width:520px;
            margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
        <!-- Modal header -->
        <div style="
                display:flex;align-items:center;justify-content:space-between;
                padding:1.1rem 1.4rem;
                background:linear-gradient(135deg,var(--primary-dark),var(--primary));
                color:#fff;">
            <span style="font-size:1.05rem;font-weight:700;">
                <i class="fas fa-id-card" style="margin-right:.5rem;"></i>
                User Details
            </span>
            <button onclick="closeUserDetails()"
                    style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;line-height:1;">
                &times;
            </button>
        </div>
        <!-- Modal body -->
        <div id="userDetailBody" style="padding:1.4rem 1.5rem;"></div>
        <!-- Modal footer -->
        <div style="
                padding:.9rem 1.5rem;
                border-top:1px solid #e9ecef;
                display:flex;justify-content:flex-end;gap:.5rem;">
            <span id="userDetailEditLink"></span>
            <button onclick="closeUserDetails()"
                    class="btn-secondary btn-sm"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<script>
const USERS_DATA = <?= json_encode(array_values($usersDetailJson), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
const USERS_MAP  = {};
USERS_DATA.forEach(u => { USERS_MAP[u.user_id] = u; });

const ROLE_COLORS = {
    admin:   '#6366f1',
    teacher: '#0ea5e9',
    student: '#10b981',
    parent:  '#f59e0b',
};

function showUserDetails(uid) {
    const u = USERS_MAP[uid];
    if (!u) return;

    const fmt = v => v ? escHtml(v) : '<span style="color:#aaa;">—</span>';
    const fmtDate = v => v ? new Date(v).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '—';

    let extraHtml = '';
    const e = u.extra || {};
    if (u.role === 'student') {
        extraHtml = `
            <tr><td><i class="fas fa-school" style="color:var(--primary);width:18px;"></i> Class</td>
                <td><strong>${fmt(e.class_name)} ${e.academic_year ? '(' + escHtml(e.academic_year) + ')' : ''}</strong></td></tr>
            <tr><td><i class="fas fa-birthday-cake" style="color:var(--primary);width:18px;"></i> Date of Birth</td>
                <td>${fmt(e.dob)}</td></tr>
            <tr><td><i class="fas fa-venus-mars" style="color:var(--primary);width:18px;"></i> Gender</td>
                <td>${fmt(e.gender)}</td></tr>`;
    } else if (u.role === 'teacher') {
        extraHtml = `
            <tr><td><i class="fas fa-book" style="color:var(--primary);width:18px;"></i> Subject</td>
                <td><strong>${fmt(e.subject)}</strong></td></tr>`;
    } else if (u.role === 'parent') {
        const kids = (e.children || []);
        const kidsHtml = kids.length === 0
            ? '<span style="color:#aaa;">No children linked</span>'
            : kids.map(k =>
                `<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.25rem;">
                    <i class="fas fa-user-graduate" style="color:var(--primary);font-size:.8rem;"></i>
                    <strong>${escHtml(k.name)}</strong>
                    <span style="color:#888;font-size:.82rem;">(${escHtml(k.relationship||'—')},
                    ${escHtml(k.class||'—')})</span>
                 </div>`
              ).join('');
        extraHtml = `
            <tr><td valign="top"><i class="fas fa-child" style="color:var(--primary);width:18px;"></i> Children</td>
                <td>${kidsHtml}</td></tr>`;
    }

    const roleBadge = `<span style="
        display:inline-block;padding:.25rem .75rem;border-radius:999px;font-size:.8rem;font-weight:700;
        background:${ROLE_COLORS[u.role]||'#6c757d'}20;color:${ROLE_COLORS[u.role]||'#6c757d'};
        border:1px solid ${ROLE_COLORS[u.role]||'#6c757d'}40;">${cap(u.role)}</span>`;

    document.getElementById('userDetailBody').innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
            <colgroup><col style="width:36%;color:#555;"><col style="width:64%;"></colgroup>
            <tbody style="vertical-align:middle;">
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-hashtag" style="color:var(--primary);width:18px;"></i> User ID
                    </td>
                    <td style="padding:.55rem .3rem;">#${u.user_id}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-user" style="color:var(--primary);width:18px;"></i> Full Name
                    </td>
                    <td style="padding:.55rem .3rem;"><strong>${fmt(u.full_name)}</strong></td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-at" style="color:var(--primary);width:18px;"></i> Username
                    </td>
                    <td style="padding:.55rem .3rem;">${fmt(u.username)}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-envelope" style="color:var(--primary);width:18px;"></i> Email
                    </td>
                    <td style="padding:.55rem .3rem;">${fmt(u.email)}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-phone" style="color:var(--primary);width:18px;"></i> Phone
                    </td>
                    <td style="padding:.55rem .3rem;">${fmt(u.phone)}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-shield-alt" style="color:var(--primary);width:18px;"></i> Role
                    </td>
                    <td style="padding:.55rem .3rem;">${roleBadge}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:.55rem .3rem;color:#6c757d;">
                        <i class="fas fa-calendar-plus" style="color:var(--primary);width:18px;"></i> Registered
                    </td>
                    <td style="padding:.55rem .3rem;">${fmtDate(u.created_at)}</td>
                </tr>
                ${extraHtml ? `<tr><td colspan="2" style="padding-top:.6rem;padding-bottom:.1rem;font-size:.75rem;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.05em;">Role Details</td></tr>${extraHtml}` : ''}
            </tbody>
        </table>`;

    document.getElementById('userDetailEditLink').innerHTML =
        `<a href="?edit=${u.user_id}" class="btn-primary btn-sm">
            <i class="fas fa-edit"></i> Edit This User
         </a>`;

    const modal = document.getElementById('userDetailModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeUserDetails() {
    document.getElementById('userDetailModal').style.display = 'none';
    document.body.style.overflow = '';
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

document.getElementById('userDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeUserDetails();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeUserDetails();
});

function handleRoleChange(role) {
    document.getElementById('field_class').style.display   = (role === 'student') ? 'grid' : 'none';
    document.getElementById('field_subject').style.display = (role === 'teacher') ? 'grid' : 'none';
    document.getElementById('field_parent').style.display  = (role === 'parent')  ? 'grid' : 'none';
}

function confirmDelete(name) {
    return confirm('Delete user "' + name + '"?\n\nThis will also remove their student/teacher/parent record. This cannot be undone.');
}

(function () {
    const role = document.getElementById('role');
    if (role && role.value) handleRoleChange(role.value);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>