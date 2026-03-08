<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Manage Vehicles';
$page_icon = 'car';

$conn = getDB();
$message = '';
$error = '';

// Handle Add Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
        $category_id = intval($_POST['category_id']);
        $owner_name = trim($_POST['owner_name']);
        $owner_phone = trim($_POST['owner_phone']);
        $owner_email = trim($_POST['owner_email']);
        $registration_date = $_POST['registration_date'];
        
        $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_number, category_id, owner_name, owner_phone, owner_email, registration_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $vehicle_number, $category_id, $owner_name, $owner_phone, $owner_email, $registration_date);
        
        if ($stmt->execute()) {
            $message = "Vehicle added successfully!";
            logActivity($_SESSION['user_id'], 'add_vehicle', "Added vehicle: $vehicle_number");
        } else {
            $error = "Error adding vehicle: " . $conn->error;
        }
    }
    
    // Handle Edit Vehicle
    if ($_POST['action'] === 'edit') {
        $vehicle_id = intval($_POST['vehicle_id']);
        $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
        $category_id = intval($_POST['category_id']);
        $owner_name = trim($_POST['owner_name']);
        $owner_phone = trim($_POST['owner_phone']);
        $owner_email = trim($_POST['owner_email']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE vehicles SET vehicle_number=?, category_id=?, owner_name=?, owner_phone=?, owner_email=?, status=? WHERE id=?");
        $stmt->bind_param("sissssi", $vehicle_number, $category_id, $owner_name, $owner_phone, $owner_email, $status, $vehicle_id);
        
        if ($stmt->execute()) {
            $message = "Vehicle updated successfully!";
            logActivity($_SESSION['user_id'], 'edit_vehicle', "Edited vehicle ID: $vehicle_id");
        } else {
            $error = "Error updating vehicle: " . $conn->error;
        }
    }
    
    // Handle Delete Vehicle
    if ($_POST['action'] === 'delete') {
        $vehicle_id = intval($_POST['vehicle_id']);
        
        // Check if vehicle is in use
        $check = $conn->prepare("SELECT COUNT(*) as count FROM parking_bookings WHERE vehicle_id = ? AND booking_status = 'active'");
        $check->bind_param("i", $vehicle_id);
        $check->execute();
        $result = $check->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete vehicle - it has active bookings.";
        } else {
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=?");
            $stmt->bind_param("i", $vehicle_id);
            
            if ($stmt->execute()) {
                $message = "Vehicle deleted successfully!";
                logActivity($_SESSION['user_id'], 'delete_vehicle', "Deleted vehicle ID: $vehicle_id");
            } else {
                $error = "Error deleting vehicle: " . $conn->error;
            }
        }
    }
}

// Get all vehicles with categories
$vehicles = $conn->query("
    SELECT v.*, vc.category_name 
    FROM vehicles v 
    JOIN vehicle_categories vc ON v.category_id = vc.id 
    ORDER BY v.id DESC
");

// Get categories for dropdown
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
        $total_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles")->fetch_assoc()['count'];
        $parked_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='parked'")->fetch_assoc()['count'];
        $out_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='out'")->fetch_assoc()['count'];
        $reserved_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='reserved'")->fetch_assoc()['count'];
        ?>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-car stat-icon"></i>
                <div class="stat-value"><?php echo $total_vehicles; ?></div>
                <div class="stat-label">Total Vehicles</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-parking stat-icon"></i>
                <div class="stat-value"><?php echo $parked_vehicles; ?></div>
                <div class="stat-label">Currently Parked</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-sign-out-alt stat-icon"></i>
                <div class="stat-value"><?php echo $out_vehicles; ?></div>
                <div class="stat-label">Out</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo $reserved_vehicles; ?></div>
                <div class="stat-label">Reserved</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <i class="fas fa-list me-2"></i>Vehicle Management
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                <i class="fas fa-plus me-2"></i>Add New Vehicle
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle Number</th>
                            <th>Category</th>
                            <th>Owner Name</th>
                            <th>Owner Contact</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $vehicle['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($vehicle['category_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($vehicle['owner_name'] ?: 'N/A'); ?></td>
                                <td>
                                    <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($vehicle['owner_phone'] ?: 'N/A'); ?></div>
                                    <small><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($vehicle['owner_email'] ?: 'N/A'); ?></small>
                                </td>
                                <td><?php echo $vehicle['registration_date'] ? date('d M Y', strtotime($vehicle['registration_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $vehicle['status'] == 'parked' ? 'success' : 
                                            ($vehicle['status'] == 'reserved' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($vehicle['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editVehicle(<?php echo $vehicle['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteVehicle(<?php echo $vehicle['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number *</label>
                        <input type="text" class="form-control" name="vehicle_number" 
                               placeholder="e.g., BA 1 PA 1234" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vehicle Category *</label>
                        <select class="form-control" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Owner Name *</label>
                        <input type="text" class="form-control" name="owner_name" required>
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
                        <label class="form-label">Registration Date</label>
                        <input type="date" class="form-control" name="registration_date" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number</label>
                        <input type="text" class="form-control" name="vehicle_number" id="edit_vehicle_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vehicle Category</label>
                        <select class="form-control" name="category_id" id="edit_category_id" required>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Owner Name</label>
                        <input type="text" class="form-control" name="owner_name" id="edit_owner_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Owner Phone</label>
                        <input type="text" class="form-control" name="owner_phone" id="edit_owner_phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Owner Email</label>
                        <input type="email" class="form-control" name="owner_email" id="edit_owner_email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="out">Out</option>
                            <option value="parked">Parked</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this vehicle? This action cannot be undone.</p>
                <p class="text-danger"><i class="fas fa-info-circle me-1"></i>Vehicles with active bookings cannot be deleted.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editVehicle(vehicleId) {
    $.ajax({
        url: 'get_vehicle.php',
        method: 'POST',
        data: {vehicle_id: vehicleId},
        dataType: 'json',
        success: function(vehicle) {
            $('#edit_vehicle_id').val(vehicle.id);
            $('#edit_vehicle_number').val(vehicle.vehicle_number);
            $('#edit_category_id').val(vehicle.category_id);
            $('#edit_owner_name').val(vehicle.owner_name);
            $('#edit_owner_phone').val(vehicle.owner_phone);
            $('#edit_owner_email').val(vehicle.owner_email);
            $('#edit_status').val(vehicle.status);
            $('#editVehicleModal').modal('show');
        }
    });
}

function deleteVehicle(vehicleId) {
    $('#delete_vehicle_id').val(vehicleId);
    $('#deleteVehicleModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>