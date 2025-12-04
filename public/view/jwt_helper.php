<?php
// JWT Helper functions for PHP

/**
 * Get JWT token from Node.js server
 */
function getJWTToken($email, $password) {
    $nodeUrl = 'https://localhost:3443/api/auth/login';
    $payload = json_encode(['email' => $email, 'password' => $password]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 30,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($nodeUrl, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($data['token'])) {
            return $data['token'];
        }
    }

    return null;
}

/**
 * Verify JWT token with Node.js server
 */
function verifyJWTToken($token) {
    $nodeUrl = 'https://localhost:3443/api/auth/verify';

    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $token\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($nodeUrl, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($data['valid'])) {
            return $data['user'];
        }
    }

    return null;
}

/**
 * Store JWT token in session
 */
function storeJWTToken($token) {
    $_SESSION['jwt_token'] = $token;
    $_SESSION['jwt_created_at'] = time();
}

/**
 * Get JWT token from session
 */
function getStoredJWTToken() {
    if (isset($_SESSION['jwt_token'])) {
        // Check if token is not too old (24 hours)
        $tokenAge = time() - ($_SESSION['jwt_created_at'] ?? 0);
        if ($tokenAge < 86400) { // 24 hours
            return $_SESSION['jwt_token'];
        } else {
            // Token expired, clear it
            unset($_SESSION['jwt_token']);
            unset($_SESSION['jwt_created_at']);
        }
    }
    return null;
}

/**
 * Clear JWT token from session
 */
function clearJWTToken() {
    unset($_SESSION['jwt_token']);
    unset($_SESSION['jwt_created_at']);
}
?>
