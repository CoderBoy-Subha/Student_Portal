<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

redirect_if_auth('dashboard.php');

// Must have verified OTP first
if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    ob_end_flush(); exit;
}

$errors     = array();
$page_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $page_error = 'Invalid submission. Please try again.';
    } else {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm  = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        $pw_errors = validate_password($password);
        if (!empty($pw_errors)) {
            $errors['password'] = $pw_errors[0];
        }
        if ($confirm === '') {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $result = reset_password($password, get_client_ip());
            if ($result['success']) {
                flash('success', 'Password reset successfully! Please log in with your new password.');
                header('Location: login.php');
                ob_end_flush(); exit;
            } else {
                $page_error = $result['error'];
            }
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
  <title>Reset Password — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-brand">
    <div class="brand-logo">
      <div class="brand-logo-mark">S</div>
      <span class="brand-logo-name">StudentPortal</span>
    </div>
    <h1 class="brand-headline">Create a<br><span>new password.</span></h1>
    <p class="brand-sub">Choose a strong password with at least 8 characters, one uppercase letter, and one number.</p>
    <ul class="brand-features">
      <li class="brand-feature"><div class="brand-feature-dot"></div>Min. 8 characters</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>At least 1 uppercase letter</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>At least 1 number</li>
    </ul>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-wrap">
      <h2 class="auth-form-title">Set New Password</h2>
      <p class="auth-form-subtitle">
        Resetting password for <strong><?php echo e($_SESSION['reset_email']); ?></strong>
      </p>

      <?php if ($page_error): ?>
        <div class="alert alert-error"><span class="alert-icon">&#x2715;</span> <?php echo e($page_error); ?></div>
      <?php endif; ?>

      <form method="POST" action="reset_password.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <div class="form-group">
          <label class="form-label" for="password">New Password</label>
          <div class="input-wrap">
            <input class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
              type="password" id="password" name="password"
              placeholder="Min. 8 chars, 1 uppercase, 1 number" maxlength="128" required>
            <button type="button" class="toggle-pw" data-target="#password">&#128065;</button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="form-error"><?php echo e($errors['password']); ?></p>
          <?php endif; ?>
          <div class="pw-strength">
            <div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>
            <span class="pw-strength-label"></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm New Password</label>
          <div class="input-wrap">
            <input class="form-input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
              type="password" id="confirm_password" name="confirm_password"
              placeholder="Repeat your new password" maxlength="128" required>
            <button type="button" class="toggle-pw" data-target="#confirm_password">&#128065;</button>
          </div>
          <?php if (isset($errors['confirm_password'])): ?>
            <p class="form-error"><?php echo e($errors['confirm_password']); ?></p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Reset Password</button>
      </form>

      <p class="auth-switch"><a href="login.php">Back to Login</a></p>
    </div>
  </main>

</div>
<script src="assets/js/app.js"></script>
<?php ob_end_flush(); ?>
</body>
</html>
