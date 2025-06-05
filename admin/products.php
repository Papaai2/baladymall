<?php
// admin/products.php - Super Admin: Manage Products

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN PRODUCTS ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Manage Products";
include_once 'includes/header.php';

$db = getPDOConnection();

// --- Filtering ---
$filter_brand_id = filter_input(INPUT_GET, 'filter_brand_id', FILTER_VALIDATE_INT);
$filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_UNSAFE_RAW); // 'active', 'inactive', or '' for all

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page < 1 ? 1 : $page;
$records_per_page = 15; // Number of products per page
$offset = ($page - 1) * $records_per_page;


// --- Fetch Brands for Filter Dropdown ---
$brands_for_filter = [];
try {
    $stmt_brands_filter = $db->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC");
    $brands_for_filter = $stmt_brands_filter->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Products - Error fetching brands for filter: " . $e->getMessage());
    // Non-critical error, page can still function
}


// --- Build Query for Products ---
$sql_products = "SELECT p.product_id, p.product_name, p.price, p.stock_quantity, p.is_active, p.main_image_url,
                        b.brand_name, b.brand_id
                 FROM products p
                 JOIN brands b ON p.brand_id = b.brand_id";
$sql_count = "SELECT COUNT(p.product_id) FROM products p JOIN brands b ON p.brand_id = b.brand_id";

$where_clauses = [];
$params = [];

if ($filter_brand_id) {
    $where_clauses[] = "p.brand_id = :brand_id";
    $params[':brand_id'] = $filter_brand_id;
}
if ($filter_status === 'active') {
    $where_clauses[] = "p.is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "p.is_active = 0";
}

if (!empty($where_clauses)) {
    $sql_products .= " WHERE " . implode(" AND ", $where_clauses);
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_products .= " ORDER BY p.updated_at DESC, p.product_name ASC LIMIT :limit OFFSET :offset";

// --- Fetch Total Records for Pagination ---
$total_records = 0;
try {
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params); // Pass params for count query as well
    $total_records = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin Products - Error fetching product count: " . $e->getMessage());
    echo "<div class='admin-message error'>Error fetching product count.</div>";
}
$total_pages = ceil($total_records / $records_per_page);


// --- Fetch Products for Current Page ---
$products = [];
if ($total_records > 0 || (empty($params) && empty($where_clauses))) { // Attempt to fetch if records exist or no filters applied
    try {
        $stmt_products = $db->prepare($sql_products);
        // Bind common params for main query
        foreach ($params as $key => $val) {
            $stmt_products->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_products->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt_products->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Admin Products - Error fetching products: " . $e->getMessage());
        echo "<div class='admin-message error'>Error fetching products.</div>"; // FIX: Removed raw error message
    }
}

// --- Function to delete a single product and its related data ---
function deleteSingleProduct($db, $product_id_to_delete) {
    // Fetch main_image_url to delete it
    $stmt_img = $db->prepare("SELECT main_image_url FROM products WHERE product_id = :pid");
    $stmt_img->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
    $stmt_img->execute();
    $product_main_image_url = $stmt_img->fetchColumn();

    // Check if product is part of any active/non-finalized orders
    // Deletion is now allowed if item_status is 'shipped_by_brand', 'delivered_to_customer', 'cancelled', or 'returned'.
    // It will only be blocked if item_status is 'pending' or 'processing'.
    $stmt_check_order_items = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :pid AND item_status IN ('pending', 'processing')");
    $stmt_check_order_items->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
    $stmt_check_order_items->execute();
    $active_order_items_count = $stmt_check_order_items->fetchColumn();

    if ($active_order_items_count > 0) {
        return ['success' => false, 'message' => 'Cannot delete product. It is part of active orders (status: pending or processing).'];
    }

    try {
        $db->beginTransaction();

        // Delete from product_category
        $stmt_del_cat = $db->prepare("DELETE FROM product_category WHERE product_id = :pid");
        $stmt_del_cat->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_del_cat->execute();

        // Delete from product_images (also collect files to delete from server)
        $stmt_imgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :pid");
        $stmt_imgs->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_imgs->execute();
        $image_files_to_delete_from_server = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);

        $stmt_del_imgs = $db->prepare("DELETE FROM product_images WHERE product_id = :pid");
        $stmt_del_imgs->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_del_imgs->execute();

        // Delete from product_variants (and their attribute values)
        $stmt_variants = $db->prepare("SELECT variant_id FROM product_variants WHERE product_id = :pid");
        $stmt_variants->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_variants->execute();
        $variant_ids = $stmt_variants->fetchAll(PDO::FETCH_COLUMN);

        if ($variant_ids) {
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
            $stmt_del_var_attrs = $db->prepare("DELETE FROM variant_attribute_values WHERE variant_id IN (" . $placeholders . ")");
            $stmt_del_var_attrs->execute($variant_ids);
        }

        $stmt_del_vars = $db->prepare("DELETE FROM product_variants WHERE product_id = :pid");
        $stmt_del_vars->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_del_vars->execute();

        // Delete related order_items if they are in 'cancelled', 'returned', 'delivered_to_customer', or 'shipped_by_brand' state
        $stmt_del_order_items = $db->prepare("DELETE FROM order_items WHERE product_id = :pid AND item_status IN ('cancelled', 'returned', 'delivered_to_customer', 'shipped_by_brand')");
        $stmt_del_order_items->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_del_order_items->execute();

        // Finally, delete the product itself
        $stmt_delete_product = $db->prepare("DELETE FROM products WHERE product_id = :product_id");
        $stmt_delete_product->bindParam(':product_id', $product_id_to_delete, PDO::PARAM_INT);

        if ($stmt_delete_product->execute() && $stmt_delete_product->rowCount() > 0) {
            $db->commit();
            // Delete the actual image files from server
            // Main image file
            if ($product_main_image_url && !filter_var($product_main_image_url, FILTER_VALIDATE_URL) && strpos($product_main_image_url, '//') !== 0) {
                $full_path_main_image = PUBLIC_UPLOADS_PATH . $product_main_image_url;
                if (file_exists($full_path_main_image) && is_file($full_path_main_image)) {
                    unlink($full_path_main_image);
                } else {
                    error_log("Admin Products - Main image for deletion not found or not a file: " . $full_path_main_image);
                }
            }
            // Additional gallery images
            foreach ($image_files_to_delete_from_server as $img_file_url) {
                if ($img_file_url && !filter_var($img_file_url, FILTER_VALIDATE_URL) && strpos($img_file_url, '//') !== 0) {
                    $full_path_gallery_image = PUBLIC_UPLOADS_PATH . $img_file_url;
                    if (file_exists($full_path_gallery_image) && is_file($full_path_gallery_image)) {
                        unlink($full_path_gallery_image);
                    } else {
                        error_log("Admin Products - Gallery image for deletion not found or not a file: " . $full_path_gallery_image);
                    }
                }
            }
            return ['success' => true, 'message' => 'Product and its associated data deleted successfully.'];
        } else {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to delete product from database.'];
        }
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Admin Products - Error deleting product: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while deleting the product.']; // FIX: Removed raw error message
    }
}


// --- Handle Single Delete Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id_to_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if ($product_id_to_delete && $csrf_token_form && hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $result = deleteSingleProduct($db, $product_id_to_delete);
        if ($result['success']) {
            $_SESSION['admin_message'] = "<div class='admin-message success'>{$result['message']}</div>";
        } else {
            $_SESSION['admin_message'] = "<div class='admin-message error'>{$result['message']}</div>";
        }
        // Refresh page
        header("Location: products.php?page={$page}" . ($filter_brand_id ? "&filter_brand_id={$filter_brand_id}" : "") . ($filter_status ? "&filter_status={$filter_status}" : ""));
        exit;
    } else {
        $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid request for deletion or CSRF token mismatch.</div>";
        header("Location: products.php?page={$page}" . ($filter_brand_id ? "&filter_brand_id={$filter_brand_id}" : "") . ($filter_status ? "&filter_status={$filter_status}" : ""));
        exit;
    }
}

// --- Handle Batch Delete Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_delete_products'])) {
    $product_ids_to_delete = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if (!empty($product_ids_to_delete) && $csrf_token_form && hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $deleted_count = 0;
        $failed_deletions = [];
        foreach ($product_ids_to_delete as $pid) {
            $result = deleteSingleProduct($db, $pid);
            if ($result['success']) {
                $deleted_count++;
            } else {
                $failed_deletions[] = "Product ID {$pid}: {$result['message']}";
            }
        }

        if ($deleted_count > 0 && empty($failed_deletions)) {
            $_SESSION['admin_message'] = "<div class='admin-message success'>Successfully deleted {$deleted_count} product(s).</div>";
        } elseif ($deleted_count > 0 && !empty($failed_deletions)) {
            $_SESSION['admin_message'] = "<div class='admin-message warning'>Deleted {$deleted_count} product(s). However, some products could not be deleted:<br><ul><li>" . htmlspecialchars(implode("</li><li>", $failed_deletions)) . "</li></ul></div>"; // FIX: htmlspecialchars
        } else {
            $_SESSION['admin_message'] = "<div class='admin-message error'>No products were deleted. Reasons:<br><ul><li>" . htmlspecialchars(implode("</li><li>", $failed_deletions)) . "</li></ul></div>"; // FIX: htmlspecialchars
        }

        header("Location: products.php?page={$page}" . ($filter_brand_id ? "&filter_brand_id={$filter_brand_id}" : "") . ($filter_status ? "&filter_status={$filter_status}" : ""));
        exit;
    } elseif (empty($product_ids_to_delete)) {
        $_SESSION['admin_message'] = "<div class='admin-message warning'>No products selected for batch deletion.</div>";
        header("Location: products.php?page={$page}" . ($filter_brand_id ? "&filter_brand_id={$filter_brand_id}" : "") . ($filter_status ? "&filter_status={$filter_status}" : ""));
        exit;
    } else {
         $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid request for batch deletion or CSRF token mismatch.</div>";
        header("Location: products.php?page={$page}" . ($filter_brand_id ? "&filter_brand_id={$filter_brand_id}" : "") . ($filter_status ? "&filter_status={$filter_status}" : ""));
        exit;
    }
}


// Generate a CSRF token for delete forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="add_product.php" class="btn-submit" style="display: inline-block; margin-bottom: 20px; text-decoration:none;">+ Add New Product</a></p>

<?php
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']); // Clear the message after displaying
}
?>

<div class="admin-filters">
    <form action="products.php" method="GET">
        <label for="filter_brand_id">Filter by Brand:</label>
        <select name="filter_brand_id" id="filter_brand_id">
            <option value="">-- All Brands --</option>
            <?php foreach ($brands_for_filter as $brand_filter): ?>
                <option value="<?php echo htmlspecialchars($brand_filter['brand_id']); ?>" <?php echo ((string)$filter_brand_id === (string)$brand_filter['brand_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($brand_filter['brand_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="filter_status">Filter by Status:</label>
        <select name="filter_status" id="filter_status">
            <option value="">-- All Statuses --</option>
            <option value="active" <?php echo ((string)$filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo ((string)$filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>

        <button type="submit">Filter</button>
        <a href="products.php" style="margin-left: 10px;">Clear Filters</a>
    </form>
</div>

<form action="products.php?page=<?php echo htmlspecialchars($page); ?>&filter_brand_id=<?php echo htmlspecialchars($filter_brand_id ?? ''); ?>&filter_status=<?php echo htmlspecialchars($filter_status ?? ''); ?>" method="POST" id="batch-actions-form" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <div style="margin-bottom: 15px;">
        <button type="submit" name="batch_delete_products" class="btn-submit" onclick="return confirm('Are you sure you want to delete all selected products and their related data? This action cannot be undone.');">Delete Selected Products</button>
    </div>

    <?php if (!empty($products)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-products" title="Select all/none"></th>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><input type="checkbox" name="product_ids[]" value="<?php echo htmlspecialchars($product['product_id']); ?>" class="product-checkbox"></td>
                        <td>
                            <?php
                            // Determine the correct image path
                            $image_path = '';
                            if (!empty($product['main_image_url'])) {
                                if (filter_var($product['main_image_url'], FILTER_VALIDATE_URL) || strpos($product['main_image_url'], '//') === 0) {
                                    // It's an absolute URL, use it directly
                                    $image_path = htmlspecialchars($product['main_image_url']);
                                } else {
                                    // It's a relative path, prepend PUBLIC_UPLOADS_URL_BASE
                                    $image_path = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $product['main_image_url']);
                                }
                            } else {
                                // No image URL, use placeholder
                                $image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=No+Image');
                            }
                            $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Error');
                            ?>
                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['product_name'] ?? ''); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                        </td>
                        <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($product['product_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($product['brand_name'] ?? 'N/A'); ?> (ID: <?php echo htmlspecialchars($product['brand_id'] ?? 'N/A'); ?>)</td>
                        <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$product['price'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($product['stock_quantity'] ?? '0'); ?></td>
                        <td>
                            <span class="status-<?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="edit_product.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>" class="btn-edit">Edit</a>
                            <form action="products.php?page=<?php echo htmlspecialchars($page); ?>&filter_brand_id=<?php echo htmlspecialchars($filter_brand_id ?? ''); ?>&filter_status=<?php echo htmlspecialchars($filter_status ?? ''); ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product and all its related data? This action cannot be undone.');">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" name="delete_product" class="btn-suspend">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 20px; text-align: center;">
                <?php
                    // Build query string for pagination, preserving filters
                    $pagination_query_params = [];
                    if (!empty($filter_brand_id)) $pagination_query_params['filter_brand_id'] = $filter_brand_id;
                    if (!empty($filter_status)) $pagination_query_params['filter_status'] = $filter_status;
                    $pagination_query_string = http_build_query($pagination_query_params);
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo htmlspecialchars($page - 1); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo htmlspecialchars($i); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px; <?php if ($i == $page) echo 'background-color: #007bff; color: white;'; ?>">
                        <?php echo htmlspecialchars($i); ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo htmlspecialchars($page + 1); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($total_records == 0 && empty($filter_brand_id) && empty($filter_status)): ?>
        <p class="admin-message info">No products found. <a href="add_product.php">Add your first product!</a></p>
    <?php else: ?>
        <p class="admin-message info">No products found matching your current filters.</p>
    <?php endif; ?>
</form> <script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-products');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!checkbox.checked) {
                selectAllCheckbox.checked = false;
            } else {
                // Check if all are checked
                let allChecked = true;
                productCheckboxes.forEach(cb => {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
});
</script>

<?php
include_once 'includes/footer.php';
?>