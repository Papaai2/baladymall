<?php
// public/contact.php

$page_title = "Contact Us - BaladyMall";

// Configuration, Header, and Footer paths
$config_path_from_public = __DIR__ . '/../src/config/config.php'; // Path to config from current file

// Ensure config.php is loaded first
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    $alt_config_path = dirname(__DIR__) . '/src/config/config.php';
    if (file_exists($alt_config_path)) {
        require_once $alt_config_path;
    } else {
        die("Critical error: Main configuration file not found. Please check paths.");
    }
}

// Define header and footer paths using PROJECT_ROOT_PATH for robustness if available.
$header_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/header.php' : __DIR__ . '/../src/includes/header.php';
$footer_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/footer.php' : __DIR__ . '/../src/includes/footer.php';

// Header includes session_start()
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

// Variables for contact information (can be fetched from DB/config or hardcoded)
$contact_email = defined('PUBLIC_CONTACT_EMAIL') ? PUBLIC_CONTACT_EMAIL : 'info@baladymall.com';
$contact_phone = defined('PUBLIC_CONTACT_PHONE') ? PUBLIC_CONTACT_PHONE : '+20 123 456 7890';

// Form handling logic removed as per request

?>

<section class="info-page-section contact-page-section" style="padding: 30px 0;">
    <div class="container">
        <h1 class="section-title text-center mb-4" style="font-size: 2.5em; color: #343a40;"><?php echo esc_html($page_title); ?></h1>

        <div class="page-content" style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="contact-layout" style="max-width: 600px; margin: 0 auto;">

                <div class="contact-details">
                    <h2 style="font-size: 1.8em; color: #007bff; margin-bottom: 20px; text-align:center;">Get In Touch</h2>
                    <p style="margin-bottom: 20px; line-height: 1.7; text-align:center;">
                        We'd love to hear from you! Whether you have a question about our products, your order, or just want to say hello, feel free to reach out through any of the methods below.
                    </p>

                    <div class="contact-info-item" style="margin-bottom: 15px; text-align: center;">
                        <h4 style="font-size: 1.2em; color: #343a40; margin-bottom: 5px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-envelope-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 8px; color: #007bff;">
                              <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586zm3.436-.586L16 11.801V4.697z"/>
                            </svg>
                            Email Us:
                        </h4>
                        <p><a href="mailto:<?php echo esc_html($contact_email); ?>"><?php echo esc_html($contact_email); ?></a></p>
                    </div>

                    <div class="contact-info-item" style="margin-bottom: 15px; text-align: center;">
                        <h4 style="font-size: 1.2em; color: #343a40; margin-bottom: 5px;">
                             <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-telephone-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 8px; color: #007bff;">
                               <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                             </svg>
                            Call Us:
                        </h4>
                        <p><a href="tel:<?php echo esc_html(str_replace(' ', '', $contact_phone)); ?>"><?php echo esc_html($contact_phone); ?></a></p>
                    </div>
                </div>

                <?php /* Contact form container and form removed
                <div class="contact-form-container">
                    ...
                </div>
                */ ?>
            </div>
        </div>
    </div>
</section>

<?php
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>