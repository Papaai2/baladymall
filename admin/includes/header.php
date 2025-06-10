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
$brand_pages = ['brands.php', 'add_brand.php', 'brand_edit.php'];
$user_pages = ['users.php', 'edit_user.php'];
$category_pages = ['categories.php', 'add_category.php', 'edit_category.php'];
$settings_pages = ['settings.php'];
$review_pages = ['reviews.php']; // Assuming a future reviews page for admin


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($admin_page_title) ? htmlspecialchars($admin_page_title) : 'Admin Panel'; ?> - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?></title>
    <?php
    // FIX: Use get_asset_url for cache busting for CSS from PUBLIC_ROOT_PATH structure
    // This assumes admin_style.css is at public/admin/css/admin_style.css
    $admin_css_full_path_for_mtime = PUBLIC_ROOT_PATH . '/admin/css/admin_style.css'; // Corrected path for filemtime
    $css_cache_buster = file_exists($admin_css_full_path_for_mtime) ? filemtime($admin_css_full_path_for_mtime) : time();
    ?>
    <link rel="stylesheet" href="<?php echo get_asset_url('admin/css/admin_style.css?v=' . $css_cache_buster); ?>">
    <?php
    // FIX: Robust image path determination for favicon using PUBLIC_UPLOADS_URL_BASE
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
                    <a href="<?php echo ADMIN_ROOT_URL; ?>/index.php">
                        <?php
                        // FIX: Robust image path determination for site logo using PUBLIC_UPLOADS_URL_BASE
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
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/index.php" class="<?php echo ($current_page_script === 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/brands.php" class="<?php echo (in_array($current_page_script, $brand_pages)) ? 'active' : ''; ?>">Brands</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/categories.php" class="<?php echo (in_array($current_page_script, $category_pages)) ? 'active' : ''; ?>">Categories</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/products.php" class="<?php echo (in_array($current_page_script, $product_pages)) ? 'active' : ''; ?>">Products</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/orders.php" class="<?php echo ($current_page_script === 'orders.php' || $current_page_script === 'order_detail.php') ? 'active' : ''; ?>">Orders</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/users.php" class="<?php echo (in_array($current_page_script, $user_pages)) ? 'active' : ''; ?>">Users</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/reviews.php" class="<?php echo (in_array($current_page_script, $review_pages)) ? 'active' : ''; ?>">Reviews</a></li>
                        <li><a href="<?php echo ADMIN_ROOT_URL; ?>/settings.php" class="<?php echo (in_array($current_page_script, $settings_pages)) ? 'active' : ''; ?>">Settings</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/') . '/logout.php?admin_logout=true'; ?>" class="logout-link">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main class="admin-main">
            <div class="admin-container">
                <?php
                // Include the common message display logic
                include_once PROJECT_ROOT_PATH . '/src/includes/display_admin_messages.php';
                ?>