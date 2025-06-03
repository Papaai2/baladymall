<?php
// src/config/config.php

// --- Error Reporting (Development vs Production) ---
ini_set('display_errors', 1); // Set to 0 in production for live site
ini_set('display_startup_errors', 1); // Set to 0 in production for live site
error_reporting(E_ALL); // Report all PHP errors

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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// **CRITICAL FIX: Robust PROJECT_ROOT_PATH definition**
// This reliably points to the 'baladymall' root directory.
// __FILE__ is the full path to the current file (config.php).
// dirname(__FILE__) is 'baladymall/src/config'
// dirname(dirname(__FILE__)) is 'baladymall/src'
// dirname(dirname(dirname(__FILE__))) is 'baladymall' (the project root)
if (!defined('PROJECT_ROOT_PATH')) {
    define('PROJECT_ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}

// Define SITE_URL to point to the public folder, accessible via the web
if (!defined('SITE_URL')) {
    $document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']); // Normalize slashes
    $project_root_normalized = str_replace('\\', '/', PROJECT_ROOT_PATH);

    $project_web_path_segment = '';
    // Check if project root is directly under document root
    if (strpos($project_root_normalized, $document_root) === 0) {
        $project_web_path_segment = substr($project_root_normalized, strlen($document_root));
    } else {
        // Fallback: Attempt to guess the project's web path segment from SCRIPT_NAME
        // This is for setups where the project folder is not directly under DOCUMENT_ROOT
        // e.g., if DOCUMENT_ROOT is /var/www/html and project is /var/www/html/myproject
        // or if DOCUMENT_ROOT is /var/www/html/myproject/public
        $script_name_parts = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
        $found_project_segment = '';
        foreach ($script_name_parts as $segment) {
            // Find the first segment that is not 'public', 'admin', or 'brand_admin'
            if (!in_array($segment, ['public', 'admin', 'brand_admin']) && !empty($segment)) {
                $found_project_segment = '/' . $segment;
                break;
            }
        }
        $project_web_path_segment = $found_project_segment;
    }
    // Ensure leading slash and no trailing slash for the segment
    $project_web_path_segment = '/' . trim($project_web_path_segment, '/');
    if ($project_web_path_segment === '/') $project_web_path_segment = ''; // If it resolves to just '/', make it empty

    define('SITE_URL', $protocol . $host . $project_web_path_segment . '/public');
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

    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', get_site_setting($_settings_db_conn, 'site_logo_url', ''));
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', get_site_setting($_settings_db_conn, 'favicon_url', ''));

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
}

// --- Email Sending Function (Simulated for local, PHPMailer for production) ---
if (!function_exists('send_email')) {
    function send_email($to_email, $subject, $body, $alt_body = '', $is_html = false) {
        // For local development, just log the email content to the PHP error log.
        error_log("--- EMAIL SENT (Simulated) ---\nTo: {$to_email}\nSubject: {$subject}\nBody:\n{$body}\n--- END EMAIL ---\n");
        return true; // Always return true for simulation

        /*
        // --- PRODUCTION READY PHPMailer INTEGRATION EXAMPLE (Uncomment for live) ---
        // You would need to ensure PHPMailer is installed (e.g., via Composer)
        // and its autoloader is included in your main application bootstrap.
        // require_once PROJECT_ROOT_PATH . '/vendor/autoload.php'; // If using Composer
        // OR manually include:
        // require_once PROJECT_ROOT_PATH . '/src/lib/PHPMailer/src/PHPMailer.php';
        // require_once PROJECT_ROOT_PATH . '/src/lib/PHPMailer/src/SMTP.php';
        // require_once PROJECT_ROOT_PATH . '/src/lib/PHPMailer/src/Exception.php';

        // use PHPMailer\PHPMailer\PHPMailer;
        // use PHPMailer\PHPMailer\Exception;
        // use PHPMailer\PHPMailer\SMTP; // Needed for SMTP::DEBUG_SERVER etc.

        // $mail = new PHPMailer(true); // Enable exceptions for error handling

        // try {
        //     // Server settings
        //     $mail->isSMTP();
        //     $mail->Host       = SMTP_HOST;
        //     $mail->SMTPAuth   = true;
        //     $mail->Username   = SMTP_USERNAME;
        //     $mail->Password   = SMTP_PASSWORD;
        //     // Use PHPMailer's constants for encryption
        //     if (SMTP_ENCRYPTION === 'ssl') {
        //         $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        //     } elseif (SMTP_ENCRYPTION === 'tls') {
        //         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        //     } else {
        //         $mail->SMTPSecure = false; // No encryption
        //     }
        //     $mail->Port       = SMTP_PORT;
        //     $mail->CharSet    = 'UTF-8';
        //     // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment for verbose SMTP debugging

        //     // Recipients
        //     $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        //     $mail->addAddress($to_email);

        //     // Content
        //     $mail->isHTML($is_html); // Set email format to HTML
        //     $mail->Subject = $subject;
        //     $mail->Body    = $body;
        //     if ($alt_body) {
        //         $mail->AltBody = $alt_body;
        //     } else {
        //         // Generate plain text from HTML if alt_body is not provided
        //         $mail->AltBody = strip_tags($body);
        //     }

        //     $mail->send();
        //     return true;
        // } catch (Exception $e) {
        //     error_log("Email sending failed to {$to_email}. Mailer Error: {$mail->ErrorInfo}");
        //     return false;
        // }
        */
    }
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
