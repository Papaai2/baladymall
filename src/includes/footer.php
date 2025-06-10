<?php
// src/includes/footer.php

// ***** URGENT FIX: Guaranteed definition of PUBLIC_ROOT_PATH for persistent error *****
// This is a direct injection to ensure the constant exists at the point of use.
// It assumes footer.php is consistently at PROJECT_ROOT_PATH/src/includes/footer.php
if (!defined('PROJECT_ROOT_PATH')) {
    define('PROJECT_ROOT_PATH', dirname(__DIR__, 2)); // Go up two directories from src/includes
}
if (!defined('PUBLIC_ROOT_PATH')) {
    define('PUBLIC_ROOT_PATH', PROJECT_ROOT_PATH . '/public');
}
// Also define SITE_URL if it happens to be undefined for get_asset_url fallback
if (!defined('SITE_URL')) {
    // This assumes your domain is baladymall.shop and public is a subfolder
    define('SITE_URL', 'https://baladymall.wuaze.com/public'); // Changed to wuaze.com for consistency
}

// Fallback for get_asset_url function in case config.php's version is somehow unavailable
if (!function_exists('get_asset_url')) {
    function get_asset_url($path = '') {
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
}
// ***************** END URGENT FIX *****************

// The rest of your footer.php content remains as it was, but now with assured constants.

// config.php should already be loaded by the page or header.php
// SITE_URL should be defined at this point from config.php or the defensive definition above.
?>
        </div> <?php // Closes the .container from header.php's site-main opening ?>
    </main> <?php // Closes site-main from header.php ?>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-widgets-area">
                <div class="footer-widget">
                    <h4>About BaladyMall</h4>
                    <p>Your one-stop shop for the best local Egyptian brands. Supporting our community, one purchase at a time.</p>
                </div>
                <div class="footer-widget">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo get_asset_url('about.php'); ?>">About Us</a></li>
                        <li><a href="<?php echo get_asset_url('contact.php'); ?>">Contact Us</a></li>
                    </ul>
                </div>
                <div class="footer-widget">
                    <h4>Connect With Us</h4>
                    <p>Follow us on social media!</p>
                    <div class="social-media-links" style="margin-top: 10px;">
                        <?php // FIX: Replace '#' with actual social media URLs for production ?>
                        <a href="#" aria-label="Facebook" title="Follow us on Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16">
                                <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram" title="Follow us on Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.703.01 5.556 0 5.829 0 8.001c0 2.172.01 2.445.048 3.297c.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.829 16 8 16s2.445-.01 3.296-.048c.852-.04 1.433-.174 1.942-.372.526-.205.972-.478 1.417-.923.445-.444.718-.891.923-1.417.198-.51.333-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.297c-.04-.852-.174-1.433-.372-1.942a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.09-.333-1.942-.372C10.445.01 10.173 0 8 0zm0 1.442c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1096.047 3232s-.008 2389-.047 3232c-.035.78-.166 1203-.275 1485a25 25 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1485-.276-.843.038-1096.047-3232s-239-.009-3232-.047c-.78-.036-1203-.166-1485-.276a25 25 0 0 1-.92-.598 25 25 0 0 1-.598-.92c-.11-.281-.24-.705-.275-1485-.038-.843-.046-1096-.046-3232s.008-2389.046-3232c.036-.78.166-1204.275-1486.145-373.319-64.599-.92.28-.28.546-453.92-.598.282-.11.705-24.1485-.276.843-.039 1096-.047 3232-.047zM8 4908c-1749 0-3184 1435-3184 3184s1435 3184 3184 3184S11184 9936 11184 8051 9749 4908 8 4908zm0 5242c-1129 0-2059-.93-2059-2058s.93-2059 2059-2059 2059.93 2059 2059 2059-.93 2058-2059 2058zM12583 334a1122 1122 0 1 0 0 2244 1122 1122 0 0 0 0-2244z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="copyright-area">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? esc_html(SITE_NAME) : 'BaladyMall'; ?>. All Rights Reserved.</p>
                <p>Proudly Made in Egypt.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>

    <script src="<?php echo get_asset_url('js/script.js') . '?v=' . (file_exists(PUBLIC_ROOT_PATH . '/js/script.js') ? filemtime(PUBLIC_ROOT_PATH . '/js/script.js') : '1.0.0'); ?>"></script>

</body>
</html>