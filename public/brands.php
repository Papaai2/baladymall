<?php
// public/brands.php

// Ensure config.php is loaded first for all constants and session_start()
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . htmlspecialchars($config_path_from_public));
}

// Include header (which will also rely on config.php)
$header_path = PROJECT_ROOT_PATH . '/src/includes/header.php';
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

$page_title = "Our Brands - BaladyMall";

// Ensure $db is available
$db_available = false;
if (isset($db) && $db instanceof PDO) {
    $db_available = true;
} elseif (function_exists('getPDOConnection')) {
    $db = getPDOConnection(); // Attempt to get connection if not already set
    if (isset($db) && $db instanceof PDO) {
        $db_available = true;
    }
}

$brands_list = [];
$page_error_message = '';

if ($db_available) {
    try {
        // Fetch only approved brands
        $stmt = $db->query("
            SELECT
                brand_id,
                brand_name,
                brand_logo_url,
                brand_description,
                (SELECT COUNT(*) FROM products p WHERE p.brand_id = brands.brand_id AND p.is_active = 1) as product_count
            FROM brands
            WHERE is_approved = 1
            ORDER BY brand_name ASC
        ");
        $brands_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching brands: " . $e->getMessage());
        $page_error_message = "Sorry, we couldn't load the brands at this time. Please try again later.";
    }
} else {
    $page_error_message = "Database connection is not available. Cannot display brands.";
}

?>

<section class="brand-listing-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Discover Our Brands</h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="form-message error-message text-center" style="padding: 20px; margin: 20px auto; max-width: 600px;">
                <?php echo esc_html($page_error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($brands_list) && empty($page_error_message)): ?>
            <p class="text-center info-message" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                No brands found at the moment. Please check back soon!
            </p>
        <?php elseif (!empty($brands_list)): ?>
            <div class="product-grid"> <?php foreach ($brands_list as $brand): ?>
                    <?php
                        // Use get_asset_url for the brand specific product listing
                        $brand_url = get_asset_url("products.php?brand_id=" . esc_html($brand['brand_id']));

                        $logo_path = !empty($brand['brand_logo_url']) ? esc_html($brand['brand_logo_url']) : '';
                        $logo_display_url = '';

                        // Determine fallback logo URL and ensure it's properly escaped for the onerror attribute
                        $fallback_logo_url_esc = get_asset_url('images/no-image.png'); // Fallback to a local 'no-image' if placeholder generator is not defined or empty
                        if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                            $fallback_logo_url_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/CCC/777?text=Logo+Error");
                        }

                        // FIX: If DB stores 'brands/filename.jpg', ensure get_asset_url gets 'uploads/brands/filename.jpg'
                        if (!empty($logo_path)) {
                            if (filter_var($logo_path, FILTER_VALIDATE_URL)) {
                                $logo_display_url = $logo_path;
                            } else {
                                $logo_display_url = get_asset_url('uploads/' . ltrim($logo_path, '/'));
                            }
                        }

                        // If main $logo_display_url is still empty, set it to the fallback
                        if (empty($logo_display_url)) {
                            $logo_display_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/F0F0F0/AAA?text=Brand+Logo" : $fallback_logo_url_esc;
                        }
                    ?>
                    <div class="product-item animate-on-scroll"> <a href="<?php echo $brand_url; ?>" class="product-item-link"> <div class="product-image-container"> <img src="<?php echo $logo_display_url; ?>"
                                     alt="<?php echo esc_html($brand['brand_name']); ?> Logo"
                                     onerror="this.onerror=null;this.src='<?php echo $fallback_logo_url_esc; ?>';"
                                     class="product-image"> </div>
                            <div class="item-content-wrapper"> <h3 class="product-name"><?php echo esc_html($brand['brand_name']); ?></h3> <p class="product-brand" style="font-style: italic; font-size: 0.9em; min-height: 2.7em; /* Approx 2 lines */ display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo !empty($brand['brand_description']) ? esc_html(substr($brand['brand_description'], 0, 70)) . (strlen($brand['brand_description']) > 70 ? '...' : '') : 'Discover products from this amazing brand.'; ?>
                                </p>
                                <p class="product-count-info" style="font-size: 0.85em; color: #6c757d; margin-top: 5px;">
                                    <?php echo esc_html($brand['product_count']); ?> Product(s)
                                </p>
                            </div>
                        </a> <div class="product-actions mt-auto"> <a href="<?php echo $brand_url; ?>" class="btn btn-sm btn-outline-primary">View Products</a>
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
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>