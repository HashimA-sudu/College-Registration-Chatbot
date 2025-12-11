<?php
session_start();
require_once __DIR__ . '/view/conn.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['admin_id'];
$user_email = $_SESSION['admin_email'] ?? '';

// Get user stats
$totalChats = 0;
$stmt = $conn->prepare("SELECT COUNT(DISTINCT message_number) as total FROM user_inquiries WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalChats = $row['total'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - KFU Course Assistant</title>
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
      max-width: 600px;
      margin: 0 auto;
    }

    .profile-card {
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .profile-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      font-weight: 600;
      color: white;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .profile-name {
      font-size: 28px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 8px;
    }

    .profile-email {
      font-size: 16px;
      color: #6b7280;
    }

    .profile-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
      padding: 24px;
      border-radius: 12px;
      text-align: center;
    }

    .stat-value {
      font-size: 32px;
      font-weight: 700;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 8px;
    }

    .stat-label {
      font-size: 14px;
      color: #6b7280;
      font-weight: 500;
    }

    .profile-actions {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .btn {
      padding: 14px 24px;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    .btn-logout {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
    }

    .btn-logout:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }
  </style>
  <?php
  if(isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ==1) {
      echo '<style>
      body {
        background: #121212;
      }
      .profile-card {
        background: #1e1e1e;
        box-shadow: 0 20px 60px rgba(0,0,0,0.7);
      }
      .profile-name, .stat-label {
        color: #e0e0e0;
      }
      .profile-email {
        color: #9ca3af;
      }
      .stat-card {
        background: #2d2d2d;
      }
      .btn-secondary {
        background: #333333;
        color: #e0e0e0;
      }
      .btn-secondary:hover {
        background: #444444;
      }
      </style>';
  }
  ?>
</head>
<body>
  <div class="container">
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-avatar"><?= strtoupper(substr($user_email, 0, 1)) ?></div>
        <h1 class="profile-name">My Profile</h1>
        <p class="profile-email"><?= htmlspecialchars($user_email) ?></p>
      </div>

      <div class="profile-stats">
        <div class="stat-card">
          <div class="stat-value"><?= $totalChats ?></div>
          <div class="stat-label">Total Conversations</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">✨</div>
          <div class="stat-label">Active Member</div>
        </div>
      </div>

      <div class="profile-actions">
        <a href="chatbot.php" class="btn btn-primary">💬 Back to Chat</a>
        <a href="settings.php" class="btn btn-secondary">⚙️ Settings</a>
        <a href="logout.php" class="btn btn-logout">🚪 Logout</a>
      </div>
    </div>
  </div>
</body>
</html>
