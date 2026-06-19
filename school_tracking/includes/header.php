<?php
require_once __DIR__ . '/auth.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;

function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart School Tracking System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/school_tracking/assets/css/style.css">
</head>
<body>

<header class="navbar">
    <div class="d-flex align-center gap-2">
        <?php if ($currentUser): ?>
        <button class="hamburger" id="sidebarToggle" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
        </button>
        <?php endif; ?>

        <a href="/school_tracking/<?= $currentUser ? $currentUser['role'] . '/index.php' : '' ?>" class="navbar-brand" style="text-decoration:none;">
            <div class="navbar-logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <div class="navbar-title">Smart School Tracker</div>
                <div class="navbar-subtitle">School Management System</div>
            </div>
        </a>
    </div>

    <?php if ($currentUser): ?>
    <nav class="navbar-nav">
        <div class="nav-user-info">
            <div class="nav-avatar">
                <?= getInitials($currentUser['full_name']) ?>
            </div>
            <div class="nav-user-text" style="display:flex;flex-direction:column;line-height:1.2;">
                <span class="nav-fullname"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                <span class="nav-role"><?= ucfirst($currentUser['role']) ?></span>
            </div>
        </div>

        <a href="/school_tracking/logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
    <?php endif; ?>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
