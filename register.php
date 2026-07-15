<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

redirect_if_auth('dashboard.php');

$errors     = array();
$old        = array();
$page_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $page_error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $name    = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $email   = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
        $password= isset($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $old     = array('full_name' => $name, 'email' => $email);

        if ($name === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (strlen($name) < 2) {
            $errors['full_name'] = 'Name must be at least 2 characters.';
        } elseif (strlen($name) > 150) {
            $errors['full_name'] = 'Name must not exceed 150 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!is_valid_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (email_exists($email)) {
            $errors['email'] = 'That email is already registered. Try logging in.';
        }

        $pw_errors = validate_password($password);
        if (!empty($pw_errors)) {
            $errors['password'] = $pw_errors[0];
        }

        if ($confirm === '') {
            $errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        $photo_stage = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $errors['photo'] = 'Upload error. Please try again.';
            } elseif ($_FILES['photo']['size'] > MAX_FILE_SIZE) {
                $errors['photo'] = 'Photo must be under 2 MB.';
            } else {
                if (!function_exists('finfo_open')) {
                    $info = @getimagesize($_FILES['photo']['tmp_name']);
                    $mime = $info ? $info['mime'] : '';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($_FILES['photo']['tmp_name']);
                }
                $allowed = array('image/jpeg'=>'jpg','image/png'=>'png',
                                 'image/gif'=>'gif','image/webp'=>'webp');
                if (!isset($allowed[$mime])) {
                    $errors['photo'] = 'Only JPG, PNG, GIF and WEBP images are allowed.';
                } else {
                    ensure_upload_dirs();
                    $ext      = $allowed[$mime];
                    $filename = 'stage_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest     = UPLOAD_STAGE . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        $photo_stage = $dest;
                    } else {
                        $errors['photo'] = 'Could not save photo. Check folder permissions.';
                    }
                }
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, array('cost' => BCRYPT_COST));
            $otp  = generate_otp();
            store_registration_otp($email, $name, $hash, $otp, $photo_stage);

            send_registration_otp($email, $name, $otp);

            header('Location: verify_otp.php?type=register');
            ob_end_flush();
            exit;
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
  <title>Create Account — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-brand">
    <div class="brand-logo">
      <div class="brand-logo-mark">S</div>
      <span class="brand-logo-name">StudentPortal</span>
    </div>
    <h1 class="brand-headline">Your learning<br><span>journey starts</span><br>here.</h1>
    <p class="brand-sub">One account. Instant access to your personalised student dashboard, activity history, and more.</p>
    <ul class="brand-features">
      <li class="brand-feature"><div class="brand-feature-dot"></div>Secure bcrypt encryption</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Email OTP verification</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Personalised dashboard from day one</li>
      <li class="brand-feature"><div class="brand-feature-dot"></div>Profile photo support</li>
    </ul>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-wrap">
      <h2 class="auth-form-title">Create your account</h2>
      <p class="auth-form-subtitle">Fill in the details below. We'll send an OTP to verify your email.</p>

      <?php if ($page_error): ?>
        <div class="alert alert-error"><span class="alert-icon">&#x2715;</span> <?php echo e($page_error); ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <!-- Profile Photo (optional) -->
        <div class="form-group">
          <label class="form-label">Profile Photo <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
          <div class="photo-upload-wrap" id="photoWrap">
            <div class="photo-preview" id="photoPreview">
              <span style="font-size:32px">&#128247;</span>
            </div>
            <div class="photo-upload-info">
              <label for="photo" class="btn btn-ghost" style="cursor:pointer;display:inline-block">
                Choose Photo
              </label>
              <input type="file" id="photo" name="photo" accept="image/*"
                     style="display:none" onchange="previewPhoto(this)">
              <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0">
                JPG, PNG, GIF or WEBP &mdash; max 2 MB
              </p>
            </div>
          </div>
          <?php if (isset($errors['photo'])): ?>
            <p class="form-error"><?php echo e($errors['photo']); ?></p>
          <?php endif; ?>
        </div>

        <hr class="form-divider">

        <div class="form-group">
          <label class="form-label" for="full_name">Full Name</label>
          <input class="form-input <?php echo isset($errors['full_name']) ? 'error' : ''; ?>"
            type="text" id="full_name" name="full_name"
            value="<?php echo e(isset($old['full_name']) ? $old['full_name'] : ''); ?>"
            placeholder="e.g. Priya Sharma" maxlength="150" required>
          <?php if (isset($errors['full_name'])): ?>
            <p class="form-error"><?php echo e($errors['full_name']); ?></p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>"
            type="email" id="email" name="email"
            value="<?php echo e(isset($old['email']) ? $old['email'] : ''); ?>"
            placeholder="you@example.com" maxlength="255" required>
          <?php if (isset($errors['email'])): ?>
            <p class="form-error"><?php echo e($errors['email']); ?></p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
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
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <div class="input-wrap">
            <input class="form-input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
              type="password" id="confirm_password" name="confirm_password"
              placeholder="Repeat your password" maxlength="128" required>
            <button type="button" class="toggle-pw" data-target="#confirm_password">&#128065;</button>
          </div>
          <?php if (isset($errors['confirm_password'])): ?>
            <p class="form-error"><?php echo e($errors['confirm_password']); ?></p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Send Verification OTP</button>
      </form>

      <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </main>

</div>
<script src="assets/js/app.js"></script>
<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('photoPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--indigo)">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php ob_end_flush(); ?>
</body>
</html>
