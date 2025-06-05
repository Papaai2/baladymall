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
    // $admin_base_for_assets should be defined by the page including this footer (e.g., admin/index.php)
    // or defaulted in admin/includes/header.php. It determines the relative path to the js/css folders.
    // Example: if current page is admin/index.php, $admin_base_for_assets would be '.'
    // If current page is admin/some_folder/page.php, $admin_base_for_assets would be '..'
    $admin_js_path_rel = (isset($admin_base_for_assets) ? rtrim($admin_base_for_assets, '/') : '.') . '/js/admin_script.js';

    // FIX: For production, use filemtime for cache busting or a fixed version.
    // Assuming PROJECT_ROOT_PATH is defined in config.php.
    $admin_js_full_path = PROJECT_ROOT_PATH . '/admin' . '/js/admin_script.js'; // Adjust if js is outside admin folder
    $cache_buster = file_exists($admin_js_full_path) ? filemtime($admin_js_full_path) : time();
    ?>
    <script src="<?php echo htmlspecialchars($admin_js_path_rel); ?>?v=<?php echo $cache_buster; ?>"></script>

    <?php
    // You can add more page-specific JavaScript file includes here if needed.
    // For example, Chart.js is included directly in admin/index.php where it's used.
    // If you had other global libraries or page-specific scripts that don't fit into admin_script.js,
    // you could conditionally include them here.
    // Example:
    // if (isset($page_specific_js) && !empty($page_specific_js)) {
    //     foreach ($page_specific_js as $script_url) {
    //         echo '<script src="' . htmlspecialchars($script_url) . '?v=' . time() . '"></script>' . "\n";
    //     }
    // }
    ?>
</body>
</html>