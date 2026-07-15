<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false));
    ob_end_flush();
    exit;
}
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(array('ok' => false));
    ob_end_flush();
    exit;
}
$_SESSION['last_active'] = time();
echo json_encode(array('ok' => true));
ob_end_flush();
exit;
