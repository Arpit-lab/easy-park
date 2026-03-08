<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Manage Users';
$page_icon = 'users';

$conn = getDB();
$message = '';
$error = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $user_type = $_POST['user_type'];
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, user_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $email, $password, $full_name, $phone, $address, $user_type);
        
        if ($stmt->execute()) {
            $message = "User added successfully!";
            logActivity($_SESSION['user_id'], 'add_user', "Added user: $username");
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    }
    
    // Handle Edit User
    if ($_POST['action'] === 'edit') {
        $user_id = $_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, address=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $full_name, $phone, $address, $status, $user_id);
        
        if ($stmt->execute()) {
            $message = "User updated successfully!";
            logActivity($_SESSION['user_id'], 'edit_user', "Edited user ID: $user_id");
        } else {
            $error = "Error updating user: " . $conn->error;
        }
    }
    
    // Handle Delete User
    if ($_POST['action'] === 'delete') {
        $user_id = $_POST['user_id'];
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND user_type='user'");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
            logActivity($_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
        } else {
            $error = "Error deleting user: " . $conn->error;
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users WHERE user_type='user' ORDER BY created_at DESC");

include 'includes/header.php';
?>

<!-- Page Content -->
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

    <!-- Stats Cards (Like Dashboard) -->
    <div class="row mb-4">
        <?php
        $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type='user'")->fetch_assoc()['count'];
        $active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type='user' AND status='active'")->fetch_assoc()['count'];
        $new_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
        ?>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-user-check stat-icon"></i>
                <div class="stat-value"><?php echo $active_users; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-user-plus stat-icon"></i>
                <div class="stat-value">+<?php echo $new_users; ?></div>
                <div class="stat-label">New This Week</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-user-tie stat-icon"></i>
                <div class="stat-value"><?php echo $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type='admin'")->fetch_assoc()['count']; ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <i class="fas fa-list me-2"></i>User Management
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Add New User
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['address'] ?: 'No address'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">User Type</label>
                        <select class="form-control" name="user_type">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="edit_phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
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
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(userId) {
    $.ajax({
        url: 'get_user.php',
        method: 'POST',
        data: {user_id: userId},
        dataType: 'json',
        success: function(user) {
            $('#edit_user_id').val(user.id);
            $('#edit_full_name').val(user.full_name);
            $('#edit_phone').val(user.phone);
            $('#edit_address').val(user.address);
            $('#edit_status').val(user.status);
            $('#editUserModal').modal('show');
        }
    });
}

function deleteUser(userId) {
    $('#delete_user_id').val(userId);
    $('#deleteUserModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>