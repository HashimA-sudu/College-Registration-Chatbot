<?php
session_start();
require_once __DIR__ . '/view/conn.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Require login
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = (int) $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean any output before JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $message = '';
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
        $message = trim((string)$data['message']);
    } else {
        $message = trim($_POST['message'] ?? $_POST['inquiry'] ?? '');
    }

    if ($message === '') {
        echo json_encode(['error' => 'Empty message']);
        exit();
    }

    $started = false;
    try {
        $conn->begin_transaction();
        $started = true;

        // Get next message_number for this user
        $sql = "SELECT COALESCE(MAX(message_number),0) AS maxnum FROM user_inquiries WHERE user_id = ? FOR UPDATE";
        $next_num = 1;
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->bind_result($maxnum);
            $stmt->fetch();
            $stmt->close();
            $next_num = ((int)$maxnum) + 1;
            if ($next_num < 1) $next_num = 1;
        } else {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }

        // Insert user message
        $ins = "INSERT INTO user_inquiries (user_id, message_number, role, content, created_at, day) VALUES (?, ?, 'user', ?, NOW(), DAYNAME(NOW()))";
        if (!($stmt = $conn->prepare($ins))) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('iis', $user_id, $next_num, $message);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Insert user failed: ' . $err);
        }
        $stmt->close();

        // Call Node API
        $reply = "I apologize, but I'm having trouble connecting to my knowledge base. Please try again.";
        $nodeUrl = 'http://127.0.0.1:3000/api/chat';
        $payload = json_encode(['message' => $message]);
        
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($opts);
        $resp = @file_get_contents($nodeUrl, false, $context);
        
        if ($resp !== false) {
            $dec = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($dec['reply'])) {
                $reply = (string)$dec['reply'];
            } else {
                error_log('Node API returned invalid JSON: ' . $resp);
            }
        } else {
            error_log('Node API connection failed');
        }

        // Insert bot reply
        $ins2 = "INSERT INTO user_inquiries (user_id, message_number, role, content, created_at, day) VALUES (?, ?, 'bot', ?, NOW(), DAYNAME(NOW()))";
        if (!($stmt = $conn->prepare($ins2))) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('iis', $user_id, $next_num, $reply);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Insert bot failed: ' . $err);
        }
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'reply' => $reply,
            'message_number' => $next_num,
            'success' => true
        ]);
        exit();
        
    } catch (Exception $e) {
        if ($started) {
            @$conn->rollback();
        }
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error',
            'debug' => $e->getMessage() // Remove in production
        ]);
        error_log('chatbot error: ' . $e->getMessage());
        exit();
    }
}

// GET: Load recent messages
$messages = [];
if ($stmt = $conn->prepare("SELECT role, content FROM user_inquiries WHERE user_id = ? ORDER BY message_number ASC, id ASC LIMIT 500")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $messages[] = $r;
    }
    $stmt->close();
}

// Clean output buffer before HTML
ob_end_clean();
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Course Assistant — AI Chatbot</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <main class="container" aria-live="polite">
    <header class="header">
      <div class="header-title">
        <br>
        <h1>KFU College Course Registration Chatbot</h1>
        <div class="muted">Logged in as: <?= htmlspecialchars($_SESSION['admin_email'] ?? 'admin') ?></div>
        <br>
      </div>
      <div class="header-actions">
        <a class="btn btn-ghost" href="index.php">Home</a>
        <a class="btn btn-ghost" href="logout.php">Logout</a>
      </div>
    </header>

    <div class="container">
      <p class="muted"><span style="align-content: center;">Ask about courses, professors, and general information</span></p>
      <div class="chat-window" id="chatWindow"></div>
      <div class="input-area">
        <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off">
        <button id="sendBtn">➤</button>
      </div>
    </div>

    <script>
      const chatWindow = document.getElementById('chatWindow');
      const messageInput = document.getElementById('messageInput');
      const sendBtn = document.getElementById('sendBtn');

      const initialMessages = <?= json_encode($messages, JSON_UNESCAPED_UNICODE) ?> || [];

      function addMessage(text, sender) {
        const messageEl = document.createElement('div');
        messageEl.className = `message ${sender}-message`;
        const contentEl = document.createElement('div');
        contentEl.className = 'message-content';
        contentEl.textContent = text;
        messageEl.appendChild(contentEl);
        chatWindow.appendChild(messageEl);
        chatWindow.scrollTop = chatWindow.scrollHeight;
      }

      function showTypingIndicator() {
        const messageEl = document.createElement('div');
        messageEl.className = 'message bot-message';
        messageEl.id = 'typingIndicator';
        const typingEl = document.createElement('div');
        typingEl.className = 'typing-indicator';
        typingEl.innerHTML = '<span></span><span></span><span></span>';
        messageEl.appendChild(typingEl);
        chatWindow.appendChild(messageEl);
        chatWindow.scrollTop = chatWindow.scrollHeight;
      }

      function removeTypingIndicator() {
        const el = document.getElementById('typingIndicator');
        if (el) el.remove();
      }

      async function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;
        
        messageInput.disabled = true;
        sendBtn.disabled = true;
        addMessage(message, 'user');
        messageInput.value = '';
        showTypingIndicator();
        
        try {
          const res = await fetch('chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
          });
          
          const data = await res.json();
          removeTypingIndicator();
          
          if (data.error) {
            addMessage(`Error: ${data.error}${data.debug ? ' (' + data.debug + ')' : ''}`, 'bot');
          } else {
            addMessage(data.reply || 'No reply', 'bot');
          }
          
        } catch (err) {
          removeTypingIndicator();
          addMessage('Sorry, an error occurred. Please try again.', 'bot');
          console.error('Fetch error:', err);
        } finally {
          messageInput.disabled = false;
          sendBtn.disabled = false;
          messageInput.focus();
        }
      }

      sendBtn.addEventListener('click', sendMessage);
      messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
      });

      // Render initial messages
      (function renderInitial() {
        for (const m of initialMessages) {
          addMessage(m.content, m.role === 'bot' ? 'bot' : 'user');
        }
      })();

      messageInput.focus();
    </script>

    <footer class="footer">
      <div class="footer-info">
        <span class="muted">API: <code id="apiBase">chatbot.php</code></span>
      </div>
      <a class="btn btn-ghost" href="index.php">← Back to Home</a>
    </footer>
  </main>
</body>
</html>