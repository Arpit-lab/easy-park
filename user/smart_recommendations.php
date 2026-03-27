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
                data: {
                    location_id: location,
                    user_lat: hasGPS ? userLat : '',
                    user_lng: hasGPS ? userLng : '',
                    duration_hours: document.getElementById('duration_mode').value,
                    category_id: document.getElementById('category_select').value || ''
                },
                success: function(response) {
                    console.log('API Response:', response);
                    hideMapLoading();
                    if (response.success) {
                        displayResults(response, location);
                    } else {
                        document.getElementById('resultsContainer').innerHTML = `
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-info-circle"></i> ${response.message}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    hideMapLoading();
                    document.getElementById('resultsContainer').innerHTML = `
                        <div class="card">
                            <div class="card-body">
                                <div class="alert alert-danger mb-0">
                                    <i class="fas fa-exclamation-circle"></i> Error finding parking (${xhr.status}: ${xhr.statusText})
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
        }

        // Display results
        function displayResults(response, destLocation) {
            const slot = response.recommended_slot;
            const isFromRequestedLocation = response.is_from_requested_location;
            const userLatRaw = document.getElementById('user_lat').value;
            const userLngRaw = document.getElementById('user_lng').value;
            const userLat = parseFloat(userLatRaw);
            const userLng = parseFloat(userLngRaw);
            const parkingLat = parseFloat(slot.latitude);
            const parkingLng = parseFloat(slot.longitude);
            const userHasLocation = Number.isFinite(userLat) && Number.isFinite(userLng);

            if (userHasLocation) {
                addUserMarker(userLat, userLng);
                addParkingMarker(parkingLat, parkingLng, slot.space_number);
                showRoute([userLat, userLng], [parkingLat, parkingLng]);
            } else {
                addParkingMarker(parkingLat, parkingLng, slot.space_number);
                smartMap.setView([parkingLat, parkingLng], 14);
            }

            document.getElementById('routeInfoContainer').innerHTML = '';

            const pricePerHour = parseFloat(slot.price_per_hour || 0).toFixed(2);
            
            let html = '';
            
            // Only show "Best Parking Spot Found!" if it's from the requested location
            if (isFromRequestedLocation) {
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
                                <h4 style="color: #28a745;">${Math.round(slot.distance_from_entry || slot.distance_from_user || 0)}m</h4>
                                <small class="text-muted">Entry point</small>
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
            } else {
                // If best spot is not from requested location, show message
                html += `
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>No spaces available in <u>${destLocation}</u></strong> for selected criteria. Showing the best available options from other locations below.
                        </div>
                    </div>
                </div>
                `;
            }

            if (response.alternative_options && response.alternative_options.length > 0) {
                const headerText = isFromRequestedLocation ? 'Other Options (Different Locations)' : 'Available Options';
                html += `<div class="card"><div class="card-header"><i class="fas fa-list me-2"></i>${headerText}</div><div class="card-body" style="padding: 0;">`;

                response.alternative_options.forEach((alt, idx) => {
                    const altPrice = parseFloat(alt.price_per_hour || 0).toFixed(2);
                    const altDistance = Math.round(alt.distance_from_entry || alt.distance_from_user || 0);
                    const categoryName = alt.category_name || 'General';
                    
                    html += `
                        <div style="padding: 18px 25px; border-bottom: 1px solid #f0f0f0; transition: all 0.3s;" class="option-row" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h5 class="mb-0" style="color: #28a745;">${alt.space_number}</h5>
                                    <p class="text-muted mb-0 small"><i class="fas fa-map-pin me-1"></i>${alt.location_name}</p>
                                </div>
                                <div class="col-md-2">
                                    <p class="mb-0 small"><strong>📍 Distance:</strong></p>
                                    <p class="mb-0" style="color: #28a745; font-weight: 600;">${altDistance}m from entry</p>
                                </div>
                                <div class="col-md-2">
                                    <p class="mb-0 small"><strong>💰 Price:</strong></p>
                                    <p class="mb-0" style="color: #28a745; font-weight: 600;">रू${altPrice}/hr</p>
                                </div>
                                <div class="col-md-2">
                                    <p class="mb-0 small"><strong>📋 Type:</strong></p>
                                    <p class="mb-0 small">${categoryName}</p>
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="book_parking.php?space_id=${alt.id}" class="btn btn-sm btn-success">
                                        <i class="fas fa-check me-1"></i>Book
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div></div>';
            } else if (isFromRequestedLocation) {
                // No alternatives, but we have the best spot from requested location
                html += '<p class="text-muted text-center mt-4"><i class="fas fa-info-circle"></i> No other parking options available</p>';
            }

            document.getElementById('resultsContainer').innerHTML = html;
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
