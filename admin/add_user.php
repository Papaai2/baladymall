<?php
// admin/add_user.php - Super Admin: Add New User

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ADD USER ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Add New User";
include_once 'includes/header.php';

$db = getPDOConnection();
$errors = [];
$message = '';

// Form data placeholders for sticky form
$username_form = '';
$email_form = '';
$first_name_form = '';
$last_name_form = '';
$phone_number_form = '';
$role_form = 'customer'; // Default role
$is_active_form = 1; // Default to active

// Available roles for the dropdown (must match DB ENUM)
$available_roles = ['customer', 'brand_admin', 'super_admin'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    // Sanitize and validate inputs
    $username_form = trim(filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
    $email_form = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? ''; // Raw password
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name_form = trim(filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
    $last_name_form = trim(filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
    $phone_number_form = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW));
    $role_form = trim(filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW));
    $is_active_form = isset($_POST['is_active']) ? 1 : 0;

    // Basic Validation
    if (empty($username_form)) {
        $errors['username'] = "Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username_form)) {
        $errors['username'] = "Username must be 3-20 characters long and contain only letters, numbers, and underscores.";
    }

    if (empty($email_form)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email_form, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (!in_array($role_form, $available_roles)) {
        $errors['role'] = "Invalid role selected.";
    }

    // Check for uniqueness (username, email, phone_number)
    try {
        if (empty($errors['username'])) {
            $stmt_check_username = $db->prepare("SELECT user_id FROM users WHERE username = :username");
            $stmt_check_username->bindParam(':username', $username_form);
            $stmt_check_username->execute();
            if ($stmt_check_username->fetch()) {
                $errors['username'] = "This username is already taken.";
            }
        }

        if (empty($errors['email'])) {
            $stmt_check_email = $db->prepare("SELECT user_id FROM users WHERE email = :email");
            $stmt_check_email->bindParam(':email', $email_form);
            $stmt_check_email->execute();
            if ($stmt_check_email->fetch()) {
                $errors['email'] = "This email address is already in use.";
            }
        }

        if (empty($errors['phone_number']) && !empty($phone_number_form)) {
            $stmt_check_phone = $db->prepare("SELECT user_id FROM users WHERE phone_number = :phone_number");
            $stmt_check_phone->bindParam(':phone_number', $phone_number_form);
            $stmt_check_phone->execute();
            if ($stmt_check_phone->fetch()) {
                $errors['phone_number'] = "This phone number is already in use.";
            }
        }

    } catch (PDOException $e) {
        error_log("Admin Add User - Uniqueness check error: " . $e->getMessage());
        $errors['database'] = "A database error occurred during uniqueness check.";
    }


    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_sql = "INSERT INTO users (username, password, email, first_name, last_name, role, phone_number, is_active, created_at, updated_at)
                           VALUES (:username, :password, :email, :first_name, :last_name, :role, :phone_number, :is_active, NOW(), NOW())";
            $stmt_insert = $db->prepare($insert_sql);

            $params_insert = [
                ':username' => $username_form,
                ':password' => $hashed_password,
                ':email' => $email_form,
                ':first_name' => $first_name_form ?: null,
                ':last_name' => $last_name_form ?: null,
                ':role' => $role_form,
                ':phone_number' => $phone_number_form ?: null,
                ':is_active' => $is_active_form
            ];

            if ($stmt_insert->execute($params_insert)) {
                $new_user_id = $db->lastInsertId();
                $_SESSION['user_management_message'] = "<div class='admin-message success'>User '" . htmlspecialchars($username_form) . "' (ID: {$new_user_id}) added successfully.</div>";
                header("Location: users.php"); // Redirect to user list
                exit;
            } else {
                $message = "<div class='admin-message error'>Failed to add new user.</div>";
            }

        } catch (PDOException $e) {
            error_log("Admin Add User - Error inserting user: " . $e->getMessage());
            $message = "<div class='admin-message error'>An error occurred while adding the user. " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='admin-message error'>Please correct the errors below and try again.</div>";
    }
}

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="users.php">&laquo; Back to User List</a></p>

<?php if ($message) echo $message; ?>

<form action="add_user.php" method="POST" class="admin-form" style="max-width: 600px;">
    <fieldset>
        <legend>New User Details</legend>
        <div class="form-group">
            <label for="username">Username <span style="color:red;">*</span></label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_form); ?>" required>
            <?php if (isset($errors['username'])): ?><small style="color:red;"><?php echo $errors['username']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Email Address <span style="color:red;">*</span></label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_form); ?>" required>
            <?php if (isset($errors['email'])): ?><small style="color:red;"><?php echo $errors['email']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Password <span style="color:red;">*</span></label>
            <input type="password" id="password" name="password" required>
            <?php if (isset($errors['password'])): ?><small style="color:red;"><?php echo $errors['password']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password <span style="color:red;">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <?php if (isset($errors['confirm_password'])): ?><small style="color:red;"><?php echo $errors['confirm_password']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name_form); ?>">
            <?php if (isset($errors['first_name'])): ?><small style="color:red;"><?php echo $errors['first_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name_form); ?>">
            <?php if (isset($errors['last_name'])): ?><small style="color:red;"><?php echo $errors['last_name']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number_form); ?>">
            <?php if (isset($errors['phone_number'])): ?><small style="color:red;"><?php echo $errors['phone_number']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="role">Role <span style="color:red;">*</span></label>
            <select id="role" name="role" required>
                <?php foreach ($available_roles as $role_value): ?>
                    <option value="<?php echo $role_value; ?>" <?php echo ($role_form === $role_value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role_value))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role'])): ?><small style="color:red;"><?php echo $errors['role']; ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="is_active">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($is_active_form == 1) ? 'checked' : ''; ?>>
                Account Active
            </label>
            <?php if (isset($errors['is_active'])): ?><small style="color:red; display:block;"><?php echo $errors['is_active']; ?></small><?php endif; ?>
        </div>
    </fieldset>

    <button type="submit" name="add_user" class="btn-submit">Add User</button>
</form>

<?php
include_once 'includes/footer.php';
?>
