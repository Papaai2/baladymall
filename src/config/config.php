<?php
// src/config/config.php

// Manually include PHPMailer files since Composer's autoloader isn't active or available.
// These paths are relative to config.php (which is in src/config/).
// So, to get to /src/vendor/PHPMailer/, we go up one directory (to src/) then into vendor/PHPMailer/.
require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

// Now, use the classes directly without the full namespace path in includes
// (though it's good practice to keep them for clarity with Composer).
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// --- Error Reporting & Handling ---
ini_set('display_errors', 0); // Disable display of errors for production. Errors are logged.
ini_set('display_startup_errors', 0); // Disable display of startup errors.
error_reporting(E_ALL); // Report all types of errors.

/**
 * Returns a default AJAX error response array.
 * @return array
 */
function get_default_ajax_error_response() {
    return ['success' => false, 'message' => 'An unexpected server error occurred.', 'type' => 'error'];
}

/**
 * Custom error handler to convert errors into ErrorExceptions.
 * This allows set_exception_handler to catch all errors.
 * @param int $severity The level of the error raised.
 * @param string $message The error message.
 * @param string $file The filename the error was raised in.
 * @param int $line The line number the error was raised at.
 * @return bool
 * @throws ErrorException
 */
function custom_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    // Convert error to an ErrorException to be caught by custom_exception_handler
    throw new ErrorException($message, 0, $severity, $file, $line);
}

/**
 * Custom exception handler for all uncaught exceptions (including errors converted by custom_error_handler).
 * Provides a user-friendly message for non-AJAX requests and JSON for AJAX requests.
 * Detailed errors are logged.
 * @param Throwable $exception The uncaught exception or error.
 */
function custom_exception_handler(Throwable $exception) {
    // Log the detailed exception information
    error_log(sprintf("Uncaught Exception: %s in %s:%d\nStack trace:\n%s", $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()));

    // Determine if the request is AJAX
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($is_ajax) {
        // For AJAX requests, send a JSON error response
        http_response_code(500); // Internal Server Error
        header('Content-Type: application/json');
        @ob_clean(); // Clean any output buffer to prevent malformed JSON
        echo json_encode(get_default_ajax_error_response());
    } else {
        // For standard (non-AJAX) requests, redirect to the custom 500 error page
        // IMPORTANT: Ensure headers have not been sent yet.
        if (!headers_sent()) {
            http_response_code(500); // Set HTTP status code
            // SITE_URL should be defined by config.php, leading to public/
            $error_page_url = rtrim(SITE_URL, '/') . '/error/500.php';
            header("Location: " . $error_page_url);
            exit(); // Essential to stop script execution after redirect
        } else {
            // Fallback if headers already sent (e.g., some prior PHP output occurred)
            // This is less ideal but prevents a blank screen or broken layout.
            echo "<h1>An unexpected server error occurred.</h1><p>We're sorry, but something went wrong. Please try again later. (Error ID: " . uniqid() . ")</p>";
        }
    }
    exit(); // Terminate script execution
}

// Set the custom error and exception handlers
set_error_handler("custom_error_handler");
set_exception_handler("custom_exception_handler");

// --- Session Management ---
// Check if session has not been started yet
if (session_status() == PHP_SESSION_NONE) {
    // Define a custom session name for security and clarity
    if (!defined('SESSION_NAME')) define('SESSION_NAME', 'BaladyMallSession');
    session_name(SESSION_NAME);

    // Configure session cookie parameters for security
    // 'lifetime' => 0 means the cookie expires when the browser closes
    // 'path' => '/' means the cookie is available across the entire domain
    // 'domain' => '.wuaze.com' is crucial for subdomain session persistence (e.g., baladymall.wuaze.com)
    // 'secure' => true ensures the cookie is only sent over HTTPS
    // 'httponly' => true prevents JavaScript access to the cookie
    // 'samesite' => 'Lax' helps mitigate CSRF attacks
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.wuaze.com', // **FIXED**: Set to parent domain for subdomain session persistence
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Start the session
    // Log a critical error if session_start fails
    if (!session_start()) {
        error_log("CRITICAL ERROR: session_start() failed in config.php.");
    }
}

// --- Path Definitions ---
// Define project root path. Assumes config.php is in src/config, and project root is two levels up.
if (!defined('PROJECT_ROOT_PATH')) define('PROJECT_ROOT_PATH', dirname(dirname(__DIR__)));

// Define base URL for the project. **FIXED**: Set to your actual subdomain for consistency.
if (!defined('PROJECT_BASE_URL')) define('PROJECT_BASE_URL', 'https://baladymall.wuaze.com/');

// Define site URL, which is the public-facing root for assets.
// Assumes public/ is a subfolder of the web-accessible root.
if (!defined('SITE_URL')) define('SITE_URL', rtrim(PROJECT_BASE_URL, '/') . '/public');

// Define administrative panel URLs. These should always be absolute URLs.
// Removed /index.php for cleaner root access, which will be handled by .htaccess or default server behavior
if (!defined('ADMIN_ROOT_URL')) define('ADMIN_ROOT_URL', rtrim(PROJECT_BASE_URL, '/') . '/admin');
if (!defined('BRAND_ADMIN_ROOT_URL')) define('BRAND_ADMIN_ROOT_URL', rtrim(PROJECT_BASE_URL, '/') . '/brand_admin');

// Define physical paths on the server
if (!defined('PUBLIC_ROOT_PATH')) define('PUBLIC_ROOT_PATH', PROJECT_ROOT_PATH . '/public');
if (!defined('PUBLIC_UPLOADS_PATH')) define('PUBLIC_UPLOADS_PATH', PUBLIC_ROOT_PATH . '/uploads/');
// Define URL for accessing uploaded files
if (!defined('PUBLIC_UPLOADS_URL_BASE')) define('PUBLIC_UPLOADS_URL_BASE', rtrim(SITE_URL, '/') . '/uploads/');

// --- Database Configuration ---
if (!defined('DB_HOST')) define('DB_HOST', 'sql112.infinityfree.com');
if (!defined('DB_NAME')) define('DB_NAME', 'if0_39200559_baladymall');
if (!defined('DB_USER')) define('DB_USER', 'if0_39200559');
if (!defined('DB_PASS')) define('DB_PASS', '0ytCfXdQw8Owm');

// Global currency symbol (can be made dynamic from site settings if needed)
$GLOBALS['currency_symbol'] = 'EGP';

/**
 * Provides a singleton PDO database connection.
 * @return PDO
 * @throws PDOException If the database connection fails.
 */
if (!function_exists('getPDOConnection')) {
    function getPDOConnection() {
        static $pdo_instance = null; // Static variable to hold the single PDO instance
        if ($pdo_instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Default fetch mode to associative arrays
                PDO::ATTR_EMULATE_PREPARES => false                 // Disable emulation for real prepared statements
            ];
            try {
                $pdo_instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log the database connection error
                error_log("Database Connection Error: " . $e->getMessage());
                // Re-throw the exception to be caught by the global exception handler or calling script
                throw $e;
            }
        }
        return $pdo_instance;
    }
}
// Removed `$db = null;` as per sync point #6. Individual pages will now call `getPDOConnection();` to set `$db`.

/**
 * Fetches a site setting from the database.
 * @param PDO $db_conn_param The PDO database connection object.
 * @param string $key The setting key to retrieve.
 * @param mixed $default_value The default value to return if the setting is not found or DB connection fails.
 * @return mixed The setting value or the default value.
 */
if (!function_exists('get_site_setting')) {
    function get_site_setting($db_conn_param, $key, $default_value = null) {
        // Return default if DB connection is not valid
        if (!$db_conn_param instanceof PDO) {
            return $default_value;
        }
        try {
            $stmt = $db_conn_param->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // Return the value if found, otherwise the default
            return $result ? $result['setting_value'] : $default_value;
        } catch (PDOException $e) {
            // Log the error but return default to prevent site breakdown
            error_log("Error fetching site setting '{$key}': " . $e->getMessage());
            return $default_value;
        }
    }
}

// Attempt to get a database connection specifically for site settings,
// as these are often needed very early in the script execution.
$_settings_db_conn = null;
try {
    $_settings_db_conn = getPDOConnection();
} catch (Exception $e) {
    // Log the error if settings DB connection fails
    error_log("CONFIG.PHP: DB connection for settings failed: " . $e->getMessage());
}

// Define site settings from the database, or use fallbacks if DB connection failed
if ($_settings_db_conn instanceof PDO) {
    if (!defined('SITE_NAME')) define('SITE_NAME', get_site_setting($_settings_db_conn, 'site_name', 'BaladyMall'));
    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', get_site_setting($_settings_db_conn, 'site_logo_url', 'site/default_logo.jpg'));
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', get_site_setting($_settings_db_conn, 'favicon_url', 'favicon.ico'));
    if (!defined('SMTP_HOST')) define('SMTP_HOST', get_site_setting($_settings_db_conn, 'smtp_host', 'smtp.mailersend.net'));
    if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)get_site_setting($_settings_db_conn, 'smtp_port', 587));
    if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', get_site_setting($_settings_db_conn, 'smtp_username', 'MS_cpcknr@baladymall.shop'));
    if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', get_site_setting($_settings_db_conn, 'smtp_password', 'mssp.WYEcBt0.neqvygm7eydg0p7w.R6KoBKk'));
    // New: Social media URLs from settings for consistency in footer (sync point #8)
    if (!defined('FACEBOOK_URL')) define('FACEBOOK_URL', get_site_setting($_settings_db_conn, 'facebook_url', '#'));
    if (!defined('INSTAGRAM_URL')) define('INSTAGRAM_URL', get_site_setting($_settings_db_conn, 'instagram_url', '#'));
} else {
    // Fallback constants if DB connection for settings failed
    if (!defined('SITE_NAME')) define('SITE_NAME', 'BaladyMall (DB Error)');
    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', 'site/default_logo.jpg');
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', 'favicon.ico');
    if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.mailersend.net');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
    if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'MS_cpcknr@baladymall.shop');
    if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'mssp.WYEcBt0.neqvygm7eydg0p7w.R6KoBKk');
    // New: Social media URL fallbacks (sync point #8)
    if (!defined('FACEBOOK_URL')) define('FACEBOOK_URL', '#');
    if (!defined('INSTAGRAM_URL')) define('INSTAGRAM_URL', '#');
}

// --- Maintenance Mode Check ---
if ($_settings_db_conn instanceof PDO) {
    $maintenance_mode_enabled = get_site_setting($_settings_db_conn, 'maintenance_mode', '0');
    // Redirect to maintenance page if enabled, and user is not logged in, and not already on maintenance/login/admin page
    if ($maintenance_mode_enabled === '1' && empty($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'maintenance.php' && basename($_SERVER['PHP_SELF']) !== 'login.php' && strpos($_SERVER['PHP_SELF'], '/admin/') === false && strpos($_SERVER['PHP_SELF'], '/brand_admin/') === false) {
        header('Location: ' . rtrim(SITE_URL, '/') . '/maintenance.php');
        exit();
    }
}

// --- General Constants & Utility Functions ---
// Max image upload size in bytes (5 MB)
if (!defined('MAX_IMAGE_SIZE')) define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
// URL for generating placeholder images (useful for development/missing images)
if (!defined('PLACEHOLDER_IMAGE_URL_GENERATOR')) define('PLACEHOLDER_IMAGE_URL_GENERATOR', 'https://placehold.co/');

/**
 * Escapes HTML entities in a string for safe output.
 * @param string|null $string The string to escape.
 * @return string The escaped string.
 */
if (!function_exists('esc_html')) {
    function esc_html($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generates a full URL for a given asset path within the public directory.
 * @param string $path The path to the asset relative to the public directory (e.g., 'css/style.css').
 * @return string The full URL to the asset.
 */
if (!function_exists('get_asset_url')) {
    function get_asset_url($path = '') {
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
}

/**
 * Sends an email using PHPMailer.
 * @param string $to The recipient email address.
 * @param string $subject The email subject.
 * @param string $body The HTML body of the email.
 * @param string $altBody (Optional) The plain-text alternative body.
 * @param bool $isHtml (Optional) True if the body is HTML, false otherwise. Defaults to true.
 * @return bool True on successful sending (doesn't guarantee delivery), false on failure.
 */
if (!function_exists('send_email')) {
    function send_email($to, $subject, $body, $altBody = '', $isHtml = true) {
        // Create a new PHPMailer instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            // --- Server Settings ---
            // Enable verbose debug output (0 for production, 2 for client and server messages)
            $mail->SMTPDebug = 0; // Set to 0 for production to disable debug output
            // For debugging, use: $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            $mail->isSMTP();                           // Send using SMTP
            $mail->Host       = SMTP_HOST;             // Set the SMTP server to send through (e.g., smtp.mailersend.net)
            $mail->SMTPAuth   = true;                  // Enable SMTP authentication
            $mail->Username   = SMTP_USERNAME;         // SMTP username (from site_settings table)
            $mail->Password   = SMTP_PASSWORD;         // SMTP password (from site_settings table)
            // Use PHPMailer::ENCRYPTION_STARTTLS for port 587, PHPMailer::ENCRYPTION_SMTPS for port 465 (SSL)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
            $mail->Port       = SMTP_PORT;             // TCP port to connect to (587 for STARTTLS)

            // --- Recipients ---
            // Set the sender email address and name (from site_settings table)
            $mail->setFrom(SMTP_USERNAME, SITE_NAME);
            $mail->addAddress($to);                    // Add a recipient

            // --- Content ---
            $mail->isHTML($isHtml);                       // Set email format to HTML
            $mail->Subject = $subject;                    // Email subject
            $mail->Body    = $body;                       // HTML body of the email
            if (!empty($altBody)) {
                $mail->AltBody = $altBody;                // Optional: plain-text alternative for non-HTML mail clients
            }

            $mail->send(); // Attempt to send the email
            return true;   // Return true on success
        } catch (PHPMailerException $e) {
            // Log PHPMailer-specific errors
            error_log("Email could not be sent to {$to}. Mailer Error: {$e->getMessage()}");
            // For deeper debugging, uncomment the next line:
            // error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
            return false; // Return false on failure
        } catch (Exception $e) {
            // Catch any other general exceptions
            error_log("Email sending failed to {$to}. General Error: {$e->getMessage()}");
            return false; // Return false on general failure
        }
    }
}
?>