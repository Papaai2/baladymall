<?php
// public/register.php

// Define a page-specific title
$page_title = "Create Your BaladyMall Account";

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/my_account.php");
    exit;
}

$db = getPDOConnection();
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Sanitize and validate inputs
    // FILTER_SANITIZE_STRING is deprecated in PHP 8.1. Use FILTER_UNSAFE_RAW or htmlspecialchars.
    // For general string input that will be HTML-escaped on output, FILTER_UNSAFE_RAW is a common replacement.
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW)); // Basic sanitization, rely on regex for format
    $password = $_POST['password']; // Will be hashed, not sanitized as string here
    $password_confirm = $_POST['password_confirm'];

    // Shipping address fields
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
    $shipping_address_line1 = trim(filter_input(INPUT_POST, 'shipping_address_line1', FILTER_UNSAFE_RAW));
    $shipping_address_line2 = trim(filter_input(INPUT_POST, 'shipping_address_line2', FILTER_UNSAFE_RAW));
    $shipping_city = trim(filter_input(INPUT_POST, 'shipping_city', FILTER_UNSAFE_RAW));
    $shipping_governorate = trim(filter_input(INPUT_POST, 'shipping_governorate', FILTER_UNSAFE_RAW));
    $shipping_postal_code = trim(filter_input(INPUT_POST, 'shipping_postal_code', FILTER_UNSAFE_RAW));
    // shipping_country defaults to 'Egypt' in DB

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
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone_number)) { // Basic phone validation
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

    // Required shipping fields (can add more validation as needed)
    if (empty($first_name)) $errors['first_name'] = "First name is required.";
    if (empty($last_name)) $errors['last_name'] = "Last name is required.";
    if (empty($shipping_address_line1)) $errors['shipping_address_line1'] = "Address Line 1 is required.";
    if (empty($shipping_city)) $errors['shipping_city'] = "City is required.";
    if (empty($shipping_governorate)) $errors['shipping_governorate'] = "Governorate is required.";


    // If no validation errors, check for existing user and insert
    if (empty($errors) && $db) {
        try {
            // Check if username or email or phone_number already exists
            $stmt_check = $db->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email OR phone_number = :phone_number LIMIT 1");
            $stmt_check->bindParam(':username', $username);
            $stmt_check->bindParam(':email', $email);
            $stmt_check->bindParam(':phone_number', $phone_number);
            $stmt_check->execute();
            $existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                // Determine which field caused the conflict for a more specific error
                // This requires re-checking each field individually if you want specific messages
                // For simplicity, a general message is used.
                $errors['form'] = "An account with this username, email, or phone number already exists.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user (role defaults to 'customer' as per DB schema)
                $insert_stmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, phone_number, first_name, last_name, 
                        shipping_address_line1, shipping_address_line2, shipping_city, 
                        shipping_governorate, shipping_postal_code
                    ) VALUES (
                        :username, :email, :password, :phone_number, :first_name, :last_name,
                        :shipping_address_line1, :shipping_address_line2, :shipping_city,
                        :shipping_governorate, :shipping_postal_code
                    )
                ");

                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':phone_number', $phone_number);
                $insert_stmt->bindParam(':first_name', $first_name);
                $insert_stmt->bindParam(':last_name', $last_name);
                $insert_stmt->bindParam(':shipping_address_line1', $shipping_address_line1);
                $insert_stmt->bindParam(':shipping_address_line2', $shipping_address_line2);
                $insert_stmt->bindParam(':shipping_city', $shipping_city);
                $insert_stmt->bindParam(':shipping_governorate', $shipping_governorate);
                $insert_stmt->bindParam(':shipping_postal_code', $shipping_postal_code);
                
                if ($insert_stmt->execute()) {
                    $new_user_id = $db->lastInsertId();
                    // TODO: Implement email verification process (send verification email)
                    // For now, we'll just show a success message.
                    $success_message = "Registration successful! You can now login.";
                    
                    // Clear form fields by effectively resetting the POST array for this request
                    // This prevents data from repopulating the form if the page isn't redirected.
                    $_POST = []; 
                    // A redirect to the login page with a success message is often a better UX:
                    // header("Location: " . SITE_URL . "/login.php?status=registered");
                    // exit;
                } else {
                    $errors['form'] = "Registration failed due to a database error. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            // Check for unique constraint violation (error code 23000 for SQLSTATE)
            if ($e->getCode() == '23000') {
                 $errors['form'] = "An account with this username, email, or phone number already exists. Please use different details.";
            } else {
                $errors['form'] = "An error occurred during registration. Please try again later.";
            }
        }
    }
}


// Include header
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}
?>

<section class="auth-form-section">
    <h2>Create Account</h2>
    <p>Join BaladyMall and start discovering amazing local products!</p>

    <?php if ($success_message): ?>
        <div class="form-message success-message">
            <?php echo htmlspecialchars($success_message); ?>
            <p><a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-secondary">Click here to Login</a></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['form']) && !$success_message): // Show general form error only if no success message ?>
        <div class="form-message error-message">
            <?php echo htmlspecialchars($errors['form']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$success_message): // Hide form if registration was successful ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="auth-form" novalidate>
        <fieldset>
            <legend>Account Details</legend>
            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required aria-describedby="usernameError">
                <?php if (isset($errors['username'])): ?><span id="usernameError" class="error-text"><?php echo $errors['username']; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required aria-describedby="emailError">
                <?php if (isset($errors['email'])): ?><span id="emailError" class="error-text"><?php echo $errors['email']; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required placeholder="+201XXXXXXXXX" aria-describedby="phoneError">
                <?php if (isset($errors['phone_number'])): ?><span id="phoneError" class="error-text"><?php echo $errors['phone_number']; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required aria-describedby="passwordError">
                <?php if (isset($errors['password'])): ?><span id="passwordError" class="error-text"><?php echo $errors['password']; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required aria-describedby="passwordConfirmError">
                <?php if (isset($errors['password_confirm'])): ?><span id="passwordConfirmError" class="error-text"><?php echo $errors['password_confirm']; ?></span><?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Shipping Address</legend>
             <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required aria-describedby="firstNameError">
                <?php if (isset($errors['first_name'])): ?><span id="firstNameError" class="error-text"><?php echo $errors['first_name']; ?></span><?php endif; ?>
            </div>
             <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required aria-describedby="lastNameError">
                <?php if (isset($errors['last_name'])): ?><span id="lastNameError" class="error-text"><?php echo $errors['last_name']; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_address_line1">Address Line 1 <span class="required">*</span></label>
                <input type="text" id="shipping_address_line1" name="shipping_address_line1" value="<?php echo isset($_POST['shipping_address_line1']) ? htmlspecialchars($_POST['shipping_address_line1']) : ''; ?>" required aria-describedby="address1Error">
                <?php if (isset($errors['shipping_address_line1'])): ?><span id="address1Error" class="error-text"><?php echo $errors['shipping_address_line1']; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_address_line2">Address Line 2 (Optional)</label>
                <input type="text" id="shipping_address_line2" name="shipping_address_line2" value="<?php echo isset($_POST['shipping_address_line2']) ? htmlspecialchars($_POST['shipping_address_line2']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="shipping_city">City <span class="required">*</span></label>
                <input type="text" id="shipping_city" name="shipping_city" value="<?php echo isset($_POST['shipping_city']) ? htmlspecialchars($_POST['shipping_city']) : ''; ?>" required aria-describedby="cityError">
                <?php if (isset($errors['shipping_city'])): ?><span id="cityError" class="error-text"><?php echo $errors['shipping_city']; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_governorate">Governorate <span class="required">*</span></label>
                <input type="text" id="shipping_governorate" name="shipping_governorate" value="<?php echo isset($_POST['shipping_governorate']) ? htmlspecialchars($_POST['shipping_governorate']) : ''; ?>" required aria-describedby="governorateError">
                <?php if (isset($errors['shipping_governorate'])): ?><span id="governorateError" class="error-text"><?php echo $errors['shipping_governorate']; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="shipping_postal_code">Postal Code (Optional)</label>
                <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="<?php echo isset($_POST['shipping_postal_code']) ? htmlspecialchars($_POST['shipping_postal_code']) : ''; ?>">
            </div>
        </fieldset>

        <div class="form-group">
            <button type="submit" name="register" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </div>

        <p class="form-switch-link">Already have an account? <a href="<?php echo SITE_URL; ?>/login.php">Login here</a>.</p>
    </form>
    <?php endif; // End hide form if successful ?>
</section>

<?php
// Include the footer
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
