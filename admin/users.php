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
$users = [];
$message = $_SESSION['user_management_message'] ?? ''; // Display message from actions
unset($_SESSION['user_management_message']); // Clear message after displaying

// Pagination variables (optional, for later enhancement)
// $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// $perPage = 20;
// $offset = ($page - 1) * $perPage;

try {
    // Fetch all users for now. Add pagination later if needed.
    // $stmt = $db->prepare("SELECT user_id, username, email, first_name, last_name, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    // $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    // $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt = $db->query("SELECT user_id, username, email, first_name, last_name, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total users for pagination (if implemented)
    // $totalUsersStmt = $db->query("SELECT COUNT(*) FROM users");
    // $totalUsers = $totalUsersStmt->fetchColumn();
    // $totalPages = ceil($totalUsers / $perPage);

} catch (PDOException $e) {
    error_log("Admin Users - Error fetching users: " . $e->getMessage());
    $message = "<div class='admin-message error'>Could not load users. Please try again later.</div>";
}

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>

<?php if ($message) echo $message; // Display any success/error messages ?>

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
    <?php
        // Pagination links placeholder
        // if ($totalPages > 1) {
        //     echo "<div class='pagination' style='margin-top:20px;'>";
        //     for ($i = 1; $i <= $totalPages; $i++) {
        //         echo "<a href='?page=$i' " . ($page == $i ? "class='active'" : "") . ">$i</a> ";
        //     }
        //     echo "</div>";
        // }
    ?>
<?php else: ?>
    <?php if (empty($message)): // Show "no users" only if no error message was already displayed ?>
    <p class="admin-message info">No users found.</p>
    <?php endif; ?>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>
