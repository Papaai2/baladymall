<?php
// public/error/401.php
// Custom Error Page: Unauthorized

// Set HTTP status code
http_response_code(401);

// Include necessary configuration and header
$page_title = "401 Unauthorized - BaladyMall";
require_once __DIR__ . '/../../src/config/config.php';
require_once PROJECT_ROOT_PATH . '/src/includes/header.php';
?>

<section class="info-page-section error-page-section text-center">
    <div class="container page-content">
        <h1 class="section-title text-center mb-4">401 Unauthorized</h1>
        <p class="lead-text">You are not authorized to access this resource.</p>
        <p>This usually means you need to authenticate or provide valid credentials.</p>
        <div class="confirmation-actions">
            <a href="<?php echo get_asset_url('login.php'); ?>" class="btn btn-primary">Login Now</a>
            <a href="<?php echo get_asset_url('index.php'); ?>" class="btn btn-outline-secondary">Go to Homepage</a>
        </div>
    </div>
</section>

<?php
require_once PROJECT_ROOT_PATH . '/src/includes/footer.php';
?>