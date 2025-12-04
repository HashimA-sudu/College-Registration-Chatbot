<?php
// Start session and check Remember Me cookie
session_start();
require_once __DIR__ . '/view/conn.php';
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Course Assistant — Home</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <main class="container">
    <header class="header">
      <br>
      <h1>Welcome to the College Course Registration Chatbot</h1>
    </header>

    <?php if (!empty($_SESSION['admin_id'])): ?>
      <!-- Logged in -->
      <section class="card">
        <h2 class="card-title">Welcome back!</h2>
        <?php if (!empty($_SESSION['auto_logged_in'])): ?>
          <p style="color: #007a3d;">✅ Automatically logged in</p>
        <?php endif; ?>
        <div class="row gap">
          <a class="btn ghost" href="chatbot.php">Start Chatting</a>
          <a class="btn ghost" href="logout.php">Logout</a>
        </div>
      </section>
    <?php else: ?>
      <!-- Not logged in -->
      <section class="card">
        <h2 class="card-title">Hello!</h2>
        <div class="row gap">
          <a class="btn ghost" href="login.php">Login</a>
          <a class="btn ghost" href="register.php">Register</a>
        </div>
      </section>
    <?php endif; ?>

  </main>
</body>
</html>
