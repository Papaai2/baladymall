<?php
// admin/users.php - Super Admin User Management (List Users)

$admin_base_url = '.'; // Current directory is admin root for this page
$main_config_path = dirname(__DIR__) . '/src/config/config.php';
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN USERS ERROR: Main config.php not found.");
}
require_once 'auth_check.php';

$admin_page_title = "Manage Users";
include_once 'includes/header.php';

$db = getPDOConnection();
$message = $_SESSION['user_management_message'] ?? ''; // Display message from actions
unset($_SESSION['user_management_message']); // Clear message after displaying

// --- Filtering and Search ---
$search_term = filter_input(INPUT_GET, 'search_term', FILTER_UNSAFE_RAW);
$filter_role = filter_input(INPUT_GET, 'filter_role', FILTER_UNSAFE_RAW);
$filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_UNSAFE_RAW);

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page < 1 ? 1 : $page;
$records_per_page = 20; // Number of users per page
$offset = ($page - 1) * $records_per_page;

// --- Build Query for Users ---
$sql_users = "SELECT user_id, username, email, first_name, last_name, role, is_active, created_at FROM users";
$sql_count = "SELECT COUNT(user_id) FROM users";

$where_clauses = [];
$params = [];

if (!empty($search_term)) {
    $where_clauses[] = "(username LIKE :search_term OR email LIKE :search_term)";
    $params[':search_term'] = '%' . $search_term . '%';
}
if (!empty($filter_role)) {
    $where_clauses[] = "role = :filter_role";
    $params[':filter_role'] = $filter_role;
}
if ($filter_status === 'active') {
    $where_clauses[] = "is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "is_active = 0";
}

if (!empty($where_clauses)) {
    $sql_users .= " WHERE " . implode(" AND ", $where_clauses);
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_users .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// --- Fetch Total Records for Pagination ---
$total_records = 0;
try {
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin Users - Error fetching user count: " . $e->getMessage());
    echo "<div class='admin-message error'>Error fetching user count.</div>";
}
$total_pages = ceil($total_records / $records_per_page);

// --- Fetch Users for Current Page ---
$users = [];
if ($total_records > 0 || empty($params)) {
    try {
        $stmt_users = $db->prepare($sql_users);
        foreach ($params as $key => $val) {
            $stmt_users->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_users->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt_users->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt_users->execute();
        $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Admin Users - Error fetching users: " . $e->getMessage());
        echo "<div class='admin-message error'>Error fetching users. Details: " . $e->getMessage() . "</div>";
    }
}

// Available roles for filter dropdown (must match DB ENUM)
$available_roles_filter = ['customer', 'brand_admin', 'super_admin'];

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 class="admin-page-title" style="margin-bottom: 0;"><?php echo htmlspecialchars($admin_page_title); ?></h1>
    <a href="add_user.php" class="btn-submit" style="padding: 8px 15px; text-decoration:none;">+ Add New User</a>
</div>


<?php if ($message) echo $message; // Display any success/error messages ?>

<!-- Filter and Search Form -->
<div class="admin-filters">
    <form action="users.php" method="GET">
        <label for="search_term">Search (Username/Email):</label>
        <input type="text" name="search_term" id="search_term" value="<?php echo htmlspecialchars($search_term ?? ''); ?>" placeholder="Username or Email">

        <label for="filter_role">Filter by Role:</label>
        <select name="filter_role" id="filter_role">
            <option value="">-- All Roles --</option>
            <?php foreach ($available_roles_filter as $role_val): ?>
                <option value="<?php echo htmlspecialchars($role_val); ?>" <?php echo ($filter_role === $role_val) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role_val))); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="filter_status">Filter by Status:</label>
        <select name="filter_status" id="filter_status">
            <option value="">-- All Statuses --</option>
            <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>

        <button type="submit">Filter/Search</button>
        <a href="users.php" style="margin-left: 10px;">Clear Filters</a>
    </form>
</div>


<?php if (!empty($users)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                    <td>
                        <?php if ($user['is_active']): ?>
                            <span style="color: green;">Active</span>
                        <?php else: ?>
                            <span style="color: red;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(date("M j, Y", strtotime($user['created_at']))); ?></td>
                    <td class="actions">
                        <a href="edit_user.php?user_id=<?php echo $user['user_id']; ?>" class="btn-edit">Edit</a>
                        <?php // Add other actions like activate/deactivate, delete later ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination Links -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php
                // Build query string for pagination, preserving filters and search
                $pagination_query_params = [];
                if (!empty($search_term)) $pagination_query_params['search_term'] = $search_term;
                if (!empty($filter_role)) $pagination_query_params['filter_role'] = $filter_role;
                if (!empty($filter_status)) $pagination_query_params['filter_status'] = $filter_status;
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
    <p class="admin-message info">No users found.</p>
<?php else: ?>
    <p class="admin-message info">No users found matching your current filters.</p>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>
