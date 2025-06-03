<?php
// admin/edit_brand.php - Super Admin Brand Management (Edit/Approve Brand)

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

$db = getPDOConnection();

if (isset($_GET['brand_id']) && filter_var($_GET['brand_id'], FILTER_VALIDATE_INT)) {
    $brand_id_to_edit = (int)$_GET['brand_id'];

    // Handle direct actions from list page (e.g., approve)
    if (isset($_GET['action']) && $_GET['action'] === 'approve' && $brand_id_to_edit) {
        try {
            $stmt_approve = $db->prepare("UPDATE brands SET is_approved = 1 WHERE brand_id = :brand_id AND is_approved = 0");
            $stmt_approve->bindParam(':brand_id', $brand_id_to_edit, PDO::PARAM_INT);
            if ($stmt_approve->execute() && $stmt_approve->rowCount() > 0) {
                $_SESSION['brand_management_message'] = "<div class='admin-message success'>Brand approved successfully.</div>";
                // TODO: Notify brand admin user (e.g., via email)
            } else {
                $_SESSION['brand_management_message'] = "<div class='admin-message warning'>Brand was already approved or could not be approved.</div>";
            }
        } catch (PDOException $e) {
            error_log("Admin Approve Brand Error: " . $e->getMessage());
            $_SESSION['brand_management_message'] = "<div class='admin-message error'>An error occurred while approving the brand.</div>";
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
            $_SESSION['brand_management_message'] = "<div class='admin-message error'>Brand not found.</div>";
            header("Location: brands.php");
            exit;
        }
        $admin_page_title = "Edit Brand: " . htmlspecialchars($brand_data['brand_name']);
    } catch (PDOException $e) {
        error_log("Admin Edit Brand - Error fetching brand: " . $e->getMessage());
        $message = "<div class='admin-message error'>Could not load brand data.</div>";
    }
} else {
    $_SESSION['brand_management_message'] = "<div class='admin-message error'>Invalid brand ID specified.</div>";
    header("Location: brands.php");
    exit;
}

// Handle form submission for updating brand
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_brand']) && $brand_data) {
    $new_brand_name = trim(filter_input(INPUT_POST, 'brand_name', FILTER_UNSAFE_RAW));
    $new_brand_description = trim(filter_input(INPUT_POST, 'brand_description', FILTER_UNSAFE_RAW)); // Or use a more specific filter if allowing HTML
    $new_brand_contact_email = trim(filter_input(INPUT_POST, 'brand_contact_email', FILTER_SANITIZE_EMAIL));
    $new_brand_contact_phone = trim(filter_input(INPUT_POST, 'brand_contact_phone', FILTER_UNSAFE_RAW));
    $new_brand_website_url = trim(filter_input(INPUT_POST, 'brand_website_url', FILTER_SANITIZE_URL));
    $new_commission_rate = filter_input(INPUT_POST, 'commission_rate', FILTER_VALIDATE_FLOAT); // Allow decimal
    $new_is_approved = isset($_POST['is_approved']) ? 1 : 0;
    // $new_user_id_for_brand = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); // For assigning a brand admin

    $errors = [];

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
    // TODO: Validate user_id exists if you allow changing it here.

    if (empty($errors)) {
        try {
            $update_sql = "UPDATE brands SET 
                            brand_name = :brand_name, 
                            brand_description = :brand_description,
                            brand_contact_email = :brand_contact_email,
                            brand_contact_phone = :brand_contact_phone,
                            brand_website_url = :brand_website_url,
                            commission_rate = :commission_rate,
                            is_approved = :is_approved
                            -- user_id = :user_id (if allowing change)
                           WHERE brand_id = :brand_id";
            $stmt_update = $db->prepare($update_sql);
            
            $params_update = [
                ':brand_name' => $new_brand_name,
                ':brand_description' => $new_brand_description,
                ':brand_contact_email' => $new_brand_contact_email,
                ':brand_contact_phone' => $new_brand_contact_phone,
                ':brand_website_url' => $new_brand_website_url,
                ':commission_rate' => ($new_commission_rate === null || $new_commission_rate === '') ? null : $new_commission_rate,
                ':is_approved' => $new_is_approved,
                ':brand_id' => $brand_id_to_edit
            ];

            if ($stmt_update->execute($params_update)) {
                $_SESSION['brand_management_message'] = "<div class='admin-message success'>Brand '" . htmlspecialchars($new_brand_name) . "' updated successfully.</div>";
                // If status changed, potentially notify brand admin
                if ($brand_data['is_approved'] != $new_is_approved) {
                     // TODO: Send notification email to brand admin
                }
                header("Location: brands.php"); // Redirect to list after update
                exit;
            } else {
                $message = "<div class='admin-message error'>Failed to update brand.</div>";
            }
        } catch (PDOException $e) {
            error_log("Admin Edit Brand - Error updating brand: " . $e->getMessage());
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
        // $brand_data['user_id'] = $new_user_id_for_brand;
    }
}


include_once 'includes/header.php';
?>

<h1 class="admin-page-title"><?php echo $admin_page_title; ?></h1>
<p><a href="brands.php">&laquo; Back to Brand List</a></p>

<?php if ($message) echo $message; ?>

<?php if ($brand_data): ?>
    <form action="edit_brand.php?brand_id=<?php echo $brand_id_to_edit; ?>" method="POST" class="admin-form" style="max-width: 700px;">
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
