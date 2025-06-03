<?php
// admin/includes/header.php

// Ensure main config is loaded for SITE_URL, DB_NAME etc.
// This should be done by the calling page (e.g., admin/index.php) before including this header.
if (!defined('SITE_URL')) {
    // Fallback attempt if main config wasn't loaded by the calling script.
    // This is not ideal; the calling script should handle loading the main config.
    $main_config_path_header = dirname(dirname(__DIR__)) . '/src/config/config.php'; 
    if (file_exists($main_config_path_header)) {
        require_once $main_config_path_header;
    } else {
        // If SITE_URL is critical and not defined, behavior here might be unpredictable.
        // Consider a more robust error or default if absolutely necessary.
        // For now, we'll proceed, but links might be relative if SITE_URL isn't found.
        // error_log("ADMIN HEADER WARNING: SITE_URL not defined. Main config might be missing.");
    }
}

// The auth_check.php (included by the calling page) should have already started the session.

// $admin_base_for_assets is for relative paths to CSS/JS from the current PHP file's location.
// It should be set by the calling script (e.g., admin/index.php sets it to '.', admin/brands/index.php would set it to '..')
$admin_base_for_assets = $admin_base_url ?? '.'; // Default to current dir if not set by calling page

// Define ADMIN_BASE_URL_FOR_LINKS for constructing absolute URLs for navigation links
if (!defined('ADMIN_BASE_URL_FOR_LINKS')) {
    if (defined('SITE_URL')) {
        // Example: SITE_URL = 'http://localhost/baladymall/public'
        // We want ADMIN_BASE_URL_FOR_LINKS = 'http://localhost/baladymall/admin'
        
        $siteUrlString = SITE_URL;
        // Remove '/public' from the end of SITE_URL if it exists
        if (substr($siteUrlString, -7) === '/public') {
            $basePath = substr($siteUrlString, 0, -7);
        } elseif (substr($siteUrlString, -6) === 'public') { // Handle case without trailing slash
            $basePath = substr($siteUrlString, 0, -6);
        } else {
            // If SITE_URL doesn't end with /public, assume it's the project root or something else.
            // This might need adjustment based on your specific SITE_URL structure.
            // For now, let's assume SITE_URL is a base that we can append /admin to,
            // or if it's already pointing to a subfolder, we need a more specific logic.
            // A common case is SITE_URL is the public root.
            $basePath = rtrim($siteUrlString, '/'); // Fallback: use SITE_URL as is, rtrim slash
        }
        define('ADMIN_BASE_URL_FOR_LINKS', rtrim($basePath, '/') . '/admin');
    } else {
        // Fallback if SITE_URL is not defined - less reliable for absolute links
        // Tries to guess based on current script path.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $script_dir_path = dirname($_SERVER['SCRIPT_NAME']); // e.g. /baladymall/admin/includes
        // Go up one level from 'includes' to get to 'admin'
        $admin_segment_guess = dirname($script_dir_path); // e.g., /baladymall/admin
        if (basename($admin_segment_guess) !== 'admin' && strpos($script_dir_path, '/admin') !== false) {
             // If we are deeper, try to find /admin part
            $admin_segment_guess = substr($script_dir_path, 0, strpos($script_dir_path, '/admin') + strlen('/admin'));
        } elseif (basename($admin_segment_guess) !== 'admin') {
            $admin_segment_guess = rtrim($script_dir_path, '/'); // If not in /admin/includes, use script_dir
        }
        define('ADMIN_BASE_URL_FOR_LINKS', $protocol . $host . rtrim($admin_segment_guess, '/'));
    }
}


// Determine the display name for the admin
$admin_display_name = 'Admin'; // Default
if (isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name']))) {
    $admin_display_name = htmlspecialchars($_SESSION['first_name']);
} elseif (isset($_SESSION['username'])) {
    $admin_display_name = htmlspecialchars($_SESSION['username']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($admin_page_title) ? htmlspecialchars($admin_page_title) : 'Admin Panel'; ?> - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($admin_base_for_assets); ?>/css/admin_style.css?v=<?php echo time(); ?>">
    </head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="admin-header-inner">
                <div class="admin-logo">
                    <a href="<?php echo ADMIN_BASE_URL_FOR_LINKS; ?>/index.php">
                        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> - Super Admin
                    </a>
                </div>
                <nav class="admin-main-nav">
                    <ul>
                        <li>Welcome, <?php echo $admin_display_name; ?>!</li>
                        <li><a href="<?php echo ADMIN_BASE_URL_FOR_LINKS; ?>/index.php">Dashboard</a></li>
                        <li><a href="<?php echo ADMIN_BASE_URL_FOR_LINKS; ?>/brands.php">Brands</a></li>
                        <li><a href="#">Products</a></li> <?php // Placeholder ?>
                        <li><a href="#">Orders</a></li> <?php // Placeholder ?>
                        <li><a href="<?php echo ADMIN_BASE_URL_FOR_LINKS; ?>/users.php">Users</a></li>
                        <li><a href="#">Settings</a></li> <?php // Placeholder ?>
                        <li><a href="<?php echo rtrim(SITE_URL, '/') . '/logout.php?admin_logout=true'; ?>" class="logout-link">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main class="admin-main">
            <div class="admin-container">
