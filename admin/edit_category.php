<?php
// admin/edit_category.php - Super Admin: Edit Category

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN EDIT CATEGORY ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Edit Category"; // Will be updated after fetching category
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Get Category ID from URL
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

if (!$category_id) {
    $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid Category ID.</div>";
    header("Location: categories.php");
    exit;
}

// --- Fetch Existing Category Data ---
$category = null;
try {
    $stmt_category = $db->prepare("SELECT * FROM categories WHERE category_id = :category_id");
    $stmt_category->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    $stmt_category->execute();
    $category = $stmt_category->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $_SESSION['admin_message'] = "<div class='admin-message error'>Category not found (ID: {$category_id}).</div>";
        header("Location: categories.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Admin Edit Category - Error fetching category data: " . $e->getMessage());
    $_SESSION['admin_message'] = "<div class='admin-message error'>Error loading category data.</div>";
    header("Location: categories.php");
    exit;
}

// Initialize form variables with existing category data
// FIX: Coalesce null to empty string for htmlspecialchars safety
$category_name_form = $category['category_name'] ?? '';
$category_description_form = $category['category_description'] ?? '';
$parent_category_id_form = $category['parent_category_id'];
$current_category_image_url = $category['category_image_url'];
$admin_page_title = "Edit Category: " . htmlspecialchars($category['category_name'] ?? 'N/A'); // FIX: Handle null for display


// --- Fetch existing categories for Parent Category dropdown (excluding the current category itself) ---
$parent_categories_options = [];
try {
    $stmt_parent_cats = $db->prepare("SELECT category_id, category_name FROM categories WHERE category_id != :current_category_id ORDER BY category_name ASC");
    $stmt_parent_cats->bindParam(':current_category_id', $category_id, PDO::PARAM_INT);
    $stmt_parent_cats->execute();
    $parent_categories_options = $stmt_parent_cats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Edit Category - Error fetching parent categories: " . $e->getMessage());
    $errors['database_fetch'] = "Could not load existing categories for parent selection.";
}


// --- Handle Form Submission for Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_name_form = trim(filter_input(INPUT_POST, 'category_name', FILTER_UNSAFE_RAW));
    // FIX: Ensure description from POST is not null before using
    $category_description_form = trim(filter_input(INPUT_POST, 'category_description', FILTER_UNSAFE_RAW) ?? '');
    $new_parent_category_id_form = filter_input(INPUT_POST, 'parent_category_id', FILTER_VALIDATE_INT);

    if ($new_parent_category_id_form === 0 || $new_parent_category_id_form === false) {
        $new_parent_category_id_form = null;
    }

    // Basic Validation
    if (empty($category_name_form)) {
        $errors['category_name'] = "Category name is required.";
    } else {
        // Check if category name already exists (excluding current category)
        $stmt_check_name = $db->prepare("SELECT category_id FROM categories WHERE category_name = :name AND category_id != :current_id AND parent_category_id <=> :parent_id");
        $stmt_check_name->bindParam(':name', $category_name_form);
        $stmt_check_name->bindParam(':current_id', $category_id, PDO::PARAM_INT);
        $stmt_check_name->bindParam(':parent_id', $new_parent_category_id_form, PDO::PARAM_INT); // FIX: Bind as INT for parent_category_id
        $stmt_check_name->execute();
        if ($stmt_check_name->fetch()) {
            $errors['category_name'] = "Another category with this name already exists " . ($new_parent_category_id_form ? "under the selected parent." : "at the top level.");
        }
    }

    // Validate parent_category_id: cannot be self, must be valid if selected
    if ($new_parent_category_id_form !== null) {
        if ($new_parent_category_id_form == $category_id) {
            $errors['parent_category_id'] = "A category cannot be its own parent.";
        } else {
            $is_valid_parent = false;
            foreach($parent_categories_options as $p_cat) { // Check against the filtered list
                if ($p_cat['category_id'] == $new_parent_category_id_form) {
                    $is_valid_parent = true;
                    break;
                }
            }
            if (!$is_valid_parent) {
                 $errors['parent_category_id'] = "Invalid parent category selected.";
                 // $new_parent_category_id_form = $parent_category_id_form; // Revert to original if invalid to avoid issues
            }
        }
    }
    // Update form variable for sticky form
    $parent_category_id_form = $new_parent_category_id_form;


    // --- Image Upload Handling (if new image provided) ---
    $category_image_url_db_path_update = $current_category_image_url; // Keep old image if new one not uploaded
    $old_image_to_delete_on_success = null;

    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == UPLOAD_ERR_OK) {
        $image_file = $_FILES['category_image'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'categories/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['category_image'] = "Failed to create category image upload directory.";
            }
        }

        if (empty($errors['category_image'])) { // Proceed only if no prior image dir error
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
                    $category_image_url_db_path_update = 'categories/' . $new_filename; // New image path for DB
                    // FIX: Check if old image was a local file to delete
                    if ($current_category_image_url && $current_category_image_url !== $category_image_url_db_path_update) {
                        if (strpos($current_category_image_url, 'http') === false && strpos($current_category_image_url, '//') !== 0) {
                           $old_image_to_delete_on_success = PUBLIC_UPLOADS_PATH . $current_category_image_url;
                        }
                    }
                } else {
                    $errors['category_image'] = "Failed to upload new category image.";
                }
            }
        }
    } elseif (isset($_FILES['category_image']) && $_FILES['category_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['category_image'] = "Error uploading image: " . $_FILES['category_image']['error'];
    }


    if (empty($errors)) {
        try {
            $sql_update_category = "UPDATE categories SET
                                    category_name = :category_name,
                                    category_description = :category_description,
                                    parent_category_id = :parent_category_id,
                                    category_image_url = :category_image_url,
                                    updated_at = NOW()
                                   WHERE category_id = :category_id";
            $stmt_update_category = $db->prepare($sql_update_category);

            $params_update = [
                ':category_name' => $category_name_form,
                ':category_description' => $category_description_form ?: null, // Use null for empty description in DB
                ':parent_category_id' => $parent_category_id_form, // Use the validated parent ID
                ':category_image_url' => $category_image_url_db_path_update,
                ':category_id' => $category_id
            ];

            if ($stmt_update_category->execute($params_update)) {
                // Delete old image file if replaced and DB update was successful
                if ($old_image_to_delete_on_success && file_exists($old_image_to_delete_on_success)) {
                    // FIX: Use is_file() to prevent accidental directory deletion
                    if (is_file($old_image_to_delete_on_success)) {
                        unlink($old_image_to_delete_on_success);
                    } else {
                         error_log("Admin Edit Category - Old image marked for deletion was not a file: " . $old_image_to_delete_on_success);
                    }
                }
                $_SESSION['admin_message'] = "<div class='admin-message success'>Category '" . htmlspecialchars($category_name_form) . "' (ID: {$category_id}) updated successfully.</div>";
                header("Location: categories.php?highlight_category_id=" . $category_id);
                exit;
            } else {
                // If new image was uploaded but DB failed, delete the newly uploaded image
                if ($category_image_url_db_path_update !== $current_category_image_url &&
                    $category_image_url_db_path_update &&
                    // FIX: Ensure it's a local file before attempting to delete
                    strpos($category_image_url_db_path_update, 'http') === false && strpos($category_image_url_db_path_update, '//') !== 0 &&
                    file_exists(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update)) {
                    // FIX: Use is_file()
                    if (is_file(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update)) {
                        unlink(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update);
                    }
                }
                $message = "<div class='admin-message error'>Failed to update category. Database error.</div>";
            }

        } catch (PDOException $e) {
            error_log("Admin Edit Category - Error updating category: " . $e->getMessage());
            // If new image was uploaded but DB failed, delete the newly uploaded image
            if ($category_image_url_db_path_update !== $current_category_image_url &&
                $category_image_url_db_path_update &&
                // FIX: Ensure it's a local file before attempting to delete
                strpos($category_image_url_db_path_update, 'http') === false && strpos($category_image_url_db_path_update, '//') !== 0 &&
                file_exists(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update)) {
                // FIX: Use is_file()
                if (is_file(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update)) {
                    unlink(PUBLIC_UPLOADS_PATH . $category_image_url_db_path_update);
                }
            }
            $errors['database'] = "An error occurred while updating the category. " . $e->getMessage();
            $message = "<div class='admin-message error'>An error occurred. Please check details.</div>";
        }
    } else {
         $message = "<div class='admin-message error'>Please correct the errors below and try again.</div>";
    }
}

?>

<h1 class="admin-page-title"><?php echo $admin_page_title; ?></h1>
<p><a href="categories.php">&laquo; Back to Category List</a></p>

<?php if ($message) echo $message; ?>
<?php if (!empty($errors['database_fetch'])) echo "<div class='admin-message error'>".$errors['database_fetch']."</div>"; ?>

<form action="edit_category.php?category_id=<?php echo htmlspecialchars($category_id); ?>" method="POST" class="admin-form" enctype="multipart/form-data" style="max-width: 700px;">
    <fieldset>
        <legend>Category Details</legend>
        <div class="form-group">
            <label for="category_name">Category Name <span style="color:red;">*</span></label>
            <input type="text" id="category_name" name="category_name" value="<?php echo htmlspecialchars($category_name_form); ?>" required maxlength="255">
            <?php if (isset($errors['category_name'])): ?><small style="color:red;"><?php echo $errors['category_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="parent_category_id">Parent Category</label>
            <select id="parent_category_id" name="parent_category_id">
                <option value="">-- None (Top Level Category) --</option>
                <?php if (!empty($parent_categories_options)): ?>
                    <?php foreach ($parent_categories_options as $p_cat): ?>
                        <?php // Do not allow selecting self as parent - already filtered in query ?>
                        <option value="<?php echo htmlspecialchars($p_cat['category_id']); ?>" <?php echo ((string)$parent_category_id_form === (string)$p_cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p_cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <small>Select a parent to make this a sub-category. Cannot be its own parent.</small>
            <?php if (isset($errors['parent_category_id'])): ?><small style="color:red;"><?php echo $errors['parent_category_id']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="category_description">Category Description</label>
            <textarea id="category_description" name="category_description" rows="4"><?php echo htmlspecialchars($category_description_form); ?></textarea>
            <?php // The description is now guaranteed to be a string (empty string if null) ?>
        </div>

        <div class="form-group">
            <label for="category_image_display">Current Category Image</label>
            <?php
            $display_image_url = '';
            if (!empty($current_category_image_url)) {
                // Check if it's an absolute URL (starts with http:// or https:// or //)
                if (filter_var($current_category_image_url, FILTER_VALIDATE_URL) || strpos($current_category_image_url, '//') === 0) {
                    $display_image_url = htmlspecialchars($current_category_image_url);
                } else {
                    // It's a relative path, prepend PUBLIC_UPLOADS_URL_BASE
                    $display_image_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $current_category_image_url);
                }
            } else {
                // No image URL, use placeholder
                $display_image_url = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '150x150/eee/aaa?text=No+Img');
            }
            $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '150x150/eee/aaa?text=Error');
            ?>
            <?php if ($display_image_url): ?>
                <img src="<?php echo $display_image_url; ?>" alt="<?php echo htmlspecialchars($category_name_form); ?> Image" style="max-width: 150px; max-height: 150px; display:block; margin-bottom:10px; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                <small>Current: <?php echo htmlspecialchars($current_category_image_url ?? 'N/A'); ?></small>
            <?php else: ?>
                <p>No image currently set for this category.</p>
            <?php endif; ?>

            <label for="category_image_upload" style="margin-top:10px; display:block;">Upload New Image (Optional - Replaces current)</label>
            <input type="file" id="category_image_upload" name="category_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small>Recommended aspect ratio: 1:1 or 4:3. Max file size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '2'; ?>MB.</small>
            <?php if (isset($errors['category_image'])): ?><small style="color:red;"><?php echo $errors['category_image']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <button type="submit" name="update_category" class="btn-submit">Update Category</button>
</form>

<?php
include_once 'includes/footer.php';
?>