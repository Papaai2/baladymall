<?php
// admin/index.php - Super Admin Dashboard

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN DASHBOARD ERROR: Main config.php not found.");
}

require_once 'auth_check.php'; 

$admin_page_title = "Sales Dashboard";
// We will include Chart.js CDN directly in this file's HTML,
// so no need to pass $include_chart_js to header/footer for now.
include_once 'includes/header.php'; 

$db = getPDOConnection(); 

// --- Fetch Basic Stat Card Data ---
$total_sales = 0;
$total_orders = 0;
$average_order_value = 0;
$total_users = 0;
$pending_brands_count = 0;
$recent_orders = [];
$sales_by_brand = []; // Top brands by sales

try {
    // Total Sales and Orders (successful)
    $stmt_sales_overview = $db->query("SELECT SUM(total_amount) as sum_sales, COUNT(order_id) as count_orders FROM orders WHERE order_status NOT IN ('cancelled', 'refunded', 'pending_payment')");
    $sales_overview = $stmt_sales_overview->fetch(PDO::FETCH_ASSOC);
    if ($sales_overview) {
        $total_sales = $sales_overview['sum_sales'] ?? 0;
        $total_orders = $sales_overview['count_orders'] ?? 0;
        if ($total_orders > 0) {
            $average_order_value = $total_sales / $total_orders;
        }
    }

    // Total Active Users (Customers + Brand Admins + Super Admins)
    $stmt_total_users = $db->query("SELECT COUNT(user_id) as count_users FROM users WHERE is_active = 1");
    $users_data = $stmt_total_users->fetch(PDO::FETCH_ASSOC);
    $total_users = $users_data['count_users'] ?? 0;

    // Pending Brands for Approval
    $stmt_pending_brands = $db->query("SELECT COUNT(brand_id) as count_pending FROM brands WHERE is_approved = 0");
    $pending_brands_data = $stmt_pending_brands->fetch(PDO::FETCH_ASSOC);
    $pending_brands_count = $pending_brands_data['count_pending'] ?? 0;

    // Recent Orders (last 5)
    $stmt_recent_orders = $db->query("SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, o.order_status, 
                                          u.username as customer_username
                                     FROM orders o
                                     JOIN users u ON o.customer_id = u.user_id
                                     ORDER BY o.order_date DESC LIMIT 5");
    $recent_orders = $stmt_recent_orders->fetchAll(PDO::FETCH_ASSOC);

    // Sales by Brand (Top 5)
    $stmt_sales_by_brand = $db->query("
        SELECT 
            b.brand_id, 
            b.brand_name, 
            SUM(oi.subtotal_for_item) as total_brand_sales,
            COUNT(DISTINCT oi.order_id) as total_brand_orders
        FROM order_items oi
        JOIN brands b ON oi.brand_id = b.brand_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status NOT IN ('cancelled', 'refunded', 'pending_payment') 
        GROUP BY b.brand_id, b.brand_name
        ORDER BY total_brand_sales DESC
        LIMIT 5 
    ");
    $sales_by_brand = $stmt_sales_by_brand->fetchAll(PDO::FETCH_ASSOC);

    // --- Data for Charts (Last 12 Months) ---
    $chart_months = [];
    $sales_data = [];
    $orders_data = [];
    $users_data_chart = [];

    for ($i = 11; $i >= 0; $i--) {
        $month_year_obj = new DateTime("first day of -$i months");
        $month_year = $month_year_obj->format('Y-m');
        $chart_months[] = $month_year_obj->format('M Y'); // For labels e.g. "Jan 2023"
        
        // Sales for the month
        $stmt_monthly_sales = $db->prepare("SELECT SUM(total_amount) as monthly_total FROM orders WHERE DATE_FORMAT(order_date, '%Y-%m') = :month_year AND order_status NOT IN ('cancelled', 'refunded', 'pending_payment')");
        $stmt_monthly_sales->bindParam(':month_year', $month_year);
        $stmt_monthly_sales->execute();
        $sales_result = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC);
        $sales_data[] = $sales_result['monthly_total'] ?? 0;

        // Orders for the month
        $stmt_monthly_orders = $db->prepare("SELECT COUNT(order_id) as monthly_orders FROM orders WHERE DATE_FORMAT(order_date, '%Y-%m') = :month_year AND order_status NOT IN ('cancelled', 'refunded', 'pending_payment')");
        $stmt_monthly_orders->bindParam(':month_year', $month_year);
        $stmt_monthly_orders->execute();
        $orders_result = $stmt_monthly_orders->fetch(PDO::FETCH_ASSOC);
        $orders_data[] = $orders_result['monthly_orders'] ?? 0;
        
        // New users for the month
        $stmt_monthly_users = $db->prepare("SELECT COUNT(user_id) as monthly_users FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = :month_year");
        $stmt_monthly_users->bindParam(':month_year', $month_year);
        $stmt_monthly_users->execute();
        $users_result = $stmt_monthly_users->fetch(PDO::FETCH_ASSOC);
        $users_data_chart[] = $users_result['monthly_users'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Admin Dashboard - Error fetching data: " . $e->getMessage());
    echo "<div class='admin-message error'>Could not load all dashboard data. Please try again later. Error: " . $e->getMessage() . "</div>";
}

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<div class="stat-cards-container">
    <div class="stat-card total-sales">
        <h3>Total Revenue</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($total_sales, 2)); ?></p>
        <p class="stat-description">From successful orders.</p>
    </div>
    <div class="stat-card total-orders">
        <h3>Total Orders</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_orders)); ?></p>
        <p class="stat-description">Successful orders.</p>
    </div>
    <div class="stat-card average-order"> 
        <h3>Average Order Value</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($average_order_value, 2)); ?></p>
        <p class="stat-description">Per successful order.</p>
    </div>
     <div class="stat-card total-users">
        <h3>Active Users</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_users)); ?></p>
        <p class="stat-description">All active roles.</p>
    </div>
    <div class="stat-card pending-brands">
        <h3>Pending Brands</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($pending_brands_count)); ?></p>
        <p class="stat-description stat-link"><a href="brands.php?filter_approved=0">Manage Brands</a></p>
    </div>
</div>

<div class="admin-section charts-section" style="margin-top: 30px;">
    <h2 class="admin-section-title">Platform Growth (Last 12 Months)</h2>
    <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">
        <div class="chart-container" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.07); height: 350px; position: relative;">
            <h3 style="margin-top:0; text-align:center;">Monthly Sales Revenue</h3>
            <canvas id="salesChart"></canvas>
        </div>
        <div class="chart-container" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.07); height: 350px; position: relative;">
            <h3 style="margin-top:0; text-align:center;">Monthly New Orders</h3>
            <canvas id="ordersChart"></canvas>
        </div>
        <div class="chart-container" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.07); grid-column: 1 / -1; height: 350px; position: relative;">
            <h3 style="margin-top:0; text-align:center;">Monthly New User Registrations</h3>
            <canvas id="usersChart"></canvas>
        </div>
    </div>
</div>


<div class="admin-section-row" style="display: flex; flex-wrap: wrap; gap: 25px; margin-top: 30px;">
    <div class="admin-section recent-orders-section" style="flex: 2 1 500px;">
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
                            <td><a href="order_detail.php?order_id=<?php echo $order['order_id']; ?>">#<?php echo htmlspecialchars($order['order_id']); ?></a></td>
                            <td><?php echo htmlspecialchars($order['customer_username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($order['order_date']))); ?></td>
                            <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                            <td><span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?></span></td>
                            <td class="actions">
                                <a href="order_detail.php?order_id=<?php echo $order['order_id']; ?>" class="btn-view">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align:right; margin-top:10px;"><a href="orders.php">View All Orders &raquo;</a></p>
        <?php else: ?>
            <p class="admin-message info">No recent orders found.</p>
        <?php endif; ?>
    </div>

    <div class="admin-section top-brands-section" style="flex: 1 1 300px;">
        <h2 class="admin-section-title">Top Brands by Sales</h2>
        <?php if (!empty($sales_by_brand)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Brand Name</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales_by_brand as $brand_sale): ?>
                        <tr>
                            <td><a href="edit_brand.php?brand_id=<?php echo $brand_sale['brand_id']; ?>"><?php echo htmlspecialchars($brand_sale['brand_name']); ?></a></td>
                            <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($brand_sale['total_brand_sales'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
             <p style="text-align:right; margin-top:10px;"><a href="brands.php">View All Brands &raquo;</a></p>
        <?php else: ?>
            <p class="admin-message info">No brand sales data available yet.</p>
        <?php endif; ?>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartMonths = <?php echo json_encode($chart_months ?? []); ?>;
    const salesData = <?php echo json_encode($sales_data ?? []); ?>;
    const ordersData = <?php echo json_encode($orders_data ?? []); ?>;
    const usersDataChart = <?php echo json_encode($users_data_chart ?? []); ?>;
    const currencySymbol = '<?php echo CURRENCY_SYMBOL; ?>';

    const defaultChartOptions = {
        responsive: true,
        maintainAspectRatio: false, // Important for respecting container height
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        }
    };

    // Sales Chart
    if (document.getElementById('salesChart')) {
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: chartMonths,
                datasets: [{
                    label: 'Monthly Sales Revenue',
                    data: salesData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: { // Merge with default options and override specific parts
                ...defaultChartOptions,
                scales: {
                    ...defaultChartOptions.scales,
                    y: {
                        ...defaultChartOptions.scales.y,
                        ticks: {
                            callback: function(value, index, values) {
                                return currencySymbol + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    ...defaultChartOptions.plugins,
                    tooltip: {
                        ...defaultChartOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += currencySymbol + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Orders Chart
    if (document.getElementById('ordersChart')) {
        new Chart(document.getElementById('ordersChart'), {
            type: 'line',
            data: {
                labels: chartMonths,
                datasets: [{
                    label: 'Monthly New Orders',
                    data: ordersData,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: defaultChartOptions // Use default options
        });
    }
    
    // New Users Chart
    if (document.getElementById('usersChart')) {
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: chartMonths,
                datasets: [{
                    label: 'Monthly New User Registrations',
                    data: usersDataChart,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: { // Merge with default options and override specific parts
                ...defaultChartOptions,
                scales: {
                    ...defaultChartOptions.scales,
                    y: {
                        ...defaultChartOptions.scales.y,
                        ticks: { 
                            stepSize: 1, // Ensure y-axis shows whole numbers for users
                            precision: 0 // No decimal places for user count
                        } 
                    } 
                }
            }
        });
    }
});
</script>

<?php
include_once 'includes/footer.php'; 
?>
