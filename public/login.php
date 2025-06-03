<?php
// public/login.php

// Define a page-specific title
$page_title = "Login to BaladyMall";

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php'; // Path to config from current file

// Ensure config.php is loaded first
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // Fallback if the above path fails (e.g. if script is moved or symlinked differently)
    $alt_config_path = dirname(__DIR__) . '/src/config/config.php';
    if (file_exists($alt_config_path)) {
        require_once $alt_config_path;
    } else {
        die("Critical error: Main configuration file not found. Please check paths.");
    }
}

// Now that config.php is loaded, SITE_URL and other constants like PROJECT_ROOT_PATH should be available.
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
    $redirect_url_if_already_loggedin = rtrim(SITE_URL, '/') . "/my_account.php";
    // This existing logic for redirecting to admin/brand_admin panels is now modified.
    // Super/Brand Admins will go to public index by default after login.
    header("Location: " . $redirect_url_if_already_loggedin);
    exit;
}

// Ensure $db is available from config.php
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        // This is a critical failure if $db is not available.
        // The error message will be displayed before the form.
        $errors['form'] = "Database connection is not available. Please try again later.";
    }
}


$errors = []; // Initialize errors array
$login_identifier = ''; // Initialize for repopulating form

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $login_identifier = trim(filter_input(INPUT_POST, 'login_identifier', FILTER_UNSAFE_RAW));
    $password = $_POST['password']; // Password will be verified, not directly used in SQL

    if (empty($login_identifier)) {
        $errors['login_identifier'] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    if (empty($errors) && isset($db) && $db instanceof PDO) { // Proceed only if no initial errors and DB is available
        try {
            $is_email = filter_var($login_identifier, FILTER_VALIDATE_EMAIL);

            $sql = "SELECT user_id, username, email, first_name, last_name, phone_number, password, role, is_active FROM users WHERE ";
            if ($is_email) {
                $sql .= "email = :identifier";
            } else {
                $sql .= "username = :identifier";
            }
            $sql .= " LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':identifier', $login_identifier);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ((int)$user['is_active'] === 0) {
                    $errors['form'] = "Your account is inactive. Please contact support.";
                } elseif (password_verify($password, $user['password'])) {
                    session_regenerate_id(true); // Regenerate session ID on successful login

                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['phone_number'] = $user['phone_number'];

                    // Determine redirect target. All roles go to public index after login.
                    $redirect_target = rtrim(SITE_URL, '/') . "/index.php?login=success";

                    // Handle redirect after login if a destination was stored (e.g., from checkout)
                    if(isset($_SESSION['redirect_after_login'])) {
                        $redirect_target = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']); // Clear it after use
                    }

                    header("Location: " . $redirect_target);
                    exit;

                } else {
                    $errors['form'] = "Invalid username/email or password.";
                }
            } else {
                $errors['form'] = "Invalid username/email or password."; // User not found
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors['form'] = "An error occurred during login. Please try again later.";
        }
    }
}
?>

<section class="auth-form-section">
    <h2>Login to Your Account</h2>
    <p>Welcome back to BaladyMall!</p>

    <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
        <div class="form-message success-message">
            Registration successful! You can now login.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'):
        echo "<div class='form-message success-message'>You have been successfully logged out.</div>";
    endif; ?>
     <?php if (isset($_GET['verified']) && $_GET['verified'] === 'success'): ?>
        <div class="form-message success-message">
            Email verified successfully! You can now login.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['verified']) && $_GET['verified'] === 'failed'): ?>
        <div class="form-message error-message">
            Email verification failed. Invalid or expired token.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['auth']) && $_GET['auth'] === 'required'): ?>
        <div class="form-message error-message">
            You need to be logged in to access that page. Please login to continue.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['password_reset']) && $_GET['password_reset'] === 'success'): ?>
        <div class="form-message success-message">
            Your password has been reset successfully. You can now login with your new password.
        </div>
    <?php endif; ?>


    <?php if (!empty($errors['form'])): ?>
        <div class="form-message error-message">
            <?php echo esc_html($errors['form']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="auth-form" novalidate>
        <fieldset>
            <legend>Login Credentials</legend>
            <div class="form-group">
                <label for="login_identifier">Username or Email Address <span class="required">*</span></label>
                <input type="text" id="login_identifier" name="login_identifier" value="<?php echo esc_html($login_identifier); ?>" required aria-describedby="loginIdentifierError">
                <?php if (isset($errors['login_identifier'])): ?><span id="loginIdentifierError" class="error-text"><?php echo esc_html($errors['login_identifier']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required aria-describedby="passwordError">
                <?php if (isset($errors['password'])): ?><span id="passwordError" class="error-text"><?php echo esc_html($errors['password']); ?></span><?php endif; ?>
            </div>
        </fieldset>

        <div class="form-group">
            <button type="submit" name="login" class="btn btn-primary btn-block btn-lg">Login</button>
        </div>

        <p class="form-switch-link"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/forgot_password.php">Forgot your password?</a></p>
        <p class="form-switch-link">Don't have an account? <a href="<?php echo rtrim(SITE_URL, '/'); ?>/register.php">Register here</a>.</p>
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
