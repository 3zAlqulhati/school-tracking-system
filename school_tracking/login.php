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
    exit;
}

$pdo = require __DIR__ . '/config/db.php';

$error    = '';
$username = '';

const MAX_ATTEMPTS    = 5;
const LOCKOUT_MINUTES = 15;

function redirectToDashboard(string $role): void {
    $map = [
        'admin'   => '/school_tracking/admin/index.php',
        'teacher' => '/school_tracking/teacher/index.php',
        'student' => '/school_tracking/student/index.php',
        'parent'  => '/school_tracking/parent/index.php',
    ];
    header('Location: ' . ($map[$role] ?? '/school_tracking/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, username, password, role, full_name,
                    failed_attempts, login_timestamp
             FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid username or password.';
        } else {
            $locked = false;
            if ($user['role'] !== 'admin'
                && (int)$user['failed_attempts'] >= MAX_ATTEMPTS
                && $user['login_timestamp'] !== null) {
                $lastAttempt = strtotime($user['login_timestamp']);
                $lockoutEnds = $lastAttempt + (LOCKOUT_MINUTES * 60);
                if (time() < $lockoutEnds) {
                    $minutesLeft = ceil(($lockoutEnds - time()) / 60);
                    $error  = "Account locked after " . MAX_ATTEMPTS . " failed attempts. "
                            . "Try again in {$minutesLeft} minute(s).";
                    $locked = true;
                } else {
                    $pdo->prepare('UPDATE users SET failed_attempts = 0 WHERE user_id = ?')
                        ->execute([$user['user_id']]);
                    $user['failed_attempts'] = 0;
                }
            }

            if (!$locked) {
                if (password_verify($password, $user['password'])) {
                    $pdo->prepare(
                        'UPDATE users SET failed_attempts=0, login_timestamp=NOW(), last_login=NOW() WHERE user_id=?'
                    )->execute([$user['user_id']]);
                    loginUser($user);
                    redirectToDashboard($user['role']);
                } else {
                    $newAttempts = (int)$user['failed_attempts'] + 1;
                    $pdo->prepare(
                        'UPDATE users SET failed_attempts=?, login_timestamp=NOW() WHERE user_id=?'
                    )->execute([$newAttempts, $user['user_id']]);
                    $remaining = MAX_ATTEMPTS - $newAttempts;
                    if ($remaining > 0) {
                        $error = "Invalid username or password. {$remaining} attempt(s) remaining before lockout.";
                    } else {
                        $error = "Account locked after " . MAX_ATTEMPTS . " failed attempts. Try again in " . LOCKOUT_MINUTES . " minute(s).";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Smart School Tracking System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/school_tracking/assets/css/style.css">
    <style>
        html, body { height: 100%; margin: 0; }
        .login-page {
            display: flex;
            min-height: 100vh;
        }

        .login-left {
            flex: 1.15;
            background: linear-gradient(145deg, #0f1f33 0%, #1e3a5f 45%, #2d5a8e 100%);
            display: flex;
            flex-direction: column;
            padding: 3rem 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(59,130,246,.12);
        }
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -60px;
            width: 250px; height: 250px;
            border-radius: 50%;
            background: rgba(16,185,129,.09);
        }
        .login-left-inner { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 1.25rem; }

        .lp-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .lp-brand-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, rgba(255,255,255,.2), rgba(255,255,255,.08));
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #fff;
            flex-shrink: 0;
        }
        .lp-brand-text { line-height: 1.1; display: flex; flex-direction: column; gap: .35rem; }
        .lp-brand-name {
            font-family: 'Poppins', sans-serif;
            font-size: .95rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.01em;
        }
        .lp-brand-sub {
            font-size: .72rem;
            color: rgba(255,255,255,.5);
            font-weight: 500;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .lp-headline {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.6rem, 2.5vw, 2.1rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }
        .lp-headline span {
            background: linear-gradient(135deg, #60a5fa, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .lp-tagline {
            font-size: .9rem;
            color: rgba(255,255,255,.65);
            line-height: 1.7;
            max-width: 400px;
        }

        .lp-live-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18);
            color: rgba(255,255,255,.85);
            padding: .35rem 1rem;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            margin-bottom: 1.75rem;
            width: fit-content;
        }
        .lp-live-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #34d399;
            animation: lp-pulse 2s infinite;
        }
        @keyframes lp-pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.6; transform:scale(1.2); }
        }

        .lp-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .7rem;
        }
        .lp-feature {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            padding: .75rem .9rem;
        }
        .lp-feature-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem;
            flex-shrink: 0;
        }
        .lp-feature-title {
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .lp-feature-desc {
            font-size: .72rem;
            color: rgba(255,255,255,.52);
            margin-top: .15rem;
            line-height: 1.4;
        }

        .lp-roles {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .lp-role-chip {
            display: flex;
            align-items: center;
            gap: .4rem;
            padding: .38rem .8rem;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,.15);
            color: rgba(255,255,255,.85);
            background: rgba(255,255,255,.08);
        }
        .lp-role-chip i { font-size: .72rem; }

        .lp-stats {
            display: flex;
            gap: 0;
            border-top: 1px solid rgba(255,255,255,.12);
            padding-top: 1.25rem;
        }
        .lp-stat {
            flex: 1;
            text-align: center;
        }
        .lp-stat + .lp-stat {
            border-left: 1px solid rgba(255,255,255,.1);
        }
        .lp-stat-num {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .lp-stat-label {
            font-size: .68rem;
            color: rgba(255,255,255,.45);
            margin-top: .25rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 600;
        }

        .login-right {
            flex: 0 0 420px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.75rem;
            background: #fff;
            overflow-y: auto;
        }

        .login-form-header {
            margin-bottom: 2rem;
        }
        .logo-mini {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.1rem;
            margin-bottom: 1.25rem;
        }
        .login-form-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: .3rem;
        }
        .login-form-header p {
            font-size: .875rem;
            color: var(--text-muted);
        }

        .login-demo-accounts {
            margin-top: 1.5rem;
            background: var(--bg);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            padding: 1rem 1.1rem;
        }
        .login-demo-accounts h4 {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .75rem;
        }
        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .35rem 0;
            font-size: .8rem;
            border-bottom: 1px solid var(--border-light);
        }
        .demo-account:last-child { border-bottom: none; }
        .demo-account span { color: var(--text-muted); font-weight: 600; }
        .demo-account code {
            background: rgba(0,0,0,.06);
            border-radius: 4px;
            padding: .1rem .4rem;
            font-size: .75rem;
            color: var(--primary);
        }

        @media (max-width: 900px) {
            .login-page { flex-direction: column; }
            .login-left  { padding: 2.5rem 2rem; flex: none; }
            .login-right { flex: none; padding: 2.5rem 2rem; }
            .lp-features { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .login-left, .login-right { padding: 1.75rem 1.25rem; }
        }
    </style>
</head>
<body>

<div class="login-page">

    <div class="login-left">
        <div class="login-left-inner">

            <div class="lp-brand">
                <div class="lp-brand-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="lp-brand-text">
                    <div class="lp-brand-name">Smart School Tracking System</div>
                    <div class="lp-brand-sub">School Management Platform</div>
                </div>
            </div>

            <h1 class="lp-headline">
                Smarter Education<br>
                <span>Management</span><br>
                for Everyone
            </h1>
            <p class="lp-tagline">
                A comprehensive platform connecting administrators, teachers, students,
                and parents — track attendance, grades, assignments, and more in real time.
            </p>

            <div class="lp-features">
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(59,130,246,.18);color:#60a5fa;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Attendance Tracking</div>
                        <div class="lp-feature-desc">Real-time recording with monthly summaries</div>
                    </div>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(16,185,129,.18);color:#34d399;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Grade Management</div>
                        <div class="lp-feature-desc">Auto GPA with performance charts</div>
                    </div>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(245,158,11,.18);color:#fbbf24;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Assignments</div>
                        <div class="lp-feature-desc">Online submission & deadline tracking</div>
                    </div>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(168,85,247,.18);color:#c084fc;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Notifications</div>
                        <div class="lp-feature-desc">Targeted announcements to all roles</div>
                    </div>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(239,68,68,.18);color:#f87171;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Reports & Analytics</div>
                        <div class="lp-feature-desc">Visual dashboards with live charts</div>
                    </div>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon" style="background:rgba(20,184,166,.18);color:#2dd4bf;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <div class="lp-feature-title">Secure & Role-Based</div>
                        <div class="lp-feature-desc">Bcrypt passwords, strict access control</div>
                    </div>
                </div>
            </div>

            <div class="lp-roles">
                <div class="lp-role-chip" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.25);">
                    <i class="fas fa-user-shield" style="color:#f87171;"></i> Administrator
                </div>
                <div class="lp-role-chip" style="background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.25);">
                    <i class="fas fa-chalkboard-teacher" style="color:#34d399;"></i> Teacher
                </div>
                <div class="lp-role-chip" style="background:rgba(59,130,246,.15);border-color:rgba(59,130,246,.25);">
                    <i class="fas fa-user-graduate" style="color:#60a5fa;"></i> Student
                </div>
                <div class="lp-role-chip" style="background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.25);">
                    <i class="fas fa-home" style="color:#fbbf24;"></i> Parent
                </div>
            </div>

            <div class="lp-stats">
                <div class="lp-stat">
                    <div class="lp-stat-num">4</div>
                    <div class="lp-stat-label">User Roles</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">20+</div>
                    <div class="lp-stat-label">Features</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">100%</div>
                    <div class="lp-stat-label">Responsive</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">PHP 8</div>
                    <div class="lp-stat-label">Powered</div>
                </div>
            </div>

        </div>
    </div>

    <div class="login-right">

        <div class="login-form-header">
            <div class="logo-mini"><i class="fas fa-graduation-cap"></i></div>
            <h2>Welcome Back</h2>
            <p>Sign in to your account to continue</p>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;">
                <i class="fas fa-check-circle alert-icon"></i>
                You have been logged out successfully.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;">
                <i class="fas fa-exclamation-triangle alert-icon"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate class="login-form">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user" style="color:var(--text-muted);margin-right:.4rem;font-size:.85rem;"></i>
                    Username
                </label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username) ?>"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock" style="color:var(--text-muted);margin-right:.4rem;font-size:.85rem;"></i>
                    Password
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="password-toggle" title="Show/hide password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-block"
                    style="margin-top:1.5rem;padding:.75rem;font-size:.95rem;border-radius:10px;">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>
        </form>

        <div class="login-demo-accounts">
            <h4><i class="fas fa-info-circle" style="margin-right:.4rem;"></i>Demo Credentials</h4>
            <div class="demo-account">
                <span><i class="fas fa-user-shield" style="color:#b91c1c;width:14px;"></i> Admin</span>
                <div style="display:flex;gap:.5rem;">
                    <code>admin</code>
                    <code>admin123</code>
                </div>
            </div>
            <div class="demo-account">
                <span><i class="fas fa-chalkboard-teacher" style="color:#065f46;width:14px;"></i> Teacher</span>
                <div style="display:flex;gap:.5rem;">
                    <code>teacher1</code>
                    <code>teacher123</code>
                </div>
            </div>
            <div class="demo-account">
                <span><i class="fas fa-user-graduate" style="color:#1e40af;width:14px;"></i> Student</span>
                <div style="display:flex;gap:.5rem;">
                    <code>student1</code>
                    <code>student123</code>
                </div>
            </div>
            <div class="demo-account">
                <span><i class="fas fa-home" style="color:#92400e;width:14px;"></i> Parent</span>
                <div style="display:flex;gap:.5rem;">
                    <code>parent1</code>
                    <code>parent123</code>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="/school_tracking/assets/js/main.js"></script>
</body>
</html>
