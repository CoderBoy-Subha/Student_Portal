<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

require_auth('login.php');

$student_id = current_user_id();
$student    = get_dashboard_data($student_id);

if (!$student) {
    logout_student();
    session_start();
    flash('error', 'Your account could not be found. Please contact support.');
    header('Location: login.php');
    ob_end_flush(); exit;
}

$recent_logins = get_recent_logins($student_id);
$member_since  = date('d M Y', strtotime($student['created_at']));
$last_login    = $student['last_login_at']
    ? date('d M Y, H:i', strtotime($student['last_login_at'])) . ' UTC'
    : 'First session';
$days_member  = (int)$student['days_member'];
$total_logins = (int)$student['total_logins'];

$parts    = explode(' ', trim($student['full_name']));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}
$first_name = $parts[0];

// Profile photo
$photo_path = !empty($student['photo_path'])
    ? APP_URL . '/' . ltrim($student['photo_path'], '/')
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — StudentPortal</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .profile-photo-banner {
      width: 90px; height: 90px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(255,255,255,0.3);
      box-shadow: 0 4px 16px rgba(0,0,0,0.25);
      flex-shrink: 0;
    }
    .profile-initials-banner {
      width: 90px; height: 90px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--indigo), #6e8fff);
      display: flex; align-items: center; justify-content: center;
      font-size: 28px; font-weight: 800; color: white;
      border: 3px solid rgba(255,255,255,0.3);
      box-shadow: 0 4px 16px rgba(0,0,0,0.25);
      flex-shrink: 0;
      letter-spacing: 1px;
    }
    .photo-upload-wrap {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 12px;
      background: var(--sky);
      border-radius: var(--radius);
      border: 1.5px dashed var(--border-dark);
    }
    .photo-preview {
      width: 80px; height: 80px;
      border-radius: 50%;
      background: var(--border);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
    }
    .photo-preview img {
      width: 100%; height: 100%;
      object-fit: cover;
    }
  </style>
</head>
<body class="is-dashboard">

<div id="session-warning" style="display:none;position:fixed;top:0;left:0;right:0;z-index:999;background:#78350f;color:white;padding:10px 24px;font-size:14px;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 2px 8px rgba(0,0,0,0.2)">
  <span>&#9203; Your session will expire in 5 minutes due to inactivity.</span>
  <div style="display:flex;gap:10px;flex-shrink:0">
    <button id="dismiss-warning" style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);color:white;padding:5px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">Stay logged in</button>
    <a href="logout.php" style="border:1px solid rgba(255,255,255,0.4);color:white;padding:5px 14px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none">Log out</a>
  </div>
</div>

<div class="dashboard-page">

  <header class="topbar">
    <a href="dashboard.php" class="topbar-logo">
      <div class="topbar-logo-mark">S</div>
      <span class="topbar-logo-name">StudentPortal</span>
    </a>
    <div class="topbar-right">
      <div class="topbar-user">
        <?php if ($photo_path): ?>
          <img src="<?php echo e($photo_path); ?>" alt="Profile"
               style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.2)">
        <?php else: ?>
          <div class="topbar-avatar"><?php echo e($initials); ?></div>
        <?php endif; ?>
        <span class="topbar-username"><?php echo e($student['full_name']); ?></span>
      </div>
      <form method="POST" action="logout.php" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <button type="submit" class="btn-danger-ghost">Log out</button>
      </form>
    </div>
  </header>

  <main class="dashboard-content">

    <!-- Welcome Banner with profile photo -->
    <section class="welcome-banner">
      <div class="welcome-text">
        <p class="welcome-greeting">Dashboard</p>
        <h1 class="welcome-name">Welcome back, <?php echo e($first_name); ?>! &#128075;</h1>
        <p class="welcome-meta">
          Member for <?php echo $days_member; ?> day<?php echo $days_member !== 1 ? 's' : ''; ?> &nbsp;&middot;&nbsp;
          <?php echo $total_logins; ?> successful login<?php echo $total_logins !== 1 ? 's' : ''; ?>
        </p>
      </div>

      <!-- Profile Photo in banner (replaces "Active Account" badge) -->
      <div style="position:relative;z-index:1;flex-shrink:0;text-align:center">
        <?php if ($photo_path): ?>
          <img src="<?php echo e($photo_path); ?>"
               alt="Profile photo of <?php echo e($student['full_name']); ?>"
               class="profile-photo-banner">
        <?php else: ?>
          <div class="profile-initials-banner"><?php echo e($initials); ?></div>
        <?php endif; ?>
        <div style="margin-top:8px">
          <span style="display:inline-flex;align-items:center;gap:5px;background:rgba(79,110,247,0.25);border:1px solid rgba(79,110,247,0.4);border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600;color:#a5b4fc">
            <span style="width:6px;height:6px;background:#4ade80;border-radius:50%;display:inline-block;box-shadow:0 0 5px #4ade80"></span>
            Active
          </span>
        </div>
      </div>
    </section>

    <!-- Stat cards -->
    <section>
      <div class="stats-grid">
        <div class="stat-card accent">
          <div class="stat-card-icon">&#128100;</div>
          <div class="stat-card-label">Full Name</div>
          <div class="stat-card-value"><?php echo e($student['full_name']); ?></div>
        </div>
        <div class="stat-card accent">
          <div class="stat-card-icon">&#9993;</div>
          <div class="stat-card-label">Email Address</div>
          <div class="stat-card-value" style="font-size:15px"><?php echo e($student['email']); ?></div>
        </div>
        <div class="stat-card accent">
          <div class="stat-card-icon">&#128197;</div>
          <div class="stat-card-label">Member Since</div>
          <div class="stat-card-value"><?php echo e($member_since); ?></div>
          <div class="stat-card-sub"><?php echo $days_member; ?> days ago</div>
        </div>
        <div class="stat-card accent">
          <div class="stat-card-icon">&#128273;</div>
          <div class="stat-card-label">Total Logins</div>
          <div class="stat-card-value"><?php echo $total_logins; ?></div>
          <div class="stat-card-sub">Successful sessions</div>
        </div>
      </div>
    </section>

    <div class="cards-row">

      <section class="info-card">
        <div class="info-card-header"><span>&#128737;</span> Account Details</div>
        <div class="info-card-body">
          <dl>
            <div class="info-row">
              <dt class="info-row-label">Status</dt>
              <dd class="info-row-value"><span class="badge badge-success">&#9679; Active</span></dd>
            </div>
            <div class="info-row">
              <dt class="info-row-label">Account ID</dt>
              <dd class="info-row-value" style="font-family:monospace">#<?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></dd>
            </div>
            <div class="info-row">
              <dt class="info-row-label">Email</dt>
              <dd class="info-row-value"><?php echo e($student['email']); ?></dd>
            </div>
            <div class="info-row">
              <dt class="info-row-label">Registered</dt>
              <dd class="info-row-value"><?php echo e($member_since); ?></dd>
            </div>
            <div class="info-row">
              <dt class="info-row-label">Last Login</dt>
              <dd class="info-row-value"><?php echo e($last_login); ?></dd>
            </div>
            <?php if ($student['last_login_ip']): ?>
            <div class="info-row">
              <dt class="info-row-label">Last Login IP</dt>
              <dd class="info-row-value" style="font-family:monospace;font-size:12px"><?php echo e($student['last_login_ip']); ?></dd>
            </div>
            <?php endif; ?>
          </dl>
        </div>
      </section>

      <section class="info-card">
        <div class="info-card-header"><span>&#128203;</span> Recent Login Activity</div>
        <div class="info-card-body">
          <?php if (empty($recent_logins)): ?>
            <div class="empty-state">No login history yet.</div>
          <?php else: ?>
            <ul class="activity-list">
              <?php foreach ($recent_logins as $entry): ?>
                <?php
                  $res = $entry['attempt_result'];
                  if ($res === 'success') {
                      $result_class = 'success';
                      $result_label = 'Successful login';
                  } elseif ($res === 'locked') {
                      $result_class = 'locked';
                      $result_label = 'Blocked (locked)';
                  } else {
                      $result_class = 'failure';
                      $result_label = 'Failed attempt';
                  }
                  $when = date('d M, H:i', strtotime($entry['attempted_at']));
                ?>
                <li class="activity-item">
                  <div class="activity-dot <?php echo $result_class; ?>"></div>
                  <div class="activity-text">
                    <div class="activity-result"><?php echo e($result_label); ?></div>
                    <div class="activity-ip"><?php echo e($entry['ip_address']); ?></div>
                  </div>
                  <div class="activity-time"><?php echo e($when); ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </section>

    </div>

    <section class="info-card" style="margin-bottom:0">
      <div class="info-card-header"><span>&#128161;</span> Security Tips</div>
      <div class="info-card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <div style="display:flex;gap:10px;align-items:flex-start;padding:4px 0">
          <span style="font-size:18px;flex-shrink:0">&#128272;</span>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:2px">Use a strong password</div>
            <div style="font-size:12px;color:var(--text-secondary)">Combine uppercase, numbers and symbols.</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:4px 0">
          <span style="font-size:18px;flex-shrink:0">&#128682;</span>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:2px">Always log out on shared devices</div>
            <div style="font-size:12px;color:var(--text-secondary)">Session auto-expires after 30 minutes.</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:4px 0">
          <span style="font-size:18px;flex-shrink:0">&#128065;</span>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:2px">Monitor your login activity</div>
            <div style="font-size:12px;color:var(--text-secondary)">Review the activity panel above for anything unfamiliar.</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <footer style="text-align:center;padding:24px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border)">
    StudentPortal &copy; <?php echo date('Y'); ?> &nbsp;&middot;&nbsp; Secured with bcrypt &amp; PDO &nbsp;&middot;&nbsp;
    <a href="logout.php" style="color:var(--indigo);text-decoration:none">Log out</a>
  </footer>

</div>

<script src="assets/js/app.js"></script>
<?php ob_end_flush(); ?>
</body>
</html>
