<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Get user's active bookings
$active_bookings = $conn->prepare("
    SELECT pb.*, ps.space_number, ps.location_name, vc.category_name
    FROM parking_bookings pb 
    JOIN parking_spaces ps ON pb.space_id = ps.id 
    JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE pb.user_id = ? AND pb.booking_status = 'active'
    ORDER BY pb.check_in DESC
");
$active_bookings->bind_param("i", $user_id);
$active_bookings->execute();
$active_bookings = $active_bookings->get_result();

// Get user's booking history
$booking_history = $conn->prepare("
    SELECT pb.*, ps.space_number, ps.location_name
    FROM parking_bookings pb 
    JOIN parking_spaces ps ON pb.space_id = ps.id 
    WHERE pb.user_id = ? AND pb.booking_status IN ('completed', 'cancelled')
    ORDER BY pb.created_at DESC
    LIMIT 5
");
$booking_history->bind_param("i", $user_id);
$booking_history->execute();
$booking_history = $booking_history->get_result();

// Get nearby available spaces
$nearby_spaces = $conn->query("
    SELECT ps.*, vc.category_name, 
           COALESCE(pb_count.current_bookings, 0) as current_bookings
    FROM parking_spaces ps
    JOIN vehicle_categories vc ON ps.category_id = vc.id
    LEFT JOIN (
        SELECT space_id, COUNT(*) as current_bookings
        FROM parking_bookings 
        WHERE booking_status = 'active'
        GROUP BY space_id
    ) pb_count ON ps.id = pb_count.space_id
    WHERE ps.status = 'active' AND ps.is_available = 1
    ORDER BY ps.id
    LIMIT 5
");

// Get availability summary by location with category info (enhanced for details view)
$location_availability = $conn->query("
    SELECT 
        ps.location_name,
        COUNT(*) as total_spaces,
        SUM(ps.is_available = 1) as available_spaces,
        SUM(ps.is_available = 0) as occupied_spaces,
        COUNT(DISTINCT ps.category_id) as category_count,
        GROUP_CONCAT(DISTINCT vc.category_name ORDER BY vc.category_name SEPARATOR ', ') as categories
    FROM parking_spaces ps
    LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE ps.status = 'active'
    GROUP BY ps.location_name
    ORDER BY available_spaces DESC
");

// Get detailed category breakdown for each location
$location_details = [];
$detail_query = $conn->query("
    SELECT 
        ps.location_name,
        vc.category_name,
        COUNT(*) as total_spaces,
        SUM(ps.is_available = 1) as available_spaces,
        SUM(ps.is_available = 0) as occupied_spaces
    FROM parking_spaces ps
    LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE ps.status = 'active'
    GROUP BY ps.location_name, vc.category_name
    ORDER BY ps.location_name, vc.category_name
");

while ($detail = $detail_query->fetch_assoc()) {
    $location_details[$detail['location_name']][] = $detail;
}

// Get parking prediction
$prediction = getParkingPrediction();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

        .welcome-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border: none;
            overflow: hidden;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .stat-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 48px;
            color: rgba(40, 167, 69, 0.2);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 20px 25px;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
        }

        .space-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .space-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .badge-success {
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .btn-book {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            transition: all 0.3s;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            color: white;
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
                <a href="dashboard.php" class="list-group-item active">
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
                <a href="profile.php" class="list-group-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="list-group-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <div class="sidebar-footer text-center py-4">
                <div class="user-info">
                    <i class="fas fa-user-circle fa-2x mb-2"></i>
                    <p class="mb-0"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <small class="text-white-50">Member</small>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h4>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Welcome Message -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
                        <p class="mb-0">Find and book parking spaces easily with EasyPark.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-0"><i class="fas fa-calendar me-2"></i><?php echo date('l, F j, Y'); ?></p>
                        <p class="mb-0"><i class="fas fa-clock me-2"></i><?php echo date('h:i A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-search stat-icon"></i>
                        <h5>Find Parking</h5>
                        <p class="text-muted">Search available spaces near you</p>
                        <a href="search_parking.php" class="btn btn-sm btn-outline-success">Search Now</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-calendar-check stat-icon"></i>
                        <h5>My Bookings</h5>
                        <p class="text-muted">View and manage your bookings</p>
                        <a href="my_bookings.php" class="btn btn-sm btn-outline-success">View Bookings</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-cogs stat-icon"></i>
                        <h5>Manage Bookings</h5>
                        <p class="text-muted">Use the search tab to find and book spaces.</p>
                        <a href="search_parking.php" class="btn btn-sm btn-outline-success">Search Spaces</a>
                    </div>
                </div>
            </div>

            <!-- Parking Prediction Widget -->
            <?php if ($prediction): ?>
            <div class="card mb-4" style="background: linear-gradient(135deg, <?php 
                echo $prediction['severity'] == 'danger' ? '#f8d7da' : 
                     ($prediction['severity'] == 'warning' ? '#fff3cd' : '#d4edda');
            ?> 0%, white 100%); border-left: 5px solid <?php 
                echo $prediction['severity'] == 'danger' ? '#dc3545' : 
                     ($prediction['severity'] == 'warning' ? '#ffc107' : '#28a745');
            ?>;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title mb-2">
                                <i class="fas fa-<?php 
                                    echo $prediction['severity'] == 'danger' ? 'exclamation-circle' : 
                                         ($prediction['severity'] == 'warning' ? 'info-circle' : 'check-circle');
                                ?> me-2"></i><?php echo $prediction['status']; ?>
                            </h5>
                            <p class="card-text mb-2"><?php echo htmlspecialchars($prediction['recommendation'] ?? 'Recommendation not available'); ?></p>
                            <small class="text-muted">
                                <strong>Current:</strong> <?php echo $prediction['available_spaces']; ?> of <?php echo $prediction['total_spaces']; ?> spaces available 
                                (<?php echo $prediction['occupancy_percent']; ?>% occupied)
                            </small>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="h3 mb-0"><?php echo $prediction['available_spaces']; ?></div>
                            <small>Spaces Available</small>
                            <div class="mt-3">
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar <?php 
                                        echo $prediction['severity'] == 'danger' ? 'bg-danger' : 
                                             ($prediction['severity'] == 'warning' ? 'bg-warning' : 'bg-success');
                                    ?>" style="width: <?php echo $prediction['occupancy_percent']; ?>%">
                                        <?php echo $prediction['occupancy_percent']; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Location Availability (Smart Insight - Enhanced) -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-map-marked-alt me-2"></i>Parking Availability by Location
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Total Spaces</th>
                                    <th>Available</th>
                                    <th>Occupied</th>
                                    <th>Vehicle Types</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $location_availability->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['location_name'] ?: 'Unknown'); ?></td>
                                        <td><?php echo intval($item['total_spaces']); ?></td>
                                        <td><span class="badge bg-success"><?php echo intval($item['available_spaces']); ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo intval($item['occupied_spaces']); ?></span></td>
                                        <td><?php echo intval($item['category_count']); ?> types</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo md5($item['location_name']); ?>" aria-expanded="false">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="p-0">
                                            <div class="collapse" id="details-<?php echo md5($item['location_name']); ?>">
                                                <div class="card card-body m-2">
                                                    <h6>Vehicle Type Breakdown for <?php echo htmlspecialchars($item['location_name']); ?></h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Vehicle Type</th>
                                                                    <th>Total Spaces</th>
                                                                    <th>Available</th>
                                                                    <th>Occupied</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (isset($location_details[$item['location_name']])): ?>
                                                                    <?php foreach ($location_details[$item['location_name']] as $detail): ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($detail['category_name'] ?: 'Unassigned'); ?></td>
                                                                            <td><?php echo intval($detail['total_spaces']); ?></td>
                                                                            <td><span class="badge bg-success"><?php echo intval($detail['available_spaces']); ?></span></td>
                                                                            <td><span class="badge bg-danger"><?php echo intval($detail['occupied_spaces']); ?></span></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-muted text-center">No data available</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Active Bookings -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock me-2"></i>Your Active Bookings
                    <a href="my_bookings.php" class="btn btn-sm btn-success float-end">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($active_bookings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Booking #</th>
                                        <th>Space</th>
                                        <th>Location</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['location_name']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($booking['check_in'])); ?></td>
                                            <td><?php echo $booking['expected_check_out'] ? date('d M Y, h:i A', strtotime($booking['expected_check_out'])) : 'Not set'; ?></td>
                                            <td><span class="badge badge-success">Active</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="extendBooking(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-clock"></i> Extend
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted my-4">You have no active bookings.</p>
                        <div class="text-center">
                            <a href="search_parking.php" class="btn btn-success">
                                <i class="fas fa-search me-2"></i>Book a Parking Space
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nearby Available Spaces -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt me-2"></i>Nearby Available Spaces
                    <a href="search_parking.php" class="btn btn-sm btn-success float-end">View All</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php while ($space = $nearby_spaces->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="space-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1">Space <?php echo htmlspecialchars($space['space_number']); ?></h5>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($space['location_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <span class="badge bg-info"><?php echo htmlspecialchars($space['category_name']); ?></span>
                                                <span class="badge bg-success">रू <?php echo $space['price_per_hour']; ?>/hr</span>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-car me-1"></i><?php echo $space['current_bookings']; ?> vehicles parked
                                            </small>
                                        </div>
                                        <a href="book_parking.php?space_id=<?php echo $space['id']; ?>" class="btn btn-book">
                                            Book Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Booking History -->
            <?php if ($booking_history->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i>Recent Booking History
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Space</th>
                                    <th>Date</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $booking_history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($booking['check_in'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($booking['check_out']) {
                                                $hours = calculateDuration($booking['check_in'], $booking['check_out']);
                                                echo $hours . ' hours';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>रू <?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $booking['booking_status'] == 'completed' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Extend Booking Modal -->
    <div class="modal fade" id="extendBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="extend_booking.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="extend_booking_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Extend Until</label>
                            <input type="datetime-local" class="form-control" name="new_check_out" 
                                   min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Additional charges will apply based on the extended duration.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Extend Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function extendBooking(bookingId) {
            document.getElementById('extend_booking_id').value = bookingId;
            new bootstrap.Modal(document.getElementById('extendBookingModal')).show();
        }
    </script>
</body>
</html>