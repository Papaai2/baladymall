<?php
// src/includes/header.php

// Ensure config.php (which defines SESSION_NAME and SITE_URL) is loaded.
// All your public/*.php files should require_once config.php before this header.
if (!defined('SESSION_NAME') || !defined('SITE_URL')) {
    $config_path_from_header = dirname(__DIR__) . '/config/config.php';
    if (file_exists($config_path_from_header)) {
        require_once $config_path_from_header;
    } else {
        die("CRITICAL ERROR: config.php not found or key constants (SESSION_NAME, SITE_URL) are not defined.");
    }
}

// Start the session if it hasn't been started already and headers haven't been sent.
if (session_status() == PHP_SESSION_NONE) {
    if (headers_sent($file, $line)) {
        error_log("HEADER.PHP: Session not started because headers already sent in $file on line $line.");
        // Optionally, display an error or die if session is critical and cannot start.
        // die("Error: Cannot start session, headers already sent.");
    } else {
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        // Recommended session cookie parameters for production:
        /*
        session_set_cookie_params([
            'lifetime' => 0, 
            'path' => '/',
            'domain' => '', // Set your domain in production, e.g., '.baladymall.com'
            'secure' => isset($_SERVER['HTTPS']), 
            'httponly' => true,
            'samesite' => 'Lax' 
        ]);
        */
        session_start();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(SITE_NAME); ?> - <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Welcome'; ?></title>
    
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/style.css?v=<?php echo time(); // Cache-busting for development ?>">
    
</head>
<body>

    <header class="site-header">
        <div class="container">
            <div class="site-logo"> 
                <a href="<?php echo rtrim(SITE_URL, '/'); ?>/index.php">
                    <h1><?php echo htmlspecialchars(SITE_NAME); ?></h1>
                </a>
            </div>

            <nav class="main-navigation">
                <ul>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/index.php">Home</a></li>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php">All Products</a></li>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/categories.php">Categories</a></li>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/brands.php">Brands</a></li>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/about.php">About Us</a></li>
                    <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/contact.php">Contact</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <ul>
                    <?php if (isset($_SESSION['user_id'])): // Correct session variable check ?>
                        <?php 
                            // Determine the display name; default to 'User' if first_name isn't set
                            $display_name = isset($_SESSION['first_name']) && !empty(trim($_SESSION['first_name'])) 
                                            ? htmlspecialchars($_SESSION['first_name']) 
                                            : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User');
                        ?>
                        <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php">My Account (<?php echo $display_name; ?>)</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/login.php">Login</a></li>
                        <li><a href="<?php echo rtrim(SITE_URL, '/'); ?>/register.php">Register</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php">
                            Cart 
                            <span class="cart-count">
                                <?php 
                                // Updated cart count logic
                                $cart_display_count = 0;
                                if (isset($_SESSION['header_cart_item_count']) && is_numeric($_SESSION['header_cart_item_count'])) {
                                    // Use the count set by cart.php (total quantity of items)
                                    $cart_display_count = $_SESSION['header_cart_item_count'];
                                } elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                    // Fallback: if cart.php hasn't set the specific count,
                                    // sum the quantities of items in the cart.
                                    // This assumes $_SESSION['cart'] is [product_id => quantity]
                                    $cart_display_count = array_sum($_SESSION['cart']);
                                }
                                echo $cart_display_count;
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
            
