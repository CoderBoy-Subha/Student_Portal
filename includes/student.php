<?php
// ============================================================
//  includes/student.php — All DB & business logic
//  Compatible: PHP 7.4+ | MariaDB 10.4 | InfinityFree
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

// ── Email existence check ─────────────────────────────────────

function email_exists($email)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM students WHERE email = ?');
    $stmt->execute(array($email));
    return (int)$stmt->fetchColumn() > 0;
}

// ── OTP helpers ───────────────────────────────────────────────

function generate_otp()
{
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function store_registration_otp($email, $name, $password_hash, $otp, $photo_stage = null)
{
    $_SESSION['reg_otp']         = password_hash($otp, PASSWORD_BCRYPT, array('cost' => 8));
    $_SESSION['reg_otp_expiry']  = time() + (OTP_EXPIRY_MINUTES * 60);
    $_SESSION['reg_email']       = $email;
    $_SESSION['reg_name']        = $name;
    $_SESSION['reg_hash']        = $password_hash;
    $_SESSION['reg_photo_stage'] = $photo_stage; // path inside uploads/stage/ or null
    $_SESSION['reg_attempts']    = 0;
}

function verify_registration_otp($otp_input)
{
    if (empty($_SESSION['reg_otp']) || empty($_SESSION['reg_otp_expiry'])) {
        return array('success' => false, 'error' => 'No OTP session found. Please register again.');
    }
    if (time() > (int)$_SESSION['reg_otp_expiry']) {
        clear_registration_session();
        return array('success' => false, 'error' => 'OTP has expired. Please register again.');
    }
    $attempts = isset($_SESSION['reg_attempts']) ? (int)$_SESSION['reg_attempts'] : 0;
    $attempts++;
    $_SESSION['reg_attempts'] = $attempts;
    if ($attempts > 5) {
        clear_registration_session();
        return array('success' => false, 'error' => 'Too many wrong attempts. Please register again.');
    }
    if (!password_verify($otp_input, $_SESSION['reg_otp'])) {
        $left = 5 - $attempts;
        return array('success' => false, 'error' => 'Incorrect OTP. ' . $left . ' attempt(s) remaining.');
    }
    return array('success' => true);
}

function clear_registration_session()
{
    // Clean up any staged photo
    if (!empty($_SESSION['reg_photo_stage']) && file_exists($_SESSION['reg_photo_stage'])) {
        @unlink($_SESSION['reg_photo_stage']);
    }
    foreach (array('reg_otp','reg_otp_expiry','reg_email','reg_name',
                   'reg_hash','reg_photo_stage','reg_attempts') as $k) {
        unset($_SESSION[$k]);
    }
}

// ── Photo staging (before OTP) ────────────────────────────────
// Moves upload into uploads/stage/ — stays in open_basedir on InfinityFree
// Final move to uploads/avatars/ happens in complete_registration()

function stage_photo_upload($file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('success' => true, 'path' => null);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'error' => 'Upload error code: ' . $file['error']);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return array('success' => false, 'error' => 'Photo must be under 2 MB.');
    }

    // Validate real MIME type — not just extension
    if (!function_exists('finfo_open')) {
        // finfo not available — fall back to getimagesize()
        $info = @getimagesize($file['tmp_name']);
        if (!$info) {
            return array('success' => false, 'error' => 'Only image files are allowed.');
        }
        $mime = $info['mime'];
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
    }

    $allowed = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    );
    if (!isset($allowed[$mime])) {
        return array('success' => false, 'error' => 'Only JPG, PNG, GIF and WEBP are allowed.');
    }

    ensure_upload_dirs();

    $ext      = $allowed[$mime];
    $filename = 'stage_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest     = UPLOAD_STAGE . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return array('success' => false, 'error' => 'Could not save photo. Check folder permissions.');
    }

    return array('success' => true, 'path' => $dest);
}

// ── Complete registration after OTP verified ──────────────────

function complete_registration($ip)
{
    $name        = isset($_SESSION['reg_name'])        ? $_SESSION['reg_name']        : '';
    $email       = isset($_SESSION['reg_email'])       ? $_SESSION['reg_email']       : '';
    $hash        = isset($_SESSION['reg_hash'])        ? $_SESSION['reg_hash']        : '';
    $photo_stage = isset($_SESSION['reg_photo_stage']) ? $_SESSION['reg_photo_stage'] : null;

    // Move staged photo to final location
    $photo_path = null;
    if ($photo_stage && file_exists($photo_stage)) {
        ensure_upload_dirs();

        // Re-detect MIME from staged file
        if (!function_exists('finfo_open')) {
            $info = @getimagesize($photo_stage);
            $mime = $info ? $info['mime'] : '';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($photo_stage);
        }

        $allowed = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
        if (isset($allowed[$mime])) {
            $ext      = $allowed[$mime];
            $filename = 'avatar_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest     = UPLOAD_DIR . $filename;
            if (rename($photo_stage, $dest)) {
                $photo_path = 'uploads/avatars/' . $filename;
            } else {
                // rename failed — try copy+delete
                if (copy($photo_stage, $dest)) {
                    $photo_path = 'uploads/avatars/' . $filename;
                    @unlink($photo_stage);
                }
            }
        }
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO students (full_name, email, password_hash, photo_path) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array($name, $email, $hash, $photo_path));
        $student_id = (int)$pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO audit_log (student_id, action, ip_address, new_value) VALUES (?, "register", ?, ?)'
        )->execute(array($student_id, $ip, json_encode(array('email' => $email, 'full_name' => $name))));

        $pdo->commit();
        clear_registration_session();
        send_welcome_email($email, $name);
        return array('success' => true, 'student_id' => $student_id);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return array('success' => false, 'error' => 'That email is already registered.');
        }
        error_log('[complete_registration] ' . $e->getMessage());
        return array('success' => false, 'error' => 'Registration failed. Please try again.');
    }
}

// ── Authentication ────────────────────────────────────────────

function authenticate_student($email, $password, $ip, $ua)
{
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password_hash, account_status,
                failed_logins, locked_until, photo_path
         FROM students WHERE email = ? LIMIT 1'
    );
    $stmt->execute(array($email));
    $student = $stmt->fetch();

    if (!$student) {
        log_attempt(null, $email, $ip, $ua, 'failure');
        return array('success' => false, 'error' => 'Invalid email or password.');
    }

    if (in_array($student['account_status'], array('suspended', 'deleted'))) {
        return array('success' => false, 'error' => 'This account is not accessible.');
    }

    if ($student['account_status'] === 'locked' &&
        !empty($student['locked_until']) &&
        strtotime($student['locked_until']) > time()) {
        $minutes = (int)ceil((strtotime($student['locked_until']) - time()) / 60);
        log_attempt($student['id'], $email, $ip, $ua, 'locked');
        return array('success' => false, 'locked' => true,
            'error' => 'Account locked. Try again in ' . $minutes . ' minute(s).');
    }

    // Auto-unlock if lockout window passed
    if ($student['account_status'] === 'locked' &&
        !empty($student['locked_until']) &&
        strtotime($student['locked_until']) <= time()) {
        $pdo->prepare(
            'UPDATE students SET account_status = "active", failed_logins = 0, locked_until = NULL WHERE id = ?'
        )->execute(array($student['id']));
        $student['account_status'] = 'active';
        $student['failed_logins']  = 0;
    }

    if (!password_verify($password, $student['password_hash'])) {
        $new_fails = (int)$student['failed_logins'] + 1;
        if ($new_fails >= MAX_LOGIN_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'));
            $pdo->prepare(
                'UPDATE students SET failed_logins = ?, account_status = "locked", locked_until = ? WHERE id = ?'
            )->execute(array($new_fails, $locked_until, $student['id']));
            $pdo->prepare(
                'INSERT INTO audit_log (student_id, action, ip_address, new_value) VALUES (?, "account_locked", ?, ?)'
            )->execute(array($student['id'], $ip, json_encode(array('locked_until' => $locked_until))));
            log_attempt($student['id'], $email, $ip, $ua, 'locked');
            return array('success' => false, 'locked' => true,
                'error' => 'Too many failed attempts. Account locked for ' . LOCKOUT_MINUTES . ' minutes.');
        }
        $pdo->prepare('UPDATE students SET failed_logins = ? WHERE id = ?')
            ->execute(array($new_fails, $student['id']));
        log_attempt($student['id'], $email, $ip, $ua, 'failure');
        $pdo->prepare('INSERT INTO audit_log (student_id, action, ip_address) VALUES (?, "login_failure", ?)')
            ->execute(array($student['id'], $ip));
        $remaining = MAX_LOGIN_ATTEMPTS - $new_fails;
        return array('success' => false,
            'error' => 'Invalid email or password. (' . $remaining . ' attempt(s) remaining)');
    }

    // Successful login
    $pdo->prepare(
        'UPDATE students SET failed_logins = 0, locked_until = NULL, account_status = "active",
         last_login_at = NOW(), last_login_ip = ? WHERE id = ?'
    )->execute(array($ip, $student['id']));
    log_attempt($student['id'], $email, $ip, $ua, 'success');
    $pdo->prepare('INSERT INTO audit_log (student_id, action, ip_address) VALUES (?, "login_success", ?)')
        ->execute(array($student['id'], $ip));

    return array('success' => true, 'student' => array(
        'id'         => $student['id'],
        'full_name'  => $student['full_name'],
        'email'      => $student['email'],
        'photo_path' => $student['photo_path'],
    ));
}

function log_attempt($student_id, $email, $ip, $ua, $result)
{
    try {
        db()->prepare(
            'INSERT INTO login_attempts (student_id, email_tried, ip_address, user_agent, attempt_result)
             VALUES (?, ?, ?, ?, ?)'
        )->execute(array($student_id, $email, $ip, $ua, $result));
    } catch (PDOException $e) {
        error_log('[log_attempt] ' . $e->getMessage());
    }
}

// ── Forgot password OTP ───────────────────────────────────────

function store_reset_otp($email, $otp)
{
    $_SESSION['reset_otp']        = password_hash($otp, PASSWORD_BCRYPT, array('cost' => 8));
    $_SESSION['reset_otp_expiry'] = time() + (OTP_EXPIRY_MINUTES * 60);
    $_SESSION['reset_email']      = $email;
    $_SESSION['reset_attempts']   = 0;
    $_SESSION['reset_verified']   = false;
}

function verify_reset_otp($otp_input)
{
    if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_otp_expiry'])) {
        return array('success' => false, 'error' => 'No OTP session found. Please request again.');
    }
    if (time() > (int)$_SESSION['reset_otp_expiry']) {
        clear_reset_session();
        return array('success' => false, 'error' => 'OTP has expired. Please request a new one.');
    }
    $attempts = isset($_SESSION['reset_attempts']) ? (int)$_SESSION['reset_attempts'] : 0;
    $attempts++;
    $_SESSION['reset_attempts'] = $attempts;
    if ($attempts > 5) {
        clear_reset_session();
        return array('success' => false, 'error' => 'Too many wrong attempts. Please request a new OTP.');
    }
    if (!password_verify($otp_input, $_SESSION['reset_otp'])) {
        $left = 5 - $attempts;
        return array('success' => false, 'error' => 'Incorrect OTP. ' . $left . ' attempt(s) remaining.');
    }
    $_SESSION['reset_verified'] = true;
    return array('success' => true);
}

function reset_password($new_password, $ip)
{
    if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_email'])) {
        return array('success' => false, 'error' => 'Session invalid. Please start over.');
    }
    $email = $_SESSION['reset_email'];
    $hash  = password_hash($new_password, PASSWORD_BCRYPT, array('cost' => BCRYPT_COST));
    try {
        $stmt = db()->prepare(
            'UPDATE students SET password_hash = ?, failed_logins = 0, locked_until = NULL,
             account_status = "active" WHERE email = ?'
        );
        $stmt->execute(array($hash, $email));
        if ($stmt->rowCount() === 0) {
            return array('success' => false, 'error' => 'Account not found.');
        }
        $row = db()->prepare('SELECT id FROM students WHERE email = ? LIMIT 1');
        $row->execute(array($email));
        $sid = $row->fetchColumn();
        if ($sid) {
            db()->prepare(
                'INSERT INTO audit_log (student_id, action, ip_address) VALUES (?, "password_changed", ?)'
            )->execute(array($sid, $ip));
        }
        clear_reset_session();
        return array('success' => true);
    } catch (PDOException $e) {
        error_log('[reset_password] ' . $e->getMessage());
        return array('success' => false, 'error' => 'Could not reset password. Please try again.');
    }
}

function clear_reset_session()
{
    foreach (array('reset_otp', 'reset_otp_expiry', 'reset_email', 'reset_attempts', 'reset_verified') as $k) {
        unset($_SESSION[$k]);
    }
}

// ── Dashboard data ────────────────────────────────────────────

function get_dashboard_data($student_id)
{
    $stmt = db()->prepare(
        'SELECT s.id, s.full_name, s.email, s.account_status,
                s.last_login_at, s.last_login_ip, s.created_at, s.photo_path,
                DATEDIFF(NOW(), DATE(s.created_at)) AS days_member,
                (SELECT COUNT(*) FROM login_attempts la
                 WHERE la.student_id = s.id AND la.attempt_result = "success") AS total_logins
         FROM students s
         WHERE s.id = ? AND s.account_status NOT IN ("deleted", "suspended") LIMIT 1'
    );
    $stmt->execute(array($student_id));
    $row = $stmt->fetch();
    return $row ? $row : null;
}

function get_recent_logins($student_id)
{
    $stmt = db()->prepare(
        'SELECT attempt_result, ip_address, attempted_at
         FROM login_attempts WHERE student_id = ?
         ORDER BY attempted_at DESC LIMIT 5'
    );
    $stmt->execute(array($student_id));
    return $stmt->fetchAll();
}

// ── Logout ────────────────────────────────────────────────────

function logout_student()
{
    if (!empty($_SESSION['student_id'])) {
        try {
            db()->prepare(
                'INSERT INTO audit_log (student_id, action, ip_address) VALUES (?, "logout", ?)'
            )->execute(array($_SESSION['student_id'], get_client_ip()));
        } catch (PDOException $e) {
            error_log('[logout] ' . $e->getMessage());
        }
    }
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
