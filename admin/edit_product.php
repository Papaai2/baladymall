<?php
// admin/edit_product.php - Super Admin: Edit Product

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN EDIT PRODUCT ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Edit Product";
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Get Product ID from URL
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

if (!$product_id) {
    $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid Product ID.</div>";
    header("Location: products.php");
    exit;
}

// --- Fetch Existing Product Data ---
$product = null;
$current_category_ids = [];
try {
    $stmt_product = $db->prepare("SELECT * FROM products WHERE product_id = :product_id");
    $stmt_product->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['admin_message'] = "<div class='admin-message error'>Product not found (ID: {$product_id}).</div>";
        header("Location: products.php");
        exit;
    }

    // Fetch current categories for this product
    $stmt_prod_cats = $db->prepare("SELECT category_id FROM product_category WHERE product_id = :product_id");
    $stmt_prod_cats->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_prod_cats->execute();
    $current_category_ids = $stmt_prod_cats->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Admin Edit Product - Error fetching product data: " . $e->getMessage());
    $_SESSION['admin_message'] = "<div class='admin-message error'>Error loading product data.</div>";
    header("Location: products.php");
    exit;
}

// Initialize form variables with existing product data
$product_name_form = $product['product_name'];
$brand_id_form = $product['brand_id'];
$category_ids_form = $current_category_ids;
$product_description_form = $product['product_description'];
$price_form = $product['price'];
$compare_at_price_form = $product['compare_at_price'];
$stock_quantity_form = $product['stock_quantity'];
$is_active_form = $product['is_active'];
$requires_variants_form = $product['requires_variants'];
$current_main_image_url = $product['main_image_url'];


// --- Fetch Brands for Dropdown ---
$brands = [];
try {
    $stmt_brands = $db->query("SELECT brand_id, brand_name FROM brands WHERE is_approved = 1 ORDER BY brand_name ASC");
    $brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Edit Product - Error fetching brands: " . $e->getMessage());
    $errors['database_fetch'] = "Could not load brands.";
}

// --- Fetch Categories for Checkboxes/Select ---
$categories = [];
try {
    $stmt_categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Edit Product - Error fetching categories: " . $e->getMessage());
    $errors['database_fetch'] = "Could not load categories.";
}


// --- Handle Form Submission for Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    // Sanitize and validate inputs
    // Assuming product_name max length is 255 based on typical VARCHAR(255)
    $product_name_form = trim(substr(filter_input(INPUT_POST, 'product_name', FILTER_UNSAFE_RAW), 0, 255));
    $brand_id_form = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);
    $category_ids_form = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
    // Assuming product_description can be long, but still trim. No specific max length for TEXT/MEDIUMTEXT.
    $product_description_form = trim(filter_input(INPUT_POST, 'product_description', FILTER_UNSAFE_RAW));

    $price_input = filter_input(INPUT_POST, 'price', FILTER_UNSAFE_RAW);
    $compare_at_price_input = filter_input(INPUT_POST, 'compare_at_price', FILTER_UNSAFE_RAW);
    $stock_quantity_input = filter_input(INPUT_POST, 'stock_quantity', FILTER_UNSAFE_RAW);

    $price_form = ($price_input === '' || $price_input === null) ? null : filter_var($price_input, FILTER_VALIDATE_FLOAT);
    $compare_at_price_form = ($compare_at_price_input === '' || $compare_at_price_input === null) ? null : filter_var($compare_at_price_input, FILTER_VALIDATE_FLOAT);
    $stock_quantity_form = ($stock_quantity_input === '' || $stock_quantity_input === null) ? 0 : filter_var($stock_quantity_input, FILTER_VALIDATE_INT, ["options" => ["min_range"=>0]]);

    $is_active_form = isset($_POST['is_active']) ? 1 : 0;
    $requires_variants_form = isset($_POST['requires_variants']) ? 1 : 0;

    // Basic Validation (similar to add_product.php)
    if (empty($product_name_form)) $errors['product_name'] = "Product name is required.";
    if (empty($brand_id_form)) $errors['brand_id'] = "Brand is required.";
    if (empty($category_ids_form)) $errors['category_ids'] = "At least one category is required.";

    // Price and stock validation logic adjusted for variants
    if (!$requires_variants_form) { // Only require price and stock if not requiring variants
        if ($price_form === null) {
            $errors['price'] = "Price is required for simple products.";
        } elseif ($price_form < 0) {
            $errors['price'] = "Price cannot be negative.";
        }
        if ($stock_quantity_form === false || $stock_quantity_form < 0) {
            $errors['stock_quantity'] = "Stock quantity must be a valid non-negative number.";
            // Revert to original on error for sticky form, but ensure it's not false
            $stock_quantity_form = ($product['stock_quantity'] === false || $product['stock_quantity'] === null) ? 0 : $product['stock_quantity'];
        }
    } else { // If requires variants, these fields are optional for the main product
        if ($price_form !== null && $price_form < 0) {
            $errors['price'] = "Price cannot be negative.";
        }
        if ($stock_quantity_form === false || $stock_quantity_form < 0) {
            $errors['stock_quantity'] = "Stock quantity must be a valid non-negative number.";
            $stock_quantity_form = ($product['stock_quantity'] === false || $product['stock_quantity'] === null) ? 0 : $product['stock_quantity'];
        }
    }

    if ($compare_at_price_form !== null && $compare_at_price_form < 0) {
        $errors['compare_at_price'] = "Compare at price cannot be negative.";
    }


    // --- Image Upload Handling (if new image provided) ---
    $main_image_url_db_path_update = $current_main_image_url; // Keep old image if new one not uploaded
    $old_image_to_delete = null;

    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
        $image_file = $_FILES['main_image'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'products/';
        
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['main_image'] = "Failed to create product image upload directory.";
            }
        }
        if (empty($errors['main_image'])) {
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_mime_type = mime_content_type($image_file['tmp_name']);

            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $errors['main_image'] = "Invalid image file type. Allowed: JPG, PNG, GIF, WEBP.";
            } elseif ($image_file['size'] > MAX_IMAGE_SIZE) {
                $errors['main_image'] = "Image file is too large. Max size: " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB.";
            } else {
                $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
                $safe_product_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($product_name_form));
                $new_filename = $safe_product_name . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($image_file['tmp_name'], $destination)) {
                    $main_image_url_db_path_update = 'products/' . $new_filename; // New image path for DB
                    // Check if old image was a local file to delete
                    if ($current_main_image_url && $current_main_image_url !== $main_image_url_db_path_update) {
                        // Only mark for deletion if the old URL was a relative path (meaning it was uploaded to our server)
                        if (strpos($current_main_image_url, 'http') === false && strpos($current_main_image_url, '//') !== 0) {
                           $old_image_to_delete = PUBLIC_UPLOADS_PATH . $current_main_image_url;
                        }
                    }
                } else {
                    $errors['main_image'] = "Failed to upload new main image.";
                }
            }
        }
    } elseif (isset($_FILES['main_image']) && $_FILES['main_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['main_image'] = "Error uploading image: " . $_FILES['main_image']['error'];
    }


    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Update products table
            $sql_update_product = "UPDATE products SET
                                    brand_id = :brand_id,
                                    product_name = :product_name,
                                    product_description = :product_description,
                                    price = :price,
                                    compare_at_price = :compare_at_price,
                                    stock_quantity = :stock_quantity,
                                    main_image_url = :main_image_url,
                                    is_active = :is_active,
                                    requires_variants = :requires_variants,
                                    updated_at = NOW()
                                   WHERE product_id = :product_id";
            $stmt_update_product = $db->prepare($sql_update_product);

            $params_update = [
                ':brand_id' => $brand_id_form,
                ':product_name' => $product_name_form,
                ':product_description' => $product_description_form ?: null,
                ':price' => $requires_variants_form ? null : $price_form,
                ':compare_at_price' => $requires_variants_form ? null : $compare_at_price_form,
                ':stock_quantity' => $requires_variants_form ? 0 : $stock_quantity_form,
                ':main_image_url' => $main_image_url_db_path_update,
                ':is_active' => $is_active_form,
                ':requires_variants' => $requires_variants_form,
                ':product_id' => $product_id
            ];
            $stmt_update_product->execute($params_update);

            // Update product_category (delete old, insert new)
            $stmt_delete_cats = $db->prepare("DELETE FROM product_category WHERE product_id = :product_id");
            $stmt_delete_cats->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_delete_cats->execute();

            if (!empty($category_ids_form)) {
                $sql_insert_prod_cat = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
                $stmt_insert_prod_cat = $db->prepare($sql_insert_prod_cat);
                foreach ($category_ids_form as $category_id) {
                    $stmt_insert_prod_cat->execute([':product_id' => $product_id, ':category_id' => $category_id]);
                }
            }

            // Update product_images table if main image changed
            if ($main_image_url_db_path_update !== $current_main_image_url) {
                // Remove old primary image flag or delete old entry
                $stmt_remove_old_primary = $db->prepare("DELETE FROM product_images WHERE product_id = :pid AND image_url = :old_url");
                $stmt_remove_old_primary->execute([':pid' => $product_id, ':old_url' => $current_main_image_url]);

                // Add new image as primary
                if ($main_image_url_db_path_update) {
                    $sql_update_main_img_gallery = "INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary_for_product, created_at)
                                                    VALUES (:product_id, :image_url, :alt_text, 0, 1, NOW())
                                                    ON DUPLICATE KEY UPDATE image_url = VALUES(image_url), alt_text = VALUES(alt_text), is_primary_for_product = 1";
                    $stmt_update_main_img_gallery = $db->prepare($sql_update_main_img_gallery);
                    $stmt_update_main_img_gallery->execute([
                        ':product_id' => $product_id,
                        ':image_url' => $main_image_url_db_path_update,
                        ':alt_text' => $product_name_form . " main image"
                    ]);
                }
            } elseif ($main_image_url_db_path_update) {
                 // Update alt text even if image URL hasn't changed, in case product name changed
                 $stmt_alt_update = $db->prepare("UPDATE product_images SET alt_text = :alt_text WHERE product_id = :pid AND image_url = :img_url AND is_primary_for_product = 1");
                 $stmt_alt_update->execute([
                    ':alt_text' => $product_name_form . " main image",
                    ':pid' => $product_id,
                    ':img_url' => $main_image_url_db_path_update
                 ]);
            }


            $db->commit();

            // Delete old image file if replaced and it was a local file
            if ($old_image_to_delete && file_exists($old_image_to_delete)) {
                // Ensure it's a file and not a directory to prevent accidental directory deletion
                if (is_file($old_image_to_delete)) {
                    unlink($old_image_to_delete);
                } else {
                    error_log("Admin Edit Product - Old image marked for deletion was not a file: " . $old_image_to_delete);
                }
            }

            $_SESSION['admin_message'] = "<div class='admin-message success'>Product '" . htmlspecialchars($product_name_form) . "' (ID: {$product_id}) updated successfully.</div>";
            header("Location: products.php?highlight_product_id=" . $product_id); // Redirect to product list, maybe highlight
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Admin Edit Product - Error updating product: " . $e->getMessage());
            // If new image was uploaded but DB failed, delete the newly uploaded image
            if ($main_image_url_db_path_update !== $current_main_image_url && $main_image_url_db_path_update) {
                // Only delete if it was a local file and exists
                $new_uploaded_file_path = PUBLIC_UPLOADS_PATH . $main_image_url_db_path_update;
                if (strpos($main_image_url_db_path_update, 'http') === false && strpos($main_image_url_db_path_update, '//') !== 0 && file_exists($new_uploaded_file_path)) {
                    if (is_file($new_uploaded_file_path)) {
                        unlink($new_uploaded_file_path);
                    }
                }
            }
            $errors['database'] = "An error occurred while updating the product. " . $e->getMessage();
            $message = "<div class='admin-message error'>An error occurred. Please check details.</div>";
        }
    } else {
         $message = "<div class='admin-message error'>Please correct the errors below and try again.</div>";
    }
}

$admin_page_title = "Edit Product: " . htmlspecialchars($product['product_name'] ?? 'N/A'); // Update page title, handle null

?>

<h1 class="admin-page-title"><?php echo $admin_page_title; ?></h1>
<p><a href="products.php">&laquo; Back to Product List</a></p>

<?php if ($message) echo $message; ?>
<?php if (!empty($errors['database_fetch'])) echo "<div class='admin-message error'>".$errors['database_fetch']."</div>"; ?>


<form action="edit_product.php?product_id=<?php echo $product_id; ?>" method="POST" class="admin-form" enctype="multipart/form-data" style="max-width: 800px;">
    <fieldset>
        <legend>Basic Information</legend>
        <div class="form-group">
            <label for="product_name">Product Name <span style="color:red;">*</span></label>
            <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name_form ?? ''); ?>" required maxlength="255">
            <?php if (isset($errors['product_name'])): ?><small style="color:red;"><?php echo $errors['product_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brand_id">Brand <span style="color:red;">*</span></label>
            <select id="brand_id" name="brand_id" required>
                <option value="">-- Select Brand --</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo htmlspecialchars($brand['brand_id']); ?>" <?php echo ((string)$brand_id_form === (string)$brand['brand_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['brand_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['brand_id'])): ?><small style="color:red;"><?php echo $errors['brand_id']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label>Categories <span style="color:red;">*</span> (Select at least one)</label>
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; border-radius: 5px;">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="category_ids[]" value="<?php echo htmlspecialchars($category['category_id']); ?>"
                                   <?php echo in_array($category['category_id'], $category_ids_form) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No categories found. Please <a href="categories.php">add categories</a> first.</p>
                <?php endif; ?>
            </div>
            <?php if (isset($errors['category_ids'])): ?><small style="color:red;"><?php echo $errors['category_ids']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="product_description">Product Description</label>
            <textarea id="product_description" name="product_description" rows="6"><?php echo htmlspecialchars($product_description_form ?? ''); ?></textarea>
        </div>
    </fieldset>

    <fieldset>
        <legend>Pricing & Stock (for Simple Products)</legend>
        <small>If "Requires Variants" is checked, these might be overridden by variant-specific values.</small>
        <div class="form-group">
            <label for="price">Price (<?php echo CURRENCY_SYMBOL; ?>) <span class="price-stock-label-note"><?php echo $requires_variants_form ? '(Optional if variants define price)' : '<span style="color:red;">*</span>'; ?></span></label>
            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($price_form ?? ''); ?>" step="0.01" min="0" placeholder="e.g., 199.99">
            <?php if (isset($errors['price'])): ?><small style="color:red;"><?php echo $errors['price']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="compare_at_price">Compare at Price (<?php echo CURRENCY_SYMBOL; ?>)</label>
            <input type="number" id="compare_at_price" name="compare_at_price" value="<?php echo htmlspecialchars($compare_at_price_form ?? ''); ?>" step="0.01" min="0" placeholder="Optional 'was' price">
            <?php if (isset($errors['compare_at_price'])): ?><small style="color:red;"><?php echo $errors['compare_at_price']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="stock_quantity">Stock Quantity <span class="price-stock-label-note"><?php echo $requires_variants_form ? '(Optional if variants define stock)' : ''; ?></span></label>
            <input type="number" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($stock_quantity_form ?? ''); ?>" step="1" min="0" placeholder="e.g., 50">
            <?php if (isset($errors['stock_quantity'])): ?><small style="color:red;"><?php echo $errors['stock_quantity']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Images</legend>
        <div class="form-group">
            <label>Current Main Product Image</label>
            <?php
            // Determine the correct image path for display
            $display_image_url = '';
            if (!empty($current_main_image_url)) {
                // Check if it's an absolute URL (starts with http:// or https:// or //)
                if (filter_var($current_main_image_url, FILTER_VALIDATE_URL) || strpos($current_main_image_url, '//') === 0) {
                    $display_image_url = htmlspecialchars($current_main_image_url);
                } else {
                    // It's a relative path, prepend PUBLIC_UPLOADS_URL_BASE
                    $display_image_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $current_main_image_url);
                }
            } else {
                // No image URL, use placeholder
                $display_image_url = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '150x150/eee/aaa?text=No+Img');
            }
            $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '150x150/eee/aaa?text=Error');
            ?>
            <?php if ($display_image_url): ?>
                <img src="<?php echo $display_image_url; ?>" alt="Current Main Image" style="max-width: 150px; max-height: 150px; display:block; margin-bottom:10px; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                <small>Current: <?php echo htmlspecialchars($current_main_image_url ?? 'N/A'); ?></small>
            <?php else: ?>
                <p>No main image currently set.</p>
            <?php endif; ?>
            <label for="main_image_upload" style="margin-top:10px; display:block;">Upload New Main Image (Optional - Replaces current)</label>
            <input type="file" id="main_image_upload" name="main_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small>Recommended size: 800x800px. Max file size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '2'; ?>MB.</small>
            <?php if (isset($errors['main_image'])): ?><small style="color:red;"><?php echo $errors['main_image']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Settings</legend>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo $is_active_form ? 'checked' : ''; ?>>
                Product is Active (Visible to customers)
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="requires_variants" value="1" <?php echo $requires_variants_form ? 'checked' : ''; ?> onchange="togglePriceStockRequired(this.checked)">
                This Product has Variants (e.g., Size, Color)
            </label>
            <small>If checked, price and stock may be set per variant. Variant management will be available after saving basic product details.</small>
        </div>
    </fieldset>

    <button type="submit" name="update_product" class="btn-submit">Update Product</button>
</form>

<hr style="margin: 30px 0;">

<div id="manage-additional-images" class="admin-section">
    <h2 class="admin-section-title">Manage Additional Images</h2>
    <p class="admin-message info">Functionality to upload, reorder, and delete additional product images will be available here soon.</p>
    </div>

<div id="manage-product-variants" class="admin-section" style="margin-top: 30px;">
    <h2 class="admin-section-title">Manage Product Variants</h2>
    <?php if ($requires_variants_form): ?>
        <p class="admin-message info">Functionality to define attributes (e.g., Size, Color), add variant combinations, set their prices, stock, and images will be available here soon.</p>
        <?php else: ?>
        <p class="admin-message info">To manage variants, first check the "This Product has Variants" option in the settings above and save the product.</p>
    <?php endif; ?>
</div>


<script>
function togglePriceStockRequired(requiresVariants) {
    const priceLabelNotes = document.querySelectorAll('.price-stock-label-note');

    priceLabelNotes.forEach(note => {
        if (requiresVariants) {
            // For variant-enabled products, price/stock on the main product become optional
            note.innerHTML = '(Optional if variants define price/stock)';
        } else {
            // For simple products, price and stock are generally required
            // Re-evaluate to specifically target price and stock for '*'
            if (note.closest('.form-group').querySelector('label[for="price"]')) {
                note.innerHTML = '<span style="color:red;">*</span>';
            } else if (note.closest('.form-group').querySelector('label[for="stock_quantity"]')) {
                // Stock quantity isn't strictly required by PHP for simple products (defaults to 0),
                // but if you want it marked as visually required, keep this.
                // Otherwise, remove the '*' for stock_quantity here.
                note.innerHTML = '<span style="color:red;">*</span>';
            } else {
                note.innerHTML = ''; // Default for other cases
            }
        }
    });

    // You can also directly set the 'required' attribute on the input fields
    const priceInput = document.getElementById('price');
    const stockInput = document.getElementById('stock_quantity');

    if (priceInput) {
        // Only make price 'required' in HTML if not requiring variants
        // PHP validation still handles null/empty specifically
        priceInput.required = !requiresVariants;
    }
    // You might not want to make stock_quantity HTML required if 0 is a valid default
    // stockInput.required = !requiresVariants;
}

// Initialize on page load based on checkbox state
document.addEventListener('DOMContentLoaded', function() {
    const requiresVariantsCheckbox = document.querySelector('input[name="requires_variants"]');
    if (requiresVariantsCheckbox) {
        togglePriceStockRequired(requiresVariantsCheckbox.checked);
    }
});
</script>

<?php
include_once 'includes/footer.php';
?>