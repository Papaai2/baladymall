<?php
// src/includes/footer.php

// No direct PHP logic is usually needed in a simple footer like this,
// but config.php might have been included by header.php already.
// We ensure SITE_URL is available for consistency, though it might already be defined
// if header.php (which includes config.php) was included before this footer.
if (!defined('SITE_URL') && file_exists(dirname(__DIR__) . '/config/config.php')) {
    // This is a fallback, ideally config.php is included once by the main page or header.
    require_once dirname(__DIR__) . '/config/config.php';
}
?>
        </div> </main> <footer class="site-footer">
        <div class="container">
            <div class="footer-widgets-area">
                <div class="footer-widget">
                    <h4>About BaladyMall</h4>
                    <p>Your one-stop shop for the best local Egyptian brands. Supporting our community, one purchase at a time.</p>
                </div>
                <div class="footer-widget">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/about.php">About Us</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/contact.php">Contact Us</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/faq.php">FAQ</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/terms.php">Terms & Conditions</a></li>
                        <li><a href="<?php echo defined('SITE_URL') ? SITE_URL : '/'; ?>/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-widget">
                    <h4>Connect With Us</h4>
                    <p>Follow us on social media!</p>
                    <!-- 
                    <a href="#">Facebook</a> | 
                    <a href="#">Instagram</a> | 
                    <a href="#">Twitter</a> 
                    -->
                </div>
            </div>

            <div class="copyright-area">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?>. All Rights Reserved.</p>
                <p>Proudly Made in Egypt.</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/js/script.js?v=<?php echo time(); // Cache busting for development ?>"></script>
    
    </body>
</html>
