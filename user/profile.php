<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user data
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    $update = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    $update->bind_param("sssi", $full_name, $phone, $address, $user_id);
    
    if ($update->execute()) {
        $message = "Profile updated successfully!";
        $_SESSION['full_name'] = $full_name;
        logActivity($user_id, 'update_profile', 'Updated profile information');
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (password_verify($current, $user['password'])) {
        if ($new === $confirm) {
            if (strlen($new) >= 6) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed, $user_id);
                
                if ($update->execute()) {
                    $message = "Password changed successfully!";
                    logActivity($user_id, 'change_password', 'Changed password');
                } else {
                    $error = "Error changing password: " . $conn->error;
                }
            } else {
                $error = "New password must be at least 6 characters long";
            }
        } else {
            $error = "New passwords do not match";
        }
    } else {
        $error = "Current password is incorrect";
    }
}

// Get user statistics
$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN booking_status = 'active' THEN 1 ELSE 0 END) as active_bookings,
        SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        COALESCE(SUM(total_amount), 0) as total_spent
    FROM parking_bookings
    WHERE user_id = ?
");
$stats->bind_param("i", $user_id);
$stats->execute();
$stats = $stats->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Original EasyPark Theme - Green Gradient */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        /* Sidebar - Original Green Gradient */
        #sidebar-wrapper {
            min-width: 280px;
            max-width: 280px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            transition: all 0.3s;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        #sidebar-wrapper .sidebar-heading {
            padding: 20px;
            font-size: 1.4rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        #sidebar-wrapper .list-group {
            width: 100%;
            padding: 15px 0;
        }

        #sidebar-wrapper .list-group-item {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        #sidebar-wrapper .list-group-item:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(5px);
        }

        #sidebar-wrapper .list-group-item.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-weight: 600;
            border-left: 4px solid #fff;
        }

        #sidebar-wrapper .list-group-item i {
            margin-right: 10px;
            width: 20px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Main Content */
        #page-content-wrapper {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }

        .navbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar h4 {
            margin: 0;
            color: #333;
        }

        .navbar h4 i {
            color: #28a745;
            margin-right: 10px;
        }

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
            border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Card Styles */
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .info-card h5 {
            border-bottom: 2px solid #28a745;
            padding-bottom: 12px;
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }

        .info-card h5 i {
            color: #28a745;
            margin-right: 10px;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        /* Button Styles */
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            color: #333;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        /* Alert Styles */
        .alert-success {
            background: #d4edda;
            border: none;
            border-radius: 10px;
            color: #155724;
            padding: 15px 20px;
        }

        .alert-danger {
            background: #f8d7da;
            border: none;
            border-radius: 10px;
            color: #721c24;
            padding: 15px 20px;
        }

        /* Stats Cards */
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid #dee2e6;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -280px;
            }
            #page-content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-parking me-2"></i> EasyPark User
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="search_parking.php" class="list-group-item">
                    <i class="fas fa-search"></i> Search Parking
                </a>
                <a href="smart_recommendations.php" class="list-group-item">
                    <i class="fas fa-lightbulb"></i> Smart Find
                </a>
                <a href="my_bookings.php" class="list-group-item">
                    <i class="fas fa-calendar-check"></i> My Bookings
                </a>
                <a href="profile.php" class="list-group-item active">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="list-group-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <div class="sidebar-footer text-center">
                <i class="fas fa-user-circle fa-2x mb-2"></i>
                <p class="mb-0"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></p>
                <small>Member</small>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h4>
                    <div class="d-flex align-items-center">
                        <span class="me-3">
                            <i class="fas fa-calendar me-2 text-success"></i><?php echo date('l, F j, Y'); ?>
                        </span>
                        <span>
                            <i class="fas fa-clock me-2 text-success"></i><?php echo date('h:i A'); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <!-- Messages -->
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h3>
                <p class="mb-2"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['active_bookings'] ?? 0; ?></div>
                        <div class="stat-label">Active Bookings</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['completed_bookings'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value">रू <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Edit Profile -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-edit"></i>Edit Profile</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email (cannot be changed)</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Username (cannot be changed)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-lock"></i>Change Password</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" 
                                       minlength="6" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Account Information -->
                    <div class="info-card">
                        <h5><i class="fas fa-info-circle"></i>Account Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Account Status:</strong></td>
                                <td>
                                    <span class="badge bg-success"><?php echo ucfirst($user['status']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($user['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Account Created:</strong></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password match validation
        document.querySelector('input[name="confirm_password"]')?.addEventListener('input', function() {
            const newPass = document.querySelector('input[name="new_password"]').value;
            const confirmPass = this.value;
            
            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
        });
    </script>
</body>
</html>