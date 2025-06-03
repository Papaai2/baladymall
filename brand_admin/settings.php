<?php
// brand_admin/settings.php - Brand Admin: Manage Brand Settings

$brand_admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL BRAND ADMIN SETTINGS ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is brand_admin and sets $_SESSION['brand_id']

$brand_admin_page_title = "My Brand Settings";
include_once 'includes/header.php';

$db = getPDOConnection();

// Get the assigned brand ID from the session
$current_brand_id = $_SESSION['brand_id'];
$current_brand_name = $_SESSION['brand_name'];

$errors = [];
$message = '';

// Fetch current brand settings
$brand_settings = null;
try {
    $stmt = $db->prepare("SELECT * FROM brands WHERE brand_id = :brand_id AND user_id = :user_id");
    $stmt->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT); // Double-check user ownership
    $stmt->execute();
    $brand_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$brand_settings) {
        $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Brand settings not found or you don't have permission.</div>";
        header("Location: index.php"); // Redirect to dashboard if brand not found
        exit;
    }
} catch (PDOException $e) {
    error_log("Brand Admin Settings - Error fetching brand settings for brand {$current_brand_id}: " . $e->getMessage());
    $message = "<div class='brand-admin-message error'>Could not load brand settings.</div>";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand_settings'])) {
    $new_brand_name = trim(filter_input(INPUT_POST, 'brand_name', FILTER_UNSAFE_RAW));
    $new_brand_description = trim(filter_input(INPUT_POST, 'brand_description', FILTER_UNSAFE_RAW) ?? '');
    $new_brand_contact_email = trim(filter_input(INPUT_POST, 'brand_contact_email', FILTER_SANITIZE_EMAIL) ?? '');
    $new_brand_contact_phone = trim(filter_input(INPUT_POST, 'brand_contact_phone', FILTER_UNSAFE_RAW) ?? '');
    $new_brand_website_url = trim(filter_input(INPUT_POST, 'brand_website_url', FILTER_SANITIZE_URL) ?? '');
    $new_facebook_url = trim(filter_input(INPUT_POST, 'facebook_url', FILTER_SANITIZE_URL) ?? '');
    $new_instagram_url = trim(filter_input(INPUT_POST, 'instagram_url', FILTER_SANITIZE_URL) ?? '');
    $new_commission_rate = filter_input(INPUT_POST, 'commission_rate', FILTER_VALIDATE_FLOAT); // Commission rate is usually set by super admin, but form allows display/submission

    // --- Logo Upload Handling ---
    $brand_logo_url_db_path_update = $brand_settings['brand_logo_url']; // Keep old logo by default
    $old_logo_file_to_delete = null;

    if (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] == UPLOAD_ERR_OK) {
        $logo_file = $_FILES['brand_logo'];
        $upload_dir = PUBLIC_UPLOADS_PATH . 'brands/';

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $errors['brand_logo'] = "Failed to create brand logo upload directory.";
            }
        }

        if (empty($errors['brand_logo'])) {
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $file_mime_type = mime_content_type($logo_file['tmp_name']);

            if (!in_array($file_mime_type, $allowed_mime_types)) {
                $errors['brand_logo'] = "Invalid logo file type. Allowed: JPG, PNG, GIF, WEBP, SVG.";
            } elseif ($logo_file['size'] > MAX_IMAGE_SIZE) {
                $errors['brand_logo'] = "Logo file is too large. Max size: " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB.";
            } else {
                $file_extension = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
                $safe_brand_name = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($new_brand_name));
                $new_filename = $safe_brand_name . '-logo-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($logo_file['tmp_name'], $destination)) {
                    $brand_logo_url_db_path_update = 'brands/' . $new_filename;
                    if ($brand_settings['brand_logo_url'] && $brand_settings['brand_logo_url'] !== $brand_logo_url_db_path_update) {
                        $old_logo_file_to_delete = PUBLIC_UPLOADS_PATH . $brand_settings['brand_logo_url'];
                    }
                } else {
                    $errors['brand_logo'] = "Failed to upload new brand logo.";
                    error_log("Failed to move uploaded brand logo file: " . $logo_file['name'] . " to " . $destination);
                }
            }
        }
    } elseif (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['brand_logo'] = "Error uploading logo: " . $_FILES['brand_logo']['error'];
    }


    // Basic Validation for other fields
    if (empty($new_brand_name)) $errors['brand_name'] = "Brand name is required.";
    if (!empty($new_brand_contact_email) && !filter_var($new_brand_contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors['brand_contact_email'] = "Invalid contact email format.";
    }
    if (!empty($new_brand_website_url) && !filter_var($new_brand_website_url, FILTER_VALIDATE_URL)) {
        $errors['brand_website_url'] = "Invalid website URL format.";
    }
    if (!empty($new_facebook_url) && !filter_var($new_facebook_url, FILTER_VALIDATE_URL)) {
        $errors['facebook_url'] = "Invalid Facebook URL format.";
    }
    if (!empty($new_instagram_url) && !filter_var($new_instagram_url, FILTER_VALIDATE_URL)) {
        $errors['instagram_url'] = "Invalid Instagram URL format.";
    }
    if ($new_commission_rate !== null && ($new_commission_rate < 0 || $new_commission_rate > 100)) {
        $errors['commission_rate'] = "Commission rate must be between 0 and 100.";
    }


    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Check if brand name already exists for another brand (excluding current brand)
            $stmt_check_brand = $db->prepare("SELECT brand_id FROM brands WHERE brand_name = :brand_name AND brand_id != :current_brand_id");
            $stmt_check_brand->bindParam(':brand_name', $new_brand_name);
            $stmt_check_brand->bindParam(':current_brand_id', $current_brand_id, PDO::PARAM_INT);
            $stmt_check_brand->execute();
            if ($stmt_check_brand->fetch()) {
                $errors['brand_name'] = "A brand with this name already exists.";
            }

            if (empty($errors)) {
                $update_sql = "UPDATE brands SET
                                brand_name = :brand_name,
                                brand_logo_url = :brand_logo_url,
                                brand_description = :brand_description,
                                brand_contact_email = :brand_contact_email,
                                brand_contact_phone = :brand_contact_phone,
                                brand_website_url = :brand_website_url,
                                facebook_url = :facebook_url,
                                instagram_url = :instagram_url,
                                commission_rate = :commission_rate,
                                updated_at = NOW()
                               WHERE brand_id = :brand_id AND user_id = :user_id"; // Double-check ownership
                $stmt_update = $db->prepare($update_sql);

                $params_update = [
                    ':brand_name' => $new_brand_name,
                    ':brand_logo_url' => $brand_logo_url_db_path_update,
                    ':brand_description' => $new_brand_description ?: null,
                    ':brand_contact_email' => $new_brand_contact_email ?: null,
                    ':brand_contact_phone' => $new_brand_contact_phone ?: null,
                    ':brand_website_url' => $new_brand_website_url ?: null,
                    ':facebook_url' => $new_facebook_url ?: null,
                    ':instagram_url' => $new_instagram_url ?: null,
                    ':commission_rate' => ($new_commission_rate === null || $new_commission_rate === '') ? null : $new_commission_rate,
                    ':brand_id' => $current_brand_id,
                    ':user_id' => $_SESSION['user_id']
                ];

                if ($stmt_update->execute($params_update)) {
                    $db->commit();
                    // Delete old logo file if a new one was uploaded and DB update was successful
                    if ($old_logo_file_to_delete && file_exists($old_logo_file_to_delete)) {
                        @unlink($old_logo_file_to_delete);
                    }

                    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message success'>Brand settings for '" . htmlspecialchars($new_brand_name) . "' updated successfully.</div>";
                    // Update session brand name in case it changed
                    $_SESSION['brand_name'] = $new_brand_name;
                    // Redirect to refresh page and clear POST data
                    header("Location: settings.php");
                    exit;
                } else {
                    $db->rollBack();
                    if ($brand_logo_url_db_path_update && $brand_logo_url_db_path_update !== $brand_settings['brand_logo_url'] && file_exists(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update)) {
                         @unlink(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update);
                    }
                    $message = "<div class='brand-admin-message error'>Failed to update brand settings.</div>";
                }
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Brand Admin Settings - Error updating brand settings: " . $e->getMessage());
            if ($brand_logo_url_db_path_update && $brand_logo_url_db_path_update !== $brand_settings['brand_logo_url'] && file_exists(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update)) {
                @unlink(PUBLIC_UPLOADS_PATH . $brand_logo_url_db_path_update);
            }
            if ($e->getCode() == '23000') {
                 $message = "<div class='brand-admin-message error'>Update failed. The brand name might already be in use.</div>";
            } else {
                $message = "<div class='brand-admin-message error'>An error occurred while updating the brand settings.</div>";
            }
        }
    } else {
        $message = "<div class='brand-admin-message error'>Please correct the errors below and try again.</div>";
        // Repopulate form with POSTed values on error
        $brand_settings['brand_name'] = $new_brand_name;
        $brand_settings['brand_description'] = $new_brand_description;
        $brand_settings['brand_contact_email'] = $new_brand_contact_email;
        $brand_settings['brand_contact_phone'] = $new_brand_contact_phone;
        $brand_settings['brand_website_url'] = $new_brand_website_url;
        $brand_settings['facebook_url'] = $new_facebook_url;
        $brand_settings['instagram_url'] = $new_instagram_url;
        $brand_settings['commission_rate'] = $new_commission_rate;
        $brand_settings['brand_logo_url'] = $brand_logo_url_db_path_update; // Keep new URL if upload succeeded but other validation failed
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>

<h1 class="brand-admin-page-title"><?php echo htmlspecialchars($brand_admin_page_title); ?> for <?php echo htmlspecialchars($current_brand_name); ?></h1>

<?php
if (isset($_SESSION['brand_admin_message'])) {
    echo $_SESSION['brand_admin_message'];
    unset($_SESSION['brand_admin_message']);
}
if ($message) echo $message;
if (!empty($errors['csrf'])) echo "<div class='brand-admin-message error'>".$errors['csrf']."</div>";
?>

<?php if ($brand_settings): ?>
    <form action="settings.php" method="POST" class="brand-admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <fieldset>
            <legend>Brand Profile</legend>
            <div class="form-group">
                <label for="brand_name">Brand Name <span style="color:red;">*</span></label>
                <input type="text" id="brand_name" name="brand_name" value="<?php echo htmlspecialchars($brand_settings['brand_name']); ?>" required>
                <?php if (isset($errors['brand_name'])): ?><small style="color:red;"><?php echo $errors['brand_name']; ?></small><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="brand_description">Brand Description</label>
                <textarea id="brand_description" name="brand_description" rows="5"><?php echo htmlspecialchars($brand_settings['brand_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="brand_logo">Current Brand Logo</label>
                <?php if (!empty($brand_settings['brand_logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $brand_settings['brand_logo_url']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($brand_settings['brand_name']); ?> Logo" style="max-width: 150px; max-height: 150px; display:block; margin-bottom:10px; border-radius: 4px; background: #f0f0f0; padding: 5px;">
                    <small>Current: <?php echo htmlspecialchars($brand_settings['brand_logo_url']); ?></small>
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
            <legend>Contact Information</legend>
            <div class="form-group">
                <label for="brand_contact_email">Contact Email</label>
                <input type="email" id="brand_contact_email" name="brand_contact_email" value="<?php echo htmlspecialchars($brand_settings['brand_contact_email'] ?? ''); ?>">
                <?php if (isset($errors['brand_contact_email'])): ?><small style="color:red;"><?php echo $errors['brand_contact_email']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="brand_contact_phone">Contact Phone</label>
                <input type="tel" id="brand_contact_phone" name="brand_contact_phone" value="<?php echo htmlspecialchars($brand_settings['brand_contact_phone'] ?? ''); ?>">
                <?php if (isset($errors['brand_contact_phone'])): ?><small style="color:red;"><?php echo $errors['brand_contact_phone']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="brand_website_url">Website URL</label>
                <input type="url" id="brand_website_url" name="brand_website_url" value="<?php echo htmlspecialchars($brand_settings['brand_website_url'] ?? ''); ?>" placeholder="https://example.com">
                <?php if (isset($errors['brand_website_url'])): ?><small style="color:red;"><?php echo $errors['brand_website_url']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="facebook_url">Facebook URL</label>
                <input type="url" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($brand_settings['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/yourbrand">
                <?php if (isset($errors['facebook_url'])): ?><small style="color:red;"><?php echo $errors['facebook_url']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="instagram_url">Instagram URL</label>
                <input type="url" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($brand_settings['instagram_url'] ?? ''); ?>" placeholder="https://instagram.com/yourbrand">
                <?php if (isset($errors['instagram_url'])): ?><small style="color:red;"><?php echo $errors['instagram_url']; ?></small><?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Financial Settings</legend>
            <div class="form-group">
                <label>Assigned Brand Admin User:</label>
                <p>
                    <strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'N/A'); ?>
                </p>
                <small>Your user account is linked to this brand. To change the assigned admin, please contact a Super Admin.</small>
            </div>
            <div class="form-group">
                <label for="commission_rate">Your Commission Rate (%)</label>
                <input type="number" id="commission_rate" name="commission_rate" value="<?php echo htmlspecialchars(number_format((float)$brand_settings['commission_rate'], 2, '.', '')); ?>" step="0.01" min="0" max="100" placeholder="e.g., 10.00" readonly disabled>
                <small>This rate is set by the Super Admin and cannot be changed here.</small>
            </div>
            <div class="form-group">
                <label>Brand Approval Status:</label>
                <p>
                    <?php if ($brand_settings['is_approved']): ?>
                        <span style="color: green; font-weight:bold;">Approved</span>
                    <?php else: ?>
                        <span style="color: orange; font-weight:bold;">Pending Approval / Not Approved</span>
                    <?php endif; ?>
                </p>
                <small>Your brand's visibility on the public site depends on this status, managed by Super Admin.</small>
            </div>
        </fieldset>

        <button type="submit" name="save_brand_settings" class="btn-submit">Save Brand Settings</button>
    </form>
<?php else: ?>
    <p class="brand-admin-message error">Brand settings could not be loaded.</p>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>
