<?php
session_start();
// Require admin login
if ($_SESSION['admin_email'] != 'admin@ad.com') {
    header('Location: login.php');
    exit();
}

// Minimal: read posted email directly (don't rely on session value)
$emailSearch = '';
$resultsHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['emailSearch'])) {
    require_once __DIR__ . '/view/conn.php';
    $emailSearch = trim($_POST['emailSearch']);
    if (!filter_var($emailSearch, FILTER_VALIDATE_EMAIL)) {
        $resultsHtml = '<p style="text-align:center;">Invalid email address.</p>';
    } else {
        // Explicitly select columns to match bind_result and then group user/bot messages by message_number
        $sql = "SELECT ui.message_number, ui.role, ui.content, ui.created_at
                FROM user_inquiries ui
                JOIN admin_users au ON ui.user_id = au.id
                WHERE au.email = ?
                ORDER BY ui.message_number ASC, ui.id ASC";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $emailSearch);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    // group by message_number
                    $groups = [];
                    while ($row = $res->fetch_assoc()) {
                        $num = (int)$row['message_number'];
                        if (!isset($groups[$num])) {
                            $groups[$num] = ['inquiry' => '', 'response' => '', 'created' => $row['created_at']];
                        }
                        if ($row['role'] === 'user') {
                            $groups[$num]['inquiry'] = $row['content'];
                        } elseif ($row['role'] === 'bot') {
                            $groups[$num]['response'] = $row['content'];
                        }
                    }

                    $resultsHtml .= '<table><tr><th>Message #</th><th>Inquiry</th><th>Response</th><th>Created At</th></tr>';
                    foreach ($groups as $num => $g) {
                        $resultsHtml .= '<tr>';
                        $resultsHtml .= '<td>' . htmlspecialchars((string)$num) . '</td>';
                        $resultsHtml .= '<td>' . nl2br(htmlspecialchars($g['inquiry'])) . '</td>';
                        $resultsHtml .= '<td>' . nl2br(htmlspecialchars($g['response'])) . '</td>';
                        $resultsHtml .= '<td>' . htmlspecialchars($g['created']) . '</td>';
                        $resultsHtml .= '</tr>';
                    }
                    $resultsHtml .= '</table>';
                } else {
                    $resultsHtml = '<p style="text-align:center;">No records found for this email.</p>';
                }
                if ($res) $res->free();
            } else {
                $resultsHtml = '<p style="text-align:center;">Error executing query: ' . htmlspecialchars($stmt->error) . '</p>';
            }
            $stmt->close();
        } else {
            $resultsHtml = '<p style="text-align:center;">Database error: ' . htmlspecialchars($conn->error) . '</p>';
        }
    }
} // else show empty $resultsHtml
?>

<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Dashboard</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <main class="container">
    <header class="row between">
      <h1>Dashboard</h1>
      <div class="row gap">
        <a id="home" class="btn" href="index.php">Home</a>
        <a id="chatbot" class="btn" href="chatbot.php">Chatbot</a>
        <a id="logout" class="btn" href="logout.php">Logout</a>
      </div>
    </header>

    <section class="card">
      <form method="post">
        <h2 class="card-title">Search User Inquiries by Email</h2>
        <label for="emailSearch">Email:</label>
        <input type="email" id="emailSearch" name="emailSearch" placeholder="Type email to be searched..." required autocomplete="off">
        <button type="submit" class="btn" id="sendBtn">Search</button>

      </form>
      <br>
      <div id="results">
        <?php echo $resultsHtml; ?>
      </div>
    </section>
  </main>

 </body>
</html>
