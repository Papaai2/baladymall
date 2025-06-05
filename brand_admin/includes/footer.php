<?php
// brand_admin/includes/footer.php
// This file closes the main HTML structure and includes global JavaScript files for the brand admin panel.
?>
            </div> </main> <footer class="brand-admin-footer">
            <div class="brand-admin-footer-inner">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?>. Brand Admin Panel. All Rights Reserved.</p>
            </div>
        </footer>

    </div> <?php
    // $brand_admin_base_for_assets should be defined by the page including this footer (e.g., brand_admin/index.php)
    // or defaulted in brand_admin/includes/header.php. It determines the relative path to the js/css folders.
    $brand_admin_js_path_rel = (isset($brand_admin_base_for_assets) ? rtrim($brand_admin_base_for_assets, '/') : '.') . '/js/brand_admin_script.js';

    // FIX: For production, use filemtime for cache busting or a fixed version.
    // Assuming PUBLIC_ROOT_PATH is defined in config.php.
    $brand_admin_js_full_path = PROJECT_ROOT_PATH . '/brand_admin' . '/js/brand_admin_script.js'; // Adjust if js is outside brand_admin folder
    $cache_buster = file_exists($brand_admin_js_full_path) ? filemtime($brand_admin_js_full_path) : time();
    ?>
    <script src="<?php echo htmlspecialchars($brand_admin_js_path_rel); ?>?v=<?php echo $cache_buster; ?>"></script>

</body>
</html>