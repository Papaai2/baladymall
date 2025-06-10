<?php
// src/includes/display_admin_messages.php
// This file is designed to be included in admin/includes/header.php or brand_admin/includes/header.php
// to display session-based messages (flash messages).

// Ensure session is started (should be from config.php)
if (session_status() == PHP_SESSION_NONE) {
    // Fallback or critical error if session isn't active
    error_log("Error: display_admin_messages.php included without active session.");
    return; // Don't proceed
}

// Determine which session key and CSS class prefix to use based on the user's role
$message_session_key = 'admin_message'; // Default for super_admin
$message_class_prefix = 'admin-message'; // Default for super_admin

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'brand_admin') {
        $message_session_key = 'brand_admin_message';
        $message_class_prefix = 'brand-admin-message';
    } elseif ($_SESSION['role'] === 'super_admin') {
        $message_session_key = 'admin_message';
        $message_class_prefix = 'admin-message';
    }
}

// Display the message if it exists in the session
if (isset($_SESSION[$message_session_key]) && !empty($_SESSION[$message_session_key])) {
    $message_content = $_SESSION[$message_session_key]; // The message already contains HTML for the div and class
    
    // Determine the type of message for the outer container if desired (e.g., for JS targeting)
    $message_type = 'info'; // Default type
    if (strpos($message_content, 'success') !== false) {
        $message_type = 'success';
    } elseif (strpos($message_content, 'error') !== false) {
        $message_type = 'error';
    } elseif (strpos($message_content, 'warning') !== false) {
        $message_type = 'warning';
    }

    // Wrap the message in a generic container for consistent placement and potential JS handling
    // The actual CSS class (e.g., 'admin-message success') is already inside $message_content HTML.
    echo '<div class="' . $message_class_prefix . '-container">';
    echo $message_content; // Echo the full HTML string stored in the session
    echo '</div>';

    // Clear the message after displaying it
    unset($_SESSION[$message_session_key]);
}
?>