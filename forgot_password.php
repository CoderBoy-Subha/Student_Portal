<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

redirect_if_auth('dashboard.php');

$page_error = null;
$page_ok    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $page_error = 'Invalid submission. Please try again.';
    } else {
        $email = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
        if ($email === '') {
            $page_error = 'Please enter your email address.';
        } elseif (!is_valid_email($email)) {
            $page_error = 'Please enter a valid email address.';
        } else {
            // Always show success to prevent email enumeration
            $stmt = db()->prepare('SELECT id, full_name, account_status FROM students WHERE email=? LIMIT 1');
            $stmt->execute(array($email));
            $student = $stmt->fetch();

            if ($student && !in_array($student['account_status'], array('deleted','suspended'))) {
                $otp  = generate_otp();
                store_reset_otp($email, $otp);
                send_forgot_password_otp($email, $student['full_name'], $otp);
            }

            // Always redirect — never confirm if email exists
            flash('info', 'If that email is registered, an OTP has been sent to it.');
            header('Location: verify_otp.php?type=forgot');
            ob_end_flush(); exit;
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-brand">
    <div class="brand-logo">
      <div class="brand-logo-mark">S</div>
      <span class="brand-logo-name">StudentPortal</span>
    </div>
    <h1 class="brand-headline">Reset your<br><span>password.</span></h1>
    <p class="brand-sub">Enter your registered email and we'll send you a one-time OTP to reset your password.</p>
    <ul class="brand-features">
      <li class="brand-feature"><div class="brand-feature-dot"></div>OTP sent to your registered email</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Expires in <?php echo OTP_EXPIRY_MINUTES; ?> minutes</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Account stays secure throughout</li>
    </ul>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-wrap">
      <h2 class="auth-form-title">Forgot Password</h2>
      <p class="auth-form-subtitle">We'll send a 6-digit OTP to your registered email.</p>

      <?php if ($page_error): ?>
        <div class="alert alert-error"><span class="alert-icon">&#x2715;</span> <?php echo e($page_error); ?></div>
      <?php endif; ?>

      <form method="POST" action="forgot_password.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <div class="form-group">
          <label class="form-label" for="email">Registered Email Address</label>
          <input class="form-input" type="email" id="email" name="email"
            placeholder="you@example.com" maxlength="255" required autocomplete="email">
        </div>

        <button type="submit" class="btn btn-primary">Send OTP</button>
      </form>

      <p class="auth-switch">
        Remembered it? <a href="login.php">Log in</a>
        &nbsp;&middot;&nbsp;
        <a href="register.php">Create account</a>
      </p>
    </div>
  </main>

</div>
<script src="assets/js/app.js"></script>
<?php ob_end_flush(); ?>
</body>
</html>
