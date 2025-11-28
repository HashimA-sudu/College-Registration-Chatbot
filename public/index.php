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

    <section class="card">
      <h2 class="card-title">Hello!</h2>
      <div class="row gap">
        <?php if (isset($_SESSION['admin_email'])): echo "<a class='btn ghost' href='chatbot.php'> Go to Chatbot</a>"; endif; ?>
        <?php if (isset($_SESSION['admin_email']) && $_SESSION['admin_email'] == 'admin@admin.com'): echo '<a class="btn ghost" href="dashboard.php"> Go to Dashboard</a>'; endif; ?>
        <a class="btn ghost" href="login.php"> Login page</a>
        <a class="btn ghost" href="register.php"> Registration page</a>
      </div>
    </section>

  </main>
  <!-- ملاحظة: السكربت العام داخل assets/js/app.js مستعمل بصفحات ثانية -->
</body>
</html>
