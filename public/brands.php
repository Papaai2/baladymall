<?php
// public/brands.php

$page_title = "Our Brands - BaladyMall";

// Configuration, Header, and Footer paths
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

// Header includes session_start()
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

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

<section class="brand-listing-section" style="padding: 30px 0;">
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
            <div class="product-grid">
                <?php foreach ($brands_list as $brand): ?>
                    <?php
                        $brand_url = rtrim(SITE_URL, '/') . "/products.php?brand_id=" . esc_html($brand['brand_id']);

                        $logo_path = !empty($brand['brand_logo_url']) ? esc_html($brand['brand_logo_url']) : '';
                        $logo_display_url = '';

                        if (!empty($logo_path) && (strpos($logo_path, 'http://') === 0 || strpos($logo_path, 'https://') === 0)) {
                            $logo_display_url = $logo_path;
                        } elseif (!empty($logo_path) && defined('PUBLIC_UPLOADS_URL_BASE')) {
                            $logo_display_url = rtrim(PUBLIC_UPLOADS_URL_BASE, '/') . '/' . ltrim($logo_path, '/');
                        } else {
                            $logo_display_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/DDEEFF/555?text=" . urlencode(esc_html($brand['brand_name'])) : '#';
                        }
                        $fallback_logo_url = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/300x200/CCC/777?text=Logo+Error" : '#';
                    ?>
                    <div class="product-item animate-on-scroll">
                        <a href="<?php echo $brand_url; ?>">
                            <img src="<?php echo $logo_display_url; ?>"
                                 alt="<?php echo esc_html($brand['brand_name']); ?> Logo"
                                 onerror="this.onerror=null;this.src='<?php echo $fallback_logo_url; ?>';"
                                 class="rounded-md shadow-sm">
                            <h3 class="product-name"><?php echo esc_html($brand['brand_name']); ?></h3>
                        </a>
                        <div class="item-content-wrapper">
                            <p class="product-brand" style="font-style: italic; font-size: 0.9em; min-height: 2.7em; /* Approx 2 lines */ display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo !empty($brand['brand_description']) ? esc_html(substr($brand['brand_description'], 0, 70)) . (strlen($brand['brand_description']) > 70 ? '...' : '') : 'Discover products from this amazing brand.'; ?>
                            </p>
                            <p class="product-count-info" style="font-size: 0.85em; color: #6c757d; margin-top: 5px;">
                                <?php echo esc_html($brand['product_count']); ?> Product(s)
                            </p>
                        </div>
                        <a href="<?php echo $brand_url; ?>" class="btn btn-sm btn-outline-primary mt-auto">View Products</a>
                    </div>
                <?php endforeach; ?>
            </div>
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