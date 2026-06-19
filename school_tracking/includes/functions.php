<?php

function sanitize(string $input): string {
    return strip_tags(trim($input));
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function showFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $cls = $flash['type'] === 'success' ? 'alert-success' : 'alert-error';
    $icon = $flash['type'] === 'success'
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-exclamation-triangle"></i>';
    echo '<div class="alert ' . $cls . '">' . $icon . ' '
       . htmlspecialchars($flash['message']) . '</div>';
}

function formatDate(string $date, string $format = 'd M Y'): string {
    $ts = strtotime($date);
    return $ts !== false ? date($format, $ts) : $date;
}

function getAttendancePercentage(int $studentId, PDO $pdo): ?float {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total, SUM(status = "present") AS present_count
         FROM attendance WHERE student_id = ?'
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['total'] === 0) return null;
    return round(($row['present_count'] / $row['total']) * 100, 1);
}

function getStudentGPA(int $studentId, PDO $pdo): ?float {
    $stmt = $pdo->prepare('SELECT AVG(grade_value) FROM grades WHERE student_id = ?');
    $stmt->execute([$studentId]);
    $val = $stmt->fetchColumn();
    return ($val !== null && $val !== false) ? round((float)$val, 2) : null;
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function isValidFileUpload(array $file, array $allowedTypes = ['pdf', 'docx', 'zip'], float $maxSizeMB = 10): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error (code ' . $file['error'] . ').'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes, true)) {
        return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes) . '.'];
    }

    $mimeMap = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];
    $mime = mime_content_type($file['tmp_name']);
    if (isset($mimeMap[$ext]) && !in_array($mime, $mimeMap[$ext], true)) {
        return ['valid' => false, 'error' => 'File content does not match its extension.'];
    }

    if ($file['size'] > $maxSizeMB * 1024 * 1024) {
        return ['valid' => false, 'error' => "File exceeds {$maxSizeMB} MB limit."];
    }

    return ['valid' => true, 'error' => ''];
}
