<?php
// public/error/403.php
// Custom Error Page: Forbidden

// Set HTTP status code
http_response_code(403);

// Include necessary configuration and header
$page_title = "403 Forbidden - BaladyMall";
require_once __DIR__ . '/../../src/config/config.php';
require_once PROJECT_ROOT_PATH . '/src/includes/header.php';
?>

<section class="info-page-section error-page-section text-center">
    <div class="container page-content">
        <h1 class="section-title text-center mb-4">403 Forbidden</h1>
        <p class="lead-text">You don't have permission to access this resource.</p>
        <p>This typically means you lack the necessary authorization or the resource is protected from public access.</p>
        <div class="confirmation-actions">
            <a href="<?php echo get_asset_url('index.php'); ?>" class="btn btn-primary">Go to Homepage</a>
            <a href="<?php echo get_asset_url('contact.php'); ?>" class="btn btn-outline-secondary">Contact Support</a>
        </div>
    </div>
</section>

<?php
require_once PROJECT_ROOT_PATH . '/src/includes/footer.php';
?>