<?php
// ==============================
// 🔐 Remember Me Helper Functions
// Based on fateh4 project implementation
// ==============================

/**
 * Create a remember me token for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return void
 */
function create_remember_login($conn, int $user_id): void {
    // Generate random selector and token
    $selector  = bin2hex(random_bytes(6));
    $tokenRaw  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenRaw);

    // Token expires in 60 days
    $expires = date('Y-m-d H:i:s', time() + (60 * 24 * 60 * 60));

    // Get user agent and IP for security
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Store in database
    $stmt = $conn->prepare("
        INSERT INTO user_remember_tokens
        (user_id, selector, token_hash, expires_at, user_agent, ip, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if ($stmt) {
        $stmt->bind_param('isssss', $user_id, $selector, $tokenHash, $expires, $userAgent, $ip);
        $stmt->execute();
        $stmt->close();
    }

    // Set cookie (selector:token)
    $cookieValue = $selector . ':' . $tokenRaw;
    $expires = time() + (60 * 24 * 60 * 60); // 60 days

    // Set cookie with explicit parameters
    $cookieSet = setcookie('remember_chatbot', $cookieValue, [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => '', // Use current domain
        'secure'   => false, // Set to true for HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Debug: Log cookie creation (remove in production)
    if ($cookieSet) {
        error_log("Remember Me cookie set for user $user_id, expires: " . date('Y-m-d H:i:s', $expires));
    } else {
        error_log("Failed to set Remember Me cookie for user $user_id");
    }
}

/**
 * Clear remember me token and cookie
 * @return void
 */
function clear_remember_login(): void {
    if (isset($_COOKIE['remember_chatbot'])) {
        setcookie('remember_chatbot', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE['remember_chatbot']);
    }
}

/**
 * Auto-login user from remember me cookie
 * @param mysqli $conn Database connection
 * @return void
 */
function auto_login_from_cookie($conn): void {
    // If already logged in, return
    if (!empty($_SESSION['admin_id'])) {
        error_log("Auto-login skipped: User already logged in");
        return;
    }

    // If no cookie, return
    if (empty($_COOKIE['remember_chatbot'])) {
        error_log("Auto-login skipped: No remember_chatbot cookie found");
        return;
    }

    error_log("Auto-login: Found remember_chatbot cookie, attempting auto-login");

    // Parse cookie (selector:token)
    $parts = explode(':', $_COOKIE['remember_chatbot'], 2);
    if (count($parts) !== 2) {
        error_log("Auto-login failed: Invalid cookie format");
        clear_remember_login();
        return;
    }

    [$selector, $tokenRaw] = $parts;
    error_log("Auto-login: Parsed selector: $selector");

    // Get token from database
    $stmt = $conn->prepare("
        SELECT user_id, token_hash, expires_at
        FROM user_remember_tokens
        WHERE selector = ? AND revoked_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        error_log("Auto-login failed: Token not found in database");
        clear_remember_login();
        return;
    }

    error_log("Auto-login: Found token in database for user_id: " . $row['user_id']);

    // Check if expired
    if (strtotime($row['expires_at']) < time()) {
        error_log("Auto-login failed: Token expired on " . $row['expires_at']);
        clear_remember_login();
        return;
    }

    // Verify token hash
    $computedHash = hash('sha256', $tokenRaw);
    if (!hash_equals($row['token_hash'], $computedHash)) {
        // Token mismatch - possible security issue
        error_log("Auto-login failed: Token hash mismatch - possible security issue!");
        clear_remember_login();
        return;
    }

    error_log("Auto-login: Token verified successfully");

    // Valid token! Auto-login the user
    $userId = (int)$row['user_id'];

    // Get user details
    $stmt = $conn->prepare("SELECT id, email FROM admin_users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Set session
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['auto_logged_in'] = true; // Flag to indicate auto-login

            error_log("Auto-login SUCCESS: User {$user['email']} (ID: {$user['id']}) logged in automatically");
        } else {
            error_log("Auto-login failed: User not found in database");
        }
    }
}

/**
 * Revoke a specific remember me token
 * @param mysqli $conn Database connection
 * @param string $selector Token selector
 * @return void
 */
function revoke_remember_token($conn, string $selector): void {
    $stmt = $conn->prepare("
        UPDATE user_remember_tokens
        SET revoked_at = NOW()
        WHERE selector = ?
    ");

    if ($stmt) {
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Revoke all remember me tokens for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return void
 */
function revoke_all_remember_tokens($conn, int $user_id): void {
    $stmt = $conn->prepare("
        UPDATE user_remember_tokens
        SET revoked_at = NOW()
        WHERE user_id = ? AND revoked_at IS NULL
    ");

    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Clean up expired tokens (call this periodically)
 * @param mysqli $conn Database connection
 * @return void
 */
function cleanup_expired_tokens($conn): void {
    $conn->query("
        DELETE FROM user_remember_tokens
        WHERE expires_at < NOW()
        OR revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
}
?>
