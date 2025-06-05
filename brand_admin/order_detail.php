<?php
// brand_admin/order_detail.php - Brand Admin: View Order Details

$brand_admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL BRAND ADMIN ORDER DETAIL ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is brand_admin and sets $_SESSION['brand_id']

$brand_admin_page_title = "Order Details"; // Will be updated
include_once 'includes/header.php';

$db = getPDOConnection();

// Get the assigned brand ID from the session
$current_brand_id = $_SESSION['brand_id'];
$current_brand_name = $_SESSION['brand_name'];

$message = '';
$errors = [];

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Invalid Order ID.</div>";
    header("Location: orders.php");
    exit;
}

// --- Fetch Order Details ---
$order = null;
$order_items = [];
$customer = null;

try {
    // Fetch main order data, but only if it contains items from this brand
    $stmt_order = $db->prepare("
        SELECT DISTINCT o.*, u.email as customer_email, u.username as customer_username, u.first_name as customer_first_name
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.order_id = :order_id AND oi.brand_id = :brand_id
    ");
    $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_order->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT); // Crucial filter
    $stmt_order->execute();
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['brand_admin_message'] = "<div class='brand-admin-message error'>Order #{$order_id} not found or does not contain your brand's products.</div>";
        header("Location: orders.php");
        exit;
    }
    $brand_admin_page_title = "Details for Order #" . htmlspecialchars($order['order_id'] ?? 'N/A'); // FIX: Coalesce for htmlspecialchars

    // Fetch customer data (no brand filter needed here, as order itself is filtered)
    $stmt_customer = $db->prepare("SELECT user_id, username, email, first_name, last_name, phone_number FROM users WHERE user_id = :customer_id");
    $stmt_customer->bindParam(':customer_id', $order['customer_id'], PDO::PARAM_INT);
    $stmt_customer->execute();
    $customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    // Fetch order items, specifically for this brand and order
    $sql_items = "SELECT oi.*, p.product_name, p.main_image_url as product_main_image,
                         pv.variant_sku, pv.variant_image_url,
                         GROUP_CONCAT(av.value ORDER BY a.attribute_name SEPARATOR ', ') as variant_attributes
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.product_id
                  LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                  LEFT JOIN variant_attribute_values vav ON pv.variant_id = vav.variant_id
                  LEFT JOIN attribute_values av ON vav.attribute_value_id = av.attribute_value_id
                  LEFT JOIN attributes a ON av.attribute_id = a.attribute_id
                  WHERE oi.order_id = :order_id AND oi.brand_id = :brand_id_item_filter
                  GROUP BY oi.order_item_id
                  ORDER BY p.product_name ASC";
    $stmt_items = $db->prepare($sql_items);
    $stmt_items->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_items->bindParam(':brand_id_item_filter', $current_brand_id, PDO::PARAM_INT); // Crucial filter for items
    $stmt_items->execute();
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Brand Admin Order Detail - Error fetching order data for ID {$order_id} and brand {$current_brand_id}: " . $e->getMessage());
    $message = "<div class='brand-admin-message error'>Could not load order details.</div>"; // FIX: Removed raw error message
}

// --- Handle Order Item Status Update ---
// Brand admins can only update the status of THEIR order items, not the overall order status.
$item_statuses_available = ['pending', 'processing', 'shipped_by_brand', 'delivered_to_customer', 'cancelled', 'returned']; // From ENUM


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_status'])) {
    $order_item_id_to_update = filter_input(INPUT_POST, 'order_item_id', FILTER_VALIDATE_INT);
    $new_item_status = filter_input(INPUT_POST, 'item_status', FILTER_UNSAFE_RAW);
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if (!$csrf_token_form || !hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        $errors['csrf'] = "CSRF token mismatch. Please try again.";
    } elseif (empty($new_item_status) || !in_array($new_item_status, $item_statuses_available)) {
        $errors['item_status'] = "Invalid item status selected.";
    } elseif (!$order_item_id_to_update) {
        $errors['order_item_id'] = "Invalid order item ID.";
    }

    if (empty($errors)) {
        try {
            // Fetch current item status and product name for email notification
            $stmt_current_item_info = $db->prepare("
                SELECT oi.item_status, p.product_name, oi.quantity
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_item_id = :order_item_id AND oi.brand_id = :brand_id LIMIT 1
            ");
            $stmt_current_item_info->bindParam(':order_item_id', $order_item_id_to_update, PDO::PARAM_INT);
            $stmt_current_item_info->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
            $stmt_current_item_info->execute();
            $current_item_info = $stmt_current_item_info->fetch(PDO::FETCH_ASSOC);

            $old_item_status = $current_item_info['item_status'] ?? '';
            $product_name_for_email = $current_item_info['product_name'] ?? 'Unknown Product';
            $item_quantity_for_email = $current_item_info['quantity'] ?? 1;


            // Crucially, ensure the order item belongs to this brand
            $stmt_update_item_status = $db->prepare("
                UPDATE order_items
                SET item_status = :status, updated_at = NOW()
                WHERE order_item_id = :order_item_id AND brand_id = :brand_id
            ");
            $stmt_update_item_status->bindParam(':status', $new_item_status);
            $stmt_update_item_status->bindParam(':order_item_id', $order_item_id_to_update, PDO::PARAM_INT);
            $stmt_update_item_status->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT); // Crucial filter

            if ($stmt_update_item_status->execute()) {
                if ($stmt_update_item_status->rowCount() > 0) {
                    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message success'>Order item #{$order_item_id_to_update} status updated to '" . htmlspecialchars(ucwords(str_replace('_', ' ', $new_item_status))) . "'.</div>";

                    // --- Send Customer Notification Email for Item Status Update ---
                    if ($new_item_status !== $old_item_status) { // Only send if status actually changed
                        $customer_email_subject = SITE_NAME . " - Update for Your Order #{$order_id}";
                        $customer_email_body = "Hello " . htmlspecialchars($order['customer_first_name'] ?? $order['customer_username'] ?? 'Customer') . ",\n\n";
                        $customer_email_body .= "The status of one of your items in order #{$order_id} has been updated:\n\n";
                        $customer_email_body .= "Product: " . htmlspecialchars($product_name_for_email) . " (x{$item_quantity_for_email})\n";
                        $customer_email_body .= "New Status: " . htmlspecialchars(ucwords(str_replace('_', ' ', $new_item_status))) . "\n\n";
                        $customer_email_body .= "You can view your full order details here: " . rtrim(SITE_URL, '/') . "/order_detail.php?order_id=" . htmlspecialchars($order_id) . "\n\n"; // FIX: htmlspecialchars for order_id in URL
                        $customer_email_body .= "Regards,\n" . htmlspecialchars(SITE_NAME) . " Team"; // FIX: htmlspecialchars for SITE_NAME

                        // Placeholder for actual email sending
                        // Using send_email function from config.php if defined
                        if (function_exists('send_email')) {
                            send_email($order['customer_email'], $customer_email_subject, $customer_email_body);
                        } else {
                            error_log("CUSTOMER ITEM STATUS UPDATE EMAIL (send_email function not found) for {$order['customer_email']} (Order #{$order_id}, Item #{$order_item_id_to_update}):\nSubject: {$customer_email_subject}\nBody:\n{$customer_email_body}\n");
                        }
                    }
                    // --- End Customer Notification Email ---


                    // --- Logic to update overall order status based on item statuses ---
                    try {
                        // Get all item statuses for this order
                        $stmt_all_item_statuses = $db->prepare("SELECT item_status FROM order_items WHERE order_id = :order_id");
                        $stmt_all_item_statuses->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                        $stmt_all_item_statuses->execute();
                        $all_item_statuses = $stmt_all_item_statuses->fetchAll(PDO::FETCH_COLUMN);

                        $all_delivered = true;
                        $all_shipped_or_delivered = true;
                        $any_pending_or_processing = false;
                        $all_cancelled_items = true;
                        $all_returned_items = true;

                        foreach ($all_item_statuses as $status) {
                            if ($status !== 'delivered_to_customer') {
                                $all_delivered = false;
                            }
                            if ($status !== 'shipped_by_brand' && $status !== 'delivered_to_customer') {
                                $all_shipped_or_delivered = false;
                            }
                            if ($status === 'pending' || $status === 'processing') {
                                $any_pending_or_processing = true;
                            }
                            if ($status !== 'cancelled') {
                                $all_cancelled_items = false;
                            }
                            if ($status !== 'returned') {
                                $all_returned_items = false;
                            }
                        }

                        $new_overall_order_status = $order['order_status']; // Default to current status

                        // Determine the new overall status based on item states, with a clear hierarchy
                        if ($all_delivered) {
                            $new_overall_order_status = 'delivered';
                        } elseif ($all_shipped_or_delivered && !$any_pending_or_processing && !$all_cancelled_items && !$all_returned_items) {
                            $new_overall_order_status = 'shipped';
                        } elseif ($any_pending_or_processing) {
                            $new_overall_order_status = 'processing';
                        } elseif (count($all_item_statuses) > 0 && $all_cancelled_items) { // All items are cancelled
                            $new_overall_order_status = 'cancelled';
                        } elseif (count($all_item_statuses) > 0 && $all_returned_items) { // All items are returned
                            $new_overall_order_status = 'refunded';
                        }


                        // Update overall order status if it's different
                        if ($new_overall_order_status !== $order['order_status']) {
                            $stmt_update_overall_status = $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id");
                            $stmt_update_overall_status->bindParam(':status', $new_overall_order_status);
                            $stmt_update_overall_status->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                            $stmt_update_overall_status->execute();

                            if ($stmt_update_overall_status->rowCount() > 0) {
                                $_SESSION['brand_admin_message'] .= "<br><div class='brand-admin-message info'>Overall order status automatically updated to '" . htmlspecialchars(ucwords(str_replace('_', ' ', $new_overall_order_status))) . "'.</div>";
                            }
                        }

                    } catch (PDOException $e) {
                        error_log("Brand Admin Order Detail - Error updating overall order status for order ID {$order_id}: " . $e->getMessage());
                    }
                    // --- End of overall order status update logic ---

                } else {
                    $_SESSION['brand_admin_message'] = "<div class='brand-admin-message warning'>Order item #{$order_item_id_to_update} status was already '" . htmlspecialchars(ucwords(str_replace('_', ' ', $new_item_status))) . "' or could not be updated.</div>";
                }
                // Redirect to refresh the page and show updated status
                header("Location: order_detail.php?order_id=".htmlspecialchars($order_id)); // FIX: htmlspecialchars
                exit;
            } else {
                $message = "<div class='brand-admin-message error'>Failed to update order item status. Database operation failed.</div>";
            }
        } catch (PDOException $e) {
            error_log("Brand Admin Order Detail - Error updating item status for order item ID {$order_item_id_to_update}: " . $e->getMessage());
            $message = "<div class='brand-admin-message error'>Database error updating item status.</div>"; // FIX: Removed raw error message
        }
    } else {
        $error_message_text = "<ul>";
        foreach($errors as $err) { $error_message_text .= "<li>" . htmlspecialchars($err) . "</li>"; } // FIX: htmlspecialchars
        $error_message_text .= "</ul>";
        $message = "<div class='brand-admin-message error'>{$error_message_text}</div>";
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
            // Update internal notes for the order, but only if the order contains items from this brand
            $stmt_update_notes = $db->prepare("
                UPDATE orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                SET o.internal_notes = :notes, o.updated_at = NOW()
                WHERE o.order_id = :order_id AND oi.brand_id = :brand_id
            ");
            $stmt_update_notes->bindParam(':notes', $internal_notes_content);
            $stmt_update_notes->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt_update_notes->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);

            if ($stmt_update_notes->execute() && $stmt_update_notes->rowCount() > 0) {
                $_SESSION['brand_admin_message'] = "<div class='brand-admin-message success'>Internal notes for Order #{$order_id} saved successfully.</div>";
                header("Location: order_detail.php?order_id=".htmlspecialchars($order_id)); // FIX: htmlspecialchars
                exit;
            } else {
                $message = "<div class='brand-admin-message error'>Failed to save internal notes. The order might not contain your brand's products or no changes were made.</div>";
            }
        } catch (PDOException $e) {
            error_log("Brand Admin Order Detail - Error saving internal notes for order ID {$order_id}: " . $e->getMessage());
            $message = "<div class='brand-admin-message error'>Database error saving internal notes.</div>"; // FIX: Removed raw error message
        }
    } else {
        $error_message_text = "<ul>";
        foreach($errors as $err) { $error_message_text .= "<li>" . htmlspecialchars($err) . "</li>"; } // FIX: htmlspecialchars
        $error_message_text .= "</ul>";
        $message = "<div class='brand-admin-message error'>{$error_message_text}</div>";
    }
}


// Generate CSRF token if not already set (for any form)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>

<h1 class="brand-admin-page-title"><?php echo htmlspecialchars($brand_admin_page_title); ?> for <?php echo htmlspecialchars($current_brand_name); ?></h1>
<p><a href="orders.php">&laquo; Back to Order List</a></p>

<?php if ($message) echo $message; ?>

<?php if ($order): ?>
    <div class="order-detail-container" style="display: flex; flex-wrap: wrap; gap: 20px;">

        <div class="order-summary-section brand-admin-form" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Order Summary</h2>
            <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($order['order_id']); ?></p>
            <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($order['order_date']))); ?></p>
            <p><strong>Overall Order Status:</strong>
                <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>" style="font-weight:bold;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?>
                </span>
                <small style="display:block; margin-top:5px;">(Managed by Super Admin)</small>
            </p>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'))); ?></p>
            <?php if ($order['payment_gateway_transaction_id']): ?>
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['payment_gateway_transaction_id']); ?></p>
            <?php endif; ?>
        </div>

        <div class="customer-details-section" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Customer Details</h2>
            <?php if ($customer): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']) ?: 'N/A'); ?></p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($customer['username'] ?? 'N/A'); ?></p> <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"><?php echo htmlspecialchars($customer['email'] ?? ''); ?></a></p> <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?></p>
            <?php else: ?>
                <p class="brand-admin-message warning">Customer details not found.</p>
            <?php endif; ?>
        </div>

        <div class="shipping-details-section" style="flex: 1 1 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">Shipping Address</h2>
            <p><strong>Recipient Name:</strong> <?php echo htmlspecialchars($order['shipping_name'] ?? 'N/A'); ?></p> <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? 'N/A'); ?></p> <p><?php echo htmlspecialchars($order['shipping_address_line1'] ?? 'N/A'); ?></p> <?php if (!empty($order['shipping_address_line2'])): ?>
                <p><?php echo htmlspecialchars($order['shipping_address_line2']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($order['shipping_city'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($order['shipping_governorate'] ?? 'N/A'); ?></p> <?php if (!empty($order['shipping_postal_code'])): ?>
                <p>Postal Code: <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($order['shipping_country'] ?? 'N/A'); ?></p> </div>
    </div>

    <div class="ordered-items-section" style="margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Your Brand's Items in This Order (<?php echo count($order_items); ?>)</h2>
        <?php if (!empty($order_items)): ?>
            <table class="brand-admin-table">
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <?php
                                $item_image_url = $item['variant_image_url'] ?: ($item['product_main_image'] ?? null); // FIX: Coalesce product_main_image
                                $image_path = '';
                                if (!empty($item_image_url)) {
                                    if (filter_var($item_image_url, FILTER_VALIDATE_URL)) {
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
                                <?php if (isset($item['variant_id']) && $item['variant_id']): // FIX: check isset ?>
                                    <small style="display:block;">Variant ID: <?php echo htmlspecialchars($item['variant_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['variant_sku'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['variant_attributes'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$item['price_at_purchase'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity'] ?? 'N/A'); ?></td> <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$item['subtotal_for_item'], 2)); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $item['item_status'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['item_status'] ?? 'N/A'))); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <form action="order_detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="order_item_id" value="<?php echo htmlspecialchars($item['order_item_id']); ?>">
                                    <input type="hidden" name="update_item_status" value="1">
                                    <select name="item_status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                                        <?php foreach ($item_statuses_available as $status_val): ?>
                                            <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ((string)$item['item_status'] === (string)$status_val) ? 'selected' : ''; ?>> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status_val))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit" name="update_item_status" class="btn-submit" style="margin-left:5px;">Update</button></noscript>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="brand-admin-message info">No items from your brand found for this order.</p>
        <?php endif; ?>
    </div>

    <div class="order-totals-notes-section" style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="order-notes" style="flex: 1 1 300px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;">Notes</h3>
            <p><strong>Customer Notes:</strong><br> <?php echo nl2br(htmlspecialchars($order['notes_to_seller'] ?? 'None')); ?></p>
            <hr>
            <p><strong>Internal Notes (Admin only):</strong></p>
            <form action="order_detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" class="brand-admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="save_internal_notes" value="1">
                <textarea name="internal_notes" rows="3" style="width:100%; margin-bottom:10px;" placeholder="Add internal notes..."><?php echo htmlspecialchars($order['internal_notes'] ?? ''); ?></textarea>
                <button type="submit" class="btn-submit" style="font-size:0.9em;">Save Internal Notes</button>
            </form>
        </div>
        <div class="order-totals" style="flex: 1 1 300px; text-align: right; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; text-align:left;">Order Totals (Overall Order)</h3>
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
            <small style="display:block; margin-top:5px;">(Reflects entire order, not just your items)</small>
        </div>
    </div>

<?php else: ?>
    <?php if (empty($message)): ?>
        <p class="brand-admin-message error">Order details could not be loaded.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>