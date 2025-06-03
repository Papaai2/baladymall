<?php
// public/products.php

$page_title = "All Products - BaladyMall";

// Configuration, Header, and Footer
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public; // Starts session
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

$db = getPDOConnection();
$all_products = [];
$page_error_message = '';

try {
    // Fetch all active products from approved brands
    // We'll order by creation date for now, newest first.
    $stmt = $db->query("
        SELECT 
            p.product_id, 
            p.product_name, 
            p.price, 
            p.main_image_url, 
            b.brand_name
        FROM products p
        JOIN brands b ON p.brand_id = b.brand_id
        WHERE p.is_active = 1 AND b.is_approved = 1 
        ORDER BY p.created_at DESC
    ");
    // In a real application with many products, you would implement pagination here.
    // For example: LIMIT :offset, :limit
    
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching all products: " . $e->getMessage());
    $page_error_message = "Sorry, we couldn't load the products at this time. Please try again later.";
}

?>

<section class="product-listing-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Our Products</h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message">
                <?php echo htmlspecialchars($page_error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($all_products) && empty($page_error_message)): ?>
            <p class="text-center info-message" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                No products found at the moment. Please check back soon!
            </p>
        <?php elseif (!empty($all_products)): ?>
            <div class="product-grid">
                <?php foreach ($all_products as $product): ?>
                    <?php
                        $product_url = rtrim(SITE_URL, '/') . "/product_detail.php?id=" . htmlspecialchars($product['product_id']); // Link to a future product_detail.php
                        $image_path = !empty($product['main_image_url']) ? htmlspecialchars($product['main_image_url']) : '';
                        
                        if (!empty($image_path) && (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0)) {
                            $image_url = $image_path;
                        } elseif (!empty($image_path)) {
                            $image_url = rtrim(SITE_URL, '/') . '/' . ltrim($image_path, '/');
                        } else {
                            $image_url = "https://placehold.co/300x300/F0F0F0/AAA?text=No+Image";
                        }
                        $fallback_image_url = "https://placehold.co/300x300/F0F0F0/AAA?text=Image+Error";
                    ?>
                    <div class="product-item animate-on-scroll"> {/* Using animate-on-scroll class from your JS */}
                        <a href="<?php echo $product_url; ?>" class="product-item-link">
                            <div class="product-image-container">
                                <img src="<?php echo $image_url; ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     class="rounded-md shadow-sm product-image" 
                                     onerror="this.onerror=null;this.src='<?php echo $fallback_image_url; ?>';">
                            </div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        </a>
                        <p class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                        <p class="product-price">
                            <?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($product['price'], 2)); ?>
                        </p>
                        <div class="product-actions">
                            <a href="<?php echo rtrim(SITE_URL, '/') . "/cart.php?action=add&id=" . htmlspecialchars($product['product_id']); ?>" class="btn btn-sm btn-add-to-cart">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-plus" viewBox="0 0 16 16" style="margin-right: 5px; vertical-align: text-bottom;">
                                  <path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/>
                                  <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                                </svg>
                                Add to Cart
                            </a>
                            {/* <a href="<?php echo $product_url; ?>" class="btn btn-sm btn-outline-secondary">View Details</a> */}
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Placeholder for pagination links if you implement it later
        // if ($total_pages > 1) {
        //     echo "<nav aria-label='Product navigation' class='mt-5 text-center'>";
        //     echo "<ul class='pagination justify-content-center'>";
        //     // ... pagination links ...
        //     echo "</ul></nav>";
        // }
        ?>
    </div>
</section>

<?php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
