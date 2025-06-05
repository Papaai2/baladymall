<?php
// public/error/500.php
// Custom Error Page: Internal Server Error

// Set HTTP status code
http_response_code(500);

// Include necessary configuration and header
$page_title = "500 Internal Server Error - BaladyMall";
require_once __DIR__ . '/../../src/config/config.php';
require_once PROJECT_ROOT_PATH . '/src/includes/header.php';
?>

<section class="info-page-section error-page-section text-center">
    <div class="container page-content">
        <h1 class="section-title text-center mb-4">500 Internal Server Error</h1>
        <p class="lead-text">Oops! Something went wrong on our end.</p>
        <p>We're experiencing technical difficulties. Our team has been notified and is working to resolve the issue as quickly as possible.</p>
        <p>Please try again in a few moments, or <a href="<?php echo get_asset_url('contact.php'); ?>">contact us</a> if the problem persists.</p>
        <div class="confirmation-actions">
            <a href="<?php echo get_asset_url('index.php'); ?>" class="btn btn-primary">Go to Homepage</a>
            <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-outline-secondary">Browse Products</a>
        </div>
    </div>
</section>

<?php
require_once PROJECT_ROOT_PATH . '/src/includes/footer.php';
?>