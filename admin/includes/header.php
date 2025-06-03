<?php
// admin/includes/header.php

// Ensure main config is loaded for SITE_URL, DB_NAME etc.
// This should be done by the calling page (e.g., admin/index.php) before including this header.
if (!defined('SITE_URL')) {
    $main_config_path_header = dirname(dirname(__DIR__)) . '/src/config/config.php'; 
    if (file_exists($main_config_path_header)) {
        require_once $main_config_path_header;
    } else {
        // error_log("ADMIN HEADER WARNING: SITE_URL not defined. Main config might be missing.");
        // Define a fallback SITE_URL if absolutely necessary, though it's better if config.php handles it.
        // This fallback is very basic.
        $protocol_fallback = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host_fallback = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Try to guess the base path, assuming admin is a direct subfolder of project root
        $script_name_fallback = $_SERVER['SCRIPT_NAME'] ?? '';
        $path_segments_fallback = explode('/', trim($script_name_fallback, '/'));
        $project_base_path_fallback = isset($path_segments_fallback[0]) && $path_segments_fallback[0] !== 'admin' ? '/' . $path_segments_fallback[0] : '';
        if(!defined('SITE_URL')) define('SITE_URL', $protocol_fallback . $host_fallback . $project_base_path_fallback . '/public'); // Default to /public
    }
}

// The auth_check.php (included by the calling page) should have already started the session.

// $admin_base_for_assets is for relative paths to CSS/JS from the current PHP file's location.
// It should be set by the calling script (e.g., admin/index.php sets it to '.', admin/brands/index.php would set it to '..')
$admin_base_for_assets = $admin_base_url ?? '.'; // Default to current dir if not set by calling page

// Define ADMIN_BASE_URL_FOR_LINKS for constructing absolute URLs for navigation links
if (!defined('ADMIN_BASE_URL_FOR_LINKS')) {
    if (defined('SITE_URL')) {
        $current_site_url_from_config = rtrim(SITE_URL, '/'); // SITE_URL from config.php

        // Scenario 1: SITE_URL from config.php (when called from an admin script) might already be the admin path.
        // e.g., http://localhost/baladymall/admin
        if (substr($current_site_url_from_config, -6) === '/admin') {
            define('ADMIN_BASE_URL_FOR_LINKS', $current_site_url_from_config);
        }
        // Scenario 2: SITE_URL from config.php is the public path.
        // e.g., http://localhost/baladymall/public
        elseif (substr($current_site_url_from_config, -7) === '/public') {
            $projectBaseUrl = substr($current_site_url_from_config, 0, -7); // Gives http://localhost/baladymall
            define('ADMIN_BASE_URL_FOR_LINKS', rtrim($projectBaseUrl, '/') . '/admin');
        }
        // Scenario 3: SITE_URL is something else (e.g., project root http://localhost/baladymall)
        else {
            // Assume SITE_URL is the project's web root, append /admin
            define('ADMIN_BASE_URL_FOR_LINKS', $current_site_url_from_config . '/admin');
        }
    } else {
        // Fallback if SITE_URL is somehow not defined even after trying to include config.php.
        // This is a last resort and indicates a deeper configuration issue.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Basic guess: assumes admin is a direct subfolder of the domain or a known project folder.
        // e.g. if SCRIPT_NAME is /baladymall/admin/index.php, dirname is /baladymall/admin
        $adminPathGuess = dirname($_SERVER['SCRIPT_NAME'] ?? ''); 
        // If script is deeper like /baladymall/admin/includes/file.php, try to go up.
        if (basename($adminPathGuess) === 'includes' && basename(dirname($adminPathGuess)) === 'admin') {
            $adminPathGuess = dirname($adminPathGuess);
        }
        define('ADMIN_BASE_URL_FOR_LINKS', $protocol . $host . rtrim($adminPathGuess, '/'));
    }
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
$brand_pages = ['brands.php', 'add_brand.php', 'edit_brand.php'];
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($admin_base_for_assets); ?>/css/admin_style.css?v=<?php echo time(); // Cache busting ?>">
    <?php if (defined('FAVICON_PATH') && !empty(FAVICON_PATH) && file_exists(PUBLIC_UPLOADS_PATH . FAVICON_PATH)): ?>
        <link rel="icon" href="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH); ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . FAVICON_PATH); ?>" type="image/x-icon">
    <?php endif; ?>
    </head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="admin-header-inner">
                <div class="admin-logo">
                    <a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/index.php">
                        <?php if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH) && file_exists(PUBLIC_UPLOADS_PATH . SITE_LOGO_PATH)): ?>
                            <img src="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . SITE_LOGO_PATH); ?>?v=<?php echo time();?>" alt="<?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> Logo" style="max-height: 40px; vertical-align: middle; margin-right: 10px;">
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
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/orders.php">Orders</a></li> <?php // Placeholder, ensure orders.php exists or link is # ?>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/users.php" class="<?php echo (in_array($current_page_script, $user_pages)) ? 'active' : ''; ?>">Users</a></li>
                        <li><a href="<?php echo rtrim(ADMIN_BASE_URL_FOR_LINKS, '/'); ?>/settings.php" class="<?php echo (in_array($current_page_script, $settings_pages)) ? 'active' : ''; ?>">Settings</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/') . '/logout.php?admin_logout=true'; ?>" class="logout-link">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <main class="admin-main">
            <div class="admin-container">
