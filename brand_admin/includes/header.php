<?php
// brand_admin/includes/header.php

// Ensure main config is loaded for SITE_URL etc.
// This block should ideally not be needed if the calling PHP file correctly
// includes config.php BEFORE this header.php.
// Given current project structure, config.php is always included first by main scripts.
// Therefore, we can remove the redundant SITE_URL definition logic here.
// Example: If it's not defined, it means config.php wasn't loaded, which is a critical error.
if (!defined('SITE_URL')) {
    // This indicates a missing include in the calling page.
    // For production, you might want to log this and redirect or die.
    // For development, we'll try to load it.
    $main_config_path_header = dirname(dirname(__DIR__)) . '/src/config/config.php';
    if (file_exists($main_config_path_header)) {
        require_once $main_config_path_header;
    } else {
        die("CRITICAL BRAND ADMIN HEADER ERROR: Main config.php not found. Ensure it's loaded by the calling page.");
    }
}

// The auth_check.php (included by the calling page) should have already started the session
// and set $_SESSION['brand_id'] and $_SESSION['brand_name'].

// $brand_admin_base_for_assets is for relative paths to CSS/JS from the current PHP file's location.
$brand_admin_base_for_assets = $brand_admin_base_url ?? '.'; // Default to current dir if not set by calling page

// Define BRAND_ADMIN_BASE_URL_FOR_LINKS for constructing absolute URLs for navigation links
if (!defined('BRAND_ADMIN_BASE_URL_FOR_LINKS')) {
    // FIX: More robust calculation based on SITE_URL and PROJECT_ROOT_PATH structure
    // SITE_URL is e.g., http://localhost/baladymall/public
    // PROJECT_ROOT_PATH is /path/to/baladymall
    // BRAND_ADMIN_BASE_URL_FOR_LINKS should be http://localhost/baladymall/brand_admin
    $current_site_url_from_config = rtrim(SITE_URL, '/');

    // Determine the base URL for the project itself (e.g., http://localhost/baladymall)
    // This assumes /public is always at the end of SITE_URL and one level below project root.
    $project_base_url_auto_derived = substr($current_site_url_from_config, 0, strrpos($current_site_url_from_config, '/public'));
    
    // Now construct the brand_admin URL using the project base URL
    define('BRAND_ADMIN_BASE_URL_FOR_LINKS', rtrim($project_base_url_auto_derived, '/') . '/brand_admin');
}

// Determine the display name for the brand admin
$brand_admin_display_name = 'Brand Admin';
if (isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name']))) {
    $brand_admin_display_name = htmlspecialchars($_SESSION['first_name']);
} elseif (isset($_SESSION['username'])) {
    $brand_admin_display_name = htmlspecialchars($_SESSION['username']);
}

$assigned_brand_name = $_SESSION['brand_name'] ?? 'Your Brand'; // FIX: Coalesce for htmlspecialchars

// Determine active page for navigation styling
$current_page_script = basename($_SERVER['PHP_SELF']);
$product_pages = ['products.php', 'add_product.php', 'edit_product.php', 'batch_upload_products.php']; // FIX: Added batch_upload
$order_pages = ['orders.php', 'order_detail.php'];
$settings_pages = ['settings.php'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($brand_admin_page_title) ? htmlspecialchars($brand_admin_page_title) : 'Brand Admin Panel'; ?> - <?php echo htmlspecialchars($assigned_brand_name); ?></title>
    <?php
    // FIX: Use filemtime for cache busting for CSS
    $brand_admin_css_full_path = PROJECT_ROOT_PATH . '/brand_admin' . '/css/brand_admin_style.css'; // Adjust path if CSS is elsewhere
    $css_cache_buster = file_exists($brand_admin_css_full_path) ? filemtime($brand_admin_css_full_path) : time();
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($brand_admin_base_for_assets); ?>/css/brand_admin_style.css?v=<?php echo $css_cache_buster; ?>">
    <?php
    // FIX: Robust image path determination for favicon
    $favicon_display_url = '';
    if (defined('FAVICON_PATH') && !empty(FAVICON_PATH)) {
        if (filter_var(FAVICON_PATH, FILTER_VALIDATE_URL) || strpos(FAVICON_PATH, '//') === 0) {
            $favicon_display_url = htmlspecialchars(FAVICON_PATH);
        } else {
            // Only check file_exists if it's a local path
            if (file_exists(PUBLIC_UPLOADS_PATH . FAVICON_PATH)) {
                $favicon_display_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH);
            }
        }
    }
    if ($favicon_display_url): ?>
        <link rel="icon" href="<?php echo $favicon_display_url; ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo $favicon_display_url; ?>" type="image/x-icon">
    <?php endif; ?>
</head>
<body>
    <div class="brand-admin-wrapper">
        <header class="brand-admin-header">
            <div class="brand-admin-header-inner">
                <div class="brand-admin-logo">
                    <a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php">
                        <?php
                        // FIX: Robust image path determination for site logo
                        $site_logo_display_url = '';
                        if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
                            if (filter_var(SITE_LOGO_PATH, FILTER_VALIDATE_URL) || strpos(SITE_LOGO_PATH, '//') === 0) {
                                $site_logo_display_url = htmlspecialchars(SITE_LOGO_PATH);
                            } else {
                                // Only check file_exists if it's a local path
                                if (file_exists(PUBLIC_UPLOADS_PATH . SITE_LOGO_PATH)) {
                                    $site_logo_display_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . SITE_LOGO_PATH);
                                }
                            }
                        }
                        if ($site_logo_display_url): ?>
                            <img src="<?php echo $site_logo_display_url; ?>?v=<?php echo time();?>" alt="<?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> Logo" style="max-height: 40px; vertical-align: middle; margin-right: 10px;">
                        <?php endif; ?>
                        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> - Brand Admin (<?php echo htmlspecialchars($assigned_brand_name); ?>)
                    </a>
                </div>
                <nav class="brand-admin-main-nav">
                    <ul>
                        <li>Welcome, <?php echo $brand_admin_display_name; ?>!</li>
                        <li><a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php" class="<?php echo ($current_page_script === 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                        <li><a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/products.php" class="<?php echo (in_array($current_page_script, $product_pages)) ? 'active' : ''; ?>">Products</a></li>
                        <li><a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/orders.php" class="<?php echo (in_array($current_page_script, $order_pages)) ? 'active' : ''; ?>">Orders</a></li>
                        <li><a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/settings.php" class="<?php echo (in_array($current_page_script, $settings_pages)) ? 'active' : ''; ?>">Brand Settings</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/') . '/logout.php?brand_admin_logout=true'; ?>" class="logout-link">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main class="brand-admin-main">
            <div class="brand-admin-container">