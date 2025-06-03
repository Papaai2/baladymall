<?php
// public/my_account.php

$page_title = "My Account Dashboard"; // Default title

// Configuration and Header
$config_path_from_public = __DIR__ . '/../src/config/config.php'; // Path to config from current file

// Ensure config.php is loaded first
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
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

// Header includes session_start()
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
}

// Authentication Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = rtrim(SITE_URL, '/') . "/my_account.php"; // Store current page
    header("Location: " . rtrim(SITE_URL, '/') . "/login.php?auth=required&target=my_account");
    exit;
}

// Ensure $db is available
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        // Set an error message that can be displayed within the page structure
        $page_level_error = "Database connection is not available. Some account features may not work.";
    }
}

$user_id = (int)$_SESSION['user_id'];
$current_username = $_SESSION['username'] ?? 'N/A';
$current_first_name = $_SESSION['first_name'] ?? '';
$current_last_name = $_SESSION['last_name'] ?? '';
$current_email = $_SESSION['email'] ?? 'N/A';
$current_phone_number = $_SESSION['phone_number'] ?? '';
$current_role = $_SESSION['role'] ?? 'customer';

$full_name = trim($current_first_name . ' ' . $current_last_name);
if (empty($full_name)) {
    $full_name = esc_html($current_username);
} else {
    $full_name = esc_html($full_name);
}

// Initialize error and success message arrays for different forms
$profile_errors = [];
$profile_success_message = '';
$password_errors = [];
$password_success_message = '';
$address_errors = [];
$address_success_message = '';
$orders_list = [];

// Determine current view
$view = $_GET['view'] ?? 'dashboard';

// Set page title based on view
switch ($view) {
    case 'orders': $page_title = "Order History - My Account"; break;
    case 'profile': $page_title = "My Details - My Account"; break;
    case 'addresses': $page_title = "Shipping Addresses - My Account"; break;
    case 'change_password': $page_title = "Change Password - My Account"; break;
    default: $page_title = "Account Dashboard - My Account"; break;
}


// --- Fetch data based on view ---
if (isset($db) && $db instanceof PDO) { // Proceed only if DB connection is available
    if ($view === 'addresses' || ($_SERVER["REQUEST_METHOD"] !== "POST" && $view === 'addresses')) { // Fetch current address for display or pre-fill
        try {
            $stmt_fetch_address = $db->prepare("SELECT shipping_address_line1, shipping_address_line2, shipping_city, shipping_governorate, shipping_postal_code, shipping_country FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt_fetch_address->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_fetch_address->execute();
            $user_address_data = $stmt_fetch_address->fetch(PDO::FETCH_ASSOC);
            if ($user_address_data) {
                $current_shipping_address_line1 = $user_address_data['shipping_address_line1'] ?? '';
                $current_shipping_address_line2 = $user_address_data['shipping_address_line2'] ?? '';
                $current_shipping_city = $user_address_data['shipping_city'] ?? '';
                $current_shipping_governorate = $user_address_data['shipping_governorate'] ?? '';
                $current_shipping_postal_code = $user_address_data['shipping_postal_code'] ?? '';
                $current_shipping_country = $user_address_data['shipping_country'] ?? 'Egypt';
            }
        } catch (PDOException $e) {
            error_log("My Account - Error fetching shipping address: " . $e->getMessage());
            if ($view === 'addresses') $address_errors['form'] = "Could not load your current address. Please try again.";
        }
    }

    if ($view === 'orders') {
        try {
            $stmt_fetch_orders = $db->prepare("SELECT order_id, order_date, total_amount, order_status FROM orders WHERE customer_id = :customer_id ORDER BY order_date DESC");
            $stmt_fetch_orders->bindParam(':customer_id', $user_id, PDO::PARAM_INT);
            $stmt_fetch_orders->execute();
            $orders_list = $stmt_fetch_orders->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("My Account - Error fetching orders: " . $e->getMessage());
            if ($view === 'orders') $page_level_error = "Could not load your order history at this time.";
        }
    }
}


// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($db) && $db instanceof PDO) {
    if (isset($_POST['update_profile'])) {
        $view = 'profile'; // Ensure view is set for displaying messages correctly
        $new_first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
        $new_last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
        $new_email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $new_phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW));

        if (empty($new_first_name)) $profile_errors['first_name'] = "First name is required.";
        if (empty($new_last_name)) $profile_errors['last_name'] = "Last name is required.";
        if (empty($new_email)) { $profile_errors['email'] = "Email is required."; }
        elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) { $profile_errors['email'] = "Invalid email format."; }
        if (empty($new_phone_number)) { $profile_errors['phone_number'] = "Phone number is required."; }
        elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $new_phone_number)) { $profile_errors['phone_number'] = "Invalid phone number format.";}

        if (empty($profile_errors)) {
            if ($new_email !== $current_email) {
                $stmt_check_email = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id LIMIT 1");
                $stmt_check_email->execute([':email' => $new_email, ':user_id' => $user_id]);
                if ($stmt_check_email->fetch()) $profile_errors['email'] = "This email address is already in use by another account.";
            }
            if ($new_phone_number !== $current_phone_number && !empty($new_phone_number) ) { 
                 $stmt_check_phone = $db->prepare("SELECT user_id FROM users WHERE phone_number = :phone_number AND user_id != :user_id LIMIT 1");
                $stmt_check_phone->execute([':phone_number' => $new_phone_number, ':user_id' => $user_id]);
                if ($stmt_check_phone->fetch()) $profile_errors['phone_number'] = "This phone number is already in use by another account.";
            }
        }
        
        if (empty($profile_errors)) {
            try {
                $update_sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone_number, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id";
                $stmt_update = $db->prepare($update_sql);
                $stmt_update->execute([':first_name' => $new_first_name, ':last_name' => $new_last_name, ':email' => $new_email, ':phone_number' => $new_phone_number, ':user_id' => $user_id]);
                
                $_SESSION['first_name'] = $new_first_name; $_SESSION['last_name'] = $new_last_name; $_SESSION['email'] = $new_email; $_SESSION['phone_number'] = $new_phone_number;
                
                $current_first_name = $new_first_name; $current_last_name = $new_last_name; $current_email = $new_email; $current_phone_number = $new_phone_number;
                $full_name = esc_html(trim($current_first_name . ' ' . $current_last_name));
                if (empty(trim($full_name))) { $full_name = esc_html($current_username); }
                $profile_success_message = "Your profile has been updated successfully!";
            } catch (PDOException $e) {
                error_log("Profile Update Error (User ID: {$user_id}): " . $e->getMessage());
                $profile_errors['form'] = ($e->getCode() == '23000') ? "The email or phone number you entered is already in use." : "An error occurred while updating your profile.";
            }
        }
    } elseif (isset($_POST['change_password_submit'])) {
        $view = 'change_password';
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password_input = $_POST['new_password'] ?? '';
        $confirm_password_input = $_POST['confirm_password'] ?? '';

        if (empty($current_password_input)) $password_errors['current_password'] = "Current password is required.";
        if (empty($new_password_input)) { $password_errors['new_password'] = "New password is required.";}
        elseif (strlen($new_password_input) < 6) { $password_errors['new_password'] = "New password must be at least 6 characters long.";}
        if ($new_password_input !== $confirm_password_input) { $password_errors['confirm_password'] = "New passwords do not match.";}

        if (empty($password_errors)) {
            try { 
                $stmt_fetch_pass = $db->prepare("SELECT password FROM users WHERE user_id = :user_id LIMIT 1");
                $stmt_fetch_pass->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_fetch_pass->execute();
                $user_data = $stmt_fetch_pass->fetch();

                if ($user_data && password_verify($current_password_input, $user_data['password'])) {
                    $hashed_new_password = password_hash($new_password_input, PASSWORD_DEFAULT);
                    $stmt_update_pass = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id");
                    $stmt_update_pass->execute([':password' => $hashed_new_password, ':user_id' => $user_id]);
                    $password_success_message = "Your password has been changed successfully.";
                } else {
                    $password_errors['current_password'] = "Incorrect current password.";
                }
            } catch (PDOException $e) {
                 error_log("Change Password Error (User ID: {$user_id}): " . $e->getMessage());
                 $password_errors['form'] = "An error occurred while changing your password.";
            }
        }
    } elseif (isset($_POST['update_shipping_address'])) {
        $view = 'addresses';
        $new_shipping_address_line1 = trim(filter_input(INPUT_POST, 'shipping_address_line1', FILTER_UNSAFE_RAW));
        $new_shipping_address_line2 = trim(filter_input(INPUT_POST, 'shipping_address_line2', FILTER_UNSAFE_RAW));
        $new_shipping_city = trim(filter_input(INPUT_POST, 'shipping_city', FILTER_UNSAFE_RAW));
        $new_shipping_governorate = trim(filter_input(INPUT_POST, 'shipping_governorate', FILTER_UNSAFE_RAW));
        $new_shipping_postal_code = trim(filter_input(INPUT_POST, 'shipping_postal_code', FILTER_UNSAFE_RAW));
        $new_shipping_country = trim(filter_input(INPUT_POST, 'shipping_country', FILTER_UNSAFE_RAW)) ?: 'Egypt';

        if (empty($new_shipping_address_line1)) $address_errors['shipping_address_line1'] = "Address Line 1 is required.";
        if (empty($new_shipping_city)) $address_errors['shipping_city'] = "City is required.";
        if (empty($new_shipping_governorate)) $address_errors['shipping_governorate'] = "Governorate is required.";
        if (empty($new_shipping_country)) $address_errors['shipping_country'] = "Country is required.";

        if (empty($address_errors)) {
            try {
                $update_address_sql = "UPDATE users SET 
                                        shipping_address_line1 = :addr1, 
                                        shipping_address_line2 = :addr2, 
                                        shipping_city = :city, 
                                        shipping_governorate = :gov, 
                                        shipping_postal_code = :zip, 
                                        shipping_country = :country,
                                        updated_at = CURRENT_TIMESTAMP 
                                      WHERE user_id = :user_id";
                $stmt_update_address = $db->prepare($update_address_sql);
                $stmt_update_address->execute([
                    ':addr1' => $new_shipping_address_line1, ':addr2' => $new_shipping_address_line2,
                    ':city' => $new_shipping_city, ':gov' => $new_shipping_governorate,
                    ':zip' => $new_shipping_postal_code, ':country' => $new_shipping_country,
                    ':user_id' => $user_id
                ]);

                // No need to check rowCount strictly for UPDATE success, execute() throws exception on failure with ATTR_ERRMODE_EXCEPTION
                $address_success_message = "Your shipping address has been updated successfully!";
                // Update current variables for immediate display
                $current_shipping_address_line1 = $new_shipping_address_line1;
                $current_shipping_address_line2 = $new_shipping_address_line2;
                $current_shipping_city = $new_shipping_city;
                $current_shipping_governorate = $new_shipping_governorate;
                $current_shipping_postal_code = $new_shipping_postal_code;
                $current_shipping_country = $new_shipping_country;
                
            } catch (PDOException $e) {
                error_log("Shipping Address Update Error (User ID: {$user_id}): " . $e->getMessage());
                $address_errors['form'] = "An error occurred while updating your shipping address.";
            }
        }
    }
}
?>

<div class="account-page-container">
    <aside class="account-sidebar">
        <div class="sidebar-header"><h4>Account Navigation</h4></div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item <?php echo ($view == 'dashboard') ? 'active' : ''; ?>"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=dashboard" class="nav-link">Dashboard</a></li>
                <li class="nav-item <?php echo ($view == 'orders') ? 'active' : ''; ?>"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=orders" class="nav-link">Order History</a></li>
                <li class="nav-item <?php echo ($view == 'profile') ? 'active' : ''; ?>"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=profile" class="nav-link">My Details</a></li>
                <li class="nav-item <?php echo ($view == 'addresses') ? 'active' : ''; ?>"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=addresses" class="nav-link">Shipping Addresses</a></li>
                <li class="nav-item <?php echo ($view == 'change_password') ? 'active' : ''; ?>"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=change_password" class="nav-link">Change Password</a></li>
                
                <?php if ($current_role === 'brand_admin'): ?>
                    <li class="nav-item separator"><hr></li>
                    <li class="nav-item"><a href="<?php echo (defined('BRAND_ADMIN_URL') ? BRAND_ADMIN_URL : rtrim(SITE_URL, '/') . "/../brand_admin/index.php"); ?>" class="nav-link btn btn-info btn-sm" style="color:white;">Brand Dashboard</a></li>
                <?php elseif ($current_role === 'super_admin'): ?>
                    <li class="nav-item separator"><hr></li>
                    <li class="nav-item"><a href="<?php echo (defined('SUPER_ADMIN_URL') ? SUPER_ADMIN_URL : rtrim(SITE_URL, '/') . "/../admin/index.php"); ?>" class="nav-link btn btn-warning btn-sm" style="color:#212529;">Super Admin Panel</a></li>
                <?php endif; ?>
                
                <li class="nav-item separator"><hr></li>
                <li class="nav-item"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/logout.php" class="nav-link logout-link"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16" style="margin-right: 8px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/></svg>Logout</a></li>
            </ul>
        </nav>
    </aside>

    <section class="account-content">
        <div class="content-header"><h1><?php echo $full_name; ?></h1>
            <?php 
            if (isset($page_level_error)) { // Display critical page-level errors
                echo "<p class='form-message error-message' style='margin-top: 15px;'>" . esc_html($page_level_error) . "</p>";
            }
            // Display login success message only on dashboard view and if redirected from login
            if ($view === 'dashboard' && isset($_GET['login']) && $_GET['login'] === 'success') {
                if (!isset($_SESSION['myaccount_login_msg_shown'])) { // Prevent re-display on refresh
                    echo "<p class='form-message success-message' style='margin-top: 15px;'>You have successfully logged in.</p>";
                    $_SESSION['myaccount_login_msg_shown'] = true;
                }
            } elseif ($view !== 'dashboard' && isset($_SESSION['myaccount_login_msg_shown'])) {
                unset($_SESSION['myaccount_login_msg_shown']); // Clear flag if navigating away from dashboard
            }
            ?>
        </div>
        <div class="content-body">
            <?php
            // Display form-specific messages
            if ($view === 'profile') {
                if (!empty($profile_success_message)) echo "<div class='form-message success-message'>" . esc_html($profile_success_message) . "</div>";
                if (!empty($profile_errors['form'])) echo "<div class='form-message error-message'>" . esc_html($profile_errors['form']) . "</div>";
            } elseif ($view === 'change_password') {
                if (!empty($password_success_message)) echo "<div class='form-message success-message'>" . esc_html($password_success_message) . "</div>";
                if (!empty($password_errors['form'])) echo "<div class='form-message error-message'>" . esc_html($password_errors['form']) . "</div>";
            } elseif ($view === 'addresses') {
                if (!empty($address_success_message)) echo "<div class='form-message success-message'>" . esc_html($address_success_message) . "</div>";
                if (!empty($address_errors['form'])) echo "<div class='form-message error-message'>" . esc_html($address_errors['form']) . "</div>";
            }

            // Switch to display content based on view
            switch ($view) {
                case 'orders':
                    echo "<h3>Your Order History</h3>";
                    if (isset($db) && $db instanceof PDO) { // Check DB connection again before displaying orders
                        if (!empty($orders_list)) {
                            echo "<div class='table-responsive'>"; // For better mobile view of table
                            echo "<table class='orders-table'>";
                            echo "<thead><tr><th>Order ID</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($orders_list as $order) {
                                echo "<tr>";
                                echo "<td>#" . esc_html($order['order_id']) . "</td>";
                                echo "<td>" . esc_html(date("F j, Y, g:i a", strtotime($order['order_date']))) . "</td>";
                                echo "<td>" . CURRENCY_SYMBOL . esc_html(number_format($order['total_amount'], 2)) . "</td>";
                                echo "<td>" . esc_html(ucfirst(str_replace('_', ' ', $order['order_status']))) . "</td>";
                                echo "<td><a href='" . rtrim(SITE_URL, '/') . "/order_detail.php?order_id=" . esc_html($order['order_id']) . "' class='btn btn-sm btn-secondary'>View Details</a></td>";
                                echo "</tr>";
                            }
                            echo "</tbody>";
                            echo "</table>";
                            echo "</div>"; // End table-responsive
                        } else {
                            echo "<p>You have not placed any orders yet.</p>";
                        }
                    } else {
                        echo "<p class='form-message error-message'>Could not display order history due to a database connection issue.</p>";
                    }
                    break;

                case 'profile':
                    echo "<h3>Edit Your Personal Details</h3>";
                    echo "<p>Keep your information up to date.</p>";
                    echo "<form action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "?view=profile' method='POST' class='profile-edit-form auth-form' novalidate>";
                    echo "<fieldset>";
                    echo "<div class='form-group'>";
                    echo "<label for='first_name'>First Name <span class='required'>*</span></label>";
                    echo "<input type='text' id='first_name' name='first_name' value='" . esc_html($current_first_name) . "' required>";
                    if (isset($profile_errors['first_name'])) echo "<span class='error-text'>" . esc_html($profile_errors['first_name']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='last_name'>Last Name <span class='required'>*</span></label>";
                    echo "<input type='text' id='last_name' name='last_name' value='" . esc_html($current_last_name) . "' required>";
                    if (isset($profile_errors['last_name'])) echo "<span class='error-text'>" . esc_html($profile_errors['last_name']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='email'>Email Address <span class='required'>*</span></label>";
                    echo "<input type='email' id='email' name='email' value='" . esc_html($current_email) . "' required>";
                    if (isset($profile_errors['email'])) echo "<span class='error-text'>" . esc_html($profile_errors['email']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='phone_number'>Phone Number <span class='required'>*</span></label>";
                    echo "<input type='tel' id='phone_number' name='phone_number' value='" . esc_html($current_phone_number) . "' required placeholder='+201XXXXXXXXX'>";
                    if (isset($profile_errors['phone_number'])) echo "<span class='error-text'>" . esc_html($profile_errors['phone_number']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='username_display'>Username (cannot be changed)</label>";
                    echo "<input type='text' id='username_display' name='username_display' value='" . esc_html($current_username) . "' readonly disabled class='form-control'>"; // Added form-control for consistent styling
                    echo "<small class='form-text text-muted'>Usernames cannot be changed after registration.</small>";
                    echo "</div>";
                    echo "</fieldset>";
                    echo "<div class='form-group mt-4'>";
                    echo "<button type='submit' name='update_profile' class='btn btn-primary btn-lg'>Save Changes</button>";
                    echo "</div>";
                    echo "</form>";
                    break;

                case 'addresses':
                    echo "<h3>Your Shipping Address</h3>";
                    echo "<p>Update your primary shipping address below.</p>";
                    echo "<form action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "?view=addresses' method='POST' class='shipping-address-form auth-form' novalidate>";
                    echo "<fieldset>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_address_line1'>Address Line 1 <span class='required'>*</span></label>";
                    echo "<input type='text' id='shipping_address_line1' name='shipping_address_line1' value='" . esc_html($current_shipping_address_line1 ?? '') . "' required>";
                    if (isset($address_errors['shipping_address_line1'])) echo "<span class='error-text'>" . esc_html($address_errors['shipping_address_line1']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_address_line2'>Address Line 2 (Optional)</label>";
                    echo "<input type='text' id='shipping_address_line2' name='shipping_address_line2' value='" . esc_html($current_shipping_address_line2 ?? '') . "'>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_city'>City <span class='required'>*</span></label>";
                    echo "<input type='text' id='shipping_city' name='shipping_city' value='" . esc_html($current_shipping_city ?? '') . "' required>";
                    if (isset($address_errors['shipping_city'])) echo "<span class='error-text'>" . esc_html($address_errors['shipping_city']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_governorate'>Governorate <span class='required'>*</span></label>";
                    echo "<input type='text' id='shipping_governorate' name='shipping_governorate' value='" . esc_html($current_shipping_governorate ?? '') . "' required>";
                    if (isset($address_errors['shipping_governorate'])) echo "<span class='error-text'>" . esc_html($address_errors['shipping_governorate']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_postal_code'>Postal Code (Optional)</label>";
                    echo "<input type='text' id='shipping_postal_code' name='shipping_postal_code' value='" . esc_html($current_shipping_postal_code ?? '') . "'>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='shipping_country'>Country <span class='required'>*</span></label>";
                    echo "<input type='text' id='shipping_country' name='shipping_country' value='" . esc_html($current_shipping_country ?? 'Egypt') . "' required>";
                    if (isset($address_errors['shipping_country'])) echo "<span class='error-text'>" . esc_html($address_errors['shipping_country']) . "</span>";
                    echo "</div>";
                    echo "</fieldset>";
                    echo "<div class='form-group mt-4'>";
                    echo "<button type='submit' name='update_shipping_address' class='btn btn-primary btn-lg'>Save Address</button>";
                    echo "</div>";
                    echo "</form>";
                    break;

                case 'change_password':
                    echo "<h3>Change Your Password</h3>";
                    echo "<p>Choose a strong new password and don't reuse it for other accounts.</p>";
                    echo "<form action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "?view=change_password' method='POST' class='change-password-form auth-form' novalidate>";
                    echo "<fieldset>";
                    echo "<div class='form-group'>";
                    echo "<label for='current_password'>Current Password <span class='required'>*</span></label>";
                    echo "<input type='password' id='current_password' name='current_password' required>";
                    if (isset($password_errors['current_password'])) echo "<span class='error-text'>" . esc_html($password_errors['current_password']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='new_password'>New Password <span class='required'>*</span> (Min. 6 characters)</label>";
                    echo "<input type='password' id='new_password' name='new_password' required>";
                    if (isset($password_errors['new_password'])) echo "<span class='error-text'>" . esc_html($password_errors['new_password']) . "</span>";
                    echo "</div>";
                    echo "<div class='form-group'>";
                    echo "<label for='confirm_password'>Confirm New Password <span class='required'>*</span></label>";
                    echo "<input type='password' id='confirm_password' name='confirm_password' required>";
                    if (isset($password_errors['confirm_password'])) echo "<span class='error-text'>" . esc_html($password_errors['confirm_password']) . "</span>";
                    echo "</div>";
                    echo "</fieldset>";
                    echo "<div class='form-group mt-4'>";
                    echo "<button type='submit' name='change_password_submit' class='btn btn-primary btn-lg'>Update Password</button>";
                    echo "</div>";
                    echo "</form>";
                    break;
                    
                case 'dashboard':
                default: // Fallback to dashboard
                    echo "<h3>Account Overview</h3>";
                    echo "<p>From your account dashboard, you can view your recent activity, manage your orders, and update your account details.</p>";
                    echo "<div class='dashboard-widgets'>";
                    echo "<div class='widget'><a href='" . rtrim(SITE_URL, '/') . "/my_account.php?view=orders'><h4>Recent Orders</h4><p>View your latest orders</p></a></div>";
                    echo "<div class='widget'><a href='" . rtrim(SITE_URL, '/') . "/my_account.php?view=profile'><h4>Account Details</h4><p>Update your information</p></a></div>";
                    echo "<div class='widget'><a href='" . rtrim(SITE_URL, '/') . "/my_account.php?view=addresses'><h4>Shipping Addresses</h4><p>Manage addresses</p></a></div>";
                    echo "<div class='widget'><a href='" . rtrim(SITE_URL, '/') . "/my_account.php?view=change_password'><h4>Security</h4><p>Change your password</p></a></div>";
                    echo "</div>";
                    break;
            }
            ?>
        </div>
    </section>
</div>

<?php
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>
