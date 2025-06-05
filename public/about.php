<?php
// public/about.php

$page_title = "About Us - BaladyMall";

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

?>

<section class="info-page-section" style="padding: 30px 0;">
    <div class="container">
        <h1 class="section-title text-center mb-4" style="font-size: 2.5em; color: #343a40;"><?php echo esc_html($page_title); ?></h1>

        <div class="page-content" style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="font-size: 1.8em; color: #007bff; margin-bottom: 15px;">Our Story</h2>
            <p style="margin-bottom: 15px; line-height: 1.7;">
                Welcome to BaladyMall, your premier destination for authentic Egyptian products. We are passionate about showcasing the rich heritage, craftsmanship, and entrepreneurial spirit of Egypt. Our mission is to connect local artisans, creators, and businesses with a wider audience, both locally and internationally.
            </p>
            <p style="margin-bottom: 15px; line-height: 1.7;">
                BaladyMall was founded with the vision of creating a vibrant online marketplace that not only offers unique and high-quality goods but also supports the growth of local economies and preserves traditional skills. We believe in fair trade, sustainable practices, and the power of community.
            </p>

            <h2 style="font-size: 1.8em; color: #007bff; margin-top: 30px; margin-bottom: 15px;">What We Offer</h2>
            <p style="margin-bottom: 15px; line-height: 1.7;">
                At BaladyMall, you'll find a diverse range of products, including:
            </p>
            <ul style="list-style: disc; margin-left: 20px; margin-bottom: 15px; line-height: 1.7;">
                <li>Handcrafted goods and traditional arts</li>
                <li>Unique fashion items and accessories</li>
                <li>Natural beauty products and wellness items</li>
                <li>Delicious local food products and delicacies</li>
                <li>Authentic home decor and furnishings</li>
            </ul>
            <p style="margin-bottom: 15px; line-height: 1.7;">
                Every item on our platform tells a story and represents the dedication and talent of Egyptian producers.
            </p>

            <h2 style="font-size: 1.8em; color: #007bff; margin-top: 30px; margin-bottom: 15px;">Our Commitment</h2>
            <p style="margin-bottom: 15px; line-height: 1.7;">
                We are committed to providing a seamless and enjoyable shopping experience for our customers and a supportive platform for our vendors. We strive for excellence in customer service, product quality, and ethical business practices.
            </p>
            <p style="line-height: 1.7;">
                Thank you for choosing BaladyMall and for supporting local Egyptian talent!
            </p>
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