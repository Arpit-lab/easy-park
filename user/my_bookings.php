<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Get all user bookings
$bookings = $conn->prepare("
    SELECT pb.*, ps.space_number, ps.location_name, ps.address, ps.price_per_hour,
           DATEDIFF(pb.expected_check_out, pb.check_in) as days,
           TIMEDIFF(pb.expected_check_out, pb.check_in) as duration
    FROM parking_bookings pb
    JOIN parking_spaces ps ON pb.space_id = ps.id
    WHERE pb.user_id = ?
    ORDER BY pb.created_at DESC
");
$bookings->bind_param("i", $user_id);
$bookings->execute();
$bookings = $bookings->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
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

        /* Card Styles */
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

        .card-header i {
            color: #28a745;
            margin-right: 10px;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-top: none;
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Button Styles */
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85rem;
            border-radius: 8px;
            margin: 0 2px;
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-info {
            background: #17a2b8;
            border: none;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }

        /* Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 15px 20px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }

        /* DataTables Customization */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            border: none !important;
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #28a745 !important;
            color: white !important;
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
                <a href="my_bookings.php" class="list-group-item active">
                    <i class="fas fa-calendar-check"></i> My Bookings
                </a>

                <a href="profile.php" class="list-group-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="list-group-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <div class="sidebar-footer text-center">
                <i class="fas fa-user-circle fa-2x mb-2"></i>
                <p class="mb-0"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                <small>Member</small>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>My Bookings</h4>
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

            <!-- Bookings List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>Your Parking History
                </div>
                <div class="card-body">
                    <?php if ($bookings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table id="bookingsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking #</th>
                                        <th>Space</th>
                                        <th>Location</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Duration</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $bookings->fetch_assoc()): 
                                        $status_class = '';
                                        if ($booking['booking_status'] == 'active') {
                                            $status_class = 'status-active';
                                        } elseif ($booking['booking_status'] == 'completed') {
                                            $status_class = 'status-completed';
                                        } else {
                                            $status_class = 'status-cancelled';
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['location_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($booking['address']); ?></small>
                                            </td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($booking['check_in'])); ?></td>
                                            <td>
                                                <?php if ($booking['expected_check_out']): ?>
                                                    <?php echo date('d M Y, h:i A', strtotime($booking['expected_check_out'])); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($booking['check_in'] && $booking['expected_check_out']) {
                                                    $hours = calculateDuration($booking['check_in'], $booking['expected_check_out']);
                                                    echo round($hours, 1) . ' hours';
                                                }
                                                ?>
                                            </td>
                                            <td><strong>रू <?php echo number_format($booking['total_amount'] ?? 0, 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($booking['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($booking['booking_status'] == 'active'): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="extendBooking(<?php echo $booking['id']; ?>)">
                                                        <i class="fas fa-clock"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Bookings Found</h5>
                            <p class="text-muted">You haven't made any parking bookings yet.</p>
                            <a href="search_parking.php" class="btn btn-success">
                                <i class="fas fa-search me-2"></i>Find Parking
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Extend Booking Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
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
                            <label class="form-label">New Check-out Date & Time</label>
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

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this booking?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="cancel_booking_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn btn-danger" onclick="confirmCancel()">Yes, Cancel Booking</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#bookingsTable').DataTable({
                pageLength: 10,
                order: [[3, 'desc']]
            });
        });

        function extendBooking(bookingId) {
            $('#extend_booking_id').val(bookingId);
            $('#extendModal').modal('show');
        }

        function cancelBooking(bookingId) {
            $('#cancel_booking_id').val(bookingId);
            $('#cancelModal').modal('show');
        }

        function confirmCancel() {
            const bookingId = $('#cancel_booking_id').val();
            
            $.ajax({
                url: '../api/booking_api.php',
                method: 'POST',
                data: {
                    action: 'cancel_booking',
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                }
            });
        }

        function viewDetails(bookingId) {
            $('#detailsContent').html('Loading...');
            $('#detailsModal').modal('show');
            
            $.ajax({
                url: 'booking_details.php',
                method: 'POST',
                data: {booking_id: bookingId},
                success: function(response) {
                    $('#detailsContent').html(response);
                }
            });
        }
    </script>
</body>
</html>