<?php
// admin/add_product.php - Super Admin: Add New Product

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ADD PRODUCT ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Add New Product";
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Default form values
$product_name_form = '';
$brand_id_form = '';
$category_ids_form = [];
$product_description_form = '';
$price_form = '';
$compare_at_price_form = '';
$stock_quantity_form = '0';
$is_active_form = 1; // Default to active
$requires_variants_form = 0;

// --- Fetch Brands for Dropdown ---
$brands = [];
try {
    $stmt_brands = $db->query("SELECT brand_id, brand_name FROM brands WHERE is_approved = 1 ORDER BY brand_name ASC");
    $brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = "Could not load brands.";
}

// --- Fetch Categories for Checkboxes ---
$categories = [];
try {
    $stmt_categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = "Could not load categories.";
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name_form = trim(filter_input(INPUT_POST, 'product_name', FILTER_UNSAFE_RAW));
    $brand_id_form = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);
    $category_ids_form = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
    $product_description_form = trim(filter_input(INPUT_POST, 'product_description', FILTER_UNSAFE_RAW));
    $price_input = filter_input(INPUT_POST, 'price', FILTER_UNSAFE_RAW);
    $compare_at_price_input = filter_input(INPUT_POST, 'compare_at_price', FILTER_UNSAFE_RAW);
    $stock_quantity_input = filter_input(INPUT_POST, 'stock_quantity', FILTER_UNSAFE_RAW);
    $price_form = ($price_input === '' || $price_input === null) ? null : filter_var($price_input, FILTER_VALIDATE_FLOAT);
    $compare_at_price_form = ($compare_at_price_input === '' || $compare_at_price_input === null) ? null : filter_var($compare_at_price_input, FILTER_VALIDATE_FLOAT);
    $stock_quantity_form = ($stock_quantity_input === '' || $stock_quantity_input === null) ? 0 : filter_var($stock_quantity_input, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
    $is_active_form = !isset($_POST['add_product']) ? 1 : (isset($_POST['is_active']) ? 1 : 0);
    $requires_variants_form = isset($_POST['requires_variants']) ? 1 : 0;

    if (empty($product_name_form)) $errors['product_name'] = "Product name is required.";
    if (empty($brand_id_form)) $errors['brand_id'] = "Brand is required.";
    if (empty($category_ids_form)) $errors['category_ids'] = "At least one category is required.";
    if ($price_form === null && !$requires_variants_form) $errors['price'] = "Price is required for simple products.";
    
    // ... (rest of validation)

    $main_image_url_db_path = null;
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
        // Image handling logic as before...
        $image_file = $_FILES['main_image'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'products/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['main_image'] = "Failed to create product image upload directory.";
            }
        }
        if (empty($errors['main_image'])) {
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array(mime_content_type($image_file['tmp_name']), $allowed_mime_types) && $image_file['size'] <= MAX_IMAGE_SIZE) {
                $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
                $safe_product_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($product_name_form));
                $new_filename = $safe_product_name . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;
                if (move_uploaded_file($image_file['tmp_name'], $destination)) {
                    $main_image_url_db_path = 'products/' . $new_filename;
                } else {
                    $errors['main_image'] = "Failed to upload main image.";
                }
            } else {
                 $errors['main_image'] = "Invalid image file type or size.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $sql_insert_product = "INSERT INTO products (brand_id, product_name, product_description, price, compare_at_price, stock_quantity, main_image_url, is_active, is_featured, requires_variants, created_at, updated_at)
                                   VALUES (:brand_id, :product_name, :product_description, :price, :compare_at_price, :stock_quantity, :main_image_url, :is_active, 0, :requires_variants, NOW(), NOW())";
            $stmt_insert_product = $db->prepare($sql_insert_product);

            $params_insert = [
                ':brand_id' => $brand_id_form,
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

            if ($new_product_id && !empty($category_ids_form)) {
                $sql_insert_prod_cat = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
                $stmt_insert_prod_cat = $db->prepare($sql_insert_prod_cat);
                foreach ($category_ids_form as $category_id) {
                    $stmt_insert_prod_cat->execute([':product_id' => $new_product_id, ':category_id' => $category_id]);
                }
            }
            
            if ($new_product_id && $main_image_url_db_path) {
                $sql_insert_main_img_gallery = "INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary_for_product) VALUES (:pid, :url, :alt, 0, 1)";
                $stmt_img = $db->prepare($sql_insert_main_img_gallery);
                $stmt_img->execute([':pid' => $new_product_id, ':url' => $main_image_url_db_path, ':alt' => $product_name_form]);
            }

            $db->commit();
            $_SESSION['admin_message'] = "<div class='admin-message success'>Product '" . htmlspecialchars($product_name_form) . "' added successfully.</div>";
            header("Location: products.php");
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            if ($main_image_url_db_path && file_exists(PUBLIC_UPLOADS_PATH . $main_image_url_db_path)) {
                unlink(PUBLIC_UPLOADS_PATH . $main_image_url_db_path);
            }
            $message = "<div class='admin-message error'>An error occurred while adding the product.</div>";
        }
    } else {
        $message = "<div class='admin-message error'>Please correct the errors below.</div>";
    }
}
?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="products.php">&laquo; Back to Product List</a></p>

<?php if ($message) echo $message; ?>

<form action="add_product.php" method="POST" class="admin-form" enctype="multipart/form-data" style="max-width: 800px;">
    <fieldset>
        <legend>Basic Information</legend>
        <div class="form-group">
            <label for="product_name">Product Name <span style="color:red;">*</span></label>
            <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name_form); ?>" required>
        </div>
        <div class="form-group">
            <label for="brand_id">Brand <span style="color:red;">*</span></label>
            <select id="brand_id" name="brand_id" required>
                <option value="">-- Select Brand --</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo htmlspecialchars($brand['brand_id']); ?>" <?php echo ($brand_id_form == $brand['brand_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['brand_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Categories <span style="color:red;">*</span></label>
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; border-radius: 5px;">
                <?php foreach ($categories as $category): ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="category_ids[]" value="<?php echo htmlspecialchars($category['category_id']); ?>" <?php echo in_array($category['category_id'], $category_ids_form) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="product_description">Product Description</label>
            <textarea id="product_description" name="product_description" rows="6"><?php echo htmlspecialchars($product_description_form); ?></textarea>
        </div>
    </fieldset>

    <fieldset>
        <legend>Pricing & Stock</legend>
        <div class="form-group">
            <label for="price">Price (<?php echo $GLOBALS['currency_symbol']; ?>)</label>
            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($price_form); ?>" step="0.01" min="0">
        </div>
        <div class="form-group">
            <label for="compare_at_price">Compare at Price (<?php echo $GLOBALS['currency_symbol']; ?>)</label>
            <input type="number" id="compare_at_price" name="compare_at_price" value="<?php echo htmlspecialchars($compare_at_price_form); ?>" step="0.01" min="0">
        </div>
        <div class="form-group">
            <label for="stock_quantity">Stock Quantity</label>
            <input type="number" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($stock_quantity_form); ?>" step="1" min="0">
        </div>
    </fieldset>

    <fieldset>
        <legend>Images</legend>
        <div class="form-group">
            <label for="main_image">Main Product Image</label>
            <input type="file" id="main_image" name="main_image" accept="image/*">
        </div>
    </fieldset>

    <fieldset>
        <legend>Settings</legend>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo $is_active_form ? 'checked' : ''; ?>>
                Product is Active
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="requires_variants" value="1" <?php echo $requires_variants_form ? 'checked' : ''; ?>>
                This Product has Variants
            </label>
        </div>
    </fieldset>

    <button type="submit" name="add_product" class="btn-submit">Add Product</button>
</form>

<?php include_once 'includes/footer.php'; ?>