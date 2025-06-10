<?php
// admin/includes/footer.php
// This file closes the main HTML structure and includes global JavaScript files for the admin panel.
?>
            </div> </main> <footer class="admin-footer">
            <div class="admin-footer-inner">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?>. Super Admin Panel. All Rights Reserved.</p>
            </div>
        </footer>

    </div> <?php
    // $admin_js_full_path should point to the actual file on disk.
    // get_asset_url uses SITE_URL, so admin_script.js needs to be in public/admin/js/
    $admin_js_full_path = PROJECT_ROOT_PATH . '/admin/js/admin_script.js';
    $cache_buster = file_exists($admin_js_full_path) ? filemtime($admin_js_full_path) : time();
    ?>
    <script src="<?php echo get_asset_url('admin/js/admin_script.js?v=' . $cache_buster); ?>"></script>

    <?php
    // You can add more page-specific JavaScript file includes here if needed.
    // For example, Chart.js is included directly in admin/index.php where it's used.
    ?>
</body>
</html>