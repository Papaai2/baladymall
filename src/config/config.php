<?php
// src/config/config.php

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for BaladyMall */
define('DB_NAME', 'baladymall_db'); // As defined in your SQL setup

/** MySQL database username */
define('DB_USER', 'root'); // Default XAMPP username

/** MySQL database password */
define('DB_PASSWORD', ''); // Default XAMPP password (usually empty)

/** MySQL hostname */
define('DB_HOST', 'localhost'); // Default XAMPP host

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

// ** Site Settings ** //
/** Site URL - Update this when you go live */
define('SITE_URL', 'http://localhost/baladymall/public'); // Adjust if your XAMPP path is different

/** Site Name */
define('SITE_NAME', 'BaladyMall');

/** Default Currency */
define('CURRENCY_CODE', 'EGP');
define('CURRENCY_SYMBOL', 'E£'); // Or just EGP

/** Uploads Directory (relative to the public folder) */
define('UPLOADS_DIR_PRODUCTS', 'uploads/products/');
define('UPLOADS_DIR_BRANDS', 'uploads/brands/');
define('UPLOADS_DIR_CATEGORIES', 'uploads/categories/'); // If you have category images

/** Paths - useful for includes and requires */
// Assuming config.php is in src/config/
// __DIR__ in this file is /path/to/baladymall/src/config
define('ROOT_PATH', dirname(dirname(__DIR__))); // Should point to /path/to/baladymall/
define('SRC_PATH', dirname(__DIR__));          // Should point to /path/to/baladymall/src/
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ADMIN_PATH', ROOT_PATH . '/admin'); // Or PUBLIC_PATH . '/admin' if it's web accessible directly
define('BRAND_ADMIN_PATH', ROOT_PATH . '/brand_admin'); // Or PUBLIC_PATH . '/brand_admin'

/** Error Reporting (for development vs. production) */
// For development:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// For production, you'd typically set display_errors to 0 and log errors instead.

/** Session Settings */
define('SESSION_NAME', 'BaladyMallSession');
// Consider adding more session security settings here later (e.g., httponly, secure for HTTPS)

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
// define('WP_DEBUG', false); // We are not using WordPress, but a similar concept for debug mode can be useful
define('DEBUG_MODE', true); // Custom debug flag

/* That's all, stop editing! Happy publishing. */

/**
 * Function to establish a database connection using PDO.
 * This can be moved to a separate database.php file later if preferred.
 *
 * @return PDO|null Returns a PDO connection object or null on failure.
 */
function getPDOConnection() {
    static $pdo = null; // Static variable to hold the connection

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // For a real site, you would log this error and show a generic error message.
            // For development, it's okay to die and show the error.
            error_log("Database Connection Error: " . $e->getMessage());
            // In a real application, you might throw the exception or return null
            // and handle it gracefully in the calling code.
            // For now, let's keep it simple for localhost development.
            die("Database connection failed: " . $e->getMessage() . "<br/>Please check your database credentials in src/config/config.php and ensure your MySQL server is running and the database 'baladymall_db' exists.");
        }
    }
    return $pdo;
}

// Example of how to get the connection:
// $db = getPDOConnection();
// if ($db) {
//     // Connection successful
// } else {
//     // Connection failed
// }

?>
