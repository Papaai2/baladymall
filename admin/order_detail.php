<?php
// admin/order_detail.php - Super Admin: View Order Details

$admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ORDER DETAIL ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Order Details"; // Will be updated
include_once 'includes/header.php';

$db = getPDOConnection();
$message = '';
$errors = [];

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid Order ID.</div>";
    header("Location: orders.php");
    exit;
}

// --- Fetch Order Details ---
$order = null;
$order_items = [];
$customer = null;

try {
    // Fetch main order data
    $stmt_order = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id");
    $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_order->execute();
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['admin_message'] = "<div class='admin-message error'>Order #{$order_id} not found.</div>";
        header("Location: orders.php");
        exit;
    }
    $admin_page_title = "Details for Order #" . htmlspecialchars($order['order_id']);

    // Fetch customer data
    $stmt_customer = $db->prepare("SELECT user_id, username, email, first_name, last_name, phone_number FROM users WHERE user_id = :customer_id");
    $stmt_customer->bindParam(':customer_id', $order['customer_id'], PDO::PARAM_INT);
    $stmt_customer->execute();
    $customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    // Fetch order items
    // Joining with products and product_variants (if variant_id is present)
    $sql_items = "SELECT oi.*, p.product_name, p.main_image_url as product_main_image,
                         pv.variant_sku, pv.variant_image_url,
                         GROUP_CONCAT(av.value ORDER BY a.attribute_name SEPARATOR ', ') as variant_attributes
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.product_id
                  LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                  LEFT JOIN variant_attribute_values vav ON pv.variant_id = vav.variant_id
                  LEFT JOIN attribute_values av ON vav.attribute_value_id = av.attribute_value_id
                  LEFT JOIN attributes a ON av.attribute_id = a.attribute_id
                  WHERE oi.order_id = :order_id
                  GROUP BY oi.order_item_id
                  ORDER BY p.product_name ASC";
    $stmt_items = $db->prepare($sql_items);
    $stmt_items->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin Order Detail - Error fetching order data for ID {$order_id}: " . $e->getMessage());
    $message = "<div class='admin-message error'>Could not load order details.</div>"; // FIX: Removed raw error message
    // No redirect here, show error on page
}

// --- Handle Order Status Update ---
$order_statuses_available = ['pending_payment', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']; // From ENUM

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $new_status = filter_input(INPUT_POST, 'order_status', FILTER_UNSAFE_RAW);
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if (!$csrf_token_form || !hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $errors['csrf'] = "CSRF token mismatch. Please try again.";
    } elseif (empty($new_status) || !in_array($new_status, $order_statuses_available)) {
        $errors['order_status'] = "Invalid order status selected.";
    }

    if (empty($errors)) {
        try {
            $stmt_update_status = $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id");
            $stmt_update_status->bindParam(':status', $new_status);
            $stmt_update_status->bindParam(':order_id', $order_id, PDO::PARAM_INT);

            if ($stmt_update_status->execute()) {
                $_SESSION['admin_message'] = "<div class='admin-message success'>Order #{$order_id} status updated to '" . htmlspecialchars(ucwords(str_replace('_', ' ', $new_status))) . "'.</div>";
                // Re-fetch order data to reflect changes immediately on the page
                $stmt_order->execute();
                $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

                $message = $_SESSION['admin_message']; // Display message immediately
                unset($_SESSION['admin_message']);

                // --- Send Order Status Update Email to Customer (if status changed) ---
                if (isset($order['order_status']) && $new_status !== $order['order_status']) { // Check if status actually changed
                    $customer_email_subject = SITE_NAME . " - Order #{$order_id} Status Updated";
                    $customer_email_body = "Hello " . htmlspecialchars($customer['first_name'] ?? $customer['username'] ?? 'Customer') . ",\n\n"; // FIX: Coalesce
                    $customer_email_body .= "The status of your order #{$order_id} on " . htmlspecialchars(SITE_NAME) . " has been updated to: " . htmlspecialchars(ucwords(str_replace('_', ' ', $new_status))) . "\n\n";
                    $customer_email_body .= "You can view your order details here: " . rtrim(SITE_URL, '/') . "/order_detail.php?order_id=" . htmlspecialchars($order_id) . "\n\n";
                    $customer_email_body .= "Regards,\n" . htmlspecialchars(SITE_NAME) . " Team";

                    // Use send_email function from config.php if defined
                    if (function_exists('send_email')) {
                        send_email($customer['email'], $customer_email_subject, $customer_email_body);
                    } else {
                        error_log("ADMIN ORDER STATUS UPDATE EMAIL (send_email function not found) for {$customer['email']} (Order #{$order_id}):\nSubject: {$customer_email_subject}\nBody:\n{$customer_email_body}\n");
                    }
                }


            } else {
                $message = "<div class='admin-message error'>Failed to update order status.</div>";
            }
        } catch (PDOException $e) {
            error_log("Admin Order Detail - Error updating status for order ID {$order_id}: " . $e->getMessage());
            $message = "<div class='admin-message error'>Database error updating status.</div>"; // FIX: Removed raw error message
        }
    } else {
        // Display validation errors
        $error_message_text = "<ul>";
        foreach($errors as $err) { $error_message_text .= "<li>" . htmlspecialchars($err) . "</li>"; } // FIX: htmlspecialchars
        $error_message_text .= "</ul>";
        $message = "<div class='admin-message error'>{$error_message_text}</div>";
    }
}

// --- Handle Internal Notes Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_internal_notes'])) {
    $internal_notes_content = trim(filter_input(INPUT_POST, 'internal_notes', FILTER_UNSAFE_RAW) ?? '');
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if (!$csrf_token_form || !hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $errors['csrf'] = "CSRF token mismatch for internal notes. Please try again.";
    }

    if (empty($errors)) {
        try {
            // Update internal notes for the order
            $stmt_update_notes = $db->prepare("
                UPDATE orders SET internal_notes = :notes, updated_at = NOW()
                WHERE order_id = :order_id
            ");
            $stmt_update_notes->bindParam(':notes', $internal_notes_content);
            $stmt_update_notes->bindParam(':order_id', $order_id, PDO::PARAM_INT);

            if ($stmt_update_notes->execute() && $stmt_update_notes->rowCount() > 0) {
                $_SESSION['admin_message'] = "<div class='admin-message success'>Internal notes for Order #{$order_id} saved successfully.</div>";
                // Re-fetch order data to reflect changes immediately
                $stmt_order->execute();
                $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

                $message = $_SESSION['admin_message']; // Display message immediately
                unset($_SESSION['admin_message']);
            } else {
                $message = "<div class='admin-message error'>Failed to save internal notes or no changes were made.</div>";
            }
        } catch (PDOException $e) {
            error_log("Admin Order Detail - Error saving internal notes for order ID {$order_id}: " . $e->getMessage());
            $message = "<div class='admin-message error'>Database error saving internal notes.</div>"; // FIX: Removed raw error message
        }
    } else {
        $error_message_text = "<ul>";
        foreach($errors as $err) { $error_message_text .= "<li>" . htmlspecialchars($err) . "</li>"; } // FIX: htmlspecialchars
        $error_message_text .= "</ul>";
        $message = "<div class='admin-message error'>{$error_message_text}</div>";
    }
}


// Generate CSRF token if not already set (for any form)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="orders.php">&laquo; Back to Order List</a></p>

<?php if ($message) echo $message; ?>

<?php if ($order): ?>
    <div class="order-detail-container" style="display: flex; flex-wrap: wrap; gap: 20px;">

        <div class="order-summary-section admin-form" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Order Summary</h2>
            <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($order['order_id']); ?></p>
            <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($order['order_date']))); ?></p>
            <p><strong>Current Status:</strong>
                <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>" style="font-weight:bold;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?>
                </span>
            </p>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'))); ?></p>
            <?php if (!empty($order['payment_gateway_transaction_id'])): // FIX: Use empty check for non-empty string ?>
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['payment_gateway_transaction_id']); ?></p>
            <?php endif; ?>
            <hr>
            <form action="order_detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="order_status"><strong>Update Order Status:</strong></label>
                    <select name="order_status" id="order_status">
                        <?php foreach ($order_statuses_available as $status_val): ?>
                            <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ((string)$order['order_status'] === (string)$status_val) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status_val))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="update_order_status" class="btn-submit">Update Status</button>
            </form>
        </div>

        <div class="customer-details-section" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Customer Details</h2>
            <?php if ($customer): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: 'N/A'); ?></p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($customer['username'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"><?php echo htmlspecialchars($customer['email'] ?? ''); ?></a></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?></p>
                <p><a href="edit_user.php?user_id=<?php echo htmlspecialchars($customer['user_id']); ?>" class="btn-edit" style="font-size:0.9em; padding: 5px 10px;">View/Edit Customer</a></p>
            <?php else: ?>
                <p class="admin-message warning">Customer details not found.</p>
            <?php endif; ?>
        </div>

        <div class="shipping-details-section" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Shipping Address</h2>
            <p><strong>Recipient Name:</strong> <?php echo htmlspecialchars($order['shipping_name'] ?? 'N/A'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? 'N/A'); ?></p>
            <p><?php echo htmlspecialchars($order['shipping_address_line1'] ?? 'N/A'); ?></p>
            <?php if (!empty($order['shipping_address_line2'])): ?>
                <p><?php echo htmlspecialchars($order['shipping_address_line2']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($order['shipping_city'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($order['shipping_governorate'] ?? 'N/A'); ?></p>
            <?php if (!empty($order['shipping_postal_code'])): ?>
                <p>Postal Code: <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($order['shipping_country'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <div class="ordered-items-section" style="margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Ordered Items (<?php echo count($order_items); ?>)</h2>
        <?php if (!empty($order_items)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Attributes</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Item Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <?php
                                $item_image_url = $item['variant_image_url'] ?: ($item['product_main_image'] ?? null);
                                $image_path = '';
                                if (!empty($item_image_url)) {
                                    if (filter_var($item_image_url, FILTER_VALIDATE_URL) || strpos($item_image_url, '//') === 0) {
                                        $image_path = htmlspecialchars($item_image_url);
                                    } else {
                                        $image_path = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $item_image_url);
                                    }
                                } else {
                                    $image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=No+Img');
                                }
                                $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Error');
                                ?>
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                            </td>
                            <td>
                                <a href="edit_product.php?product_id=<?php echo htmlspecialchars($item['product_id']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?>
                                </a>
                                <?php if (isset($item['variant_id']) && $item['variant_id']): ?>
                                    <small style="display:block;">Variant ID: <?php echo htmlspecialchars($item['variant_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['variant_sku'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['variant_attributes'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$item['price_at_purchase'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$item['subtotal_for_item'], 2)); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $item['item_status'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['item_status'] ?? 'N/A'))); ?>
                                </span>
                                <?php // Potentially add a way to update individual item statuses later ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="admin-message info">No items found for this order.</p>
        <?php endif; ?>
    </div>

    <div class="order-totals-notes-section" style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="order-notes" style="flex: 1 1 300px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;">Notes</h3>
            <p><strong>Customer Notes:</strong><br> <?php echo nl2br(htmlspecialchars($order['notes_to_seller'] ?? 'None')); ?></p>
            <hr>
            <p><strong>Internal Notes (Admin only):</strong></p>
            <form action="order_detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="save_internal_notes" value="1">
                <textarea name="internal_notes" rows="3" style="width:100%; margin-bottom:10px;" placeholder="Add internal notes..."><?php echo htmlspecialchars($order['internal_notes'] ?? ''); ?></textarea>
                <button type="submit" name="save_internal_notes" class="btn-submit" style="font-size:0.9em;">Save Internal Notes</button>
            </form>
        </div>
        <div class="order-totals" style="flex: 1 1 300px; text-align: right; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; text-align:left;">Order Totals</h3>
            <p><strong>Subtotal:</strong> <?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['subtotal_amount'], 2)); ?></p>
            <p><strong>Shipping:</strong> <?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['shipping_amount'], 2)); ?></p>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <p><strong>Discount:</strong> -<?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['discount_amount'], 2)); ?></p>
            <?php endif; ?>
            <?php if ((float)$order['tax_amount'] > 0): ?>
                <p><strong>Tax:</strong> <?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['tax_amount'], 2)); ?></p>
            <?php endif; ?>
            <hr>
            <p style="font-size: 1.2em; font-weight: bold;"><strong>Grand Total: <?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['total_amount'], 2)); ?></strong></p>
        </div>
    </div>

<?php else: ?>
    <?php if (empty($message)): // Show default not found if no other message is set ?>
        <p class="admin-message error">Order details could not be loaded.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>