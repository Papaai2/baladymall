<?php
// public/index.php

$page_title = "Welcome to BaladyMall - Your Home for Local Egyptian Brands";

// Define paths relative to the current file (public/index.php)
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

// Ensure config.php is loaded first
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    $alt_config_path = dirname(__DIR__) . '/src/config/config.php';
    if (file_exists($alt_config_path)) {
        require_once $alt_config_path;
    } else {
        die("Critical error: Main configuration file not found. Please check paths.");
    }
}

// Define header and footer paths using PROJECT_ROOT_PATH for robustness if available.
if (defined('PROJECT_ROOT_PATH')) {
    $header_path_from_public = PROJECT_ROOT_PATH . '/src/includes/header.php';
    $footer_path_from_public = PROJECT_ROOT_PATH . '/src/includes/footer.php';
}


if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path_from_public));
}

// $db should be initialized in config.php
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        error_log("INDEX.PHP: Database connection not available after including config and header.");
    }
}

// Check for global messages passed via GET (e.g., after login)
if (isset($_GET['login']) && $_GET['login'] === 'success' && isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['login_success_message_shown'])) {
        echo "<div id='phpGlobalSuccessMessage' style='display:none;'>You have successfully logged in. Welcome back, " . esc_html($_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User') . "!</div>";
        $_SESSION['login_success_message_shown'] = true;
    }
}
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    if (!isset($_SESSION['logout_success_message_shown'])) {
        echo "<div id='phpGlobalSuccessMessage' style='display:none;'>You have been successfully logged out.</div>";
        $_SESSION['logout_success_message_shown'] = true;
    }
}
if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
     if (!isset($_SESSION['registration_success_message_shown'])) {
        echo "<div id='phpGlobalSuccessMessage' style='display:none;'>Registration successful! Please check your email to verify your account. You can now log in.</div>";
        $_SESSION['registration_success_message_shown'] = true;
    }
}
?>

<section class="hero-section">
    <div class="hero-content">
        <h1 data-typing-text="Discover Authentic Egyptian Brands">Discover Authentic Egyptian Brands</h1>
        <p>Shop unique products from local artisans and businesses across Egypt. Support local, feel the pride.</p>
        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary btn-lg">Shop All Products</a>
        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/brands.php" class="btn btn-secondary btn-lg">Explore Brands</a>
    </div>
</section>

<section class="featured-categories animate-on-scroll">
    <div class="container"> <?php // Added .container wrapper ?>
        <h2>Featured Categories</h2>
        <?php
        $categories_html = "";
        $featured_categories_count = 0;
        if (isset($db) && $db instanceof PDO) {
            try {
                $stmt_cat = $db->query("
                    SELECT category_id, category_name, category_description, category_image_url
                    FROM categories
                    WHERE category_image_url IS NOT NULL AND category_image_url != '' AND parent_category_id IS NULL
                    ORDER BY created_at DESC
                    LIMIT 4
                ");
                $featured_categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
                $featured_categories_count = count($featured_categories);

                if ($featured_categories) {
                    foreach ($featured_categories as $category) {
                        $category_url = rtrim(SITE_URL, '/') . "/products.php?category_id=" . esc_html($category['category_id']);
                        $cat_image_path = !empty($category['category_image_url']) ? esc_html($category['category_image_url']) : '';
                        $cat_image_display_url = '';
                        if (!empty($cat_image_path) && (strpos($cat_image_path, 'http://') === 0 || strpos($cat_image_path, 'https://') === 0)) {
                            $cat_image_display_url = $cat_image_path;
                        } elseif (!empty($cat_image_path) && defined('PUBLIC_UPLOADS_URL_BASE')) {
                            $cat_image_display_url = rtrim(PUBLIC_UPLOADS_URL_BASE, '/') . '/' . ltrim($cat_image_path, '/');
                        } else {
                            $cat_image_display_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/E0E0E0/777?text=" . urlencode(esc_html($category['category_name'])) : '#';
                        }
                        $cat_fallback_image_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/CCC/555?text=Error" : '#';

                        $categories_html .= "<div class='category-item animate-on-scroll'>";
                        $categories_html .= "  <a href='" . $category_url . "'>";
                        $categories_html .= "    <img src='" . $cat_image_display_url . "' alt='" . esc_html($category['category_name']) . " Category' class='rounded-md shadow-sm' onerror='this.onerror=null;this.src=\"" . $cat_fallback_image_url . "\";'>";
                        $categories_html .= "    <h3>" . esc_html($category['category_name']) . "</h3>";
                        $categories_html .= "  </a>";
                        $categories_html .= "  <div class='item-content-wrapper'>";
                        $categories_html .= "    <p class='category-description'>" . (!empty($category['category_description']) ? esc_html(substr($category['category_description'], 0, 70)) . (strlen($category['category_description']) > 70 ? '...' : '') : 'Explore products in this category.') . "</p>";
                        $categories_html .= "  </div>";
                        $categories_html .= "  <a href='" . $category_url . "' class='btn btn-sm btn-outline-primary mt-auto'>View Products</a>";
                        $categories_html .= "</div>";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching featured categories: " . $e->getMessage());
                $categories_html = "<p class='col-span-full text-center text-red-500' style='grid-column: 1 / -1;'>Could not load categories at this time.</p>";
            }
        } else {
            $categories_html = "<p class='col-span-full text-center text-red-500' style='grid-column: 1 / -1;'>Database connection not available to load categories.</p>";
        }
        // Add class to grid if only one item - this might not be needed with auto-fill/justify-content
        $category_grid_class = ($featured_categories_count === 1) ? 'category-grid single-item-grid' : 'category-grid';
        echo "<div class=\"" . $category_grid_class . "\">";
        if (empty($categories_html) && $featured_categories_count === 0 && !(isset($e))) {
             echo "<p class='col-span-full text-center text-gray-500' style='grid-column: 1 / -1;'>No featured categories to display at the moment.</p>";
        } else {
            echo $categories_html;
        }
        echo "</div>";
        ?>
    </div> <?php // End .container wrapper ?>
</section>

<section class="featured-products animate-on-scroll">
    <div class="container"> <?php // Added .container wrapper ?>
        <h2>New Arrivals</h2>
        <?php
        $products_html = "";
        $featured_products_count = 0;
        if (isset($db) && $db instanceof PDO) {
            try {
                $stmt = $db->query("
                    SELECT p.product_id, p.product_name, p.price, p.main_image_url, p.compare_at_price, b.brand_name
                    FROM products p
                    JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.is_active = 1 AND b.is_approved = 1
                    ORDER BY p.created_at DESC
                    LIMIT 8
                ");
                $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $featured_products_count = count($featured_products);

                if ($featured_products) {
                    foreach ($featured_products as $product) {
                        $product_url = rtrim(SITE_URL, '/') . "/product_detail.php?id=" . esc_html($product['product_id']);
                        $image_path = !empty($product['main_image_url']) ? esc_html($product['main_image_url']) : '';
                        $image_url = '';
                        if (!empty($image_path) && (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0)) {
                            $image_url = $image_path;
                        } elseif (!empty($image_path) && defined('PUBLIC_UPLOADS_URL_BASE')) {
                            $image_url = rtrim(PUBLIC_UPLOADS_URL_BASE, '/') . '/' . ltrim($image_path, '/');
                        } else {
                            $image_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/250x250/F0F0F0/AAA?text=No+Image" : '#';
                        }
                        $fallback_image_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/250x250/E0E0E0/777?text=Error" : '#';

                        $products_html .= "<div class='product-item animate-on-scroll'>";
                        $products_html .= "  <a href='" . $product_url . "'>";
                        $products_html .= "    <img src='" . $image_url . "' alt='" . esc_html($product['product_name']) . "' class='rounded-md shadow-sm' onerror='this.onerror=null;this.src=\"" . $fallback_image_url . "\";'>";
                        $products_html .= "    <h3>" . esc_html($product['product_name']) . "</h3>";
                        $products_html .= "  </a>";
                        $products_html .= "  <div class='item-content-wrapper'>";
                        $products_html .= "    <p class='product-brand'>" . esc_html($product['brand_name']) . "</p>";
                        $products_html .= "    <div class='price-container'>";
                        if (!empty($product['compare_at_price']) && $product['compare_at_price'] > 0 && $product['compare_at_price'] > $product['price']) {
                            $products_html .= "    <span class='product-price current'>" . CURRENCY_SYMBOL . esc_html(number_format($product['price'], 2)) . "</span>";
                            $products_html .= "    <span class='product-price original'>" . CURRENCY_SYMBOL . esc_html(number_format($product['compare_at_price'], 2)) . "</span>";
                        } else {
                            $products_html .= "    <span class='product-price current'>" . CURRENCY_SYMBOL . esc_html(number_format($product['price'], 2)) . "</span>";
                        }
                        $products_html .= "    </div>";
                        $products_html .= "  </div>";
                        // ADDED CLASS 'ajax-add-to-cart-form'
                        $products_html .= "  <form action='" . rtrim(SITE_URL, '/') . "/cart.php' method='POST' class='add-to-cart-form-list ajax-add-to-cart-form mt-auto'>";
                        $products_html .= "      <input type='hidden' name='action' value='add'>";
                        $products_html .= "      <input type='hidden' name='product_id' value='" . esc_html($product['product_id']) . "'>";
                        $products_html .= "      <input type='hidden' name='quantity' value='1'>";
                        $products_html .= "      <button type='submit' class='btn btn-sm btn-add-to-cart'>Add to Cart</button>";
                        $products_html .= "  </form>";
                        $products_html .= "</div>";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching featured products: " . $e->getMessage());
                $products_html = "<p class='col-span-full text-center text-red-500' style='grid-column: 1 / -1;'>Could not load new products at this time.</p>";
            }
        } else {
             $products_html = "<p class='col-span-full text-center text-red-500' style='grid-column: 1 / -1;'>Database connection not available to load products.</p>";
        }
        $product_grid_class = ($featured_products_count === 1) ? 'product-grid single-item-grid' : 'product-grid';
        echo "<div class=\"" . $product_grid_class . "\">";
        if (empty($products_html) && $featured_products_count === 0 && !(isset($e))) {
             echo "<p class='col-span-full text-center text-gray-500' style='grid-column: 1 / -1;'>No new products to display at the moment. Check back soon!</p>";
        } else {
            echo $products_html;
        }
        echo "</div>";
        ?>
    </div> <?php // End .container wrapper ?>
</section>

<?php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path_from_public));
}
?>