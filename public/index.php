<?php
// public/index.php

$page_title = "Welcome to BaladyMall - Your Home for Local Egyptian Brands";

$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found from public/index.php. Expected at: " . $config_path_from_public);
}

if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

$db = getPDOConnection();

// Check for global messages passed via GET (e.g., after login)
// These divs will be picked up by the showCustomMessage function in script.js
if (isset($_GET['login']) && $_GET['login'] === 'success') {
    echo "<div id='phpGlobalSuccessMessage' style='display:none;'>You have successfully logged in. Welcome back!</div>";
}
// You can add other global messages here if needed, e.g., for errors from other processes.
// if (isset($_GET['error_code'])) {
//     echo "<div id='phpGlobalErrorMessage' style='display:none;'>An error occurred: " . htmlspecialchars($_GET['error_code']) . "</div>";
// }

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
    <h2>Featured Categories</h2>
    <div class="category-grid">
        <div class="category-item">
            <a href="<?php echo rtrim(SITE_URL, '/'); ?>/category.php?id=1"> 
                <img src="https://placehold.co/300x200/E0E0E0/777?text=Clothing" alt="Clothing Category" 
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/E0E0E0/777?text=Image+Error';" 
                     class="rounded-md shadow-sm">
                <h3>Clothing & Apparel</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo rtrim(SITE_URL, '/'); ?>/category.php?id=2"> 
                <img src="https://placehold.co/300x200/D0D0D0/666?text=Home+Decor" alt="Home Decor Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/D0D0D0/666?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Home Decor</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo rtrim(SITE_URL, '/'); ?>/category.php?id=3"> 
                <img src="https://placehold.co/300x200/C0C0C0/555?text=Handicrafts" alt="Handicrafts Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/C0C0C0/555?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Art & Handicrafts</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo rtrim(SITE_URL, '/'); ?>/category.php?id=4"> 
                <img src="https://placehold.co/300x200/B0B0B0/444?text=Food+Products" alt="Food Products Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/B0B0B0/444?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Local Food Products</h3>
            </a>
        </div>
    </div>
</section>

<section class="featured-products animate-on-scroll">
    <h2>New Arrivals</h2>
    <div class="product-grid">
        <?php
        $products_html = "<p>No new products to display at the moment. Check back soon!</p>"; 
        if ($db) {
            try {
                $stmt = $db->query("
                    SELECT p.product_id, p.product_name, p.price, p.main_image_url, b.brand_name
                    FROM products p
                    JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.is_active = 1 AND b.is_approved = 1 
                    ORDER BY p.created_at DESC 
                    LIMIT 4
                ");
                $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($featured_products) {
                    $products_html = ""; 
                    foreach ($featured_products as $product) {
                        $product_url = rtrim(SITE_URL, '/') . "/product.php?id=" . htmlspecialchars($product['product_id']);
                        $image_path = !empty($product['main_image_url']) ? htmlspecialchars($product['main_image_url']) : '';
                        // Check if main_image_url is a full URL or a relative path
                        if (!empty($image_path) && (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0)) {
                            $image_url = $image_path; // It's a full URL
                        } elseif (!empty($image_path)) {
                             // It's a relative path, prepend SITE_URL (which points to public)
                            $image_url = rtrim(SITE_URL, '/') . '/' . ltrim($image_path, '/');
                        } else {
                            $image_url = "https://placehold.co/250x250/F0F0F0/AAA?text=No+Image";
                        }
                        $fallback_image_url = "https://placehold.co/250x250/F0F0F0/AAA?text=Image+Error";
                        
                        $products_html .= "<div class='product-item'>";
                        $products_html .= "  <a href='" . $product_url . "'>";
                        $products_html .= "    <img src='" . $image_url . "' alt='" . htmlspecialchars($product['product_name']) . "' class='rounded-md shadow-sm' onerror='this.onerror=null;this.src=\"" . $fallback_image_url . "\";'>";
                        $products_html .= "    <h3>" . htmlspecialchars($product['product_name']) . "</h3>";
                        $products_html .= "  </a>";
                        $products_html .= "  <p class='product-brand'>" . htmlspecialchars($product['brand_name']) . "</p>";
                        $products_html .= "  <p class='product-price'>" . CURRENCY_SYMBOL . htmlspecialchars(number_format($product['price'], 2)) . "</p>";
                        $products_html .= "  <a href='" . rtrim(SITE_URL, '/') . "/cart.php?action=add&id=" . htmlspecialchars($product['product_id']) . "' class='btn btn-sm btn-add-to-cart'>Add to Cart</a>";
                        $products_html .= "</div>";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching featured products: " . $e->getMessage());
            }
        }
        echo $products_html;
        ?>
    </div>
</section>

<section class="how-it-works animate-on-scroll">
    <h2>Why Shop BaladyMall?</h2>
    <div class="features-grid">
        <div class="feature-item">
            <img src="https://placehold.co/100x100/A0D2DB/333?text=Local" alt="Support Local Icon" class="rounded-full">
            <h3>Support Local Economy</h3>
            <p>Every purchase directly supports Egyptian artisans, creators, and small businesses.</p>
        </div>
        <div class="feature-item">
            <img src="https://placehold.co/100x100/FAD02E/333?text=Unique" alt="Unique Products Icon" class="rounded-full">
            <h3>Unique & Authentic Products</h3>
            <p>Discover items you won't find anywhere else, rich in Egyptian heritage and craftsmanship.</p>
        </div>
        <div class="feature-item">
            <img src="https://placehold.co/100x100/D4A5A5/333?text=Quality" alt="Quality Assured Icon" class="rounded-full">
            <h3>Quality Assured</h3>
            <p>We partner with brands committed to quality and customer satisfaction.</p>
        </div>
    </div>
</section>

<?php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
