<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Auto-migrate database columns if they don't exist
ensureSmartSlotColumns($conn);

// Get all active parking locations
$locations = $conn->query("SELECT DISTINCT location_name FROM parking_spaces WHERE status = 'active' ORDER BY location_name");

// Get user's previous vehicles from bookings (most recent)
$vehicle_query = "SELECT DISTINCT vehicle_number, created_at FROM parking_bookings WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$vehicles = $conn->prepare($vehicle_query);
if ($vehicles) {
    $vehicles->bind_param("i", $user_id);
    $vehicles->execute();
    $vehicles = $vehicles->get_result();
} else {
    $vehicles = null;
}

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

        /* Sidebar - Purple Theme */
        #sidebar-wrapper {
            min-width: 280px;
            max-width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* Search Card - Purple Gradient */
        .search-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
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
            color: #667eea;
            transition: all 0.3s;
            height: 48px;
        }

        .search-card .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            color: #667eea;
            margin-right: 10px;
        }

        .slot-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }

        .slot-card:hover {
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
            border-color: #667eea;
        }

        .slot-number {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }

        .score-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .btn-book-smart {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-book-smart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .explanation-text {
            background: #f5f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
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
            <div class="sidebar-footer">
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
                    <h4 class="mb-0"><i class="fas fa-lightbulb me-2" style="color: #667eea;"></i>Smart Parking Finder</h4>
                </div>
            </nav>

            <!-- Smart Recommendation Card -->
            <div class="search-card">
                <form id="smartForm" method="POST" onsubmit="return false;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Select Location</label>
                            <select class="form-select" id="location_select" required>
                                <option value="">-- Choose a location --</option>
                                <?php while ($loc = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_name']); ?>">
                                        <?php echo htmlspecialchars($loc['location_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Previous Vehicles (Optional)</label>
                            <select class="form-select" id="vehicle_select">
                                <option value="">-- Any vehicle --</option>
                                <?php 
                                if ($vehicles && $vehicles->num_rows > 0):
                                    while ($vehicle = $vehicles->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($vehicle['vehicle_number']); ?>">
                                        <?php echo htmlspecialchars($vehicle['vehicle_number']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-light w-100" onclick="getRecommendation()">
                                <i class="fas fa-magic me-2"></i>Find Best Spot
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results Section -->
            <div id="resultsContainer"></div>

            <!-- Parking Status Card -->
            <?php if (isset($prediction['occupancy_percent'])): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Parking Status
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="mb-2"><strong>Current Occupancy: <?php echo round($prediction['occupancy_percent']); ?>%</strong></p>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: <?php echo round($prediction['occupancy_percent']); ?>%">
                                    <?php echo round($prediction['occupancy_percent']); ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block mt-2">
                                <?php 
                                $occupancy = $prediction['occupancy_percent'];
                                if ($occupancy < 30) echo "✓ Excellent availability";
                                elseif ($occupancy < 60) echo "◐ Good availability";
                                else echo "✗ Limited availability";
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
        function getRecommendation() {
            const location = document.getElementById('location_select').value;
            const resultsContainer = document.getElementById('resultsContainer');
            
            if (!location) {
                alert('Please select a location');
                return;
            }
            
            resultsContainer.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="loading-spinner">
                            <div class="spinner-border" role="status" style="color: #667eea;">
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
                    location_id: location
                },
                success: function(response) {
                    if (response.success) {
                        displayRecommendation(response);
                    } else {
                        resultsContainer.innerHTML = `
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-info-circle me-2"></i>${response.message}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Unable to fetch recommendations. Please try again.';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.error) {
                            errorMsg = errorResponse.error;
                        }
                    } catch (e) {}
                    
                    resultsContainer.innerHTML = `
                        <div class="card">
                            <div class="card-body">
                                <div class="alert alert-danger mb-0">
                                    <i class="fas fa-exclamation-circle me-2"></i>${errorMsg}
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
        }

        function displayRecommendation(response) {
            const slot = response.recommended_slot;
            const scoring = response.scoring_details;
            const resultsContainer = document.getElementById('resultsContainer');
            
            const pricePerHour = parseFloat(slot.price_per_hour || 0).toFixed(2);
            
            let html = `
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                        <i class="fas fa-star me-2"></i>✓ Recommended Slot
                    </div>
                    <div class="card-body">
                        <div class="slot-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="slot-number">${slot.space_number}</div>
                                    <small class="text-muted">${slot.location_name}</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="explanation-text">
                                        <small><strong>Why this?</strong></small>
                                        <p class="mb-0 mt-2" style="font-size: 0.9em;">${response.explanation || 'Best balance of convenience and availability'}</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Distance</small>
                                        <h6>${Math.round(slot.distance_from_entry || 0)}m</h6>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Price/hr</small>
                                        <h6>रू${pricePerHour}</h6>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <span class="score-badge">${(slot.score || 0).toFixed(1)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="book_parking.php?space_id=${slot.id}" class="btn btn-book-smart">
                                <i class="fas fa-check me-2"></i>Book This Spot
                            </a>
                        </div>
                    </div>
                </div>
            `;

            if (response.alternative_options && response.alternative_options.length > 0) {
                html += `
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list me-2"></i>Alternative Options
                        </div>
                        <div class="card-body">
                `;
                
                response.alternative_options.forEach(alt => {
                    const altPrice = parseFloat(alt.price_per_hour || 0).toFixed(2);
                    html += `
                        <div class="slot-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><strong>${alt.space_number}</strong> - ${alt.location_name}</h6>
                                    <small class="text-muted"><i class="fas fa-walking"></i> ${Math.round(alt.distance_from_entry || 0)}m • रू${altPrice}/hr</small>
                                </div>
                                <div>
                                    <span class="score-badge">${(alt.score || 0).toFixed(1)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }

            resultsContainer.innerHTML = html;
        }

        // Allow Enter key to search
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.getElementById('location_select').value) {
                getRecommendation();
            }
        });
    </script>
</body>
</html>
