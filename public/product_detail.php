<?php
// public/product_detail.php

$page_error_message = '';
$product = null;
$brand = null;
$additional_images = [];

// Configuration, Header, and Footer
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// Get Product ID from URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $page_error_message = "Invalid product ID specified.";
    // Optional: Redirect to products page or 404
    // header("Location: " . rtrim(SITE_URL, '/') . "/products.php");
    // exit;
} else {
    $product_id = (int)$_GET['id'];
    $db = getPDOConnection();

    try {
        // Fetch main product details
        $stmt_product = $db->prepare("
            SELECT 
                p.*, 
                b.brand_name, 
                b.brand_id 
            FROM products p
            JOIN brands b ON p.brand_id = b.brand_id
            WHERE p.product_id = :product_id AND p.is_active = 1 AND b.is_approved = 1
            LIMIT 1
        ");
        $stmt_product->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_product->execute();
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $page_error_message = "Product not found or is no longer available.";
        } else {
            // Fetch additional product images
            $stmt_images = $db->prepare("
                SELECT image_url, alt_text 
                FROM product_images 
                WHERE product_id = :product_id 
                ORDER BY sort_order ASC, image_id ASC
            ");
            $stmt_images->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_images->execute();
            $additional_images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

            // Set page title to product name
            $page_title = htmlspecialchars($product['product_name']) . " - BaladyMall";
        }

    } catch (PDOException $e) {
        error_log("Error fetching product details (ID: $product_id): " . $e->getMessage());
        $page_error_message = "Sorry, we couldn't load the product details at this time. Please try again later.";
    }
}

// Include Header (after $page_title is set)
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public; // Starts session
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

?>

<section class="product-detail-section">
    <div class="container">
        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message text-center" style="padding: 20px;">
                <?php echo htmlspecialchars($page_error_message); ?>
                <p class="mt-3"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary">Back to Products</a></p>
            </div>
        <?php elseif ($product): ?>
            <div class="product-detail-layout">
                <div class="product-gallery animate-on-scroll">
                    <div class="main-image-container">
                        <?php
                            $main_display_image = !empty($product['main_image_url']) ? $product['main_image_url'] : (isset($additional_images[0]['image_url']) ? $additional_images[0]['image_url'] : '');
                            if (!empty($main_display_image) && (strpos($main_display_image, 'http://') === 0 || strpos($main_display_image, 'https://') === 0)) {
                                $main_image_src = htmlspecialchars($main_display_image);
                            } elseif (!empty($main_display_image)) {
                                $main_image_src = rtrim(SITE_URL, '/') . '/' . ltrim(htmlspecialchars($main_display_image), '/');
                            } else {
                                $main_image_src = "https://placehold.co/600x600/F0F0F0/AAA?text=Product+Image";
                            }
                            $fallback_image_src = "https://placehold.co/600x600/F0F0F0/AAA?text=Image+Error";
                        ?>
                        <img src="<?php echo $main_image_src; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                             id="mainProductImage"
                             onerror="this.onerror=null;this.src='<?php echo $fallback_image_src; ?>';">
                    </div>
                    <?php if (!empty($additional_images) || (!empty($product['main_image_url']) && count($additional_images) > 0) ): ?>
                        <div class="thumbnail-gallery">
                            <?php 
                            // Add main image to thumbnails if it's set and not already in additional images (simple check)
                            $all_gallery_images = [];
                            if (!empty($product['main_image_url'])) {
                                $all_gallery_images[] = ['image_url' => $product['main_image_url'], 'alt_text' => $product['product_name'] . ' - Main View'];
                            }
                            foreach ($additional_images as $img) {
                                // Avoid duplicating main image if it's also in additional_images
                                if (empty($product['main_image_url']) || $img['image_url'] !== $product['main_image_url']) {
                                    $all_gallery_images[] = $img;
                                }
                            }
                            // Ensure unique images if main_image_url was also in additional_images
                            $displayed_urls = [];
                            ?>

                            <?php foreach ($all_gallery_images as $index => $image_data): ?>
                                <?php
                                    if (in_array($image_data['image_url'], $displayed_urls)) continue; // Skip if already displayed
                                    $displayed_urls[] = $image_data['image_url'];

                                    $thumb_path = !empty($image_data['image_url']) ? htmlspecialchars($image_data['image_url']) : '';
                                    if (!empty($thumb_path) && (strpos($thumb_path, 'http://') === 0 || strpos($thumb_path, 'https://') === 0)) {
                                        $thumb_src = $thumb_path;
                                    } elseif(!empty($thumb_path)) {
                                        $thumb_src = rtrim(SITE_URL, '/') . '/' . ltrim($thumb_path, '/');
                                    } else {
                                        continue; // Skip if no valid image path
                                    }
                                    $thumb_alt = !empty($image_data['alt_text']) ? htmlspecialchars($image_data['alt_text']) : htmlspecialchars($product['product_name']) . ' - View ' . ($index + 1);
                                ?>
                                <img src="<?php echo $thumb_src; ?>" 
                                     alt="<?php echo $thumb_alt; ?>" 
                                     class="thumbnail-image <?php echo ($thumb_src === $main_image_src) ? 'active' : ''; ?>"
                                     data-large-src="<?php echo $thumb_src; ?>"
                                     onerror="this.style.display='none';">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-info animate-on-scroll" style="animation-delay: 0.2s;">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    <p class="product-brand-info">
                        By: <a href="<?php echo rtrim(SITE_URL, '/'); ?>/brand_detail.php?id=<?php echo htmlspecialchars($product['brand_id']); ?>"><?php echo htmlspecialchars($product['brand_name']); ?></a>
                    </p>
                    
                    <div class="price-section">
                        <span class="current-price"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($product['price'], 2)); ?></span>
                        <?php if (isset($product['compare_at_price']) && $product['compare_at_price'] > $product['price']): ?>
                            <span class="original-price"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($product['compare_at_price'], 2)); ?></span>
                            <?php 
                                $discount_percentage = (($product['compare_at_price'] - $product['price']) / $product['compare_at_price']) * 100;
                            ?>
                            <span class="discount-badge"><?php echo round($discount_percentage); ?>% OFF</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($product['requires_variants'] == 1): ?>
                        <div class="variant-info notice-message info-message">
                            <p>This product has multiple options (e.g., size, color). Variant selection will be available soon.</p>
                            {/* TODO: Implement variant selection (dropdowns, swatches) */}
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-actions-detail">
                        <?php if ($product['requires_variants'] == 0 && (!isset($product['stock_quantity']) || $product['stock_quantity'] > 0) ): ?>
                            {/* Simple product add to cart */}
                            <form action="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php" method="POST" class="add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                <div class="quantity-selector form-group" style="max-width: 150px; margin-bottom:15px;">
                                    <label for="quantity">Quantity:</label>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                           max="<?php echo isset($product['stock_quantity']) ? htmlspecialchars($product['stock_quantity']) : '99'; ?>" 
                                           class="form-control form-control-sm">
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg btn-add-to-cart-detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-cart-plus-fill" viewBox="0 0 16 16" style="margin-right: 8px; vertical-align: text-bottom;">
                                      <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </form>
                        <?php elseif ($product['requires_variants'] == 1): ?>
                             <button type="button" class="btn btn-primary btn-lg btn-add-to-cart-detail disabled" disabled title="Select options to add to cart">
                                Add to Cart (Select Options)
                            </button>
                        <?php else: ?>
                            <p class="out-of-stock-message error-message">Currently Out of Stock</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-description">
                        <h3>Product Description</h3>
                        <?php echo !empty($product['product_description']) ? nl2br(htmlspecialchars($product['product_description'])) : '<p>No description available for this product.</p>'; ?>
                    </div>

                    {/* Placeholder for other details like SKU, categories, tags, shipping info */}
                    <div class="additional-details mt-4">
                        <?php if(isset($product['sku']) && !empty($product['sku'])): ?>
                            <p><small>SKU: <?php echo htmlspecialchars($product['sku']); ?></small></p>
                        <?php endif; ?>
                        {/* TODO: Display categories, tags if available */}
                    </div>
                </div>
            </div>

            {/* TODO: Related Products Section */}
            {/* <section class="related-products-section mt-5"> ... </section> */}

        <?php endif; ?>
    </div>
</section>

<?php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>

<script>
// Basic JS for thumbnail gallery image switching
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.thumbnail-gallery .thumbnail-image');

    if (mainImage && thumbnails.length > 0) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.dataset.largeSrc; // Use data-large-src for the full image
                mainImage.alt = this.alt;

                // Update active state for thumbnails
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
            // Add hover effect to change main image (optional)
            /*
            thumb.addEventListener('mouseover', function() {
                mainImage.src = this.dataset.largeSrc;
                mainImage.alt = this.alt;
            });
            */
        });
    }
});
</script>
