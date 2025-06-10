<?php
// public/order_success.php

$page_title = "Order Confirmation - BaladyMall";

// Configuration, Header, and Footer paths
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

// Ensure $db is available
$db_available = false;
if (isset($db) && $db instanceof PDO) {
    $db_available = true;
} elseif (function_exists('getPDOConnection')) {
    $db = getPDOConnection(); // Attempt to get connection if not already set
    if (isset($db) && $db instanceof PDO) {
        $db_available = true;
    }
}

$order_details = null;
$error_message = '';

// Check if user is logged in - they should be if they just placed an order
if (!isset($_SESSION['user_id'])) {
    // This case should ideally not happen if checkout requires login
    $_SESSION['redirect_after_login'] = get_asset_url("my_account.php?view=orders"); // Redirect to orders after login
    header("Location: " . get_asset_url("login.php?auth=required&target=order_confirmation"));
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// Retrieve the last order ID from the session
if (isset($_SESSION['last_order_id'])) {
    // REMOVED: The send_email call from here. Email is now sent from checkout.php.
    // This prevents duplicate emails if the user refreshes order_success.php.

    $last_order_id = (int)$_SESSION['last_order_id'];

    if ($db_available) {
        try {
            // Fetch basic order details to confirm it belongs to the current user and exists
            $stmt = $db->prepare("SELECT order_id, order_date, total_amount, shipping_name, payment_method
                                  FROM orders
                                  WHERE order_id = :order_id AND customer_id = :customer_id
                                  LIMIT 1");
            $stmt->bindParam(':order_id', $last_order_id, PDO::PARAM_INT);
            $stmt->bindParam(':customer_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order_details) {
                $error_message = "Could not retrieve your recent order details. Please check your account or contact support.";
            }

            // Clear the last_order_id from session to prevent re-display on refresh
            // or if user navigates back to this page without placing a new order.
            unset($_SESSION['last_order_id']);

        } catch (PDOException $e) {
            error_log("Order Success - Error fetching order details (ID: $last_order_id, User: $user_id): " . $e->getMessage());
            $error_message = "An error occurred while retrieving your order information. Please contact support if the issue persists.";
        }
    } else {
        $error_message = "Database connection error. Cannot retrieve order details.";
    }
} else {
    // If last_order_id is not in session, it means the user might have landed here directly
    // or refreshed after the session variable was cleared.
    $error_message = "No recent order information found. If you just placed an order, please check your account for details.";
}

?>

<section class="order-success-section">
    <div class="container text-center">

        <?php if (!empty($error_message) && !$order_details): ?>
            <div class="order-confirmation-box error-box">
                <?php
                // Use get_asset_url for image paths
                $error_icon_url = get_asset_url('images/order-error.svg');
                $error_icon_alt = "Order Error Icon";
                ?>
                <img src="<?php echo esc_html($error_icon_url); ?>" alt="<?php echo esc_html($error_icon_alt); ?>" class="confirmation-icon"
                     onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('afterbegin', '<p style=\'font-size: 2.5em; color: #dc3545; margin-bottom:15px;\'>&#10008;</p>');">
                <h2>Order Information Not Found</h2>
                <p><?php echo esc_html($error_message); ?></p>
                <div class="confirmation-actions">
                    <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo get_asset_url('my_account.php?view=orders'); ?>" class="btn btn-outline-secondary">View My Orders</a>
                </div>
            </div>
        <?php elseif ($order_details): ?>
            <div class="order-confirmation-box success-box">
                <?php
                $success_icon_url = get_asset_url('images/order-success.svg');
                $success_icon_alt = "Order Success Icon";
                ?>
                 <img src="<?php echo esc_html($success_icon_url); ?>" alt="<?php echo esc_html($success_icon_alt); ?>" class="confirmation-icon"
                      onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('afterbegin', '<p style=\'font-size: 2.5em; color: #28a745; margin-bottom:15px;\'>&#10004;</p>');">
                <h2>Thank You For Your Order, <?php echo esc_html($_SESSION['first_name'] ?? 'Valued Customer'); ?>!</h2>
                <p class="lead-text">Your order has been placed successfully.</p>

                <div class="order-summary-brief">
                    <p><strong>Order ID:</strong> #<?php echo esc_html($order_details['order_id']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo esc_html(date("F j, Y, g:i a", strtotime($order_details['order_date']))); ?></p>
                    <p><strong>Total Amount:</strong> <?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($order_details['total_amount'], 2)); ?></p>
                    <p><strong>Shipping To:</strong> <?php echo esc_html($order_details['shipping_name']); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $order_details['payment_method']))); ?></p>
                </div>

                <p class="mt-4">You will receive an email confirmation shortly with the full details of your order.</p>
                <p>If you have any questions, please don't hesitate to <a href="<?php echo get_asset_url('contact.php'); ?>">contact us</a>.</p>

                <div class="confirmation-actions">
                    <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo get_asset_url('order_detail.php?order_id=' . esc_html($order_details['order_id'])); ?>" class="btn btn-outline-secondary">View Order Details</a>
                </div>
            </div>
        <?php else: // Fallback if $error_message is empty but $order_details is also null (e.g. direct access after session cleared) ?>
             <div class="order-confirmation-box info-box">
                <?php
                $info_icon_url = get_asset_url('images/order-info.svg');
                $info_icon_alt = "Order Information Icon";
                ?>
                <img src="<?php echo esc_html($info_icon_url); ?>" alt="<?php echo esc_html($info_icon_alt); ?>" class="confirmation-icon"
                     onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('afterbegin', '<p style=\'font-size: 2.5em; color: #17a2b8; margin-bottom:15px;\'>&#8505;</p>');">
                <h2>Order Status</h2>
                <p>Looking for your order details? You can find all your past orders in your account.</p>
                 <div class="confirmation-actions">
                    <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo get_asset_url('my_account.php?view=orders'); ?>" class="btn btn-outline-secondary">View My Orders</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php
// Footer
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
}
?>