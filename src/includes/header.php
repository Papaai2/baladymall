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
                    <?php if (isset($_SESSION['user_id'])): // Check if user is logged in ?>
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
                                // Only try to sum cart if session cart exists and is an array
                                if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
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
