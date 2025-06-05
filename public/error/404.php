<?php
// public/error/404.php
// Custom Error Page: Not Found

// Set HTTP status code
http_response_code(404);

// Include necessary configuration and header
$page_title = "404 Not Found - BaladyMall";
require_once __DIR__ . '/../../src/config/config.php';
require_once PROJECT_ROOT_PATH . '/src/includes/header.php';
?>

<section class="info-page-section error-page-section text-center">
    <div class="container page-content">
        <h1 class="section-title text-center mb-4">404 Page Not Found</h1>
        <p class="lead-text">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <p>Please check the URL for any typos, or try navigating from the homepage.</p>
        <div class="confirmation-actions">
            <a href="<?php echo get_asset_url('index.php'); ?>" class="btn btn-primary">Go to Homepage</a>
            <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-outline-secondary">Browse Products</a>
        </div>
    </div>
</section>

<?php
require_once PROJECT_ROOT_PATH . '/src/includes/footer.php';
?>