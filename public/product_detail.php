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

// Variables for reviews
$reviews_list = [];
$average_rating = 0;
$total_reviews_count = 0;
$review_form_message = '';
$review_errors = [];
$user_has_reviewed = false;          // Flag to check if the current user has already submitted a review
$user_has_purchased_product = false; // New flag: Check if user has purchased this product

// --- Handle Review Submission (if POST request and user is logged in) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review']) && isset($_SESSION['user_id'])) {
    if ($product_id > 0 && isset($db) && $db instanceof PDO) {
        $user_id_review = (int)$_SESSION['user_id']; // Explicitly cast to int for safety
        $product_id_review = $product_id; // Use the ID from GET parameter

        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
        $review_text = trim(filter_input(INPUT_POST, 'review_text', FILTER_UNSAFE_RAW)); // Keep HTML for now, consider sanitizing later

        // Input Validation
        if ($rating === false || $rating < 1 || $rating > 5) {
            $review_errors['rating'] = "Please provide a rating between 1 and 5 stars.";
        }
        if (empty($review_text)) {
            $review_errors['review_text'] = "Review text cannot be empty.";
        } elseif (strlen($review_text) > 1000) { // Limit review text length
            $review_errors['review_text'] = "Review text is too long (max 1000 characters).";
        }

        if (empty($review_errors)) {
            try {
                // Check if user has already reviewed this product
                $stmt_check_review = $db->prepare("SELECT review_id FROM product_reviews WHERE product_id = :product_id AND user_id = :user_id LIMIT 1");
                $stmt_check_review->bindParam(':product_id', $product_id_review, PDO::PARAM_INT);
                $stmt_check_review->bindParam(':user_id', $user_id_review, PDO::PARAM_INT);
                $stmt_check_review->execute();
                $existing_review = $stmt_check_review->fetch(PDO::FETCH_ASSOC);

                if ($existing_review) {
                    $review_errors['form'] = "You have already submitted a review for this product.";
                } else {
                    // Check if user has purchased the product
                    $stmt_check_purchase_for_review = $db->prepare("
                        SELECT COUNT(oi.order_item_id)
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE oi.product_id = :product_id
                          AND o.customer_id = :user_id
                          AND o.order_status IN ('shipped', 'delivered') -- Only allow review if product was shipped/delivered
                    ");
                    $stmt_check_purchase_for_review->bindParam(':product_id', $product_id_review, PDO::PARAM_INT);
                    $stmt_check_purchase_for_review->bindParam(':user_id', $user_id_review, PDO::PARAM_INT);
                    $stmt_check_purchase_for_review->execute();
                    $purchase_count = $stmt_check_purchase_for_review->fetchColumn();

                    if ($purchase_count > 0) {
                        // Insert the new review (initially as not approved: is_approved = 0)
                        $stmt_insert_review = $db->prepare("
                            INSERT INTO product_reviews (product_id, user_id, rating, review_text, is_approved)
                            VALUES (:product_id, :user_id, :rating, :review_text, 1)
                        ");
                        $stmt_insert_review->bindParam(':product_id', $product_id_review, PDO::PARAM_INT);
                        $stmt_insert_review->bindParam(':user_id', $user_id_review, PDO::PARAM_INT);
                        $stmt_insert_review->bindParam(':rating', $rating, PDO::PARAM_INT);
                        $stmt_insert_review->bindParam(':review_text', $review_text);
                        $stmt_insert_review->execute();

                        $review_form_message = "<div class='form-message success-message'>Thank you for your review! It will be visible after moderation.</div>";
                        $_POST = []; // Clear form fields
                        $user_has_reviewed = true; // Set flag to hide the form
                    } else {
                        $review_errors['form'] = "You must purchase this product (and it must be shipped/delivered) to leave a review.";
                    }
                }

            } catch (PDOException $e) {
                error_log("Error submitting review for product ID {$product_id_review}, User ID {$user_id_review}: " . $e->getMessage());
                $review_errors['form'] = "An error occurred while submitting your review. Please try again.";
            }
        }
    } else {
        $review_errors['form'] = "Cannot submit review: Product or database connection error.";
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

            // --- Fetch Approved Product Reviews and Calculate Average Rating ---
            try {
                // Fetch approved reviews, joining with users table to get username/name
                $stmt_reviews = $db->prepare("
                    SELECT pr.*, u.username, u.first_name, u.last_name
                    FROM product_reviews pr
                    JOIN users u ON pr.user_id = u.user_id
                    WHERE pr.product_id = :product_id AND pr.is_approved = 1
                    ORDER BY pr.created_at DESC
                ");
                $stmt_reviews->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt_reviews->execute();
                $reviews_list = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
                $total_reviews_count = count($reviews_list);

                if ($total_reviews_count > 0) {
                    $sum_ratings = array_sum(array_column($reviews_list, 'rating'));
                    $average_rating = $sum_ratings / $total_reviews_count;
                }

                // Check if the current logged-in user has already reviewed this product AND purchased it
                if (isset($_SESSION['user_id'])) {
                    $current_logged_in_user_id = (int)$_SESSION['user_id']; // Cast to int immediately for safety

                    $stmt_user_review_check = $db->prepare("SELECT review_id FROM product_reviews WHERE product_id = :product_id AND user_id = :user_id LIMIT 1");
                    $stmt_user_review_check->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                    $stmt_user_review_check->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
                    $stmt_user_review_check->execute();
                    if ($stmt_user_review_check->fetch()) {
                        $user_has_reviewed = true;
                    }

                    // Check if the current logged-in user has purchased this product
                    $stmt_check_purchase = $db->prepare("
                        SELECT COUNT(oi.order_item_id)
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE oi.product_id = :product_id
                          AND o.customer_id = :user_id
                          AND o.order_status IN ('shipped', 'delivered')
                    ");
                    $stmt_check_purchase->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                    $stmt_check_purchase->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
                    $stmt_check_purchase->execute();
                    $purchase_count = $stmt_check_purchase->fetchColumn();

                    if ($purchase_count > 0) {
                        $user_has_purchased_product = true;
                    }
                }

            } catch (PDOException $e) {
                // Log errors related to reviews or purchase status, but don't halt the page
                error_log("Error fetching product reviews or purchase status for product ID {$product_id}: " . $e->getMessage());
                // You might choose to display a soft message to the user here if reviews are critical:
                // $page_level_error_reviews = "Could not load reviews or check purchase status at this time.";
            }
        }

    } catch (PDOException $e) {
        error_log("Error fetching main product details (ID: $product_id): " . $e->getMessage());
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
                                    $main_image_src = get_asset_url('uploads/' . ltrim($main_display_image_path, '/')); // Use ltrim to ensure no double slash if path starts with one
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
                                            $thumb_src = get_asset_url('uploads/' . ltrim($thumb_path, '/'));
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
                    
                    <?php if ($total_reviews_count > 0): ?>
                        <div class="product-detail-rating">
                            <span class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($average_rating)): ?>
                                        <i class="fa-solid fa-star" style="color: gold;"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-star" style="color: lightgray;"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </span>
                            <span class="review-count">(<?php echo esc_html($total_reviews_count); ?> Reviews)</span>
                            <a href="#customer-reviews" class="view-all-reviews-link">View all reviews</a>
                        </div>
                    <?php else: ?>
                        <p class="no-reviews-text">No reviews yet. Be the first!</p>
                    <?php endif; ?>

                    <div class="price-section">
                        <span class="current-price"><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($product['price'], 2)); ?></span>
                        <?php if (isset($product['compare_at_price']) && $product['compare_at_price'] > 0 && $product['compare_at_price'] > $product['price']): ?>
                            <span class="original-price"><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($product['compare_at_price'], 2)); ?></span>
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

            <section class="product-reviews-section" id="customer-reviews">
                <div class="reviews-summary">
                    <h2>Customer Reviews (<?php echo $total_reviews_count; ?>)</h2>
                    <?php if ($total_reviews_count > 0): ?>
                        <div class="average-rating">
                            Average Rating: **<?php echo number_format($average_rating, 1); ?>** out of 5
                            <span class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($average_rating)): ?>
                                        <i class="fa-solid fa-star" style="color: gold;"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-star" style="color: lightgray;"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p>No reviews yet. Be the first to review this product!</p>
                    <?php endif; ?>
                </div>

                <div class="review-list">
                    <h3>All Reviews</h3>
                    <?php if (!empty($reviews_list)): ?>
                        <?php foreach ($reviews_list as $review): ?>
                            <div class="review-item">
                                <p class="review-author">
                                    <strong><?php echo esc_html($review['first_name'] ?: $review['username']); ?></strong>
                                    on <?php echo esc_html(date("F j, Y", strtotime($review['created_at']))); ?>
                                </p>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['rating']): ?>
                                            <i class="fa-solid fa-star" style="color: gold;"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-star" style="color: lightgray;"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <p class="review-text"><?php echo nl2br(esc_html($review['review_text'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="info-message text-center">No reviews to display at the moment.</p>
                    <?php endif; ?>
                </div>

                <div class="review-submission-form">
                    <?php if (isset($_SESSION['user_id'])): // User is logged in ?>
                        <?php if ($user_has_purchased_product): // User has purchased the product ?>
                            <?php if (!$user_has_reviewed): // User has not yet reviewed this specific product ?>
                                <h3>Submit Your Review</h3>
                                <?php if (!empty($review_form_message)): ?>
                                    <?php echo $review_form_message; // Success message from PHP ?>
                                <?php endif; ?>
                                <?php if (!empty($review_errors['form'])): ?>
                                    <div class="form-message error-message">
                                        <?php echo esc_html($review_errors['form']); ?>
                                    </div>
                                <?php endif; ?>

                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . esc_html($product_id); ?>" method="POST" class="auth-form" novalidate>
                                    <div class="form-group">
                                        <label for="rating">Your Rating <span class="required">*</span></label>
                                        <select id="rating" name="rating" required class="form-control">
                                            <option value="">Select a rating</option>
                                            <option value="5" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'selected' : ''; ?>>5 Stars - Excellent</option>
                                            <option value="4" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'selected' : ''; ?>>4 Stars - Very Good</option>
                                            <option value="3" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'selected' : ''; ?>>3 Stars - Good</option>
                                            <option value="2" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'selected' : ''; ?>>2 Stars - Fair</option>
                                            <option value="1" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'selected' : ''; ?>>1 Star - Poor</option>
                                        </select>
                                        <?php if (isset($review_errors['rating'])): ?><span class="error-text"><?php echo esc_html($review_errors['rating']); ?></span><?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label for="review_text">Your Review <span class="required">*</span></label>
                                        <textarea id="review_text" name="review_text" rows="5" class="form-control" required><?php echo esc_html($_POST['review_text'] ?? ''); ?></textarea>
                                        <?php if (isset($review_errors['review_text'])): ?><span class="error-text"><?php echo esc_html($review_errors['review_text']); ?></span><?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                    </div>
                                </form>
                            <?php else: // User has reviewed ?>
                                <p class="info-message text-center">You have already submitted a review for this product. Thank you!</p>
                                <?php endif; ?>
                        <?php else: // User is logged in but hasn't purchased the product ?>
                            <p class="info-message text-center">You must purchase this product (and it must be shipped/delivered) to leave a review.</p>
                        <?php endif; ?>
                    <?php else: // User is not logged in ?>
                        <p class="info-message text-center">Please <a href="<?php echo get_asset_url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); ?>">log in</a> to submit a review.</p>
                    <?php endif; ?>
                </div>
            </section>
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