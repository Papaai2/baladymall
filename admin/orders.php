<?php
// admin/orders.php - Super Admin: Manage Orders

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN ORDERS ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Manage Orders";
include_once 'includes/header.php';

$db = getPDOConnection();

// --- Filtering ---
$filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_UNSAFE_RAW);
// You can add more filters like date range, customer ID, etc. later

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page < 1 ? 1 : $page;
$records_per_page = 20; // Number of orders per page
$offset = ($page - 1) * $records_per_page;

// --- Build Query for Orders ---
$sql_orders = "SELECT o.order_id, o.order_date, o.total_amount, o.order_status, 
                      u.user_id as customer_user_id, u.username as customer_username, 
                      CONCAT(u.first_name, ' ', u.last_name) as customer_full_name
               FROM orders o
               JOIN users u ON o.customer_id = u.user_id";
$sql_count = "SELECT COUNT(o.order_id) FROM orders o JOIN users u ON o.customer_id = u.user_id";

$where_clauses = [];
$params = [];

if (!empty($filter_status)) {
    $where_clauses[] = "o.order_status = :status";
    $params[':status'] = $filter_status;
}
// Example for date filter (add corresponding HTML inputs)
// $filter_date_from = filter_input(INPUT_GET, 'date_from', FILTER_UNSAFE_RAW);
// $filter_date_to = filter_input(INPUT_GET, 'date_to', FILTER_UNSAFE_RAW);
// if (!empty($filter_date_from)) {
//     $where_clauses[] = "DATE(o.order_date) >= :date_from";
//     $params[':date_from'] = $filter_date_from;
// }
// if (!empty($filter_date_to)) {
//     $where_clauses[] = "DATE(o.order_date) <= :date_to";
//     $params[':date_to'] = $filter_date_to;
// }


if (!empty($where_clauses)) {
    $sql_orders .= " WHERE " . implode(" AND ", $where_clauses);
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_orders .= " ORDER BY o.order_date DESC LIMIT :limit OFFSET :offset";

// --- Fetch Total Records for Pagination ---
$total_records = 0;
try {
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin Orders - Error fetching order count: " . $e->getMessage());
    echo "<div class='admin-message error'>Error fetching order count.</div>";
}
$total_pages = ceil($total_records / $records_per_page);

// --- Fetch Orders for Current Page ---
$orders = [];
if ($total_records > 0 || empty($params)) {
    try {
        $stmt_orders = $db->prepare($sql_orders);
        foreach ($params as $key => $val) {
            $stmt_orders->bindValue($key, $val);
        }
        $stmt_orders->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt_orders->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Admin Orders - Error fetching orders: " . $e->getMessage());
        echo "<div class='admin-message error'>Error fetching orders. Details: " . $e->getMessage() . "</div>";
    }
}

// Order statuses for filter dropdown (from ENUM in DB)
// This can be hardcoded or fetched dynamically if preferred
$order_statuses_available = ['pending_payment', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<?php
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']); 
}
?>

<!-- Filter Form -->
<div class="admin-filters">
    <form action="orders.php" method="GET">
        <label for="filter_status">Filter by Status:</label>
        <select name="filter_status" id="filter_status">
            <option value="">-- All Statuses --</option>
            <?php foreach ($order_statuses_available as $status_val): ?>
                <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ($filter_status == $status_val) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status_val))); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Add more filters here (e.g., date range) -->
        <!-- 
        <label for="date_from">Date From:</label>
        <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>">
        <label for="date_to">Date To:</label>
        <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>">
        -->

        <button type="submit">Filter</button>
        <a href="orders.php" style="margin-left: 10px;">Clear Filters</a>
    </form>
</div>


<?php if (!empty($orders)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Order Date</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                    <td>
                        <?php echo htmlspecialchars(trim($order['customer_full_name']) ?: $order['customer_username']); ?>
                        (ID: <?php echo htmlspecialchars($order['customer_user_id']); ?>)
                    </td>
                    <td><?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($order['order_date']))); ?></td>
                    <td><?php echo CURRENCY_SYMBOL . htmlspecialchars(number_format((float)$order['total_amount'], 2)); ?></td>
                    <td>
                        <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="order_detail.php?order_id=<?php echo $order['order_id']; ?>" class="btn-view">View Details</a>
                        <?php // Add more actions like 'Update Status' if needed directly here or on detail page ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination Links -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php 
                // Build query string for pagination, preserving filters
                $pagination_query_params = [];
                if (!empty($filter_status)) $pagination_query_params['filter_status'] = $filter_status;
                // Add other filters here:
                // if (!empty($filter_date_from)) $pagination_query_params['date_from'] = $filter_date_from;
                // if (!empty($filter_date_to)) $pagination_query_params['date_to'] = $filter_date_to;
                $pagination_query_string = http_build_query($pagination_query_params);
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&<?php echo $pagination_query_string; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">&laquo; Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&<?php echo $pagination_query_string; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px; <?php if ($i == $page) echo 'background-color: #007bff; color: white;'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&<?php echo $pagination_query_string; ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php elseif (empty($params) && $total_records == 0): ?>
    <p class="admin-message info">No orders found yet.</p>
<?php else: ?>
     <p class="admin-message info">No orders found matching your current filters.</p>
<?php endif; ?>


<?php
include_once 'includes/footer.php';
?>
