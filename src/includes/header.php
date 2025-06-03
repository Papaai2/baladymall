<?php
// src/includes/header.php

// It's crucial to include config.php first to have access to constants and functions
// We need to adjust the path relative to header.php's location in src/includes/
if (file_exists(dirname(__DIR__) . '/config/config.php')) {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    // Fallback for cases where the path might be different or if called from a different context
    // This is less ideal and assumes a known root structure if the direct relative path fails.
    // For robust applications, a single entry point (like index.php in public) setting up paths is better.
    if (defined('SRC_PATH') && file_exists(SRC_PATH . '/config/config.php')) {
         require_once SRC_PATH . '/config/config.php';
    } else {
        die("Configuration file not found. Please check the path in header.php.");
    }
}


// Start the session if it hasn't been started already.
// It's good practice to check if headers have already been sent.
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    // Set session parameters for security before starting
    // session_set_cookie_params([
    //     'lifetime' => 0, // 0 = until browser closes
    //     'path' => '/',
    //     'domain' => '', // Set your domain in production
    //     'secure' => isset($_SERVER['HTTPS']), // True if on HTTPS
    //     'httponly' => true, // Prevent JavaScript access to session cookie
    //     'samesite' => 'Lax' // Lax or Strict
    // ]);
    session_name(SESSION_NAME); // Use the session name from config
    session_start();
}

// Attempt to connect to the database (optional here, but can be useful for user status in header)
// $db = getPDOConnection(); // You might use this to check login status, display username, etc.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?> - <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Welcome'; ?></title>
    
    <link rel="stylesheet" href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/css/style.css?v=<?php echo time(); // Cache busting for development ?>">
    
    </head>
<body>

    <header class="site-header">
        <div class="container">
            <div class="logo-container">
                <a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>" class="site-logo">
                    <h1><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?></h1>
                </a>
            </div>

            <nav class="main-navigation">
                <ul>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/index.php">Home</a></li>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/products.php">All Products</a></li>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/categories.php">Categories</a></li>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/brands.php">Brands</a></li>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/about.php">About Us</a></li>
                    <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/contact.php">Contact</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <ul>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/my_account.php">My Account</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/login.php">Login</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/register.php">Register</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/cart.php">
                            Cart 
                            <span class="cart-count">
                                <?php 
                                // Basic cart count example - you'll implement this properly later
                                if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                    echo count($_SESSION['cart']);
                                } else {
                                    echo 0;
                                }
                                ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="site-main">
        <div class="container"> 