<?php
// admin/includes/header.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyPark Admin - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- OpenStreetMap with Leaflet (100% FREE - No API keys needed) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        /* Additional fix for sidebar */
        #sidebar-wrapper {
            overflow-y: auto;
            padding-bottom: 20px;
        }
        
        .sidebar-footer {
            position: relative;
            bottom: auto;
            margin-top: 20px;
        }
        
        .user-info i {
            font-size: 2rem;
            color: #ffd700;
            margin-bottom: 5px;
        }
        
        .user-info p {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .user-info small {
            opacity: 0.7;
            font-size: 0.8rem;
        }
        
        /* Remove any duplicate text */
        .list-group-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-parking"></i> EasyPark Admin
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage_users.php" class="list-group-item <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="manage_categories.php" class="list-group-item <?php echo $current_page == 'manage_categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Vehicle Categories
                </a>
                <a href="manage_vehicles.php" class="list-group-item <?php echo $current_page == 'manage_vehicles.php' ? 'active' : ''; ?>">
                    <i class="fas fa-car"></i> Manage Vehicles
                </a>
                <a href="manage_spaces.php" class="list-group-item <?php echo $current_page == 'manage_spaces.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i> Parking Spaces
                </a>
                <a href="incoming_vehicle.php" class="list-group-item <?php echo $current_page == 'incoming_vehicle.php' ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-right"></i> Incoming Vehicle
                </a>
                <a href="outgoing_vehicle.php" class="list-group-item <?php echo $current_page == 'outgoing_vehicle.php' ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-left"></i> Outgoing Vehicle
                </a>
                <a href="search_vehicle.php" class="list-group-item <?php echo $current_page == 'search_vehicle.php' ? 'active' : ''; ?>">
                    <i class="fas fa-search"></i> Search Vehicle
                </a>
                <a href="analytics.php" class="list-group-item <?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
                <a href="anomaly_alerts.php" class="list-group-item <?php echo $current_page == 'anomaly_alerts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i> Anomaly Alerts
                </a>
                <a href="smart_recommendations.php" class="list-group-item <?php echo $current_page == 'smart_recommendations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-lightbulb"></i> Smart Recommendations
                </a>
            </div>
            
            <!-- User Info Footer (Only Once) -->
            <div class="sidebar-footer">
                <div class="user-info text-center">
                    <i class="fas fa-user-circle"></i>
                    <p class="mb-0"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></p>
                    <small>Administrator</small>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <h4><i class="fas fa-<?php echo $page_icon ?? 'tachometer-alt'; ?> me-2"></i><?php echo $page_title ?? 'Dashboard'; ?></h4>
                    <div class="d-flex align-items-center">
                        <div class="dropdown me-3">
                            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php
                                // Get unread anomaly alerts count
                                $conn = getDB();
                                $alert_count = $conn->query("SELECT COUNT(*) as count FROM anomaly_alerts WHERE status = 'new'")->fetch_assoc()['count'];
                                if ($alert_count > 0):
                                ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $alert_count; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>