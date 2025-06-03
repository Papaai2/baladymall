<?php
// public/checkout.php

$page_title = "Checkout - BaladyMall";

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
$errors = [];
$checkout_message = ''; // For success/error messages

// --- 1. Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    // Store the intended destination to redirect after login
    $_SESSION['redirect_after_login'] = SITE_URL . "/checkout.php";
    header("Location: " . rtrim(SITE_URL, '/') . "/login.php?auth=required&checkout=true");
    exit;
}
$user_id = $_SESSION['user_id'];

// --- 2. Cart Validation ---
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=empty_checkout_attempt");
    exit;
}

// --- Fetch Cart Item Details for Summary ---
$cart_items_details_checkout = [];
$cart_subtotal_checkout = 0;
$cart_total_items_checkout = 0;

if (!empty($_SESSION['cart']) && $db) {
    $product_ids_in_cart_checkout = array_keys($_SESSION['cart']);
    if (!empty($product_ids_in_cart_checkout)) {
        $placeholders_checkout = implode(',', array_fill(0, count($product_ids_in_cart_checkout), '?'));
        $sql_fetch_cart_items_checkout = "
            SELECT product_id, product_name, price, main_image_url, stock_quantity, requires_variants, brand_id
            FROM products 
            WHERE product_id IN ($placeholders_checkout) AND is_active = 1
        ";
        try {
            $stmt_fetch_cart_items_checkout = $db->prepare($sql_fetch_cart_items_checkout);
            $stmt_fetch_cart_items_checkout->execute($product_ids_in_cart_checkout);
            $fetched_products_data_checkout = $stmt_fetch_cart_items_checkout->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

            $validation_messages_checkout = [];
            foreach ($_SESSION['cart'] as $pid_co => $qty_co) {
                if (isset($fetched_products_data_checkout[$pid_co])) {
                    $product_data_co = $fetched_products_data_checkout[$pid_co];
                    $product_name_co_display = htmlspecialchars($product_data_co['product_name']);

                    if ($product_data_co['requires_variants'] == 0 && isset($product_data_co['stock_quantity'])) {
                        $current_stock_co = (int)$product_data_co['stock_quantity'];
                        if ($current_stock_co < $qty_co) { // Not enough stock
                            // This should ideally be handled more gracefully, e.g., redirect back to cart with message
                            $errors['cart_stock'] = "Item '{$product_name_co_display}' has insufficient stock ({$current_stock_co} available). Please update your cart.";
                            // For now, we'll just add an error. A robust system might redirect.
                            // To prevent order, ensure this error blocks form submission.
                        }
                        if ($current_stock_co <= 0) {
                             $errors['cart_stock'] = "Item '{$product_name_co_display}' is out of stock. Please remove it from your cart.";
                        }
                    }

                    $line_total_co = $product_data_co['price'] * $qty_co;
                    $cart_items_details_checkout[] = [
                        'id' => $pid_co,
                        'name' => $product_data_co['product_name'],
                        'price' => $product_data_co['price'],
                        'quantity' => $qty_co,
                        'image_url' => $product_data_co['main_image_url'],
                        'line_total' => $line_total_co,
                        'brand_id' => $product_data_co['brand_id'] // Needed for order_items
                    ];
                    $cart_subtotal_checkout += $line_total_co;
                    $cart_total_items_checkout += $qty_co;
                } else {
                    $errors['cart_availability'] = "An item previously in your cart is no longer available. Please review your cart.";
                }
            }
        } catch (PDOException $e) {
            error_log("Checkout - Error fetching cart item details: " . $e->getMessage());
            $errors['database'] = "Could not load cart details for checkout. Please try again.";
        }
    }
     // If errors occurred during cart fetching for checkout, redirect to cart page
    if (!empty($errors['cart_stock']) || !empty($errors['cart_availability']) || !empty($errors['database'])) {
        $_SESSION['checkout_error_message'] = $errors['cart_stock'] ?? ($errors['cart_availability'] ?? $errors['database']);
        header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=checkout_error");
        exit;
    }
}


// --- Shipping Information ---
// Fetch user's default shipping address
$user_shipping_info = [
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'phone_number' => $_SESSION['phone_number'] ?? '',
    'shipping_address_line1' => '',
    'shipping_address_line2' => '',
    'shipping_city' => '',
    'shipping_governorate' => '',
    'shipping_postal_code' => '',
    'shipping_country' => 'Egypt' // Default
];

try {
    $stmt_user_addr = $db->prepare("SELECT first_name, last_name, phone_number, shipping_address_line1, shipping_address_line2, shipping_city, shipping_governorate, shipping_postal_code, shipping_country FROM users WHERE user_id = :user_id");
    $stmt_user_addr->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_user_addr->execute();
    $db_user_info = $stmt_user_addr->fetch(PDO::FETCH_ASSOC);
    if ($db_user_info) {
        $user_shipping_info['first_name'] = $db_user_info['first_name'] ?: $user_shipping_info['first_name'];
        $user_shipping_info['last_name'] = $db_user_info['last_name'] ?: $user_shipping_info['last_name'];
        $user_shipping_info['phone_number'] = $db_user_info['phone_number'] ?: $user_shipping_info['phone_number'];
        $user_shipping_info['shipping_address_line1'] = $db_user_info['shipping_address_line1'] ?? '';
        $user_shipping_info['shipping_address_line2'] = $db_user_info['shipping_address_line2'] ?? '';
        $user_shipping_info['shipping_city'] = $db_user_info['shipping_city'] ?? '';
        $user_shipping_info['shipping_governorate'] = $db_user_info['shipping_governorate'] ?? '';
        $user_shipping_info['shipping_postal_code'] = $db_user_info['shipping_postal_code'] ?? '';
        $user_shipping_info['shipping_country'] = $db_user_info['shipping_country'] ?: 'Egypt';
    }
} catch (PDOException $e) {
    error_log("Checkout - Error fetching user address: " . $e->getMessage());
    $errors['address_fetch'] = "Could not load your saved address. Please enter it manually.";
}

// --- Totals ---
// For now, simple totals. Shipping and taxes can be added later.
$shipping_amount_checkout = 0.00; // Placeholder
$tax_amount_checkout = 0.00;      // Placeholder
$grand_total_checkout = $cart_subtotal_checkout + $shipping_amount_checkout + $tax_amount_checkout;


// --- Handle Order Placement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    // Sanitize and validate shipping inputs from POST
    $shipping_name_post = trim(filter_input(INPUT_POST, 'shipping_name', FILTER_UNSAFE_RAW)); // Full name
    $shipping_phone_post = trim(filter_input(INPUT_POST, 'shipping_phone', FILTER_UNSAFE_RAW));
    $shipping_address1_post = trim(filter_input(INPUT_POST, 'shipping_address_line1', FILTER_UNSAFE_RAW));
    $shipping_address2_post = trim(filter_input(INPUT_POST, 'shipping_address_line2', FILTER_UNSAFE_RAW));
    $shipping_city_post = trim(filter_input(INPUT_POST, 'shipping_city', FILTER_UNSAFE_RAW));
    $shipping_governorate_post = trim(filter_input(INPUT_POST, 'shipping_governorate', FILTER_UNSAFE_RAW));
    $shipping_postal_code_post = trim(filter_input(INPUT_POST, 'shipping_postal_code', FILTER_UNSAFE_RAW));
    $shipping_country_post = trim(filter_input(INPUT_POST, 'shipping_country', FILTER_UNSAFE_RAW)) ?: 'Egypt';
    $payment_method_post = trim(filter_input(INPUT_POST, 'payment_method', FILTER_UNSAFE_RAW));
    $notes_to_seller_post = trim(filter_input(INPUT_POST, 'notes_to_seller', FILTER_UNSAFE_RAW));


    // Validation for POSTed data
    if (empty($shipping_name_post)) $errors['shipping_name'] = "Full name for shipping is required.";
    if (empty($shipping_phone_post)) $errors['shipping_phone'] = "Shipping phone number is required.";
    elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $shipping_phone_post)) $errors['shipping_phone'] = "Invalid shipping phone number format.";
    if (empty($shipping_address1_post)) $errors['shipping_address_line1'] = "Shipping Address Line 1 is required.";
    if (empty($shipping_city_post)) $errors['shipping_city'] = "Shipping city is required.";
    if (empty($shipping_governorate_post)) $errors['shipping_governorate'] = "Shipping governorate is required.";
    if (empty($payment_method_post)) $errors['payment_method'] = "Please select a payment method.";
    elseif ($payment_method_post !== 'cod') $errors['payment_method'] = "Invalid payment method selected."; // Only COD for now

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // 1. Create Order
            $sql_insert_order = "INSERT INTO orders (customer_id, order_status, total_amount, subtotal_amount, shipping_amount, tax_amount, discount_amount, 
                                shipping_name, shipping_phone, shipping_address_line1, shipping_address_line2, 
                                shipping_city, shipping_governorate, shipping_postal_code, shipping_country, 
                                payment_method, notes_to_seller) 
                                VALUES (:customer_id, :order_status, :total_amount, :subtotal_amount, :shipping_amount, :tax_amount, :discount_amount,
                                :s_name, :s_phone, :s_addr1, :s_addr2, :s_city, :s_gov, :s_zip, :s_country, 
                                :pay_method, :notes)";
            $stmt_insert_order = $db->prepare($sql_insert_order);
            $order_status = ($payment_method_post === 'cod') ? 'processing' : 'pending_payment';
            
            $stmt_insert_order->execute([
                ':customer_id' => $user_id,
                ':order_status' => $order_status,
                ':total_amount' => $grand_total_checkout,
                ':subtotal_amount' => $cart_subtotal_checkout,
                ':shipping_amount' => $shipping_amount_checkout,
                ':tax_amount' => $tax_amount_checkout,
                ':discount_amount' => 0.00, // Placeholder
                ':s_name' => $shipping_name_post,
                ':s_phone' => $shipping_phone_post,
                ':s_addr1' => $shipping_address1_post,
                ':s_addr2' => $shipping_address2_post,
                ':s_city' => $shipping_city_post,
                ':s_gov' => $shipping_governorate_post,
                ':s_zip' => $shipping_postal_code_post,
                ':s_country' => $shipping_country_post,
                ':pay_method' => $payment_method_post,
                ':notes' => $notes_to_seller_post
            ]);
            $new_order_id = $db->lastInsertId();

            // 2. Create Order Items & Update Stock
            $sql_insert_order_item = "INSERT INTO order_items (order_id, product_id, variant_id, brand_id, quantity, price_at_purchase, subtotal_for_item) 
                                      VALUES (:order_id, :product_id, NULL, :brand_id, :quantity, :price, :subtotal)"; // variant_id is NULL for now
            $stmt_insert_order_item = $db->prepare($sql_insert_order_item);

            $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id AND requires_variants = 0 AND stock_quantity IS NOT NULL";
            $stmt_update_stock = $db->prepare($sql_update_stock);

            foreach ($cart_items_details_checkout as $item_co) {
                $stmt_insert_order_item->execute([
                    ':order_id' => $new_order_id,
                    ':product_id' => $item_co['id'],
                    ':brand_id' => $item_co['brand_id'],
                    ':quantity' => $item_co['quantity'],
                    ':price' => $item_co['price'],
                    ':subtotal' => $item_co['line_total']
                ]);

                // Update stock for simple products
                $stmt_update_stock->execute([
                    ':quantity' => $item_co['quantity'],
                    ':product_id' => $item_co['id']
                ]);
            }

            $db->commit();

            // 3. Clear Cart & Redirect to Success Page
            unset($_SESSION['cart']);
            unset($_SESSION['header_cart_item_count']); // Clear specific count too
            // Store order ID in session for success page to pick up
            $_SESSION['last_order_id'] = $new_order_id; 
            header("Location: " . rtrim(SITE_URL, '/') . "/order_success.php");
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Checkout - Order Placement Error: " . $e->getMessage());
            $errors['database'] = "An error occurred while placing your order. Please try again. Details: " . $e->getMessage();
        }
    }
}
// If form was submitted but had errors, repopulate POSTed shipping values for the form
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($errors)) {
    $user_shipping_info['shipping_name_form'] = $shipping_name_post ?? ($user_shipping_info['first_name'] . ' ' . $user_shipping_info['last_name']);
    $user_shipping_info['phone_number'] = $shipping_phone_post ?? $user_shipping_info['phone_number'];
    $user_shipping_info['shipping_address_line1'] = $shipping_address1_post ?? $user_shipping_info['shipping_address_line1'];
    $user_shipping_info['shipping_address_line2'] = $shipping_address2_post ?? $user_shipping_info['shipping_address_line2'];
    $user_shipping_info['shipping_city'] = $shipping_city_post ?? $user_shipping_info['shipping_city'];
    $user_shipping_info['shipping_governorate'] = $shipping_governorate_post ?? $user_shipping_info['shipping_governorate'];
    $user_shipping_info['shipping_postal_code'] = $shipping_postal_code_post ?? $user_shipping_info['shipping_postal_code'];
    $user_shipping_info['shipping_country'] = $shipping_country_post ?? $user_shipping_info['shipping_country'];
    $user_shipping_info['notes_to_seller'] = $notes_to_seller_post ?? '';
} else {
    // For initial load, construct full name for shipping name field
     $user_shipping_info['shipping_name_form'] = trim($user_shipping_info['first_name'] . ' ' . $user_shipping_info['last_name']);
}


?>

<section class="checkout-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Checkout</h2>

        <?php if (!empty($errors['database'])): ?>
            <div class="form-message error-message"><?php echo htmlspecialchars($errors['database']); ?></div>
        <?php endif; ?>
         <?php if (!empty($errors['address_fetch'])): ?>
            <div class="form-message warning-message"><?php echo htmlspecialchars($errors['address_fetch']); ?></div>
        <?php endif; ?>


        <div class="checkout-layout">
            <div class="checkout-form-container">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="checkout-form auth-form" novalidate>
                    <fieldset>
                        <legend>Shipping Information</legend>
                        <div class="form-group">
                            <label for="shipping_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo htmlspecialchars($user_shipping_info['shipping_name_form']); ?>" required>
                            <?php if (isset($errors['shipping_name'])): ?><span class="error-text"><?php echo $errors['shipping_name']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="shipping_phone" name="shipping_phone" value="<?php echo htmlspecialchars($user_shipping_info['phone_number']); ?>" required placeholder="+201XXXXXXXXX">
                            <?php if (isset($errors['shipping_phone'])): ?><span class="error-text"><?php echo $errors['shipping_phone']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_line1">Address Line 1 <span class="required">*</span></label>
                            <input type="text" id="shipping_address_line1" name="shipping_address_line1" value="<?php echo htmlspecialchars($user_shipping_info['shipping_address_line1']); ?>" required>
                            <?php if (isset($errors['shipping_address_line1'])): ?><span class="error-text"><?php echo $errors['shipping_address_line1']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_line2">Address Line 2 (Optional)</label>
                            <input type="text" id="shipping_address_line2" name="shipping_address_line2" value="<?php echo htmlspecialchars($user_shipping_info['shipping_address_line2']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="shipping_city">City <span class="required">*</span></label>
                            <input type="text" id="shipping_city" name="shipping_city" value="<?php echo htmlspecialchars($user_shipping_info['shipping_city']); ?>" required>
                            <?php if (isset($errors['shipping_city'])): ?><span class="error-text"><?php echo $errors['shipping_city']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_governorate">Governorate <span class="required">*</span></label>
                            <input type="text" id="shipping_governorate" name="shipping_governorate" value="<?php echo htmlspecialchars($user_shipping_info['shipping_governorate']); ?>" required>
                            <?php if (isset($errors['shipping_governorate'])): ?><span class="error-text"><?php echo $errors['shipping_governorate']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_postal_code">Postal Code (Optional)</label>
                            <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="<?php echo htmlspecialchars($user_shipping_info['shipping_postal_code']); ?>">
                        </div>
                         <div class="form-group">
                            <label for="shipping_country">Country <span class="required">*</span></label>
                            <input type="text" id="shipping_country" name="shipping_country" value="<?php echo htmlspecialchars($user_shipping_info['shipping_country']); ?>" required>
                             <?php if (isset($errors['shipping_country'])): ?><span class="error-text"><?php echo $errors['shipping_country']; ?></span><?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Payment Method</legend>
                        <div class="form-group">
                            <label for="payment_method_cod">
                                <input type="radio" id="payment_method_cod" name="payment_method" value="cod" checked required> 
                                Cash on Delivery (COD)
                            </label>
                            <?php if (isset($errors['payment_method'])): ?><span class="error-text d-block"><?php echo $errors['payment_method']; ?></span><?php endif; ?>
                            <small class="form-text text-muted">Other payment methods will be available soon.</small>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Order Notes (Optional)</legend>
                        <div class="form-group">
                            <label for="notes_to_seller">Special instructions for your order?</label>
                            <textarea id="notes_to_seller" name="notes_to_seller" rows="3" class="form-control"><?php echo htmlspecialchars($user_shipping_info['notes_to_seller'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>


                    <div class="form-group mt-4">
                        <button type="submit" name="place_order" class="btn btn-primary btn-lg btn-block">Place Order</button>
                    </div>
                </form>
            </div>

            <div class="checkout-summary-container">
                <div class="order-summary-box">
                    <h4>Order Summary</h4>
                    <?php if (!empty($cart_items_details_checkout)): ?>
                        <ul class="order-summary-items">
                        <?php foreach ($cart_items_details_checkout as $item_sum): ?>
                            <li>
                                <span class="item-name"><?php echo htmlspecialchars($item_sum['name']); ?> (x<?php echo $item_sum['quantity']; ?>)</span>
                                <span class="item-price"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($item_sum['line_total'], 2)); ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <hr>
                        <p><strong>Subtotal:</strong> <span><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($cart_subtotal_checkout, 2)); ?></span></p>
                        <p>Shipping: <span class="text-muted">Calculated at next step (Free for now)</span></p>
                        <p>Taxes: <span class="text-muted">Calculated at next step (None for now)</span></p>
                        <hr>
                        <p class="grand-total"><strong>Total:</strong> <strong><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($grand_total_checkout, 2)); ?></strong></p>
                    <?php else: ?>
                        <p>Your cart is empty.</p>
                    <?php endif; ?>
                </div>
                <p class="text-center mt-3"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php">&laquo; Return to Cart</a></p>
            </div>
        </div>
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
