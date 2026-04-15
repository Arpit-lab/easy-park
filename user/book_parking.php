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
    $check_in_date = $_POST['check_in'];
    $check_in_time = $_POST['check_in_time'];
    $check_out_date = $_POST['check_out'];
    $check_out_time = $_POST['check_out_time'];
    $vehicle_number = strtoupper(trim($_POST['vehicle_number']));

    $check_in_dt = new DateTime($check_in_date . ' ' . $check_in_time . ':00');
    $check_out_dt = new DateTime($check_out_date . ' ' . $check_out_time . ':00');

    $check_in = $check_in_dt->format('Y-m-d H:i:s');
    $check_out = $check_out_dt->format('Y-m-d H:i:s');

    // Validate dates
    if ($check_in_dt >= $check_out_dt) {
        $error = "Check-out time must be after check-in time";
    } elseif ($check_in_dt < new DateTime()) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- Add caching headers for better performance -->
    <meta http-equiv="Cache-Control" content="max-age=86400, public">
    <meta http-equiv="Expires" content="<?php echo gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT'; ?>">
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

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            text-align: center;
            color: #28a745;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #28a745;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #wrapper.loaded {
            opacity: 1;
        }

        /* Sidebar - Green Theme */
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
            text-align: center;
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

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .alert-info {
            background: #d1ecf1;
            border: none;
            border-radius: 10px;
            color: #0c5460;
        }

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
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h4>Loading Booking Page...</h4>
            <p class="text-muted">Please wait while we prepare your booking details.</p>
        </div>
    </div>

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
                <a href="profile.php" class="list-group-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="list-group-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <div class="sidebar-footer">
                <i class="fas fa-user-circle fa-2x mb-2"></i>
                <p class="mb-0"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                <small>Member</small>
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
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Check-in Date</label>
                                    <input type="date" class="form-control" name="check_in" 
                                           id="check_in" min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Check-in Time</label>
                                    <div class="row">
                                        <div class="col-4">
                                            <select class="form-select" id="check_in_hour" required>
                                                <?php for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo ($h == date('g')) ? 'selected' : ''; ?>><?php echo $h; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select class="form-select" id="check_in_minute" required>
                                                <?php for ($m = 0; $m < 60; $m++): ?>
                                                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo (str_pad($m, 2, '0', STR_PAD_LEFT) == date('i')) ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select class="form-select" id="check_in_ampm" required>
                                                <option value="AM" <?php echo (date('A') == 'AM') ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo (date('A') == 'PM') ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="check_in_time" id="check_in_time_hidden" value="<?php echo date('H:i'); ?>">
                                </div>
                            </div>
                            <small class="text-muted mb-3 d-block">* Select your preferred check-in and check-out times using the hour, minute, and AM/PM selectors. Duration selection quickly sets the check-out time, but you can adjust it manually. The cost updates dynamically based on your selected time period.</small>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-out Date</label>
                                    <input type="date" class="form-control" name="check_out" 
                                           id="check_out" min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-out Time</label>
                                    <div class="row">
                                        <div class="col-4">
                                            <select class="form-select" id="check_out_hour" required>
                                                <?php 
                                                $co_hour = date('g', strtotime('+1 hour'));
                                                for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo ($h == $co_hour) ? 'selected' : ''; ?>><?php echo $h; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select class="form-select" id="check_out_minute" required>
                                                <?php 
                                                $co_minute = date('i', strtotime('+1 hour'));
                                                for ($m = 0; $m < 60; $m++): ?>
                                                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo (str_pad($m, 2, '0', STR_PAD_LEFT) == $co_minute) ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select class="form-select" id="check_out_ampm" required>
                                                <?php 
                                                $co_ampm = date('A', strtotime('+1 hour'));
                                                ?>
                                                <option value="AM" <?php echo ($co_ampm == 'AM') ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo ($co_ampm == 'PM') ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="check_out_time" id="check_out_time_hidden" value="<?php echo date('H:i', strtotime('+1 hour')); ?>">
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
        
        function updateTimeInput(prefix) {
            const hour = parseInt($('#' + prefix + '_hour').val());
            const minute = $('#' + prefix + '_minute').val();
            const ampm = $('#' + prefix + '_ampm').val();
            let hour24 = hour;
            if (ampm === 'PM' && hour !== 12) hour24 += 12;
            if (ampm === 'AM' && hour === 12) hour24 = 0;
            const time24 = ('0' + hour24).slice(-2) + ':' + minute;
            $('#' + prefix + '_time_hidden').val(time24);
        }
        
        $(document).ready(function() {
            updateTimeInput('check_in');
            updateTimeInput('check_out');
            calculateCost();
            updateCheckout();
            getDemandPrediction();

            $('#duration_mode, #check_in, #check_in_hour, #check_in_minute, #check_in_ampm').on('change', function() {
                updateTimeInput('check_in');
                updateCheckout();
            });
            
            $('#check_in, #check_in_hour, #check_in_minute, #check_in_ampm, #check_out, #check_out_hour, #check_out_minute, #check_out_ampm').on('change', function() {
                updateTimeInput('check_in');
                updateTimeInput('check_out');
                calculateCost();
            });
        });
        
        function calculateCost() {
            const checkIn = new Date($('#check_in').val() + 'T' + $('#check_in_time_hidden').val());
            const checkOut = new Date($('#check_out').val() + 'T' + $('#check_out_time_hidden').val());
            
            if (checkIn && checkOut && checkOut > checkIn) {
                const hours = (checkOut - checkIn) / (1000 * 60 * 60);
                const cost = hours * pricePerHour;
                
                $('#duration').text(hours.toFixed(1));
                $('#totalCost').text('रू ' + cost.toFixed(2));
            }
        }

        function updateCheckout() {
            const checkInDate = $('#check_in').val();
            const checkInTime = $('#check_in_time_hidden').val();
            const durationHours = parseInt($('#duration_mode').val(), 10) || 1;

            if (!checkInDate || !checkInTime) {
                return;
            }

            const checkIn = new Date(checkInDate + 'T' + checkInTime);
            const checkOut = new Date(checkIn.getTime() + durationHours * 60 * 60 * 1000);

            const isoDate = checkOut.toISOString();
            $('#check_out').val(isoDate.split('T')[0]);
            
            // Update the selects
            const coHour12 = checkOut.getHours() % 12 || 12;
            const coMinute = ('0' + checkOut.getMinutes()).slice(-2);
            const coAmpm = checkOut.getHours() >= 12 ? 'PM' : 'AM';
            
            $('#check_out_hour').val(coHour12);
            $('#check_out_minute').val(coMinute);
            $('#check_out_ampm').val(coAmpm);
            
            updateTimeInput('check_out');
            calculateCost();
        }
        
        function getDemandPrediction() {
            $.ajax({
                url: '../api/predict_demand.php',
                method: 'POST',
                data: {
                    space_id: <?php echo $space_id; ?>,
                    date: $('#check_in').val(),
                    hour: $('#check_in_time_hidden').val().split(':')[0]
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
            const checkIn = new Date($('#check_in').val() + 'T' + $('#check_in_time_hidden').val());
            const checkOut = new Date($('#check_out').val() + 'T' + $('#check_out_time_hidden').val());
            
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
    <script>
        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const wrapper = document.getElementById('wrapper');

            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }

            if (wrapper) {
                wrapper.classList.add('loaded');
            }
        });
    </script>
</body>
</html>