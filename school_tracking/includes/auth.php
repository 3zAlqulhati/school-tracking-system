<?php

date_default_timezone_set('Asia/Muscat');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /school_tracking/login.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /school_tracking/login.php');
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'user_id'   => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? null,
        'role'      => $_SESSION['role']       ?? null,
        'full_name' => $_SESSION['full_name']  ?? null,
    ];
}

function loginUser(array $user): void {
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
}

function logoutUser(): void {
    session_unset();
    session_destroy();
    header('Location: /school_tracking/login.php');
    exit;
}
