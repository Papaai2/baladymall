<?php
// brand_admin/batch_upload_products.php - Brand Admin: Batch Upload Products

$brand_admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL BRAND ADMIN BATCH UPLOAD ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is brand_admin and sets $_SESSION['brand_id']

$brand_admin_page_title = "Batch Upload Products";
include_once 'includes/header.php';

$db = getPDOConnection();

// Get the assigned brand ID from the session
$current_brand_id = $_SESSION['brand_id'];
$current_brand_name = $_SESSION['brand_name'];

$message = '';
$errors = [];
$upload_results = []; // To store success/failure messages for each row

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Handle File Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_products'])) {
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if (!$csrf_token_form || !hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $errors['csrf'] = "CSRF token mismatch. Please try again.";
    } elseif (!isset($_FILES['product_csv']) || $_FILES['product_csv']['error'] !== UPLOAD_ERR_OK) {
        $errors['file_upload'] = "Error uploading file. Please ensure a file is selected and it's within size limits.";
        switch ($_FILES['product_csv']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors['file_upload'] .= " File is too large.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors['file_upload'] .= " File upload was interrupted.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors['file_upload'] = "No file was uploaded.";
                break;
            default:
                $errors['file_upload'] .= " Unknown error code: " . $_FILES['product_csv']['error'];
        }
    } else {
        $file_mimetype = mime_content_type($_FILES['product_csv']['tmp_name']);
        $allowed_mimetypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']; // CSV and Excel
        $file_extension = strtolower(pathinfo($_FILES['product_csv']['name'], PATHINFO_EXTENSION));

        if (!in_array($file_mimetype, $allowed_mimetypes) && !in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            $errors['file_type'] = "Invalid file type. Only CSV or Excel (XLSX, XLS) files are allowed.";
        } elseif ($_FILES['product_csv']['size'] > 10 * 1024 * 1024) { // 10MB limit for batch upload
            $errors['file_size'] = "File is too large. Maximum 10MB allowed.";
        }
    }

    if (empty($errors)) {
        $uploaded_file_path = $_FILES['product_csv']['tmp_name'];
        $file_extension = strtolower(pathinfo($_FILES['product_csv']['name'], PATHINFO_EXTENSION));

        // --- CSV Parsing Logic (Placeholder) ---
        if ($file_extension === 'csv') {
            if (($handle = fopen($uploaded_file_path, "r")) !== FALSE) {
                $row_number = 0;
                $header = [];
                $processed_count = 0;
                $failed_count = 0;

                // Fetch all categories for validation lookup
                $all_categories = [];
                try {
                    $stmt_cats = $db->query("SELECT category_id, category_name FROM categories");
                    while ($row = $stmt_cats->fetch(PDO::FETCH_ASSOC)) {
                        $all_categories[strtolower($row['category_name'])] = $row['category_id'];
                    }
                } catch (PDOException $e) {
                    error_log("Batch Upload: Error fetching categories: " . $e->getMessage());
                    $errors['db_categories'] = "Could not load categories for validation.";
                }

                if (empty($errors['db_categories'])) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row_number++;
                        if ($row_number === 1) {
                            // Header row
                            $header = array_map('trim', $data);
                            // Expected headers (case-insensitive for matching)
                            $expected_headers = [
                                'product_name', 'product_description', 'price', 'compare_at_price',
                                'stock_quantity', 'main_image_url', 'is_active', 'requires_variants',
                                'categories' // Comma-separated category names
                            ];
                            $missing_headers = array_diff($expected_headers, array_map('strtolower', $header));
                            if (!empty($missing_headers)) {
                                $errors['csv_format'] = "Missing required CSV headers: " . implode(', ', $missing_headers) . ". Please use the template.";
                                break; // Stop processing if headers are wrong
                            }
                            continue;
                        }

                        // Map CSV data to associative array using headers
                        $row_data = [];
                        foreach ($header as $index => $col_name) {
                            $row_data[strtolower($col_name)] = $data[$index] ?? '';
                        }

                        // --- Product Data Extraction and Validation ---
                        $product_errors = [];
                        $product_name = $row_data['product_name'];
                        $product_description = $row_data['product_description'] ?: null;
                        $price = filter_var($row_data['price'], FILTER_VALIDATE_FLOAT);
                        $compare_at_price = filter_var($row_data['compare_at_price'], FILTER_VALIDATE_FLOAT);
                        $stock_quantity = filter_var($row_data['stock_quantity'], FILTER_VALIDATE_INT);
                        $main_image_url = $row_data['main_image_url'] ?: null; // This should be a full URL for external images, or a path to an existing uploaded image
                        $is_active = (strtolower($row_data['is_active']) === 'yes' || $row_data['is_active'] === '1') ? 1 : 0;
                        $requires_variants = (strtolower($row_data['requires_variants']) === 'yes' || $row_data['requires_variants'] === '1') ? 1 : 0;
                        $categories_raw = $row_data['categories'];

                        if (empty($product_name)) $product_errors[] = "Product Name is required.";
                        if ($price === false && !$requires_variants) $product_errors[] = "Price is required for simple products.";
                        if ($price !== false && $price < 0) $product_errors[] = "Price cannot be negative.";
                        if ($compare_at_price !== false && $compare_at_price < 0) $product_errors[] = "Compare at price cannot be negative.";
                        if ($stock_quantity === false || $stock_quantity < 0) $product_errors[] = "Stock quantity must be a non-negative number.";

                        $product_category_ids = [];
                        if (empty($categories_raw)) {
                            $product_errors[] = "At least one category is required.";
                        } else {
                            $category_names_array = array_map('trim', explode(',', $categories_raw));
                            foreach ($category_names_array as $cat_name) {
                                if (isset($all_categories[strtolower($cat_name)])) {
                                    $product_category_ids[] = $all_categories[strtolower($cat_name)];
                                } else {
                                    $product_errors[] = "Category '{$cat_name}' not found. Please ensure categories exist before uploading products.";
                                }
                            }
                            if (empty($product_category_ids) && empty($product_errors)) { // If categories were provided but none matched
                                $product_errors[] = "No valid categories found for this product.";
                            }
                        }

                        // Handle main_image_url: For batch upload, this is usually an external URL or a path to a pre-uploaded image.
                        // We are NOT handling image uploads from CSV rows directly here.
                        // If it's a URL, you might want to validate it or download it later.
                        // For now, we'll just store the provided URL/path.

                        if (empty($product_errors)) {
                            // --- Database Insertion ---
                            try {
                                $db->beginTransaction();

                                $sql_insert_product = "INSERT INTO products (brand_id, product_name, product_description, price, compare_at_price, stock_quantity, main_image_url, is_active, is_featured, requires_variants, created_at, updated_at)
                                                       VALUES (:brand_id, :product_name, :product_description, :price, :compare_at_price, :stock_quantity, :main_image_url, :is_active, 0, :requires_variants, NOW(), NOW())";
                                $stmt_insert_product = $db->prepare($sql_insert_product);

                                $params_insert = [
                                    ':brand_id' => $current_brand_id,
                                    ':product_name' => $product_name,
                                    ':product_description' => $product_description,
                                    ':price' => $requires_variants ? null : $price,
                                    ':compare_at_price' => $requires_variants ? null : $compare_at_price,
                                    ':stock_quantity' => $requires_variants ? 0 : $stock_quantity,
                                    ':main_image_url' => $main_image_url,
                                    ':is_active' => $is_active,
                                    ':requires_variants' => $requires_variants
                                ];

                                $stmt_insert_product->execute($params_insert);
                                $new_product_id = $db->lastInsertId();

                                // Insert into product_category table
                                if ($new_product_id && !empty($product_category_ids)) {
                                    $sql_insert_prod_cat = "INSERT INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)";
                                    $stmt_insert_prod_cat = $db->prepare($sql_insert_prod_cat);
                                    foreach ($product_category_ids as $category_id) {
                                        $stmt_insert_prod_cat->execute([':product_id' => $new_product_id, ':category_id' => $category_id]);
                                    }
                                }

                                // Add to product_images if main_image_url is provided
                                if ($new_product_id && $main_image_url) {
                                    $sql_insert_main_img_gallery = "INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary_for_product, created_at)
                                                                    VALUES (:product_id, :image_url, :alt_text, 0, 1, NOW())";
                                    $stmt_insert_main_img_gallery = $db->prepare($sql_insert_main_img_gallery);
                                    $stmt_insert_main_img_gallery->execute([
                                        ':product_id' => $new_product_id,
                                        ':image_url' => $main_image_url,
                                        ':alt_text' => $product_name . " main image"
                                    ]);
                                }

                                $db->commit();
                                $upload_results[] = ['status' => 'success', 'message' => "Row {$row_number}: Product '{$product_name}' added successfully (ID: {$new_product_id})."];
                                $processed_count++;

                            } catch (PDOException $e) {
                                $db->rollBack();
                                $upload_results[] = ['status' => 'error', 'message' => "Row {$row_number}: Database error for '{$product_name}': " . $e->getMessage()];
                                $failed_count++;
                            }
                        } else {
                            $upload_results[] = ['status' => 'error', 'message' => "Row {$row_number}: Validation failed for '{$product_name}': " . implode(', ', $product_errors)];
                            $failed_count++;
                        }
                    }
                } // End if (empty($errors['db_categories']))
                fclose($handle);

                if (empty($errors)) {
                    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message info'>Batch upload complete. Processed: {$processed_count}, Failed: {$failed_count}.</div>";
                }

            } else {
                $errors['file_read'] = "Could not open the uploaded CSV file.";
            }
        } elseif (in_array($file_extension, ['xlsx', 'xls'])) {
            $errors['excel_support'] = "Excel file parsing is not yet implemented. Please convert your file to CSV format.";
            // For full Excel support, you would need a library like PhpSpreadsheet
            // require 'vendor/autoload.php'; // Assuming Composer for PhpSpreadsheet
            // use PhpOffice\PhpSpreadsheet\IOFactory;
            // $spreadsheet = IOFactory::load($uploaded_file_path);
            // $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            // ... then process $sheetData similar to CSV
        }
    }

    if (!empty($errors)) {
        $message = "<div class='brand-admin-message error'>Please correct the following issues:<br><ul>";
        foreach ($errors as $error_msg) {
            $message .= "<li>" . htmlspecialchars($error_msg) . "</li>";
        }
        $message .= "</ul></div>";
    }
}

?>

<h1 class="brand-admin-page-title"><?php echo htmlspecialchars($brand_admin_page_title); ?> for <?php echo htmlspecialchars($current_brand_name); ?></h1>
<p><a href="products.php">&laquo; Back to Product List</a></p>

<?php
if (isset($_SESSION['brand_admin_message'])) {
    echo $_SESSION['brand_admin_message'];
    unset($_SESSION['brand_admin_message']);
}
if ($message) echo $message;
?>

<div class="brand-admin-section">
    <h2 class="brand-admin-section-title">Upload Product CSV/Excel File</h2>
    <p class="brand-admin-message info">
        Download the <a href="templates/product_upload_template.csv" download>CSV template</a> to ensure your file is in the correct format.
        <br>
        <strong>Required Columns:</strong> `product_name`, `product_description`, `price`, `compare_at_price`, `stock_quantity`, `main_image_url`, `is_active` (use 'yes' or '1' for active, 'no' or '0' for inactive), `requires_variants` (use 'yes' or '1' for variants, 'no' or '0' for simple), `categories` (comma-separated category names, e.g., "Electronics, Laptops").
        <br>
        For `main_image_url`, provide a full URL to an existing image or a relative path if the image is already in `public/uploads/products/`.
    </p>

    <form action="batch_upload_products.php" method="POST" enctype="multipart/form-data" class="brand-admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <fieldset>
            <legend>Upload File</legend>
            <div class="form-group">
                <label for="product_csv">Select CSV or Excel File:</label>
                <input type="file" id="product_csv" name="product_csv" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                <small>Max file size: 10MB. Supported formats: CSV, XLSX, XLS.</small>
                <?php if (isset($errors['file_upload'])): ?><small style="color:red;"><?php echo $errors['file_upload']; ?></small><?php endif; ?>
                <?php if (isset($errors['file_type'])): ?><small style="color:red;"><?php echo $errors['file_type']; ?></small><?php endif; ?>
                <?php if (isset($errors['file_size'])): ?><small style="color:red;"><?php echo $errors['file_size']; ?></small><?php endif; ?>
            </div>
        </fieldset>
        <button type="submit" name="upload_products" class="btn-submit">Upload Products</button>
    </form>

    <?php if (!empty($upload_results)): ?>
        <h3 style="margin-top: 30px;">Upload Results:</h3>
        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 15px; border-radius: 6px; background-color: #fdfdfd;">
            <?php foreach ($upload_results as $result): ?>
                <p style="color: <?php echo ($result['status'] === 'success') ? 'green' : 'red'; ?>;">
                    <?php echo htmlspecialchars($result['message']); ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
include_once 'includes/footer.php';
?>
