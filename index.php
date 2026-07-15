<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: register.php');
}
ob_end_flush();
exit;
