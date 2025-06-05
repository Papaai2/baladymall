<?php
// public/reset_password.php - Handles password reset via token

$page_title = "Reset Your Password";

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
    header("Location: " . get_asset_url("my_account.php"));
    exit;
}

$db = getPDOConnection();
$errors = [];
$message = '';
$token_valid = false;
$user_id_from_token = null;
$user_email_from_token = '';
$user_username_from_token = ''; // Added to fetch username for email

// Get token and email from URL parameters
$token_param = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW);
$email_param = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);

if (empty($token_param) || empty($email_param)) {
    $message = "<div class='form-message error-message'>Invalid or missing password reset link.</div>";
} elseif (!$db) {
    $message = "<div class='form-message error-message'>Database connection is not available. Cannot process your request.</div>";
} else {
    try {
        // 1. Validate the token and email, fetch username
        $stmt_token = $db->prepare("
            SELECT pr.user_id, u.email, u.username, pr.expires_at
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.user_id
            WHERE pr.token = :token AND u.email = :email AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt_token->bindParam(':token', $token_param);
        $stmt_token->bindParam(':email', $email_param);
        $stmt_token->execute();
        $token_data = $stmt_token->fetch(PDO::FETCH_ASSOC);

        if ($token_data) {
            $token_valid = true;
            $user_id_from_token = $token_data['user_id'];
            $user_email_from_token = $token_data['email'];
            $user_username_from_token = $token_data['username'];
        } else {
            $message = "<div class='form-message error-message'>Invalid or expired password reset token. Please request a new one.</div>";
        }

    } catch (PDOException $e) {
        error_log("Reset Password Token Validation Error: " . $e->getMessage());
        $message = "<div class='form-message error-message'>An error occurred during token validation. Please try again later.</div>";
    }
}

// Handle new password submission
if ($token_valid && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password_submit'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($new_password)) {
        $errors['new_password'] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors['new_password'] = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors['confirm_new_password'] = "Passwords do not match.";
    }

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();

            // Update user's password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update_pass = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id");
            $stmt_update_pass->bindParam(':password', $hashed_password);
            $stmt_update_pass->bindParam(':user_id', $user_id_from_token, PDO::PARAM_INT);
            $stmt_update_pass->execute();

            // Invalidate the token by deleting it
            $stmt_delete_token = $db->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt_delete_token->bindParam(':token', $token_param);
            $stmt_delete_token->execute();

            $db->commit();

            // Send password reset success email
            $email_subject = SITE_NAME . " - Your Password Has Been Reset";
            $email_body = "Hello " . esc_html($user_username_from_token) . ",\n\n";
            $email_body .= "Your password for your " . SITE_NAME . " account has been successfully reset.\n\n";
            $email_body .= "If you did not perform this action, please contact support immediately.\n\n";
            $email_body .= "Regards,\n" . SITE_NAME . " Team";

            if (send_email($user_email_from_token, $email_subject, $email_body)) {
                $_SESSION['password_reset_success'] = true; // Use session to show success message on login page
                header("Location: " . get_asset_url("login.php?password_reset=success"));
                exit;
            } else {
                // If email sending fails, still redirect as password was reset successfully in DB
                error_log("Failed to send password reset success email to {$user_email_from_token} for user {$user_id_from_token}.");
                $_SESSION['password_reset_success'] = true;
                header("Location: " . get_asset_url("login.php?password_reset=success&email_fail=true"));
                exit;
            }

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Reset Password Error (User ID: {$user_id_from_token}): " . $e->getMessage());
            $message = "<div class='form-message error-message'>An error occurred while resetting your password. Please try again.</div>";
        }
    } else {
        if (empty($errors)) { // If no specific password errors, but DB connection failed
            $message = "<div class='form-message error-message'>Database connection is not available. Cannot reset password.</div>";
        } else {
            $message = "<div class='form-message error-message'>Please correct the errors below.</div>";
        }
    }
}

?>

<section class="auth-form-section">
    <h2><?php echo esc_html($page_title); ?></h2>

    <?php if (!empty($message)) echo $message; ?>

    <?php if ($token_valid): ?>
        <p>Enter your new password for <?php echo esc_html($user_email_from_token); ?>.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?token=" . urlencode($token_param) . "&email=" . urlencode($email_param); ?>" method="POST" class="auth-form" novalidate>
            <fieldset>
                <legend>New Password</legend>
                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span> (Min. 6 characters)</label>
                    <input type="password" id="new_password" name="new_password" required aria-describedby="newPasswordError">
                    <?php if (isset($errors['new_password'])): ?><span id="newPasswordError" class="error-text"><?php echo esc_html($errors['new_password']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required aria-describedby="confirmNewPasswordError">
                    <?php if (isset($errors['confirm_new_password'])): ?><span id="confirmNewPasswordError" class="error-text"><?php echo esc_html($errors['confirm_new_password']); ?></span><?php endif; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <button type="submit" name="reset_password_submit" class="btn btn-primary btn-block btn-lg">Reset Password</button>
            </div>
        </form>
    <?php else: ?>
        <p>If you need to reset your password, please <a href="<?php echo get_asset_url('forgot_password.php'); ?>">request a new reset link</a>.</p>
    <?php endif; ?>

</section>

<?php
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>