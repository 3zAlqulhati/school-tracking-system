<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

session_unset();
session_destroy();

header('Location: /school_tracking/login.php?msg=logged_out');
exit;
