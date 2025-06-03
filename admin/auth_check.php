<?php
// admin/auth_check.php

// This script should be included at the very top of all super admin pages.

// Ensure session is started. If header.php (which starts session) from the main site 
// is not used here, we need to start it manually.
// Assuming a separate admin session context or that config.php is included.

if (session_status() == PHP_SESSION_NONE) {
    // If config.php (which might define SESSION_NAME) is not included yet by an admin bootstrap,
    // you might need to define it or ensure it's loaded.
    // For simplicity, let's assume SESSION_NAME is available or default session name is used.
    if (defined('SESSION_NAME') && !headers_sent()) { // Check if SESSION_NAME is defined from main config
        session_name(SESSION_NAME);
    }
    if(!headers_sent()) {
        session_start();
    } else {
        // This is a problem, headers already sent.
        // Log this error and potentially die or redirect.
        error_log("Admin Auth: Session could not be started because headers were already sent.");
        // For a real admin panel, you'd redirect to an error page or login.
        die("A session error occurred. Please contact support. (Headers Sent)");
    }
}


// Define SITE_URL if not already defined (e.g. if main config.php isn't loaded yet)
// This is important for redirects.
if (!defined('SITE_URL')) {
    // Attempt to determine SITE_URL relative to this admin directory.
    // This is a basic guess; ideally, config.php is included first.
    // Assuming admin is one level down from public, and public is one level down from root.
    // http://localhost/baladymall/public
    // This might need adjustment based on your exact setup if config.php isn't pre-included.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Assuming admin is /admin/ and public is /public/ at the same level from project root
    // e.g. /baladymall/admin/ and /baladymall/public/
    // So, to get to /public/, we go up one from /admin/ and then to /public/
    $public_path_segment = str_replace('/admin', '/public', dirname($_SERVER['PHP_SELF']));
    // define('SITE_URL', $protocol . $host . rtrim($public_path_segment, '/'));
    // A more robust way is to ensure config.php is always loaded first.
    // For now, let's assume config.php (which defines SITE_URL) will be included by each admin page.
    // If not, the redirect below might fail.
}


// Check if user is logged in and is a super_admin
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to main site login page
    // Store the intended admin URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Current admin page URL
    $login_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/login.php?auth_required_admin=true' : '../public/login.php?auth_required_admin=true';
    header("Location: " . $login_url);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    // Logged in, but not a super_admin.
    // Redirect to their account page or a "permission denied" page on the main site.
    // Or, if you have a generic error page for admin:
    // header("Location: " . rtrim(SITE_URL, '/') . "/admin/permission_denied.php");
    
    // For now, redirect to main site's my_account page with an error message.
    $account_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/my_account.php?admin_access_denied=true' : '../public/my_account.php?admin_access_denied=true';
    // It might be better to log them out or show a specific "access denied" page within the admin scope if one exists.
    // For simplicity, redirecting to main site account.
    // You could also just destroy their session and send to login:
    // session_destroy();
    // header("Location: " . $login_url . "&reason=not_admin");
    // exit;

    // Display a simple error and link to go back or to their account.
    // This avoids complex redirection logic if SITE_URL isn't perfectly set here.
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 50px auto; max-width: 600px; border-radius: 5px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have sufficient permissions to access this area.</p>";
    $main_site_link = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/index.php' : '../public/index.php';
    echo "<p><a href='" . htmlspecialchars($main_site_link) . "' style='color: #007bff;'>Return to Main Site</a></p>";
    if (isset($_SESSION['user_id'])) {
        $my_account_link = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/my_account.php' : '../public/my_account.php';
        echo "<p><a href='" . htmlspecialchars($my_account_link) . "' style='color: #007bff;'>Go to My Account</a></p>";
    }
    echo "</div>";
    exit; // Stop further script execution
}

// If we reach here, the user is a logged-in super_admin.
// You might want to regenerate session ID for added security upon entering admin area.
// if (!isset($_SESSION['admin_session_regenerated'])) {
//     session_regenerate_id(true);
//     $_SESSION['admin_session_regenerated'] = true;
// }

?>
