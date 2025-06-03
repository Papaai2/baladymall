<?php
// public/maintenance.php

// Attempt to load minimal configuration to get DB connection for the message
// This is a bit tricky as we don't want to load the full app that might redirect back here.
// For simplicity, we can try to include a very stripped-down config or directly connect.
// Or, the message can be hardcoded here if dynamic message is too complex for this simple page.

// Let's try to fetch the dynamic message if possible.
// This assumes config.php can be included partially or a helper function is available.

$db_host = 'localhost'; // Replace with your DB_HOST if defined elsewhere
$db_name = 'baladymall_db'; // Replace with your DB_NAME
$db_user = 'root';      // Replace with your DB_USER
$db_pass = '';          // Replace with your DB_PASS

$maintenance_message_from_db = 'Our site is currently undergoing scheduled maintenance. We should be back shortly. Thank you for your patience.'; // Default
$site_name_from_db = 'Our Website'; // Default

try {
    // Minimal PDO connection
    $pdo_maintenance = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo_maintenance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_maintenance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt_msg = $pdo_maintenance->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_message'");
    $stmt_msg->execute();
    $msg_row = $stmt_msg->fetch();
    if ($msg_row && !empty($msg_row['setting_value'])) {
        $maintenance_message_from_db = $msg_row['setting_value'];
    }

    $stmt_name = $pdo_maintenance->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
    $stmt_name->execute();
    $name_row = $stmt_name->fetch();
    if ($name_row && !empty($name_row['setting_value'])) {
        $site_name_from_db = $name_row['setting_value'];
    }

} catch (PDOException $e) {
    // Log error, use default message
    error_log("Maintenance Page DB Error: " . $e->getMessage());
}

header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
// header('Retry-After: 3600'); // Optional
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name_from_db); ?> - Under Maintenance</title>
    <style>
        body { text-align: center; padding: 50px; font-family: Arial, sans-serif; background-color: #f8f9fa; color: #343a40; margin:0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { font-size: 2.5em; color: #007bff; margin-bottom: 0.5em; }
        p { font-size: 1.1em; line-height: 1.6; color: #495057; }
        .logo-placeholder { font-size: 3em; font-weight: bold; color: #007bff; margin-bottom: 20px; } /* Basic text logo */
    </style>
</head>
<body>
    <div class="container">
        <!-- You can try to display a logo here if you have a fixed path or fetch it -->
        <!-- <img src="/path/to/your/logo.png" alt="Site Logo" style="max-width: 200px; margin-bottom: 20px;"> -->
        <div class="logo-placeholder"><?php echo htmlspecialchars($site_name_from_db); ?></div>
        <h1>Under Maintenance</h1>
        <p><?php echo nl2br(htmlspecialchars($maintenance_message_from_db)); ?></p>
        <p>We apologize for any inconvenience.</p>
    </div>
</body>
</html>
