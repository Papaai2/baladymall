<?php
// brand_admin/index.php - Brand Admin Dashboard

$brand_admin_base_url = '.';
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL BRAND ADMIN DASHBOARD ERROR: Main config.php not found.");
}

require_once 'auth_check.php'; // Ensures user is brand_admin and sets $_SESSION['brand_id']

$brand_admin_page_title = "Brand Dashboard";
include_once 'includes/header.php';

$db = getPDOConnection();

// Get the assigned brand ID from the session
$current_brand_id = $_SESSION['brand_id'];
$current_brand_name = $_SESSION['brand_name'];

// --- Fetch Brand-Specific Stat Card Data ---
$total_brand_sales = 0;
$total_brand_orders = 0;
$total_brand_products = 0;
$average_brand_order_value = 0;
$recent_brand_orders = [];
$top_selling_products = [];

try {
    // Total Sales and Orders for THIS brand (successful orders)
    $stmt_sales_overview = $db->prepare("
        SELECT SUM(oi.subtotal_for_item) as sum_sales, COUNT(DISTINCT oi.order_id) as count_orders
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.brand_id = :brand_id AND o.order_status NOT IN ('cancelled', 'refunded', 'pending_payment')
    ");
    $stmt_sales_overview->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
    $stmt_sales_overview->execute();
    $sales_overview = $stmt_sales_overview->fetch(PDO::FETCH_ASSOC);
    if ($sales_overview) {
        $total_brand_sales = $sales_overview['sum_sales'] ?? 0;
        $total_brand_orders = $sales_overview['count_orders'] ?? 0;
        if ($total_brand_orders > 0) {
            $average_brand_order_value = $total_brand_sales / $total_brand_orders;
        }
    }

    // Total Products for THIS brand
    $stmt_total_products = $db->prepare("SELECT COUNT(product_id) as count_products FROM products WHERE brand_id = :brand_id");
    $stmt_total_products->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
    $stmt_total_products->execute();
    $products_data = $stmt_total_products->fetch(PDO::FETCH_ASSOC);
    $total_brand_products = $products_data['count_products'] ?? 0;

    // Recent Orders for THIS brand (last 5, where at least one item is from this brand)
    $stmt_recent_orders = $db->prepare("
        SELECT DISTINCT o.order_id, o.customer_id, o.order_date, o.total_amount, o.order_status,
                        u.username as customer_username
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN users u ON o.customer_id = u.user_id
        WHERE oi.brand_id = :brand_id
        ORDER BY o.order_date DESC LIMIT 5
    ");
    $stmt_recent_orders->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
    $stmt_recent_orders->execute();
    $recent_brand_orders = $stmt_recent_orders->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Selling Products for THIS brand
    $stmt_top_selling_products = $db->prepare("
        SELECT p.product_id, p.product_name, p.main_image_url, SUM(oi.quantity) as total_quantity_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.brand_id = :brand_id AND o.order_status NOT IN ('cancelled', 'refunded', 'pending_payment')
        GROUP BY p.product_id, p.product_name, p.main_image_url
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ");
    $stmt_top_selling_products->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
    $stmt_top_selling_products->execute();
    $top_selling_products = $stmt_top_selling_products->fetchAll(PDO::FETCH_ASSOC);


    // --- Data for Charts (Last 12 Months) for THIS brand ---
    $chart_months = [];
    $brand_sales_data = [];
    $brand_orders_data = [];

    for ($i = 11; $i >= 0; $i--) {
        $month_year_obj = new DateTime("first day of -$i months");
        $month_year = $month_year_obj->format('Y-m');
        $chart_months[] = $month_year_obj->format('M Y'); // For labels e.g. "Jan 2023"

        // Sales for the month for this brand
        $stmt_monthly_sales = $db->prepare("
            SELECT SUM(oi.subtotal_for_item) as monthly_total
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.brand_id = :brand_id AND DATE_FORMAT(o.order_date, '%Y-%m') = :month_year
            AND o.order_status NOT IN ('cancelled', 'refunded', 'pending_payment')
        ");
        $stmt_monthly_sales->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
        $stmt_monthly_sales->bindParam(':month_year', $month_year);
        $stmt_monthly_sales->execute();
        $sales_result = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC);
        $brand_sales_data[] = $sales_result['monthly_total'] ?? 0;

        // Orders for the month for this brand (distinct orders containing this brand's products)
        $stmt_monthly_orders = $db->prepare("
            SELECT COUNT(DISTINCT oi.order_id) as monthly_orders
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.brand_id = :brand_id AND DATE_FORMAT(o.order_date, '%Y-%m') = :month_year
            AND o.order_status NOT IN ('cancelled', 'refunded', 'pending_payment')
        ");
        $stmt_monthly_orders->bindParam(':brand_id', $current_brand_id, PDO::PARAM_INT);
        $stmt_monthly_orders->bindParam(':month_year', $month_year);
        $stmt_monthly_orders->execute();
        $orders_result = $stmt_monthly_orders->fetch(PDO::FETCH_ASSOC);
        $brand_orders_data[] = $orders_result['monthly_orders'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Brand Admin Dashboard - Error fetching data for brand ID {$current_brand_id}: " . $e->getMessage());
    echo "<div class='brand-admin-message error'>Could not load all dashboard data for your brand. Please try again later. Error: " . $e->getMessage() . "</div>";
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 class="brand-admin-page-title" style="margin-bottom: 0;"><?php echo htmlspecialchars($brand_admin_page_title); ?> for <?php echo htmlspecialchars($current_brand_name); ?></h1>
    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/index.php" class="btn-submit" style="padding: 8px 15px; text-decoration:none; background-color: #6c757d; border-color: #6c757d;">Back to Site</a>
</div>

<div class="stat-cards-container">
    <div class="stat-card total-sales">
        <h3>Total Brand Revenue</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($total_brand_sales, 2)); ?></p>
        <p class="stat-description">From your brand's successful sales.</p>
    </div>
    <div class="stat-card total-orders">
        <h3>Total Brand Orders</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_brand_orders)); ?></p>
        <p class="stat-description">Orders containing your products.</p>
    </div>
    <div class="stat-card average-order">
        <h3>Avg. Order Value (Your Products)</h3>
        <p class="stat-value"><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format($average_brand_order_value, 2)); ?></p>
        <p class="stat-description">Per order with your products.</p>
    </div>
    <div class="stat-card total-products">
        <h3>Total Products</h3>
        <p class="stat-value"><?php echo htmlspecialchars(number_format($total_brand_products)); ?></p>
        <p class="stat-description stat-link"><a href="products.php">Manage Your Products</a></p>
    </div>
</div>

<div class="brand-admin-section charts-section" style="margin-top: 30px;">
    <h2 class="brand-admin-section-title">Your Brand's Performance (Last 12 Months)</h2>
    <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">
        <div class="chart-container" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.07); height: 350px; position: relative;">
            <h3 style="margin-top:0; text-align:center;">Monthly Sales Revenue</h3>
            <canvas id="brandSalesChart"></canvas>
        </div>
        <div class="chart-container" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.07); height: 350px; position: relative;">
            <h3 style="margin-top:0; text-align:center;">Monthly Orders</h3>
            <canvas id="brandOrdersChart"></canvas>
        </div>
    </div>
</div>


<div class="brand-admin-section-row" style="display: flex; flex-wrap: wrap; gap: 25px; margin-top: 30px;">
    <div class="brand-admin-section recent-orders-section" style="flex: 2 1 500px;">
        <h2 class="brand-admin-section-title">Recent Orders (Containing Your Products)</h2>
        <?php if (!empty($recent_brand_orders)): ?>
            <table class="brand-admin-table">
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
                    <?php foreach ($recent_brand_orders as $order): ?>
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
            <p class="brand-admin-message info">No recent orders found for your brand.</p>
        <?php endif; ?>
    </div>

    <div class="brand-admin-section top-products-section" style="flex: 1 1 300px;">
        <h2 class="brand-admin-section-title">Top Selling Products (Your Brand)</h2>
        <?php if (!empty($top_selling_products)): ?>
            <table class="brand-admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_selling_products as $product): ?>
                        <tr>
                            <td>
                                <?php
                                $image_path = '';
                                if (!empty($product['main_image_url'])) {
                                    if (filter_var($product['main_image_url'], FILTER_VALIDATE_URL)) {
                                        $image_path = htmlspecialchars($product['main_image_url']);
                                    } else {
                                        $image_path = htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $product['main_image_url']);
                                    }
                                } else {
                                    $image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '30x30/eee/aaa?text=Img');
                                }
                                $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '30x30/eee/aaa?text=Error');
                                ?>
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 3px; vertical-align: middle; margin-right: 5px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                                <a href="edit_product.php?product_id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($product['total_quantity_sold']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align:right; margin-top:10px;"><a href="products.php">View All Products &raquo;</a></p>
        <?php else: ?>
            <p class="brand-admin-message info">No sales data for your products yet.</p>
        <?php endif; ?>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartMonths = <?php echo json_encode($chart_months ?? []); ?>;
    const brandSalesData = <?php echo json_encode($brand_sales_data ?? []); ?>;
    const brandOrdersData = <?php echo json_encode($brand_orders_data ?? []); ?>;
    const currencySymbol = '<?php echo CURRENCY_SYMBOL; ?>';

    const defaultChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
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

    // Brand Sales Chart
    if (document.getElementById('brandSalesChart')) {
        new Chart(document.getElementById('brandSalesChart'), {
            type: 'line',
            data: {
                labels: chartMonths,
                datasets: [{
                    label: 'Monthly Sales Revenue',
                    data: brandSalesData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
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

    // Brand Orders Chart
    if (document.getElementById('brandOrdersChart')) {
        new Chart(document.getElementById('brandOrdersChart'), {
            type: 'line',
            data: {
                labels: chartMonths,
                datasets: [{
                    label: 'Monthly Orders',
                    data: brandOrdersData,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: defaultChartOptions
        });
    }
});
</script>

<?php
include_once 'includes/footer.php';
?>
