<?php
// public/register.php

// Define a page-specific title
$page_title = "Create Your BaladyMall Account";

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php'; // Path to config from current file

// Ensure config.php is loaded first
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // Fallback if the above path fails
    $alt_config_path = dirname(__DIR__) . '/src/config/config.php';
    if (file_exists($alt_config_path)) {
        require_once $alt_config_path;
    } else {
        die("Critical error: Main configuration file not found. Please check paths.");
    }
}

// Define header and footer paths using PROJECT_ROOT_PATH for robustness if available.
$header_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/header.php' : __DIR__ . '/../src/includes/header.php';
$footer_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/footer.php' : __DIR__ . '/../src/includes/footer.php';

// The header.php include will handle session_start()
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Determine redirect based on role, using get_asset_url for consistency
    $redirect_url_if_already_loggedin = get_asset_url("my_account.php");
    // The specific admin/brand_admin URLs should ideally be defined as constants in config.php
    // For now, using direct paths via get_asset_url.
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'brand_admin') {
            $redirect_url_if_already_loggedin = get_asset_url("brand_admin/index.php");
        } elseif ($_SESSION['role'] === 'super_admin') {
            $redirect_url_if_already_loggedin = get_asset_url("admin/index.php");
        }
    }
    header("Location: " . $redirect_url_if_already_loggedin);
    exit;
}

// Ensure $db is available from config.php
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        $errors['form'] = "Database connection is not available. Please try again later.";
    }
}

$errors = $errors ?? []; // Initialize if not already set (e.g. by DB check)
$success_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Sanitize and validate inputs
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW));
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
    $shipping_address_line1 = trim(filter_input(INPUT_POST, 'shipping_address_line1', FILTER_UNSAFE_RAW));
    $shipping_address_line2 = trim(filter_input(INPUT_POST, 'shipping_address_line2', FILTER_UNSAFE_RAW));
    $shipping_city = trim(filter_input(INPUT_POST, 'shipping_city', FILTER_UNSAFE_RAW));
    $shipping_governorate = trim(filter_input(INPUT_POST, 'shipping_governorate', FILTER_UNSAFE_RAW));
    $shipping_postal_code = trim(filter_input(INPUT_POST, 'shipping_postal_code', FILTER_UNSAFE_RAW));

    // Basic Validations
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = "Username must be between 3 and 50 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "Username can only contain letters, numbers, and underscores.";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (empty($phone_number)) {
        $errors['phone_number'] = "Phone number is required.";
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone_number)) {
        $errors['phone_number'] = "Invalid phone number format.";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters long.";
    }

    if ($password !== $password_confirm) {
        $errors['password_confirm'] = "Passwords do not match.";
    }

    if (empty($first_name)) $errors['first_name'] = "First name is required.";
    if (empty($last_name)) $errors['last_name'] = "Last name is required.";
    if (empty($shipping_address_line1)) $errors['shipping_address_line1'] = "Address Line 1 is required.";
    if (empty($shipping_city)) $errors['shipping_city'] = "City is required.";
    if (empty($shipping_governorate)) $errors['shipping_governorate'] = "Governorate is required.";

    // If no validation errors and DB connection exists
    if (empty($errors) && isset($db) && $db instanceof PDO) {
        try {
            $stmt_check = $db->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email OR phone_number = :phone_number LIMIT 1");
            $stmt_check->bindParam(':username', $username);
            $stmt_check->bindParam(':email', $email);
            $stmt_check->bindParam(':phone_number', $phone_number);
            $stmt_check->execute();
            $existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                // More specific error messages
                $stmt_check_username = $db->prepare("SELECT user_id FROM users WHERE username = :username LIMIT 1");
                $stmt_check_username->execute([':username' => $username]);
                if ($stmt_check_username->fetch()) $errors['username'] = "This username is already taken.";

                $stmt_check_email = $db->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
                $stmt_check_email->execute([':email' => $email]);
                if ($stmt_check_email->fetch()) $errors['email'] = "This email address is already registered.";
                
                $stmt_check_phone = $db->prepare("SELECT user_id FROM users WHERE phone_number = :phone_number LIMIT 1");
                $stmt_check_phone->execute([':phone_number' => $phone_number]);
                if ($stmt_check_phone->fetch()) $errors['phone_number'] = "This phone number is already registered.";

                if(empty($errors)) $errors['form'] = "An account with the provided details already exists."; // Fallback if individual checks don't populate

            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, phone_number, first_name, last_name, 
                        shipping_address_line1, shipping_address_line2, shipping_city, 
                        shipping_governorate, shipping_postal_code, role, is_active, email_verified_at
                    ) VALUES (
                        :username, :email, :password, :phone_number, :first_name, :last_name,
                        :shipping_address_line1, :shipping_address_line2, :shipping_city,
                        :shipping_governorate, :shipping_postal_code, 'customer', 1, NULL 
                    )
                "); // Default role to customer, is_active to 1 (or 0 if email verification is strict)
                   // email_verified_at to NULL initially

                $insert_stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':phone_number' => $phone_number,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':shipping_address_line1' => $shipping_address_line1,
                    ':shipping_address_line2' => $shipping_address_line2,
                    ':shipping_city' => $shipping_city,
                    ':shipping_governorate' => $shipping_governorate,
                    ':shipping_postal_code' => $shipping_postal_code
                ]);
                
                // $new_user_id = $db->lastInsertId();
                // TODO: Implement email verification (send token, etc.)
                // For now, redirect to login with a success message.
                header("Location: " . get_asset_url("login.php?registration=success"));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            if ($e->getCode() == '23000') { // SQLSTATE for unique constraint violation
                 $errors['form'] = "An account with this username, email, or phone number already exists.";
            } else {
                $errors['form'] = "An error occurred during registration. Please try again later.";
            }
        }
    }
}
?>

<section class="auth-form-section">
    <h2>Create Account</h2>
    <br><br>

    <?php if (!empty($errors['form'])): ?>
        <div class="form-message error-message">
            <?php echo esc_html($errors['form']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="auth-form" novalidate>
        
            
            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?php echo esc_html($_POST['username'] ?? ''); ?>" required aria-describedby="usernameError">
                <?php if (isset($errors['username'])): ?><span id="usernameError" class="error-text"><?php echo esc_html($errors['username']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo esc_html($_POST['email'] ?? ''); ?>" required aria-describedby="emailError">
                <?php if (isset($errors['email'])): ?><span id="emailError" class="error-text"><?php echo esc_html($errors['email']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo esc_html($_POST['phone_number'] ?? ''); ?>" required placeholder="+201XXXXXXXXX" aria-describedby="phoneError">
                <?php if (isset($errors['phone_number'])): ?><span id="phoneError" class="error-text"><?php echo esc_html($errors['phone_number']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span> (Min. 6 characters)</label>
                <input type="password" id="password" name="password" required aria-describedby="passwordError">
                <?php if (isset($errors['password'])): ?><span id="passwordError" class="error-text"><?php echo esc_html($errors['password']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required aria-describedby="passwordConfirmError">
                <?php if (isset($errors['password_confirm'])): ?><span id="passwordConfirmError" class="error-text"><?php echo esc_html($errors['password_confirm']); ?></span><?php endif; ?>
            </div>
        

        
            
             <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" value="<?php echo esc_html($_POST['first_name'] ?? ''); ?>" required aria-describedby="firstNameError">
                <?php if (isset($errors['first_name'])): ?><span id="firstNameError" class="error-text"><?php echo esc_html($errors['first_name']); ?></span><?php endif; ?>
            </div>
             <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" value="<?php echo esc_html($_POST['last_name'] ?? ''); ?>" required aria-describedby="lastNameError">
                <?php if (isset($errors['last_name'])): ?><span id="lastNameError" class="error-text"><?php echo esc_html($errors['last_name']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_address_line1">Address Line 1 <span class="required">*</span></label>
                <input type="text" id="shipping_address_line1" name="shipping_address_line1" value="<?php echo esc_html($_POST['shipping_address_line1'] ?? ''); ?>" required aria-describedby="address1Error">
                <?php if (isset($errors['shipping_address_line1'])): ?><span id="address1Error" class="error-text"><?php echo esc_html($errors['shipping_address_line1']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_address_line2">Address Line 2 (Optional)</label>
                <input type="text" id="shipping_address_line2" name="shipping_address_line2" value="<?php echo esc_html($_POST['shipping_address_line2'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="shipping_city">City <span class="required">*</span></label>
                <input type="text" id="shipping_city" name="shipping_city" value="<?php echo esc_html($_POST['shipping_city'] ?? ''); ?>" required aria-describedby="cityError">
                <?php if (isset($errors['shipping_city'])): ?><span id="cityError" class="error-text"><?php echo esc_html($errors['shipping_city']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_governorate">Governorate <span class="required">*</span></label>
                <input type="text" id="shipping_governorate" name="shipping_governorate" value="<?php echo esc_html($_POST['shipping_governorate'] ?? ''); ?>" required aria-describedby="governorateError">
                <?php if (isset($errors['shipping_governorate'])): ?><span id="governorateError" class="error-text"><?php echo esc_html($errors['shipping_governorate']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_postal_code">Postal Code (Optional)</label>
                <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="<?php echo esc_html($_POST['shipping_postal_code'] ?? ''); ?>">
            </div>
        

        <div class="form-group">
            <button type="submit" name="register" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </div>

        <p class="form-switch-link">Already have an account? <a href="<?php echo get_asset_url('login.php'); ?>">Login here</a>.</p>
    </form>
</section>

<?php
// Include the footer
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>