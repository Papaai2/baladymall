<?php
// public/checkout.php

$page_title = "Checkout - BaladyMall";

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
if (!isset($db) || !$db instanceof PDO) {
    if (function_exists('getPDOConnection')) {
        $db = getPDOConnection();
    }
    if (!isset($db) || !$db instanceof PDO) {
        // Critical for checkout, set an error and potentially prevent form display or processing
        $errors['database'] = "Database connection is not available. Cannot proceed with checkout.";
    }
}

$errors = $errors ?? []; // Initialize if not already set (e.g. by DB check)
$checkout_message = ''; // For general messages

// --- 1. Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = rtrim(SITE_URL, '/') . "/checkout.php";
    header("Location: " . rtrim(SITE_URL, '/') . "/login.php?auth=required&checkout=true");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? ''; // Get user email for order confirmation
$user_first_name = $_SESSION['first_name'] ?? ''; // Get user first name for email

// --- 2. Cart Validation ---
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    // Redirect to cart page with a message if trying to access checkout with an empty cart
    $_SESSION['cart_message'] = "<div class='form-message info-message'>Your cart is empty. Please add items before proceeding to checkout.</div>";
    header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=empty_checkout_attempt");
    exit;
}

// --- Fetch Cart Item Details for Summary & Validation ---
$cart_items_details_checkout = [];
$cart_subtotal_checkout = 0;
$cart_total_items_checkout = 0;

if (empty($errors) && isset($db) && $db instanceof PDO) { // Proceed only if no critical DB error
    $product_ids_in_cart_checkout = array_keys($_SESSION['cart']);
    if (!empty($product_ids_in_cart_checkout)) {
        $product_ids_in_cart_checkout = array_map('intval', $product_ids_in_cart_checkout);
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

            foreach ($_SESSION['cart'] as $pid_co => $qty_co) {
                $pid_co_int = (int)$pid_co;
                if (isset($fetched_products_data_checkout[$pid_co_int])) {
                    $product_data_co = $fetched_products_data_checkout[$pid_co_int];
                    $product_name_co_display = esc_html($product_data_co['product_name']);

                    if ($product_data_co['requires_variants'] == 0 && isset($product_data_co['stock_quantity'])) {
                        $current_stock_co = (int)$product_data_co['stock_quantity'];
                        if ($current_stock_co < $qty_co) {
                            $errors['cart_stock'][$pid_co_int] = "Item '{$product_name_co_display}' has insufficient stock ({$current_stock_co} available). Please update your cart.";
                        }
                        if ($current_stock_co <= 0 && $qty_co > 0) { // Ensure item with 0 stock but in cart is flagged
                             $errors['cart_stock'][$pid_co_int] = "Item '{$product_name_co_display}' is out of stock. Please remove it from your cart.";
                        }
                    }

                    $line_total_co = $product_data_co['price'] * $qty_co;
                    $cart_items_details_checkout[] = [
                        'id' => $pid_co_int,
                        'name' => $product_data_co['product_name'],
                        'price' => $product_data_co['price'],
                        'quantity' => $qty_co,
                        'image_url' => $product_data_co['main_image_url'],
                        'line_total' => $line_total_co,
                        'brand_id' => $product_data_co['brand_id']
                    ];
                    $cart_subtotal_checkout += $line_total_co;
                    $cart_total_items_checkout += $qty_co;
                } else {
                    $errors['cart_availability'][$pid_co_int] = "An item (ID: {$pid_co_int}) previously in your cart is no longer available. Please review your cart.";
                }
            }
        } catch (PDOException $e) {
            error_log("Checkout - Error fetching cart item details: " . $e->getMessage());
            $errors['database'] = "Could not load cart details for checkout. Please try again.";
        }
    }

    // If cart validation errors occurred, build a message and redirect to cart page
    if (!empty($errors['cart_stock']) || !empty($errors['cart_availability'])) {
        $error_message_for_cart = "Please review your cart:<ul>";
        if (!empty($errors['cart_stock'])) {
            foreach($errors['cart_stock'] as $err_msg) $error_message_for_cart .= "<li>" . esc_html($err_msg) . "</li>";
        }
        if (!empty($errors['cart_availability'])) {
             foreach($errors['cart_availability'] as $err_msg) $error_message_for_cart .= "<li>" . esc_html($err_msg) . "</li>";
        }
        $error_message_for_cart .= "</ul>";
        $_SESSION['cart_message'] = "<div class='form-message error-message'>{$error_message_for_cart}</div>";
        header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=checkout_validation_error");
        exit;
    }
}


// --- Shipping Information ---
$user_shipping_info = [
    'shipping_name_form' => '', // Combined first and last name for the form field
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'phone_number' => $_SESSION['phone_number'] ?? '',
    'shipping_address_line1' => '',
    'shipping_address_line2' => '',
    'shipping_city' => '',
    'shipping_governorate' => '',
    'shipping_postal_code' => '',
    'shipping_country' => 'Egypt',
    'notes_to_seller' => ''
];

if (empty($errors) && isset($db) && $db instanceof PDO) { // Proceed only if no critical DB error
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
}
// Populate form shipping name from fetched first/last name
$user_shipping_info['shipping_name_form'] = trim($user_shipping_info['first_name'] . ' ' . $user_shipping_info['last_name']);


// --- Totals ---
$shipping_amount_checkout = 0.00; // Placeholder, implement actual shipping calculation later
$tax_amount_checkout = 0.00;      // Placeholder
$discount_amount_checkout = 0.00; // Placeholder for future coupon/discount logic
$grand_total_checkout = $cart_subtotal_checkout + $shipping_amount_checkout + $tax_amount_checkout - $discount_amount_checkout;


// --- Handle Order Placement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    if (!empty($errors)) { // If there were critical errors before form submission (e.g. DB down, cart validation)
        // Do not proceed with order placement. Errors are already set.
    } else {
        $shipping_name_post = trim(filter_input(INPUT_POST, 'shipping_name', FILTER_UNSAFE_RAW));
        $shipping_phone_post = trim(filter_input(INPUT_POST, 'shipping_phone', FILTER_UNSAFE_RAW));
        $shipping_address1_post = trim(filter_input(INPUT_POST, 'shipping_address_line1', FILTER_UNSAFE_RAW));
        $shipping_address2_post = trim(filter_input(INPUT_POST, 'shipping_address_line2', FILTER_UNSAFE_RAW));
        $shipping_city_post = trim(filter_input(INPUT_POST, 'shipping_city', FILTER_UNSAFE_RAW));
        $shipping_governorate_post = trim(filter_input(INPUT_POST, 'shipping_governorate', FILTER_UNSAFE_RAW));
        $shipping_postal_code_post = trim(filter_input(INPUT_POST, 'shipping_postal_code', FILTER_UNSAFE_RAW));
        $shipping_country_post = trim(filter_input(INPUT_POST, 'shipping_country', FILTER_UNSAFE_RAW)) ?: 'Egypt';
        $payment_method_post = trim(filter_input(INPUT_POST, 'payment_method', FILTER_UNSAFE_RAW));
        $notes_to_seller_post = trim(filter_input(INPUT_POST, 'notes_to_seller', FILTER_SANITIZE_SPECIAL_CHARS)); // Stricter sanitization for notes

        // Validation for POSTed data
        if (empty($shipping_name_post)) $errors['shipping_name'] = "Full name for shipping is required.";
        if (empty($shipping_phone_post)) $errors['shipping_phone'] = "Shipping phone number is required.";
        elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $shipping_phone_post)) $errors['shipping_phone'] = "Invalid shipping phone number format.";
        if (empty($shipping_address1_post)) $errors['shipping_address_line1'] = "Shipping Address Line 1 is required.";
        if (empty($shipping_city_post)) $errors['shipping_city'] = "City is required.";
        if (empty($shipping_governorate_post)) $errors['shipping_governorate'] = "Governorate is required.";
        if (empty($payment_method_post)) $errors['payment_method'] = "Please select a payment method.";
        elseif (!in_array($payment_method_post, ['cod'])) $errors['payment_method'] = "Invalid payment method selected."; // Allow only 'cod' for now

        if (empty($errors) && isset($db) && $db instanceof PDO) {
            $db->beginTransaction();
            try {
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
                    ':discount_amount' => $discount_amount_checkout,
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

                $sql_insert_order_item = "INSERT INTO order_items (order_id, product_id, variant_id, brand_id, quantity, price_at_purchase, subtotal_for_item)
                                          VALUES (:order_id, :product_id, NULL, :brand_id, :quantity, :price, :subtotal)";
                $stmt_insert_order_item = $db->prepare($sql_insert_order_item);

                $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id AND requires_variants = 0 AND stock_quantity IS NOT NULL AND stock_quantity >= :quantity_to_subtract";
                $stmt_update_stock = $db->prepare($sql_update_stock);

                // Collect brand admin emails for notification
                $brand_admin_emails_for_notification = [];

                foreach ($cart_items_details_checkout as $item_co) {
                    // Re-check stock before committing this item to order_items and updating stock
                    $stmt_check_final_stock = $db->prepare("SELECT stock_quantity, requires_variants FROM products WHERE product_id = :pid AND is_active = 1");
                    $stmt_check_final_stock->execute([':pid' => $item_co['id']]);
                    $final_stock_info = $stmt_check_final_stock->fetch(PDO::FETCH_ASSOC);

                    if (!$final_stock_info || ($final_stock_info['requires_variants'] == 0 && isset($final_stock_info['stock_quantity']) && (int)$final_stock_info['stock_quantity'] < $item_co['quantity'])) {
                        $db->rollBack();
                        $errors['final_stock_check'] = "Unfortunately, stock for '" . esc_html($item_co['name']) . "' changed before your order could be completed. Please review your cart.";
                        $_SESSION['cart_message'] = "<div class='form-message error-message'>" . $errors['final_stock_check'] . "</div>";
                        header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=stock_unavailable");
                        exit;
                    }

                    $stmt_insert_order_item->execute([
                        ':order_id' => $new_order_id,
                        ':product_id' => $item_co['id'],
                        ':brand_id' => $item_co['brand_id'],
                        ':quantity' => $item_co['quantity'],
                        ':price' => $item_co['price'],
                        ':subtotal' => $item_co['line_total']
                    ]);

                    if ($final_stock_info['requires_variants'] == 0 && isset($final_stock_info['stock_quantity'])) {
                        $stmt_update_stock->execute([
                            ':quantity' => $item_co['quantity'], // This is the quantity being subtracted
                            ':product_id' => $item_co['id'],
                            ':quantity_to_subtract' => $item_co['quantity'] // Ensure stock is sufficient before subtracting
                        ]);
                         if ($stmt_update_stock->rowCount() == 0) {
                            // This means stock was not updated, possibly due to race condition or stock_quantity < quantity
                            // This case should ideally be caught by the re-check above, but as a safeguard:
                            $db->rollBack();
                            $errors['stock_update_failed'] = "Could not update stock for '" . esc_html($item_co['name']) . "'. Order cancelled. Please try again.";
                             $_SESSION['cart_message'] = "<div class='form-message error-message'>" . $errors['stock_update_failed'] . "</div>";
                            header("Location: " . rtrim(SITE_URL, '/') . "/cart.php?status=stock_update_error");
                            exit;
                        }
                    }

                    // Fetch brand admin email for this product's brand
                    $stmt_brand_admin_email = $db->prepare("
                        SELECT u.email FROM users u
                        JOIN brands b ON u.user_id = b.user_id
                        WHERE b.brand_id = :brand_id AND u.role = 'brand_admin' LIMIT 1
                    ");
                    $stmt_brand_admin_email->bindParam(':brand_id', $item_co['brand_id'], PDO::PARAM_INT);
                    $stmt_brand_admin_email->execute();
                    $brand_admin_email_result = $stmt_brand_admin_email->fetch(PDO::FETCH_ASSOC);
                    if ($brand_admin_email_result && !empty($brand_admin_email_result['email'])) {
                        $brand_admin_emails_for_notification[$item_co['brand_id']] = $brand_admin_email_result['email']; // Store unique emails by brand_id
                    }
                }

                $db->commit();
                unset($_SESSION['cart']);
                unset($_SESSION['header_cart_item_count']);
                $_SESSION['last_order_id'] = $new_order_id;

                // --- Send Order Confirmation Email to Customer ---
                $customer_email_subject = SITE_NAME . " - Order Confirmation #" . $new_order_id;
                $customer_email_body = "Hello " . htmlspecialchars($user_first_name) . ",\n\n";
                $customer_email_body .= "Thank you for your order! Your order #" . $new_order_id . " has been placed successfully.\n\n";
                $customer_email_body .= "You can view your order details here: " . rtrim(SITE_URL, '/') . "/order_detail.php?order_id=" . $new_order_id . "\n\n";
                $customer_email_body .= "Total Amount: " . CURRENCY_SYMBOL . number_format($grand_total_checkout, 2) . "\n\n";
                $customer_email_body .= "We will notify you once your items are shipped.\n\n";
                $customer_email_body .= "Regards,\n" . SITE_NAME . " Team";

                send_email($user_email, $customer_email_subject, $customer_email_body);


                // --- Send New Order Notification to Brand Admins ---
                foreach ($brand_admin_emails_for_notification as $brand_id_notif => $admin_email_notif) {
                    $brand_name_notif = '';
                    $stmt_brand_name = $db->prepare("SELECT brand_name FROM brands WHERE brand_id = :brand_id LIMIT 1");
                    $stmt_brand_name->bindParam(':brand_id', $brand_id_notif, PDO::PARAM_INT);
                    $stmt_brand_name->execute();
                    $brand_name_result = $stmt_brand_name->fetch(PDO::FETCH_ASSOC);
                    if ($brand_name_result) {
                        $brand_name_notif = " for Brand " . htmlspecialchars($brand_name_result['brand_name']);
                    }

                    $brand_admin_subject = SITE_NAME . " - New Order Received (Order #{$new_order_id}){$brand_name_notif}";
                    $brand_admin_body = "Hello Brand Admin,\n\n";
                    $brand_admin_body .= "A new order (#{$new_order_id}) has been placed on " . SITE_NAME . " that includes products from your brand.\n\n";
                    $brand_admin_body .= "Please log in to your Brand Admin Panel to view the order details and update item statuses:\n";
                    $brand_admin_body .= rtrim(SITE_URL, '/') . "/brand_admin/order_detail.php?order_id=" . $new_order_id . "\n\n";
                    $brand_admin_body .= "Regards,\n" . SITE_NAME . " Team";

                    send_email($admin_email_notif, $brand_admin_subject, $brand_admin_body);
                }

                header("Location: " . rtrim(SITE_URL, '/') . "/order_success.php");
                exit;

            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Checkout - Order Placement Error: " . $e->getMessage());
                $errors['database'] = "An error occurred while placing your order. Please try again.";
            }
        }
        // If errors occurred during POST validation, repopulate form fields
        if (!empty($errors)) {
            $user_shipping_info['shipping_name_form'] = $shipping_name_post;
            $user_shipping_info['phone_number'] = $shipping_phone_post;
            $user_shipping_info['shipping_address_line1'] = $shipping_address1_post;
            $user_shipping_info['shipping_address_line2'] = $shipping_address2_post;
            $user_shipping_info['shipping_city'] = $shipping_city_post;
            $user_shipping_info['shipping_governorate'] = $shipping_governorate_post;
            $user_shipping_info['shipping_postal_code'] = $shipping_postal_code_post;
            $user_shipping_info['shipping_country'] = $shipping_country_post;
            $user_shipping_info['notes_to_seller'] = $notes_to_seller_post;
        }
    }
}
?>

<section class="checkout-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Checkout</h2>

        <?php if (!empty($errors['database'])): ?>
            <div class="form-message error-message"><?php echo esc_html($errors['database']); ?></div>
        <?php endif; ?>
         <?php if (!empty($errors['address_fetch'])): ?>
            <div class="form-message warning-message"><?php echo esc_html($errors['address_fetch']); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors['final_stock_check'])): // This error would typically redirect, but shown if not ?>
            <div class="form-message error-message"><?php echo esc_html($errors['final_stock_check']); ?></div>
        <?php endif; ?>
         <?php if (!empty($errors['stock_update_failed'])): ?>
            <div class="form-message error-message"><?php echo esc_html($errors['stock_update_failed']); ?></div>
        <?php endif; ?>


        <div class="checkout-layout">
            <div class="checkout-form-container">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="checkout-form auth-form" novalidate>
                    <fieldset>
                        <legend>Shipping Information</legend>
                        <div class="form-group">
                            <label for="shipping_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo esc_html($user_shipping_info['shipping_name_form']); ?>" required>
                            <?php if (isset($errors['shipping_name'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_name']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="shipping_phone" name="shipping_phone" value="<?php echo esc_html($user_shipping_info['phone_number']); ?>" required placeholder="+201XXXXXXXXX">
                            <?php if (isset($errors['shipping_phone'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_phone']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_line1">Address Line 1 <span class="required">*</span></label>
                            <input type="text" id="shipping_address_line1" name="shipping_address_line1" value="<?php echo esc_html($user_shipping_info['shipping_address_line1']); ?>" required>
                            <?php if (isset($errors['shipping_address_line1'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_address_line1']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_line2">Address Line 2 (Optional)</label>
                            <input type="text" id="shipping_address_line2" name="shipping_address_line2" value="<?php echo esc_html($user_shipping_info['shipping_address_line2']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="shipping_city">City <span class="required">*</span></label>
                            <input type="text" id="shipping_city" name="shipping_city" value="<?php echo esc_html($user_shipping_info['shipping_city']); ?>" required>
                            <?php if (isset($errors['shipping_city'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_city']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_governorate">Governorate <span class="required">*</span></label>
                            <input type="text" id="shipping_governorate" name="shipping_governorate" value="<?php echo esc_html($user_shipping_info['shipping_governorate']); ?>" required>
                            <?php if (isset($errors['shipping_governorate'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_governorate']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="shipping_postal_code">Postal Code (Optional)</label>
                            <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="<?php echo esc_html($user_shipping_info['shipping_postal_code']); ?>">
                        </div>
                         <div class="form-group">
                            <label for="shipping_country">Country <span class="required">*</span></label>
                            <input type="text" id="shipping_country" name="shipping_country" value="<?php echo esc_html($user_shipping_info['shipping_country']); ?>" required>
                             <?php if (isset($errors['shipping_country'])): ?><span class="error-text"><?php echo esc_html($errors['shipping_country']); ?></span><?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Payment Method</legend>
                        <div class="form-group">
                            <label for="payment_method_cod">
                                <input type="radio" id="payment_method_cod" name="payment_method" value="cod" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cod' || !isset($_POST['payment_method'])) ? 'checked' : ''; ?> required>
                                Cash on Delivery (COD)
                            </label>
                            <?php if (isset($errors['payment_method'])): ?><span class="error-text d-block"><?php echo esc_html($errors['payment_method']); ?></span><?php endif; ?>
                            <small class="form-text text-muted">Other payment methods will be available soon.</small>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Order Notes (Optional)</legend>
                        <div class="form-group">
                            <label for="notes_to_seller">Special instructions for your order?</label>
                            <textarea id="notes_to_seller" name="notes_to_seller" rows="3" class="form-control"><?php echo esc_html($user_shipping_info['notes_to_seller']); ?></textarea>
                        </div>
                    </fieldset>

                    <div class="form-group mt-4">
                        <button type="submit" name="place_order" class="btn btn-primary btn-lg btn-block" <?php if (!empty($errors['database']) || !empty($errors['cart_stock']) || !empty($errors['cart_availability'])) echo 'disabled'; ?>>Place Order</button>
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
                                <span class="item-name"><?php echo esc_html($item_sum['name']); ?> (x<?php echo esc_html($item_sum['quantity']); ?>)</span>
                                <span class="item-price"><?php echo CURRENCY_SYMBOL . esc_html(number_format($item_sum['line_total'], 2)); ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <hr>
                        <p><strong>Subtotal:</strong> <span><?php echo CURRENCY_SYMBOL . esc_html(number_format($cart_subtotal_checkout, 2)); ?></span></p>
                        <p>Shipping: <span class="text-muted"><?php echo ($shipping_amount_checkout > 0) ? CURRENCY_SYMBOL . esc_html(number_format($shipping_amount_checkout, 2)) : 'Free'; ?></span></p>
                        <p>Taxes: <span class="text-muted"><?php echo ($tax_amount_checkout > 0) ? CURRENCY_SYMBOL . esc_html(number_format($tax_amount_checkout, 2)); ?></span></p>
                        <?php if ($discount_amount_checkout > 0): ?>
                        <p><strong>Discount:</strong> <span style="color: green;">-<?php echo CURRENCY_SYMBOL . esc_html(number_format($discount_amount_checkout, 2)); ?></span></p>
                        <?php endif; ?>
                        <hr>
                        <p class="grand-total"><strong>Total:</strong> <strong><?php echo CURRENCY_SYMBOL . esc_html(number_format($grand_total_checkout, 2)); ?></strong></p>
                    <?php elseif (empty($errors)): // Only show "cart is empty" if no other critical errors ?>
                        <p>Your cart is empty or items are unavailable.</p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cart_items_details_checkout) || empty($errors)): // Show return to cart link if cart had items or no major errors ?>
                <p class="text-center mt-3"><a href="<?php echo rtrim(SITE_URL, '/'); ?>/cart.php">&laquo; Return to Cart</a></p>
                <?php endif; ?>
            </div>
        </div>
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
