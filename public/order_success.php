<?php
// public/order_success.php

$page_title = "Order Confirmation - BaladyMall";

// Configuration, Header, and Footer paths
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . $config_path_from_public);
}

// Header includes session_start()
if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public;
} else {
    die("Critical error: Header file not found. Expected at: " . $header_path_from_public);
}

$db = getPDOConnection();
$order_details = null;
$error_message = '';

// Check if user is logged in - they should be if they just placed an order
if (!isset($_SESSION['user_id'])) {
    // This case should ideally not happen if checkout requires login
    header("Location: " . rtrim(SITE_URL, '/') . "/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Retrieve the last order ID from the session
if (isset($_SESSION['last_order_id'])) {
    $last_order_id = $_SESSION['last_order_id'];

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
            $error_message = "Could not retrieve your order details. Please check your account or contact support.";
        }
        
        // Clear the last_order_id from session to prevent re-display on refresh
        // or if user navigates back to this page without placing a new order.
        unset($_SESSION['last_order_id']);

    } catch (PDOException $e) {
        error_log("Order Success - Error fetching order details (ID: $last_order_id): " . $e->getMessage());
        $error_message = "An error occurred while retrieving your order information. Please contact support if the issue persists.";
    }
} else {
    // If last_order_id is not in session, it means the user might have landed here directly
    // or refreshed after the session variable was cleared.
    // You can redirect them or show a generic message.
    // For now, we'll show a message indicating no specific order to display.
    $error_message = "No recent order information found. If you just placed an order, please check your account for details.";
    // Or redirect:
    // header("Location: " . rtrim(SITE_URL, '/') . "/my_account.php?view=orders");
    // exit;
}

?>

<section class="order-success-section">
    <div class="container text-center">

        <?php if (!empty($error_message) && !$order_details): ?>
            <div class="order-confirmation-box error-box">
                <img src="<?php echo rtrim(SITE_URL, '/'); ?>/images/order-error.svg" alt="Order Error" class="confirmation-icon" 
                     onerror="this.style.display='none';">
                <h2>Order Information Not Found</h2>
                <p><?php echo htmlspecialchars($error_message); ?></p>
                <div class="confirmation-actions">
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=orders" class="btn btn-outline-secondary">View My Orders</a>
                </div>
            </div>
        <?php elseif ($order_details): ?>
            <div class="order-confirmation-box success-box">
                 <img src="<?php echo rtrim(SITE_URL, '/'); ?>/images/order-success.svg" alt="Order Success" class="confirmation-icon"
                      onerror="this.style.display='none';">
                <h2>Thank You For Your Order, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Valued Customer'); ?>!</h2>
                <p class="lead-text">Your order has been placed successfully.</p>
                
                <div class="order-summary-brief">
                    <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($order_details['order_id']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($order_details['order_date']))); ?></p>
                    <p><strong>Total Amount:</strong> <?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($order_details['total_amount'], 2)); ?></p>
                    <p><strong>Shipping To:</strong> <?php echo htmlspecialchars($order_details['shipping_name']); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_details['payment_method']))); ?></p>
                </div>

                <p class="mt-4">You will receive an email confirmation shortly with the full details of your order. <br>
                   (Note: Email sending functionality is not yet implemented in this version.)</p>
                
                <p>If you have any questions, please don't hesitate to <a href="<?php echo rtrim(SITE_URL, '/'); ?>/contact.php">contact us</a>.</p>

                <div class="confirmation-actions">
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=orders" class="btn btn-outline-secondary">View Order Details</a> 
                    <?php /* Link to specific order: /order_details.php?order_id=<?php echo $order_details['order_id']; ?> */ ?>
                </div>
            </div>
        <?php else: ?>
             <div class="order-confirmation-box info-box">
                <img src="<?php echo rtrim(SITE_URL, '/'); ?>/images/order-info.svg" alt="Order Information" class="confirmation-icon"
                     onerror="this.style.display='none';">
                <h2>Order Status</h2>
                <p>Looking for your order details? You can find all your past orders in your account.</p>
                 <div class="confirmation-actions">
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary">Continue Shopping</a>
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/my_account.php?view=orders" class="btn btn-outline-secondary">View My Orders</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php
// Footer
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . $footer_path_from_public);
}
?>
