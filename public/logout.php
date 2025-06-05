<?php
// public/logout.php

// The config file defines SITE_URL and SESSION_NAME, and handles session_start().
// It's crucial to include it first to ensure all necessary constants and session management
// are in place.
$config_path_from_public = __DIR__ . '/../src/config/config.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // Critical error if config is missing, as SITE_URL for redirect and SESSION_NAME are essential.
    die("Critical error: Main configuration file not found. Cannot process logout.");
}

// FIX: Removed the redundant session_name() and session_start() block.
// config.php already handles session_start() and sets session_name() if SESSION_NAME is defined.
// If the session is already active, calling session_name() again will cause the error.

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
$redirect_url = get_asset_url('login.php?logout=success'); // Using get_asset_url for consistency
header("Location: " . $redirect_url);
exit; // Important to prevent further script execution.