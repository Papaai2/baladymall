<?php
// public/order_detail.php

$page_title = "Order Details - BaladyMall";

// Configuration, Header, and Footer paths
$config_path_from_public = __DIR__ . '/../src/config/config.php';
$header_path_from_public = __DIR__ . '/../src/includes/header.php';
$footer_path_from_public = __DIR__ . '/../src/includes/footer.php';

if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    die("Critical error: Main configuration file not found. Expected at: " . htmlspecialchars($config_path_from_public));
}

if (file_exists($header_path_from_public)) {
    require_once $header_path_from_public; // Starts session
} else {
    die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path_from_public));
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // Use get_asset_url for the redirect path and target URL
    $_SESSION['redirect_after_login'] = get_asset_url("order_detail.php?order_id=" . ($_GET['order_id'] ?? '')); // Store current page
    header("Location: " . get_asset_url("login.php?auth=required&target=order_detail"));
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$db = getPDOConnection();
$order_details = null;
$order_items = [];
$page_error_message = '';

// Validate order_id from URL
$order_id = null;
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    if ($order_id <= 0) {
        $page_error_message = "Invalid order ID provided.";
    }
} else {
    $page_error_message = "No order ID specified.";
}

if (empty($page_error_message) && $order_id > 0 && isset($db) && $db instanceof PDO) {
    try {
        // Fetch order details
        $stmt_order = $db->prepare("
            SELECT
                order_id, order_date, order_status, total_amount, subtotal_amount,
                shipping_amount, discount_amount, tax_amount,
                shipping_name, shipping_phone, shipping_address_line1, shipping_address_line2,
                shipping_city, shipping_governorate, shipping_postal_code, shipping_country,
                payment_method, notes_to_seller
            FROM orders
            WHERE order_id = :order_id AND customer_id = :customer_id
            LIMIT 1
        ");
        $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt_order->bindParam(':customer_id', $user_id, PDO::PARAM_INT);
        $stmt_order->execute();
        $order_details = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if (!$order_details) {
            $page_error_message = "Order not found or you do not have permission to view this order.";
        } else {
            // Fetch order items
            $stmt_items = $db->prepare("
                SELECT
                    oi.quantity,
                    oi.price_at_purchase,
                    oi.subtotal_for_item,
                    p.product_name,
                    p.main_image_url,
                    p.product_id,
                    b.brand_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                JOIN brands b ON oi.brand_id = b.brand_id
                WHERE oi.order_id = :order_id
                ORDER BY p.product_name ASC
            ");
            $stmt_items->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt_items->execute();
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        error_log("Order Detail Page DB Error (Order ID: $order_id, User ID: $user_id): " . $e->getMessage());
        $page_error_message = "An error occurred while loading order details. Please try again later.";
    }
} else {
    // If DB connection fails even without a page_error_message, set one
    if (empty($page_error_message) && (!isset($db) || !$db instanceof PDO)) {
        $page_error_message = "Database connection is not available. Cannot load order details.";
    }
}

?>

<section class="order-detail-section info-page-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Order Details #<?php echo esc_html($order_id); ?></h2>

        <?php if (!empty($page_error_message)): ?>
            <div class="page-content">
                <div class="form-message error-message text-center">
                    <?php echo esc_html($page_error_message); ?>
                    <p class="mt-3"><a href="<?php echo get_asset_url('my_account.php?view=orders'); ?>" class="btn btn-primary">Back to Order History</a></p>
                </div>
            </div>
        <?php elseif ($order_details): ?>
            <div class="page-content">
                <div class="order-detail-summary-grid">
                    <div class="order-info-block order-detail-card">
                        <h3>Order Information</h3>
                        <p><strong>Order Date:</strong> <span><?php echo esc_html(date("F j, Y, g:i a", strtotime($order_details['order_date']))); ?></span></p>
                        <p><strong>Status:</strong> <span class="order-status-badge status-<?php echo esc_html(str_replace(' ', '-', $order_details['order_status'])); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order_details['order_status']))); ?></span></p>
                        <p><strong>Payment Method:</strong> <span><?php echo esc_html(ucwords(str_replace('_', ' ', $order_details['payment_method']))); ?></span></p>
                        <?php if (!empty($order_details['notes_to_seller'])): ?>
                            <p><strong>Customer Notes:</strong><br><span><?php echo nl2br(esc_html($order_details['notes_to_seller'])); ?></span></p>
                        <?php endif; ?>
                    </div>

                    <div class="shipping-info-block order-detail-card">
                        <h3>Shipping Address</h3>
                        <p><span><?php echo esc_html($order_details['shipping_name']); ?></span></p>
                        <p><span><?php echo esc_html($order_details['shipping_phone']); ?></span></p>
                        <p><span><?php echo esc_html($order_details['shipping_address_line1']); ?></span></p>
                        <?php if (!empty($order_details['shipping_address_line2'])): ?>
                            <p><span><?php echo esc_html($order_details['shipping_address_line2']); ?></span></p>
                        <?php endif; ?>
                        <p><span><?php echo esc_html($order_details['shipping_city']); ?>, <?php echo esc_html($order_details['shipping_governorate']); ?></span></p>
                        <?php if (!empty($order_details['shipping_postal_code'])): ?>
                            <p><span><?php echo esc_html($order_details['shipping_postal_code']); ?></span></p>
                        <?php endif; ?>
                        <p><span><?php echo esc_html($order_details['shipping_country']); ?></span></p>
                    </div>

                    <div class="order-totals-block order-detail-card">
                        <h3>Order Summary</h3>
                        <p><strong>Subtotal:</strong> <span><?php echo CURRENCY_SYMBOL . esc_html(number_format($order_details['subtotal_amount'], 2)); ?></span></p>
                        <?php if ($order_details['shipping_amount'] > 0): ?>
                            <p><strong>Shipping:</strong> <span><?php echo CURRENCY_SYMBOL . esc_html(number_format($order_details['shipping_amount'], 2)); ?></span></p>
                        <?php else: ?>
                            <p><strong>Shipping:</strong> <span>Free</span></p>
                        <?php endif; ?>
                        <?php if ($order_details['tax_amount'] > 0): ?>
                            <p><strong>Taxes:</strong> <span><?php echo CURRENCY_SYMBOL . esc_html(number_format($order_details['tax_amount'], 2)); ?></span></p>
                        <?php endif; ?>
                        <?php if ($order_details['discount_amount'] > 0): ?>
                            <p><strong>Discount:</strong> <span style="color: green;">-<?php echo CURRENCY_SYMBOL . esc_html(number_format($order_details['discount_amount'], 2)); ?></span></p>
                        <?php endif; ?>
                        <hr>
                        <p class="grand-total"><strong>Grand Total:</strong> <strong><?php echo CURRENCY_SYMBOL . esc_html(number_format($order_details['total_amount'], 2)); ?></strong></p>
                    </div>
                </div>

                <h3 class="order-items-heading">Items in This Order</h3>
                <?php if (!empty($order_items)): ?>
                    <div class="table-responsive">
                        <table class="orders-table order-items-table">
                            <thead>
                                <tr>
                                    <th colspan="2" class="text-left">Product</th>
                                    <th class="text-left">Brand</th>
                                    <th class="text-right">Price at Purchase</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <?php
                                        // Use get_asset_url for product item link and image URLs
                                        $product_item_url = get_asset_url("product_detail.php?id=" . esc_html($item['product_id']));
                                        $item_image_url_display = '';

                                        // Determine fallback image URL and ensure it's properly escaped for the onerror attribute
                                        $fallback_order_item_image_esc = '';
                                        if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                                            $fallback_order_item_image_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/80x80/CCC/777?text=Error");
                                        } else {
                                            $fallback_order_item_image_esc = get_asset_url('images/no-image.png'); // Assuming you have this file
                                        }

                                        // Fix: Use get_asset_url for consistency in image paths, adjusting for 'products/filename.jpg'
                                        if (!empty($item['main_image_url'])) {
                                            if (filter_var($item['main_image_url'], FILTER_VALIDATE_URL)) {
                                                $item_image_url_display = esc_html($item['main_image_url']);
                                            } else {
                                                // Assuming $item['main_image_url'] starts with 'products/'. Prepends 'uploads/'
                                                $item_image_url_display = get_asset_url('uploads/' . ltrim(esc_html($item['main_image_url']), '/'));
                                            }
                                        }

                                        // If main $item_image_url_display is still empty, set it to the fallback
                                        if (empty($item_image_url_display)) {
                                            $item_image_url_display = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/80x80/F0F0F0/AAA?text=No+Image" : $fallback_order_item_image_esc;
                                        }
                                    ?>
                                    <tr>
                                        <td class="order-item-image" data-label="Image" style="width: 80px;">
                                            <a href="<?php echo $product_item_url; ?>">
                                                <img src="<?php echo $item_image_url_display; ?>" alt="<?php echo esc_html($item['product_name']); ?>"
                                                     onerror="this.onerror=null;this.src='<?php echo $fallback_order_item_image_esc; ?>';">
                                            </a>
                                        </td>
                                        <td class="order-item-name" data-label="Product">
                                            <a href="<?php echo $product_item_url; ?>"><?php echo esc_html($item['product_name']); ?></a>
                                        </td>
                                        <td data-label="Brand"><?php echo esc_html($item['brand_name']); ?></td>
                                        <td class="text-right" data-label="Price"><?php echo CURRENCY_SYMBOL . esc_html(number_format($item['price_at_purchase'], 2)); ?></td>
                                        <td class="text-center" data-label="Quantity"><?php echo esc_html($item['quantity']); ?></td>
                                        <td class="text-right" data-label="Line Total"><?php echo CURRENCY_SYMBOL . esc_html(number_format($item['subtotal_for_item'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No items found for this order.</p>
                <?php endif; ?>

                <div class="text-center mt-5">
                    <a href="<?php echo get_asset_url('my_account.php?view=orders'); ?>" class="btn btn-outline-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="margin-right:5px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg>
                        Back to Order History
                    </a>
                    <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary">Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
if (file_exists($footer_path_from_public)) {
    require_once $footer_path_from_public;
} else {
    die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path_from_public));
}
?>