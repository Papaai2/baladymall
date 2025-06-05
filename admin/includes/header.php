<?php
// admin/includes/header.php

// Ensure main config is loaded for SITE_URL, DB_NAME etc.
// This should be done by the calling page (e.g., admin/index.php) before including this header.
// If not defined, it means a critical setup error in the calling script.
if (!defined('SITE_URL')) {
    $main_config_path_header = dirname(dirname(__DIR__)) . '/src/config/config.php';
    if (file_exists($main_config_path_header)) {
        require_once $main_config_path_header;
    } else {
        // This is a critical error. Die to prevent further issues.
        die("CRITICAL ADMIN HEADER ERROR: Main config.php not found. Ensure it's loaded by the calling page.");
    }
}

// The auth_check.php (included by the calling page) should have already started the session.

// $admin_base_for_assets is for relative paths to CSS/JS from the current PHP file's location.
// It should be set by the calling script (e.g., admin/index.php sets it to '.', admin/brands/index.php would set it to '..')
$admin_base_for_assets = $admin_base_url ?? '.'; // Default to current dir if not set by calling page

// Define ADMIN_BASE_URL_FOR_LINKS for constructing absolute URLs for navigation links
if (!defined('ADMIN_BASE_URL_FOR_LINKS')) {
    $current_site_url_from_config = rtrim(SITE_URL, '/'); // SITE_URL from config.php

    // This logic assumes SITE_URL points to /public. We need to go up one level then to /admin
    // Example: If SITE_URL is http://localhost/baladymall/public
    // Then projectBaseUrl will be http://localhost/baladymall
    $projectBaseUrl = substr($current_site_url_from_config, 0, strrpos($current_site_url_from_config, '/public'));
    if ($projectBaseUrl === false) { // Fallback if /public not found, e.g., SITE_URL is already domain root
        $projectBaseUrl = $current_site_url_from_config;
    }
    define('ADMIN_BASE_URL_FOR_LINKS', rtrim($projectBaseUrl, '/') . '/admin');
}


// Determine the display name for the admin
$admin_display_name = 'Admin';
if (isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name']))) {
    $admin_display_name = htmlspecialchars($_SESSION['first_name']);
} elseif (isset($_SESSION['username'])) {
    $admin_display_name = htmlspecialchars($_SESSION['username']);
}

// Determine active page for navigation styling
$current_page_script = basename($_SERVER['PHP_SELF']);
$product_pages = ['products.php', 'add_product.php', 'edit_product.php'];
$brand_pages = ['brands.php', 'add_brand.php', 'brand_edit.php']; // FIX: changed edit_brand to brand_edit
$user_pages = ['users.php', 'edit_user.php'];
$category_pages = ['categories.php', 'add_category.php', 'edit_category.php'];
$settings_pages = ['settings.php'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($admin_page_title) ? htmlspecialchars($admin_page_title) : 'Admin Panel'; ?> - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?></title>
    <?php
    // FIX: Use filemtime for cache busting for CSS
    $admin_css_full_path = PROJECT_ROOT_PATH . '/admin' . '/css/admin_style.css'; // Adjust path if CSS is elsewhere
    $css_cache_buster = file_exists($admin_css_full_path) ? filemtime($admin_css_full_path) : time();
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($admin_base_for_assets); ?>/css/admin_style.css?v=<?php echo $css_cache_buster; ?>">
    <?php
    // FIX: Robust image path determination for favicon
    $favicon_display_url = '';
    if (defined('FAVICON_PATH') && !empty(FAVICON_PATH)) {
        if (filter_var(FAVICON_PATH, FILTER_VALIDATE_URL) || strpos(FAVICON_PATH, '//') === 0) {
            $favicon_display_url = htmlspecialchars(FAVICON_PATH);
        } else {
            $full_favicon_path = PUBLIC_UPLOADS_PATH . FAVICON_PATH;
            if (file_exists($full_favicon_path) && is_file($full_favicon_path)) {
                $favicon_display_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH);
            }
        }
    }
    if ($favicon_display_url): ?>
        <link rel="icon" href="<?php echo $favicon_display_url; ?>?v=<?php echo $css_cache_buster; ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo $favicon_display_url; ?>?v=<?php echo $css_cache_buster; ?>" type="image/x-icon">
    <?php endif; ?>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="admin-header-inner">
                <div class="admin-logo">
                    <a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php">
                        <?php
                        // FIX: Robust image path determination for site logo
                        $site_logo_display_url = '';
                        if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
                            if (filter_var(SITE_LOGO_PATH, FILTER_VALIDATE_URL) || strpos(SITE_LOGO_PATH, '//') === 0) {
                                $site_logo_display_url = htmlspecialchars(SITE_LOGO_PATH);
                            } else {
                                $full_site_logo_path = PUBLIC_UPLOADS_PATH . SITE_LOGO_PATH;
                                if (file_exists($full_site_logo_path) && is_file($full_site_logo_path)) {
                                    $site_logo_display_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . SITE_LOGO_PATH);
                                }
                            }
                        }
                        if ($site_logo_display_url): ?>
                            <img src="<?php echo $site_logo_display_url; ?>?v=<?php echo $css_cache_buster;?>" alt="<?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> Logo" style="max-height: 40px; vertical-align: middle; margin-right: 10px;">
                        <?php endif; ?>
                        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> - Super Admin
                    </a>
                </div>
                <nav class="admin-main-nav">
                    <ul>
                        <li>Welcome, <?php echo $admin_display_name; ?>!</li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php" class="<?php echo ($current_page_script === 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/brands.php" class="<?php echo (in_array($current_page_script, $brand_pages)) ? 'active' : ''; ?>">Brands</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/categories.php" class="<?php echo (in_array($current_page_script, $category_pages)) ? 'active' : ''; ?>">Categories</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/products.php" class="<?php echo (in_array($current_page_script, $product_pages)) ? 'active' : ''; ?>">Products</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/orders.php" class="<?php echo ($current_page_script === 'orders.php' || $current_page_script === 'order_detail.php') ? 'active' : ''; ?>">Orders</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/users.php" class="<?php echo (in_array($current_page_script, $user_pages)) ? 'active' : ''; ?>">Users</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/settings.php" class="<?php echo (in_array($current_page_script, $settings_pages)) ? 'active' : ''; ?>">Settings</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/') . '/logout.php?admin_logout=true'; ?>" class="logout-link">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main class="admin-main">
            <div class="admin-container">