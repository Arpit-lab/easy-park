<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Auto-migrate database columns if they don't exist
ensureSmartSlotColumns($conn);

// Get all active parking locations with coordinates
$locations = $conn->query("
    SELECT DISTINCT 
        location_name,
        COALESCE(AVG(latitude), 27.7172) as avg_lat,
        COALESCE(AVG(longitude), 85.3240) as avg_lng
    FROM parking_spaces 
    WHERE status = 'active' 
    GROUP BY location_name
    ORDER BY location_name
");

// Get all active vehicle categories for filtering
$categories = $conn->query("SELECT id, category_name FROM vehicle_categories WHERE status = 'active' ORDER BY category_name");

// Get parking prediction data
$prediction = getParkingPrediction();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Find Parking - EasyPark</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- OpenStreetMap with Leaflet (100% FREE - No API keys needed) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    
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
            overflow-y: auto;
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

        /* Search Card - Green Gradient */
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
            border-color: transparent;
        }

        .search-card .btn-light, .search-card .btn-success {
            background: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            color: #28a745;
            transition: all 0.3s;
            height: 48px;
        }

        .search-card .btn-light:hover, .search-card .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            background: white;
            color: #28a745;
        }

        /* Map Styles */
        #smartMap {
            height: 350px;
            border: 2px solid #ddd;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Responsive Map */
        @media (max-width: 768px) {
            #smartMap {
                height: 280px;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            #smartMap {
                height: 250px;
            }
        }

        /* Route Info Card */
        .route-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .route-info h6 {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .route-stat {
            display: inline-block;
            margin-right: 20px;
            padding: 12px 18px;
            background: #f0f5f9;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }

        .route-stat i {
            color: #28a745;
            margin-right: 8px;
        }

        .route-stat .value {
            font-weight: 600;
            color: #333;
            font-size: 1.3rem;
            display: block;
        }

        .route-stat .label {
            font-size: 0.8rem;
            color: #666;
            display: block;
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

        /* Location Status */
        #locationStatus {
            margin-top: 10px;
            font-size: 0.9rem;
        }

        #locationAccuracy {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 6px;
            background: #e8f5e9;
        }

        .text-success { color: #28a745 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }

        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }

        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -280px;
                height: 100%;
            }
            #page-content-wrapper {
                margin-left: 0;
            }
        }

        /* Custom marker styles */
        .user-marker-icon {
            background: #dc3545;
            border: 3px solid white;
            border-radius: 50%;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            animation: pulse 2s infinite;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .destination-marker-icon {
            background: #007bff;
            border: 3px solid white;
            border-radius: 50%;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .parking-marker-icon {
            background: #28a745;
            border: 3px solid white;
            border-radius: 50%;
            padding: 2px 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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
                <a href="smart_recommendations.php" class="list-group-item active">
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
                    <h4 class="mb-0"><i class="fas fa-lightbulb me-2" style="color: #28a745;"></i>Smart Parking Finder</h4>
                </div>
            </nav>

            <!-- Search Form -->
            <div class="search-card">
                <form id="smartForm" onsubmit="return false;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Destination Location</label>
                            <select class="form-select" id="location_select">
                                <option value="">-- Choose destination --</option>
                                <?php while ($loc = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_name']); ?>" 
                                            data-lat="<?php echo $loc['avg_lat']; ?>" 
                                            data-lng="<?php echo $loc['avg_lng']; ?>">
                                        <?php echo htmlspecialchars($loc['location_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Vehicle Type</label>
                            <select class="form-select" id="category_select">
                                <option value="">All types</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Time Zone</label>
                            <select class="form-select" id="duration_mode">
                                <option value="24">24 hours</option>
                                <option value="12">12 hours</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Your Location</label>
                            <button type="button" class="btn btn-success w-100" id="useLocationBtn" onclick="enableGPS()">
                                <i class="fas fa-location-arrow me-2"></i>Enable GPS
                            </button>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-light w-100" onclick="findBestParking()">
                                <i class="fas fa-magic me-2"></i>Find Best Spot
                            </button>
                        </div>
                        <input type="hidden" id="user_lat">
                        <input type="hidden" id="user_lng">
                    </div>
                    <div id="locationStatus" style="margin-top: 15px; display: none;">
                        <small id="locationAccuracy" class="text-success">
                            <i class="fas fa-check-circle"></i> Location acquired
                        </small>
                    </div>
                </form>
            </div>

            <!-- Map Section with Toggle -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><i class="fas fa-map me-2" style="color: #28a745;"></i>Parking Location Map</h5>
                    <button class="btn btn-sm btn-outline-success" type="button" id="toggleMapBtn" onclick="toggleSmartMap()">
                        <i class="fas fa-eye-slash me-1"></i>Hide Map
                    </button>
                </div>
                <div id="smartMap" style="transition: all 0.3s;"></div>
            </div>

            <!-- Route Info Section -->
            <div id="routeInfoContainer"></div>

            <!-- Results Section -->
            <div id="resultsContainer"></div>

            <!-- Parking Status Card -->
            <?php if (isset($prediction['occupancy_percent'])): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Parking Occupancy
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10">
                            <p class="mb-2"><strong>Current: <?php echo round($prediction['occupancy_percent']); ?>%</strong></p>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); width: <?php echo round($prediction['occupancy_percent']); ?>%">
                                    <?php echo round($prediction['occupancy_percent']); ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <small class="text-muted">
                                <?php 
                                $occ = $prediction['occupancy_percent'];
                                echo ($occ < 30 ? '✓ Excellent' : ($occ < 60 ? '◐ Good' : '✗ Limited'));
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let smartMap = null;
        let userMarker = null;
        let destinationMarker = null;
        let parkingMarker = null;
        let directionsService = null;
        let directionsRenderer = null;
        let geocoder = null;
        let routingControl = null;

        // Ensure Leaflet is loaded
        function ensureLeaflet() {
            if (!window.L) {
                alert('Leaflet JS is not loaded. Please check your internet connection.');
                throw new Error('Leaflet JS not loaded');
            }
        }

        // Initialize map (lazy loading)
        function initSmartMap() {
            if (smartMap) return; // Already initialized

            ensureLeaflet();
            smartMap = L.map('smartMap').setView([27.7172, 85.3240], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(smartMap);

            // Add loading indicator
            const mapContainer = document.getElementById('smartMap');
            mapContainer.style.position = 'relative';

            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'mapLoading';
            loadingDiv.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255,255,255,0.9);
                padding: 20px;
                border-radius: 10px;
                z-index: 1000;
                display: none;
            `;
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading map...';
            mapContainer.appendChild(loadingDiv);
        }

        // Show map loading indicator
        function showMapLoading() {
            const loading = document.getElementById('mapLoading');
            if (loading) loading.style.display = 'block';
        }

        // Hide map loading indicator
        function hideMapLoading() {
            const loading = document.getElementById('mapLoading');
            if (loading) loading.style.display = 'none';
        }

        // Enable GPS
        function enableGPS() {
            console.log('enableGPS function called');
            const btn = document.getElementById('useLocationBtn');
            const status = document.getElementById('locationStatus');
            const accuracy = document.getElementById('locationAccuracy');

            console.log('Button element:', btn);
            if (!btn) {
                console.error('GPS button not found');
                return;
            }

            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Getting location...';
            btn.disabled = true;

            // Initialize map if not already done
            initSmartMap();

            if (!navigator.geolocation) {
                alert('Geolocation not supported');
                btn.innerHTML = '<i class="fas fa-location-arrow me-2"></i>Enable GPS';
                btn.disabled = false;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy_m = Math.round(position.coords.accuracy);

                    document.getElementById('user_lat').value = lat;
                    document.getElementById('user_lng').value = lng;

                    status.style.display = 'block';
                    accuracy.innerHTML = `<i class="fas fa-check-circle"></i> GPS acquired (±${accuracy_m}m)`;
                    accuracy.className = accuracy_m < 50 ? 'text-success' : accuracy_m < 100 ? 'text-warning' : 'text-danger';

                    btn.innerHTML = '<i class="fas fa-location-arrow me-2"></i>GPS Active ✓';
                    btn.disabled = false;
                    btn.className = 'btn btn-success w-100';

                    if (!smartMap) initSmartMap();
                    addUserMarker(lat, lng);
                    smartMap.setView([lat, lng], 14);

                    console.log('GPS success:', lat, lng, accuracy_m);
                },
                function(err) {
                    console.error('GPS error:', err);
                    let errorMsg = 'Error getting location: ';
                    switch(err.code) {
                        case err.PERMISSION_DENIED:
                            errorMsg += 'Location access denied. Please enable location permissions.';
                            break;
                        case err.POSITION_UNAVAILABLE:
                            errorMsg += 'Location unavailable.';
                            break;
                        case err.TIMEOUT:
                            errorMsg += 'Location request timed out.';
                            break;
                        default:
                            errorMsg += 'Unknown error.';
                            break;
                    }
                    alert(errorMsg);
                    btn.innerHTML = '<i class="fas fa-location-arrow me-2"></i>Enable GPS';
                    btn.disabled = false;
                    status.style.display = 'none';
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }

        // Add user marker
        function addUserMarker(lat, lng) {
            if (userMarker) smartMap.removeLayer(userMarker);

            const userIcon = L.divIcon({
                html: '<div class="user-marker-icon">📍</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            userMarker = L.marker([lat, lng], { icon: userIcon })
                .addTo(smartMap)
                .bindPopup('Your Location', { permanent: false });
        }

        // Add destination marker
        function addDestinationMarker(lat, lng, name) {
            if (destinationMarker) smartMap.removeLayer(destinationMarker);

            const destIcon = L.divIcon({
                html: '<div class="destination-marker-icon">🎯</div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            destinationMarker = L.marker([lat, lng], { icon: destIcon })
                .addTo(smartMap)
                .bindPopup(`${name}`, { permanent: true });
        }

        // Add parking marker
        function addParkingMarker(lat, lng, space_number) {
            if (parkingMarker) smartMap.removeLayer(parkingMarker);

            const parkingIcon = L.divIcon({
                html: `<div class="parking-marker-icon">🅿</div>`,
                iconSize: [35, 35],
                iconAnchor: [17, 17]
            });

            parkingMarker = L.marker([lat, lng], { icon: parkingIcon })
                .addTo(smartMap)
                .bindPopup(`Parking: ${space_number}`, { permanent: true });
        }

        // Show route between two points
        function showRoute(from, to) {
            if (!smartMap) initSmartMap();

            if (routingControl) smartMap.removeControl(routingControl);

            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(from[0], from[1]),
                    L.latLng(to[0], to[1])
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                collapsible: true,
                createMarker: () => null,
                lineOptions: {
                    styles: [
                        { color: '#28a745', opacity: 0.8, weight: 5 }
                    ]
                }
            }).on('routesfound', function(e) {
                const route = e.routes[0];
                const distance = (route.summary.totalDistance / 1000).toFixed(2);
                const time = Math.round(route.summary.totalTime / 60);

                let routeHtml = `
                    <div class="route-info">
                        <h6><i class="fas fa-road me-2"></i>Route Information</h6>
                        <div>
                            <div class="route-stat">
                                <i class="fas fa-map-marked-alt"></i>
                                <span class="value">${distance} km</span>
                                <span class="label">Distance</span>
                            </div>
                            <div class="route-stat">
                                <i class="fas fa-clock"></i>
                                <span class="value">${time} min</span>
                                <span class="label">Drive Time</span>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('routeInfoContainer').innerHTML = routeHtml;
            }).addTo(smartMap);

            setTimeout(() => {
                smartMap.fitBounds(routingControl.getBounds());
            }, 500);
        }

        // Find best parking
        function findBestParking() {
            console.log('findBestParking function called');
            const location = document.getElementById('location_select').value;
            const userLat = document.getElementById('user_lat').value;
            const userLng = document.getElementById('user_lng').value;
            const hasGPS = userLat && userLng;

            console.log('Location:', location, 'GPS:', hasGPS, 'Lat:', userLat, 'Lng:', userLng);

            if (!location) {
                alert('Please select a destination location');
                return;
            }

            // Initialize map if not already done
            initSmartMap();
            showMapLoading();

            // If GPS is not enabled, continue with destination-based search only
            if (!hasGPS) {
                document.getElementById('locationStatus').style.display = 'none';
            }

            document.getElementById('resultsContainer').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="loading-spinner">
                            <div class="spinner-border" role="status" style="color: #28a745;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Finding best parking spot...</p>
                        </div>
                    </div>
                </div>
            `;

            $.ajax({
                url: '../api/smart_slot_recommendation.php',
                method: 'POST',
                dataType: 'json',
                timeout: 30000,
                data: {
                    location_id: location,
                    user_lat: hasGPS ? userLat : '',
                    user_lng: hasGPS ? userLng : '',
                    duration_hours: document.getElementById('duration_mode').value,
                    category_id: document.getElementById('category_select').value || ''
                },
                success: function(response) {
                    console.log('API Response:', response);
                    try {
                        hideMapLoading();
                        if (response && response.success) {
                            displayResults(response, location);
                        } else {
                            displayError(response && response.message ? response.message : 'No parking spots found for the selected destination.');
                        }
                    } catch (err) {
                        hideMapLoading();
                        console.error('Error in success callback:', err);
                        displayError('Error processing results: ' + err.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideMapLoading();
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    // Try to parse response as JSON even in error case
                    try {
                        const responseJson = JSON.parse(xhr.responseText);
                        if (responseJson && responseJson.message) {
                            displayError(responseJson.message);
                            return;
                        }
                    } catch (e) {
                        // Not JSON, continue with normal error handling
                    }
                    
                    // Check if it's actually a successful response that got misclassified
                    if (xhr.status === 200 && xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.success !== false) {
                                displayResults(response, location);
                                return;
                            }
                        } catch (e) {
                            displayError('Invalid response format from server');
                            return;
                        }
                    }
                    
                    displayError(`Error finding parking (${xhr.status}: ${xhr.statusText || status})`);
                }
            });
        }

        function displayError(message) {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> ${message}
                        </div>
                    </div>
                </div>
            `;
        }

        // Format distance to show both meters and km
        function formatDistance(meters) {
            if (meters < 1000) {
                return `${meters}m`;
            } else {
                const km = (meters / 1000).toFixed(1);
                return `${km}km (${meters}m)`;
            }
        }

        // Display results
        function displayResults(response, destLocation) {
            try {
                const slot = response.recommended_slot;
                const isFromRequestedLocation = response.is_from_requested_location;
                const categoryAvailableInLocation = response.category_available_in_location;
                const categoryMessage = response.category_message;
                const selectedCategoryOption = document.getElementById('category_select').selectedOptions[0];
                const selectedCategoryName = selectedCategoryOption ? selectedCategoryOption.text.trim() : '';
                const selectedCategoryId = document.getElementById('category_select').value || '';
                const userLatRaw = document.getElementById('user_lat').value;
                const userLngRaw = document.getElementById('user_lng').value;
                const userLat = parseFloat(userLatRaw);
                const userLng = parseFloat(userLngRaw);
                const parkingLat = parseFloat(slot.latitude);
                const parkingLng = parseFloat(slot.longitude);
                const userHasLocation = Number.isFinite(userLat) && Number.isFinite(userLng);

                // Clear existing markers
                if (userMarker) smartMap.removeLayer(userMarker);
                if (destinationMarker) smartMap.removeLayer(destinationMarker);
                if (parkingMarker) smartMap.removeLayer(parkingMarker);

                // Clear all parking spot markers
                if (window.parkingSpotMarkers) {
                    window.parkingSpotMarkers.forEach(marker => smartMap.removeLayer(marker));
                }
                window.parkingSpotMarkers = [];

                // Clear existing routing controls
                if (routingControl && smartMap) {
                    smartMap.removeControl(routingControl);
                    routingControl = null;
                }
                if (window.currentRoutes) {
                    window.currentRoutes.forEach(route => {
                        if (route.remove) route.remove();
                    });
                    window.currentRoutes = [];
                }

                // Add user marker if GPS enabled
                if (userHasLocation) {
                    addUserMarker(userLat, userLng);
                }

                // Add destination marker
                const destOption = document.querySelector('#location_select option:checked');
                if (destOption && destOption.dataset.lat && destOption.dataset.lng) {
                    const destLat = parseFloat(destOption.dataset.lat);
                    const destLng = parseFloat(destOption.dataset.lng);
                    addDestinationMarker(destLat, destLng, destLocation);
                }

                // Add markers for all available spots
                if (response.all_available_spots && response.all_available_spots.length > 0) {
                    addAllAvailableMarkers(response.all_available_spots, slot.id);
                }

                // Show routes for all available spots if GPS enabled
                if (userHasLocation && response.all_available_spots && response.all_available_spots.length > 0) {
                    setTimeout(() => {
                        showAllRoutes(response.all_available_spots, userLat, userLng);
                    }, 1000);
                } else {
                    // Center map on parking spot
                    smartMap.setView([parkingLat, parkingLng], 14);
                }

                document.getElementById('routeInfoContainer').innerHTML = '';

                const pricePerHour = parseFloat(slot.price_per_hour || 0).toFixed(2);
                
                let html = '';
                
                // Show category availability message if category not available in requested location
                if (!categoryAvailableInLocation && categoryMessage) {
                    html += `
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Category Notice:</strong> ${categoryMessage}
                            </div>
                        </div>
                    </div>
                    `;
                }
                
                // Show recommended spot
                html += `
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none;">
                        <i class="fas fa-star me-2"></i>✓ Best Parking Spot Found!
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <h2 style="color: #28a745; margin-bottom: 5px;">${slot.space_number}</h2>
                                <p class="text-muted mb-0">${slot.location_name}</p>
                                <small class="text-muted">${slot.address}</small>
                            </div>
                            <div class="col-md-3">
                                <p class="mb-2"><strong>📍 Distance from Entry:</strong></p>
                                <h4 style="color: #28a745;">${Math.round(slot.distance_from_entry || 0)}m</h4>
                                <small class="text-muted">Entry point</small>
                                ${userHasLocation && slot.distance_from_user ? `<br><small class="text-info"><i class="fas fa-route me-1"></i>${Math.round(slot.distance_from_user)}m from you</small>` : ''}
                            </div>
                            <div class="col-md-3">
                                <p class="mb-2"><strong>💰 Price/hr:</strong></p>
                                <h4 style="color: #28a745;">रू${pricePerHour}</h4>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="book_parking.php?space_id=${slot.id}" class="btn btn-success btn-lg">
                                    <i class="fas fa-check me-2"></i>Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                `;

                // Show available spots list or fallback alternatives
                const hasSameLocationSpots = response.all_available_spots && response.all_available_spots.length > 0;
                let spotsToShow = hasSameLocationSpots ? response.all_available_spots : response.alternative_options;
                const showingFallbackOptions = !hasSameLocationSpots;
                let headerText = 'Available Spots';

                if (!categoryAvailableInLocation) {
                    headerText = userHasLocation ? 'Available Spots in Other Locations (with GPS Distances)' : 'Available Spots in Other Locations';
                } else if (selectedCategoryId) {
                    headerText = userHasLocation ? `All Available ${selectedCategoryName} Spots in ${destLocation} (with GPS Distances)` : `All Available ${selectedCategoryName} Spots in ${destLocation}`;
                } else {
                    headerText = userHasLocation ? `All Available Spots in ${destLocation} (with GPS Distances)` : `All Available Spots in ${destLocation}`;
                }

                spotsToShow = spotsToShow.slice().sort((a, b) => {
                    if (a.id === slot.id) return -1;
                    if (b.id === slot.id) return 1;
                    if (selectedCategoryId && a.category_name === selectedCategoryName && b.category_name !== selectedCategoryName) return -1;
                    if (selectedCategoryId && b.category_name === selectedCategoryName && a.category_name !== selectedCategoryName) return 1;
                    return (a.score || 0) - (b.score || 0);
                });

                if (spotsToShow && spotsToShow.length > 0) {
                    html += `<div class="card mt-4"><div class="card-header"><i class="fas fa-list me-2"></i>${headerText} in ${destLocation}</div><div class="card-body" style="padding: 0;">`;

                    spotsToShow.forEach((spot, idx) => {
                        const spotPrice = parseFloat(spot.price_per_hour || 0).toFixed(2);
                        const entryDistance = Math.round(spot.distance_from_entry || 0);
                        const userDistance = spot.distance_from_user ? Math.round(spot.distance_from_user) : null;
                        const categoryName = spot.category_name || 'General';
                        const isRecommended = spot.id === slot.id;
                        
                        html += `
                            <div style="padding: 18px 25px; border-bottom: 1px solid #f0f0f0; transition: all 0.3s; ${isRecommended ? 'background: #f0f9f0;' : ''}" class="option-row" onmouseover="this.style.background='${isRecommended ? '#e8f5e9' : '#f8f9fa'}'" onmouseout="this.style.background='${isRecommended ? '#f0f9f0' : 'white'}'">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <h5 class="mb-0" style="color: #28a745;">${isRecommended ? '⭐ ' : ''}${spot.space_number}</h5>
                                        <p class="text-muted mb-0 small"><i class="fas fa-map-pin me-1"></i>${spot.location_name}</p>
                                        ${isRecommended ? '<small class="badge bg-success">Recommended</small>' : ''}
                                    </div>
                                    <div class="col-md-2">
                                        <p class="mb-0 small"><strong>📍 From Entry:</strong></p>
                                        <p class="mb-0" style="color: #28a745; font-weight: 600;">${entryDistance}m</p>
                                        ${userDistance !== null ? `<small class="text-info"><i class="fas fa-route me-1"></i>${formatDistance(userDistance)}</small>` : ''}
                                    </div>
                                    <div class="col-md-2">
                                        <p class="mb-0 small"><strong>💰 Price:</strong></p>
                                        <p class="mb-0" style="color: #28a745; font-weight: 600;">रू${spotPrice}/hr</p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="mb-0 small"><strong>📋 Type:</strong></p>
                                        <p class="mb-0 small">${categoryName}</p>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <a href="book_parking.php?space_id=${spot.id}" class="btn btn-sm ${isRecommended ? 'btn-success' : 'btn-success'}">
                                            <i class="fas fa-check me-1"></i>${isRecommended ? 'Book Recommended' : 'Book This Spot'}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += '</div></div>';
                }

                document.getElementById('resultsContainer').innerHTML = html;

                // Show routes for all available spots if GPS enabled
                if (userHasLocation && response.all_available_spots && response.all_available_spots.length > 0) {
                    setTimeout(() => {
                        showAllRoutes(response.all_available_spots);
                    }, 1000);
                }

                // Fit map to show all markers
                setTimeout(() => {
                    const bounds = [];
                    if (userHasLocation) bounds.push([userLat, userLng]);
                    if (Number.isFinite(parkingLat) && Number.isFinite(parkingLng)) bounds.push([parkingLat, parkingLng]);
                    
                    if (bounds.length > 1) {
                        smartMap.fitBounds(bounds, { padding: [20, 20] });
                    }
                }, 1000);

            } catch (err) {
                console.error('Error in displayResults:', err);
                displayError('Error displaying results: ' + err.message);
            }
        }

        // Function to show routes for all available spots
        function showAllRoutes(spots, userLat, userLng) {
            try {
                console.log('showAllRoutes called with', spots.length, 'spots');

                // Clear existing single routing control
                if (routingControl && smartMap) {
                    smartMap.removeControl(routingControl);
                    routingControl = null;
                }

                // Clear existing routes
                if (window.currentRoutes) {
                    window.currentRoutes.forEach(route => {
                        if (route.remove) route.remove();
                    });
                }
                window.currentRoutes = [];

                // Get user location
                if (!userLat || !userLng) {
                    console.warn('User location not available for routing');
                    return;
                }

                console.log('User location:', userLat, userLng);

                spots.forEach((spot, index) => {
                    if (spot.latitude && spot.longitude) {
                        console.log(`Creating route ${index + 1} to spot:`, spot.space_number, 'at', spot.latitude, spot.longitude);

                        try {
                            const route = L.Routing.control({
                                waypoints: [
                                    L.latLng(userLat, userLng),
                                    L.latLng(parseFloat(spot.latitude), parseFloat(spot.longitude))
                                ],
                                routeWhileDragging: false,
                                addWaypoints: false,
                                createMarker: function() { return null; }, // Don't create markers
                                lineOptions: {
                                    styles: [
                                        {
                                            color: index === 0 ? '#28a745' : '#007bff', // Green for recommended, blue for others
                                            weight: index === 0 ? 4 : 3,
                                            opacity: index === 0 ? 0.8 : 0.6
                                        }
                                    ]
                                },
                                show: false, // Don't show the routing panel
                                collapsible: false,
                                autoRoute: true,
                                useZoomParameter: false,
                                router: L.Routing.osrmv1({
                                    serviceUrl: 'https://router.project-osrm.org/route/v1',
                                    profile: 'driving'
                                })
                            });

                            // Add event listeners for debugging
                            route.on('routesfound', function(e) {
                                console.log(`Route ${index + 1} found:`, e.routes[0].summary);
                            });

                            route.on('routingerror', function(e) {
                                console.error(`Routing error for route ${index + 1}:`, e.error);
                                // Fallback: draw a straight line if routing fails
                                drawStraightLineRoute(userLat, userLng, parseFloat(spot.latitude), parseFloat(spot.longitude), index);
                            });

                            route.addTo(smartMap);
                            window.currentRoutes.push(route);
                        } catch (error) {
                            console.error(`Error creating route ${index + 1}:`, error);
                            // Fallback: draw a straight line
                            drawStraightLineRoute(userLat, userLng, parseFloat(spot.latitude), parseFloat(spot.longitude), index);
                        }
                    }
                });

                console.log('Created', window.currentRoutes.length, 'routes');

                // Fit map to show all routes after a delay
                setTimeout(() => {
                    if (window.currentRoutes.length > 0) {
                        const bounds = L.latLngBounds();
                        bounds.extend([userLat, userLng]);
                        spots.forEach(spot => {
                            if (spot.latitude && spot.longitude) {
                                bounds.extend([parseFloat(spot.latitude), parseFloat(spot.longitude)]);
                            }
                        });
                        smartMap.fitBounds(bounds, { padding: [20, 20] });
                        console.log('Map fitted to bounds');
                    }
                }, 2000);

            } catch (error) {
                console.error('Error showing all routes:', error);
            }
        }

        // Fallback function to draw straight line routes when routing service fails
        function drawStraightLineRoute(fromLat, fromLng, toLat, toLng, index) {
            try {
                console.log(`Drawing straight line route ${index + 1} as fallback`);

                const latlngs = [
                    [fromLat, fromLng],
                    [toLat, toLng]
                ];

                const routeLine = L.polyline(latlngs, {
                    color: index === 0 ? '#28a745' : '#007bff',
                    weight: index === 0 ? 4 : 3,
                    opacity: index === 0 ? 0.8 : 0.6,
                    dashArray: '10, 10' // Dashed line to indicate it's a straight line
                }).addTo(smartMap);

                // Store the line so it can be removed later
                if (!window.currentRoutes) window.currentRoutes = [];
                window.currentRoutes.push({
                    remove: function() {
                        smartMap.removeLayer(routeLine);
                    }
                });

                console.log(`Straight line route ${index + 1} drawn successfully`);
            } catch (error) {
                console.error(`Error drawing straight line route ${index + 1}:`, error);
            }
        }

        // Function to add markers for all available spots
        function addAllAvailableMarkers(spots, recommendedId) {
            try {
                // Clear existing markers
                if (window.parkingSpotMarkers) {
                    window.parkingSpotMarkers.forEach(marker => smartMap.removeLayer(marker));
                }
                window.parkingSpotMarkers = [];

                spots.forEach((spot, index) => {
                    if (spot.latitude && spot.longitude) {
                        const isRecommended = spot.id === recommendedId;
                        const markerColor = isRecommended ? '#28a745' : '#007bff';
                        const markerIcon = L.divIcon({
                            className: 'custom-marker',
                            html: `<div style="background-color: ${markerColor}; border: 2px solid white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">${isRecommended ? '★' : index + 1}</div>`,
                            iconSize: [30, 30],
                            iconAnchor: [15, 15]
                        });

                        const marker = L.marker([parseFloat(spot.latitude), parseFloat(spot.longitude)], { icon: markerIcon })
                            .addTo(smartMap)
                            .bindPopup(`
                                <div style="font-family: Arial, sans-serif; max-width: 200px;">
                                    <h6 style="margin: 0 0 8px 0; color: ${markerColor};">${isRecommended ? '⭐ ' : ''}${spot.space_number}</h6>
                                    <p style="margin: 4px 0;"><strong>Location:</strong> ${spot.location_name}</p>
                                    <p style="margin: 4px 0;"><strong>Price:</strong> रू${parseFloat(spot.price_per_hour || 0).toFixed(2)}/hr</p>
                                    <p style="margin: 4px 0;"><strong>From Entry:</strong> ${Math.round(spot.distance_from_entry || 0)}m</p>
                                    ${spot.distance_from_user ? `<p style="margin: 4px 0;"><strong>From You:</strong> ${Math.round(spot.distance_from_user)}m</p>` : ''}
                                    <a href="book_parking.php?space_id=${spot.id}" class="btn btn-sm ${isRecommended ? 'btn-success' : 'btn-success'} mt-2" style="width: 100%;">Book This Spot</a>
                                </div>
                            `);

                        window.parkingSpotMarkers.push(marker);
                    }
                });
            } catch (error) {
                console.error('Error adding all available markers:', error);
            }
        }

        // Toggle smart map visibility
        function toggleSmartMap() {
            const mapDiv = document.getElementById('smartMap');
            const toggleBtn = document.getElementById('toggleMapBtn');
            
            if (mapDiv.style.display === 'none') {
                // Show map
                mapDiv.style.display = 'block';
                mapDiv.style.height = '350px';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide Map';
                if (smartMap) {
                    smartMap.invalidateSize();
                }
            } else {
                // Hide map
                mapDiv.style.display = 'none';
                mapDiv.style.height = '0px';
                toggleBtn.innerHTML = '<i class="fas fa-eye me-1"></i>Show Map';
            }
        }

        // Handle location change
        document.getElementById('location_select').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                const lat = parseFloat(option.dataset.lat);
                const lng = parseFloat(option.dataset.lng);
                if (!smartMap) initSmartMap();
                smartMap.setView([lng, lat], 13);
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Smart Recommendations page DOMContentLoaded');
            console.log('enableGPS function defined:', typeof enableGPS);
            console.log('findBestParking function defined:', typeof findBestParking);
            console.log('initSmartMap function defined:', typeof initSmartMap);
            // Map will be initialized lazily when needed
        });
    </script>
</body>
</html>
