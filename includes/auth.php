<?php
// ============================================================
//  includes/auth.php — Session bootstrap, guards, helpers
//  Compatible: PHP 7.4+ | InfinityFree
// ============================================================

require_once __DIR__ . '/../config/db.php';

// Start session once, using individual params for max host compatibility
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params(SESSION_LIFETIME, '/', '', $secure, true);
    session_start();
}

function require_auth($redirect = 'login.php')
{
    if (empty($_SESSION['student_id'])) {
        flash('error', 'Please log in to continue.');
        header('Location: ' . $redirect);
        exit;
    }
    if (!empty($_SESSION['last_active']) &&
        (time() - $_SESSION['last_active']) > SESSION_LIFETIME) {
        $sid = $_SESSION['student_id'];
        session_unset();
        session_destroy();
        // Re-start clean session so flash() works
        session_start();
        flash('error', 'Your session expired. Please log in again.');
        header('Location: ' . $redirect);
        exit;
    }
    $_SESSION['last_active'] = time();
}

function redirect_if_auth($destination = 'dashboard.php')
{
    if (!empty($_SESSION['student_id'])) {
        header('Location: ' . $destination);
        exit;
    }
}

// Store a flash message — session must be active
function flash($type, $message)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$type] = $message;
}

// Read and clear a flash message
function get_flash($type)
{
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate()
{
    $submitted = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $stored    = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    return !empty($stored) && hash_equals($stored, $submitted);
}

// Escape output — always use this for any user data in HTML
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function is_logged_in()
{
    return !empty($_SESSION['student_id']);
}

function current_user_name()
{
    return isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';
}

function current_user_id()
{
    return isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : null;
}

function is_valid_email($email)
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password)
{
    $errors = array();
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    return $errors;
}

function get_client_ip()
{
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function get_user_agent()
{
    return substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown', 0, 512);
}
