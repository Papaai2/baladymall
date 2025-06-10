<?php
// brand_admin/includes/footer.php
// This file closes the main HTML structure and includes global JavaScript files for the admin panel.
?>
            </div> </main> <footer class="brand-admin-footer">
            <div class="brand-admin-footer-inner">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?>. Brand Admin Panel. All Rights Reserved.</p>
            </div>
        </footer>

    </div> <?php
    // $brand_admin_js_full_path should point to the actual file on disk.
    // get_asset_url uses SITE_URL, so brand_admin_script.js needs to be in public/brand_admin/js/
    $brand_admin_js_full_path = PROJECT_ROOT_PATH . '/brand_admin/js/brand_admin_script.js';
    $cache_buster = file_exists($brand_admin_js_full_path) ? filemtime($brand_admin_js_full_path) : time();
    ?>
    <script src="<?php echo get_asset_url('brand_admin/js/brand_admin_script.js?v=' . $cache_buster); ?>"></script>

    <?php
    // You can add more page-specific JavaScript file includes here if needed.
    // For example, Chart.js is included directly in brand_admin/index.php where it's used.
    ?>
</body>
</html>