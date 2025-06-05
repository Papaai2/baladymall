<?php
// public/product_detail.php

$page_error_message = '';
$product = null;
$additional_images = [];

// Configuration, Header, and Footer
$config_path_from_public = __DIR__ . '/../src/config/config.php'; // Path to config from current file

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
$header_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/header.php' : __DIR__ . '/../src/includes/header.php';
$footer_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/footer.php' : __DIR__ . '/../src/includes/footer.php';

// Get Product ID from URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || (int)$_GET['id'] <= 0) {
    $page_error_message = "Invalid product ID specified.";
    $product_id = 0; // Invalid ID
} else {
    $product_id = (int)$_GET['id'];
}

// Initialize $db and check connection
if (empty($page_error_message)) { // Proceed only if product ID seems valid initially
    if (!isset($db) || !$db instanceof PDO) {
        if (function_exists('getPDOConnection')) {
            $db = getPDOConnection();
        }
        if (!isset($db) || !$db instanceof PDO) {
            $page_error_message = "Database connection is not available. Please try again later.";
        }
    }
}

if (empty($page_error_message) && $product_id > 0 && isset($db) && $db instanceof PDO) {
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
                SELECT image_id, image_url, alt_text
                FROM product_images
                WHERE product_id = :product_id
                ORDER BY sort_order ASC, image_id ASC
            ");
            $stmt_images->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_images->execute();
            $additional_images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

            // Set page title to product name
            $page_title = esc_html($product['product_name']) . " - BaladyMall";
        }

    } catch (PDOException $e) {
        error_log("Error fetching product details (ID: $product_id): " . $e->getMessage());
        $page_error_message = "Sorry, we couldn't load the product details at this time. Please try again later.";
    }
} elseif ($product_id === 0 && empty($page_error_message)) { // If ID was invalid from the start
     $page_error_message = "Invalid product ID specified.";
}

// Include Header (after $page_title is potentially set)
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

?>

<section class="product-detail-section">
    <div class="container">
        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message text-center" style="padding: 20px; margin: 20px auto; max-width: 600px;">
                <?php echo esc_html($page_error_message); ?>
                <p class="mt-3"><a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary">Back to Products</a></p>
            </div>
        <?php elseif ($product): ?>
            <div class="product-detail-layout">
                <div class="product-gallery animate-on-scroll">
                    <div class="main-image-container">
                        <?php
                            // Determine the primary image to display
                            $main_display_image_path = '';
                            if (!empty($product['main_image_url'])) {
                                $main_display_image_path = $product['main_image_url'];
                            } elseif (isset($additional_images[0]['image_url'])) {
                                $main_display_image_path = $additional_images[0]['image_url'];
                            }

                            $main_image_src = '';
                            $fallback_image_src_esc = '';

                            // Determine fallback image URL and ensure it's properly escaped for the onerror attribute
                            // Prefer a local 'no-image.png' as it's more reliable than external placeholders
                            $fallback_image_src_esc = get_asset_url('images/no-image.png'); // Ensure this file exists in public/images/

                            // If PLACEHOLDER_IMAGE_URL_GENERATOR is explicitly set and preferred as a fallback
                            if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                                $fallback_image_src_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/600x600/E0E0E0/777?text=Error");
                            }


                            // Fix: If DB stores 'products/filename.jpg', ensure get_asset_url gets 'uploads/products/filename.jpg'
                            if (!empty($main_display_image_path)) {
                                if (filter_var($main_display_image_path, FILTER_VALIDATE_URL)) {
                                    $main_image_src = esc_html($main_display_image_path);
                                } else {
                                    // Assuming $main_display_image_path starts with 'products/'. Prepends 'uploads/'
                                    $main_image_src = get_asset_url('uploads/' . ltrim(esc_html($main_display_image_path), '/'));
                                }
                            }

                            // If main $main_image_src is still empty, set it to the fallback
                            if (empty($main_image_src)) {
                                $main_image_src = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/600x600/F0F0F0/AAA?text=Product+Image" : $fallback_image_src_esc;
                            }
                        ?>
                        <img src="<?php echo $main_image_src; ?>"
                             alt="<?php echo esc_html($product['product_name']); ?>"
                             id="mainProductImage"
                             onerror="this.onerror=null;this.src='<?php echo $fallback_image_src_esc; ?>';">
                    </div>
                    <?php
                        // Prepare all images for the thumbnail gallery, including the main one if set
                        $gallery_source_images = [];
                        if (!empty($product['main_image_url'])) {
                            // Ensure the path is relative to 'uploads/products/'
                            $gallery_source_images[$product['main_image_url']] = ['image_url' => $product['main_image_url'], 'alt_text' => $product['product_name'] . ' - Main View'];
                        }
                        foreach ($additional_images as $img_item) {
                            if (!empty($img_item['image_url'])) { // Ensure URL is not empty
                                // Ensure the path is relative to 'uploads/products/'
                                $gallery_source_images[$img_item['image_url']] = $img_item; // Use URL as key to auto-deduplicate
                            }
                        }
                    ?>
                    <?php if (count($gallery_source_images) > 1 ): // Show thumbnails only if there's more than one unique image ?>
                        <div class="thumbnail-gallery">
                            <?php foreach ($gallery_source_images as $image_data): ?>
                                <?php
                                    $thumb_path = !empty($image_data['image_url']) ? esc_html($image_data['image_url']) : '';
                                    $thumb_src = '';
                                    if (!empty($thumb_path)) {
                                        // Fix: Use get_asset_url for thumbnail sources, adjusting path from DB
                                        if (filter_var($thumb_path, FILTER_VALIDATE_URL)) {
                                            $thumb_src = $thumb_path;
                                        } else {
                                            $thumb_src = get_asset_url('uploads/products/' . ltrim($thumb_path, '/'));
                                        }
                                    } else {
                                        continue; // Skip if no valid image path for thumbnail
                                    }
                                ?>
                                <img src="<?php echo $thumb_src; ?>"
                                     alt="<?php echo esc_html($image_data['alt_text']); ?>"
                                     class="thumbnail-image <?php echo ($thumb_src === $main_image_src) ? 'active' : ''; ?>"
                                     data-large-src="<?php echo $thumb_src; ?>"
                                     onerror="this.onerror=null;this.src='<?php echo $fallback_image_src_esc; ?>';">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-info animate-on-scroll" style="animation-delay: 0.2s;">
                    <h1 class="product-title"><?php echo esc_html($product['product_name']); ?></h1>
                    <p class="product-brand-info">
                        By: <a href="<?php echo get_asset_url('products.php?brand_id=' . esc_html($product['brand_id'])); ?>"><?php echo esc_html($product['brand_name']); ?></a>
                    </p>

                    <div class="price-section">
                        <span class="current-price"><?php echo CURRENCY_SYMBOL . esc_html(number_format($product['price'], 2)); ?></span>
                        <?php if (isset($product['compare_at_price']) && $product['compare_at_price'] > 0 && $product['compare_at_price'] > $product['price']): ?>
                            <span class="original-price"><?php echo CURRENCY_SYMBOL . esc_html(number_format($product['compare_at_price'], 2)); ?></span>
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
                        <?php
                        $effective_stock = $product['requires_variants'] == 0 ? (int)($product['stock_quantity'] ?? 0) : 9999; // Assume variants have stock for now
                        $is_in_stock = $product['requires_variants'] == 1 || ($product['requires_variants'] == 0 && $effective_stock > 0);
                        ?>
                        <?php if ($is_in_stock && $product['requires_variants'] == 0): ?>
                            <form action="<?php echo get_asset_url('cart.php'); ?>" method="POST" class="add-to-cart-form ajax-add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo esc_html($product['product_id']); ?>">
                                <div class="quantity-selector form-group" style="max-width: 150px; margin-bottom:15px;">
                                    <label for="quantity" class="form-label">Quantity:</label>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1"
                                           max="<?php echo esc_html($effective_stock); ?>"
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
                        <?php else: // Not variant and out of stock ?>
                            <p class="out-of-stock-message error-message"> Currently Out of Stock</p>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <h3>Product Description</h3>
                        <?php echo !empty($product['product_description']) ? nl2br(esc_html($product['product_description'])) : '<p>No description available for this product.</p>'; ?>
                    </div>

                    <div class="additional-details mt-4">
                        <?php if(isset($product['sku']) && !empty($product['sku'])): ?>
                            <p><small>SKU: <?php echo esc_html($product['sku']); ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            // TODO: Related Products Section (fetch and display similar products)
            // <section class="related-products-section mt-5"><h2>You Might Also Like</h2><div class="product-grid">...</div></section>
            ?>

        <?php endif; ?>
    </div>
</section>

<?php
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
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
                if (mainImage.src !== this.dataset.largeSrc) { // Only change if different
                    mainImage.src = this.dataset.largeSrc;
                    mainImage.alt = this.alt; // Update alt text too
                }
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
});
</script>