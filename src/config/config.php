<?php
// src/config/config.php

// --- Error Reporting (Development vs Production) ---
ini_set('display_errors', 1); // Set to 0 in production for live site
ini_set('display_startup_errors', 1); // Set to 0 in production for live site
error_reporting(E_ALL); // Report all PHP errors

// --- Custom Error and Exception Handling (for production robustness) ---
// Define a default response for AJAX errors
function get_default_ajax_error_response() {
    return [
        'success' => false,
        'message' => 'An unexpected server error occurred. Please try again later.',
        'type' => 'error'
    ];
}

// Custom Error Handler: Convert PHP errors (warnings, notices) into exceptions
// This allows errors to be caught by try-catch blocks.
function custom_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    // Throw an ErrorException, which can be caught by a standard catch(Exception $e)
    throw new ErrorException($message, 0, $severity, $file, $line);
}

// Custom Exception Handler: Catch all uncaught exceptions (including fatal errors if registered early enough)
function custom_exception_handler(Throwable $exception) {
    // Log the error details
    error_log(sprintf(
        "Uncaught Exception: %s in %s:%d\nStack trace:\n%s",
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    ));

    // Determine if it's an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $is_ajax_action = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['update_cart_all_btn'])); // As defined in cart.php

    if ($is_ajax || $is_ajax_action) {
        // For AJAX requests, output a generic JSON error
        http_response_code(500); // Internal Server Error
        header('Content-Type: application/json');
        ob_clean(); // Clear any existing buffer content
        echo json_encode(get_default_ajax_error_response());
    } else {
        // For regular HTML requests, display a generic error page or message
        // In production, you might redirect to a friendly error page.
        // For development, display a simple message.
        if (ini_get('display_errors')) { // If display_errors is ON (development)
            echo "<h1>Application Error</h1>";
            echo "<p>An unexpected error occurred: " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p>File: " . htmlspecialchars($exception->getFile()) . " Line: " . htmlspecialchars($exception->getLine()) . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else { // If display_errors is OFF (production)
            http_response_code(500);
            echo "<h1>An unexpected error occurred.</h1>";
            echo "<p>We're sorry, but something went wrong. Please try again later.</p>";
            // You could include a link to contact support or home page.
        }
    }
    exit(); // Terminate script execution
}

// Register the custom handlers
set_error_handler("custom_error_handler");
set_exception_handler("custom_exception_handler");


// --- Session Management ---
// Ensure session is started only once per request.
// This block should be at the very top of any file that uses sessions.
if (session_status() == PHP_SESSION_NONE) {
    // Define SESSION_NAME if not already set (it should ideally be loaded from site_settings later)
    if (!defined('SESSION_NAME')) {
        define('SESSION_NAME', 'BaladyMallSession'); // Default fallback name
    }
    session_name(SESSION_NAME);

    // Set secure session cookie parameters for production.
    // IMPORTANT: Uncomment and configure these for your live domain!
    /*
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie expires when the browser closes
        'path' => '/', // Cookie is available across the entire domain
        'domain' => '', // IMPORTANT: Set your actual domain for production, e.g., '.yourdomain.com'
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // Only send over HTTPS
        'httponly' => true, // Prevent JavaScript access to the cookie
        'samesite' => 'Lax' // Or 'Strict' for more security
    ]);
    */

    $session_start_result = session_start();
    if (!$session_start_result) {
        // Log a critical error if session_start fails (e.g., permissions on session save path)
        error_log("CRITICAL ERROR: session_start() failed in config.php. Check session save path permissions.");
        // In a production environment, you might want to redirect to a generic error page or exit here.
        // For development, we'll log and attempt to continue, but expect session-related issues.
    }
}


// --- Site URL and Paths ---

// **CRITICAL FIX: Robust PROJECT_ROOT_PATH definition (filesystem path)**
// This reliably points to the 'baladymall' root directory on the server's filesystem.
// __FILE__ is the full path to the current file (config.php).
// dirname(__FILE__) is 'baladymall/src/config'
// dirname(dirname(__FILE__)) is 'baladymall/src'
// dirname(dirname(dirname(__FILE__))) is 'baladymall' (the project root)
if (!defined('PROJECT_ROOT_PATH')) {
    define('PROJECT_ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}

// **CRITICAL FIX: EXPLICITLY DEFINE YOUR PROJECT'S BASE URLS**
// This bypasses any dynamic server variable issues that cause incorrect path segment detection.
// YOU MUST SET THESE MANUALLY TO MATCH YOUR EXACT XAMPP/WEB SERVER SETUP.
// Example: If your project is at C:\xampp\htdocs\baladymall\
// Then PROJECT_BASE_URL should be 'https://localhost/baladymall' if you're forcing HTTPS.
// If you're NOT forcing HTTPS locally, set it to 'http://localhost/baladymall'.
if (!defined('PROJECT_BASE_URL')) {
    define('PROJECT_BASE_URL', 'https://localhost/baladymall'); // <<-- IMPORTANT: Changed to HTTPS
}
if (!defined('SITE_URL')) {
    define('SITE_URL', PROJECT_BASE_URL . '/public'); // Derived from above
}


// Define specific URLs for admin and brand_admin panels
// These are siblings to 'public', so they derive from PROJECT_BASE_URL
// They now explicitly include /index.php for direct access.
if (!defined('ADMIN_ROOT_URL')) {
    define('ADMIN_ROOT_URL', rtrim(PROJECT_BASE_URL, '/') . '/admin/index.php');
}
if (!defined('BRAND_ADMIN_ROOT_URL')) {
    define('BRAND_ADMIN_ROOT_URL', rtrim(PROJECT_BASE_URL, '/') . '/brand_admin/index.php');
}


// Define public root path (filesystem path to the public folder)
if (!defined('PUBLIC_ROOT_PATH')) define('PUBLIC_ROOT_PATH', PROJECT_ROOT_PATH . '/public');

// Define path to the public uploads directory (filesystem path)
if (!defined('PUBLIC_UPLOADS_PATH')) define('PUBLIC_UPLOADS_PATH', PUBLIC_ROOT_PATH . '/uploads/');
// Define the web-accessible base URL for uploads
if (!defined('PUBLIC_UPLOADS_URL_BASE')) define('PUBLIC_UPLOADS_URL_BASE', rtrim(SITE_URL, '/') . '/uploads/');


// --- Database Credentials ---
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'baladymall_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// --- PDO Database Connection Function ---
if (!function_exists('getPDOConnection')) {
    function getPDOConnection() {
        static $pdo_instance = null;
        if ($pdo_instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                $pdo_instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                return null;
            }
        }
        return $pdo_instance;
    }
}
$db = null; // Initialize to null; individual scripts call getPDOConnection() as needed.


// --- Function to get a single setting from site_settings table ---
if (!function_exists('get_site_setting')) {
    function get_site_setting($db_conn_param, $key, $default_value = null) {
        if (!$db_conn_param instanceof PDO) {
            error_log("get_site_setting: Invalid database connection provided for key '{$key}'.");
            return $default_value;
        }
        try {
            $stmt = $db_conn_param->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default_value;
        } catch (PDOException $e) {
            error_log("Error fetching site_setting {$key}: " . $e->getMessage());
            return $default_value;
        }
    }
}

// --- Load Core Site Settings from Database into Constants ---
// This block attempts to get a DB connection to load settings.
$_settings_db_conn = null;
try {
    $_settings_db_conn = getPDOConnection(); // Get a connection for settings
} catch (Exception $e) {
    error_log("CONFIG.PHP: Initial DB connection for settings failed: " . $e->getMessage());
}

if ($_settings_db_conn instanceof PDO) {
    // Define constants using values from the database or defaults
    if (!defined('SITE_NAME')) define('SITE_NAME', get_site_setting($_settings_db_conn, 'site_name', 'BaladyMall'));
    if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', get_site_setting($_settings_db_conn, 'admin_email', 'admin@example.com'));
    if (!defined('PUBLIC_CONTACT_EMAIL')) define('PUBLIC_CONTACT_EMAIL', get_site_setting($_settings_db_conn, 'public_contact_email', 'support@example.com'));
    if (!defined('PUBLIC_CONTACT_PHONE')) define('PUBLIC_CONTACT_PHONE', get_site_setting($_settings_db_conn, 'public_contact_phone', ''));

    if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', get_site_setting($_settings_db_conn, 'default_currency_symbol', 'EGP '));
    if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', get_site_setting($_settings_db_conn, 'default_currency_code', 'EGP'));

    if (!defined('PLATFORM_COMMISSION_RATE')) define('PLATFORM_COMMISSION_RATE', (float)get_site_setting($_settings_db_conn, 'platform_commission_rate', '10.00'));

    // SITE_LOGO_PATH: The value from DB should be relative to PUBLIC_UPLOADS_URL_BASE (e.g., 'site/default_logo.png')
    // The get_asset_url function in header.php will then construct the full URL.
    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', get_site_setting($_settings_db_conn, 'site_logo_url', 'site/default_logo.png')); // FIX: Fallback simplified
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', get_site_setting($_settings_db_conn, 'favicon_url', 'favicon.ico')); // FIX: Fallback simplified


    // SMTP Configuration (from DB settings or hardcoded defaults)
    if (!defined('SMTP_HOST')) define('SMTP_HOST', get_site_setting($_settings_db_conn, 'smtp_host', 'smtp.example.com'));
    if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)get_site_setting($_settings_db_conn, 'smtp_port', 587));
    if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', get_site_setting($_settings_db_conn, 'smtp_username', 'your_email@example.com'));
    if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', get_site_setting($_settings_db_conn, 'smtp_password', 'your_smtp_password'));
    if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', get_site_setting($_settings_db_conn, 'smtp_encryption', 'tls')); // 'ssl' or 'tls' or ''
    if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', get_site_setting($_settings_db_conn, 'mail_from_address', 'no-reply@' . parse_url(SITE_URL, PHP_URL_HOST)));
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', get_site_setting($_settings_db_conn, 'mail_from_name', SITE_NAME));

} else {
    // Fallback constants if DB connection for settings failed
    if (!defined('SITE_NAME')) define('SITE_NAME', 'BaladyMall (DB Error)');
    if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'EGP ');
    error_log("CONFIG.PHP: Database connection not available when trying to load site settings, using hardcoded fallbacks.");

    if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 25);
    if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
    if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
    if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', '');
    if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'no-reply@example.com');
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'BaladyMall');

    // Fallback for logo and favicon if DB is not available
    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', 'site/default_logo.png'); // FIX: Consistent fallback
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', 'favicon.ico'); // FIX: Consistent fallback
}

// --- Maintenance Mode Check ---
// This check relies on site settings from the database.
if ($_settings_db_conn instanceof PDO) {
    $maintenance_mode_enabled = get_site_setting($_settings_db_conn, 'maintenance_mode', '0');
    $is_admin_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
    $is_brand_admin_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'brand_admin');


    $is_admin_area_script = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
    $is_brand_admin_area_script = (strpos($_SERVER['PHP_SELF'], '/brand_admin/') !== false);
    $is_maintenance_script = (basename($_SERVER['PHP_SELF']) === 'maintenance.php');

    // If maintenance mode is enabled and user is not an admin or on the maintenance page itself, redirect.
    if ($maintenance_mode_enabled === '1' && !$is_admin_logged_in && !$is_brand_admin_logged_in && !$is_admin_area_script && !$is_brand_admin_area_script && !$is_maintenance_script) {
        $maintenance_page_url = rtrim(SITE_URL, '/') . '/maintenance.php';

        // Prevent infinite redirects if already on the maintenance page
        if (strpos($_SERVER['REQUEST_URI'], 'maintenance.php') === false) {
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            header('Status: 503 Service Temporarily Unavailable');
            header('Location: ' . $maintenance_page_url);
            exit();
        }
    }
}


// --- Other Application-Wide Constants ---
if (!defined('MAX_IMAGE_SIZE')) define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB Max image upload size
if (!defined('PLACEHOLDER_IMAGE_URL_GENERATOR')) define('PLACEHOLDER_IMAGE_URL_GENERATOR', 'https://placehold.co/');

// --- Helper Functions ---
// Ensure this function is available globally for HTML escaping.
if (!function_exists('esc_html')) {
    function esc_html($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Global helper function to get asset URLs, including CSS, JS, images, and general links.
// This replaces get_site_url_for_link from footer.php and get_asset_url from header.php.
if (!function_exists('get_asset_url')) {
    function get_asset_url($path = '') {
        $base_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        // Ensure that if path starts with a slash, it's treated as relative to SITE_URL's public directory.
        // If it's a full URL, return it as is.
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        return $base_url . '/' . ltrim($path, '/');
    }
}