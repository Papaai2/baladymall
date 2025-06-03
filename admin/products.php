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
if ($total_records > 0 || empty($params)) { // Attempt to fetch if records exist or no filters applied
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
        echo "<div class='admin-message error'>Error fetching products. Details: " . $e->getMessage() . "</div>";
    }
}

// --- Function to delete a single product and its related data ---
function deleteSingleProduct($db, $product_id_to_delete) {
    // Fetch main_image_url to delete it
    $stmt_img = $db->prepare("SELECT main_image_url FROM products WHERE product_id = :pid");
    $stmt_img->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
    $stmt_img->execute();
    $product_image_path = $stmt_img->fetchColumn();

    // Delete from product_category
    $stmt_del_cat = $db->prepare("DELETE FROM product_category WHERE product_id = :pid");
    $stmt_del_cat->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
    $stmt_del_cat->execute();
    
    // Delete from product_images (also collect files to delete from server)
    $stmt_imgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :pid");
    $stmt_imgs->bindParam(':pid', $product_id_to_delete, PDO::PARAM_INT);
    $stmt_imgs->execute();
    $image_files_to_delete = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);

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

    // Finally, delete the product itself
    $stmt_delete_product = $db->prepare("DELETE FROM products WHERE product_id = :product_id");
    $stmt_delete_product->bindParam(':product_id', $product_id_to_delete, PDO::PARAM_INT);
    
    if ($stmt_delete_product->execute()) {
        // Delete the actual image files
        if ($product_image_path && file_exists(PUBLIC_UPLOADS_PATH . $product_image_path)) {
            @unlink(PUBLIC_UPLOADS_PATH . $product_image_path);
        }
        foreach ($image_files_to_delete as $img_file) {
            if ($img_file && file_exists(PUBLIC_UPLOADS_PATH . $img_file)) {
                @unlink(PUBLIC_UPLOADS_PATH . $img_file);
            }
        }
        return true;
    }
    return false;
}


// --- Handle Single Delete Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id_to_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if ($product_id_to_delete && $csrf_token_form && hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        try {
            $db->beginTransaction();
            if (deleteSingleProduct($db, $product_id_to_delete)) {
                $db->commit();
                $_SESSION['admin_message'] = "<div class='admin-message success'>Product ID #{$product_id_to_delete} and its associated data deleted successfully.</div>";
            } else {
                $db->rollBack();
                $_SESSION['admin_message'] = "<div class='admin-message error'>Failed to delete product ID #{$product_id_to_delete}.</div>";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Admin Products - Error deleting product: " . $e->getMessage());
            $_SESSION['admin_message'] = "<div class='admin-message error'>An error occurred while deleting the product. Check logs. Error: " . $e->getMessage() ."</div>";
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
        $error_count = 0;
        try {
            $db->beginTransaction();
            foreach ($product_ids_to_delete as $pid) {
                if (deleteSingleProduct($db, $pid)) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            }
            if ($error_count > 0) {
                $db->rollBack(); // Rollback if any product failed to delete
                 $_SESSION['admin_message'] = "<div class='admin-message error'>Batch delete failed for {$error_count} product(s). No products were deleted. Please try again or delete individually.</div>";
            } else {
                $db->commit();
                $_SESSION['admin_message'] = "<div class='admin-message success'>Successfully deleted {$deleted_count} product(s).</div>";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Admin Products - Error batch deleting products: " . $e->getMessage());
            $_SESSION['admin_message'] = "<div class='admin-message error'>An error occurred during batch deletion. Check logs. Error: " . $e->getMessage() ."</div>";
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
                <option value="<?php echo htmlspecialchars($brand_filter['brand_id']); ?>" <?php echo ($filter_brand_id == $brand_filter['brand_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($brand_filter['brand_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="filter_status">Filter by Status:</label>
        <select name="filter_status" id="filter_status">
            <option value="">-- All Statuses --</option>
            <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>

        <button type="submit">Filter</button>
        <a href="products.php" style="margin-left: 10px;">Clear Filters</a>
    </form>
</div>

<form action="products.php?page=<?php echo $page; ?>&filter_brand_id=<?php echo $filter_brand_id; ?>&filter_status=<?php echo $filter_status; ?>" method="POST" id="batch-actions-form" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
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
                        <td><input type="checkbox" name="product_ids[]" value="<?php echo $product['product_id']; ?>" class="product-checkbox"></td>
                        <td>
                            <?php 
                            $image_path = $product['main_image_url'] ? htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $product['main_image_url']) : htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=No+Image');
                            $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Error');
                            ?>
                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                        </td>
                        <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['brand_name']); ?> (ID: <?php echo htmlspecialchars($product['brand_id']); ?>)</td>
                        <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format((float)$product['price'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                        <td>
                            <span class="status-<?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="edit_product.php?product_id=<?php echo $product['product_id']; ?>" class="btn-edit">Edit</a>
                            <form action="products.php?page=<?php echo $page; ?>&filter_brand_id=<?php echo $filter_brand_id; ?>&filter_status=<?php echo $filter_status; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product and all its related data? This action cannot be undone.');">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button type="submit" name="delete_product" class="btn-suspend">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 20px; text-align: center;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&filter_brand_id=<?php echo $filter_brand_id; ?>&filter_status=<?php echo $filter_status; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&filter_brand_id=<?php echo $filter_brand_id; ?>&filter_status=<?php echo $filter_status; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px; <?php if ($i == $page) echo 'background-color: #007bff; color: white;'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter_brand_id=<?php echo $filter_brand_id; ?>&filter_status=<?php echo $filter_status; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php elseif (empty($params) && $total_records == 0): ?>
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
