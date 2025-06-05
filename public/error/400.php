<?php
// public/error/400.php
// Custom Error Page: Bad Request

// Set HTTP status code
http_response_code(400);

// Include necessary configuration and header
$page_title = "400 Bad Request - BaladyMall";
require_once __DIR__ . '/../../src/config/config.php';
require_once PROJECT_ROOT_PATH . '/src/includes/header.php';
?>

<section class="info-page-section error-page-section text-center">
    <div class="container page-content">
        <h1 class="section-title text-center mb-4">400 Bad Request</h1>
        <p class="lead-text">The server cannot process the request due to a client error.</p>
        <p>This might be due to malformed request syntax, invalid request message framing, or deceptive request routing.</p>
        <p>Please check your request and try again.</p>
        <div class="confirmation-actions">
            <a href="<?php echo get_asset_url('index.php'); ?>" class="btn btn-primary">Go to Homepage</a>
            <a href="<?php echo get_asset_url('contact.php'); ?>" class="btn btn-outline-secondary">Contact Support</a>
        </div>
    </div>
</section>

<?php
require_once PROJECT_ROOT_PATH . '/src/includes/footer.php';
?>