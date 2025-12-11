<?php
session_start();
require_once __DIR__ . '/view/conn.php';
require_once __DIR__ . '/view/jwt_helper.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $msg = 'Please fill required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Invalid email address.';
    } else {
        $sql = 'SELECT id, email, password_hash FROM admin_users WHERE email = ? LIMIT 1';
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($id, $dbEmail, $hash);
                    $stmt->fetch();
                    if ($hash && password_verify($password, $hash)) {
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = $id;
                        $_SESSION['admin_email'] = $dbEmail;

                        // Handle Remember Me
                        if (!empty($_POST['remember_me'])) {
                            require_once __DIR__ . '/view/remember_me_helper.php';
                            create_remember_login($conn, $id);
                        }

                        // Get JWT token from Node.js server
                        $jwtToken = getJWTToken($email, $password);
                        if ($jwtToken) {
                            storeJWTToken($jwtToken);
                        }

                        // Send login notification email (optional)
                        require_once __DIR__ . '/view/email_helper.php';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        sendLoginNotificationEmail($dbEmail, $dbEmail, $ipAddress, $userAgent);

                        header('Location: chatbot.php');
                        exit();
                    } else {
                        $msg = 'Incorrect email or password.';
                    }
                } else {
                    $msg = 'Incorrect email or password.';
                }
            } else {
                $msg = 'Login failed: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $msg = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <main class="container">
    <section class="card narrow">
      <h1 class="center">Login</h1>
      <form id="loginForm" class="stack" method="post" action="">
        <label>Email
          <input id="email" name="email" type="email" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label>
        <label>Password
          <input id="password" name="password" type="password" placeholder="••••••••" required>
        </label>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
          <input type="checkbox" name="remember_me" id="remember_me" value="1" style="width:auto; margin:0;">
          <span>Remember me for 60 days</span>
        </label>
        <button class="btn" type="submit">Sign in</button>
        <button class="muted center" onclick="window.location.href='register.php'; return false;">Go to sign up page</button>

        <p id="msg" style="color:#c00;background:#fff;padding:6px;border-radius:4px;text-align:center;font-size:large;">
          <?= htmlspecialchars($msg) ?>
        </p>
      </form>
    </section>
  </main>
</body>
</html>
