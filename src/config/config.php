<?php
// src/config/config.php

// --- Error Reporting (Development vs Production) ---
// For development, show all errors. For production, log errors and show a generic message.
ini_set('display_errors', 1); // Set to 0 in production
ini_set('display_startup_errors', 1); // Set to 0 in production
error_reporting(E_ALL); // In production, consider E_ALL & ~E_DEPRECATED & ~E_STRICT

// --- Session Management ---
if (session_status() == PHP_SESSION_NONE) {
    // Consider setting session cookie parameters for security if not using defaults
    // session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '.yourdomain.com', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (defined('SESSION_NAME') && !headers_sent()) {
        session_name(SESSION_NAME); // If you define a custom session name
    }
    if(!headers_sent()) {
        session_start();
    } else {
        error_log("CONFIG.PHP: Session could not be started because headers were already sent.");
        // Potentially die or redirect to an error page if session is critical here
    }
}


// --- Site URL and Paths ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback for CLI or misconfigured server

// Define project root path on the server's filesystem
// __DIR__ is the directory of the current file (src/config)
if (!defined('PROJECT_ROOT_PATH')) define('PROJECT_ROOT_PATH', dirname(dirname(__DIR__))); // This should give /path/to/baladymall

// Define SITE_URL to point to the public folder, accessible via the web
if (!defined('SITE_URL')) {
    // Calculate the web path from the document root to the project root
    // This assumes $_SERVER['DOCUMENT_ROOT'] is set correctly and PROJECT_ROOT_PATH is under it.
    // And that the project itself (e.g., 'baladymall') is a subdirectory in the web server's doc root,
    // or the project root IS the doc root.

    $document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']); // Normalize slashes
    $project_root_normalized = str_replace('\\', '/', PROJECT_ROOT_PATH);

    if (strpos($project_root_normalized, $document_root) === 0) {
        // Project is under document root
        $project_web_path_segment = substr($project_root_normalized, strlen($document_root));
    } else {
        // Fallback or different logic if project is not directly under document root
        // This might happen with aliases or complex server configs.
        // For a typical XAMPP/MAMP setup where baladymall is in htdocs:
        // SCRIPT_NAME for /baladymall/public/index.php is /baladymall/public/index.php
        // We want the base /baladymall part.
        $script_name_parts = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
        // Assuming the first segment is the project folder name if not at webroot
        $project_folder_name_from_script = $script_name_parts[0] ?? ''; 
        // This is a guess; adjust if your project is deeper or at the root.
        // If your project 'baladymall' is directly in htdocs, and htdocs is web root,
        // $project_web_path_segment should be '/baladymall'
        // A common pattern is to manually set a BASE_WEB_PATH constant if dynamic detection is too complex.
        // For now, let's assume a simple structure:
        if ($project_folder_name_from_script && $project_folder_name_from_script !== 'public' && $project_folder_name_from_script !== 'admin') {
             $project_web_path_segment = '/' . $project_folder_name_from_script;
        } else {
            // If public is the root, or script is not in a clear project subfolder
            $project_web_path_segment = ''; // Assuming project root is web root
        }
    }
    // Ensure project_web_path_segment starts with a slash if it's not empty, and remove trailing slash
    $project_web_path_segment = '/' . trim($project_web_path_segment, '/');
    if ($project_web_path_segment === '/') $project_web_path_segment = ''; // Avoid double slash if root

    define('SITE_URL', $protocol . $host . $project_web_path_segment . '/public');
}


// Define public root path (where index.php, css, js for public site are)
if (!defined('PUBLIC_ROOT_PATH')) define('PUBLIC_ROOT_PATH', PROJECT_ROOT_PATH . '/public');

// Define path to the public uploads directory
if (!defined('PUBLIC_UPLOADS_PATH')) define('PUBLIC_UPLOADS_PATH', PUBLIC_ROOT_PATH . '/uploads/');
// Define the web-accessible base URL for uploads
if (!defined('PUBLIC_UPLOADS_URL_BASE')) define('PUBLIC_UPLOADS_URL_BASE', rtrim(SITE_URL, '/') . '/uploads/');


// --- Database Credentials ---
// Best practice: Store these in environment variables or a non-web-accessible config file.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'baladymall_db');
if (!defined('DB_USER')) define('DB_USER', 'root'); // Replace with your DB username
if (!defined('DB_PASS')) define('DB_PASS', '');     // Replace with your DB password

// --- PDO Database Connection Function ---
if (!function_exists('getPDOConnection')) {
    function getPDOConnection() {
        // Static variable to store the connection an ensure it's created only once per request.
        static $pdo_instance = null;
        if ($pdo_instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Good for security with modern MySQL
            ];
            try {
                $pdo_instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                // In production, show a generic error page instead of die()
                die("Database connection failed. Please check server logs. (" . $e->getCode() . ")");
            }
        }
        return $pdo_instance;
    }
}
// Initialize $db connection globally for scripts that might expect it.
$db = getPDOConnection();


// --- Function to get a single setting (can be moved to a helper file later) ---
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

// --- Load Core Site Settings from Database into Constants (or a global settings array) ---
if (isset($db) && $db instanceof PDO) {
    if (!defined('SITE_NAME')) define('SITE_NAME', get_site_setting($db, 'site_name', 'BaladyMall'));
    if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', get_site_setting($db, 'admin_email', 'admin@example.com'));
    if (!defined('PUBLIC_CONTACT_EMAIL')) define('PUBLIC_CONTACT_EMAIL', get_site_setting($db, 'public_contact_email', 'support@example.com'));
    if (!defined('PUBLIC_CONTACT_PHONE')) define('PUBLIC_CONTACT_PHONE', get_site_setting($db, 'public_contact_phone', ''));
    
    if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', get_site_setting($db, 'default_currency_symbol', 'EGP '));
    if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', get_site_setting($db, 'default_currency_code', 'EGP'));
    
    if (!defined('PLATFORM_COMMISSION_RATE')) define('PLATFORM_COMMISSION_RATE', (float)get_site_setting($db, 'platform_commission_rate', '10.00'));

    if (!defined('SITE_LOGO_PATH')) define('SITE_LOGO_PATH', get_site_setting($db, 'site_logo_url', ''));
    if (!defined('FAVICON_PATH')) define('FAVICON_PATH', get_site_setting($db, 'favicon_url', ''));

} else {
    if (!defined('SITE_NAME')) define('SITE_NAME', 'BaladyMall (DB Error)');
    if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'EGP ');
    error_log("CONFIG.PHP: Database connection not available when trying to load site settings.");
}


// --- Maintenance Mode Check ---
if (isset($db) && $db instanceof PDO) {
    $maintenance_mode_enabled = get_site_setting($db, 'maintenance_mode', '0');
    $is_admin_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
    
    $is_admin_area_script = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
    $is_maintenance_script = (basename($_SERVER['PHP_SELF']) === 'maintenance.php');

    if ($maintenance_mode_enabled === '1' && !$is_admin_logged_in && !$is_admin_area_script && !$is_maintenance_script) {
        // SITE_URL should now correctly point to the public directory.
        $maintenance_page_url = rtrim(SITE_URL, '/') . '/maintenance.php'; 
        
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

// --- Helper Functions (Consider moving to a separate helpers.php file and including it) ---
if (!function_exists('esc_html')) {
    function esc_html($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>
