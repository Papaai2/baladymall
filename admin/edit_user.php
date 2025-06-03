<?php
// admin/edit_user.php - Super Admin User Management (Edit User)

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN EDIT USER ERROR: Main config.php not found.");
}
require_once 'auth_check.php';

$admin_page_title = "Edit User";
$message = '';
$user_data = null;
$user_id_to_edit = null;

$db = getPDOConnection();

// Available roles for the dropdown
$available_roles = ['customer', 'brand_admin', 'super_admin'];

if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $user_id_to_edit = (int)$_GET['user_id'];

    // Fetch user data for editing
    try {
        $stmt = $db->prepare("SELECT user_id, username, email, first_name, last_name, role, is_active, phone_number FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id_to_edit, PDO::PARAM_INT);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $_SESSION['user_management_message'] = "<div class='admin-message error'>User not found.</div>";
            header("Location: users.php");
            exit;
        }
        $admin_page_title = "Edit User: " . htmlspecialchars($user_data['username']);
    } catch (PDOException $e) {
        error_log("Admin Edit User - Error fetching user: " . $e->getMessage());
        $message = "<div class='admin-message error'>Could not load user data.</div>";
    }
} else {
    $_SESSION['user_management_message'] = "<div class='admin-message error'>Invalid user ID specified.</div>";
    header("Location: users.php");
    exit;
}

// Handle form submission for updating user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user']) && $user_data) {
    $new_email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $new_first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
    $new_last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
    $new_phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW));
    $new_role = trim(filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW));
    $new_is_active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];

    if (empty($new_email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }
    // Add more validation for first_name, last_name, phone_number if needed

    if (!in_array($new_role, $available_roles)) {
        $errors['role'] = "Invalid role selected.";
    }
    
    // Prevent self-demotion or deactivation for the currently logged-in super admin
    if ($user_id_to_edit === $_SESSION['user_id']) {
        if ($new_role !== 'super_admin') {
            $errors['role'] = "You cannot change your own role from Super Admin.";
            $new_role = 'super_admin'; // Force it back
        }
        if ($new_is_active === 0) {
            $errors['is_active'] = "You cannot deactivate your own Super Admin account.";
            $new_is_active = 1; // Force it back
        }
    }


    if (empty($errors)) {
        try {
            // Check if email is being changed and if the new email is already taken by another user
            if ($new_email !== $user_data['email']) {
                $stmt_check_email = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id_to_edit");
                $stmt_check_email->bindParam(':email', $new_email);
                $stmt_check_email->bindParam(':user_id_to_edit', $user_id_to_edit, PDO::PARAM_INT);
                $stmt_check_email->execute();
                if ($stmt_check_email->fetch()) {
                    $errors['email'] = "This email address is already in use by another account.";
                }
            }
             // Check if phone is being changed and if the new phone is already taken by another user
            if (!empty($new_phone_number) && $new_phone_number !== $user_data['phone_number']) {
                $stmt_check_phone = $db->prepare("SELECT user_id FROM users WHERE phone_number = :phone AND user_id != :user_id_to_edit");
                $stmt_check_phone->bindParam(':phone', $new_phone_number);
                $stmt_check_phone->bindParam(':user_id_to_edit', $user_id_to_edit, PDO::PARAM_INT);
                $stmt_check_phone->execute();
                if ($stmt_check_phone->fetch()) {
                    $errors['phone_number'] = "This phone number is already in use by another account.";
                }
            }


            if (empty($errors)) {
                $update_sql = "UPDATE users SET 
                                email = :email, 
                                first_name = :first_name, 
                                last_name = :last_name, 
                                phone_number = :phone_number,
                                role = :role, 
                                is_active = :is_active 
                               WHERE user_id = :user_id";
                $stmt_update = $db->prepare($update_sql);
                $stmt_update->bindParam(':email', $new_email);
                $stmt_update->bindParam(':first_name', $new_first_name);
                $stmt_update->bindParam(':last_name', $new_last_name);
                $stmt_update->bindParam(':phone_number', $new_phone_number);
                $stmt_update->bindParam(':role', $new_role);
                $stmt_update->bindParam(':is_active', $new_is_active, PDO::PARAM_INT);
                $stmt_update->bindParam(':user_id', $user_id_to_edit, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    $_SESSION['user_management_message'] = "<div class='admin-message success'>User '" . htmlspecialchars($user_data['username']) . "' updated successfully.</div>";
                    
                    // If a user is made a brand_admin, you might need to create a corresponding brand entry
                    // or have a separate workflow for that. For now, this just changes the role.
                    if ($user_data['role'] !== 'brand_admin' && $new_role === 'brand_admin') {
                         $_SESSION['user_management_message'] .= "<div class='admin-message info'>User role changed to Brand Admin. Remember to associate them with a brand if needed.</div>";
                    }

                    header("Location: users.php");
                    exit;
                } else {
                    $message = "<div class='admin-message error'>Failed to update user.</div>";
                }
            }
        } catch (PDOException $e) {
            error_log("Admin Edit User - Error updating user: " . $e->getMessage());
            if ($e->getCode() == '23000') { // Integrity constraint violation (e.g. unique email)
                 $message = "<div class='admin-message error'>Update failed. The email or phone number might already be in use.</div>";
            } else {
                $message = "<div class='admin-message error'>An error occurred while updating the user.</div>";
            }
        }
    }
    // If errors, repopulate user_data with POSTed values for sticky form
    if (!empty($errors)) {
        $user_data['email'] = $new_email;
        $user_data['first_name'] = $new_first_name;
        $user_data['last_name'] = $new_last_name;
        $user_data['phone_number'] = $new_phone_number;
        $user_data['role'] = $new_role;
        $user_data['is_active'] = $new_is_active;
    }
}


include_once 'includes/header.php';
?>

<h1 class="admin-page-title"><?php echo $admin_page_title; ?></h1>
<p><a href="users.php">&laquo; Back to User List</a></p>

<?php if ($message) echo $message; ?>

<?php if ($user_data): ?>
    <form action="edit_user.php?user_id=<?php echo $user_id_to_edit; ?>" method="POST" class="admin-form" style="max-width: 600px;">
        <div class="form-group">
            <label for="username">Username (cannot be changed)</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly disabled>
        </div>

        <div class="form-group">
            <label for="email">Email Address <span style="color:red;">*</span></label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
            <?php if (isset($errors['email'])): ?><small style="color:red;"><?php echo $errors['email']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>">
            <?php if (isset($errors['first_name'])): ?><small style="color:red;"><?php echo $errors['first_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>">
            <?php if (isset($errors['last_name'])): ?><small style="color:red;"><?php echo $errors['last_name']; ?></small><?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number']); ?>">
            <?php if (isset($errors['phone_number'])): ?><small style="color:red;"><?php echo $errors['phone_number']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="role">Role <span style="color:red;">*</span></label>
            <select id="role" name="role" required>
                <?php foreach ($available_roles as $role_value): ?>
                    <option value="<?php echo $role_value; ?>" <?php echo ($user_data['role'] === $role_value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role_value))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role'])): ?><small style="color:red;"><?php echo $errors['role']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="is_active">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($user_data['is_active'] == 1) ? 'checked' : ''; ?>>
                Account Active
            </label>
            <?php if (isset($errors['is_active'])): ?><small style="color:red; display:block;"><?php echo $errors['is_active']; ?></small><?php endif; ?>
        </div>
        
        <div class="form-group">
            <p><small>Password can be changed by the user via "Forgot Password" or their account settings. Admins typically do not set user passwords directly here for security reasons, unless implementing a password reset feature.</small></p>
        </div>

        <button type="submit" name="update_user" class="btn-submit">Update User</button>
    </form>
<?php else: ?>
    <?php if (empty($message)): ?>
    <p class="admin-message error">User data could not be loaded.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>
