<?php
// admin/brands.php - Super Admin Brand Management (List Brands)

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN BRANDS ERROR: Main config.php not found.");
}
require_once 'auth_check.php';

$admin_page_title = "Manage Brands";
include_once 'includes/header.php'; // This uses ADMIN_BASE_URL_FOR_LINKS

$db = getPDOConnection();
$brands = [];
$message = $_SESSION['brand_management_message'] ?? ''; // Display message from actions
unset($_SESSION['brand_management_message']); // Clear message after displaying

// Filters
// FIX: Use filter_input for user-supplied GET parameters
$filter_status = filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW) ?? 'all';

try {
    // Select brand_logo_url as well
    $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo_url, b.brand_contact_email, b.is_approved, b.created_at, u.username as admin_username
            FROM brands b
            LEFT JOIN users u ON b.user_id = u.user_id";

    $params = [];
    $conditions = [];

    if ($filter_status === 'pending') {
        $conditions[] = "b.is_approved = 0";
    } elseif ($filter_status === 'approved') {
        $conditions[] = "b.is_approved = 1";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin Brands - Error fetching brands: " . $e->getMessage());
    $message = "<div class='admin-message error'>Could not load brands. Please try again later.</div>";
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 class="admin-page-title" style="margin-bottom: 0;"><?php echo htmlspecialchars($admin_page_title); ?></h1>
    <a href="<?php echo ADMIN_BASE_URL_FOR_LINKS; ?>/add_brand.php" class="btn-submit" style="padding: 8px 15px; text-decoration:none;">Add New Brand</a>
</div>


<?php if ($message) echo $message; // Display any success/error messages ?>

<div class="admin-filters mb-3" style="padding: 10px; background-color:#f9f9f9; border-radius:5px;">
    <form action="brands.php" method="GET" style="display:flex; align-items:center; gap:15px;">
        <label for="status_filter">Filter by status:</label>
        <select name="status" id="status_filter" onchange="this.form.submit()" style="padding:5px;">
            <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Brands</option>
            <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending Approval</option>
            <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
            <?php // Add 'suspended' option later ?>
        </select>
        <noscript><button type="submit">Filter</button></noscript>
    </form>
</div>


<?php if (!empty($brands)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Logo</th> <th>ID</th>
                <th>Brand Name</th>
                <th>Contact Email</th>
                <th>Brand Admin</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($brands as $brand): ?>
                <tr>
                    <td>
                        <?php
                        $logo_display_url = '';
                        if (!empty($brand['brand_logo_url'])) {
                            // Check if it's an absolute URL (starts with http:// or https:// or //)
                            if (filter_var($brand['brand_logo_url'], FILTER_VALIDATE_URL) || strpos($brand['brand_logo_url'], '//') === 0) {
                                $logo_display_url = htmlspecialchars($brand['brand_logo_url']);
                            } else {
                                // It's a relative path, prepend PUBLIC_UPLOADS_URL_BASE
                                $logo_display_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $brand['brand_logo_url']);
                            }
                        } else {
                            // Placeholder if no logo is set
                            $logo_display_url = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Logo');
                        }
                        $fallback_logo_url = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Error');
                        ?>
                        <img src="<?php echo $logo_display_url; ?>" alt="<?php echo htmlspecialchars($brand['brand_name']); ?> Logo" style="width: 50px; height: 50px; object-fit: contain; border-radius: 4px; background: #f0f0f0; padding: 2px;" onerror="this.onerror=null; this.src='<?php echo $fallback_logo_url; ?>';">
                    </td>
                    <td><?php echo htmlspecialchars($brand['brand_id']); ?></td>
                    <td><?php echo htmlspecialchars($brand['brand_name']); ?></td>
                    <td><?php echo htmlspecialchars($brand['brand_contact_email'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($brand['admin_username'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if ($brand['is_approved'] == 1): ?>
                            <span style="color: green; font-weight:bold;">Approved</span>
                        <?php elseif ($brand['is_approved'] == 0): ?>
                            <span style="color: orange; font-weight:bold;">Pending Approval</span>
                        <?php else: ?>
                            <span style="color: red; font-weight:bold;">Suspended/Other</span> <?php // For future statuses ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(date("M j, Y", strtotime($brand['created_at']))); ?></td>
                    <td class="actions">
                        <a href="brand_edit.php?brand_id=<?php echo htmlspecialchars($brand['brand_id']); ?>" class="btn-edit">View/Edit</a>
                        <?php if ($brand['is_approved'] == 0): ?>
                            <a href="brand_edit.php?brand_id=<?php echo htmlspecialchars($brand['brand_id']); ?>&action=approve" class="btn-approve" onclick="return confirm('Are you sure you want to approve this brand?');">Approve</a>
                        <?php endif; ?>
                        <?php // Add Suspend/Delete actions later ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <?php if (empty($message)): ?>
    <p class="admin-message info">No brands found matching the criteria.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>