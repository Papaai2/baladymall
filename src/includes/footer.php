<?php
// src/includes/footer.php

// config.php should already be loaded by the page or header.php
// SITE_URL should be defined at this point.

// Helper function to ensure SITE_URL is defined for links, providing a fallback.
if (!function_exists('get_site_url_for_link')) {
    function get_site_url_for_link($path = '') {
        $base_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        return $base_url . '/' . ltrim($path, '/');
    }
}
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
                        <li><a href="<?php echo get_site_url_for_link('about.php'); ?>">About Us</a></li>
                        <li><a href="<?php echo get_site_url_for_link('contact.php'); ?>">Contact Us</a></li>
                    </ul>
                </div>
                <div class="footer-widget">
                    <h4>Connect With Us</h4>
                    <p>Follow us on social media!</p>
                    <div class="social-media-links" style="margin-top: 10px;">
                        <a href="#" aria-label="Facebook" title="Follow us on Facebook" style="margin-right: 15px; display: inline-block; text-decoration:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#ced4da" class="bi bi-facebook" viewBox="0 0 16 16">
                                <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram" title="Follow us on Instagram" style="display: inline-block; text-decoration:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#ced4da" class="bi bi-instagram" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.703.01 5.556 0 5.829 0 8.001c0 2.172.01 2.445.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.829 16 8 16s2.445-.01 3.296-.048c.852-.04 1.433-.174 1.942-.372.526-.205.972-.478 1.417-.923.445-.444.718-.891.923-1.417.198-.51.333-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.297c-.04-.852-.174-1.433-.372-1.942a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.09-.333-1.942-.372C10.445.01 10.173 0 8 0zm0 1.442c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.232s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.232-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.598-.92c-.11-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.232s.008-2.389.046-3.232c.036-.78.166-1.204.275-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.843-.039 1.096-.047 3.232-.047zM8 4.908c-1.749 0-3.184 1.435-3.184 3.184s1.435 3.184 3.184 3.184S11.184 9.936 11.184 8.051 9.749 4.908 8 4.908zm0 5.242c-1.129 0-2.059-.93-2.059-2.058s.93-2.059 2.059-2.059 2.059.93 2.059 2.059-.93 2.058-2.059 2.058zM12.583 3.34a1.122 1.122 0 1 0 0 2.244 1.122 1.122 0 0 0 0-2.244z"/>
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

    <script src="<?php echo get_site_url_for_link('js/script.js?v=' . time()); // Cache busting for development ?>"></script>

</body>
</html>
