<?php
// src/includes/header.php

// Ensure config.php (which defines all necessary constants and starts session) is loaded.
// All public/*.php files should require_once config.php before this header.
$config_path_from_header = dirname(__DIR__) . '/config/config.php';
if (file_exists($config_path_from_header)) {
    require_once $config_path_from_header;
} else {
    // If config.php is missing, this is a critical error.
    die("CRITICAL ERROR: config.php not found. Expected at: " . htmlspecialchars($config_path_from_header));
}

// At this point, session_start() should have already been called by config.php
// Remember: For production, ensure error display is turned OFF in config.php.

// get_asset_url function is now defined in config.php for global availability.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(SITE_NAME); ?> - <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Welcome'; ?></title>

    <?php
    // Add a favicon link
    if (defined('FAVICON_PATH') && !empty(FAVICON_PATH)) {
        echo '<link rel="icon" href="' . get_asset_url(FAVICON_PATH) . '" type="image/x-icon">';
    } else {
        // Fallback or default favicon if not set in DB/config
        // Ensure you have a favicon.ico in your public/ directory for this fallback.
        echo '<link rel="icon" href="' . get_asset_url('favicon.ico') . '" type="image/x-icon">';
    }
    ?>

    <?php
    $css_version = '1.0.0'; // Update this version number when style.css changes in production
    ?>
    <link rel="stylesheet" href="<?php echo get_asset_url('css/style.css?v=' . $css_version); ?>">

</head>
<body>

    <header class="site-header">
        <?php /* Removed: Top bar for announcements, secondary navigation, or contact info */ ?>
        <?php // The entire <div class="header-top-bar"> block has been removed as per request. ?>

        <div class="header-main">
            <div class="container">
                <div class="site-logo">
                    <a href="<?php echo get_asset_url('index'); ?>">
                        <?php
                        // Display logo image if SITE_LOGO_PATH is defined and not empty, otherwise display site name as H1
                        if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
                            // FIX: Use get_asset_url for SITE_LOGO_PATH
                            $logo_src = get_asset_url('uploads/' . ltrim(SITE_LOGO_PATH, '/'));
                            echo '<img src="' . htmlspecialchars($logo_src) . '" alt="' . htmlspecialchars(SITE_NAME) . ' Logo" class="site-logo-image">';
                        } else {
                            echo '<h1>' . htmlspecialchars(SITE_NAME) . '</h1>';
                        }
                        ?>
                    </a>
                </div>

                <nav class="main-navigation">
                    <ul>
                        <li><a href="<?php echo get_asset_url('index'); ?>">Home</a></li>
                        <li><a href="<?php echo get_asset_url('products'); ?>">All Products</a></li>
                        <li><a href="<?php echo get_asset_url('categories'); ?>">Categories</a></li>
                        <li><a href="<?php echo get_asset_url('brands'); ?>">Brands</a></li>
                        <li><a href="<?php echo get_asset_url('about'); ?>">About Us</a></li>
                        <li><a href="<?php echo get_asset_url('contact'); ?>">Contact</a></li>
                    </ul>
                </nav>

                <div class="header-actions">
                    <ul>
                        <?php if (isset($_SESSION['user_id'])): // Check if user is logged in ?>
                            <?php
                                // Determine the display name; default to 'User' if first_name isn't set
                                $display_name = isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name']))
                                                ? htmlspecialchars($_SESSION['first_name'])
                                                : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User');
                            ?>
                            <li class="user-profile-link"><a href="<?php echo get_asset_url('my_account'); ?>">My Account (<?php echo $display_name; ?>)</a></li>
                            <li class="logout-link"><a href="<?php echo get_asset_url('logout'); ?>">Logout</a></li>
                            <?php /* Cart link only shown if user is logged in */ ?>
                            <li class="cart-link">
                                <a href="<?php echo get_asset_url('cart'); ?>">
                                    Cart
                                    <span class="cart-count">
                                        <?php
                                        // Updated cart count logic
                                        $cart_display_count = 0;
                                        // Only try to sum cart if session cart exists and is an array
                                        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                            $cart_display_count = array_sum($_SESSION['cart']);
                                        }
                                        echo $cart_display_count;
                                        ?>
                                    </span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="login-link"><a href="<?php echo get_asset_url('login'); ?>">Login</a></li>
                            <li class="register-link"><a href="<?php echo get_asset_url('register'); ?>">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="header-search-area">
            <div class="container">
                <form action="<?php echo get_asset_url('products'); ?>" method="GET" class="search-form">
                    <input type="search" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" aria-label="Search products">
                    <button type="submit" aria-label="Perform search">🔍</button>
                </form>
            </div>
        </div>

    </header>

    <main class="site-main">
        <div class="container">