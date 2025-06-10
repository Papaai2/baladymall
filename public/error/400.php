<?php
// public/error/400.php - Bad Request
http_response_code(400);

// Attempt to load config for site info, but be prepared for it to fail
$config_path = dirname(__DIR__) . '/src/config/config.php';
$site_name = 'BaladyMall'; // Default fallback
$site_url = '/'; // Default fallback
$logo_url = '/images/default_logo.jpg'; // Default fallback logo

if (file_exists($config_path)) {
    require_once $config_path;
    $site_name = defined('SITE_NAME') ? esc_html(SITE_NAME) : $site_name;
    $site_url = defined('SITE_URL') ? esc_html(SITE_URL) : $site_url;
    // For logo, use get_asset_url if available and logo path is defined
    if (function_exists('get_asset_url') && defined('SITE_LOGO_PATH') && !empty(SITE_LOGO_PATH)) {
        $logo_url = get_asset_url(SITE_LOGO_PATH);
    } else {
        $logo_url = get_asset_url('images/default_logo.jpg'); // Public path to a generic default logo
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>400 Bad Request - <?php echo $site_name; ?></title>
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
        <h1>400</h1>
        <h2>Bad Request</h2>
        <p>The server cannot process the request because it is malformed. Please check your input or try again.</p>
        <p><a href="<?php echo $site_url; ?>/index.php">Go to Homepage</a></p>
    </div>
</body>
</html>