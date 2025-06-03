<?php
// public/cart.php

$page_title = "Your Shopping Cart - BaladyMall";

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
$cart_items_details = [];
$cart_subtotal = 0;
$cart_total_items = 0;
$cart_action_message = ''; // For success/error messages related to cart actions

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Cart Action Handling ---
$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to catch both GET and POST for action
// Ensure product_id is correctly identified from GET or POST
if (isset($_REQUEST['product_id'])) {
    $product_id = (int)$_REQUEST['product_id'];
} elseif (isset($_REQUEST['id'])) { // Fallback for 'id' if 'product_id' isn't used consistently
    $product_id = (int)$_REQUEST['id'];
} else {
    $product_id = null;
}
// Quantity can be an array for 'update_all' or a single value for other actions
$quantity_request = $_REQUEST['quantity'] ?? 1;


if ($action && $product_id !== null && $action !== 'update_all') { // Ensure product_id is set for single item actions
    $stock_quantity_db = 9999; // Default large stock if not variant-specific or simple product stock check
    $is_simple_product_with_stock_db = false;
    $product_name_for_message_db = "Product (ID: {$product_id})"; // Default name

    try {
        $stmt_stock_check = $db->prepare("SELECT product_name, stock_quantity, requires_variants FROM products WHERE product_id = :pid AND is_active = 1");
        $stmt_stock_check->bindParam(':pid', $product_id, PDO::PARAM_INT);
        $stmt_stock_check->execute();
        $product_stock_info = $stmt_stock_check->fetch(PDO::FETCH_ASSOC);

        if ($product_stock_info) {
            $product_name_for_message_db = htmlspecialchars($product_stock_info['product_name']);
            if ($product_stock_info['requires_variants'] == 0 && isset($product_stock_info['stock_quantity'])) {
                $stock_quantity_db = (int)$product_stock_info['stock_quantity'];
                $is_simple_product_with_stock_db = true;
            }
        } else {
             $cart_action_message = "<div class='form-message error-message'>Product (ID: {$product_id}) not found or is inactive.</div>";
             $action = null; // Prevent further processing for this product
        }
    } catch (PDOException $e) {
        error_log("Cart Action - Stock Check Error (Product ID: {$product_id}): " . $e->getMessage());
        $cart_action_message = "<div class='form-message error-message'>Could not verify product stock. Please try again.</div>";
        $action = null; // Prevent further processing
    }

    if ($action) { // Re-check action as it might have been nullified
        $current_quantity_for_action = is_numeric($quantity_request) ? (int)$quantity_request : 1;

        switch ($action) {
            case 'add':
                if ($current_quantity_for_action <= 0) {
                    $cart_action_message = "<div class='form-message error-message'>Quantity must be at least 1.</div>";
                } elseif ($is_simple_product_with_stock_db && $current_quantity_for_action > $stock_quantity_db && $stock_quantity_db > 0) {
                     $cart_action_message = "<div class='form-message warning-message'>Cannot add {$current_quantity_for_action} of '{$product_name_for_message_db}'. Only {$stock_quantity_db} available. Max quantity added.</div>";
                     $current_quantity_for_action = $stock_quantity_db;
                } elseif ($is_simple_product_with_stock_db && $stock_quantity_db <= 0) {
                    $cart_action_message = "<div class='form-message error-message'>Sorry, '{$product_name_for_message_db}' is out of stock.</div>";
                    break; 
                }

                if (!($is_simple_product_with_stock_db && $stock_quantity_db <= 0 && $current_quantity_for_action > 0) ) {
                    if (isset($_SESSION['cart'][$product_id])) {
                        $new_quantity_in_cart = $_SESSION['cart'][$product_id] + $current_quantity_for_action;
                        if ($is_simple_product_with_stock_db && $new_quantity_in_cart > $stock_quantity_db && $stock_quantity_db > 0) {
                            $_SESSION['cart'][$product_id] = $stock_quantity_db;
                             $cart_action_message = "<div class='form-message warning-message'>Total quantity for '{$product_name_for_message_db}' in cart exceeds stock. Adjusted to {$stock_quantity_db}.</div>";
                        } else {
                            $_SESSION['cart'][$product_id] = $new_quantity_in_cart;
                            if(empty($cart_action_message)) $cart_action_message = "<div class='form-message success-message'>'{$product_name_for_message_db}' quantity updated in cart.</div>";
                        }
                    } else {
                        $_SESSION['cart'][$product_id] = $current_quantity_for_action;
                        if(empty($cart_action_message)) $cart_action_message = "<div class='form-message success-message'>'{$product_name_for_message_db}' added to cart.</div>";
                    }
                }
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Redirect only if action was POST to prevent refresh issues
                    header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?action_status=added&product_name=" . urlencode($product_name_for_message_db));
                    exit;
                }
                break;

            case 'update': // For individual item updates from cart page (e.g. if you had separate update buttons per item)
                $quantity_to_update_item = is_numeric($quantity_request) ? (int)$quantity_request : 0;

                if ($quantity_to_update_item > 0) {
                    if ($is_simple_product_with_stock_db && $quantity_to_update_item > $stock_quantity_db) {
                        $_SESSION['cart'][$product_id] = $stock_quantity_db; 
                        $cart_action_message = "<div class='form-message warning-message'>Quantity for '{$product_name_for_message_db}' updated to max available stock: {$stock_quantity_db}.</div>";
                    } else {
                        $_SESSION['cart'][$product_id] = $quantity_to_update_item;
                        $cart_action_message = "<div class='form-message success-message'>Cart updated for '{$product_name_for_message_db}'.</div>";
                    }
                } else { 
                    unset($_SESSION['cart'][$product_id]);
                    $cart_action_message = "<div class='form-message success-message'>'{$product_name_for_message_db}' removed from cart.</div>";
                }
                header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?action_status=item_updated&product_name=" . urlencode($product_name_for_message_db));
                exit;
                break;

            case 'remove':
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]);
                    $cart_action_message = "<div class='form-message success-message'>'{$product_name_for_message_db}' removed from cart.</div>";
                }
                header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?action_status=removed&product_name=" . urlencode($product_name_for_message_db));
                exit;
                break;
        }
    }
} elseif ($action === 'update_all' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Handle "Update Cart" button
    if (isset($quantity_request) && is_array($quantity_request)) { // $quantity_request is $_POST['quantity']
        $all_updates_successful = true;
        $update_messages = [];

        foreach ($quantity_request as $pid_from_form => $qty_from_form) {
            $pid = (int)$pid_from_form;
            $new_qty = (int)$qty_from_form;

            $current_product_stock_ua = 9999;
            $current_product_is_simple_with_stock_ua = false;
            $current_product_name_ua = "Product ID {$pid}";
            try {
                $stmt_prod_info_ua = $db->prepare("SELECT product_name, stock_quantity, requires_variants FROM products WHERE product_id = :pid AND is_active = 1");
                $stmt_prod_info_ua->bindParam(':pid', $pid, PDO::PARAM_INT);
                $stmt_prod_info_ua->execute();
                $prod_info_ua = $stmt_prod_info_ua->fetch(PDO::FETCH_ASSOC);
                if ($prod_info_ua) {
                    $current_product_name_ua = htmlspecialchars($prod_info_ua['product_name']);
                    if ($prod_info_ua['requires_variants'] == 0 && isset($prod_info_ua['stock_quantity'])) {
                        $current_product_stock_ua = (int)$prod_info_ua['stock_quantity'];
                        $current_product_is_simple_with_stock_ua = true;
                    }
                } else {
                    unset($_SESSION['cart'][$pid]); 
                    $update_messages[] = "Item '{$current_product_name_ua}' no longer available and removed.";
                    continue;
                }
            } catch (PDOException $e) {
                error_log("Update All - Stock Check Error (PID: {$pid}): " . $e->getMessage());
                $update_messages[] = "Could not verify stock for '{$current_product_name_ua}'.";
                $all_updates_successful = false;
                continue;
            }

            if ($new_qty > 0) {
                if ($current_product_is_simple_with_stock_ua && $new_qty > $current_product_stock_ua) {
                    $_SESSION['cart'][$pid] = $current_product_stock_ua;
                    $update_messages[] = "Quantity for '{$current_product_name_ua}' adjusted to max stock: {$current_product_stock_ua}.";
                } else {
                    $_SESSION['cart'][$pid] = $new_qty;
                }
            } else { // If new quantity is 0 or less, remove from cart
                unset($_SESSION['cart'][$pid]);
                $update_messages[] = "'{$current_product_name_ua}' removed from cart.";
            }
        }
        if (empty($update_messages) && $all_updates_successful) {
            $cart_action_message = "<div class='form-message success-message'>Cart updated successfully.</div>";
        } elseif (!empty($update_messages)) {
            $message_type = $all_updates_successful ? 'warning-message' : 'error-message'; 
            $cart_action_message = "<div class='form-message {$message_type}'>Cart update status:<ul>";
            foreach($update_messages as $msg) {
                $cart_action_message .= "<li>" . htmlspecialchars($msg) . "</li>";
            }
            $cart_action_message .= "</ul></div>";
        }
    }
    // No redirect here for 'update_all', show messages on the same page after processing
}


// Display messages from GET parameters (after redirects from single item actions)
if(isset($_GET['action_status']) && empty($cart_action_message)){ // Only if no message is already set
    $status_product_name_get = isset($_GET['product_name']) ? htmlspecialchars(urldecode($_GET['product_name'])) : "Item";
    switch($_GET['action_status']) {
        case 'added': $cart_action_message = "<div class='form-message success-message'>'{$status_product_name_get}' added/updated in cart.</div>"; break;
        case 'item_updated': $cart_action_message = "<div class='form-message success-message'>Cart updated for '{$status_product_name_get}'.</div>"; break;
        case 'removed': $cart_action_message = "<div class='form-message success-message'>'{$status_product_name_get}' removed from cart.</div>"; break;
    }
}


// --- Fetch Product Details for Cart Items ---
if (!empty($_SESSION['cart']) && $db) {
    $product_ids_in_cart = array_keys($_SESSION['cart']);
    if (!empty($product_ids_in_cart)) {
        $placeholders = implode(',', array_fill(0, count($product_ids_in_cart), '?'));
        $sql_fetch_cart_items = "
            SELECT product_id, product_name, price, main_image_url, stock_quantity, requires_variants
            FROM products 
            WHERE product_id IN ($placeholders) AND is_active = 1
        ";
        try {
            $stmt_fetch_cart_items = $db->prepare($sql_fetch_cart_items);
            $stmt_fetch_cart_items->execute($product_ids_in_cart);
            $fetched_products_data = $stmt_fetch_cart_items->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE); 

            $temp_cart_validation_messages = []; 

            foreach ($_SESSION['cart'] as $pid_session => $qty_session) {
                if (isset($fetched_products_data[$pid_session])) {
                    $product_data_session = $fetched_products_data[$pid_session];
                    $current_product_name_display_session = htmlspecialchars($product_data_session['product_name']);

                    // Final stock validation and quantity adjustment before display
                    if ($product_data_session['requires_variants'] == 0 && isset($product_data_session['stock_quantity'])) {
                        $current_stock_session = (int)$product_data_session['stock_quantity'];
                        if ($current_stock_session <= 0) { 
                            unset($_SESSION['cart'][$pid_session]); 
                            $temp_cart_validation_messages[] = "Item '{$current_product_name_display_session}' became out of stock and has been removed.";
                            continue; 
                        }
                        if ($qty_session > $current_stock_session) {
                            $qty_session = $current_stock_session; 
                            $_SESSION['cart'][$pid_session] = $qty_session; 
                            $temp_cart_validation_messages[] = "Quantity for '{$current_product_name_display_session}' was adjusted to available stock: {$qty_session}.";
                        }
                    }

                    $line_total_session = $product_data_session['price'] * $qty_session;
                    $cart_items_details[] = [
                        'id' => $pid_session,
                        'name' => $product_data_session['product_name'],
                        'price' => $product_data_session['price'],
                        'quantity' => $qty_session,
                        'image_url' => $product_data_session['main_image_url'],
                        'line_total' => $line_total_session,
                        'stock_quantity' => ($product_data_session['requires_variants'] == 0 && isset($product_data_session['stock_quantity'])) ? $product_data_session['stock_quantity'] : 9999,
                        'requires_variants' => $product_data_session['requires_variants']
                    ];
                    $cart_subtotal += $line_total_session;
                    $cart_total_items += $qty_session;
                } else { // Product ID from session not found in DB (e.g., product deleted or made inactive)
                    unset($_SESSION['cart'][$pid_session]); 
                    $temp_cart_validation_messages[] = "An item (ID: {$pid_session}) in your cart is no longer available and has been removed.";
                }
            }
            // Append validation messages if any
            if (!empty($temp_cart_validation_messages)) {
                $cart_action_message .= "<div class='form-message warning-message'>Important Cart Updates:<ul>";
                foreach($temp_cart_validation_messages as $msg_val) { $cart_action_message .= "<li>" . htmlspecialchars($msg_val) . "</li>"; }
                $cart_action_message .= "</ul></div>";
            }

        } catch (PDOException $e) {
            error_log("Error fetching cart item details: " . $e->getMessage());
            $cart_action_message = "<div class='form-message error-message'>Could not load cart details. Please try again.</div>";
            $_SESSION['cart'] = []; // Clear cart on error to prevent issues
            $cart_items_details = []; // Ensure it's empty for display
            $cart_subtotal = 0;
            $cart_total_items = 0;
        }
    }
}

// Update header cart count (total quantity of items)
$_SESSION['header_cart_item_count'] = $cart_total_items;

$grand_total = $cart_subtotal; // For now, no taxes or shipping on cart page

?>

<section class="cart-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Your Shopping Cart</h2>

        <?php if (!empty($cart_action_message)): ?>
            <?php echo $cart_action_message; /* Message is already wrapped in div with appropriate class */ ?>
        <?php endif; ?>

        <?php if (empty($cart_items_details)): ?>
            <div class="cart-empty text-center" style="padding-top: 30px; padding-bottom: 30px;"> 
                <h3>Your cart is currently empty.</h3>
                <p style="margin-top:15px; margin-bottom:25px;">Looks like you haven't added any products yet. Start exploring!</p>
                <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-primary btn-lg">Shop Products</a>
            </div>
        <?php else: ?>
            <form action="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php" method="POST" class="cart-form">
                <input type="hidden" name="action" value="update_all">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="text-left">Product</th>
                            <th class="text-right">Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items_details as $item): ?>
                            <tr>
                                <td class="cart-item-image" data-label="Product Image">
                                    <?php
                                        $item_image_url_display = "https://placehold.co/80x80/F0F0F0/AAA?text=No+Image";
                                        if (!empty($item['image_url'])) {
                                            if (strpos($item['image_url'], 'http://') === 0 || strpos($item['image_url'], 'https://') === 0) {
                                                $item_image_url_display = htmlspecialchars($item['image_url']);
                                            } else {
                                                $item_image_url_display = rtrim(SITE_URL, '/') . '/' . ltrim(htmlspecialchars($item['image_url']), '/');
                                            }
                                        }
                                    ?>
                                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/product_detail.php?id=<?php echo $item['id']; ?>">
                                        <img src="<?php echo $item_image_url_display; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             onerror="this.onerror=null;this.src='https://placehold.co/80x80/CCC/777?text=Error';">
                                    </a>
                                </td>
                                <td class="cart-item-name" data-label="Product">
                                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/product_detail.php?id=<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                     <?php if ($item['requires_variants'] == 1): ?>
                                        <small class="text-muted d-block">(Variant details TBD)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="cart-item-price text-right" data-label="Price"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($item['price'], 2)); ?></td>
                                <td class="cart-item-quantity text-center" data-label="Quantity">
                                    <input type="number" name="quantity[<?php echo $item['id']; ?>]" 
                                           value="<?php echo htmlspecialchars($item['quantity']); ?>" 
                                           min="0" 
                                           max="<?php echo htmlspecialchars($item['stock_quantity']); ?>"
                                           class="form-control form-control-sm quantity-input" 
                                           aria-label="Quantity for <?php echo htmlspecialchars($item['name']); ?>">
                                </td>
                                <td class="cart-item-line-total text-right" data-label="Total"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($item['line_total'], 2)); ?></td>
                                <td class="cart-item-remove text-center" data-label="Remove">
                                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php?action=remove&product_id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-danger remove-item-btn"
                                       aria-label="Remove <?php echo htmlspecialchars($item['name']); ?>"
                                       onclick="return confirm('Are you sure you want to remove this item: \'<?php echo htmlspecialchars(addslashes($item['name'])); ?>\' from your cart?');">
                                       &times;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div class="cart-actions-bar">
                         <a href="<?php echo rtrim(SITE_URL, '/'); ?>/products.php" class="btn btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="margin-right:5px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg>
                            Continue Shopping
                        </a>
                        <button type="submit" name="update_cart_all_btn" class="btn btn-info">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16" style="margin-right:5px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/></svg>
                            Update Cart
                        </button>
                    </div>
                    <div class="cart-totals">
                        <p><strong>Subtotal:</strong> <span class="float-right"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($cart_subtotal, 2)); ?></span></p>
                        <p><strong>Items in Cart:</strong> <span class="float-right"><?php echo $cart_total_items; ?></span></p>
                        <hr>
                        <p class="grand-total"><strong>Grand Total:</strong> <span class="float-right"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($grand_total, 2)); ?></span></p>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/checkout.php" class="btn btn-primary btn-lg btn-block mt-3">
                            Proceed to Checkout
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-arrow-right-circle-fill" viewBox="0 0 16 16" style="margin-left:8px; vertical-align: text-bottom;">
                                <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0M4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </form>
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

<script>
// No specific JS needed for this version of cart.php as updates are full form submits.
document.addEventListener('DOMContentLoaded', function() {
    // Any general cart page JS can go here if needed in the future.
});
</script>
