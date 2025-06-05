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
$search_order_id = filter_input(INPUT_GET, 'search_order_id', FILTER_VALIDATE_INT);

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
if ($search_order_id) {
    $where_clauses[] = "o.order_id = :order_id_search";
    $params[':order_id_search'] = $search_order_id;
}


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
if ($total_records > 0 || (empty($params) && empty($where_clauses))) { // Attempt to fetch if records exist or no filters applied
    try {
        $stmt_orders = $db->prepare($sql_orders);
        foreach ($params as $key => $val) {
            $stmt_orders->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_orders->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt_orders->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Admin Orders - Error fetching orders: " . $e->getMessage());
        echo "<div class='admin-message error'>Error fetching orders.</div>"; // FIX: Removed raw error message
    }
}

// Order statuses for filter dropdown (from ENUM in DB)
$order_statuses_available = ['pending_payment', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<?php
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
?>

<div class="admin-filters">
    <form action="orders.php" method="GET">
        <label for="filter_status">Filter by Status:</label>
        <select name="filter_status" id="filter_status">
            <option value="">-- All Statuses --</option>
            <?php foreach ($order_statuses_available as $status_val): ?>
                <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ((string)$filter_status === (string)$status_val) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status_val))); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="search_order_id">Search by Order ID:</label>
        <input type="number" name="search_order_id" id="search_order_id" value="<?php echo htmlspecialchars($search_order_id ?? ''); ?>" placeholder="Enter Order ID">

        <button type="submit">Filter/Search</button>
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
                        <?php echo htmlspecialchars(trim(($order['customer_full_name'] ?? '') ?: ($order['customer_username'] ?? 'N/A'))); ?>
                        (ID: <?php echo htmlspecialchars($order['customer_user_id'] ?? 'N/A'); ?>)
                    </td>
                    <td><?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($order['order_date']))); ?></td>
                    <td><?php echo htmlspecialchars(CURRENCY_SYMBOL ?? '') . htmlspecialchars(number_format((float)$order['total_amount'], 2)); ?></td>
                    <td>
                        <span class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $order['order_status']))); ?>">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="order_detail.php?order_id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn-view">View Details</a>
                        <?php // Add more actions like 'Update Status' if needed directly here or on detail page ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php
                // Build query string for pagination, preserving filters
                $pagination_query_params = [];
                if (!empty($filter_status)) $pagination_query_params['filter_status'] = $filter_status;
                if ($search_order_id) $pagination_query_params['search_order_id'] = $search_order_id;
                $pagination_query_string = http_build_query($pagination_query_params);
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo htmlspecialchars($page - 1); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">&laquo; Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo htmlspecialchars($i); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px; <?php if ($i == $page) echo 'background-color: #007bff; color: white;'; ?>">
                    <?php echo htmlspecialchars($i); ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo htmlspecialchars($page + 1); ?>&<?php echo htmlspecialchars($pagination_query_string); ?>" style="padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px;">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php elseif ($total_records == 0 && empty($search_order_id) && empty($filter_status)): ?>
    <p class="admin-message info">No orders found yet.</p>
<?php else: ?>
     <p class="admin-message info">No orders found matching your current filters.</p>
<?php endif; ?>


<?php
include_once 'includes/footer.php';
?>