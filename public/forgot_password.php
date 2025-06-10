<?php
// public/forgot_password.php - Handles "Forgot Password" request

$page_title = "Forgot Your Password?";

// Configuration and Header paths
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/header.php' : __DIR__ . '/../src/includes/header.php';
$footer_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/footer.php' : __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Please check paths.");
}

if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . get_asset_url("my_account.php")); // Use get_asset_url
    exit;
}

$db = getPDOConnection();
$errors = [];
$message = '';
$email_form = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reset_link'])) {
    $email_form = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

    if (empty($email_form)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email_form, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (empty($errors) && $db) {
        try {
            // 1. Check if email exists in the database
            $stmt_user = $db->prepare("SELECT user_id, username, email FROM users WHERE email = :email LIMIT 1");
            $stmt_user->bindParam(':email', $email_form);
            $stmt_user->execute();
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 2. Generate a unique token
                $token = bin2hex(random_bytes(32)); // 64-character hex string
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

                // 3. Store the token in a password_resets table
                // First, delete any old tokens for this user to keep it clean
                $stmt_delete_old_tokens = $db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt_delete_old_tokens->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
                $stmt_delete_old_tokens->execute();

                $stmt_insert_token = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (:user_id, :token, :expires_at, NOW())");
                $stmt_insert_token->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
                $stmt_insert_token->bindParam(':token', $token);
                $stmt_insert_token->bindParam(':expires_at', $expires_at);
                $stmt_insert_token->execute();

                // 4. Construct the reset link
                $reset_link = get_asset_url("reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($user['email'])); // Use get_asset_url

                // 5. Send Email
                $email_subject = SITE_NAME . " - Password Reset Request";
                $email_body_html = "
                <div style='font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;'>
                    <h2 style='color: " . (defined('SITE_NAME') ? '#FF6B00' : '#007bff') . ";'>Password Reset Request for " . esc_html(SITE_NAME) . "</h2>
                    <p>Hello <strong>" . esc_html($user['username']) . "</strong>,</p>
                    <p>You have requested to reset your password for your " . esc_html(SITE_NAME) . " account.</p>
                    <p>Please click on the following link to reset your password:</p>
                    <p style='margin-top: 20px;'>
                        <a href='" . esc_html($reset_link) . "' style='display: inline-block; padding: 10px 20px; background-color: " . (defined('SITE_NAME') ? '#FF6B00' : '#007bff') . "; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            Reset Your Password
                        </a>
                    </p>
                    <p style='margin-top: 20px;'>This link is valid for 1 hour. If you did not request a password reset, please ignore this email.</p>
                    <p>Regards,<br>The " . esc_html(SITE_NAME) . " Team</p>
                </div>
                ";
                $email_body_plain = "Hello " . $user['username'] . ",\n\n";
                $email_body_plain .= "You have requested to reset your password for your " . SITE_NAME . " account.\n\n";
                $email_body_plain .= "Please click on the following link to reset your password:\n";
                $email_body_plain .= $reset_link . "\n\n";
                $email_body_plain .= "This link is valid for 1 hour. If you did not request a password reset, please ignore this email.\n\n";
                $email_body_plain .= "Regards,\nThe " . SITE_NAME . " Team";

                $email_sent = send_email($user['email'], $email_subject, $email_body_html, $email_body_plain, true);

                if ($email_sent) {
                    $message = "<div class='form-message success-message'>If an account with that email exists, a password reset link has been sent to your email address. Please check your inbox (and spam folder).</div>";
                    $email_form = ''; // Clear form on success
                } else {
                    $message = "<div class='form-message error-message'>Failed to send password reset email. Please try again later.</div>";
                }

            } else {
                // For security, always give a generic success message even if email doesn't exist.
                $message = "<div class='form-message success-message'>If an account with that email exists, a password reset link has been sent to your email address. Please check your inbox (and spam folder).</div>";
            }
        } catch (PDOException $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $message = "<div class='form-message error-message'>An error occurred. Please try again later.</div>";
        } catch (Exception $e) { // Catch any other general exceptions
            error_log("Forgot Password Token Generation Error: " . $e->getMessage());
            $message = "<div class='form-message error-message'>An internal error occurred. Please try again.</div>";
        }
    } else {
        if (empty($errors)) { // If no specific email errors, but DB connection failed
            $message = "<div class='form-message error-message'>Database connection is not available. Cannot process your request.</div>";
        } else {
            $message = "<div class='form-message error-message'>Please correct the errors below.</div>";
        }
    }
}

?>

<section class="auth-form-section">
    <h2>Forgot Your Password?</h2>
    <p>Enter your email address below and we'll send you a link to reset your password.</p>

    <?php if (!empty($message)) echo $message; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="auth-form" novalidate>
        
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo esc_html($email_form); ?>" required aria-describedby="emailError">
                <?php if (isset($errors['email'])): ?><span id="emailError" class="error-text"><?php echo esc_html($errors['email']); ?></span><?php endif; ?>
            </div>
        

        <div class="form-group">
            <button type="submit" name="send_reset_link" class="btn btn-primary btn-block btn-lg">Send Reset Link</button>
        </div>

        <p class="form-switch-link">Remember your password? <a href="<?php echo get_asset_url('login.php'); ?>">Login here</a>.</p>
    </form>
</section>

<?php
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>