<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Incoming Vehicle';
$page_icon = 'arrow-right';

$conn = getDB();
$message = '';
$error = '';

// Handle vehicle entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
    $space_id = intval($_POST['space_id']);
    $category_id = intval($_POST['category_id']);
    $owner_name = trim($_POST['owner_name']);
    $owner_phone = trim($_POST['owner_phone']);
    $owner_email = trim($_POST['owner_email']);
    
    // Check if space is available
    $space_check = $conn->prepare("
        SELECT COUNT(*) as count FROM parking_bookings 
        WHERE space_id = ? AND booking_status = 'active'
    ");
    $space_check->bind_param("i", $space_id);
    $space_check->execute();
    $result = $space_check->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = "This parking space is already occupied!";
    } else {
        // Check if vehicle exists
        $vehicle_check = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number = ?");
        $vehicle_check->bind_param("s", $vehicle_number);
        $vehicle_check->execute();
        $vehicle_result = $vehicle_check->get_result();
        
        if ($vehicle_result->num_rows > 0) {
            $vehicle = $vehicle_result->fetch_assoc();
            $vehicle_id = $vehicle['id'];
            
            // Update vehicle status
            $conn->query("UPDATE vehicles SET status = 'parked' WHERE id = $vehicle_id");
        } else {
            // Create new vehicle
            $vehicle_stmt = $conn->prepare("
                INSERT INTO vehicles (vehicle_number, category_id, owner_name, owner_phone, owner_email, status) 
                VALUES (?, ?, ?, ?, ?, 'parked')
            ");
            $vehicle_stmt->bind_param("sisss", $vehicle_number, $category_id, $owner_name, $owner_phone, $owner_email);
            $vehicle_stmt->execute();
            $vehicle_id = $conn->insert_id;
        }
        
        // Create booking
        $booking_number = generateBookingNumber();
        $check_in = date('Y-m-d H:i:s');
        
        $booking_stmt = $conn->prepare("
            INSERT INTO parking_bookings (booking_number, space_id, vehicle_id, vehicle_number, check_in, booking_status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $booking_stmt->bind_param("siiss", $booking_number, $space_id, $vehicle_id, $vehicle_number, $check_in);
        
        if ($booking_stmt->execute()) {
            $booking_id = $conn->insert_id;
            
            // Update space availability
            $conn->query("UPDATE parking_spaces SET is_available = 0 WHERE id = $space_id");
            
            $message = "Vehicle checked in successfully! Booking #: $booking_number";
            logActivity($_SESSION['user_id'], 'vehicle_entry', "Vehicle $vehicle_number entered space ID: $space_id");
        } else {
            $error = "Error processing vehicle entry: " . $conn->error;
        }
    }
}

// Get available spaces
$available_spaces = $conn->query("
    SELECT ps.*, vc.category_name 
    FROM parking_spaces ps 
    JOIN vehicle_categories vc ON ps.category_id = vc.id 
    WHERE ps.status = 'active' AND ps.is_available = 1
    ORDER BY ps.space_number
");

// Get vehicle categories
$categories = $conn->query("SELECT * FROM vehicle_categories WHERE status = 'active'");

include 'includes/header.php';
?>

<div class="container-fluid px-0">
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

    <!-- Stats Cards -->
    <div class="row mb-4">
        <?php
        $available_count = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'active' AND is_available = 1")->fetch_assoc()['count'];
        $total_spaces = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'active'")->fetch_assoc()['count'];
        $occupancy = $total_spaces > 0 ? round((($total_spaces - $available_count) / $total_spaces) * 100) : 0;
        ?>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-parking stat-icon"></i>
                <div class="stat-value"><?php echo $available_count; ?></div>
                <div class="stat-label">Available Spaces</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <div class="stat-value"><?php echo $occupancy; ?>%</div>
                <div class="stat-label">Current Occupancy</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card primary">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo date('h:i A'); ?></div>
                <div class="stat-label">Current Time</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Available Spaces -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-parking me-2"></i>Select Parking Space
                </div>
                <div class="card-body">
                    <?php if ($available_spaces->num_rows > 0): ?>
                        <div class="row">
                            <?php while ($space = $available_spaces->fetch_assoc()): ?>
                                <div class="col-md-6">
                                    <div class="card mb-3" style="cursor: pointer;" onclick="selectSpace(<?php echo $space['id']; ?>, '<?php echo $space['space_number']; ?>', <?php echo $space['category_id']; ?>)">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1">Space <?php echo htmlspecialchars($space['space_number']); ?></h5>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($space['location_name']); ?>
                                                    </p>
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($space['category_name']); ?></span>
                                                    <span class="badge badge-success ms-2">रू <?php echo $space['price_per_hour']; ?>/hr</span>
                                                </div>
                                                <div class="text-success">
                                                    <i class="fas fa-check-circle fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No parking spaces available at the moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Vehicle Entry Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-car me-2"></i>Vehicle Details
                </div>
                <div class="card-body">
                    <form method="POST" id="incomingForm">
                        <input type="hidden" name="space_id" id="selected_space_id" required>
                        <input type="hidden" name="category_id" id="selected_category_id" required>
                        
                        <div class="mb-3">
                            <label class="form-label">Selected Space</label>
                            <div class="form-control bg-light" id="selected_space_display">None selected</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle Number *</label>
                            <input type="text" class="form-control" name="vehicle_number" 
                                   placeholder="e.g., BA 1 PA 1234" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Owner Name</label>
                            <input type="text" class="form-control" name="owner_name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Owner Phone</label>
                            <input type="text" class="form-control" name="owner_phone">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Owner Email</label>
                            <input type="email" class="form-control" name="owner_email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Check-in Time</label>
                            <div class="form-control bg-light">
                                <?php echo date('Y-m-d H:i:s'); ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100" <?php echo ($available_spaces->num_rows == 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle me-2"></i>Check In Vehicle
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectSpace(spaceId, spaceNumber, categoryId) {
    document.getElementById('selected_space_id').value = spaceId;
    document.getElementById('selected_category_id').value = categoryId;
    document.getElementById('selected_space_display').innerHTML = spaceNumber;
    
    // Highlight selected card
    document.querySelectorAll('.card').forEach(card => {
        card.style.border = 'none';
    });
    event.currentTarget.style.border = '2px solid #28a745';
}

// Form validation
document.getElementById('incomingForm').addEventListener('submit', function(e) {
    const spaceId = document.getElementById('selected_space_id').value;
    const vehicleNumber = document.querySelector('input[name="vehicle_number"]').value.trim();
    
    if (!spaceId) {
        e.preventDefault();
        alert('Please select a parking space');
        return;
    }
    
    if (!vehicleNumber) {
        e.preventDefault();
        alert('Please enter vehicle number');
        return;
    }
});
</script>

<?php include 'includes/footer.php'; ?>