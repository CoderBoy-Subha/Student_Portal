<?php
// ============================================================
//  includes/mailer.php — HTML email delivery
//  Tries SMTP first (SimpleSMTP), falls back to mail()
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/smtp.php';

function send_email($to, $to_name, $subject, $html_body)
{
    // ── SMTP (real delivery — works on localhost + InfinityFree) ─────────
    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        $timeout = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 10;
        $smtp    = new SimpleSMTP(
            SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION, $timeout
        );
        if ($smtp->send(MAIL_FROM, MAIL_FROM_NAME, $to, $to_name, $subject, $html_body)) {
            return true;
        }
        error_log('[mailer/SMTP] ' . $smtp->last_error . ' | To: ' . $to);
    }

    // ── Fallback: PHP mail() ──────────────────────────────────────────────
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM . "\r\n";
    $result   = @mail($to, $subject, $html_body, $headers);
    if (!$result) {
        error_log('[mailer/mail()] Failed | To: ' . $to . ' | ' . $subject);
    }
    return $result;
}

function send_registration_otp($to_email, $to_name, $otp)
{
    $subject = 'Your StudentPortal verification code: ' . $otp;
    $body    = email_template(
        'Verify Your Email Address', $to_name,
        '<p style="font-size:15px;color:#4a5578;margin:0 0 24px">
            Thank you for registering with <strong>StudentPortal</strong>.<br>
            Use the OTP below to verify your email address.
         </p>
         <div style="text-align:center;margin:32px 0">
           <div style="display:inline-block;background:#f0f4ff;border:2px dashed #4f6ef7;border-radius:12px;padding:20px 48px">
             <div style="font-size:11px;font-weight:600;color:#8892b0;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px">Your OTP</div>
             <div style="font-size:40px;font-weight:800;color:#1a3260;letter-spacing:10px">' . $otp . '</div>
           </div>
         </div>
         <p style="font-size:13px;color:#8892b0;text-align:center;margin:0">
           Expires in <strong>' . OTP_EXPIRY_MINUTES . ' minutes</strong>. Never share this code.
         </p>'
    );
    return send_email($to_email, $to_name, $subject, $body);
}

function send_welcome_email($to_email, $to_name)
{
    $subject = 'Welcome to StudentPortal, ' . $to_name . '!';
    $body    = email_template(
        'Welcome Aboard!', $to_name,
        '<p style="font-size:15px;color:#4a5578;margin:0 0 20px">
            Your account has been <strong>verified and activated</strong>. You can now log in.
         </p>
         <div style="background:#f0f4ff;border-radius:10px;padding:20px 24px;margin:24px 0">
           <div style="font-size:13px;font-weight:600;color:#8892b0;margin-bottom:4px">Registered Email</div>
           <div style="font-size:15px;color:#1a3260;font-weight:600">' . htmlspecialchars($to_email, ENT_QUOTES, 'UTF-8') . '</div>
         </div>
         <div style="text-align:center;margin:32px 0">
           <a href="' . APP_URL . '/login.php"
              style="background:#4f6ef7;color:white;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block">
             Log In to Dashboard
           </a>
         </div>
         <p style="font-size:13px;color:#8892b0;margin:0">
           If you did not create this account, please ignore this email.
         </p>'
    );
    return send_email($to_email, $to_name, $subject, $body);
}

function send_forgot_password_otp($to_email, $to_name, $otp)
{
    $subject = 'Your StudentPortal password reset code: ' . $otp;
    $body    = email_template(
        'Password Reset Request', $to_name,
        '<p style="font-size:15px;color:#4a5578;margin:0 0 24px">
            We received a request to reset your password. Use the OTP below.
         </p>
         <div style="text-align:center;margin:32px 0">
           <div style="display:inline-block;background:#fff8f0;border:2px dashed #f97316;border-radius:12px;padding:20px 48px">
             <div style="font-size:11px;font-weight:600;color:#8892b0;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px">Reset OTP</div>
             <div style="font-size:40px;font-weight:800;color:#92400e;letter-spacing:10px">' . $otp . '</div>
           </div>
         </div>
         <p style="font-size:13px;color:#8892b0;text-align:center;margin:0">
           Expires in <strong>' . OTP_EXPIRY_MINUTES . ' minutes</strong>.<br>
           If you did not request this, your account is safe — ignore this email.
         </p>'
    );
    return send_email($to_email, $to_name, $subject, $body);
}

function email_template($title, $name, $content)
{
    return '<!DOCTYPE html><html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f7f8fc;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fc;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:560px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(15,30,60,.08);overflow:hidden">
<tr><td style="background:#0f1e3c;padding:28px 40px">
  <span style="display:inline-block;background:#4f6ef7;border-radius:8px;width:36px;height:36px;text-align:center;line-height:36px;font-size:18px;font-weight:900;color:white;vertical-align:middle">S</span>
  <span style="font-size:18px;font-weight:700;color:white;margin-left:10px;vertical-align:middle">StudentPortal</span>
</td></tr>
<tr><td style="padding:36px 40px 20px">
  <h1 style="font-size:22px;font-weight:800;color:#0f1e3c;margin:0 0 8px">' . $title . '</h1>
  <p style="font-size:14px;color:#8892b0;margin:0 0 24px">Hi <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
  ' . $content . '
</td></tr>
<tr><td style="background:#f7f8fc;padding:20px 40px;border-top:1px solid #e2e6f0">
  <p style="font-size:12px;color:#8892b0;margin:0;text-align:center">
    &copy; ' . date('Y') . ' StudentPortal &nbsp;&middot;&nbsp; Automated email — do not reply.
  </p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>';
}
