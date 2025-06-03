<?php
// brand_admin/includes/header.php

// Ensure main config is loaded for SITE_URL etc.
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

// The auth_check.php (included by the calling page) should have already started the session
// and set $_SESSION['brand_id'] and $_SESSION['brand_name'].

// $brand_admin_base_for_assets is for relative paths to CSS/JS from the current PHP file's location.
$brand_admin_base_for_assets = $brand_admin_base_url ?? '.'; // Default to current dir if not set by calling page

// Define BRAND_ADMIN_BASE_URL_FOR_LINKS for constructing absolute URLs for navigation links
if (!defined('BRAND_ADMIN_BASE_URL_FOR_LINKS')) {
    if (defined('SITE_URL')) {
        $current_site_url_from_config = rtrim(SITE_URL, '/'); // SITE_URL from config.php

        // This logic assumes SITE_URL points to /public. We need to go up one level then to /brand_admin
        $projectBaseUrl = substr($current_site_url_from_config, 0, -7); // Gives http://localhost/baladymall
        define('BRAND_ADMIN_BASE_URL_FOR_LINKS', rtrim($projectBaseUrl, '/') . '/brand_admin');
    } else {
        // Fallback if SITE_URL is somehow not defined.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $adminPathGuess = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if (basename($adminPathGuess) === 'includes' && basename(dirname($adminPathGuess)) === 'brand_admin') {
            $adminPathGuess = dirname($adminPathGuess);
        }
        define('BRAND_ADMIN_BASE_URL_FOR_LINKS', $protocol . $host . rtrim($adminPathGuess, '/'));
    }
}

// Determine the display name for the brand admin
$brand_admin_display_name = 'Brand Admin';
if (isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name']))) {
    $brand_admin_display_name = htmlspecialchars($_SESSION['first_name']);
} elseif (isset($_SESSION['username'])) {
    $brand_admin_display_name = htmlspecialchars($_SESSION['username']);
}

$assigned_brand_name = $_SESSION['brand_name'] ?? 'Your Brand';

// Determine active page for navigation styling
$current_page_script = basename($_SERVER['PHP_SELF']);
$product_pages = ['products.php', 'add_product.php', 'edit_product.php'];
$order_pages = ['orders.php', 'order_detail.php'];
$settings_pages = ['settings.php'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($brand_admin_page_title) ? htmlspecialchars($brand_admin_page_title) : 'Brand Admin Panel'; ?> - <?php echo $assigned_brand_name; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($brand_admin_base_for_assets); ?>/css/brand_admin_style.css?v=<?php echo time(); // Cache busting ?>">
    <?php if (defined('FAVICON_PATH') && !empty(FAVICON_PATH) && file_exists(PUBLIC_UPLOADS_PATH . FAVICON_PATH)): ?>
        <link rel="icon" href="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH); ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH); ?>" type="image/x-icon">
    <?php endif; ?>
</head>
<body>
    <div class="brand-admin-wrapper">
        <header class="brand-admin-header">
            <div class="brand-admin-header-inner">
                <div class="brand-admin-logo">
                    <a href="<?php echo rtrim(BRAND_ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php">
                        <?php if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH) && file_exists(PUBLIC_UPLOADS_PATH . SITE_LOGO_PATH)): ?>
                            <img src="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . SITE_LOGO_PATH); ?>?v=<?php echo time();?>" alt="<?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> Logo" style="max-height: 40px; vertical-align: middle; margin-right: 10px;">
                        <?php endif; ?>
                        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> - Brand Admin (<?php echo $assigned_brand_name; ?>)
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
