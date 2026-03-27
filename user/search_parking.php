<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$search_results = null;

// Get categories for filter
$categories = $conn->query("SELECT * FROM vehicle_categories WHERE status = 'active'");

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $time = $_POST['time'] ?? date('H:i');
    $user_lat = floatval($_POST['user_lat'] ?? 0);
    $user_lng = floatval($_POST['user_lng'] ?? 0);
    $duration_mode = intval($_POST['duration_mode'] ?? 24);

    $check_datetime = $date . ' ' . $time . ':00';
    
    $query = "
        SELECT ps.*, vc.category_name,
               (SELECT COUNT(*) FROM parking_bookings WHERE space_id = ps.id AND booking_status = 'active') as current_bookings
        FROM parking_spaces ps
        JOIN vehicle_categories vc ON ps.category_id = vc.id
        WHERE ps.status = 'active' AND ps.is_available = 1
    ";
    
    $params = [];
    $types = "";
    
    if (!empty($location)) {
        $query .= " AND (ps.location_name LIKE ? OR ps.address LIKE ?)";
        $location_param = "%$location%";
        $params[] = $location_param;
        $params[] = $location_param;
        $types .= "ss";
    }
    
    if ($category_id > 0) {
        $query .= " AND ps.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY ps.price_per_hour ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_set = $stmt->get_result();

    $search_results = [];
    while ($space = $result_set->fetch_assoc()) {
        $space['distance'] = null;
        if ($user_lat && $user_lng && $space['latitude'] && $space['longitude']) {
            $space['distance'] = calculateDistanceKm($user_lat, $user_lng, $space['latitude'], $space['longitude']) * 1000; // meters
        }
        $search_results[] = $space;
    }

    if ($user_lat && $user_lng) {
        usort($search_results, function($a, $b) {
            $aDist = $a['distance'] === null ? PHP_INT_MAX : $a['distance'];
            $bDist = $b['distance'] === null ? PHP_INT_MAX : $b['distance'];
            return $aDist <=> $bDist;
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Parking - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    
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

        /* Search Card - Original Green Gradient */
        .search-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
        }

        .search-card .form-label {
            color: white;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .search-card .form-control,
        .search-card .form-select {
            border: none;
            border-radius: 10px;
            padding: 12px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-card .form-control:focus,
        .search-card .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.5);
            outline: none;
        }

        .search-card .btn-light {
            background: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            color: #28a745;
            transition: all 0.3s;
            height: 48px;
        }

        .search-card .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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

        /* Space Cards */
        .space-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }

        .space-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #28a745;
        }

        .space-number {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }

        .badge-info {
            background: #e3f2fd;
            color: #0d6efd;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }

        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }

        .price-tag small {
            font-size: 0.9rem;
            color: #666;
            font-weight: normal;
        }

        .btn-book {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-warning {
            color: #ffc107 !important;
        }

        /* Alert Styles */
        .alert-info {
            background: #d1ecf1;
            border: none;
            border-radius: 10px;
            color: #0c5460;
            padding: 15px 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -280px;
            }
            #page-content-wrapper {
                margin-left: 0;
            }
            #sidebar-wrapper.active {
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
                <a href="search_parking.php" class="list-group-item active">
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
                    <h4 class="mb-0"><i class="fas fa-search me-2"></i>Search Parking Spaces</h4>
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

            <!-- Search Form -->
            <div class="search-card">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" 
                                   placeholder="Enter location..." 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vehicle Type</label>
                            <select class="form-select" name="category_id">
                                <option value="0">All Types</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" 
                                   value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="time" 
                                   value="<?php echo htmlspecialchars($_POST['time'] ?? date('H:i')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Duration</label>
                            <select class="form-select" name="duration_mode">
                                <option value="24" <?php echo (isset($_POST['duration_mode']) && $_POST['duration_mode'] == 24) ? 'selected' : ''; ?>>24 hours</option>
                                <option value="12" <?php echo (isset($_POST['duration_mode']) && $_POST['duration_mode'] == 12) ? 'selected' : ''; ?>>12 hours</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Use Current Location</label>
                            <button type="button" class="btn btn-success w-100" onclick="fetchCurrentLocation()"><i class="fas fa-location-arrow me-2"></i>Enable</button>
                        </div>
                        <input type="hidden" name="user_lat" id="user_lat" value="<?php echo htmlspecialchars($_POST['user_lat'] ?? ''); ?>">
                        <input type="hidden" name="user_lng" id="user_lng" value="<?php echo htmlspecialchars($_POST['user_lng'] ?? ''); ?>">
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-light w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><i class="fas fa-map me-2"></i>Parking Location Map</h5>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="toggleMapBtn" onclick="toggleMap()">
                        <i class="fas fa-eye-slash me-1"></i>Hide Map
                    </button>
                </div>
                <div id="searchMap" style="height: 320px; border: 1px solid #ddd; border-radius: 10px; transition: all 0.3s;"></div>
            </div>

            <!-- Search Results -->
            <?php if (!empty($search_results)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i>Available Parking Spaces (<?php echo count($search_results); ?> found)
                    </div>
                    <div class="card-body">
                        <?php if (count($search_results) > 0): ?>
                            <?php foreach ($search_results as $space): ?>
                                <div class="space-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <div class="space-number">Space <?php echo htmlspecialchars($space['space_number']); ?></div>
                                            <span class="badge bg-info text-white mt-2"><?php echo htmlspecialchars($space['category_name']); ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <i class="fas fa-map-marker-alt text-success me-1"></i>
                                            <?php echo htmlspecialchars($space['location_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($space['address']); ?></small>
                                            <br>
                                            <small class="text-muted">Distance: <?php echo $space['distance'] !== null ? round($space['distance'], 1) . ' m' : 'N/A'; ?></small>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="price-tag">रू <?php echo $space['price_per_hour']; ?><small>/hr</small></div>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($space['current_bookings'] > 0): ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-clock me-1"></i> <?php echo $space['current_bookings']; ?> vehicles parked
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i> Available now
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <a href="book_parking.php?space_id=<?php echo $space['id']; ?>" 
                                               class="btn btn-book">
                                                Book Now <i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No parking spaces found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        let searchMap = null;
        let searchMarker = null;

        function initSearchMap() {
            searchMap = L.map('searchMap').setView([27.7172, 85.3240], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '© OpenStreetMap contributors'
            }).addTo(searchMap);
        }

        function fetchCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation not supported by your browser');
                return;
            }

            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('user_lat').value = lat;
                document.getElementById('user_lng').value = lng;
                document.querySelector('input[name="location"]').value = 'Current Location';

                if (!searchMap) {
                    initSearchMap();
                }

                if (searchMarker) {
                    searchMap.removeLayer(searchMarker);
                }

                searchMarker = L.marker([lat, lng]).addTo(searchMap).bindPopup('Your Current Location').openPopup();
                searchMap.setView([lat, lng], 13);
            }, function(err) {
                alert('Unable to get your location. Please enable location access in browser.');
            });
        }

        function toggleMap() {
            const mapDiv = document.getElementById('searchMap');
            const toggleBtn = document.getElementById('toggleMapBtn');
            
            if (mapDiv.style.display === 'none') {
                // Show map
                mapDiv.style.display = 'block';
                mapDiv.style.height = '320px';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide Map';
                if (searchMap) {
                    searchMap.invalidateSize();
                }
            } else {
                // Hide map
                mapDiv.style.display = 'none';
                mapDiv.style.height = '0px';
                toggleBtn.innerHTML = '<i class="fas fa-eye me-1"></i>Show Map';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initSearchMap();
        });
    </script>
</body>
</html>