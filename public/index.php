<?php
// public/index.php

// Define a page-specific title
$page_title = "Welcome to BaladyMall - Your Home for Local Egyptian Brands";

// Include the header.php.
// The path needs to be relative to index.php's location in the public/ folder.
// We are going up one level from public/ to the project root, then into src/includes/
// A more robust way, if config.php (which defines ROOT_PATH/SRC_PATH) is included first,
// would be to use those constants. Let's try to include config.php directly first.

$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';


if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found from public/index.php. Expected at: " . $config_path_from_public);
}

// Now that config.php is loaded, SITE_URL and other constants should be available.
// The header.php also tries to include config.php, which is fine; require_once handles it.
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

// At this point, $db = getPDOConnection(); from config.php can be used if needed.
// For example, to fetch featured products or brands for the homepage.
$db = getPDOConnection();

?>

<section class="hero-section">
    <div class="hero-content">
        <h1>Discover Authentic Egyptian Brands</h1>
        <p>Shop unique products from local artisans and businesses across Egypt. Support local, feel the pride.</p>
        <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary">Shop All Products</a>
        <a href="<?php echo SITE_URL; ?>/brands.php" class="btn btn-secondary">Explore Brands</a>
    </div>
    </section>

<section class="featured-categories">
    <h2>Featured Categories</h2>
    <div class="category-grid">
        <div class="category-item">
            <a href="<?php echo SITE_URL; ?>/category.php?id=1">
                <img src="https://placehold.co/300x200/E0E0E0/777?text=Clothing" alt="Clothing Category" 
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/E0E0E0/777?text=Image+Error';" 
                     class="rounded-md shadow-sm">
                <h3>Clothing & Apparel</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo SITE_URL; ?>/category.php?id=2">
                <img src="https://placehold.co/300x200/D0D0D0/666?text=Home+Decor" alt="Home Decor Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/D0D0D0/666?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Home Decor</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo SITE_URL; ?>/category.php?id=3">
                <img src="https://placehold.co/300x200/C0C0C0/555?text=Handicrafts" alt="Handicrafts Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/C0C0C0/555?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Art & Handicrafts</h3>
            </a>
        </div>
        <div class="category-item">
            <a href="<?php echo SITE_URL; ?>/category.php?id=4">
                <img src="https://placehold.co/300x200/B0B0B0/444?text=Food+Products" alt="Food Products Category"
                     onerror="this.onerror=null;this.src='https://placehold.co/300x200/B0B0B0/444?text=Image+Error';"
                     class="rounded-md shadow-sm">
                <h3>Local Food Products</h3>
            </a>
        </div>
    </div>
</section>

<section class="featured-products">
    <h2>New Arrivals</h2>
    <div class="product-grid">
        <?php
        // Example: Fetch a few products from the database
        // In a real scenario, you'd have a more robust way to select featured/new products
        $products_html = ""; // Default message
        if ($db) {
            try {
                // Fetch, for example, 4 active products, ordered by creation date descending
                // Ensure products are active and belong to an approved brand.
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
                    $products_html = ""; // Clear default message
                    foreach ($featured_products as $product) {
                        $product_url = SITE_URL . "/product.php?id=" . htmlspecialchars($product['product_id']);
                        $image_url = !empty($product['main_image_url']) ? SITE_URL . "/" . htmlspecialchars($product['main_image_url']) : "https://placehold.co/250x250/F0F0F0/AAA?text=No+Image";
                        $fallback_image_url = "https://placehold.co/250x250/F0F0F0/AAA?text=Image+Error";
                        
                        $products_html .= "<div class='product-item'>";
                        $products_html .= "  <a href='" . $product_url . "'>";
                        $products_html .= "    <img src='" . $image_url . "' alt='" . htmlspecialchars($product['product_name']) . "' class='rounded-md shadow-sm' onerror='this.onerror=null;this.src=\"" . $fallback_image_url . "\";'>";
                        $products_html .= "    <h3>" . htmlspecialchars($product['product_name']) . "</h3>";
                        $products_html .= "  </a>";
                        $products_html .= "  <p class='product-brand'>" . htmlspecialchars($product['brand_name']) . "</p>";
                        $products_html .= "  <p class='product-price'>" . CURRENCY_SYMBOL . htmlspecialchars(number_format($product['price'], 2)) . "</p>";
                        $products_html .= "  <a href='" . SITE_URL . "/cart.php?action=add&id=" . htmlspecialchars($product['product_id']) . "' class='btn btn-sm btn-add-to-cart'>Add to Cart</a>";
                        $products_html .= "</div>";
                    }
                }
            } catch (PDOException $e) {
                // Log error, don't show to public in production
                error_log("Error fetching featured products: " . $e->getMessage());
                // $products_html variable will keep its default error message
            }
        }
        echo $products_html;
        ?>
    </div>
</section>

<section class="how-it-works">
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
// Include the footer.php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
