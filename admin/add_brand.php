<?php
// admin/add_brand.php - Super Admin: Add New Brand

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ADD BRAND ERROR: Main config.php not found.");
}
require_once 'auth_check.php';

$admin_page_title = "Add New Brand";
$message = '';
$errors = [];

// Posted data for sticky form
$brand_name_form = '';
$brand_description_form = '';
$brand_contact_email_form = '';
$brand_contact_phone_form = '';
$brand_website_url_form = '';
$commission_rate_form = '';
$user_id_form = '';

$db = getPDOConnection();

// Fetch users with 'brand_admin' role who are not already assigned to a brand
$available_brand_admins = [];
try {
    $stmt_admins = $db->query("SELECT u.user_id, u.username, u.first_name, u.last_name 
                               FROM users u
                               LEFT JOIN brands b ON u.user_id = b.user_id
                               WHERE u.role = 'brand_admin' AND b.brand_id IS NULL AND u.is_active = 1
                               ORDER BY u.username ASC");
    $available_brand_admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Add Brand - Error fetching available brand admins: " . $e->getMessage());
    $message = "<div class='admin-message error'>Could not load available brand admins. Please ensure users with 'brand_admin' role exist and are unassigned.</div>";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_brand'])) {
    $brand_name_form = trim(filter_input(INPUT_POST, 'brand_name', FILTER_UNSAFE_RAW));
    $brand_description_form = trim(filter_input(INPUT_POST, 'brand_description', FILTER_UNSAFE_RAW));
    $brand_contact_email_form = trim(filter_input(INPUT_POST, 'brand_contact_email', FILTER_SANITIZE_EMAIL));
    $brand_contact_phone_form = trim(filter_input(INPUT_POST, 'brand_contact_phone', FILTER_UNSAFE_RAW));
    $brand_website_url_form = trim(filter_input(INPUT_POST, 'brand_website_url', FILTER_SANITIZE_URL));
    $commission_rate_form = filter_input(INPUT_POST, 'commission_rate', FILTER_VALIDATE_FLOAT);
    $user_id_form = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); // Brand Admin User ID

    if (empty($brand_name_form)) $errors['brand_name'] = "Brand name is required.";
    if (empty($user_id_form)) {
        $errors['user_id'] = "A Brand Admin user must be selected.";
    } else {
        // Validate if the selected user_id is a valid, unassigned brand_admin
        $is_valid_admin_selection = false;
        foreach($available_brand_admins as $admin_user) {
            if ($admin_user['user_id'] == $user_id_form) {
                $is_valid_admin_selection = true;
                break;
            }
        }
        if (!$is_valid_admin_selection && !empty($available_brand_admins)) { // Check if list wasn't empty
             // If the selected user is not in the fetched list of available admins (could be due to form tampering or stale list)
             // Re-fetch just this one user to double check their status if list was not empty.
             // This is an extra check, usually the dropdown should be accurate.
            try {
                $stmt_check_single_admin = $db->prepare("SELECT u.user_id FROM users u LEFT JOIN brands b ON u.user_id = b.user_id WHERE u.user_id = :uid AND u.role = 'brand_admin' AND b.brand_id IS NULL AND u.is_active = 1");
                $stmt_check_single_admin->bindParam(':uid', $user_id_form, PDO::PARAM_INT);
                $stmt_check_single_admin->execute();
                if(!$stmt_check_single_admin->fetch()) {
                     $errors['user_id'] = "The selected user is not a valid or available Brand Admin.";
                }
            } catch (PDOException $e) {
                 $errors['user_id'] = "Error validating selected Brand Admin.";
            }
        } elseif(empty($available_brand_admins) && $user_id_form) {
             $errors['user_id'] = "No Brand Admins available to assign. Please create or assign one first.";
        }
    }

    if (!empty($brand_contact_email_form) && !filter_var($brand_contact_email_form, FILTER_VALIDATE_EMAIL)) {
        $errors['brand_contact_email'] = "Invalid contact email format.";
    }
    if (!empty($brand_website_url_form) && !filter_var($brand_website_url_form, FILTER_VALIDATE_URL)) {
        $errors['brand_website_url'] = "Invalid website URL format.";
    }
    if ($commission_rate_form !== null && $commission_rate_form !== '' && ($commission_rate_form < 0 || $commission_rate_form > 100)) {
        $errors['commission_rate'] = "Commission rate must be between 0 and 100.";
    }

    if (empty($errors)) {
        try {
            // Check if brand name already exists
            $stmt_check_brand = $db->prepare("SELECT brand_id FROM brands WHERE brand_name = :brand_name");
            $stmt_check_brand->bindParam(':brand_name', $brand_name_form);
            $stmt_check_brand->execute();
            if ($stmt_check_brand->fetch()) {
                $errors['brand_name'] = "A brand with this name already exists.";
            }

            if (empty($errors)) {
                $insert_sql = "INSERT INTO brands (user_id, brand_name, brand_description, brand_contact_email, brand_contact_phone, brand_website_url, commission_rate, is_approved, created_at, updated_at) 
                               VALUES (:user_id, :brand_name, :brand_description, :brand_contact_email, :brand_contact_phone, :brand_website_url, :commission_rate, 1, NOW(), NOW())";
                $stmt_insert = $db->prepare($insert_sql);
                
                $params_insert = [
                    ':user_id' => $user_id_form,
                    ':brand_name' => $brand_name_form,
                    ':brand_description' => $brand_description_form ?: null,
                    ':brand_contact_email' => $brand_contact_email_form ?: null,
                    ':brand_contact_phone' => $brand_contact_phone_form ?: null,
                    ':brand_website_url' => $brand_website_url_form ?: null,
                    ':commission_rate' => ($commission_rate_form === '' || $commission_rate_form === null) ? null : $commission_rate_form
                ];

                if ($stmt_insert->execute($params_insert)) {
                    $new_brand_id = $db->lastInsertId();
                    $_SESSION['brand_management_message'] = "<div class='admin-message success'>Brand '" . htmlspecialchars($brand_name_form) . "' added successfully (ID: {$new_brand_id}).</div>";
                    header("Location: brands.php");
                    exit;
                } else {
                    $message = "<div class='admin-message error'>Failed to add new brand.</div>";
                }
            }
        } catch (PDOException $e) {
            error_log("Admin Add Brand - Error inserting brand: " . $e->getMessage());
            if ($e->getCode() == '23000') { 
                 $message = "<div class='admin-message error'>Operation failed. The brand name or assigned user might already be in use in a conflicting way.</div>";
            } else {
                $message = "<div class='admin-message error'>An error occurred while adding the brand.</div>";
            }
        }
    }
}

include_once 'includes/header.php';
?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="brands.php">&laquo; Back to Brand List</a></p>

<?php if ($message) echo $message; ?>

<form action="add_brand.php" method="POST" class="admin-form" style="max-width: 700px;">
    <fieldset>
        <legend>Brand Details</legend>
        <div class="form-group">
            <label for="brand_name">Brand Name <span style="color:red;">*</span></label>
            <input type="text" id="brand_name" name="brand_name" value="<?php echo htmlspecialchars($brand_name_form); ?>" required>
            <?php if (isset($errors['brand_name'])): ?><small style="color:red;"><?php echo $errors['brand_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brand_description">Brand Description</label>
            <textarea id="brand_description" name="brand_description" rows="5"><?php echo htmlspecialchars($brand_description_form); ?></textarea>
        </div>
    </fieldset>

    <fieldset>
        <legend>Contact & Links</legend>
        <div class="form-group">
            <label for="brand_contact_email">Contact Email</label>
            <input type="email" id="brand_contact_email" name="brand_contact_email" value="<?php echo htmlspecialchars($brand_contact_email_form); ?>">
            <?php if (isset($errors['brand_contact_email'])): ?><small style="color:red;"><?php echo $errors['brand_contact_email']; ?></small><?php endif; ?>
        </div>
         <div class="form-group">
            <label for="brand_contact_phone">Contact Phone</label>
            <input type="tel" id="brand_contact_phone" name="brand_contact_phone" value="<?php echo htmlspecialchars($brand_contact_phone_form); ?>">
        </div>
        <div class="form-group">
            <label for="brand_website_url">Website URL</label>
            <input type="url" id="brand_website_url" name="brand_website_url" value="<?php echo htmlspecialchars($brand_website_url_form); ?>" placeholder="https://example.com">
            <?php if (isset($errors['brand_website_url'])): ?><small style="color:red;"><?php echo $errors['brand_website_url']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Administration</legend>
         <div class="form-group">
            <label for="user_id">Assign Brand Admin User <span style="color:red;">*</span></label>
            <select id="user_id" name="user_id" required>
                <option value="">-- Select a Brand Admin --</option>
                <?php if (!empty($available_brand_admins)): ?>
                    <?php foreach ($available_brand_admins as $admin): ?>
                        <option value="<?php echo $admin['user_id']; ?>" <?php echo ($user_id_form == $admin['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin['username'] . ' (' . trim($admin['first_name'] . ' ' . $admin['last_name']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                     <option value="" disabled>No available (unassigned) Brand Admins found.</option>
                <?php endif; ?>
            </select>
            <?php if (isset($errors['user_id'])): ?><small style="color:red;"><?php echo $errors['user_id']; ?></small><?php endif; ?>
            <small>Only active users with the 'brand_admin' role who are not already assigned to a brand are listed. You can manage user roles in the <a href="users.php">Users section</a>.</small>
        </div>

        <div class="form-group">
            <label for="commission_rate">Commission Rate (%)</label>
            <input type="number" id="commission_rate" name="commission_rate" value="<?php echo htmlspecialchars($commission_rate_form); ?>" step="0.01" min="0" max="100" placeholder="e.g., 15.50">
            <?php if (isset($errors['commission_rate'])): ?><small style="color:red;"><?php echo $errors['commission_rate']; ?></small><?php endif; ?>
            <small>Enter a percentage, e.g., 10 for 10%. Leave blank if not applicable yet.</small>
        </div>
        
        <p><small>Brands added via this form are automatically <strong>approved</strong>.</small></p>
    </fieldset>

    <button type="submit" name="add_brand" class="btn-submit">Add Brand</button>
</form>

<?php
include_once 'includes/footer.php';
?>
