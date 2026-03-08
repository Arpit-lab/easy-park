<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get space ID from URL
$space_id = isset($_GET['space_id']) ? intval($_GET['space_id']) : 0;

if ($space_id == 0) {
    header('Location: search_parking.php');
    exit();
}

// Get space details
$space_query = $conn->prepare("
    SELECT ps.*, vc.category_name 
    FROM parking_spaces ps
    JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE ps.id = ? AND ps.status = 'active'
");
$space_query->bind_param("i", $space_id);
$space_query->execute();
$space = $space_query->get_result()->fetch_assoc();

if (!$space) {
    header('Location: search_parking.php');
    exit();
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_in = $_POST['check_in'] . ' ' . $_POST['check_in_time'] . ':00';
    $check_out = $_POST['check_out'] . ' ' . $_POST['check_out_time'] . ':00';
    $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
    
    // Validate dates
    if (strtotime($check_in) >= strtotime($check_out)) {
        $error = "Check-out time must be after check-in time";
    } elseif (strtotime($check_in) < time()) {
        $error = "Check-in time cannot be in the past";
    } else {
        // Check availability
        if (checkSpaceAvailability($space_id, $check_in, $check_out)) {
            // Calculate amount
            $hours = calculateDuration($check_in, $check_out);
            $amount = $hours * $space['price_per_hour'];
            
            // Create booking
            $booking_number = generateBookingNumber();
            
            $stmt = $conn->prepare("
                INSERT INTO parking_bookings 
                (booking_number, user_id, space_id, vehicle_number, check_in, expected_check_out, total_amount, booking_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param("siisssd", $booking_number, $user_id, $space_id, $vehicle_number, $check_in, $check_out, $amount);
            
            if ($stmt->execute()) {
                $booking_id = $conn->insert_id;
                
                // Update space availability
                $conn->query("UPDATE parking_spaces SET is_available = 0 WHERE id = $space_id");
                
                $message = "Booking successful! Your booking number is: $booking_number";
                logActivity($user_id, 'book_space', "Booked space $space_id");
            } else {
                $error = "Error creating booking: " . $conn->error;
            }
        } else {
            $error = "Space is not available for the selected time period";
        }
    }
}

// Get user's vehicles (if any)
$vehicles = $conn->prepare("SELECT vehicle_number FROM vehicles WHERE owner_email = ? OR owner_phone = ? LIMIT 5");
$vehicles->bind_param("ss", $_SESSION['username'], $_SESSION['username']);
$vehicles->execute();
$vehicles = $vehicles->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Parking - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .space-detail-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .price-display {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .booking-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
                <a href="my_bookings.php" class="list-group-item">
                    <i class="fas fa-calendar-check"></i> My Bookings
                </a>
                <a href="add_parking_space.php" class="list-group-item">
                    <i class="fas fa-plus-circle"></i> Add Parking Space
                </a>
                <a href="profile.php" class="list-group-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="list-group-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Book Parking Space</h4>
                </div>
            </nav>

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
                <div class="col-md-5">
                    <div class="space-detail-card">
                        <h3>Space <?php echo htmlspecialchars($space['space_number']); ?></h3>
                        <p class="mb-2">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <?php echo htmlspecialchars($space['location_name']); ?>
                        </p>
                        <p class="mb-3"><?php echo htmlspecialchars($space['address']); ?></p>
                        
                        <div class="row mt-4">
                            <div class="col-6">
                                <small>Category</small>
                                <div class="h5"><?php echo htmlspecialchars($space['category_name']); ?></div>
                            </div>
                            <div class="col-6">
                                <small>Price</small>
                                <div class="price-display">रू <?php echo $space['price_per_hour']; ?></div>
                                <small>per hour</small>
                            </div>
                        </div>
                        
                        <!-- Demand Prediction -->
                        <div class="mt-4 p-3 bg-white bg-opacity-25 rounded">
                            <small><i class="fas fa-chart-line me-1"></i>Demand Prediction</small>
                            <div id="demandPrediction">Loading...</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="booking-form">
                        <h4 class="mb-4">Booking Details</h4>
                        
                        <form method="POST" action="" id="bookingForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-in Date</label>
                                    <input type="date" class="form-control" name="check_in" 
                                           id="check_in" min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-in Time</label>
                                    <input type="time" class="form-control" name="check_in_time" 
                                           id="check_in_time" value="<?php echo date('H:i'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-out Date</label>
                                    <input type="date" class="form-control" name="check_out" 
                                           id="check_out" min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-out Time</label>
                                    <input type="time" class="form-control" name="check_out_time" 
                                           id="check_out_time" value="<?php echo date('H:i', strtotime('+2 hours')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Vehicle Number</label>
                                <input type="text" class="form-control" name="vehicle_number" 
                                       id="vehicle_number" list="vehicles" required>
                                <datalist id="vehicles">
                                    <?php while ($v = $vehicles->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($v['vehicle_number']); ?>">
                                    <?php endwhile; ?>
                                </datalist>
                            </div>
                            
                            <!-- Cost Calculator -->
                            <div class="alert alert-info" id="costCalculator">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Duration:</strong> <span id="duration">0</span> hours
                                    </div>
                                    <div class="col-6">
                                        <strong>Total Cost:</strong> <span id="totalCost">रू 0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the terms and conditions
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-2"></i>Confirm Booking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const pricePerHour = <?php echo $space['price_per_hour']; ?>;
        
        $(document).ready(function() {
            calculateCost();
            getDemandPrediction();
        });
        
        $('#check_in, #check_in_time, #check_out, #check_out_time').on('change', function() {
            calculateCost();
        });
        
        function calculateCost() {
            const checkIn = new Date($('#check_in').val() + 'T' + $('#check_in_time').val());
            const checkOut = new Date($('#check_out').val() + 'T' + $('#check_out_time').val());
            
            if (checkIn && checkOut && checkOut > checkIn) {
                const hours = (checkOut - checkIn) / (1000 * 60 * 60);
                const cost = hours * pricePerHour;
                
                $('#duration').text(hours.toFixed(1));
                $('#totalCost').text('रू ' + cost.toFixed(2));
            }
        }
        
        function getDemandPrediction() {
            $.ajax({
                url: '../api/predict_demand.php',
                method: 'POST',
                data: {
                    space_id: <?php echo $space_id; ?>,
                    date: $('#check_in').val(),
                    hour: $('#check_in_time').val().split(':')[0]
                },
                success: function(response) {
                    if (response.success) {
                        $('#demandPrediction').html(`
                            <div class="h4">${response.demand}%</div>
                            <small>Expected occupancy at check-in time</small>
                        `);
                    }
                }
            });
        }
        
        // Form validation
        $('#bookingForm').on('submit', function(e) {
            const checkIn = new Date($('#check_in').val() + 'T' + $('#check_in_time').val());
            const checkOut = new Date($('#check_out').val() + 'T' + $('#check_out_time').val());
            
            if (checkOut <= checkIn) {
                e.preventDefault();
                alert('Check-out time must be after check-in time');
            }
            
            if (checkIn < new Date()) {
                e.preventDefault();
                alert('Check-in time cannot be in the past');
            }
        });
    </script>
</body>
</html>