<?php
// public/products.php

// Ensure config.php is loaded first
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found.");
}

// Include header
$header_path = PROJECT_ROOT_PATH . '/src/includes/header.php';
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

$page_title = "All Products - " . esc_html(SITE_NAME);

$db = getPDOConnection();
$all_products = [];
$page_error_message = '';
$category_name = '';
$brand_name = '';
$search_query = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? '');

// Filter by Category ID
$filter_category_id = null;
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filter_category_id = (int)$_GET['category_id'];
    if ($db) {
        try {
            $stmt_cat = $db->prepare("SELECT category_name FROM categories WHERE category_id = ?");
            $stmt_cat->execute([$filter_category_id]);
            $cat_result = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            if ($cat_result) {
                $category_name = esc_html($cat_result['category_name']);
                $page_title = $category_name . " Products - " . esc_html(SITE_NAME);
            }
        } catch (PDOException $e) { error_log("Error fetching category name: " . $e->getMessage()); }
    }
}

// Filter by Brand ID
$filter_brand_id = null;
if (isset($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filter_brand_id = (int)$_GET['brand_id'];
     if ($db) {
        try {
            $stmt_brand = $db->prepare("SELECT brand_name FROM brands WHERE brand_id = ?");
            $stmt_brand->execute([$filter_brand_id]);
            $brand_result = $stmt_brand->fetch(PDO::FETCH_ASSOC);
            if ($brand_result) {
                $brand_name = esc_html($brand_result['brand_name']);
                $page_title = $brand_name . " Products - " . esc_html(SITE_NAME);
            }
        } catch (PDOException $e) { error_log("Error fetching brand name: " . $e->getMessage()); }
    }
}


if ($db) {
    try {
        $sql = "
            SELECT p.product_id, p.product_name, p.price, p.compare_at_price, p.main_image_url, b.brand_name
            FROM products p
            JOIN brands b ON p.brand_id = b.brand_id
        ";
        $conditions = ["p.is_active = 1", "b.is_approved = 1"];
        $params = [];

        if ($filter_category_id !== null) {
            $sql .= " JOIN product_category pc ON p.product_id = pc.product_id ";
            $conditions[] = "pc.category_id = ?";
            $params[] = $filter_category_id;
        }
        if ($filter_brand_id !== null) {
            $conditions[] = "p.brand_id = ?";
            $params[] = $filter_brand_id;
        }
        if (!empty($search_query)) {
            $conditions[] = "(p.product_name LIKE ? OR p.product_description LIKE ?)";
            $params[] = '%' . $search_query . '%';
            $params[] = '%' . $search_query . '%';
            $page_title = "Search Results for '" . esc_html($search_query) . "' - " . esc_html(SITE_NAME);
        }

        if (!empty($conditions)) { $sql .= " WHERE " . implode(" AND ", $conditions); }
        $sql .= " ORDER BY p.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching products: " . $e->getMessage());
        $page_error_message = "Sorry, we couldn't load the products at this time.";
    }
} else {
    $page_error_message = "Database connection is not available.";
}
?>

<section class="product-listing-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">
            <?php
            if (!empty($search_query)) { echo "Search Results for '" . esc_html($search_query) . "'"; }
            elseif ($category_name) { echo esc_html($category_name) . " Products"; }
            elseif ($brand_name) { echo "Products by " . esc_html($brand_name); }
            else { echo "Our Products"; }
            ?>
        </h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message"><?php echo esc_html($page_error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($all_products) && empty($page_error_message)): ?>
            <p class="text-center info-message" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                <?php if (!empty($search_query)): ?>
                    No products found matching "<?php echo esc_html($search_query); ?>".
                <?php else: ?>
                    No products found at the moment.
                <?php endif; ?>
            </p>
        <?php elseif (!empty($all_products)): ?>
            <div class="product-grid">
                <?php foreach ($all_products as $product): ?>
                    <?php
                        $product_url = get_asset_url("product_detail.php?id=" . esc_html($product['product_id']));
                        $image_path = !empty($product['main_image_url']) ? esc_html($product['main_image_url']) : '';
                        $image_url = '';
                        $fallback_image_url_esc = get_asset_url('images/no-image.png');
                         if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                           $fallback_image_url_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x300/E0E0E0/777?text=No+Image");
                        }
                        if (!empty($image_path)) {
                            if (filter_var($image_path, FILTER_VALIDATE_URL)) { $image_url = $image_path; }
                            // FIX: Use get_asset_url with 'uploads/' prefix for consistency
                            else { $image_url = get_asset_url('uploads/' . ltrim($image_path, '/'));}
                        }
                        if (empty($image_url)) { $image_url = $fallback_image_url_esc; }
                    ?>
                    <div class="product-item animate-on-scroll">
                        <a href="<?php echo $product_url; ?>" class="product-item-link">
                            <div class="product-image-container">
                                <img src="<?php echo $image_url; ?>" alt="<?php echo esc_html($product['product_name']); ?>" class="rounded-md shadow-sm product-image" onerror="this.onerror=null;this.src='<?php echo $fallback_image_url_esc; ?>';">
                            </div>
                            <div class='item-content-wrapper'> <h3 class="product-name"><?php echo esc_html($product['product_name']); ?></h3>
                                <p class="product-brand"><?php echo esc_html($product['brand_name']); ?></p>
                                <div class="price-container">
                                    <?php if (!empty($product['compare_at_price']) && $product['compare_at_price'] > 0 && $product['compare_at_price'] > $product['price']): ?>
                                        <span class="product-price current"><?php echo $GLOBALS['currency_symbol'] . " " . esc_html(number_format($product['price'], 2)); ?></span>
                                        <span class="product-price original"><?php echo $GLOBALS['currency_symbol'] . " " . esc_html(number_format($product['compare_at_price'], 2)); ?></span>
                                    <?php else: ?>
                                        <span class="product-price current"><?php echo $GLOBALS['currency_symbol'] . " " . esc_html(number_format($product['price'], 2)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div> </a> <div class="product-actions mt-auto">
                            <form action="<?php echo get_asset_url("cart.php"); ?>" method="POST" class="add-to-cart-form-list ajax-add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo esc_html($product['product_id']); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-sm btn-add-to-cart">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-plus" viewBox="0 0 16 16" style="margin-right: 5px; vertical-align: text-bottom;"><path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/><path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
                                    Add to Cart
                                </button>
                            </form>
                             <a href="<?php echo $product_url; ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$footer_path = PROJECT_ROOT_PATH . '/src/includes/footer.php';
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found.");
}
?>