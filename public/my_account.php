<?php
// public/my_account.php

// Define a page-specific title
$page_title = "My Account";

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// Check if user is logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/login.php?auth=required");
    exit;
}

// User is logged in, get their details from session (or database if needed for more info)
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$role = $_SESSION['role']; // 'customer', 'brand_admin', 'super_admin'

$db = getPDOConnection(); // In case we need to fetch more user data

// Include header
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}
?>

<section class="account-section">
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>This is your BaladyMall account dashboard. From here you can manage your orders, profile, and more.</p>

    <div class="account-details">
        <h3>Account Information</h3>
        <ul>
            <li><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></li>
            <li><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></li>
            <li><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($role)); ?></li> 
        </ul>
    </div>

    <div class="account-actions">
        <h3>Account Management</h3>
        <ul>
            <?php if ($role === 'customer'): ?>
                <li><a href="<?php echo SITE_URL; ?>/order_history.php" class="btn btn-secondary">View Order History</a></li>
                <li><a href="<?php echo SITE_URL; ?>/edit_profile.php" class="btn btn-secondary">Edit Profile & Address</a></li>
                <li><a href="<?php echo SITE_URL; ?>/change_password.php" class="btn btn-secondary">Change Password</a></li>
            <?php elseif ($role === 'brand_admin'): ?>
                <li><a href="<?php echo SITE_URL; ?>/../brand_admin/index.php" class="btn btn-primary">Go to Brand Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/edit_profile.php" class="btn btn-secondary">Edit Your Profile</a></li>
            <?php elseif ($role === 'super_admin'): ?>
                 <li><a href="<?php echo SITE_URL; ?>/../admin/index.php" class="btn btn-primary">Go to Super Admin Dashboard</a></li>
                 <li><a href="<?php echo SITE_URL; ?>/edit_profile.php" class="btn btn-secondary">Edit Your Profile</a></li>
            <?php endif; ?>
            <li><a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-danger">Logout</a></li>
        </ul>
    </div>

    <?php
    // Example: Display a message if redirected from login for a fresh login
    if (isset($_GET['login']) && $_GET['login'] === 'success') {
        echo "<p class='form-message success-message' style='margin-top: 20px;'>Successfully logged in!</p>";
    }
    ?>
</section>

<?php
// Include the footer
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
