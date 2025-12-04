<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

// ==============================
// 🔧 Load Email Settings from mail.txt
// ==============================
function loadMailConfig(): array {
    $configFile = __DIR__ . '/../../mail.txt';
    $config = [
        'MAIL_HOST' => 'smtp.gmail.com',
        'MAIL_PORT' => 587,
        'MAIL_SECURE' => 'tls',
        'MAIL_USER' => 'your-email@gmail.com',
        'MAIL_PASS' => 'your-app-password-here',
        'MAIL_FROM' => 'your-email@gmail.com',
        'MAIL_FROM_NAME' => 'KFU Chatbot System'
    ];

    if (!file_exists($configFile)) {
        error_log("Warning: mail.txt not found. Using default values.");
        return $config;
    }

    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (isset($config[$key])) {
                $config[$key] = $value;
            }
        }
    }

    return $config;
}

// ==============================
// 🚚 Create PHPMailer instance
// ==============================
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    // SMTP Configuration (from "use for mail.txt")
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noortion@gmail.com';
    $mail->Password   = 'zgpb fcuq nzmb ipoq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // General settings
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('noortion@gmail.com', 'KFU Chatbot System');
    $mail->isHTML(true);

    return $mail;
}

// ==============================
// 📧 Build Email HTML Template
// ==============================
function buildEmailHtml(string $title, string $bodyHtml): string {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return "
<!doctype html>
<html lang=\"en\" dir=\"ltr\">
  <head>
    <meta charset=\"UTF-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <title>{$safeTitle}</title>
  </head>
  <body style=\"margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;\">
    <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background:#f4f4f4;\">
      <tr>
        <td align=\"center\" style=\"padding:20px 10px;\">
          <table role=\"presentation\" width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
                 style=\"width:600px; max-width:100%; background:#ffffff; border:2px solid #007a3d; border-radius:12px; overflow:hidden;\">

            <!-- Header -->
            <tr>
              <td align=\"center\" style=\"padding:30px 24px 10px 24px; background:linear-gradient(135deg, #007a3d 0%, #005a2d 100%);\">
                <h2 style=\"font-family:Arial,sans-serif; color:#ffffff; margin:0; font-size:24px;\">{$safeTitle}</h2>
              </td>
            </tr>

            <!-- Content -->
            <tr>
              <td align=\"left\" style=\"padding:30px 24px; font-family:Arial,sans-serif; font-size:15px; color:#333; line-height:1.6;\">
                {$bodyHtml}
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td align=\"center\" style=\"padding:20px 24px; font-family:Arial,sans-serif; font-size:12px; color:#666; border-top:1px solid #e0e0e0;\">
                <p style=\"margin:0;\">King Faisal University - College Registration Chatbot</p>
                <p style=\"margin:5px 0 0 0;\">© " . date('Y') . " All rights reserved</p>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>";
}

// ==============================
// ✉️ Send Welcome Email
// ==============================
function sendWelcomeEmail($toEmail, $name): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $name);
        $mail->Subject = "Welcome to KFU College Registration Chatbot!";

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $body = "
        <p>Hello <strong style='color:#007a3d;'>{$safeName}</strong>!</p>
        <p>Welcome to the King Faisal University College Registration Chatbot! 🎉</p>
        <p>Your account has been successfully created. You can now:</p>
        <ul style='line-height:1.8;'>
            <li>Ask about course details and schedules</li>
            <li>Get information about professors</li>
            <li>Learn about the Banner system</li>
            <li>Generate course schedules</li>
        </ul>
        <p>We're here to help make your college registration experience easier!</p>
        <p style='margin-top:20px;'>Best regards,<br><strong>KFU Chatbot Team</strong></p>
        ";

        $mail->Body = buildEmailHtml("Welcome to KFU Chatbot!", $body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Welcome email failed: " . $e->getMessage());
        return false;
    }
}

// ==============================
// 🔐 Send Password Reset Email
// ==============================
function sendPasswordResetEmail($toEmail, $name, $resetCode): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $name);
        $mail->Subject = "Password Reset Request - KFU Chatbot";

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($resetCode, ENT_QUOTES, 'UTF-8');

        $body = "
        <p>Hello <strong>{$safeName}</strong>,</p>
        <p>You requested to reset your password for KFU Chatbot.</p>
        <p>Your password reset code is:</p>
        <div style='text-align:center; margin:20px 0;'>
            <div style='display:inline-block; background:#e0f7e9; color:#006c35; padding:15px 30px; border-radius:8px; font-size:24px; font-weight:bold; letter-spacing:2px;'>
                {$safeCode}
            </div>
        </div>
        <p style='color:#666; font-size:14px;'>This code will expire in 10 minutes.</p>
        <p>If you didn't request this, please ignore this email.</p>
        <p style='margin-top:20px;'>Best regards,<br><strong>KFU Chatbot Team</strong></p>
        ";

        $mail->Body = buildEmailHtml("Password Reset Request", $body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Password reset email failed: " . $e->getMessage());
        return false;
    }
}

// ==============================
// 📨 Send Login Notification
// ==============================
function sendLoginNotificationEmail($toEmail, $name, $ipAddress, $userAgent): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $name);
        $mail->Subject = "New Login to Your KFU Chatbot Account";

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeIP = htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8');
        $safeUA = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
        $loginTime = date('F j, Y \a\t g:i A');

        $body = "
        <p>Hello <strong>{$safeName}</strong>,</p>
        <p>We detected a new login to your account:</p>
        <div style='background:#f8f9fa; padding:15px; border-radius:8px; margin:15px 0;'>
            <p style='margin:5px 0;'><strong>Time:</strong> {$loginTime}</p>
            <p style='margin:5px 0;'><strong>IP Address:</strong> {$safeIP}</p>
            <p style='margin:5px 0;'><strong>Device:</strong> {$safeUA}</p>
        </div>
        <p>If this was you, no action is needed.</p>
        <p style='color:#d32f2f;'>If you didn't log in, please change your password immediately and contact support.</p>
        <p style='margin-top:20px;'>Best regards,<br><strong>KFU Chatbot Team</strong></p>
        ";

        $mail->Body = buildEmailHtml("New Login Alert", $body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Login notification email failed: " . $e->getMessage());
        return false;
    }
}
?>
