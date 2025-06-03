<?php
// public/login.php

// Define a page-specific title
$page_title = "Login to BaladyMall";

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php'; // This will start the session
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// The header.php include will handle session_start()
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    $redirect_url_if_already_loggedin = SITE_URL . "/my_account.php"; 
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'brand_admin') {
            $redirect_url_if_already_loggedin = rtrim(SITE_URL, '/') . "/../brand_admin/index.php"; 
        } elseif ($_SESSION['role'] === 'super_admin') {
            $redirect_url_if_already_loggedin = rtrim(SITE_URL, '/') . "/../admin/index.php"; 
        }
    }
    header("Location: " . $redirect_url_if_already_loggedin); 
    exit;
}

$db = getPDOConnection(); 
$errors = [];
$login_identifier = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $login_identifier = trim(filter_input(INPUT_POST, 'login_identifier', FILTER_UNSAFE_RAW)); 
    $password = $_POST['password'];

    if (empty($login_identifier)) {
        $errors['login_identifier'] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    if (empty($errors) && $db) {
        try {
            $is_email = filter_var($login_identifier, FILTER_VALIDATE_EMAIL);
            
            // MODIFIED SQL: Added phone_number to the SELECT statement
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
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name']; 
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['phone_number'] = $user['phone_number']; // ADDED: Store phone_number in session
                    
                    $redirect_target = rtrim(SITE_URL, '/') . "/index.php?login=success"; 
                    
                    if ($user['role'] === 'brand_admin') {
                        $redirect_target = rtrim(SITE_URL, '/') . "/../brand_admin/index.php?login=success"; 
                    } elseif ($user['role'] === 'super_admin') {
                        $redirect_target = rtrim(SITE_URL, '/') . "/../admin/index.php?login=success"; 
                    }
                    
                    header("Location: " . $redirect_target);
                    exit;

                } else {
                    $errors['form'] = "Invalid username/email or password.";
                }
            } else {
                $errors['form'] = "Invalid username/email or password. User not found.";
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
    <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] === 'success'): ?>
        <div class="form-message success-message">
            You have been successfully logged out.
        </div>
    <?php endif; ?>
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
            You need to be logged in to access that page.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['form'])): ?>
        <div class="form-message error-message">
            <?php echo htmlspecialchars($errors['form']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="auth-form" novalidate>
        <fieldset>
            <legend>Login Credentials</legend>
            <div class="form-group">
                <label for="login_identifier">Username or Email Address <span class="required">*</span></label>
                <input type="text" id="login_identifier" name="login_identifier" value="<?php echo htmlspecialchars($login_identifier); ?>" required aria-describedby="loginIdentifierError">
                <?php if (isset($errors['login_identifier'])): ?><span id="loginIdentifierError" class="error-text"><?php echo htmlspecialchars($errors['login_identifier']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required aria-describedby="passwordError">
                <?php if (isset($errors['password'])): ?><span id="passwordError" class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
            </div>
        </fieldset>

        <div class="form-group">
            <button type="submit" name="login" class="btn btn-primary btn-block btn-lg">Login</button>
        </div>
        
        <p class="form-switch-link"><a href="<?php echo defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; ?>/forgot_password.php">Forgot your password?</a></p>
        <p class="form-switch-link">Don't have an account? <a href="<?php echo defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; ?>/register.php">Register here</a>.</p>
    </form>
</section>

<?php
// Include the footer
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
