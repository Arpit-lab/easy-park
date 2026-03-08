<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$message = '';
$error = '';

// Get categories
$categories = $conn->query("SELECT * FROM vehicle_categories WHERE status = 'active'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $space_number = strtoupper(trim($_POST['space_number']));
    $category_id = intval($_POST['category_id']);
    $location_name = trim($_POST['location_name']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $address = trim($_POST['address']);
    $price_per_hour = floatval($_POST['price_per_hour']);
    
    // Check if space number already exists
    $check = $conn->prepare("SELECT id FROM parking_spaces WHERE space_number = ?");
    $check->bind_param("s", $space_number);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Space number already exists. Please choose a different number.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO parking_spaces 
            (space_number, category_id, location_name, latitude, longitude, address, price_per_hour, owner_id, is_available) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("sisddsdi", $space_number, $category_id, $location_name, $latitude, $longitude, $address, $price_per_hour, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $message = "Parking space added successfully! It will be listed after admin approval.";
            logActivity($_SESSION['user_id'], 'add_space', "Added parking space: $space_number");
        } else {
            $error = "Error adding parking space: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Parking Space - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Leaflet for Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    
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

        .card-body {
            padding: 25px;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        /* Map Styles */
        #map {
            height: 400px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }

        .coordinate-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .coordinate-info .form-control[readonly] {
            background-color: #e9ecef;
            cursor: default;
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

        /* Button Styles */
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 40px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }

        hr {
            margin: 30px 0;
            opacity: 0.2;
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
                <a href="my_bookings.php" class="list-group-item">
                    <i class="fas fa-calendar-check"></i> My Bookings
                </a>
                <a href="add_parking_space.php" class="list-group-item active">
                    <i class="fas fa-plus-circle"></i> Add Parking Space
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
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add Parking Space</h4>
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

            <!-- Main Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Parking Space Details
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="spaceForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Space Number *</label>
                                    <input type="text" class="form-control" name="space_number" 
                                           placeholder="e.g., P001" required>
                                    <small class="text-muted">Enter a unique space identifier</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Category *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['id']; ?>">
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Location Name *</label>
                                    <input type="text" class="form-control" name="location_name" 
                                           placeholder="e.g., Downtown Parking" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address *</label>
                                    <textarea class="form-control" name="address" rows="3" 
                                              placeholder="Full address of the parking space" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Price Per Hour (रू) *</label>
                                    <input type="number" step="0.01" class="form-control" name="price_per_hour" 
                                           placeholder="e.g., 50" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Location on Map</h5>
                                
                                <div id="map"></div>
                                
                                <div class="coordinate-info mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Latitude</label>
                                            <input type="text" class="form-control" name="latitude" id="latitude" 
                                                   value="27.7172" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Longitude</label>
                                            <input type="text" class="form-control" name="longitude" id="longitude" 
                                                   value="85.3240" readonly>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Click on the map to set the exact location
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-plus-circle me-2"></i>Add Parking Space
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    
    <script>
        // Initialize map
        const map = L.map('map').setView([27.7172, 85.3240], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        let marker;
        
        // Add marker on click
        map.on('click', function(e) {
            if (marker) {
                map.removeLayer(marker);
            }
            
            marker = L.marker(e.latlng).addTo(map);
            
            document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
        });
        
        // Form validation
        document.getElementById('spaceForm').addEventListener('submit', function(e) {
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            const spaceNumber = document.querySelector('input[name="space_number"]').value.trim();
            const price = document.querySelector('input[name="price_per_hour"]').value;
            
            if (!spaceNumber) {
                e.preventDefault();
                alert('Please enter space number');
                return;
            }
            
            if (price <= 0) {
                e.preventDefault();
                alert('Please enter a valid price');
                return;
            }
            
            if (lat == '27.7172' && lng == '85.3240') {
                if (!confirm('You haven\'t set the exact location on map. Continue with default location?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>