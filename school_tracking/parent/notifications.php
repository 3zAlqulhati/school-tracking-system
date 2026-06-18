<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole('parent');

$pdo = require __DIR__ . '/../config/db.php';

$userId = (int) $_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $nid = is_numeric($_POST['mark_read']) ? (int)$_POST['mark_read'] : 0;
    if ($nid > 0) {

        $stmtMark = $pdo->prepare(
            'UPDATE notifications SET is_read = 1
             WHERE notification_id = ?
               AND (recipient_id = ? OR recipient_id IS NULL)'
        );
        $stmtMark->execute([$nid, $userId]);
    }

    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    header("Location: notifications.php?page={$page}");
    exit;
}


$perPage     = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($currentPage - 1) * $perPage;


$stmtCount = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE recipient_id = ? OR recipient_id IS NULL'
);
$stmtCount->execute([$userId]);
$totalCount = (int) $stmtCount->fetchColumn();
$totalPages = (int) ceil($totalCount / $perPage);


$stmtFetch = $pdo->prepare(
    'SELECT n.notification_id, n.title, n.message, n.is_read,
            n.created_at, n.recipient_id,
            u.full_name AS sender_name
     FROM notifications n
     JOIN users u ON u.user_id = n.sender_id
     WHERE n.recipient_id = ? OR n.recipient_id IS NULL
     ORDER BY n.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmtFetch->execute([$userId, $perPage, $offset]);
$notifications = $stmtFetch->fetchAll();


$stmtUnread = $pdo->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE (recipient_id = ? OR recipient_id IS NULL) AND is_read = 0'
);
$stmtUnread->execute([$userId]);
$unreadCount = (int) $stmtUnread->fetchColumn();


$stmtChild = $pdo->prepare(
    'SELECT s.student_id FROM parents p
     JOIN students s ON s.student_id = p.student_id
     WHERE p.user_id = ? LIMIT 1'
);
$stmtChild->execute([$userId]);
$firstChild = $stmtChild->fetchColumn();
$childSid   = $firstChild ? (int)$firstChild : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-section">
            <span class="sidebar-label">Parent Menu</span>
            <a href="/school_tracking/parent/index.php<?= $childSid ? "?student_id={$childSid}" : '' ?>"
               class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/school_tracking/parent/view_child.php<?= $childSid ? "?student_id={$childSid}" : '' ?>"
               class="sidebar-link">
                <span class="sidebar-icon"><i class="fas fa-user-graduate"></i></span> Child Details
            </a>
            <a href="/school_tracking/parent/notifications.php" class="sidebar-link active">
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

        <h1 class="page-title">
            <span class="page-title-icon"><i class="fas fa-bell"></i></span> Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="badge badge-danger" style="font-size:0.85rem;vertical-align:middle;margin-left:0.5rem;">
                    <?= $unreadCount ?> unread
                </span>
            <?php endif; ?>
        </h1>

        <?php if (empty($notifications)): ?>
            <div class="card">
                <p class="text-muted text-center mt-3 mb-3">No notifications yet.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                <?php foreach ($notifications as $n):
                    $isUnread  = !(bool)$n['is_read'];
                    $isBroadcast = $n['recipient_id'] === null;
                ?>
                <div style="
                    background:#fff;
                    border:1px solid <?= $isUnread ? '#4a90d9' : '#e9ecef' ?>;
                    border-left:4px solid <?= $isUnread ? '#1a2b4a' : '#dee2e6' ?>;
                    border-radius:8px;
                    padding:1rem 1.25rem;
                    <?= $isUnread ? 'background:#f0f5ff;' : '' ?>
                ">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.3rem;">
                                <span style="font-weight:<?= $isUnread ? '700' : '600' ?>;font-size:1rem;">
                                    <?= htmlspecialchars($n['title']) ?>
                                </span>
                                <?php if ($isUnread): ?>
                                    <span class="badge badge-info" style="font-size:0.72rem;">New</span>
                                <?php endif; ?>
                                <?php if ($isBroadcast): ?>
                                    <span class="badge badge-warning" style="font-size:0.72rem;">Broadcast</span>
                                <?php endif; ?>
                            </div>
                            <p style="margin:0 0 0.5rem;color:#495057;font-size:0.9rem;line-height:1.5;">
                                <?= nl2br(htmlspecialchars($n['message'])) ?>
                            </p>
                            <div style="font-size:0.78rem;color:#6c757d;">
                                From: <strong><?= htmlspecialchars($n['sender_name']) ?></strong>
                                &nbsp;&bull;&nbsp;
                                <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                        <?php if ($isUnread): ?>
                        <form method="POST" style="flex-shrink:0;">
                            <input type="hidden" name="mark_read" value="<?= $n['notification_id'] ?>">
                            <button type="submit" class="btn-secondary"
                                    style="font-size:0.78rem;padding:0.35rem 0.85rem;white-space:nowrap;">
                                <i class="fas fa-check"></i> Mark Read
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:center;align-items:center;gap:0.5rem;margin-top:1.5rem;flex-wrap:wrap;">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>" class="btn-secondary"
                       style="padding:0.4rem 1rem;">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <a href="?page=<?= $p ?>"
                       style="padding:0.4rem 0.85rem;border-radius:6px;text-decoration:none;font-weight:600;
                              <?= $p === $currentPage
                                  ? 'background:#1a2b4a;color:#fff;'
                                  : 'background:#e9ecef;color:#495057;' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>" class="btn-secondary"
                       style="padding:0.4rem 1rem;">Next &raquo;</a>
                <?php endif; ?>

                <span style="font-size:0.82rem;color:#6c757d;margin-left:0.5rem;">
                    Page <?= $currentPage ?> of <?= $totalPages ?>
                    (<?= $totalCount ?> total)
                </span>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
