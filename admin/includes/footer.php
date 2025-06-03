<?php
// admin/includes/footer.php
?>
            </div> </main> <footer class="admin-footer">
            <div class="admin-footer-inner">
                <p>&copy; <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'BaladyMall'; ?>. Super Admin Panel. All Rights Reserved.</p>
            </div>
        </footer>
    </div> <?php 
    // $admin_base_for_assets should be defined by the page including this footer, or defaulted in header.
    $admin_js_path = (isset($admin_base_for_assets) ? $admin_base_for_assets : '.') . '/js/admin_script.js';
    ?>
    <script src="<?php echo htmlspecialchars($admin_js_path); ?>?v=<?php echo time(); ?>"></script>
    <?php
    // You can add more specific JS file includes here if needed for certain pages
    // if (isset($include_chart_js) && $include_chart_js) {
    //     echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    // }
    ?>
</body>
</html>
