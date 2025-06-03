<?php
// admin/index.php - Super Admin Dashboard

// Define a base URL for assets if your header needs it (adjust path as necessary)
// If admin/index.php is at the root of the admin folder, assets in 'css' or 'js' subfolders are relative.
$admin_base_url = '.'; // Current directory is admin root for this page

// Include main configuration
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN DASHBOARD ERROR: Main config.php not found.");
}

// Authenticate: Ensure user is super_admin
require_once 'auth_check.php'; // This will handle session start and role check

$admin_page_title = "Sales Dashboard";
include_once 'includes/header.php'; // Admin specific header

$db = getPDOConnection(); // From main config

// --- Fetch Sales Data ---
$total_sales = 0;
$total_orders = 0;
$average_order_value = 0;
$total_users = 0;
$pending_brands_count = 0;
$recent_orders = [];
$sales_by_brand = [];

try {
    // Total Sales and Orders
    $stmt_sales_overview = $db->query("SELECT SUM(total_amount) as sum_sales, COUNT(order_id) as count_orders FROM orders WHERE order_status NOT IN ('cancelled', 'refunded')");
    $sales_overview = $stmt_sales_overview->fetch(PDO::FETCH_ASSOC);
    if ($sales_overview) {
        $total_sales = $sales_overview['sum_sales'] ?? 0;
        $total_orders = $sales_overview['count_orders'] ?? 0;
        if ($total_orders > 0) {
            $average_order_value = $total_sales / $total_orders;
        }
    }

    // Total Users (Customers + Brand Admins)
    $stmt_total_users = $db->query("SELECT COUNT(user_id) as count_users FROM users WHERE is_active = 1");
    $users_data = $stmt_total_users->fetch(PDO::FETCH_ASSOC);
    $total_users = $users_data['count_users'] ?? 0;

    // Pending Brands
    $stmt_pending_brands = $db->query("SELECT COUNT(brand_id) as count_pending FROM brands WHERE is_approved = 0");
    $pending_brands_data = $stmt_pending_brands->fetch(PDO::FETCH_ASSOC);
    $pending_brands_count = $pending_brands_data['count_pending'] ?? 0;


    // Recent Orders (e.g., last 5)
    $stmt_recent_orders = $db->query("SELECT order_id, customer_id, order_date, total_amount, order_status, 
                                      (SELECT username FROM users WHERE users.user_id = orders.customer_id) as customer_username
                                      FROM orders 
                                      ORDER BY order_date DESC LIMIT 5");
    $recent_orders = $stmt_recent_orders->fetchAll(PDO::FETCH_ASSOC);

    // Sales by Brand (Simplified for now - sum of order_items linked to a brand)
    // This query could be more complex if commission is calculated here or if orders can have items from multiple brands.
    // For now, let's assume order_items has brand_id and we sum subtotal_for_item.
    $stmt_sales_by_brand = $db->query("
        SELECT 
            b.brand_id, 
            b.brand_name, 
            SUM(oi.subtotal_for_item) as total_brand_sales,
            COUNT(DISTINCT oi.order_id) as total_brand_orders
        FROM order_items oi
        JOIN brands b ON oi.brand_id = b.brand_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status NOT IN ('cancelled', 'refunded') 
        GROUP BY b.brand_id, b.brand_name
        ORDER BY total_brand_sales DESC
        LIMIT 10 
    "); // Limiting to top 10 brands for display
    $sales_by_brand = $stmt_sales_by_brand->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin Dashboard - Error fetching sales data: " . $e->getMessage());
    echo "<div class='admin-message error'>Could not load all dashboard data. Please try again later.</div>";
}

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<div class="stat-cards-container">
    <div class="stat-card total-sales">
        <h3>Total Revenue</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($total_sales, 2)); ?></p>
        <p class="stat-description">From all successful orders.</p>
    </div>
    <div class="stat-card total-orders">
        <h3>Total Orders</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_orders)); ?></p>
        <p class="stat-description">Excluding cancelled/refunded.</p>
    </div>
    <div class="stat-card total-users"> <h3>Average Order Value</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($average_order_value, 2)); ?></p>
        <p class="stat-description">Average per successful order.</p>
    </div>
     <div class="stat-card total-users">
        <h3>Active Users</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_users)); ?></p>
        <p class="stat-description">Customers & Brand Admins.</p>
    </div>
    <div class="stat-card pending-brands">
        <h3>Pending Brands</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($pending_brands_count)); ?></p>
        <p class="stat-description stat-link"><a href="#">Manage Brands</a></p> <?php // Link to brand management page ?>
    </div>
</div>

<div class="admin-section">
    <h2 class="admin-section-title">Recent Orders</h2>
    <?php if (!empty($recent_orders)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_username'] ?? 'N/A'); ?> (ID: <?php echo htmlspecialchars($order['customer_id']); ?>)</td>
                        <td><?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($order['order_date']))); ?></td>
                        <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                        <td><span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?></span></td>
                        <td class="actions">
                            <a href="#" class="btn-view">View</a> <?php // Link to order detail page ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="admin-message info">No recent orders found.</p>
    <?php endif; ?>
</div>

<div class="admin-section mt-3">
    <h2 class="admin-section-title">Sales by Brand (Top 10)</h2>
    <?php if (!empty($sales_by_brand)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Brand Name</th>
                    <th>Total Orders</th>
                    <th>Total Sales</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales_by_brand as $brand_sale): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($brand_sale['brand_name']); ?> (ID: <?php echo htmlspecialchars($brand_sale['brand_id']); ?>)</td>
                        <td><?php echo htmlspecialchars(number_format($brand_sale['total_brand_orders'])); ?></td>
                        <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($brand_sale['total_brand_sales'], 2)); ?></td>
                        <td class="actions">
                            <a href="#" class="btn-view">View Brand</a> <?php // Link to brand detail/management page ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="admin-message info">No brand sales data available yet.</p>
    <?php endif; ?>
</div>


<?php
// Placeholder for charts - you would include a JS library like Chart.js
// and then use PHP to output data in a format Chart.js can use.
// Example:
// echo "<canvas id='salesChart'></canvas>";
// echo "<script> const ctx = document.getElementById('salesChart'); /* ... Chart.js setup ... */ </script>";
?>

<?php
include_once 'includes/footer.php'; // Admin specific footer
?>
