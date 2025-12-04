<!-- Connect to database function (no pass required) -->

<?php
        $dbhost = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $dbname = "ucr_chatbot";

        // First, connect without database to create it if needed
        $conn = new mysqli($dbhost, $dbuser, $dbpass);
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        // Create database if not exists
        $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            die('Error creating database: ' . $conn->error);
        }

        // Select the database
        $conn->select_db($dbname);

        // Create admin_users table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            die('Error creating admin_users table: ' . $conn->error);
        }

        // Create user_inquiries table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS `user_inquiries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `message_number` INT NOT NULL,
            `role` ENUM('user', 'bot') NOT NULL,
            `content` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `day` VARCHAR(20),
            FOREIGN KEY (`user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
            INDEX `idx_user_msg` (`user_id`, `message_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            die('Error creating user_inquiries table: ' . $conn->error);
        }

        // Create user_remember_tokens table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `selector` VARCHAR(20) NOT NULL UNIQUE,
            `token_hash` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `user_agent` VARCHAR(255),
            `ip` VARCHAR(45),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `revoked_at` DATETIME NULL,
            FOREIGN KEY (`user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
            INDEX `idx_selector` (`selector`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            die('Error creating user_remember_tokens table: ' . $conn->error);
        }

        // Insert default admin if not exists
        $defaultEmail = 'admin@admin.com';
        $defaultHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // admin123

        $checkAdmin = $conn->query("SELECT id FROM admin_users WHERE email = '$defaultEmail'");
        if ($checkAdmin->num_rows == 0) {
            $sql = "INSERT INTO admin_users (email, password_hash) VALUES ('$defaultEmail', '$defaultHash')";
            $conn->query($sql);
        }

        // Start session FIRST (if not already started)
        if (session_status() === PHP_SESSION_NONE) {
            // Set session cookie parameters for better persistence
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie (until browser closes, unless Remember Me is used)
                'path' => '/',
                'secure' => false, // Set to true if using HTTPS
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        // Include Remember Me helper and auto-login
        require_once __DIR__ . '/remember_me_helper.php';
        auto_login_from_cookie($conn);

        // Cleanup expired tokens periodically (1% chance)
        if (rand(1, 100) === 1) {
            cleanup_expired_tokens($conn);
        }
?>