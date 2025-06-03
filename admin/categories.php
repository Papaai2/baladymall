<?php
// admin/categories.php - Super Admin: Manage Categories

$admin_base_url = '.'; 
$main_config_path = dirname(__DIR__) . '/src/config/config.php'; 
if (file_exists($main_config_path)) {
    require_once $main_config_path;
} else {
    die("CRITICAL ADMIN CATEGORIES ERROR: Main config.php not found.");
}
require_once 'auth_check.php'; // Ensures user is super_admin

$admin_page_title = "Manage Categories";
include_once 'includes/header.php';

$db = getPDOConnection();
$message = '';

// --- Handle Delete Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id_to_delete = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $csrf_token_form = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);

    if ($category_id_to_delete && $csrf_token_form && hash_equals($_SESSION['csrf_token'], $csrf_token_form)) {
        try {
            $db->beginTransaction();

            // Before deleting, check if this category is a parent to any other categories.
            // If so, you might want to prevent deletion or reassign children.
            // For simplicity, our schema uses ON DELETE SET NULL for parent_category_id,
            // so children will become top-level categories.

            // Products linked via product_category will have their entries deleted due to ON DELETE CASCADE.
            // This might be desired, or you might want to prevent deleting categories with products.
            // For now, we proceed with the cascade.

            // Fetch category image to delete it if it exists
            $stmt_img = $db->prepare("SELECT category_image_url FROM categories WHERE category_id = :cid");
            $stmt_img->bindParam(':cid', $category_id_to_delete, PDO::PARAM_INT);
            $stmt_img->execute();
            $category_image_path = $stmt_img->fetchColumn();


            $stmt_delete = $db->prepare("DELETE FROM categories WHERE category_id = :category_id");
            $stmt_delete->bindParam(':category_id', $category_id_to_delete, PDO::PARAM_INT);
            
            if ($stmt_delete->execute()) {
                // Delete the actual image file
                if ($category_image_path && file_exists(PUBLIC_UPLOADS_PATH . $category_image_path)) {
                    unlink(PUBLIC_UPLOADS_PATH . $category_image_path);
                }
                $db->commit();
                $_SESSION['admin_message'] = "<div class='admin-message success'>Category ID #{$category_id_to_delete} deleted successfully. Associated product links removed and child categories (if any) are now top-level.</div>";
            } else {
                $db->rollBack();
                $_SESSION['admin_message'] = "<div class='admin-message error'>Failed to delete category ID #{$category_id_to_delete}. It might be in use in a way that prevents deletion.</div>";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Admin Categories - Error deleting category: " . $e->getMessage());
            $_SESSION['admin_message'] = "<div class='admin-message error'>An error occurred: " . $e->getMessage() . "</div>";
        }
        // Refresh page
        header("Location: categories.php");
        exit;
    } else {
        $_SESSION['admin_message'] = "<div class='admin-message error'>Invalid request for deletion or CSRF token mismatch.</div>";
        header("Location: categories.php");
        exit;
    }
}


// --- Fetch Categories ---
// Using a self-join to get parent category name
$sql_categories = "SELECT c.*, p.category_name as parent_category_name 
                   FROM categories c
                   LEFT JOIN categories p ON c.parent_category_id = p.category_id
                   ORDER BY c.category_name ASC"; // Or order by hierarchy later
$categories = [];
try {
    $stmt_categories = $db->query($sql_categories);
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Categories - Error fetching categories: " . $e->getMessage());
    $message = "<div class='admin-message error'>Could not load categories.</div>";
}

// Generate a CSRF token for delete forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>

<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_page_title); ?></h1>
<p><a href="add_category.php" class="btn-submit" style="display: inline-block; margin-bottom: 20px; text-decoration:none;">+ Add New Category</a></p>

<?php 
if (isset($_SESSION['admin_message'])) {
    echo $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if ($message) echo $message; 
?>

<?php if (!empty($categories)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Parent Category</th>
                <th>Products Count</th> <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <?php
                // Simple product count (can be performance intensive for many categories, consider optimizing)
                $stmt_count = $db->prepare("SELECT COUNT(*) FROM product_category WHERE category_id = :cid");
                $stmt_count->bindParam(':cid', $category['category_id'], PDO::PARAM_INT);
                $stmt_count->execute();
                $product_count = $stmt_count->fetchColumn();
                ?>
                <tr>
                    <td>
                        <?php 
                        $image_path = $category['category_image_url'] ? htmlspecialchars(PUBLIC_UPLOADS_URL_BASE . $category['category_image_url']) : htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=No+Img');
                        $fallback_image_path = htmlspecialchars(PLACEHOLDER_IMAGE_URL_GENERATOR . '50x50/eee/aaa?text=Error');
                        ?>
                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback_image_path; ?>';">
                    </td>
                    <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($category['category_description'] ?? '', 0, 70)) . (strlen($category['category_description'] ?? '') > 70 ? '...' : ''); ?></td>
                    <td><?php echo htmlspecialchars($category['parent_category_name'] ?? 'N/A (Top Level)'); ?></td>
                    <td><?php echo htmlspecialchars($product_count); ?></td>
                    <td class="actions">
                        <a href="edit_category.php?category_id=<?php echo $category['category_id']; ?>" class="btn-edit">Edit</a>
                        <form action="categories.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete category \'<?php echo htmlspecialchars(addslashes($category['category_name'])); ?>\'? This will remove its link from all products and any child categories will become top-level.');">
                            <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" name="delete_category" class="btn-suspend">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="admin-message info">No categories found. <a href="add_category.php">Add your first category!</a></p>
<?php endif; ?>

<?php
include_once 'includes/footer.php';
?>
