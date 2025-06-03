<?php
// admin/add_category.php - Super Admin: Add New Category

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ADD CATEGORY ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Add New Category";
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Form data placeholders
$category_name_form = '';
$category_description_form = '';
$parent_category_id_form = ''; // Empty means top-level

// --- Fetch existing categories for Parent Category dropdown ---
$parent_categories_options = [];
try {
    // Fetch all categories to be used as potential parents
    $stmt_parent_cats = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $parent_categories_options = $stmt_parent_cats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Add Category - Error fetching parent categories: " . $e->getMessage());
    $errors['database_fetch'] = "Could not load existing categories for parent selection.";
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    // Sanitize and validate inputs
    $category_name_form = trim(filter_input(INPUT_POST, 'category_name', FILTER_UNSAFE_RAW));
    $category_description_form = trim(filter_input(INPUT_POST, 'category_description', FILTER_UNSAFE_RAW));
    $parent_category_id_form = filter_input(INPUT_POST, 'parent_category_id', FILTER_VALIDATE_INT);
    if ($parent_category_id_form === 0 || $parent_category_id_form === false) { // Treat 0 or invalid as NULL (no parent)
        $parent_category_id_form = null;
    }


    // Basic Validation
    if (empty($category_name_form)) {
        $errors['category_name'] = "Category name is required.";
    } else {
        // Check if category name already exists (optional, but good for usability)
        $stmt_check_name = $db->prepare("SELECT category_id FROM categories WHERE category_name = :name AND parent_category_id <=> :parent_id"); // <=> for NULL-safe comparison
        $stmt_check_name->bindParam(':name', $category_name_form);
        $stmt_check_name->bindParam(':parent_id', $parent_category_id_form); // Check uniqueness within the same parent
        $stmt_check_name->execute();
        if ($stmt_check_name->fetch()) {
            $errors['category_name'] = "A category with this name already exists " . ($parent_category_id_form ? "under the selected parent." : "at the top level.");
        }
    }
    if ($parent_category_id_form !== null) {
        // Ensure selected parent_category_id is valid (exists in categories table)
        $is_valid_parent = false;
        foreach($parent_categories_options as $p_cat) {
            if ($p_cat['category_id'] == $parent_category_id_form) {
                $is_valid_parent = true;
                break;
            }
        }
        if (!$is_valid_parent) {
             $errors['parent_category_id'] = "Invalid parent category selected.";
             $parent_category_id_form = null; // Reset if invalid
        }
    }


    // --- Image Upload Handling ---
    $category_image_url_db_path = null;
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == UPLOAD_ERR_OK) {
        $image_file = $_FILES['category_image'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'categories/'; // Ensure this directory exists and is writable
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['category_image'] = "Failed to create category image upload directory.";
            }
        }

        if (empty($errors['category_image'])) {
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_mime_type = mime_content_type($image_file['tmp_name']);

            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $errors['category_image'] = "Invalid image file type. Allowed: JPG, PNG, GIF, WEBP.";
            } elseif ($image_file['size'] > MAX_IMAGE_SIZE) {
                $errors['category_image'] = "Image file is too large. Max size: " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB.";
            } else {
                $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
                $safe_category_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($category_name_form));
                $new_filename = $safe_category_name . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($image_file['tmp_name'], $destination)) {
                    $category_image_url_db_path = 'categories/' . $new_filename; // Relative path for DB
                } else {
                    $errors['category_image'] = "Failed to upload category image. Check permissions or server error.";
                    error_log("Failed to move uploaded file: " . $image_file['name'] . " to " . $destination);
                }
            }
        }
    } elseif (isset($_FILES['category_image']) && $_FILES['category_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['category_image'] = "Error uploading image: " . $_FILES['category_image']['error'];
    }


    if (empty($errors)) {
        try {
            $sql_insert_category = "INSERT INTO categories (category_name, category_description, parent_category_id, category_image_url, created_at, updated_at)
                                   VALUES (:category_name, :category_description, :parent_category_id, :category_image_url, NOW(), NOW())";
            $stmt_insert_category = $db->prepare($sql_insert_category);
            
            $params_insert = [
                ':category_name' => $category_name_form,
                ':category_description' => $category_description_form ?: null,
                ':parent_category_id' => $parent_category_id_form, // Will be NULL if not selected or invalid
                ':category_image_url' => $category_image_url_db_path
            ];
            
            if ($stmt_insert_category->execute($params_insert)) {
                $new_category_id = $db->lastInsertId();
                $_SESSION['admin_message'] = "<div class='admin-message success'>Category '" . htmlspecialchars($category_name_form) . "' added successfully (ID: {$new_category_id}).</div>";
                header("Location: categories.php"); // Redirect to category list
                exit;
            } else {
                if ($category_image_url_db_path && file_exists(PUBLIC_UPLOADS_PATH . $category_image_url_db_path)) {
                    unlink(PUBLIC_UPLOADS_PATH . $category_image_url_db_path); 
                }
                $message = "<div class='admin-message error'>Failed to add new category. Database error.</div>";
            }

        } catch (PDOException $e) {
            error_log("Admin Add Category - Error inserting category: " . $e->getMessage());
            if ($category_image_url_db_path && file_exists(PUBLIC_UPLOADS_PATH . $category_image_url_db_path)) {
                unlink(PUBLIC_UPLOADS_PATH . $category_image_url_db_path); 
            }
            $errors['database'] = "An error occurred while adding the category. " . $e->getMessage();
            $message = "<div class='admin-message error'>An error occurred. Please check the details and try again.</div>";
        }
    } else {
        $message = "<div class='admin-message error'>Please correct the errors below and try again.</div>";
    }
}

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="categories.php">&laquo; Back to Category List</a></p>

<?php if ($message) echo $message; ?>
<?php if (!empty($errors['database_fetch'])) echo "<div class='admin-message error'>".$errors['database_fetch']."</div>"; ?>


<form action="add_category.php" method="POST" class="admin-form" enctype="multipart/form-data" style="max-width: 700px;">
    <fieldset>
        <legend>Category Details</legend>
        <div class="form-group">
            <label for="category_name">Category Name <span style="color:red;">*</span></label>
            <input type="text" id="category_name" name="category_name" value="<?php echo htmlspecialchars($category_name_form); ?>" required>
            <?php if (isset($errors['category_name'])): ?><small style="color:red;"><?php echo $errors['category_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="parent_category_id">Parent Category</label>
            <select id="parent_category_id" name="parent_category_id">
                <option value="">-- None (Top Level Category) --</option>
                <?php if (!empty($parent_categories_options)): ?>
                    <?php foreach ($parent_categories_options as $p_cat): ?>
                        <option value="<?php echo $p_cat['category_id']; ?>" <?php echo ($parent_category_id_form == $p_cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p_cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <small>Select a parent to make this a sub-category.</small>
            <?php if (isset($errors['parent_category_id'])): ?><small style="color:red;"><?php echo $errors['parent_category_id']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="category_description">Category Description</label>
            <textarea id="category_description" name="category_description" rows="4"><?php echo htmlspecialchars($category_description_form); ?></textarea>
        </div>

        <div class="form-group">
            <label for="category_image">Category Image</label>
            <input type="file" id="category_image" name="category_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small>Optional. Recommended aspect ratio: 1:1 (square) or 4:3. Max file size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '2'; ?>MB.</small>
            <?php if (isset($errors['category_image'])): ?><small style="color:red;"><?php echo $errors['category_image']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <button type="submit" name="add_category" class="btn-submit">Add Category</button>
</form>

<?php
include_once 'includes/footer.php';
?>
