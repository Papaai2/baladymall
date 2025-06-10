<?php
// public/index.php
// Ensure config.php is loaded first
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("CRITICAL ERROR: Main config.php not found.");
}

// --- TEMPORARY DEBUGGING START (Commented out for production) ---
/*
echo "<pre>";
if (defined('PROJECT_ROOT_PATH')) {
    echo "PROJECT_ROOT_PATH is defined: " . PROJECT_ROOT_PATH . "\n";
} else {
    echo "PROJECT_ROOT_PATH is NOT defined.\n";
}
if (defined('PUBLIC_ROOT_PATH')) {
    echo "PUBLIC_ROOT_PATH is defined: " . PUBLIC_ROOT_PATH . "\n";
} else {
    echo "PUBLIC_ROOT_PATH is NOT defined.\n";
}
echo "</pre>";
*/
// --- TEMPORARY DEBUGGING END ---

// Include header - Rely on PROJECT_ROOT_PATH now that config.php has defined it
$header_path = PROJECT_ROOT_PATH . '/src/includes/header.php';
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

$page_title = "Welcome to " . esc_html(SITE_NAME) . " - Your Home for Local Egyptian Brands";

// Check database connection
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        error_log("INDEX.PHP: Database connection not available after including config and header.");
    }
}

// REMOVED: The old global message for login success.
// It's better to show a message via JS using showCustomMessage().
// if (isset($_GET['login']) && $_GET['login'] === 'success' && isset($_SESSION['user_id'])) {
//     if (!isset($_SESSION['login_success_message_shown'])) {
//         echo "<div id='phpGlobalSuccessMessage' style='display:none;'>You have successfully logged in. Welcome back, " . esc_html($_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User') . "!</div>";
//         $_SESSION['login_success_message_shown'] = true;
//     }
// }
?>

<section class="hero-section">
  <div class="hero-content">
    <h1>Discover Authentic Egyptian Brands</h1> 
    
    <p>Shop unique products from local artisans and businesses across Egypt. Support local, feel the pride.</p>
    <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary btn-lg">Shop All Products</a>
    <a href="<?php echo get_asset_url('brands.php'); ?>" class="btn btn-secondary btn-lg">Explore Brands</a>
  </div>
</section>

<section class="featured-categories animate-on-scroll">
  <div class="container">
    <h2>Featured Categories</h2>
    <?php
    $categories_html = "";
    $featured_categories_count = 0;

    if (isset($db) && $db instanceof PDO) {
        try {
            $stmt_cat = $db->query("SELECT category_id, category_name, category_description, category_image_url FROM categories WHERE category_image_url IS NOT NULL AND category_image_url != '' AND parent_category_id IS NULL ORDER BY created_at DESC LIMIT 4");
            $featured_categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
            $featured_categories_count = count($featured_categories);

            if ($featured_categories) {
                foreach ($featured_categories as $category) {
                    $category_url = get_asset_url("products.php?category_id=" . esc_html($category['category_id']));
                    $cat_image_path = !empty($category['category_image_url']) ? esc_html($category['category_image_url']) : '';
                    $cat_image_display_url = '';
                    $fallback_image_url_esc = get_asset_url('images/no-image.png');

                    if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                        $fallback_image_url_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/E8E8E8/4A4A4A?text=No+Image");
                    }

                    if (!empty($cat_image_path)) {
                        if (filter_var($cat_image_path, FILTER_VALIDATE_URL)) {
                            $cat_image_display_url = $cat_image_path;
                        } else {
                            $cat_image_display_url = get_asset_url('uploads/' . ltrim($cat_image_path, '/'));
                        }
                    }

                    if (empty($cat_image_display_url)) {
                        $cat_image_display_url = $fallback_image_url_esc;
                    }

                    $categories_html .= "<div class='category-item animate-on-scroll'>";
                    $categories_html .= "  <a href='" . $category_url . "'>";
                    $categories_html .= "    <img src='" . $cat_image_display_url . "' alt='" . esc_html($category['category_name']) . "' loading='lazy'>";
                    $categories_html .= "    <div class='item-content-wrapper-cat'>";
                    $categories_html .= "      <h3>" . esc_html($category['category_name']) . "</h3>";
                    $categories_html .= "    </div>";
                    $categories_html .= "  </a>";
                    $categories_html .= "</div>";
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching featured categories: " . $e->getMessage());
            $categories_html = "<p class='text-center error-message' style='grid-column: 1 / -1;'>Could not load categories.</p>";
        }
    }

    $grid_class = $featured_categories_count === 1 ? 'category-grid single-item-grid' : 'category-grid';

    echo "<div class=\"$grid_class\">";
    if (empty($categories_html)) {
        echo "<p class='text-center info-message' style='grid-column: 1 / -1;'>No featured categories to display.</p>";
    } else {
        echo $categories_html;
    }
    echo "</div>";
    ?>
  </div>
</section>

<section class="featured-products animate-on-scroll">
  <div class="container">
    <h2>New Arrivals</h2>
    <?php
    $products_html = "";
    $featured_products_count = 0;

    if (isset($db) && $db instanceof PDO) {
        try {
            $stmt = $db->query("SELECT p.product_id, p.product_name, p.price, p.main_image_url, p.compare_at_price, b.brand_name FROM products p JOIN brands b ON p.brand_id = b.brand_id WHERE p.is_active = 1 AND b.is_approved = 1 ORDER BY p.created_at DESC LIMIT 8");
            $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $featured_products_count = count($featured_products);

            if ($featured_products) {
                foreach ($featured_products as $product) {
                    $product_url = get_asset_url("product_detail.php?id=" . esc_html($product['product_id']));
                    $image_path = !empty($product['main_image_url']) ? esc_html($product['main_image_url']) : '';
                    $image_url = '';
                    $fallback_image_url_esc = get_asset_url('images/no-image.png');

                    if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                        $fallback_image_url_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x300/E8E8E8/4A4A4A?text=No+Image");
                    }

                    if (!empty($image_path)) {
                        if (filter_var($image_path, FILTER_VALIDATE_URL)) {
                            $image_url = $image_path;
                        } else {
                            $image_url = get_asset_url('uploads/' . ltrim($image_path, '/'));
                        }
                    }

                    if (empty($image_url)) {
                        $image_url = $fallback_image_url_esc;
                    }

                    $products_html .= "<div class='product-item animate-on-scroll'>";
                    $products_html .= "  <a href='" . $product_url . "' class='product-item-link'>";
                    $products_html .= "    <div class='product-image-container'><img class='product-image' src='" . $image_url . "' alt='" . esc_html($product['product_name']) . "' loading='lazy'></div>";
                    $products_html .= "    <div class='item-content-wrapper'>";
                    $products_html .= "      <h3 class='product-name'>" . esc_html($product['product_name']) . "</h3>";
                    $products_html .= "      <span class='product-brand'>By " . esc_html($product['brand_name']) . "</span>";
                    
                    $products_html .= "<div class='price-container'>";
                    if (isset($product['compare_at_price']) && $product['compare_at_price'] > $product['price']) {
                        $products_html .= "  <span class='product-price original'>" . ($GLOBALS['currency_symbol'] ?? '$') . esc_html(number_format($product['compare_at_price'], 2)) . "</span>";
                        $products_html .= "  <span class='product-price current'>" . ($GLOBALS['currency_symbol'] ?? '$') . esc_html(number_format($product['price'], 2)) . "</span>";
                    } else {
                        $products_html .= "  <span class='product-price current'>" . ($GLOBALS['currency_symbol'] ?? '$') . esc_html(number_format($product['price'], 2)) . "</span>";
                    }
                     $products_html .= "</div>";

                    $products_html .= "    </div>";
                    $products_html .= "  </a>";

                    $products_html .= "  <form action='" . get_asset_url('cart.php') . "' method='POST' class='add-to-cart-form-list ajax-add-to-cart-form mt-auto'>";
                    $products_html .= "    <input type='hidden' name='action' value='add'>";
                    $products_html .= "    <input type='hidden' name='product_id' value='" . esc_html($product['product_id']) . "'>";
                    $products_html .= "    <input type='hidden' name='quantity' value='1'>";
                    $products_html .= "    <button type='submit' class='btn btn-sm btn-add-to-cart btn-block'>Add to Cart</button>";
                    $products_html .= "  </form>";
                    $products_html .= "</div>";
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching featured products: " . $e->getMessage());
            $products_html = "<p class='text-center error-message' style='grid-column: 1 / -1;'>Could not load products.</p>";
        }
    }

    $product_grid_class = $featured_products_count === 1 ? 'product-grid single-item-grid' : 'product-grid';

    echo "<div class=\"" . $product_grid_class . "\">";
    if (empty($products_html)) {
        echo "<p class='text-center info-message' style='grid-column: 1 / -1;'>No new products to display.</p>";
    } else {
        echo $products_html;
    }
    echo "</div>";
    ?>
  </div>
</section>

<?php
$footer_path_from_public = PROJECT_ROOT_PATH . '/src/includes/footer.php';
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found.");
}
?>