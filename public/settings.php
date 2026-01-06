<?php
session_start();
require_once __DIR__ . '/view/conn.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['admin_id'];
$user_email = $_SESSION['admin_email'] ?? '';


// Get user preferences
$darkMode = 0;

$stmt = $conn->prepare("SELECT dark_mode FROM admin_users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $darkMode = (int)($row['dark_mode'] ?? 0) ;
    }
    $stmt->close();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    
    $dark = isset($_POST['dark_mode']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE admin_users SET dark_mode = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $dark, $user_id);
        if ($stmt->execute()) {
            $success = "Settings updated successfully!";
            $_SESSION['dark_mode'] = $dark;
            $darkMode = $dark;
        } else {
            error_log('settings.php: Update execute failed: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log('settings.php: Failed to prepare update query: ' . $conn->error);
    }
}

// Handle clear chat history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    $stmt = $conn->prepare("DELETE FROM user_inquiries WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        $success = "Chat history cleared successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings - KFU Course Assistant</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 40px 20px;
    }

    .container {
      max-width: 700px;
      margin: 0 auto;
    }

    .settings-card {
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .settings-header {
      margin-bottom: 40px;
    }

    .settings-title {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .settings-subtitle {
      font-size: 14px;
      color: #6b7280;
    }

    .setting-section {
      margin-bottom: 32px;
      padding-bottom: 32px;
      border-bottom: 1px solid #e5e7eb;
    }

    .setting-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .section-description {
      font-size: 14px;
      color: #6b7280;
      margin-bottom: 16px;
    }

    .setting-option {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px;
      background: #f9fafb;
      border-radius: 10px;
      margin-bottom: 12px;
    }

    .option-label {
      font-size: 15px;
      color: #374151;
      font-weight: 500;
    }

    .option-description {
      font-size: 13px;
      color: #6b7280;
      margin-top: 4px;
    }

    .toggle-switch {
      position: relative;
      width: 48px;
      height: 26px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .toggle-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #e5e7eb;
      transition: 0.3s;
      border-radius: 34px;
    }

    .toggle-slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: 0.3s;
      border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .toggle-switch input:checked + .toggle-slider:before {
      transform: translateX(22px);
    }

    .btn {
      padding: 12px 24px;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      margin-right: 12px;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    .alert {
      padding: 14px 18px;
      border-radius: 10px;
      margin-bottom: 24px;
      font-size: 14px;
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #6ee7b7;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: white;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 20px;
      padding: 10px 16px;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
      transition: all 0.2s;
    }

    .back-link:hover {
      background: rgba(255,255,255,0.2);
    }
  </style>
  <?php
    if ($darkMode) {
        echo '<style>
    body {
      background: #121212;
      color: #e0e0e0;
    }
    .settings-card {
      color: #e0e0e0;
      background: #1e1e1e;
      box-shadow: 0 20px 60px rgba(0,0,0,0.7);
    }
    .setting-option {
    color: #e0e0e0;
      background: #2d2d2d;
    }
    .section-title, .option-label .settings-title {
      color: #e0e0e0;
    }
    .section-description, .option-description {
      color: #9ca3af;
    }
    .alert-success {
      background: #064e3b;
      color: #d1fae5;
      border: 1px solid #34d399;
    }
      h1, h2, h3, h4, h5, h6, p, label, span, .option-label, .option-description, .section-description , .settings-subtitle , .section-title {
        color: #e0e0e0;
      }
    </style>';
    }
  ?>
</head>
<body>
  <div class="container">
    <a href="chatbot.php" class="back-link">← Back to Chat</a>

    <div class="settings-card">
      <div class="settings-header">
        <h1 class="settings-title">⚙️ Settings</h1>
        <p class="settings-subtitle">Manage your preferences and account settings</p>
      </div>

      <?php if (isset($success)): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="setting-section">
        <div class="section-title">
          <span>👤</span>
          <span>Account Information</span>
        </div>
        <div class="section-description">Your account details</div>
        <div class="setting-option">
          <div>
            <div class="option-label">Email Address</div>
            <div class="option-description"><?= htmlspecialchars($user_email) ?></div>
          </div>
        </div>
      </div>


      <div class="setting-section">
        <div class="section-title">
          <span>🗑️</span>
          <span>Data Management</span>
        </div>
        <div class="section-description">Manage your chat history and data</div>
        <form method="POST" onsubmit="return confirm('Are you sure you want to clear all chat history? This cannot be undone.');">
          <button type="submit" name="clear_history" class="btn btn-danger">
            Clear Chat History
          </button>
        </form>
      </div>

      <form method="POST">
        <input type="hidden" name="update_settings" value="1">
        <input type="hidden" name="email_notifications" value="<?= $emailNotifications ?>">
        <input type="hidden" name="course_updates" value="<?= $courseUpdates ?>">

        <div class="setting-section">
          <div class="section-title">
            <span>🎨</span>
            <span>Appearance</span>
          </div>
          <div class="section-description">Customize your experience</div>
          <div class="setting-option">
            <div>
              <div class="option-label">Dark Mode</div>
              <div class="option-description">Switch to dark theme</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="dark_mode" <?= $darkMode ? 'checked' : '' ?> onchange="this.form.submit()">
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </form>

      <div style="margin-top: 32px; padding-top: 32px; border-top: 1px solid #e5e7eb;">
        <a href="chatbot.php" class="btn btn-secondary">Cancel</a>
        <a href="profile.php" class="btn btn-secondary">View Profile</a>
      </div>
    </div>
  </div>
</body>
</html>
