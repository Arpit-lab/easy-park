<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Vehicle Categories';
$page_icon = 'tags';

$conn = getDB();
$message = '';
$error = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $daily_rate = floatval($_POST['daily_rate']);
        
        $stmt = $conn->prepare("INSERT INTO vehicle_categories (category_name, description, hourly_rate, daily_rate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdd", $category_name, $description, $hourly_rate, $daily_rate);
        
        if ($stmt->execute()) {
            $message = "Category added successfully!";
            logActivity($_SESSION['user_id'], 'add_category', "Added category: $category_name");
        } else {
            $error = "Error adding category: " . $conn->error;
        }
    }
    
    // Handle Edit Category
    if ($_POST['action'] === 'edit') {
        $category_id = $_POST['category_id'];
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $daily_rate = floatval($_POST['daily_rate']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE vehicle_categories SET category_name=?, description=?, hourly_rate=?, daily_rate=?, status=? WHERE id=?");
        $stmt->bind_param("ssddsi", $category_name, $description, $hourly_rate, $daily_rate, $status, $category_id);
        
        if ($stmt->execute()) {
            $message = "Category updated successfully!";
            logActivity($_SESSION['user_id'], 'edit_category', "Edited category ID: $category_id");
        } else {
            $error = "Error updating category: " . $conn->error;
        }
    }
    
    // Handle Delete Category
    if ($_POST['action'] === 'delete') {
        $category_id = $_POST['category_id'];
        
        // Check if category is in use
        $check = $conn->prepare("SELECT COUNT(*) as count FROM vehicles WHERE category_id = ?");
        $check->bind_param("i", $category_id);
        $check->execute();
        $result = $check->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete category - it is being used by vehicles.";
        } else {
            $stmt = $conn->prepare("DELETE FROM vehicle_categories WHERE id=?");
            $stmt->bind_param("i", $category_id);
            
            if ($stmt->execute()) {
                $message = "Category deleted successfully!";
                logActivity($_SESSION['user_id'], 'delete_category', "Deleted category ID: $category_id");
            } else {
                $error = "Error deleting category: " . $conn->error;
            }
        }
    }
}

// Get all categories
$categories = $conn->query("SELECT * FROM vehicle_categories ORDER BY id DESC");

include 'includes/header.php';
?>

<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <?php
        $total_categories = $conn->query("SELECT COUNT(*) as count FROM vehicle_categories")->fetch_assoc()['count'];
        $active_categories = $conn->query("SELECT COUNT(*) as count FROM vehicle_categories WHERE status='active'")->fetch_assoc()['count'];
        $avg_hourly = $conn->query("SELECT AVG(hourly_rate) as avg FROM vehicle_categories")->fetch_assoc()['avg'];
        $max_category = $conn->query("SELECT category_name FROM vehicle_categories ORDER BY hourly_rate DESC LIMIT 1")->fetch_assoc();
        ?>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-tags stat-icon"></i>
                <div class="stat-value"><?php echo $total_categories; ?></div>
                <div class="stat-label">Total Categories</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-value"><?php echo $active_categories; ?></div>
                <div class="stat-label">Active Categories</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-dollar-sign stat-icon"></i>
                <div class="stat-value">रू <?php echo number_format($avg_hourly, 2); ?></div>
                <div class="stat-label">Avg Hourly Rate</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-star stat-icon"></i>
                <div class="stat-value"><?php echo $max_category['category_name'] ?? 'N/A'; ?></div>
                <div class="stat-label">Highest Rate</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <i class="fas fa-list me-2"></i>Vehicle Categories Management
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add New Category
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title"><?php echo htmlspecialchars($category['category_name']); ?></h5>
                                    <span class="badge badge-<?php echo $category['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </div>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($category['description'] ?: 'No description'); ?></p>
                                
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <div class="text-center p-2" style="background: #f8f9fa; border-radius: 10px;">
                                            <small class="text-muted">Per Hour</small>
                                            <h5 class="mb-0 text-success">रू <?php echo number_format($category['hourly_rate'], 2); ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2" style="background: #f8f9fa; border-radius: 10px;">
                                            <small class="text-muted">Per Day</small>
                                            <h5 class="mb-0 text-primary">रू <?php echo number_format($category['daily_rate'], 2); ?></h5>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 d-flex justify-content-end">
                                    <button class="btn btn-sm btn-primary me-2" onclick="editCategory(<?php echo $category['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hourly Rate (रू) *</label>
                            <input type="number" step="0.01" class="form-control" name="hourly_rate" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Daily Rate (रू) *</label>
                            <input type="number" step="0.01" class="form-control" name="daily_rate" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hourly Rate (रू)</label>
                            <input type="number" step="0.01" class="form-control" name="hourly_rate" id="edit_hourly_rate" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Daily Rate (रू)</label>
                            <input type="number" step="0.01" class="form-control" name="daily_rate" id="edit_daily_rate" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this category? This action cannot be undone.</p>
                <p class="text-danger"><i class="fas fa-info-circle me-1"></i>Categories with vehicles assigned cannot be deleted.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(categoryId) {
    $.ajax({
        url: 'get_category.php',
        method: 'POST',
        data: {category_id: categoryId},
        dataType: 'json',
        success: function(category) {
            $('#edit_category_id').val(category.id);
            $('#edit_category_name').val(category.category_name);
            $('#edit_description').val(category.description);
            $('#edit_hourly_rate').val(category.hourly_rate);
            $('#edit_daily_rate').val(category.daily_rate);
            $('#edit_status').val(category.status);
            $('#editCategoryModal').modal('show');
        }
    });
}

function deleteCategory(categoryId) {
    $('#delete_category_id').val(categoryId);
    $('#deleteCategoryModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>