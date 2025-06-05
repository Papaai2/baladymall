<?php
// public/categories.php

// Ensure config.php is loaded first for all constants and session_start()
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . htmlspecialchars($config_path_from_public));
}

// Include header (which will also rely on config.php)
$header_path_from_public = PROJECT_ROOT_PATH . '/src/includes/header.php';
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public; // Starts session
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path_from_public));
}

$page_title = "Browse Product Categories - BaladyMall";

$db = getPDOConnection();
$categories_list = [];
$page_error_message = '';

if ($db) { // Check if DB connection was successful
    try {
        // Fetch all categories, linking to parent category name for display.
        // This query includes both top-level and sub-categories and their product counts.
        $stmt = $db->query("
            SELECT
                c.category_id,
                c.category_name,
                c.category_description,
                c.category_image_url,
                (SELECT COUNT(*) FROM product_category pc JOIN products p ON pc.product_id = p.product_id WHERE pc.category_id = c.category_id AND p.is_active = 1) as product_count,
                p.category_name as parent_category_name
            FROM categories c
            LEFT JOIN categories p ON c.parent_category_id = p.category_id
            ORDER BY p.category_name ASC, c.category_name ASC
        ");

        $categories_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        $page_error_message = "Sorry, we couldn't load the categories at this time. Please try again later.";
    }
} else {
    $page_error_message = "Database connection is not available. Cannot display categories.";
}

?>

<section class="category-listing-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Explore Our Categories</h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message text-center">
                <?php echo esc_html($page_error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($categories_list) && empty($page_error_message)): ?>
            <p class="text-center info-message" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                No product categories found at the moment. Please check back soon!
            </p>
        <?php elseif (!empty($categories_list)): ?>
            <div class="category-grid">
                <?php foreach ($categories_list as $category): ?>
                    <?php
                        // Link to products.php, passing category_id to filter. Use get_asset_url.
                        $category_url = get_asset_url("products.php?category_id=" . esc_html($category['category_id']));

                        $image_path = !empty($category['category_image_url']) ? esc_html($category['category_image_url']) : '';
                        $image_url = '';

                        // Determine fallback image URL and ensure it's properly escaped for the onerror attribute
                        $fallback_image_url_esc = '';
                        if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                            $fallback_image_url_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/E0E0E0/777?text=No+Image");
                        } else {
                            // Fallback to a local 'no-image' if placeholder generator is not defined or empty
                            $fallback_image_url_esc = get_asset_url('images/no-image.png'); // Assuming you have this file
                        }

                        // Fix: If DB stores 'categories/filename.jpg', ensure get_asset_url gets 'uploads/categories/filename.jpg'
                        if (!empty($image_path)) {
                            if (filter_var($image_path, FILTER_VALIDATE_URL)) {
                                $image_url = $image_path; // It's a full URL already
                            } else {
                                // It's a relative path starting with 'categories/'. Prepends 'uploads/' then trims leading slash.
                                $image_url = get_asset_url('uploads/' . ltrim($image_path, '/'));
                            }
                        }

                        // If main $image_url is still empty, set it to the fallback
                        if (empty($image_url)) {
                            $image_url = $fallback_image_url_esc;
                        }
                    ?>
                    <div class="category-item animate-on-scroll">
                        <a href="<?php echo $category_url; ?>">
                            <img src="<?php echo $image_url; ?>"
                                 alt="<?php echo esc_html($category['category_name']); ?>"
                                 onerror="this.onerror=null;this.src='<?php echo $fallback_image_url_esc; ?>';"
                                 class="rounded-md shadow-sm">
                            <h3><?php echo esc_html($category['category_name']); ?></h3>
                        </a>
                        <div class="item-content-wrapper">
                            <?php if(!empty($category['parent_category_name'])): ?>
                                <p class="parent-category-info"><small>In: <?php echo esc_html($category['parent_category_name']); ?></small></p>
                            <?php endif; ?>
                            <p class="category-description">
                                <?php echo !empty($category['category_description']) ? esc_html(substr($category['category_description'], 0, 100)) . (strlen($category['category_description']) > 100 ? '...' : '') : 'Explore products in this category.'; ?>
                            </p>
                            <p class="product-count-info">
                                <small><?php echo esc_html($category['product_count']); ?> Product(s)</small>
                            </p>
                        </div>
                        <a href="<?php echo $category_url; ?>" class="btn btn-sm btn-outline-primary mt-auto">View Products</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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