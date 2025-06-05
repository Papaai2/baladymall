<?php
// admin/settings.php - Super Admin: Manage Site Settings

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN SETTINGS ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Site Settings";
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Define expected settings and their default values if not found
$expected_settings_keys = [
    'site_name' => 'BaladyMall',
    'admin_email' => 'admin@example.com',
    'public_contact_email' => 'support@example.com',
    'public_contact_phone' => '+201234567890',
    'default_currency_symbol' => 'EGP',
    'default_currency_code' => 'EGP',
    'platform_commission_rate' => '10.00', // Default 10%
    'maintenance_mode' => '0', // 0 = off, 1 = on
    'maintenance_message' => 'Our site is currently undergoing scheduled maintenance. We should be back shortly. Thank you for your patience.',
    'site_logo_url' => '', // Relative path from public/uploads/
    'favicon_url' => ''    // Relative path from public/uploads/
];
$current_settings = [];

// Function to get a setting value
function get_setting($db_conn, $key, $default_value = null) {
    try {
        $stmt = $db_conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default_value;
    } catch (PDOException $e) {
        error_log("Error fetching setting {$key}: " . $e->getMessage());
        return $default_value;
    }
}

// Function to update or insert a setting value
function update_setting($db_conn, $key, $value) {
    try {
        // Using VALUES(setting_value) for the update part is generally more robust.
        $stmt = $db_conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        // Coalesce empty string to null for DB if column allows NULL, otherwise, keep empty string
        $bind_value = ($value === '') ? null : $value; // Ensure empty string becomes null for nullable TEXT fields
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $bind_value);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error updating setting {$key}: " . $e->getMessage());
        return false;
    }
}

// Load current settings
foreach ($expected_settings_keys as $key => $default) {
    $current_settings[$key] = get_setting($db, $key, $default);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
    if (!$csrf_token_form || !hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $errors['csrf'] = "CSRF token mismatch. Please try again.";
    } else {
        // Sanitize and prepare settings from POST data
        $new_settings = [];
        $new_settings['site_name'] = trim(filter_input(INPUT_POST, 'site_name', FILTER_UNSAFE_RAW));
        $new_settings['admin_email'] = trim(filter_input(INPUT_POST, 'admin_email', FILTER_SANITIZE_EMAIL));
        $new_settings['public_contact_email'] = trim(filter_input(INPUT_POST, 'public_contact_email', FILTER_SANITIZE_EMAIL));
        $new_settings['public_contact_phone'] = trim(filter_input(INPUT_POST, 'public_contact_phone', FILTER_UNSAFE_RAW));

        $new_settings['default_currency_symbol'] = trim(filter_input(INPUT_POST, 'default_currency_symbol', FILTER_UNSAFE_RAW));
        $new_settings['default_currency_code'] = trim(filter_input(INPUT_POST, 'default_currency_code', FILTER_UNSAFE_RAW));
        $new_settings['platform_commission_rate'] = filter_input(INPUT_POST, 'platform_commission_rate', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100]]);

        $new_settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0';
        $new_settings['maintenance_message'] = trim(filter_input(INPUT_POST, 'maintenance_message', FILTER_UNSAFE_RAW));

        // Validation
        if (empty($new_settings['site_name'])) $errors['site_name'] = "Site name cannot be empty.";
        if (!empty($new_settings['admin_email']) && !filter_var($new_settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = "Invalid Admin Email format.";
        }
        if (!empty($new_settings['public_contact_email']) && !filter_var($new_settings['public_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['public_contact_email'] = "Invalid Public Contact Email format.";
        }
        if ($new_settings['platform_commission_rate'] === false || ($new_settings['platform_commission_rate'] !== null && ($new_settings['platform_commission_rate'] < 0 || $new_settings['platform_commission_rate'] > 100)) ) {
             $errors['platform_commission_rate'] = "Commission rate must be a number between 0 and 100.";
             // The form will stick to the value that caused the error, or the initial one
        }


        // --- Logo Upload ---
        $new_site_logo_url = $current_settings['site_logo_url']; // Keep old URL by default
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == UPLOAD_ERR_OK) {
            $upload_path_segment = 'site/'; // Subdirectory within uploads
            $upload_dir = PUBLIC_UPLOADS_PATH . $upload_path_segment;
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) { // Create directory if not exists
                     $errors['site_logo'] = "Failed to create site logo upload directory.";
                }
            }

            if (empty($errors['site_logo'])) { // Only proceed if no prior directory error
                $logo_file = $_FILES['site_logo'];
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                $file_mime_type = mime_content_type($logo_file['tmp_name']);

                if (in_array($file_mime_type, $allowed_mimes) && $logo_file['size'] <= MAX_IMAGE_SIZE) {
                    $ext = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
                    $new_logo_filename = "site-logo-" . time() . "." . $ext;
                    $destination = $upload_dir . $new_logo_filename;
                    if (move_uploaded_file($logo_file['tmp_name'], $destination)) {
                        // Delete old logo file if it was a local file
                        if ($current_settings['site_logo_url'] && !filter_var($current_settings['site_logo_url'], FILTER_VALIDATE_URL) && strpos($current_settings['site_logo_url'], '//') !== 0) {
                            $old_logo_full_path = PUBLIC_UPLOADS_PATH . $current_settings['site_logo_url'];
                            if (file_exists($old_logo_full_path) && is_file($old_logo_full_path)) {
                                unlink($old_logo_full_path);
                            }
                        }
                        $new_site_logo_url = $upload_path_segment . $new_logo_filename;
                    } else $errors['site_logo'] = "Failed to upload site logo. Check directory permissions.";
                } else $errors['site_logo'] = "Invalid logo file type or size too large. Max size: " . (defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE/1024/1024) : '5') . "MB.";
            }
        } elseif (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors['site_logo'] = "Error uploading logo: Code " . $_FILES['site_logo']['error'];
        }
        $new_settings['site_logo_url'] = $new_site_logo_url;

        // --- Favicon Upload ---
        $new_favicon_url = $current_settings['favicon_url']; // Keep old URL by default
         if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] == UPLOAD_ERR_OK) {
            $upload_path_segment = 'site/';
            $upload_dir = PUBLIC_UPLOADS_PATH . $upload_path_segment;
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) { // Create directory if not exists
                    $errors['favicon'] = "Failed to create favicon upload directory.";
                }
            }

            if (empty($errors['favicon'])) { // Only proceed if no prior directory error
                $favicon_file = $_FILES['favicon'];
                $allowed_mimes_favicon = ['image/vnd.microsoft.icon', 'image/x-icon', 'image/png', 'image/svg+xml'];
                // Favicon specific size limit (e.g., 512KB)
                $max_favicon_size = 512 * 1024; // 512KB
                if (in_array(mime_content_type($favicon_file['tmp_name']), $allowed_mimes_favicon) && $favicon_file['size'] <= $max_favicon_size) {
                    $ext = strtolower(pathinfo($favicon_file['name'], PATHINFO_EXTENSION));
                    if(!in_array($ext, ['ico', 'png', 'svg'])) $ext = 'png'; // default to png if ext is weird or not standard
                    $new_favicon_filename = "favicon-" . time() . "." . $ext;
                    $destination = $upload_dir . $new_favicon_filename;
                    if (move_uploaded_file($favicon_file['tmp_name'], $destination)) {
                        // Delete old favicon file if it was a local file
                        if ($current_settings['favicon_url'] && !filter_var($current_settings['favicon_url'], FILTER_VALIDATE_URL) && strpos($current_settings['favicon_url'], '//') !== 0) {
                            $old_favicon_full_path = PUBLIC_UPLOADS_PATH . $current_settings['favicon_url'];
                            if (file_exists($old_favicon_full_path) && is_file($old_favicon_full_path)) {
                                unlink($old_favicon_full_path);
                            }
                        }
                        $new_favicon_url = $upload_path_segment . $new_favicon_filename;
                    } else $errors['favicon'] = "Failed to upload favicon. Check directory permissions.";
                } else $errors['favicon'] = "Invalid favicon file type or size too large (max " . ($max_favicon_size / 1024) . "KB). Allowed: ico, png, svg.";
            }
        } elseif (isset($_FILES['favicon']) && $_FILES['favicon']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors['favicon'] = "Error uploading favicon: Code " . $_FILES['favicon']['error'];
        }
        $new_settings['favicon_url'] = $new_favicon_url;


        if (empty($errors)) {
            $all_saved = true;
            foreach ($new_settings as $key => $value) {
                // The update_setting function already handles coalescing empty strings to null for DB.
                if (!update_setting($db, $key, $value)) {
                    $all_saved = false;
                    error_log("Failed to save setting: {$key}. Value: " . print_r($value, true)); // Log value for debugging
                }
            }

            if ($all_saved) {
                $_SESSION['admin_message'] = "<div class='admin-message success'>Settings updated successfully.</div>";
                // Reload current settings after saving (crucial for accurate display and for subsequent script execution)
                foreach ($expected_settings_keys as $key => $default) {
                    $current_settings[$key] = get_setting($db, $key, $default);
                }
                // Also update constants in memory if needed for immediate use (though config.php defines them usually)
                // Removed the problematic re-definition of SITE_NAME.
                // If you need to immediately use the new SITE_NAME, rely on $current_settings['site_name']
                // or ensure your config.php is re-run (which it won't be on the same request).
                // The best practice is that config.php loads values from the DB on *each new request*.
            } else {
                $message = "<div class='admin-message error'>Some settings could not be saved. Please check logs.</div>";
            }
        } else {
            $message = "<div class='admin-message error'>Please correct the errors below.</div>";
            // If validation fails, repopulate $current_settings with POSTed values for sticky form
            // The logic for $new_settings already applies default values if empty, and then sets new values.
            // So, simply mapping $new_settings back to $current_settings after validation is enough.
            foreach ($new_settings as $key => $value) {
                 if (array_key_exists($key, $current_settings)) {
                    $current_settings[$key] = $value;
                 }
            }
            // For image URLs, if an upload failed, we want to revert to the original (current_settings) value
            // (the $new_site_logo_url/$new_favicon_url variables would already hold the current_settings values if upload failed).
            // So, this sticky logic is mostly implicitly correct by how $new_site_logo_url/$new_favicon_url are handled.
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<?php
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if ($message) echo $message;
if (!empty($errors['csrf'])) echo "<div class='admin-message error'>".htmlspecialchars($errors['csrf'])."</div>";
?>

<form action="settings.php" method="POST" class="admin-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <fieldset>
        <legend>General Site Settings</legend>
        <div class="form-group">
            <label for="site_name">Site Name <span style="color:red;">*</span></label>
            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" required maxlength="255">
            <?php if (isset($errors['site_name'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['site_name']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="admin_email">Administrator Email</label>
            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>">
            <?php if (isset($errors['admin_email'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['admin_email']); ?></small><?php endif; ?>
        </div>
         <div class="form-group">
            <label for="public_contact_email">Public Contact Email</label>
            <input type="email" id="public_contact_email" name="public_contact_email" value="<?php echo htmlspecialchars($current_settings['public_contact_email'] ?? ''); ?>">
            <?php if (isset($errors['public_contact_email'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['public_contact_email']); ?></small><?php endif; ?>
        </div>
         <div class="form-group">
            <label for="public_contact_phone">Public Contact Phone</label>
            <input type="tel" id="public_contact_phone" name="public_contact_phone" value="<?php echo htmlspecialchars($current_settings['public_contact_phone'] ?? ''); ?>">
            <?php if (isset($errors['public_contact_phone'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['public_contact_phone']); ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Branding</legend>
        <div class="form-group">
            <label for="site_logo">Site Logo</label>
            <?php
            $display_site_logo_url = '';
            // Check if it's an absolute URL or a relative local path
            if (!empty($current_settings['site_logo_url'])) {
                if (filter_var($current_settings['site_logo_url'], FILTER_VALIDATE_URL) || strpos($current_settings['site_logo_url'], '//') === 0) {
                    $display_site_logo_url = htmlspecialchars($current_settings['site_logo_url']);
                } else {
                    $full_local_path = PUBLIC_UPLOADS_PATH . $current_settings['site_logo_url'];
                    if (file_exists($full_local_path) && is_file($full_local_path)) {
                        $display_site_logo_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $current_settings['site_logo_url']);
                    }
                }
            }
            $fallback_logo_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '80x80/eee/aaa?text=No+Logo');
            ?>
            <?php if ($display_site_logo_url): ?>
                <img src="<?php echo $display_site_logo_url; ?>?v=<?php echo time(); ?>" alt="Current Site Logo" style="max-height: 80px; display: block; margin-bottom: 10px; background: #f0f0f0; padding: 5px; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_logo_path; ?>';">
                <small>Current: <?php echo htmlspecialchars($current_settings['site_logo_url'] ?? 'N/A'); ?></small>
            <?php else: ?>
                <p><small>No site logo uploaded.</small></p>
            <?php endif; ?>
            <input type="file" id="site_logo" name="site_logo" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
            <small>Recommended: PNG or SVG. Max size: <?php echo defined('MAX_IMAGE_SIZE') ? (MAX_IMAGE_SIZE / 1024 / 1024) : '5'; ?>MB.</small>
            <?php if (isset($errors['site_logo'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['site_logo']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="favicon">Favicon</label>
             <?php
            $display_favicon_url = '';
            if (!empty($current_settings['favicon_url'])) {
                if (filter_var($current_settings['favicon_url'], FILTER_VALIDATE_URL) || strpos($current_settings['favicon_url'], '//') === 0) {
                    $display_favicon_url = htmlspecialchars($current_settings['favicon_url']);
                } else {
                    $full_local_path = PUBLIC_UPLOADS_PATH . $current_settings['favicon_url'];
                    if (file_exists($full_local_path) && is_file($full_local_path)) {
                        $display_favicon_url = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $current_settings['favicon_url']);
                    }
                }
            }
            $fallback_favicon_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '32x32/eee/aaa?text=No+Fav');
            ?>
             <?php if ($display_favicon_url): ?>
                <img src="<?php echo $display_favicon_url; ?>?v=<?php echo time(); ?>" alt="Current Favicon" style="max-height: 32px; display: block; margin-bottom: 10px; padding: 2px; border: 1px solid #eee;" onerror="this.onerror=null; this.src='<?php echo $fallback_favicon_path; ?>';">
                <small>Current: <?php echo htmlspecialchars($current_settings['favicon_url'] ?? 'N/A'); ?></small>
            <?php else: ?>
                <p><small>No favicon uploaded.</small></p>
            <?php endif; ?>
            <input type="file" id="favicon" name="favicon" accept="image/vnd.microsoft.icon,image/x-icon,image/png,image/svg+xml">
            <small>Recommended: ICO, PNG, or SVG (e.g., 32x32px). Max size: 512KB.</small>
            <?php if (isset($errors['favicon'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['favicon']); ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Store & Financial Settings</legend>
        <div class="form-group">
            <label for="default_currency_symbol">Default Currency Symbol</label>
            <input type="text" id="default_currency_symbol" name="default_currency_symbol" value="<?php echo htmlspecialchars($current_settings['default_currency_symbol'] ?? ''); ?>" placeholder="e.g., EGP or ج.م">
            <?php if (isset($errors['default_currency_symbol'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['default_currency_symbol']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="default_currency_code">Default Currency Code (ISO 4217)</label>
            <input type="text" id="default_currency_code" name="default_currency_code" value="<?php echo htmlspecialchars($current_settings['default_currency_code'] ?? ''); ?>" placeholder="e.g., EGP">
            <?php if (isset($errors['default_currency_code'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['default_currency_code']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="platform_commission_rate">Default Platform Commission Rate (%)</label>
            <input type="number" id="platform_commission_rate" name="platform_commission_rate" value="<?php echo htmlspecialchars(number_format((float)($current_settings['platform_commission_rate'] ?? 0), 2, '.', '')); ?>" step="0.01" min="0" max="100" placeholder="e.g., 15.50">
            <small>This is the default rate. It can be overridden per brand.</small>
            <?php if (isset($errors['platform_commission_rate'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['platform_commission_rate']); ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Maintenance Mode</legend>
        <div class="form-group">
            <label>
                <input type="checkbox" name="maintenance_mode" value="1" <?php echo (($current_settings['maintenance_mode'] ?? '0') == '1') ? 'checked' : ''; ?>>
                Enable Maintenance Mode
            </label>
            <small>If enabled, the public site will display the maintenance message to visitors (except logged-in super admins).</small>
        </div>
        <div class="form-group">
            <label for="maintenance_message">Maintenance Mode Message</label>
            <textarea id="maintenance_message" name="maintenance_message" rows="4" placeholder="e.g., Site is down for scheduled maintenance. We will be back soon."><?php echo htmlspecialchars($current_settings['maintenance_message'] ?? ''); ?></textarea>
            <?php if (isset($errors['maintenance_message'])): ?><small style="color:red;"><?php echo htmlspecialchars($errors['maintenance_message']); ?></small><?php endif; ?>
        </div>
    </fieldset>

    <button type="submit" name="save_settings" class="btn-submit">Save Settings</button>
</form>

<?php
include_once 'includes/footer.php';
?>