<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $map = [
        'admin'   => '/school_tracking/admin/index.php',
        'teacher' => '/school_tracking/teacher/index.php',
        'student' => '/school_tracking/student/index.php',
        'parent'  => '/school_tracking/parent/index.php',
    ];
    header('Location: ' . ($map[$_SESSION['role']] ?? '/school_tracking/login.php'));
} else {
    header('Location: /school_tracking/login.php');
}
exit;
