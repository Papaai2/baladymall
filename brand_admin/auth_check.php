<?php
// brand_admin/auth_check.php

// This script should be included at the very top of all brand_admin pages.

// Ensure session is started.
if (session_status() == PHP_SESSION_NONE) {
    if (defined('SESSION_NAME') && !headers_sent()) {
        session_name(SESSION_NAME);
    }
    if(!headers_sent()) {
        session_start();
    } else {
        error_log("Brand Admin Auth: Session could not be started because headers were already sent.");
        die("A session error occurred. Please contact support. (Headers Sent)");
    }
}

// Define SITE_URL if not already defined (e.g. if main config.php isn't loaded yet)
if (!defined('SITE_URL')) {
    $main_config_path_header = dirname(dirname(__DIR__)) . '/src/config/config.php';
    if (file_exists($main_config_path_header)) {
        require_once $main_config_path_header;
    } else {
        $protocol_fallback = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host_fallback = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_name_fallback = $_SERVER['SCRIPT_NAME'] ?? '';
        $path_segments_fallback = explode('/', trim($script_name_fallback, '/'));
        $project_base_path_fallback = isset($path_segments_fallback[0]) && $path_segments_fallback[0] !== 'brand_admin' ? '/' . $path_segments_fallback[0] : '';
        if(!defined('SITE_URL')) define('SITE_URL', $protocol_fallback . $host_fallback . $project_base_path_fallback . '/public');
    }
}

// Check if user is logged in and is a brand_admin
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to main site login page
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Current brand admin page URL
    $login_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/login.php?auth_required_brand_admin=true' : '../public/login.php?auth_required_brand_admin=true';
    header("Location: " . $login_url);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'brand_admin') {
    // Logged in, but not a brand_admin.
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 50px auto; max-width: 600px; border-radius: 5px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have sufficient permissions to access this area. Only Brand Admins are allowed.</p>";
    $main_site_link = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/index.php' : '../public/index.php';
    echo "<p><a href='" . htmlspecialchars($main_site_link) . "' style='color: #007bff;'>Return to Main Site</a></p>";
    if (isset($_SESSION['user_id'])) {
        $my_account_link = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/my_account.php' : '../public/my_account.php';
        echo "<p><a href='" . htmlspecialchars($my_account_link) . "' style='color: #007bff;'>Go to My Account</a></p>";
    }
    echo "</div>";
    exit; // Stop further script execution
}

// If we reach here, the user is a logged-in brand_admin.
// Now, fetch the brand_id associated with this brand_admin.
// This is CRUCIAL for restricting data access.
$brand_admin_user_id = $_SESSION['user_id'];
$assigned_brand_id = null;
$assigned_brand_name = null;

// Ensure DB connection is available
$db = getPDOConnection();
if (!$db) {
    error_log("Brand Admin Auth: Database connection failed for brand_admin_user_id: {$brand_admin_user_id}");
    die("Database connection error. Please try again later.");
}

try {
    $stmt_brand = $db->prepare("SELECT brand_id, brand_name FROM brands WHERE user_id = :user_id");
    $stmt_brand->bindParam(':user_id', $brand_admin_user_id, PDO::PARAM_INT);
    $stmt_brand->execute();
    $brand_info = $stmt_brand->fetch(PDO::FETCH_ASSOC);

    if ($brand_info) {
        $_SESSION['brand_id'] = $brand_info['brand_id'];
        $_SESSION['brand_name'] = $brand_info['brand_name'];
        $assigned_brand_id = $brand_info['brand_id'];
        $assigned_brand_name = $brand_info['brand_name'];
    } else {
        // Brand admin user is not assigned to any brand. This is an invalid state for a brand admin.
        // Log them out or redirect with an error.
        session_destroy(); // Clear session
        $_SESSION['admin_message'] = "<div class='admin-message error'>Your Brand Admin account is not assigned to a brand. Please contact support.</div>";
        $login_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/login.php?reason=no_brand_assigned' : '../public/login.php?reason=no_brand_assigned';
        header("Location: " . $login_url);
        exit;
    }
} catch (PDOException $e) {
    error_log("Brand Admin Auth - Error fetching assigned brand: " . $e->getMessage());
    die("Database error during brand assignment check. Please try again later.");
}

// If we reach here, the user is a brand_admin AND is assigned to a brand.
// The $_SESSION['brand_id'] and $_SESSION['brand_name'] are now set.

?>
