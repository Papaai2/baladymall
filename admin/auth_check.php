<?php
// admin/auth_check.php

// This script should be included at the very top of all super admin pages,
// AFTER the main config.php file has been included.
// It relies on config.php to have already:
// 1. Started the session (`session_start()`)
// 2. Defined necessary constants like SITE_URL.

// No need to session_start() or define SESSION_NAME here, as config.php handles it.
// Given your other files, config.php is indeed included first.

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to main site login page
    // Store the intended admin URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Current admin page URL

    // SITE_URL is guaranteed to be defined because config.php is included before auth_check.php
    $login_url = rtrim(SITE_URL, '/') . '/login.php?auth_required_admin=true';
    header("Location: " . $login_url);
    exit;
}

// Check if user is a super_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    // Logged in, but not a super_admin.
    // Display a simple access denied message directly.
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 50px auto; max-width: 600px; border-radius: 5px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have sufficient permissions to access this area.</p>";
    // SITE_URL is guaranteed to be defined because config.php is included before auth_check.php
    $main_site_link = rtrim(SITE_URL, '/') . '/index.php';
    echo "<p><a href='" . htmlspecialchars($main_site_link) . "' style='color: #007bff;'>Return to Main Site</a></p>";
    if (isset($_SESSION['user_id'])) {
        $my_account_link = rtrim(SITE_URL, '/') . '/my_account.php';
        echo "<p><a href='" . htmlspecialchars($my_account_link) . "' style='color: #007bff;'>Go to My Account</a></p>";
    }
    echo "</div>";
    exit; // Stop further script execution
}

// If we reach here, the user is a logged-in super_admin.
// Regenerate session ID for added security upon entering admin area to prevent session fixation.
// This check ensures it's only done once per admin session (or upon first access to an admin page).
if (!isset($_SESSION['admin_session_regenerated'])) {
    session_regenerate_id(true); // true deletes the old session file
    $_SESSION['admin_session_regenerated'] = true;
}

?>