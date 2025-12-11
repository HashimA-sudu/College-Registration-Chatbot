<?php
include 'view/conn.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeat = $_POST['repeatPassword'] ?? '';

    if ($email === '' || $password === '' || $repeat === '') {
        $msg = 'Please fill required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Invalid email address.';
    } elseif ($password !== $repeat) {
        $msg = 'Passwords do not match.';
    } else {
        // Check existing email
        $stmt = $conn->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $msg = 'User with this email already exists.';
            } else {
                // Insert with hashed password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO admin_users (email, password_hash) VALUES (?, ?)');
                if ($ins) {
                    $ins->bind_param('ss', $email, $hash);
                    if ($ins->execute()) {
                        // Get new user ID
                        $newUserId = $conn->insert_id;

                        // Send welcome email
                        require_once __DIR__ . '/view/email_helper.php';
                        sendWelcomeEmail($email, $email);

                        // Set session
                        session_start();
                        $_SESSION['admin_id'] = $newUserId;
                        $_SESSION['admin_email'] = $email;

                        setcookie('email', $email, time() + 60*60*24*7, '/');
                        header('Location: chatbot.php');
                        exit();
                    } else {
                        $msg = 'Failed to create account.';
                    }
                    $ins->close();
                } else {
                    $msg = 'Database error (insert).';
                }
            }
            $stmt->close();
        } else {
            $msg = 'Database error (select).';
        }
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>User Registration</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <main class="container">
    <section class="card narrow">
      <h1 class="center">Register New User</h1>
      <form id="registerForm" class="stack" method="post" action="">
        <label>Email
          <input id="email" name="email" type="email" placeholder="you@example.com" required>
        </label>
        <label>Password
          <input id="password" name="password" type="password" placeholder="••••••••" required>
        </label>
        <label>Repeat Password
          <input id="repeatPassword" name="repeatPassword" type="password" placeholder="••••••••" required>
        </label>
        <button class="btn" type="submit">Sign up</button>
        <button class="muted center" onclick="window.location.href='login.php'; return false;">Go to sign in page</button>

        <p id="msg" class="muted center"><?php echo htmlspecialchars($msg); ?></p>
      </form>
    </section>
  </main>
</body>
</html>
