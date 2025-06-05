<?php
// brand_admin/auth_check.php

// This script should be included at the very top of all brand_admin pages,
// AFTER the main config.php file has been included.
// It relies on config.php to have already:
// 1. Started the session (`session_start()`)
// 2. Defined necessary constants like SITE_URL.

// No need to session_start() or define SESSION_NAME/SITE_URL here, as config.php handles it.
// If config.php is *not* guaranteed to be included before this,
// then this file structure would need to be different, but your current setup ensures it.

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to main site login page
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Current brand admin page URL
    // SITE_URL is guaranteed to be defined because config.php is included before auth_check.php
    $login_url = rtrim(SITE_URL, '/') . '/login.php?auth_required_brand_admin=true';
    header("Location: " . $login_url);
    exit;
}

// Check if user is a brand_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'brand_admin') {
    // Logged in, but not a brand_admin.
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 50px auto; max-width: 600px; border-radius: 5px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have sufficient permissions to access this area. Only Brand Admins are allowed.</p>";
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

// If we reach here, the user is a logged-in brand_admin.
// Regenerate session ID for added security upon entering admin area to prevent session fixation.
// This check ensures it's only done once per admin session (or upon first access to an admin page).
if (!isset($_SESSION['brand_admin_session_regenerated'])) { // Use a specific flag for brand admin
    session_regenerate_id(true); // true deletes the old session file
    $_SESSION['brand_admin_session_regenerated'] = true;
}


// Now, fetch the brand_id associated with this brand_admin.
// This is CRUCIAL for restricting data access.
$brand_admin_user_id = $_SESSION['user_id'];
$assigned_brand_id = null;
$assigned_brand_name = null;

// Ensure DB connection is available
$db = getPDOConnection();
if (!$db) {
    error_log("Brand Admin Auth: Database connection failed for brand_admin_user_id: {$brand_admin_user_id}. File: " . __FILE__ . " Line: " . __LINE__); // FIX: Add file/line
    // Redirect to login with error message if DB fails
    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Database connection error. Please try again later.</div>"; // FIX: Consistent message key
    $login_url = rtrim(SITE_URL, '/') . '/login.php'; // Redirect to generic login
    header("Location: " . $login_url);
    exit;
}

try {
    // If brand_id is already in session, and we're not force-refreshing, use it.
    // This avoids a DB query on every page load if brand is already established.
    if (isset($_SESSION['brand_id']) && isset($_SESSION['brand_name'])) {
        $assigned_brand_id = $_SESSION['brand_id'];
        $assigned_brand_name = $_SESSION['brand_name'];
    } else {
        // Fetch from DB if not in session or force-refresh
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
            // Log them out and redirect with an error.
            session_destroy(); // Clear session
            // FIX: Consistent message key
            $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Your Brand Admin account is not assigned to a brand. Please contact support.</div>";
            $login_url = rtrim(SITE_URL, '/') . '/login.php?reason=no_brand_assigned';
            header("Location: " . $login_url);
            exit;
        }
    }
} catch (PDOException $e) {
    error_log("Brand Admin Auth - Error fetching assigned brand for user {$brand_admin_user_id}: " . $e->getMessage() . " File: " . __FILE__ . " Line: " . __LINE__); // FIX: Add file/line
    session_destroy(); // Clear session on critical DB error
    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>A critical database error occurred during brand assignment check. Please try again later.</div>"; // FIX: Consistent message key
    $login_url = rtrim(SITE_URL, '/') . '/login.php';
    header("Location: " . $login_url);
    exit;
}

// Ensure $_SESSION['brand_id'] is truly set before allowing script to continue.
// If brand_info was not found (and not redirected) or if $assigned_brand_id is somehow null
if (empty($assigned_brand_id)) {
    session_destroy();
    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Could not determine your assigned brand. Please contact support.</div>";
    $login_url = rtrim(SITE_URL, '/') . '/login.php';
    header("Location: " . $login_url);
    exit;
}

// These are now guaranteed to be set
$_SESSION['brand_id'] = $assigned_brand_id;
$_SESSION['brand_name'] = $assigned_brand_name;

?>