<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

redirect_if_auth('dashboard.php');

$errors     = array();
$old_email  = '';
$page_error = null;
$is_locked  = false;

$flash_success = get_flash('success');
$flash_error   = get_flash('error');
$flash_info    = get_flash('info');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $page_error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $email    = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $old_email = $email;

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!is_valid_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if (empty($errors)) {
            $result = authenticate_student($email, $password, get_client_ip(), get_user_agent());
            if ($result['success']) {
                session_regenerate_id(false);
                $_SESSION['student_id']    = $result['student']['id'];
                $_SESSION['student_name']  = $result['student']['full_name'];
                $_SESSION['student_email'] = $result['student']['email'];
                $_SESSION['student_photo'] = $result['student']['photo_path'];
                $_SESSION['last_active']   = time();
                header('Location: dashboard.php');
                ob_end_flush(); exit;
            } else {
                $is_locked  = isset($result['locked']) ? $result['locked'] : false;
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
  <title>Log In — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-brand">
    <div class="brand-logo">
      <div class="brand-logo-mark">S</div>
      <span class="brand-logo-name">StudentPortal</span>
    </div>
    <h1 class="brand-headline">Good to see<br>you <span>again.</span></h1>
    <p class="brand-sub">Log in to pick up right where you left off.</p>
    <ul class="brand-features">
      <li class="brand-feature"><div class="brand-feature-dot"></div>Sessions secured with HttpOnly cookies</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Auto-lock after 5 failed attempts</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>30-minute idle session timeout</li>
    </ul>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-wrap">
      <h2 class="auth-form-title">Welcome back</h2>
      <p class="auth-form-subtitle">Enter your credentials to access your dashboard.</p>

      <?php if ($flash_success): ?>
        <div class="alert alert-success"><span class="alert-icon">&#x2713;</span> <?php echo e($flash_success); ?></div>
      <?php endif; ?>
      <?php if ($flash_error): ?>
        <div class="alert alert-error"><span class="alert-icon">&#x2715;</span> <?php echo e($flash_error); ?></div>
      <?php endif; ?>
      <?php if ($flash_info): ?>
        <div class="alert alert-warning"><span class="alert-icon">&#8505;</span> <?php echo e($flash_info); ?></div>
      <?php endif; ?>
      <?php if ($page_error): ?>
        <div class="alert <?php echo $is_locked ? 'alert-warning' : 'alert-error'; ?>">
          <span class="alert-icon"><?php echo $is_locked ? '&#x1F512;' : '&#x2715;'; ?></span>
          <?php echo e($page_error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>"
            type="email" id="email" name="email"
            value="<?php echo e($old_email); ?>"
            placeholder="you@example.com" maxlength="255" required autocomplete="email">
          <?php if (isset($errors['email'])): ?>
            <p class="form-error"><?php echo e($errors['email']); ?></p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">
            Password
            <a href="forgot_password.php" style="float:right;font-size:12px;color:var(--indigo);text-decoration:none;font-weight:500">
              Forgot password?
            </a>
          </label>
          <div class="input-wrap">
            <input class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
              type="password" id="password" name="password"
              placeholder="Your password" maxlength="128" required autocomplete="current-password">
            <button type="button" class="toggle-pw" data-target="#password">&#128065;</button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="form-error"><?php echo e($errors['password']); ?></p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" <?php echo $is_locked ? 'disabled' : ''; ?>>
          <?php echo $is_locked ? 'Account Locked' : 'Log In'; ?>
        </button>
      </form>

      <p class="auth-switch">Don't have an account? <a href="register.php">Create one free</a></p>
    </div>
  </main>

</div>
<script src="assets/js/app.js"></script>
<?php ob_end_flush(); ?>
</body>
</html>
