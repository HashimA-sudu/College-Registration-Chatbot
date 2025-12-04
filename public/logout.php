<?php
session_start();

// Clear remember me cookie
require_once __DIR__ . '/view/remember_me_helper.php';
require_once __DIR__ . '/view/conn.php';

// Revoke all remember tokens for this user
if (!empty($_SESSION['admin_id'])) {
    revoke_all_remember_tokens($conn, $_SESSION['admin_id']);
}

clear_remember_login();

// Destroy session
session_destroy();

header('Location: index.php');
exit();
?>