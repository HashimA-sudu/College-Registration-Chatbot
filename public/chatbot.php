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
$user_email = $_SESSION['admin_email'] ?? 'User';

// Handle delete conversation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation'])) {
    $conv_id = (int) $_POST['delete_conversation'];
    $stmt = $conn->prepare("DELETE FROM user_inquiries WHERE user_id = ? AND conversation_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $conv_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit();
}

// Handle new chat request
if (isset($_GET['new'])) {
    unset($_SESSION['current_conversation_id']);
    header('Location: chatbot.php');
    exit();
}

// Get or create current conversation ID
if (!isset($_SESSION['current_conversation_id'])) {
    // Get the highest conversation_id for this user
    $stmt = $conn->prepare("SELECT COALESCE(MAX(conversation_id), 0) as max_id FROM user_inquiries WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $_SESSION['current_conversation_id'] = ($row['max_id'] ?? 0) + 1;
        $stmt->close();
    } else {
        $_SESSION['current_conversation_id'] = 1;
    }
}

$current_conversation_id = (int) $_SESSION['current_conversation_id'];

// Load specific conversation if requested
if (isset($_GET['conv'])) {
    $current_conversation_id = (int) $_GET['conv'];
    $_SESSION['current_conversation_id'] = $current_conversation_id;
}

// Verify user exists in database
$stmt = $conn->prepare("SELECT id FROM admin_users WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        session_destroy();
        header('Location: login.php?error=user_not_found');
        exit();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    $message = trim($_POST['message'] ?? '');
    $fileText = '';

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/view/file_processor.php';

        error_log("Processing file: " . $_FILES['file']['name'] . " (size: " . $_FILES['file']['size'] . ")");
        $fileResult = extractTextFromFile($_FILES['file']);

        if ($fileResult['success']) {
            $fileText = $fileResult['text'];
            $fileName = $_FILES['file']['name'];
            error_log("Successfully extracted " . strlen($fileText) . " characters from: $fileName");
        } else {
            $errorMsg = $fileResult['error'];
            error_log("File processing FAILED: $errorMsg");
            echo json_encode([
                'error' => 'File processing failed',
                'debug' => $errorMsg
            ]);
            exit();
        }
    }

    // Combine file text with user message
    if ($fileText !== '') {
        $fileTextPreview = mb_substr($fileText, 0, 4000);
        if (strlen($fileText) > 4000) {
            $fileTextPreview .= "\n\n[Text truncated - file too large]";
        }

        if ($message === '') {
            $message = "Please analyze this file content:\n\n" . $fileTextPreview;
        } else {
            $message = $message . "\n\nFile content:\n" . $fileTextPreview;
        }

        error_log("Combined message length: " . strlen($message) . " chars");
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

        // Get current conversation ID
        $current_conv_id = $_SESSION['current_conversation_id'] ?? 1;

        // Insert user message
        $ins = "INSERT INTO user_inquiries (user_id, conversation_id, message_number, role, content, created_at, day) VALUES (?, ?, ?, 'user', ?, NOW(), DAYNAME(NOW()))";
        if (!($stmt = $conn->prepare($ins))) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('iiis', $user_id, $current_conv_id, $next_num, $message);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Insert user failed: ' . $err);
        }
        $stmt->close();

        // Call Node API with JWT
        require_once __DIR__ . '/view/jwt_helper.php';
        $reply = "I apologize, but I'm having trouble connecting to my knowledge base. Please try again.";
        $nodeUrl = 'https://localhost:3443/api/chat/public';
        $payload = json_encode(['message' => $message]);

        $jwtToken = getStoredJWTToken();
        $headers = "Content-Type: application/json\r\n";

        if ($jwtToken) {
            $nodeUrl = 'https://localhost:3443/api/chat';
            $headers .= "Authorization: Bearer $jwtToken\r\n";
        }

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => $headers,
                'content' => $payload,
                'timeout' => 90,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        $context = stream_context_create($opts);
        error_log('Chatbot: Sending request to ' . $nodeUrl);
        $startTime = microtime(true);

        $resp = @file_get_contents($nodeUrl, false, $context);

        $duration = round((microtime(true) - $startTime), 2);
        error_log("Chatbot: API call took {$duration} seconds");

        if ($resp !== false) {
            $dec = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($dec['reply'])) {
                $reply = (string)$dec['reply'];
                error_log('Chatbot: Got reply successfully (length: ' . strlen($reply) . ')');
            } else {
                error_log('Chatbot: Node API returned invalid JSON: ' . substr($resp, 0, 200));
            }
        } else {
            error_log('Chatbot: Node API connection failed - no response received');
        }

        // Insert bot reply
        $ins2 = "INSERT INTO user_inquiries (user_id, conversation_id, message_number, role, content, created_at, day) VALUES (?, ?, ?, 'bot', ?, NOW(), DAYNAME(NOW()))";
        if (!($stmt = $conn->prepare($ins2))) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('iiis', $user_id, $current_conv_id, $next_num, $reply);
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
            'debug' => $e->getMessage()
        ]);
        error_log('chatbot error: ' . $e->getMessage());
        exit();
    }
}

// GET: Load chat history (grouped by conversation_id)
$chatHistory = [];
$historyQuery = "
    SELECT
        conversation_id,
        MIN(content) as first_message,
        MIN(created_at) as created_at,
        COUNT(*) as message_count
    FROM user_inquiries
    WHERE user_id = ? AND role = 'user'
    GROUP BY conversation_id
    ORDER BY conversation_id DESC
    LIMIT 20
";
if ($stmt = $conn->prepare($historyQuery)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $chatHistory[] = [
            'conversation_id' => $r['conversation_id'],
            'preview' => mb_substr($r['first_message'], 0, 35),
            'created_at' => $r['created_at'],
            'count' => $r['message_count']
        ];
    }
    $stmt->close();
}

// GET: Load messages for current conversation
$messages = [];
if ($stmt = $conn->prepare("SELECT role, content FROM user_inquiries WHERE user_id = ? AND conversation_id = ? ORDER BY id ASC")) {
    $stmt->bind_param('ii', $user_id, $current_conversation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $messages[] = $r;
    }
    $stmt->close();
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include __DIR__ . '/view/head.php'; 
  //switch page into dark mode when its 1
$stmt2 = $conn->prepare("SELECT dark_mode FROM admin_users WHERE id = ? LIMIT 1");
if ($stmt2) {
    $stmt2->bind_param('i', $user_id);
    $stmt2->execute();
    $stmt2->bind_result($dark_mode);
    if ($stmt2->fetch()) {
        $_SESSION['dark_mode'] = (int)$dark_mode;
    }
    $stmt2->close();
}

if(isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] == 1){
    echo '<style>
    #chatWindow {
        background-color: #2d2130fff;
        color: #e0e0e0;
    }
    .bot-message .message-content {
        background: #121212ff;
        color: #e0e0e0;
        border: 1px solid #333333;
    }
    .input-field {
        background-color: #121212ff;
        color: #e0e0e0;
        border: 1px solid #333333;
    }
    .input-field:focus {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.3);
    }
    chat-header, .input-container {
        background-color: #2d2130fff;
        border-bottom: 1px solid #333333;
    }
    .chat-title {
        color: #e0e0e0;
    }
    .send-btn {
        background: #8b5cf6;
    }
    .send-btn:hover:not(:disabled) {
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }
    .main-content {
        background-color: #121212ff;
    }
    .sidebar {
        background: #1e1e1eff;
    }
    .message-content {
        background: #333333ff;
        color: #e0e0e0;
    }
    </style>';

}
else {
    echo '<style>
    .sidebar {
      background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
    }
    .chat-title {
      color: #111827;
    }
    .message-content {
      background: #f3f4f6ff;
      color: #111827;
    }
    </style>';
}

  ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KFU Course Assistant</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
      background: #f7f7f8;
      height: 100vh;
      overflow: hidden;
    }

    .container {
      display: flex;
      height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 280px;
      color: white;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
      position: relative;
      z-index: 100;
    }

    .sidebar.collapsed {
      transform: translateX(-280px);
    }

    .sidebar-header {
      padding: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-header h2 {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .new-chat-btn {
      width: 100%;
      padding: 12px 16px;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 8px;
      color: white;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
    }

    .new-chat-btn:hover {
      background: rgba(255,255,255,0.25);
    }

    .sidebar-search {
      padding: 10px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .search-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 8px;
      background: rgba(255,255,255,0.1);
      color: white;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
    }

    .search-input::placeholder {
      color: rgba(255,255,255,0.5);
    }

    .search-input:focus {
      background: rgba(255,255,255,0.15);
      border-color: rgba(255,255,255,0.3);
    }

    .sidebar-menu {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }

    .menu-section {
      margin-bottom: 20px;
    }

    .menu-section-title {
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,0.6);
      padding: 8px 16px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .menu-item {
      padding: 12px 16px;
      border-radius: 8px;
      color: rgba(255,255,255,0.9);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .menu-item:hover {
      background: rgba(255,255,255,0.15);
    }

    .history-item {
      padding: 10px 16px;
      border-radius: 8px;
      color: rgba(255,255,255,0.85);
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 4px;
      font-size: 13px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }

    .history-item:hover {
      background: rgba(255,255,255,0.15);
    }

    .history-item.active {
      background: rgba(255,255,255,0.2);
      font-weight: 600;
    }

    .history-item.hidden {
      display: none;
    }

    .history-content {
      flex: 1;
      min-width: 0;
    }

    .history-actions {
      display: none;
      gap: 4px;
    }

    .history-item:hover .history-actions {
      display: flex;
    }

    .delete-btn {
      width: 24px;
      height: 24px;
      border-radius: 4px;
      background: rgba(239, 68, 68, 0.2);
      border: none;
      color: #ef4444;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      transition: all 0.2s;
    }

    .delete-btn:hover {
      background: #ef4444;
      color: white;
    }

    .history-preview {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 4px;
    }

    .history-meta {
      font-size: 11px;
      color: rgba(255,255,255,0.5);
    }

    .sidebar-footer {
      padding: 20px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .user-info:hover {
      background: rgba(255,255,255,0.2);
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 16px;
    }

    .user-details {
      flex: 1;
    }

    .user-name {
      font-size: 14px;
      font-weight: 500;
    }

    .user-email {
      font-size: 12px;
      opacity: 0.8;
    }

    /* Main Chat Area */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .chat-header {
      padding: 20px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .hamburger-btn {
      width: 36px;
      height: 36px;
      border-radius: 6px;
      border: none;
      background: #f3f4f6;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      font-size: 20px;
    }

    .hamburger-btn:hover {
      background: #e5e7eb;
    }

    .chat-title {
      font-size: 16px;
      font-weight: 600;
    }

    .header-actions {
      display: flex;
      gap: 8px;
    }

    .icon-btn {
      width: 36px;
      height: 36px;
      border-radius: 6px;
      border: none;
      background: #f3f4f6;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      font-size: 18px;
    }

    .icon-btn:hover {
      background: #e5e7eb;
    }

    .chat-window {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      max-width: 750px;
      margin: 0 auto;
      width: 100%;
    }

    .message {
      margin-bottom: 16px;
      animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .message-content {
      padding: 12px 16px;
      border-radius: 10px;
      line-height: 1.6;
      font-size: 14px;
      max-width: 100%;
    }

    .user-message .message-content {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      margin-left: auto;
      max-width: 75%;
    }

    .bot-message .message-content {
      border: 1px solid #e5e7eb;
      max-width: 85%;
    }

    .system-message {
      text-align: center;
    }

    .system-message .message-content {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      padding: 10px 16px;
      border-radius: 20px;
      font-size: 13px;
      display: inline-block;
    }

    .typing-indicator {
      display: flex;
      gap: 4px;
      padding: 16px 20px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      width: fit-content;
    }

    .typing-indicator span {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #9ca3af;
      animation: bounce 1.4s infinite;
    }

    .typing-indicator span:nth-child(2) {
      animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-10px); }
    }

    /* Input Area */
    .input-container {
      padding: 20px 24px;
      border-top: 1px solid #e5e7eb;
    }

    .input-wrapper {
      max-width: 750px;
      margin: 0 auto;
      display: flex;
      gap: 10px;
      align-items: flex-end;
    }

    .file-upload-btn {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
      position: relative;
    }

    .file-upload-btn::before {
      content: '📎';
      filter: grayscale(1) brightness(10);
      font-size: 20px;
    }

    .file-upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .file-upload-btn.file-selected {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .input-field {
      flex: 1;
      padding: 12px 16px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      resize: none;
      max-height: 180px;
      min-height: 42px;
      transition: all 0.2s;
    }

    .input-field:focus {
      border-color: #8b5cf6;
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    .send-btn {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      font-size: 20px;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .send-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .send-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #9ca3af;
    }

    /* Overlay for mobile */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 99;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .sidebar-overlay.active {
      display: block;
      opacity: 1;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        z-index: 1000;
        transform: translateX(-280px);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .sidebar.collapsed {
        transform: translateX(-280px);
      }

      .chat-window {
        padding: 16px;
      }

      .input-container {
        padding: 16px;
      }

      .message-content {
        max-width: 95%;
      }

      .user-message .message-content {
        max-width: 85%;
      }
    }

    @media (min-width: 769px) {
      .hamburger-btn {
        display: none;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <h2>KFU Course Assistant</h2>
        <button class="new-chat-btn" id="newChatBtn">
          <span>➕</span>
          <span>New Chat</span>
        </button>
      </div>

      <div class="sidebar-search">
        <input type="text" class="search-input" id="searchInput" placeholder="🔍 Search chats...">
      </div>

      <div class="sidebar-menu">
        <!-- Main Menu -->
        <div class="menu-section">
          <div class="menu-item" onclick="window.location.reload()">
            <span>💬</span>
            <span>Current Chat</span>
          </div>
          <div class="menu-item" onclick="window.location.href='settings.php'">
            <span>⚙️</span>
            <span>Settings</span>
          </div>
        </div>

        <!-- Chat History -->
        <?php if (!empty($chatHistory)): ?>
        <div class="menu-section">
          <div class="menu-section-title">Recent Chats</div>
          <?php foreach ($chatHistory as $chat): ?>
          <div class="history-item <?= $chat['conversation_id'] == $current_conversation_id ? 'active' : '' ?>"
               data-conversation-id="<?= $chat['conversation_id'] ?>"
               data-search-text="<?= htmlspecialchars(strtolower($chat['preview'])) ?>">
            <div class="history-content">
              <div class="history-preview"><?= htmlspecialchars($chat['preview']) ?>...</div>
              <div class="history-meta">
                <?php
                  $chatDate = strtotime($chat['created_at']);
                  $today = strtotime(date('Y-m-d'));
                  $yesterday = strtotime('-1 day', $today);

                  if (date('Y-m-d', $chatDate) == date('Y-m-d', $today)) {
                    echo 'Today';
                  } elseif (date('Y-m-d', $chatDate) == date('Y-m-d', $yesterday)) {
                    echo 'Yesterday';
                  } else {
                    echo date('M j', $chatDate);
                  }
                ?>
              </div>
            </div>
            <div class="history-actions">
              <button class="delete-btn" data-conv-id="<?= $chat['conversation_id'] ?>" title="Delete chat">🗑️</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="sidebar-footer">
        <div class="user-info" onclick="window.location.href='profile.php'">
          <div class="user-avatar"><?= strtoupper(substr($user_email, 0, 1)) ?></div>
          <div class="user-details">
            <div class="user-name">Account</div>
            <div class="user-email"><?= htmlspecialchars(substr($user_email, 0, 20)) ?>...</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <div class="chat-header">
        <div class="header-left">
          <button class="hamburger-btn" id="hamburgerBtn">☰</button>
          <div class="chat-title">Course Registration Assistant</div>
        </div>
        <div class="header-actions">
          <button class="icon-btn" title="Settings" onclick="window.location.href='settings.php'">⚙️</button>
          <button class="icon-btn" title="Logout" onclick="window.location.href='logout.php'">🚪</button>
        </div>
      </div>

      <div class="chat-window" id="chatWindow"></div>

      <div class="input-container">
        <div class="input-wrapper">
          <input type="file" id="fileInput" accept=".txt,.pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx" style="display: none;">
          <button class="file-upload-btn" id="fileBtn" title="Upload file"></button>
          <textarea
            class="input-field"
            id="messageInput"
            placeholder="Type in Arabic or English, or upload a file..."
            rows="1"
          ></textarea>
          <button class="send-btn" id="sendBtn">➤</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const chatWindow = document.getElementById('chatWindow');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const fileBtn = document.getElementById('fileBtn');
    const fileInput = document.getElementById('fileInput');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');

    const initialMessages = <?= json_encode($messages, JSON_UNESCAPED_UNICODE) ?> || [];
    let uploadedFile = null;

    // Sidebar Toggle Functionality
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      sidebar.classList.toggle('collapsed');
      sidebarOverlay.classList.toggle('active');
    }

    if (hamburgerBtn) {
      hamburgerBtn.addEventListener('click', toggleSidebar);
    }

    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      sidebar.classList.add('collapsed');
      sidebarOverlay.classList.remove('active');
    });

    // Desktop sidebar toggle (optional - double click on edge)
    let desktopSidebarCollapsed = false;
    if (window.innerWidth > 768) {
      sidebar.addEventListener('dblclick', (e) => {
        if (e.target === sidebar || e.target.closest('.sidebar-header')) {
          desktopSidebarCollapsed = !desktopSidebarCollapsed;
          if (desktopSidebarCollapsed) {
            sidebar.classList.add('collapsed');
          } else {
            sidebar.classList.remove('collapsed');
          }
        }
      });
    }

    // Chat History Click Handler
    document.querySelectorAll('.history-item').forEach(item => {
      item.addEventListener('click', function(e) {
        // Don't navigate if clicking delete button
        if (e.target.closest('.delete-btn')) return;

        const convId = this.getAttribute('data-conversation-id');
        if (convId) {
          window.location.href = `chatbot.php?conv=${convId}`;
        }
      });
    });

    // Search Functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const historyItems = document.querySelectorAll('.history-item');

        historyItems.forEach(item => {
          const searchText = item.getAttribute('data-search-text') || '';
          if (searchText.includes(searchTerm)) {
            item.classList.remove('hidden');
          } else {
            item.classList.add('hidden');
          }
        });
      });
    }

    // Delete Chat Functionality
    document.querySelectorAll('.delete-btn').forEach(btn => {
      btn.addEventListener('click', async function(e) {
        e.stopPropagation();

        const convId = this.getAttribute('data-conv-id');
        if (!confirm('Delete this conversation? This cannot be undone.')) {
          return;
        }

        try {
          const res = await fetch('chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `delete_conversation=${convId}`
          });

          if (res.ok) {
            // Reload page if deleted current conversation
            const currentConv = <?= $current_conversation_id ?>;
            if (parseInt(convId) === currentConv) {
              window.location.href = 'chatbot.php?new=1';
            } else {
              window.location.reload();
            }
          } else {
            alert('Failed to delete conversation');
          }
        } catch (err) {
          console.error('Delete error:', err);
          alert('Error deleting conversation');
        }
      });
    });

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight) + 'px';
    });

    // Handle paste event for files
    document.addEventListener('paste', (e) => {
      const items = e.clipboardData?.items;
      if (!items) return;

      for (let i = 0; i < items.length; i++) {
        const item = items[i];
        if (item.kind === 'file') {
          e.preventDefault();
          const file = item.getAsFile();
          if (file) {
            uploadedFile = file;
            fileBtn.classList.add('file-selected');
            messageInput.placeholder = `📎 ${file.name} (Pasted) - Add message or click send`;
            addMessage(`📋 File pasted: ${file.name}`, 'system');
          }
          break;
        }
      }
    });

    function addMessage(text, sender) {
      const messageEl = document.createElement('div');
      messageEl.className = `message ${sender}-message`;
      const contentEl = document.createElement('div');
      contentEl.className = 'message-content';

      // Enhanced ChatGPT-like formatting
      let formattedText = text
        .split('\n')
        .map(line => {
          line = line.trim();

          // Bold text with **text**
          line = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

          // Headers with emojis at start
          if (line.match(/^[🎓💡✅❌📋📌⚠️🔍📊]/)) {
            return `<div style="margin: 12px 0; font-weight: 600; font-size: 16px;">${line}</div>`;
          }

          // Bullet points with better styling
          if (line.startsWith('•') || line.startsWith('-') || line.startsWith('*')) {
            return `<div style="margin: 8px 0; padding-left: 24px; position: relative;">
              <span style="position: absolute; left: 0; color: #8b5cf6; font-size: 18px;">●</span>
              ${line.substring(1).trim()}
            </div>`;
          }

          // Numbered lists
          if (/^\d+\./.test(line)) {
            return `<div style="margin: 8px 0; padding-left: 24px; font-weight: 500;">${line}</div>`;
          }

          // Empty lines for spacing
          if (line === '') {
            return '<div style="height: 14px;"></div>';
          }

          // Regular text
          return `<div style="margin: 10px 0; line-height: 1.8;">${line}</div>`;
        })
        .join('');

      contentEl.innerHTML = formattedText;
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
      const hasFile = uploadedFile !== null;

      if (!message && !hasFile) return;

      messageInput.disabled = true;
      sendBtn.disabled = true;
      fileBtn.disabled = true;

      // Show user message
      if (hasFile) {
        addMessage(`📎 ${uploadedFile.name}${message ? '\n' + message : ''}`, 'user');
      } else {
        addMessage(message, 'user');
      }

      messageInput.value = '';
      messageInput.style.height = 'auto';
      showTypingIndicator();

      try {
        const formData = new FormData();
        formData.append('message', message);

        if (uploadedFile) {
          formData.append('file', uploadedFile);
        }

        const res = await fetch('chatbot.php', {
          method: 'POST',
          body: formData
        });

        removeTypingIndicator();

        if (!res.ok) {
          const errorText = await res.text();
          console.error('HTTP Error:', res.status, errorText);
          addMessage(`Server error (${res.status}). Check browser console for details.`, 'bot');
          return;
        }

        let data;
        try {
          const responseText = await res.text();
          data = JSON.parse(responseText);
        } catch (parseErr) {
          console.error('JSON parse error:', parseErr);
          addMessage('Server returned invalid response. Check browser console for details.', 'bot');
          return;
        }

        if (data.error) {
          addMessage(`Error: ${data.error}${data.debug ? '\n\nDetails: ' + data.debug : ''}`, 'bot');
        } else {
          addMessage(data.reply || 'No reply', 'bot');
        }

        // Clear uploaded file
        uploadedFile = null;
        fileInput.value = '';
        fileBtn.classList.remove('file-selected');
        messageInput.placeholder = 'Type in Arabic or English, or upload a file...';

      } catch (err) {
        removeTypingIndicator();
        addMessage(`Sorry, an error occurred: ${err.message}`, 'bot');
        console.error('Fetch error:', err);
      } finally {
        messageInput.disabled = false;
        sendBtn.disabled = false;
        fileBtn.disabled = false;
        messageInput.focus();
      }
    }

    // File upload handlers
    fileBtn.addEventListener('click', () => {
      fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        uploadedFile = e.target.files[0];
        fileBtn.classList.add('file-selected');
        messageInput.placeholder = `📎 ${uploadedFile.name} - Add message or click send`;
      }
    });

    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Render initial messages
    (function renderInitial() {
      for (const m of initialMessages) {
        addMessage(m.content, m.role === 'bot' ? 'bot' : 'user');
      }
    })();

    // New Chat functionality - creates a new conversation
    document.getElementById('newChatBtn').addEventListener('click', () => {
      window.location.href = 'chatbot.php?new=1';
    });

    messageInput.focus();
  </script>
</body>
</html>
