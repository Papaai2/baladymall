<?php
// admin/brand_edit.php - Super Admin Brand Management (Edit/Approve Brand)

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN EDIT BRAND ERROR: Main config.php not found.");
}
require_once 'auth_check.php';

$admin_page_title = "Edit Brand";
$message = '';
$brand_data = null;
$brand_id_to_edit = null;
$errors = []; // Initialize errors array

$db = getPDOConnection();

if (!isset($db) || !$db instanceof PDO) {
    $_SESSION['admin_message'] = "<div class='admin-message error'>Database connection not available. Cannot load brand details.</div>";
    header("Location: brands.php");
    exit;
}


if (isset($_GET['brand_id']) && filter_var($_GET['brand_id'], FILTER_VALIDATE_INT)) {
    $brand_id_to_edit = (int)$_GET['brand_id'];

    // Handle direct actions from list page (e.g., approve)
    if (isset($_GET['action']) && $_GET['action'] === 'approve' && $brand_id_to_edit) {
        try {
            $stmt_approve = $db->prepare("UPDATE brands SET is_approved = 1 WHERE brand_id = :brand_id AND is_approved = 0");
            $stmt_approve->bindParam(':brand_id', $brand_id_to_edit, PDO::PARAM_INT);
            if ($stmt_approve->execute() && $stmt_approve->rowCount() > 0) {
                $_SESSION['admin_message'] = "<div class='admin-message success'>Brand approved successfully.</div>";
                // TODO: Notify brand admin user (e.g., via email)
            } else {
                $_SESSION['admin_message'] = "<div class='admin-message warning'>Brand was already approved or could not be approved.</div>";
            }
        } catch (PDOException $e) {
            error_log("Admin Approve Brand Error: " . $e->getMessage());
            $_SESSION['admin_message'] = "<div class='admin-message error'>An error occurred while approving the brand.</div>";
        }
        header("Location: brands.php"); // Redirect back to list
        exit;
    }


    // Fetch brand data for editing
    try {
        // Fetch brand data along with the associated user's username and email
        $stmt = $db->prepare("SELECT b.*, u.username as admin_username, u.email as admin_email
                              FROM brands b
                              LEFT JOIN users u ON b.user_id = u.user_id
                              WHERE b.brand_id = :brand_id");
        $stmt->bindParam(':brand_id', $brand_id_to_edit, PDO::PARAM_INT);
        $stmt->execute();
        $brand_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$brand_data) {
            $_SESSION['admin_message'] = "<div class='admin-message error'>Brand not found.</div>";
            header("Location: brands.php");
            exit;
        }
        $admin_page_title = "Edit Brand: " . htmlspecialchars($brand_data['brand_name']);
    } catch (PDOException $e) {
        error_log("Admin Edit Brand - Error fetching brand: " . $e->getMessage());
        $message = "<div class='admin-message error'>Could not load brand data.</div>";
    }
} else {
    $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid brand ID specified.</div>";
    header("Location: brands.php");
    exit;
}

// Initialize current logo URL for form display & deletion logic
$current_brand_logo_url = $brand_data['brand_logo_url'] ?? null;


// Handle form submission for updating brand
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_brand']) && $brand_data) {
    $new_brand_name = trim(filter_input(INPUT_POST, 'brand_name', FILTER_UNSAFE_RAW));
    $new_brand_description = trim(filter_input(INPUT_POST, 'brand_description', FILTER_UNSAFE_RAW) ?? '');
    $new_brand_contact_email = trim(filter_input(INPUT_POST, 'brand_contact_email', FILTER_SANITIZE_EMAIL) ?? '');
    $new_brand_contact_phone = trim(filter_input(INPUT_POST, 'brand_contact_phone', FILTER_UNSAFE_RAW) ?? '');
    $new_brand_website_url = trim(filter_input(INPUT_POST, 'brand_website_url', FILTER_SANITIZE_URL) ?? '');
    $new_commission_rate = filter_input(INPUT_POST, 'commission_rate', FILTER_VALIDATE_FLOAT);
    $new_is_approved = isset($_POST['is_approved']) ? 1 : 0;

    // --- NEW: Brand Logo Upload Handling ---
    $brand_logo_url_db_path_update = $current_brand_logo_url; // Keep old logo by default
    $old_logo_file_to_delete = null;

    if (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] == UPLOAD_ERR_OK) {
        $logo_file = $_FILES['brand_logo'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'brands/'; // Target directory for brand logos

        if (!is_dir($upload_dir)) { // Create directory if it doesn't exist
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['brand_logo'] = "Failed to create brand logo upload directory.";
            }
        }

        if (empty($errors['brand_logo'])) { // Only proceed if no directory creation error
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $file_mime_type = mime_content_type($logo_file['tmp_name']);

            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $errors['brand_logo'] = "Invalid logo file type. Allowed: JPG, PNG, GIF, WEBP, SVG.";
            } elseif ($logo_file['size'] > MAX_IMAGE_SIZE) {
                $errors['brand_logo'] = "Logo file is too large. Max size: " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB.";
            } else {
                $file_extension = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
                $safe_brand_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($new_brand_name)); // Sanitize for filename
                $new_filename = $safe_brand_name . '-logo-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($logo_file['tmp_name'], $destination)) {
                    $brand_logo_url_db_path_update = 'brands/' . $new_filename; // Relative path for DB
                    // If a new logo was successfully uploaded and there was an old one, mark old for deletion
                    if ($current_brand_logo_url && $current_brand_logo_url !== $brand_logo_url_db_path_update) {
                        $old_logo_file_to_delete = PUBLIC_UPLOADS_PATH . $current_brand_logo_url;
                    }
                } else {
                    $errors['brand_logo'] = "Failed to upload new brand logo. Check permissions or server error.";
                    error_log("Failed to move uploaded brand logo file: " . $logo_file['name'] . " to " . $destination);
                }
            }
        }
    } elseif (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['brand_logo'] = "Error uploading logo: " . $logo_file['error'];
    }


    // Basic Validation for other fields
    if (empty($new_brand_name)) $errors['brand_name'] = "Brand name is required.";
    if (!empty($new_brand_contact_email) && !filter_var($new_brand_contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors['brand_contact_email'] = "Invalid contact email format.";
    }
    if (!empty($new_brand_website_url) && !filter_var($new_brand_website_url, FILTER_VALIDATE_URL)) {
        $errors['brand_website_url'] = "Invalid website URL format.";
    }
    if ($new_commission_rate !== null && ($new_commission_rate < 0 || $new_commission_rate > 100)) {
        $errors['commission_rate'] = "Commission rate must be between 0 and 100.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Check if brand name already exists for another brand
            $stmt_check_brand = $db->prepare("SELECT brand_id FROM brands WHERE brand_name = :brand_name AND brand_id != :current_brand_id");
            $stmt_check_brand->bindParam(':brand_name', $new_brand_name);
            $stmt_check_brand->bindParam(':current_brand_id', $brand_id_to_edit, PDO::PARAM_INT);
            $stmt_check_brand->execute();
            if ($stmt_check_brand->fetch()) {
                $errors['brand_name'] = "A brand with this name already exists.";
            }

            if (empty($errors)) {
                $update_sql = "UPDATE brands SET
                                brand_name = :brand_name,
                                brand_logo_url = :brand_logo_url,    /* NEW: Include logo URL */
                                brand_description = :brand_description,
                                brand_contact_email = :brand_contact_email,
                                brand_contact_phone = :brand_contact_phone,
                                brand_website_url = :brand_website_url,
                                commission_rate = :commission_rate,
                                is_approved = :is_approved,
                                updated_at = NOW()
                               WHERE brand_id = :brand_id";
                $stmt_update = $db->prepare($update_sql);

                $params_update = [
                    ':brand_name' => $new_brand_name,
                    ':brand_logo_url' => $brand_logo_url_db_path_update, /* NEW: Set logo URL */
                    ':brand_description' => $new_brand_description ?: null,
                    ':brand_contact_email' => $new_brand_contact_email ?: null,
                    ':brand_contact_phone' => $new_brand_contact_phone ?: null,
                    ':brand_website_url' => $new_brand_website_url ?: null,
                    ':commission_rate' => ($new_commission_rate === null || $new_commission_rate === '') ? null : $new_commission_rate,
                    ':is_approved' => $new_is_approved,
                    ':brand_id' => $brand_id_to_edit
                ];

                if ($stmt_update->execute($params_update)) {
                    $db->commit();
                    // Delete old logo file if a new one was uploaded and DB update was successful
                    if ($old_logo_file_to_delete && file_exists($old_logo_file_to_delete)) {
                        @unlink($old_logo_file_to_delete);
                    }

                    $_SESSION['admin_message'] = "<div class='admin-message success'>Brand '" . htmlspecialchars($new_brand_name) . "' updated successfully.</div>";
                    // If status changed, potentially notify brand admin
                    if ($brand_data['is_approved'] != $new_is_approved) {
                         // TODO: Send notification email to brand admin
                    }
                    header("Location: brands.php"); // Redirect to list after update
                    exit;
                } else {
                    $db->rollBack();
                    // If DB update failed, delete newly uploaded logo to prevent orphaned files
                    if ($brand_logo_url_db_path_update && $brand_logo_url_db_path_update !== $current_brand_logo_url && file_exists(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update)) {
                         @unlink(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update);
                    }
                    $message = "<div class='admin-message error'>Failed to update brand.</div>";
                }
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Admin Edit Brand - Error updating brand: " . $e->getMessage());
            // If DB update failed due to exception, delete newly uploaded logo
            if ($brand_logo_url_db_path_update && $brand_logo_url_db_path_update !== $current_brand_logo_url && file_exists(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update)) {
                @unlink(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update);
            }
            if ($e->getCode() == '23000') { // Integrity constraint (e.g. unique brand_name if you have such constraint)
                 $message = "<div class='admin-message error'>Update failed. The brand name might already be in use.</div>";
            } else {
                $message = "<div class='admin-message error'>An error occurred while updating the brand.</div>";
            }
        }
    }
    // If errors, repopulate brand_data with POSTed values for sticky form
    if (!empty($errors)) {
        $brand_data['brand_name'] = $new_brand_name;
        $brand_data['brand_description'] = $new_brand_description;
        $brand_data['brand_contact_email'] = $new_brand_contact_email;
        $brand_data['brand_contact_phone'] = $new_brand_contact_phone;
        $brand_data['brand_website_url'] = $new_brand_website_url;
        $brand_data['commission_rate'] = $new_commission_rate;
        $brand_data['is_approved'] = $new_is_approved;
        $brand_data['brand_logo_url'] = $brand_logo_url_db_path_update; // Update for sticky form if upload worked
    }
}


include_once 'includes/header.php';
?>

<h1 class="admin-page-title"><?php echo $admin_page_title; ?></h1>
<p><a href="brands.php">&laquo; Back to Brand List</a></p>

<?php
// Display session message from previous page
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
// Display current page messages
if ($message) echo $message;
// Display form validation errors
if (!empty($errors)): ?>
    <div class="admin-message error">
        Please correct the following errors:
        <ul>
            <?php foreach ($errors as $field_error): ?>
                <li><?php echo htmlspecialchars($field_error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


<?php if ($brand_data): ?>
    <form action="brand_edit.php?brand_id=<?php echo $brand_id_to_edit; ?>" method="POST" class="admin-form" enctype="multipart/form-data" style="max-width: 700px;">
        <fieldset>
            <legend>Brand Details</legend>
            <div class="form-group">
                <label for="brand_name">Brand Name <span style="color:red;">*</span></label>
                <input type="text" id="brand_name" name="brand_name" value="<?php echo htmlspecialchars($brand_data['brand_name']); ?>" required>
                <?php if (isset($errors['brand_name'])): ?><small style="color:red;"><?php echo $errors['brand_name']; ?></small><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="brand_description">Brand Description</label>
                <textarea id="brand_description" name="brand_description" rows="5"><?php echo htmlspecialchars($brand_data['brand_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="brand_logo">Current Brand Logo</label>
                <?php if (!empty($brand_data['brand_logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $brand_data['brand_logo_url']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($brand_data['brand_name']); ?> Logo" style="max-width: 150px; max-height: 150px; display:block; margin-bottom:10px; border-radius: 4px; background: #f0f0f0; padding: 5px;">
                    <small>Current: <?php echo htmlspecialchars($brand_data['brand_logo_url']); ?></small>
                <?php else: ?>
                    <p>No logo currently set.</p>
                <?php endif; ?>
                <label for="brand_logo_upload" style="margin-top:10px; display:block;">Upload New Logo (Optional - Replaces current)</label>
                <input type="file" id="brand_logo_upload" name="brand_logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                <small>Recommended: Square logo. Max file size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '5'; ?>MB. Allowed types: JPG, PNG, GIF, WEBP, SVG.</small>
                <?php if (isset($errors['brand_logo'])): ?><small style="color:red;"><?php echo $errors['brand_logo']; ?></small><?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Contact & Links</legend>
            <div class="form-group">
                <label for="brand_contact_email">Contact Email</label>
                <input type="email" id="brand_contact_email" name="brand_contact_email" value="<?php echo htmlspecialchars($brand_data['brand_contact_email'] ?? ''); ?>">
                <?php if (isset($errors['brand_contact_email'])): ?><small style="color:red;"><?php echo $errors['brand_contact_email']; ?></small><?php endif; ?>
            </div>
             <div class="form-group">
                <label for="brand_contact_phone">Contact Phone</label>
                <input type="tel" id="brand_contact_phone" name="brand_contact_phone" value="<?php echo htmlspecialchars($brand_data['brand_contact_phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="brand_website_url">Website URL</label>
                <input type="url" id="brand_website_url" name="brand_website_url" value="<?php echo htmlspecialchars($brand_data['brand_website_url'] ?? ''); ?>" placeholder="https://example.com">
                <?php if (isset($errors['brand_website_url'])): ?><small style="color:red;"><?php echo $errors['brand_website_url']; ?></small><?php endif; ?>
            </div>
            <?php // Add fields for social media links (facebook_url, instagram_url) from your DB schema if needed ?>
        </fieldset>

        <fieldset>
            <legend>Administration</legend>
             <div class="form-group">
                <label>Brand Admin User:</label>
                <p>
                    <?php if (isset($brand_data['admin_username']) && $brand_data['admin_username']): ?>
                        <strong>Username:</strong> <?php echo htmlspecialchars($brand_data['admin_username']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($brand_data['admin_email'] ?? 'N/A'); ?>
                        (User ID: <?php echo htmlspecialchars($brand_data['user_id']); ?>)
                        <?php // You might add a link here to edit this user in users.php ?>
                        <br><small>To change the brand admin, edit the user in the User Management section and assign them to this brand, or update the user_id for this brand (requires careful handling).</small>
                    <?php else: ?>
                        <span style="color:red;">No admin user currently assigned to this brand.</span>
                        <?php // Add UI to assign a user_id (brand_admin role) to this brand ?>
                    <?php endif; ?>
            </p>
            </div>

            <div class="form-group">
                <label for="commission_rate">Commission Rate (%)</label>
                <input type="number" id="commission_rate" name="commission_rate" value="<?php echo htmlspecialchars($brand_data['commission_rate'] ?? ''); ?>" step="0.01" min="0" max="100" placeholder="e.g., 15.50">
                <?php if (isset($errors['commission_rate'])): ?><small style="color:red;"><?php echo $errors['commission_rate']; ?></small><?php endif; ?>
                <small>Enter a percentage, e.g., 10 for 10%.</small>
            </div>

            <div class="form-group">
                <label for="is_approved">
                    <input type="checkbox" id="is_approved" name="is_approved" value="1" <?php echo ($brand_data['is_approved'] == 1) ? 'checked' : ''; ?>>
                    Brand Approved
                </label>
                <small>If unchecked, the brand and its products will not be visible on the public site.</small>
            </div>
        </fieldset>

        <button type="submit" name="update_brand" class="btn-submit">Update Brand</button>
    </form>
<?php else: ?>
    <?php if (empty($message)): ?>
    <p class="admin-message error">Brand data could not be loaded.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>