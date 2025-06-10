<?php
// public/cart.php

// Start output buffering as the VERY FIRST THING to catch all output.
ob_start();

// These error settings are for development. In production, they should typically be Off or logged to a file.
// These will be overridden for AJAX requests later.
// Note: config.php also sets error reporting, which will take precedence after it's included.
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// --- CRITICAL: Load config.php directly and unconditionally at the very beginning ---
$config_path_from_public = __DIR__ . '/../src/config/config.php';
if (file_exists($config_path_from_public)) {
    require_once $config_path_from_public;
} else {
    // If config.php is not found, this is a fatal error.
    // Attempt to send JSON if it's an AJAX request, otherwise die with HTML.
    $is_critical_error_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    // A simpler check if POST action implies AJAX, common in this app
    $is_critical_error_ajax_action = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

    if ($is_critical_error_ajax || $is_critical_error_ajax_action) {
        if (ob_get_level() > 0) ob_clean(); // Clean buffer
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false,
            'message' => "Critical error: Main configuration file not found. Site cannot operate.",
            'type' => 'error'
        ]);
        exit;
    } else {
        die("Critical error: Main configuration file not found. Expected at: " . htmlspecialchars($config_path_from_public));
    }
}

// Define flags for AJAX requests
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
// Check if it's an AJAX action based on POST data, but not the 'update_cart_all_btn' which is a full page form submit
$is_ajax_action_specific = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['update_cart_all_btn']));
$is_definitely_ajax = $is_ajax_request || $is_ajax_action_specific;

$response = []; // Initialize $response for AJAX

if ($is_definitely_ajax) {
    if (ob_get_level() > 0) {
         ob_clean(); // Clean any output buffered so far
    }
    // For AJAX requests, suppress error display and report no errors directly to output.
    // Errors will still be logged if configured in php.ini (via config.php's handlers).
   // ini_set('display_errors', 0);
   // ini_set('display_startup_errors', 0);
   // error_reporting(0);

    header('Content-Type: application/json');
    // Initialize a default response. Specific actions will modify or replace this.
    $response = ['success' => false, 'message' => 'An unexpected error occurred processing your AJAX request.', 'cart_item_count' => 0, 'type' => 'error'];
}
// For non-AJAX HTML requests, output buffering continues.
// The main buffer will flush at the end of the script or when ob_end_flush() is called.

$page_title = "Your Shopping Cart - BaladyMall";

// Define header and footer paths using PROJECT_ROOT_PATH (defined in config.php)
$header_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/header.php' : __DIR__ . '/../src/includes/header.php';
$footer_path = defined('PROJECT_ROOT_PATH') ? PROJECT_ROOT_PATH . '/src/includes/footer.php' : __DIR__ . '/../src/includes/footer.php';

// Only include header HTML if NOT an AJAX request
if (!$is_definitely_ajax) {
    if (file_exists($header_path)) {
        require_once $header_path;
    } else {
        die("Critical error: Header file not found. Expected at: " . htmlspecialchars($header_path));
    }
}

$db = null;
if (function_exists('getPDOConnection')) {
    $db = getPDOConnection();
}

$cart_action_message = ''; // Initialize to avoid undefined variable issues

if (!isset($db) || !$db instanceof PDO) {
    $error_message_for_db = "Database connection is not available. Cart operations may fail.";
    if ($is_definitely_ajax) {
        $response['message'] = "Database connection error. Cannot perform cart operations.";
        $response['type'] = 'error';
        // Safely encode and echo JSON
        $json_output = json_encode($response);
        if ($json_output === false) {
            error_log("cart.php (DB connection error): json_encode failed. Error: " . json_last_error_msg());
            http_response_code(500);
            echo '{"success":false,"message":"Server error: Could not encode JSON response for DB error.","type":"error", "cart_item_count":0}';
        } else {
            echo $json_output;
        }
        exit;
    } else {
        $cart_action_message = "<div class='form-message error-message'>{$error_message_for_db}</div>";
    }
}

$cart_items_details = [];
$cart_subtotal = 0;
$cart_total_items = 0;

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper function to safely output JSON
function safe_json_encode_and_exit($data_array) {
    if (ob_get_level() > 0) ob_clean(); // Ensure buffer is clean
    header('Content-Type: application/json'); // Re-affirm header just in case
    $json_output = json_encode($data_array);
    if ($json_output === false) {
        $error_message = "Server error: Could not encode JSON response. JSON Error: " . json_last_error_msg();
        error_log("cart.php (safe_json_encode_and_exit): json_encode failed. Error: " . json_last_error_msg() . ". Data: " . print_r($data_array, true));
        http_response_code(500);
        // Fallback JSON string
        echo '{"success":false,"message":"' . $error_message . '","type":"error","cart_item_count":' . ($_SESSION['header_cart_item_count'] ?? 0) . '}';
    } else {
        echo $json_output;
    }
    exit;
}


// --- Cart Action Handling ---
if (isset($db) && $db instanceof PDO) {
    $action = $_REQUEST['action'] ?? null;
    $product_id_req = null;

    if (isset($_REQUEST['product_id'])) {
        $product_id_req = filter_var($_REQUEST['product_id'], FILTER_VALIDATE_INT);
    } elseif (isset($_REQUEST['id'])) {
        $product_id_req = filter_var($_REQUEST['id'], FILTER_VALIDATE_INT);
    }

    $quantity_request = $_REQUEST['quantity'] ?? 1;

    if ($action === 'add' && !isset($_SESSION['user_id'])) {
        $redirect_url = get_asset_url("login.php?auth=required&target=cart&message=login_to_add_to_cart");
        if ($is_definitely_ajax) {
            $response['success'] = false;
            $response['message'] = "Please log in to add items to your cart.";
            $response['type'] = 'info';
            $response['redirect'] = $redirect_url;
            safe_json_encode_and_exit($response);
        } else {
            $_SESSION['redirect_after_login'] = get_asset_url("products.php");
            $_SESSION['cart_message'] = "<div class='form-message info-message'>Please log in to add items to your cart.</div>";
            header("Location: " . $redirect_url);
            exit;
        }
    }

    if ($action && $product_id_req !== null && $product_id_req > 0 && isset($_SESSION['user_id']) && $action !== 'update_all') {
        $stock_quantity_db = 9999;
        $is_simple_product_with_stock_db = false;
        $product_name_for_message_db = "Product (ID: {$product_id_req})";
        $product_is_active_db = false;

        try {
            $stmt_stock_check = $db->prepare("SELECT product_name, stock_quantity, requires_variants, is_active FROM products WHERE product_id = :pid");
            $stmt_stock_check->bindParam(':pid', $product_id_req, PDO::PARAM_INT);
            $stmt_stock_check->execute();
            $product_stock_info = $stmt_stock_check->fetch(PDO::FETCH_ASSOC);

            if ($product_stock_info) {
                $product_name_for_message_db = esc_html($product_stock_info['product_name']);
                $product_is_active_db = (int)$product_stock_info['is_active'] === 1;
                if ($product_stock_info['requires_variants'] == 0 && isset($product_stock_info['stock_quantity'])) {
                    $stock_quantity_db = (int)$product_stock_info['stock_quantity'];
                    $is_simple_product_with_stock_db = true;
                }
            } else {
                 $response['message'] = "Product (ID: {$product_id_req}) not found or inactive.";
                 $response['success'] = false; $response['type'] = 'error';
                 if ($is_definitely_ajax) safe_json_encode_and_exit($response);
                 $cart_action_message .= "<div class='form-message error-message'>{$response['message']}</div>";
                 $action = null;
            }
        } catch (PDOException $e) {
            error_log("Cart Action - Stock Check Error (Product ID: {$product_id_req}): " . $e->getMessage());
            $response['message'] = "Could not verify product stock. Please try again.";
            $response['success'] = false; $response['type'] = 'error';
            if ($is_definitely_ajax) safe_json_encode_and_exit($response);
            $cart_action_message .= "<div class='form-message error-message'>{$response['message']}</div>";
            $action = null;
        }

        if ($action && $product_is_active_db) {
            $current_quantity_for_action = is_numeric($quantity_request) ? (int)$quantity_request : 1;

            switch ($action) {
                case 'add':
                    if ($current_quantity_for_action <= 0) {
                        $response['message'] = "Quantity must be at least 1.";
                        $response['success'] = false; $response['type'] = 'error';
                        if ($is_definitely_ajax) safe_json_encode_and_exit($response);
                        $cart_action_message .= "<div class='form-message error-message'>{$response['message']}</div>";
                        break;
                    }

                    // This variable will track if a specific warning related to stock capping during this 'add' operation was set.
                    $specific_add_warning_set = false;

                    if ($is_simple_product_with_stock_db) {
                        if ($stock_quantity_db <= 0) {
                            $response['message'] = "Sorry, '{$product_name_for_message_db}' is out of stock.";
                            $response['success'] = false; $response['type'] = 'error';
                            if ($is_definitely_ajax) safe_json_encode_and_exit($response);
                            $cart_action_message .= "<div class='form-message error-message'>{$response['message']}</div>";
                            break; // Exit switch for out of stock
                        }
                        if ($current_quantity_for_action > $stock_quantity_db) {
                             $response['message'] = "Cannot add {$current_quantity_for_action} of '{$product_name_for_message_db}'. Only {$stock_quantity_db} available. Max quantity added.";
                             $current_quantity_for_action = $stock_quantity_db; // Cap quantity to add
                             $response['type'] = 'warning';
                             $specific_add_warning_set = true;
                             // $response['success'] will be true because item is still added/updated
                        }
                    }

                    // Proceed to update session cart if not an "out of stock" scenario that broke earlier
                    if (!($is_simple_product_with_stock_db && $stock_quantity_db <= 0 && $current_quantity_for_action > 0) ) {
                        if (isset($_SESSION['cart'][$product_id_req])) { // Product already in cart
                            $new_quantity_in_cart = $_SESSION['cart'][$product_id_req] + $current_quantity_for_action;
                            if ($is_simple_product_with_stock_db && $new_quantity_in_cart > $stock_quantity_db) {
                                $_SESSION['cart'][$product_id_req] = $stock_quantity_db; // Cap total
                                if (!$specific_add_warning_set) { // Only set this if the earlier cap warning wasn't for the initial quantity
                                    $response['message'] = "Total quantity for '{$product_name_for_message_db}' in cart exceeds stock. Adjusted to {$stock_quantity_db}.";
                                    $response['type'] = 'warning';
                                    $specific_add_warning_set = true;
                                }
                            } else { // Simple increment
                                $_SESSION['cart'][$product_id_req] = $new_quantity_in_cart;
                            }
                        } else { // New product to cart
                            $_SESSION['cart'][$product_id_req] = $current_quantity_for_action;
                        }

                        // Set overall success status for the action
                        $response['success'] = true;

                        // If no specific warning was set during this 'add' operation, set a generic success message.
                        if (!$specific_add_warning_set) {
                            $response['message'] = "'{$product_name_for_message_db}' added to cart.";
                            $response['type'] = 'success';
                        }
                        // If $specific_add_warning_set is true, $response['message'] and $response['type']='warning'
                        // would have been set by the capping logic already.
                    }

                    // Final AJAX response part for 'add'
                    if ($is_definitely_ajax) {
                        $_SESSION['header_cart_item_count'] = array_sum($_SESSION['cart']);
                        $response['cart_item_count'] = $_SESSION['header_cart_item_count'];
                        safe_json_encode_and_exit($response);
                    }
                    // For non-AJAX POST, redirect
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_definitely_ajax) {
                        header("Location: " . get_asset_url("cart.php?action_status=added&product_name=" . urlencode($product_name_for_message_db)));
                        exit;
                    }
                    break;

                case 'update':
                    $quantity_to_update_item = is_numeric($quantity_request) ? (int)$quantity_request : 0;
                    if ($quantity_to_update_item > 0) {
                        if ($is_simple_product_with_stock_db && $quantity_to_update_item > $stock_quantity_db) {
                            $_SESSION['cart'][$product_id_req] = $stock_quantity_db;
                            $response['message'] = "Quantity for '{$product_name_for_message_db}' updated to max available stock: {$stock_quantity_db}.";
                            $response['type'] = 'warning'; $response['success'] = true;
                        } else {
                            $_SESSION['cart'][$product_id_req] = $quantity_to_update_item;
                            $response['message'] = "Cart updated for '{$product_name_for_message_db}'.";
                            $response['type'] = 'success'; $response['success'] = true;
                        }
                    } else {
                        unset($_SESSION['cart'][$product_id_req]);
                        $response['message'] = "'{$product_name_for_message_db}' removed from cart.";
                        $response['type'] = 'success'; $response['success'] = true;
                    }
                    if ($is_definitely_ajax) {
                        $_SESSION['header_cart_item_count'] = array_sum($_SESSION['cart']);
                        $response['cart_item_count'] = $_SESSION['header_cart_item_count'];
                        safe_json_encode_and_exit($response);
                    }
                    header("Location: " . get_asset_url("cart.php?action_status=item_updated&product_name=" . urlencode($product_name_for_message_db)));
                    exit;
                    break;

                case 'remove':
                    if (isset($_SESSION['cart'][$product_id_req])) {
                        unset($_SESSION['cart'][$product_id_req]);
                        $response['message'] = "'{$product_name_for_message_db}' removed from cart.";
                        $response['type'] = 'success'; $response['success'] = true;
                    } else {
                         $response['message'] = "'{$product_name_for_message_db}' not found in cart.";
                         $response['type'] = 'info'; $response['success'] = false;
                    }
                    if ($is_definitely_ajax) {
                        $_SESSION['header_cart_item_count'] = array_sum($_SESSION['cart']);
                        $response['cart_item_count'] = $_SESSION['header_cart_item_count'];
                        safe_json_encode_and_exit($response);
                    }
                    header("Location: " . get_asset_url("cart.php?action_status=removed&product_name=" . urlencode($product_name_for_message_db)));
                    exit;
                    break;
            }
        } elseif (!$product_is_active_db && $product_id_req !== null) {
            $response['message'] = "Sorry, '{$product_name_for_message_db}' is not currently active or available and cannot be added/updated in cart.";
            $response['success'] = false; $response['type'] = 'error';
            if ($is_definitely_ajax) safe_json_encode_and_exit($response);
            $cart_action_message .= "<div class='form-message error-message'>{$response['message']}</div>";
        }
    } elseif ($action === 'update_all' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($db)) {
        if (isset($quantity_request) && is_array($quantity_request)) {
            $all_updates_successful = true;
            $update_messages = [];
            foreach ($quantity_request as $pid_from_form => $qty_from_form) {
                $pid = filter_var($pid_from_form, FILTER_VALIDATE_INT);
                $new_qty = filter_var($qty_from_form, FILTER_VALIDATE_INT);
                if ($pid === false || $new_qty === false) {
                    $update_messages[] = "Invalid data received for an item.";
                    $all_updates_successful = false; continue;
                }
                $current_product_stock_ua = 9999; $current_product_is_simple_with_stock_ua = false;
                $current_product_name_ua = "Product ID {$pid}"; $product_is_active_ua = false;
                try {
                    $stmt_prod_info_ua = $db->prepare("SELECT product_name, stock_quantity, requires_variants, is_active FROM products WHERE product_id = :pid");
                    $stmt_prod_info_ua->bindParam(':pid', $pid, PDO::PARAM_INT); $stmt_prod_info_ua->execute();
                    $prod_info_ua = $stmt_prod_info_ua->fetch(PDO::FETCH_ASSOC);
                    if ($prod_info_ua) {
                        $current_product_name_ua = esc_html($prod_info_ua['product_name']);
                        $product_is_active_ua = (int)$prod_info_ua['is_active'] === 1;
                        if ($prod_info_ua['requires_variants'] == 0 && isset($prod_info_ua['stock_quantity'])) {
                            $current_product_stock_ua = (int)$prod_info_ua['stock_quantity'];
                            $current_product_is_simple_with_stock_ua = true;
                        }
                    } else {
                        unset($_SESSION['cart'][$pid]); $update_messages[] = "Item '{$current_product_name_ua}' no longer available and removed."; continue;
                    }
                } catch (PDOException $e) {
                    error_log("Update All - Stock Check Error (PID: {$pid}): " . $e->getMessage());
                    $update_messages[] = "Could not verify stock for '{$current_product_name_ua}'.";
                    $all_updates_successful = false; continue;
                }
                if (!$product_is_active_ua) {
                    unset($_SESSION['cart'][$pid]); $update_messages[] = "Item '{$current_product_name_ua}' is no longer active and has been removed."; continue;
                }
                if ($new_qty > 0) {
                    if ($current_product_is_simple_with_stock_ua && $new_qty > $current_product_stock_ua) {
                        $_SESSION['cart'][$pid] = $current_product_stock_ua;
                        $update_messages[] = "Quantity for '{$current_product_name_ua}' adjusted to max stock: {$current_product_stock_ua}.";
                    } else { $_SESSION['cart'][$pid] = $new_qty; }
                } else {
                    unset($_SESSION['cart'][$pid]); $update_messages[] = "'{$current_product_name_ua}' removed from cart.";
                }
            }
            if (empty($update_messages) && $all_updates_successful) {
                $cart_action_message .= "<div class='form-message success-message'>Cart updated successfully.</div>";
            } elseif (!empty($update_messages)) {
                $message_type_class = $all_updates_successful ? 'warning-message' : 'error-message';
                $cart_action_message .= "<div class='form-message {$message_type_class}'>Cart update status:<ul>";
                foreach($update_messages as $msg) { $cart_action_message .= "<li>" . esc_html($msg) . "</li>"; }
                $cart_action_message .= "</ul></div>";
            }
        }
    }
}

if(isset($_GET['action_status']) || isset($_SESSION['cart_message'])){
    $status_product_name_get = isset($_GET['product_name']) ? esc_html(urldecode($_GET['product_name'])) : "Item";
    switch($_GET['action_status'] ?? '') {
        case 'added': $cart_action_message .= "<div class='form-message success-message'>'{$status_product_name_get}' added/updated in cart.</div>"; break;
        case 'item_updated': $cart_action_message .= "<div class='form-message success-message'>Cart updated for '{$status_product_name_get}'.</div>"; break;
        case 'removed': $cart_action_message .= "<div class='form-message success-message'>'{$status_product_name_get}' removed from cart.</div>"; break;
        case 'checkout_validation_error': case 'stock_unavailable': case 'stock_update_error':
            if (isset($_SESSION['cart_message'])) {
                $cart_action_message .= $_SESSION['cart_message']; unset($_SESSION['cart_message']);
            } else { $cart_action_message .= "<div class='form-message error-message'>There was an issue with your cart items during checkout. Please review.</div>"; }
            break;
    }
}

if (!empty($_SESSION['cart']) && isset($db) && $db instanceof PDO) {
    $product_ids_in_cart = array_keys($_SESSION['cart']);
    if (!empty($product_ids_in_cart)) {
        $product_ids_in_cart = array_map('intval', $product_ids_in_cart);
        $placeholders = implode(',', array_fill(0, count($product_ids_in_cart), '?'));
        $sql_fetch_cart_items = "SELECT product_id, product_name, price, main_image_url, stock_quantity, requires_variants FROM products WHERE product_id IN ($placeholders) AND is_active = 1";
        try {
            $stmt_fetch_cart_items = $db->prepare($sql_fetch_cart_items);
            $stmt_fetch_cart_items->execute($product_ids_in_cart);
            $fetched_products_data = $stmt_fetch_cart_items->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
            $temp_cart_validation_messages = [];
            foreach ($_SESSION['cart'] as $pid_session => $qty_session) {
                $pid_session_int = (int)$pid_session;
                if (isset($fetched_products_data[$pid_session_int])) {
                    $product_data_session = $fetched_products_data[$pid_session_int];
                    $current_product_name_display_session = esc_html($product_data_session['product_name']);
                    if ($product_data_session['requires_variants'] == 0 && isset($product_data_session['stock_quantity'])) {
                        $current_stock_session = (int)$product_data_session['stock_quantity'];
                        if ($current_stock_session <= 0) {
                            unset($_SESSION['cart'][$pid_session]);
                            $temp_cart_validation_messages[] = "Item '{$current_product_name_display_session}' became out of stock and has been removed."; continue;
                        }
                        if ($qty_session > $current_stock_session) {
                            $qty_session = $current_stock_session; $_SESSION['cart'][$pid_session] = $qty_session;
                            $temp_cart_validation_messages[] = "Quantity for '{$current_product_name_display_session}' was adjusted to available stock: {$qty_session}.";
                        }
                    }
                    $line_total_session = $product_data_session['price'] * $qty_session;
                    $cart_items_details[] = [
                        'id' => $pid_session_int, 'name' => $product_data_session['product_name'], 'price' => $product_data_session['price'],
                        'quantity' => $qty_session, 'image_url' => $product_data_session['main_image_url'], 'line_total' => $line_total_session,
                        'stock_quantity' => ($product_data_session['requires_variants'] == 0 && isset($product_data_session['stock_quantity'])) ? $product_data_session['stock_quantity'] : 9999,
                        'requires_variants' => $product_data_session['requires_variants']
                    ];
                    $cart_subtotal += $line_total_session; $cart_total_items += $qty_session;
                } else {
                    unset($_SESSION['cart'][$pid_session]);
                    $temp_cart_validation_messages[] = "An item (ID: {$pid_session_int}) in your cart is no longer available or active and has been removed.";
                }
            }
            if (!empty($temp_cart_validation_messages)) {
                $cart_action_message .= "<div class='form-message warning-message'>Important Cart Updates:<ul>";
                foreach($temp_cart_validation_messages as $msg_val) { $cart_action_message .= "<li>" . esc_html($msg_val) . "</li>"; }
                $cart_action_message .= "</ul></div>";
            }
        } catch (PDOException $e) {
            error_log("Error fetching cart item details for display: " . $e->getMessage());
            $cart_action_message .= "<div class='form-message error-message'>Could not load cart details. Please try again.</div>";
            $_SESSION['cart'] = []; $cart_items_details = []; $cart_subtotal = 0; $cart_total_items = 0;
        }
    }
}

$_SESSION['header_cart_item_count'] = array_sum($_SESSION['cart']);
$grand_total = $cart_subtotal;

// ONLY output HTML if it's NOT an AJAX request.
if (!$is_definitely_ajax) {
?>

<section class="cart-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Your Shopping Cart</h2>

        <?php if (!empty($cart_action_message)): ?>
            <?php echo $cart_action_message; ?>
        <?php endif; ?>

        <?php if (empty($cart_items_details)): ?>
            <div class="cart-empty text-center" style="padding-top: 30px; padding-bottom: 30px;">
                <h3>Your cart is currently empty.</h3>
                <p style="margin-top:15px; margin-bottom:25px;">Looks like you haven't added any products yet. Start exploring!</p>
                <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-primary btn-lg">Shop Products</a>
            </div>
        <?php else: ?>
            <form action="<?php echo get_asset_url('cart.php'); ?>" method="POST" class="cart-form">
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
                                        $item_image_url_display = '';
                                        $fallback_cart_item_image_esc = get_asset_url('images/no-image.png');
                                        if (defined('PLACEHOLDER_IMAGE_URL_GENERATOR') && !empty(PLACEHOLDER_IMAGE_URL_GENERATOR)) {
                                            $fallback_cart_item_image_esc = esc_html(rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/80x80/CCC/777?text=Error");
                                        }
                                        if (!empty($item['image_url'])) {
                                            if (filter_var($item['image_url'], FILTER_VALIDATE_URL)) {
                                                $item_image_url_display = esc_html($item['image_url']);
                                            } else {
                                                $item_image_url_display = get_asset_url('uploads/' . ltrim(esc_html($item['image_url']), '/'));
                                            }
                                        }
                                        if (empty($item_image_url_display)) {
                                            $item_image_url_display = defined('PLACEHOLDER_IMAGE_URL_GENERATOR') ? rtrim(PLACEHOLDER_IMAGE_URL_GENERATOR, '/') . "/80x80/F0F0F0/AAA?text=No+Image" : $fallback_cart_item_image_esc;
                                        }
                                    ?>
                                    <a href="<?php echo get_asset_url('product_detail.php?id=' . esc_html($item['id'])); ?>">
                                        <img src="<?php echo $item_image_url_display; ?>" alt="<?php echo esc_html($item['name']); ?>"
                                             onerror="this.onerror=null;this.src='<?php echo $fallback_cart_item_image_esc; ?>';">
                                    </a>
                                </td>
                                <td class="cart-item-name" data-label="Product">
                                    <a href="<?php echo get_asset_url('product_detail.php?id=' . esc_html($item['id'])); ?>">
                                        <?php echo esc_html($item['name']); ?>
                                    </a>
                                     <?php if ($item['requires_variants'] == 1): ?>
                                        <small class="text-muted d-block">(Variant details TBD)</small>
                                     <?php endif; ?>
                                </td>
                                <td class="cart-item-price text-right" data-label="Price"><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($item['price'], 2)); ?></td>
                                <td class="cart-item-quantity text-center" data-label="Quantity">
                                    <input type="number" name="quantity[<?php echo esc_html($item['id']); ?>]"
                                           value="<?php echo esc_html($item['quantity']); ?>"
                                           min="0"
                                           max="<?php echo esc_html($item['stock_quantity']); ?>"
                                           class="form-control form-control-sm quantity-input"
                                           aria-label="Quantity for <?php echo esc_html($item['name']); ?>">
                                </td>
                                <td class="cart-item-line-total text-right" data-label="Total"><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($item['line_total'], 2)); ?></td>
                                <td class="cart-item-remove text-center" data-label="Remove">
                                    <a href="<?php echo get_asset_url('cart.php?action=remove&product_id=' . esc_html($item['id'])); ?>"
                                       class="btn btn-sm btn-danger remove-item-btn"
                                       title="Remove <?php echo esc_html($item['name']); ?>"
                                       aria-label="Remove <?php echo esc_html($item['name']); ?>"
                                       onclick="return confirm('Are you sure you want to remove this item: \'<?php echo esc_html(addslashes($item['name'])); ?>\' from your cart?');">
                                       &times;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div class="cart-actions-bar">
                         <a href="<?php echo get_asset_url('products.php'); ?>" class="btn btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="margin-right:5px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg>
                            Continue Shopping
                        </a>
                        <button type="submit" name="update_cart_all_btn" class="btn btn-info">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16" style="margin-right:5px; vertical-align: text-bottom;"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/></svg>
                            Update Cart
                        </button>
                    </div>
                    <div class="cart-totals">
                        <p><strong>Subtotal:</strong> <span><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($cart_subtotal, 2)); ?></span></p>
                        <p><strong>Items in Cart:</strong> <span><?php echo esc_html($cart_total_items); ?></span></p>
                        <hr>
                        <p class="grand-total"><strong>Total:</strong> <span><?php echo $GLOBALS['currency_symbol'] . esc_html(number_format($grand_total, 2)); ?></span></p>
                        <a href="<?php echo get_asset_url('checkout.php'); ?>" class="btn btn-primary btn-lg btn-block mt-3">
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
// Footer inclusion conditional for non-AJAX requests
    if (file_exists($footer_path)) {
        require_once $footer_path;
    } else {
        die("Critical error: Footer file not found. Expected at: " . htmlspecialchars($footer_path));
    }
} // End of if(!$is_definitely_ajax)

// Flush the output buffer at the very end of the script if it's still active.
// This is important for both AJAX (though it should be cleaned/exited earlier) and HTML responses.
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
