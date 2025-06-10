<?php
// src/includes/header.php

// This file assumes config.php has already been loaded by the calling script.
if (!defined('PROJECT_ROOT_PATH')) {
    die("CRITICAL ERROR: config.php must be loaded before header.php.");
}

// get_asset_url function is defined in config.php and available here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? esc_html($page_title) : esc_html(SITE_NAME); ?></title>

    <?php
    // Favicon path is defined in config.php
    $favicon_url = defined('FAVICON_PATH') && !empty(FAVICON_PATH) ? get_asset_url(FAVICON_PATH) : get_asset_url('favicon.ico');
    echo '<link rel="icon" href="' . $favicon_url . '" type="image/x-icon">';
    ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HuvyJKc2gjjApGFM6uUdfqYvJgfaA0gBljWN9SS7PMjcuH0+rNNQzTT5rU0zFjJv6m8/Lz+P+vA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="<?php echo get_asset_url('css/style.css?v=' . filemtime(PROJECT_ROOT_PATH . '/public/css/style.css')); ?>">
</head>
<body>

<header class="site-header">
    <div class="header-main">
        <div class="container">
            <div class="site-logo">
                <a href="<?php echo get_asset_url('index.php'); ?>">
                    <?php
                    if (defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
                        $logo_src = get_asset_url(SITE_LOGO_PATH); // SITE_LOGO_PATH is now correctly defined in config.php
                        echo '<img src="' . esc_html($logo_src) . '" alt="' . esc_html(SITE_NAME) . ' Logo" class="site-logo-image" onerror="this.onerror=null;this.src=\'' . get_asset_url('images/logo_placeholder.png') . '\';">'; // Add onerror fallback
                    } else {
                        echo '<h1>' . esc_html(SITE_NAME) . '</h1>';
                    }
                    ?>
                </a>
            </div>

            <div id="mobile-menu-toggle" aria-label="Toggle menu" aria-expanded="false" role="button" tabindex="0">☰</div>

            <nav class="main-navigation">
                <ul>
                    <li><a href="<?php echo get_asset_url('index.php'); ?>">Home</a></li>
                    <li><a href="<?php echo get_asset_url('products.php'); ?>">All Products</a></li>
                    <li><a href="<?php echo get_asset_url('categories.php'); ?>">Categories</a></li>
                    <li><a href="<?php echo get_asset_url('brands.php'); ?>">Brands</a></li>
                    <li><a href="<?php echo get_asset_url('about.php'); ?>">About Us</a></li>
                    <li><a href="<?php echo get_asset_url('contact.php'); ?>">Contact</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <ul>
                    <?php if (isset($_SESSION['user_id'])):
                        $display_name = !empty(trim($_SESSION['first_name'])) ? esc_html($_SESSION['first_name']) : esc_html($_SESSION['username']);
                    ?>
                        <li><a href="<?php echo get_asset_url('my_account.php'); ?>">My Account (<?php echo $display_name; ?>)</a></li>
                        <li><a href="<?php echo get_asset_url('logout.php'); ?>">Logout</a></li>
                        <li>
                            <a href="<?php echo get_asset_url('cart.php'); ?>">
                                Cart
                                <span class="cart-count"><?php echo isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li><a href="<?php echo get_asset_url('login.php'); ?>">Login</a></li>
                        <li><a href="<?php echo get_asset_url('register.php'); ?>">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="header-search-area">
        <div class="container">
            <form action="<?php echo get_asset_url('products.php'); ?>" method="GET" class="search-form">
                <input type="search" name="search" placeholder="Search products..." value="<?php echo esc_html($_GET['search'] ?? ''); ?>" aria-label="Search products">
                <button type="submit" aria-label="Perform search">🔍</button>
            </form>
        </div>
    </div>
</header>

<main class="site-main">
    <div class="container">