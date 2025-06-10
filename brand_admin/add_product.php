<?php
// brand_admin/add_product.php - Brand Admin: Add New Product

$brand_admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL BRAND ADMIN ADD PRODUCT ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is brand_admin and sets $_SESSION['brand_id']

$brand_admin_page_title = "Add New Product";
include_once 'includes/header.php';

$db = getPDOConnection();

// Get the assigned brand ID from the session
$current_brand_id = $_SESSION['brand_id'];
$current_brand_name = $_SESSION['brand_name'];

$errors = [];
$message = '';

// Form data placeholders - FIX: Initialize with empty strings for htmlspecialchars safety
$product_name_form = '';
$category_ids_form = []; // Array for multiple categories
$product_description_form = '';
$price_form = '';
$compare_at_price_form = '';
$stock_quantity_form = '0';
$is_active_form = 1; // Default to active
$requires_variants_form = 0; // Default to simple product


// --- Fetch Categories for Checkboxes/Select ---
$categories = [];
try {
    $stmt_categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Brand Admin Add Product - Error fetching categories: " . $e->getMessage());
    $errors['database'] = "Could not load categories. Please try again.";
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Sanitize and validate inputs
    $product_name_form = trim(substr(filter_input(INPUT_POST, 'product_name', FILTER_UNSAFE_RAW), 0, 255)); // FIX: Substr for maxlength
    $category_ids_form = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
    $product_description_form = trim(filter_input(INPUT_POST, 'product_description', FILTER_UNSAFE_RAW));

    $price_input = filter_input(INPUT_POST, 'price', FILTER_UNSAFE_RAW);
    $compare_at_price_input = filter_input(INPUT_POST, 'compare_at_price', FILTER_UNSAFE_RAW);
    $stock_quantity_input = filter_input(INPUT_POST, 'stock_quantity', FILTER_UNSAFE_RAW);

    // FIX: Ensure filter_var results in null for empty string or values.
    $price_form = ($price_input === '' || $price_input === null) ? null : filter_var($price_input, FILTER_VALIDATE_FLOAT);
    $compare_at_price_form = ($compare_at_price_input === '' || $compare_at_price_input === null) ? null : filter_var($compare_at_price_input, FILTER_VALIDATE_FLOAT);
    $stock_quantity_form = ($stock_quantity_input === '' || $stock_quantity_input === null) ? 0 : filter_var($stock_quantity_input, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);

    $is_active_form = isset($_POST['is_active']) ? 1 : 0;
    $requires_variants_form = isset($_POST['requires_variants']) ? 1 : 0;

    // Basic Validation
    if (empty($product_name_form)) $errors['product_name'] = "Product name is required.";
    // FIX: Add maxlength validation for product_name
    if (strlen($product_name_form) > 255) $errors['product_name'] = "Product name cannot exceed 255 characters.";
    if (empty($category_ids_form)) $errors['category_ids'] = "At least one category is required.";

    if ($price_form === null && !$requires_variants_form) { // Price is required for simple products
        $errors['price'] = "Price is required for simple products.";
    } elseif ($price_form !== null && $price_form < 0) {
        $errors['price'] = "Price cannot be negative.";
    }
    if ($compare_at_price_form !== null && $compare_at_price_form < 0) {
        $errors['compare_at_price'] = "Compare at price cannot be negative.";
    }
    if ($stock_quantity_form === false || $stock_quantity_form < 0) { // false if validation failed
        $errors['stock_quantity'] = "Stock quantity must be a valid non-negative number.";
        $stock_quantity_form = 0; // Reset to default on error for sticky form
    }


    // --- Image Upload Handling ---
    $main_image_url_db_path = null;
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
        $image_file = $_FILES['main_image'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'products/'; // Ensure this directory exists and is writable
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) { // Create directory if it doesn't exist
                 $errors['main_image'] = "Failed to create product image upload directory.";
            }
        }

        if (empty($errors['main_image'])) {
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_mime_type = mime_content_type($image_file['tmp_name']);

            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $errors['main_image'] = "Invalid image file type. Allowed: JPG, PNG, GIF, WEBP.";
            } elseif ($image_file['size'] > MAX_IMAGE_SIZE) { // MAX_IMAGE_SIZE from config.php
                $errors['main_image'] = "Image file is too large. Max size: " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB.";
            } else {
                $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
                $safe_product_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($product_name_form));
                $new_filename = $safe_product_name . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($image_file['tmp_name'], $destination)) {
                    $main_image_url_db_path = 'products/' . $new_filename; // Relative path for DB
                } else {
                    $errors['main_image'] = "Failed to upload main image. Check permissions or server error.";
                    error_log("Failed to move uploaded file: " . $image_file['name'] . " to " . $destination);
                }
            }
        }
    } elseif (isset($_FILES['main_image']) && $_FILES['main_image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $errors['main_image'] = "Error uploading image: " . $_FILES['main_image']['error'];
    }


    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Insert into products table, using the brand_id from the session
            $sql_insert_product = "INSERT INTO products (brand_id, product_name, product_description, price, compare_at_price, stock_quantity, main_image_url, is_active, is_featured, requires_variants, created_at, updated_at)
                                   VALUES (:brand_id, :product_name, :product_description, :price, :compare_at_price, :stock_quantity, :main_image_url, :is_active, 0, :requires_variants, NOW(), NOW())";
            $stmt_insert_product = $db->prepare($sql_insert_product);

            $params_insert = [
                ':brand_id' => $current_brand_id, // Use brand ID from session
                ':product_name' => $product_name_form,
                ':product_description' => $product_description_form ?: null,
                ':price' => $requires_variants_form ? null : $price_form,
                ':compare_at_price' => $requires_variants_form ? null : $compare_at_price_form,
                ':stock_quantity' => $requires_variants_form ? 0 : $stock_quantity_form,
                ':main_image_url' => $main_image_url_db_path,
                ':is_active' => $is_active_form,
                ':requires_variants' => $requires_variants_form
            ];

            $stmt_insert_product->execute($params_insert);
            $new_product_id = $db->lastInsertId();

            // Insert into product_category table
            if ($new_product_id && !empty($category_ids_form)) {
                $sql_insert_prod_cat = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
                $stmt_insert_prod_cat = $db->prepare($sql_insert_prod_cat);
                foreach ($category_ids_form as $category_id) {
                    $stmt_insert_prod_cat->execute([':product_id' => $new_product_id, ':category_id' => $category_id]);
                }
            }

            // If main image was uploaded and it's the only one for now, also add to product_images
            if ($new_product_id && $main_image_url_db_path) {
                $sql_insert_main_img_gallery = "INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary_for_product, created_at)
                                                VALUES (:product_id, :image_url, :alt_text, 0, 1, NOW())";
                $stmt_insert_main_img_gallery = $db->prepare($sql_insert_main_img_gallery);
                $stmt_insert_main_img_gallery->execute([
                    ':product_id' => $new_product_id,
                    ':image_url' => $main_image_url_db_path,
                    ':alt_text' => $product_name_form . " main image"
                ]);
            }

            $db->commit();
            $_SESSION['brand_admin_message'] = "<div class='brand-admin-message success'>Product '" . htmlspecialchars($product_name_form) . "' added successfully (ID: {$new_product_id}).</div>";
            header("Location: products.php"); // Redirect to product list
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Brand Admin Add Product - Error inserting product: " . $e->getMessage());
            // FIX: Add check for file_exists and is_file()
            if ($main_image_url_db_path && file_exists(PUBLIC_UPLOADS_PATH . $main_image_url_db_path)) {
                if (is_file(PUBLIC_UPLOADS_PATH . $main_image_url_db_path)) {
                    unlink(PUBLIC_UPLOADS_PATH . $main_image_url_db_path); // Delete uploaded image if DB insert fails
                } else {
                    error_log("Brand Admin Add Product - Newly uploaded image was not a file during DB insert failure cleanup: " . PUBLIC_UPLOADS_PATH . $main_image_url_db_path);
                }
            }
            $errors['database'] = "An error occurred while adding the product. " . $e->getMessage();
            $message = "<div class='brand-admin-message error'>An error occurred while adding the product. Please check the details and try again.</div>";
        }
    } else {
        $message = "<div class='brand-admin-message error'>Please correct the errors below and try again.</div>";
    }
}

?>

<h1 class="brand-admin-page-title"><?php echo htmlspecialchars($brand_admin_page_title); ?> for <?php echo htmlspecialchars($current_brand_name); ?></h1>
<p><a href="products.php">&laquo; Back to Product List</a></p>

<?php if ($message) echo $message; ?>

<form action="add_product.php" method="POST" class="brand-admin-form" enctype="multipart/form-data" style="max-width: 800px;">
    <fieldset>
        <legend>Basic Information</legend>
        <div class="form-group">
            <label for="product_name">Product Name <span style="color:red;">*</span></label>
            <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name_form); ?>" required maxlength="255"> <?php if (isset($errors['product_name'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['product_name']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label>Brand</label>
            <input type="text" value="<?php echo htmlspecialchars($current_brand_name); ?>" disabled>
            <small>This product will be associated with your brand: <?php echo htmlspecialchars($current_brand_name); ?></small>
        </div>

        <div class="form-group">
            <label>Categories <span style="color:red;">*</span> (Select at least one)</label>
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; border-radius: 5px;">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="category_ids[]" value="<?php echo htmlspecialchars($category['category_id']); ?>" <?php echo in_array($category['category_id'], $category_ids_form) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No categories found. Please contact Super Admin to add categories.</p>
                <?php endif; ?>
            </div>
            <?php if (isset($errors['category_ids'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['category_ids']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="product_description">Product Description</label>
            <textarea id="product_description" name="product_description" rows="6"><?php echo htmlspecialchars($product_description_form); ?></textarea>
        </div>
    </fieldset>

    <fieldset>
        <legend>Pricing & Stock (for Simple Products)</legend>
        <small>If "Requires Variants" is checked, these might be overridden by variant-specific values.</small>
        <div class="form-group">
            <label for="price">Price (<?php echo $GLOBALS['currency_symbol']; ?>) <span class="price-stock-label-note"><?php echo $requires_variants_form ? '(Optional if variants define price)' : '<span style="color:red;">*</span>'; ?></span></label>
            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($price_form ?? ''); ?>" step="0.01" min="0" placeholder="e.g., 199.99"> <?php if (isset($errors['price'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['price']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="compare_at_price">Compare at Price (<?php echo $GLOBALS['currency_symbol']; ?>)</label>
            <input type="number" id="compare_at_price" name="compare_at_price" value="<?php echo htmlspecialchars($compare_at_price_form ?? ''); ?>" step="0.01" min="0" placeholder="Optional 'was' price"> <?php if (isset($errors['compare_at_price'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['compare_at_price']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="stock_quantity">Stock Quantity <span class="price-stock-label-note"><?php echo $requires_variants_form ? '(Optional if variants define stock)' : ''; ?></span></label>
            <input type="number" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($stock_quantity_form ?? ''); ?>" step="1" min="0" placeholder="e.g., 50"> <?php if (isset($errors['stock_quantity'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['stock_quantity']); ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Images</legend>
        <div class="form-group">
            <label for="main_image">Main Product Image</label>
            <input type="file" id="main_image" name="main_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small>Recommended size: 800x800px. Max file size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '2'; ?>MB.</small>
            <?php if (isset($errors['main_image'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['main_image']); ?></small><?php endif; ?>
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
            <small>If checked, you will add variants (like sizes, colors) after creating the base product. Price and stock may be set per variant.</small>
        </div>
    </fieldset>

    <button type="submit" name="add_product" class="btn-submit">Add Product</button>
</form>

<script>
function togglePriceStockRequired(requiresVariants) {
    const priceLabelNote = document.querySelector('label[for="price"] .price-stock-label-note'); // Select specific note for price
    const stockLabelNote = document.querySelector('label[for="stock_quantity"] .price-stock-label-note'); // Select specific note for stock
    const priceInput = document.getElementById('price');
    const stockInput = document.getElementById('stock_quantity');


    if (priceLabelNote) {
        if (requiresVariants) {
            priceLabelNote.innerHTML = '(Optional if variants define price)';
            if (priceInput) priceInput.required = false;
        } else {
            priceLabelNote.innerHTML = '<span style="color:red;">*</span>';
            if (priceInput) priceInput.required = true;
        }
    }

    if (stockLabelNote) {
        if (requiresVariants) {
            stockLabelNote.innerHTML = '(Optional if variants define stock)';
            if (stockInput) stockInput.required = false; // Stock is optional if variants exist
        } else {
            stockLabelNote.innerHTML = ''; // No '*' for stock for simple products, as 0 is a valid default in DB
            // If you *want* stock to be HTML required for simple products, uncomment and set `required = true`
            // stockLabelNote.innerHTML = '<span style="color:red;">*</span>';
            // if (stockInput) stockInput.required = true;
        }
    }
}
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