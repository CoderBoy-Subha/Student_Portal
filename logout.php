<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        ob_end_flush();
        exit;
    }
    logout_student();
    session_start();
    flash('success', 'You have been logged out successfully.');
    header('Location: login.php');
    ob_end_flush();
    exit;
}

// GET — timeout redirect from JS
logout_student();
session_start();
$reason = isset($_GET['reason']) ? $_GET['reason'] : '';
if ($reason === 'timeout') {
    flash('error', 'Your session expired. Please log in again.');
} else {
    flash('success', 'You have been logged out.');
}
header('Location: login.php');
ob_end_flush();
exit;
