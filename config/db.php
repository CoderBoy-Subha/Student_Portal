<?php
define('DB_HOST',    'sql306.infinityfree.com');
define('DB_NAME',    'if0_42287710_student_portal_v2');
define('DB_USER',    'if0_42287710');
define('DB_PASS',    'Subha27052003');
define('DB_CHARSET', 'utf8mb4');

// ── APP ──────────────────────────────────────────────────────
define('APP_NAME',           'StudentPortal');
define('APP_URL',            'https://subhajit.infinityfree.io/');
define('SESSION_LIFETIME',   1800);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',    15);
define('BCRYPT_COST',        10);
define('OTP_EXPIRY_MINUTES', 10);

// ── SMTP MAIL ─────────────────────────────────────────────────
// For Gmail: use an App Password from myaccount.google.com/apppasswords
// For InfinityFree: use smtp.infinityfree.com with your email account
define('SMTP_ENABLED',    true);
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'itzsubha2705@gmail.com');
define('SMTP_PASSWORD', 'euzjagmrfpgynymp');

define('MAIL_FROM',      'itzsubha2705@gmail.com');
define('MAIL_FROM_NAME',  'StudentPortal');
define('SMTP_TIMEOUT',    10);  // seconds — keeps page responsive if SMTP blocked

// ── UPLOADS ──────────────────────────────────────────────────
// UPLOAD_DIR must be writable. InfinityFree: htdocs/student_portal/uploads/avatars/
define('UPLOAD_DIR',    __DIR__ . '/../uploads/avatars/');
define('UPLOAD_STAGE',  __DIR__ . '/../uploads/stage/');   // temp staging before OTP
define('UPLOAD_URL',    APP_URL . '/uploads/avatars/');
define('MAX_FILE_SIZE', 2097152); // 2 MB in bytes

// ─────────────────────────────────────────────────────────────

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opt = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
        } catch (PDOException $e) {
            error_log('[db] Connection failed: ' . $e->getMessage());
            die('Database unavailable. Please try again later.');
        }
    }
    return $pdo;
}

// Ensure upload directories exist and are writable
function ensure_upload_dirs()
{
    foreach (array(UPLOAD_DIR, UPLOAD_STAGE) as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
