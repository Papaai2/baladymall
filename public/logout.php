<?php
// public/logout.php

// The config file defines SITE_URL and SESSION_NAME
// It's good practice to include it to ensure constants are available,
// especially if session_name() was used.
$config_path_from_public = __DIR__ . '/../src/config/config.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // Fallback or error if config is strictly needed for SITE_URL or SESSION_NAME
    // For a simple logout, direct session handling might be enough if SITE_URL is hardcoded for redirect.
    // However, using SITE_URL is better.
    die("Critical error: Main configuration file not found. Cannot process logout.");
}

// Start the session to access its variables and then destroy it.
// Ensure session_name is set if it was used during session_start in header.php
if (defined('SESSION_NAME')) {
    session_name(SESSION_NAME);
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Unset all of the session variables.
$_SESSION = array();

// 2. Destroy the session cookie.
// If you are using a session cookie (default behavior),
// it's good practice to delete the cookie as well.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session.
session_destroy();

// 4. Redirect to login page (or homepage) with a success message.
// Ensure SITE_URL is defined (from config.php)
$redirect_url = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/login.php?logged_out=success';
header("Location: " . $redirect_url);
exit; // Important to prevent further script execution.
?>
