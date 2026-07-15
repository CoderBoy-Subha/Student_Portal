<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

redirect_if_auth('dashboard.php');

$type       = isset($_GET['type']) ? $_GET['type'] : 'register';
$page_error = null;
$page_ok    = null;

if ($type === 'register' && empty($_SESSION['reg_otp'])) {
    header('Location: register.php');
    ob_end_flush(); exit;
}
if ($type === 'forgot' && empty($_SESSION['reset_otp'])) {
    header('Location: forgot_password.php');
    ob_end_flush(); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $page_error = 'Invalid submission. Please try again.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'verify';

        if ($action === 'resend') {
            $otp = generate_otp();
            if ($type === 'register') {
                store_registration_otp(
                    $_SESSION['reg_email'],
                    $_SESSION['reg_name'],
                    $_SESSION['reg_hash'],
                    $otp,
                    isset($_SESSION['reg_photo_stage']) ? $_SESSION['reg_photo_stage'] : null
                );
                send_registration_otp($_SESSION['reg_email'], $_SESSION['reg_name'], $otp);
            } else {
                store_reset_otp($_SESSION['reset_email'], $otp);
                $row = db()->prepare('SELECT full_name FROM students WHERE email = ? LIMIT 1');
                $row->execute(array($_SESSION['reset_email']));
                $name = $row->fetchColumn();
                send_forgot_password_otp($_SESSION['reset_email'], $name ? $name : 'Student', $otp);
            }
            $page_ok = 'A new OTP has been sent to your email.';

        } else {
            $otp_input = trim(isset($_POST['otp']) ? $_POST['otp'] : '');
            if ($otp_input === '') {
                $page_error = 'Please enter the OTP.';
            } elseif (!preg_match('/^\d{6}$/', $otp_input)) {
                $page_error = 'OTP must be 6 digits.';
            } else {
                if ($type === 'register') {
                    $result = verify_registration_otp($otp_input);
                    if ($result['success']) {
                        $reg = complete_registration(get_client_ip());
                        if ($reg['success']) {
                            flash('success', 'Account verified! A welcome email has been sent. Please log in.');
                            header('Location: login.php');
                            ob_end_flush(); exit;
                        } else {
                            $page_error = $reg['error'];
                        }
                    } else {
                        $page_error = $result['error'];
                        if (strpos($page_error, 'register again') !== false) {
                            header('Location: register.php');
                            ob_end_flush(); exit;
                        }
                    }
                } else {
                    $result = verify_reset_otp($otp_input);
                    if ($result['success']) {
                        header('Location: reset_password.php');
                        ob_end_flush(); exit;
                    } else {
                        $page_error = $result['error'];
                        if (strpos($page_error, 'start over') !== false || strpos($page_error, 'request') !== false) {
                            header('Location: forgot_password.php');
                            ob_end_flush(); exit;
                        }
                    }
                }
            }
        }
    }
}

$csrf        = csrf_token();
$masked_email = '';
if ($type === 'register' && !empty($_SESSION['reg_email'])) {
    $parts = explode('@', $_SESSION['reg_email']);
    $masked_email = substr($parts[0], 0, 2) . str_repeat('*', max(2, strlen($parts[0])-2)) . '@' . $parts[1];
}
if ($type === 'forgot' && !empty($_SESSION['reset_email'])) {
    $parts = explode('@', $_SESSION['reset_email']);
    $masked_email = substr($parts[0], 0, 2) . str_repeat('*', max(2, strlen($parts[0])-2)) . '@' . $parts[1];
}

$title    = $type === 'register' ? 'Verify Your Email' : 'Enter Reset OTP';
$subtitle = $type === 'register' ? 'Complete your registration' : 'Reset your password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($title); ?> — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-brand">
    <div class="brand-logo">
      <div class="brand-logo-mark">S</div>
      <span class="brand-logo-name">StudentPortal</span>
    </div>
    <h1 class="brand-headline">Check your<br><span>inbox.</span></h1>
    <p class="brand-sub">We sent a 6-digit OTP to your email address. It expires in <?php echo OTP_EXPIRY_MINUTES; ?> minutes.</p>
    <ul class="brand-features">
      <li class="brand-feature"><div class="brand-feature-dot"></div>OTP expires in <?php echo OTP_EXPIRY_MINUTES; ?> minutes</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Maximum 5 attempts allowed</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Never share your OTP with anyone</li>
    </ul>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-wrap">
      <h2 class="auth-form-title"><?php echo e($title); ?></h2>
      <p class="auth-form-subtitle">
        We sent a 6-digit code to <strong><?php echo e($masked_email); ?></strong>
      </p>

      <?php if ($page_error): ?>
        <div class="alert alert-error"><span class="alert-icon">&#x2715;</span> <?php echo e($page_error); ?></div>
      <?php endif; ?>
      <?php if ($page_ok): ?>
        <div class="alert alert-success"><span class="alert-icon">&#x2713;</span> <?php echo e($page_ok); ?></div>
      <?php endif; ?>

      <form method="POST" action="verify_otp.php?type=<?php echo e($type); ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
        <input type="hidden" name="action" value="verify">

        <div class="form-group">
          <label class="form-label" for="otp">6-Digit OTP</label>
          <input class="form-input otp-input" type="text" id="otp" name="otp"
            placeholder="Enter 6-digit OTP"
            maxlength="6" inputmode="numeric" pattern="\d{6}"
            autocomplete="one-time-code"
            style="font-size:24px;letter-spacing:8px;text-align:center;font-weight:700" required>
        </div>

        <button type="submit" class="btn btn-primary">
          <?php echo $type === 'register' ? 'Verify & Create Account' : 'Verify OTP'; ?>
        </button>
      </form>

      <div style="text-align:center;margin-top:20px">
        <form method="POST" action="verify_otp.php?type=<?php echo e($type); ?>" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-ghost" style="width:auto">
            &#8635; Resend OTP
          </button>
        </form>
      </div>

      <p class="auth-switch">
        <?php if ($type === 'register'): ?>
          Wrong email? <a href="register.php">Go back</a>
        <?php else: ?>
          <a href="forgot_password.php">Request a new OTP</a>
        <?php endif; ?>
      </p>
    </div>
  </main>

</div>
<script src="assets/js/app.js"></script>
<script>
var otpInput = document.getElementById('otp');
if (otpInput) {
    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
    });
}
</script>
<?php ob_end_flush(); ?>
</body>
</html>
