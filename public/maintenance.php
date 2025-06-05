<?php
// public/maintenance.php

// Attempt to load full config.php to get SITE_NAME, SITE_URL, and DB functions.
// This page itself is the maintenance page, so the maintenance mode check in config.php
// (which would redirect *to* this page) should not cause an infinite loop.
// The check in config.php: `!$is_maintenance_script` is designed to handle this.
$config_path_from_public = __DIR__ . '/../src/config/config.php';

// First, check if config.php exists. If not, we have a critical error and use minimal fallback.
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // If config.php is absolutely missing, define minimal constants for the error message.
    if (!defined('SITE_NAME')) define('SITE_NAME', 'BaladyMall');
    // Ensure esc_html is available as a last resort
    if (!function_exists('esc_html')) {
        function esc_html($string) { return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); }
    }
    // Set headers and die, as no proper site functionality is possible.
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    die("Critical error: Main configuration file not found. System is unavailable.");
}

// Set HTTP 503 status headers *after* config.php has run.
// This is important because config.php might contain its own redirection logic
// (e.g., if maintenance mode is off for admins) which should execute first.
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
// header('Retry-After: 3600'); // Optional: Tells search engines when to retry (e.g., 1 hour later)

// Fetch maintenance message and site name dynamically from DB via get_site_setting or use fallbacks.
// These constants and functions should be available from config.php now.
$maintenance_message = 'Our site is currently undergoing scheduled maintenance. We should be back shortly. Thank you for your patience.';
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Our Website';
$admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
$site_logo_path = defined('SITE_LOGO_PATH') ? SITE_LOGO_PATH : '';

// Only try to fetch from DB if a connection is available after config.php has run
// and if get_site_setting function exists.
if (isset($db) && $db instanceof PDO && function_exists('get_site_setting')) {
    try {
        $dynamic_message = get_site_setting($db, 'maintenance_message');
        if ($dynamic_message) {
            $maintenance_message = $dynamic_message;
        }
        $dynamic_site_name = get_site_setting($db, 'site_name');
        if ($dynamic_site_name) {
            $site_name = $dynamic_site_name;
        }
        $dynamic_admin_email = get_site_setting($db, 'admin_email');
        if ($dynamic_admin_email) {
            $admin_email = $dynamic_admin_email;
        }
        $dynamic_site_logo_path = get_site_setting($db, 'site_logo_url'); // Assuming site_logo_url is in DB
        if ($dynamic_site_logo_path) {
            $site_logo_path = $dynamic_site_logo_path;
        }
    } catch (Exception $e) {
        // Log the error but use the default/fallback messages
        error_log("Maintenance Page: Failed to fetch dynamic settings from DB: " . $e->getMessage());
    }
}

$page_title = esc_html($site_name) . " - Under Maintenance";

// Minimal CSS version for potential external stylesheet cache-busting
$css_version = '1.0.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* Minimal inline styles for maintenance page to ensure it always renders */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
            color: #333;
            text-align: center;
            flex-direction: column;
        }
        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
        }
        h1 {
            color: #007bff;
            margin-bottom: 20px;
            font-size: 2.2em;
        }
        p {
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .contact-info p {
            margin-bottom: 5px;
            font-size: 1em;
        }
        .contact-info a {
            color: #007bff;
            text-decoration: none;
        }
        .contact-info a:hover {
            text-decoration: underline;
        }
        .site-logo-container {
            margin-bottom: 20px;
        }
        .site-logo-container img {
            max-width: 150px;
            height: auto;
        }
    </style>
    <?php
    // Include the main stylesheet for site-wide styling, if desired, but keep minimal inline styles as fallback.
    // Use get_asset_url for consistency.
    // Only include if SITE_URL is defined (from config.php) and the file exists.
    if (defined('SITE_URL') && defined('PROJECT_ROOT_PATH') && file_exists(PROJECT_ROOT_PATH . '/public/css/style.css')) {
        // Ensure get_asset_url is available before trying to use it.
        if (function_exists('get_asset_url')) {
            echo '<link rel="stylesheet" href="' . get_asset_url('css/style.css?v=' . $css_version) . '">';
        } else {
            // Fallback for CSS if get_asset_url somehow isn't available
            echo '<link rel="stylesheet" href="' . rtrim(SITE_URL, '/') . '/css/style.css?v=' . $css_version . '">';
        }
    }
    ?>
</head>
<body>
    <div class="container">
        <?php
        // Display logo image if SITE_LOGO_PATH is defined and not empty, otherwise display site name as H1
        // Using get_asset_url for logo path for consistency.
        if (!empty($site_logo_path) && function_exists('get_asset_url')) {
            echo '<div class="site-logo-container"><img src="' . get_asset_url($site_logo_path) . '" alt="' . esc_html($site_name) . ' Logo"></div>';
        } else {
            echo '<h1>' . esc_html($site_name) . '</h1>';
        }
        ?>

        <h1>Under Maintenance</h1>
        <p><?php echo nl2br(esc_html($maintenance_message)); ?></p>
        <p>We apologize for any inconvenience.</p>

        <div class="contact-info">
            <p>Thank you for your patience.</p>
            <p>If you have urgent inquiries, please contact us at:</p>
            <p>Email: <a href="mailto:<?php echo esc_html($admin_email); ?>"><?php echo esc_html($admin_email); ?></a></p>
        </div>
    </div>
</body>
</html>