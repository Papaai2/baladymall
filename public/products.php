<?php
// public/products.php

// Ensure config.php is loaded first for all constants and session_start()
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// Include header (which will also rely on config.php)
$header_path_from_public = PROJECT_ROOT_PATH . '/src/includes/header.php';
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public; // Starts session
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path_from_public));
}

$page_title = "All Products - BaladyMall";

$db = getPDOConnection();
$all_products = [];
$page_error_message = '';
$category_name = ''; // To display the category name in the title
$brand_name = '';    // To display the brand name if filtered by brand

// --- NEW LOGIC: Filter by Category ID from URL ---
$filter_category_id = null;
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filter_category_id = (int)$_GET['category_id'];
    // Try to fetch category name for title display
    try {
        $stmt_cat = $db->prepare("SELECT category_name FROM categories WHERE category_id = ?");
        $stmt_cat->execute([$filter_category_id]);
        $cat_result = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        if ($cat_result) {
            $category_name = esc_html($cat_result['category_name']);
            $page_title = $category_name . " Products - BaladyMall";
        }
    } catch (PDOException $e) {
        error_log("Error fetching category name: " . $e->getMessage());
    }
}

// --- NEW LOGIC: Filter by Brand ID from URL (similar to category filtering) ---
$filter_brand_id = null;
if (isset($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filter_brand_id = (int)$_GET['brand_id'];
    // Try to fetch brand name for title display
    try {
        $stmt_brand = $db->prepare("SELECT brand_name FROM brands WHERE brand_id = ?");
        $stmt_brand->execute([$filter_brand_id]);
        $brand_result = $stmt_brand->fetch(PDO::FETCH_ASSOC);
        if ($brand_result) {
            $brand_name = esc_html($brand_result['brand_name']);
            $page_title = $brand_name . " Products - BaladyMall";
        }
    } catch (PDOException $e) {
        error_log("Error fetching brand name: " . $e->getMessage());
    }
}


try {
    // Base SQL query
    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.price,
            p.compare_at_price,
            p.main_image_url,
            b.brand_name
        FROM products p
        JOIN brands b ON p.brand_id = b.brand_id
    ";

    $conditions = ["p.is_active = 1", "b.is_approved = 1"]; // Default conditions
    $params = []; // Parameters for prepared statement

    // If filtering by category, add JOIN and WHERE clause
    if ($filter_category_id !== null) {
        $sql .= " JOIN product_category pc ON p.product_id = pc.product_id ";
        $conditions[] = "pc.category_id = ?";
        $params[] = $filter_category_id;
    }

    // If filtering by brand, add WHERE clause
    if ($filter_brand_id !== null) {
        $conditions[] = "p.brand_id = ?";
        $params[] = $filter_brand_id;
    }

    // Append all conditions to the SQL query
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC"; // Order by newest first

    // Prepare and execute the statement
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $page_error_message = "Sorry, we couldn't load the products at this time. Please try again later.";
}

?>

<section class="product-listing-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">
            <?php
            if ($category_name) {
                echo esc_html($category_name) . " Products";
            } elseif ($brand_name) {
                echo "Products by " . esc_html($brand_name);
            } else {
                echo "Our Products";
            }
            ?>
        </h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message">
                <?php echo htmlspecialchars($page_error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($all_products) && empty($page_error_message)): ?>
            <p class="text-center info-message" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                No products found at the moment. Please check back soon!
                <?php if ($category_name): ?> in this category.<?php endif; ?>
                <?php if ($brand_name): ?> for this brand.<?php endif; ?>
            </p>
        <?php elseif (!empty($all_products)): ?>
            <div class="product-grid">
                <?php foreach ($all_products as $product): ?>
                    <?php
                        $product_url = rtrim(SITE_URL, '/') . "/product_detail.php?id=" . htmlspecialchars($product['product_id']);
                        $image_path = !empty($product['main_image_url']) ? htmlspecialchars($product['main_image_url']) : '';

                        $image_url = '';

                        if (!empty($image_path) && (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0)) {
                            $image_url = $image_path;
                        } elseif (!empty($image_path) && defined('PUBLIC_UPLOADS_URL_BASE')) {
                            $image_url = rtrim(PUBLIC_UPLOADS_URL_BASE, '/') . '/' . ltrim($image_path, '/');
                        } else {
                            $image_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x300/F0F0F0/AAA?text=No+Image" : 'https://placehold.co/300x300/F0F0F0/AAA?text=No+Image';
                        }
                        $fallback_image_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x300/E0E0E0/777?text=Image+Error" : 'https://placehold.co/300x300/E0E0E0/777?text=Image+Error';
                    ?>
                    <div class="product-item animate-on-scroll">
                        <a href="<?php echo $product_url; ?>" class="product-item-link">
                            <div class="product-image-container">
                                <img src="<?php echo $image_url; ?>"
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     class="rounded-md shadow-sm product-image"
                                     onerror="this.onerror=null;this.src='<?php echo $fallback_image_url; ?>';">
                            </div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        </a>
                        <div class="item-content-wrapper">
                            <p class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                            <div class="price-container">
                                <?php
                                if (!empty($product['compare_at_price']) && $product['compare_at_price'] > 0 && $product['compare_at_price'] > $product['price']):
                                ?>
                                    <span class="product-price current"><?php echo CURRENCY_SYMBOL . esc_html(number_format($product['price'], 2)); ?></span>
                                    <span class="product-price original"><?php echo CURRENCY_SYMBOL . esc_html(number_format($product['compare_at_price'], 2)); ?></span>
                                <?php else: ?>
                                    <span class="product-price current"><?php echo CURRENCY_SYMBOL . esc_html(number_format($product['price'], 2)); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="product-actions mt-auto">
                            <form action="<?php echo rtrim(SITE_URL, '/') . "/cart.php"; ?>" method="POST" class="add-to-cart-form-list ajax-add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-sm btn-add-to-cart">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-plus" viewBox="0 0 16 16" style="margin-right: 5px; vertical-align: text-bottom;">
                                      <path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/>
                                      <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </form>
                             <a href="<?php echo $product_url; ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
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
$footer_path_from_public = PROJECT_ROOT_PATH . '/src/includes/footer.php';
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path_from_public));
}
?>
