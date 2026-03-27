<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'My Profile';
$page_icon = 'user-circle';

$conn = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user details
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    
    if (empty($full_name)) {
        $error = "Full name cannot be empty";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $phone, $address, $email, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
            $message = "Profile updated successfully!";
            logActivity($user_id, 'update_profile', 'Updated admin profile');
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            logActivity($user_id, 'change_password', 'Changed admin password');
        } else {
            $error = "Error changing password: " . $conn->error;
        }
    }
}

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

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-circle me-2"></i>Profile Information
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">User Type</label>
                            <input type="text" class="form-control" value="Administrator" disabled>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-key me-2"></i>Change Password
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-lock me-2"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
