<?php
// public/error/500.php - Internal Server Error
http_response_code(500);

// For 500.php, we keep dependencies minimal because the error might be preventing config.php itself from loading.
// Define basic constants as fallbacks if config.php cannot be loaded.
$site_name = 'BaladyMall';
$site_url = '/';
$logo_url = '/images/default_logo.jpg'; // Path from public/ directory, assume it's always there.

// Attempt to load config.php, but wrap in a try-catch for robustness specific to 500.php
// This allows some dynamic content if config loads, but won't crash if it doesn't.
$config_path_for_500 = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($config_path_for_500)) {
    try {
        require_once $config_path_for_500;
        // If config.php loaded, try to get settings, but check if constants are defined
        if (defined('SITE_NAME')) $site_name = esc_html(SITE_NAME);
        if (defined('SITE_URL')) $site_url = esc_html(SITE_URL);
        if (function_exists('get_asset_url') && defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
            $logo_url = get_asset_url(SITE_LOGO_PATH);
        } else {
            $logo_url = get_asset_url('images/default_logo.jpg'); // Fallback using get_asset_url if defined
        }
    } catch (Throwable $e) {
        // If config.php itself throws an error, log it but proceed with hardcoded fallbacks.
        error_log("CRITICAL: Error loading config.php within 500.php! Using hardcoded fallbacks. " . $e->getMessage());
        // $site_name, $site_url, $logo_url remain as their initial hardcoded fallbacks.
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error - <?php echo $site_name; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; color: #343a40; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; text-align: center; }
        .error-container { background-color: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 500px; width: 90%; }
        h1 { font-size: 3em; color: #dc3545; margin-bottom: 10px; }
        h2 { font-size: 1.5em; color: #6c757d; margin-bottom: 20px; }
        p { font-size: 1.1em; line-height: 1.6; margin-bottom: 25px; }
        a { color: #007bff; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        .logo { margin-bottom: 20px; }
        .logo img { max-width: 150px; height: auto; display: block; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">
            <a href="<?php echo $site_url; ?>/index.php">
                <img src="<?php echo $logo_url; ?>" alt="<?php echo $site_name; ?> Logo">
            </a>
        </div>
        <h1>500</h1>
        <h2>Internal Server Error</h2>
        <p>We're really sorry, but something unexpected went wrong on our server.</p>
        <p>Our team has been notified and is working to fix the issue. Please try again later.</p>
        <p><a href="<?php echo $site_url; ?>/index.php">Go to Homepage</a></p>
    </div>
</body>
</html>