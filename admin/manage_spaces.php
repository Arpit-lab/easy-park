<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Parking Spaces';
$page_icon = 'map-marker-alt';

$conn = getDB();
$message = '';
$error = '';

// Handle Add Space
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $space_number = strtoupper(trim($_POST['space_number']));
        $category_id = intval($_POST['category_id']);
        $location_name = trim($_POST['location_name']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $address = trim($_POST['address']);
        $price_per_hour = floatval($_POST['price_per_hour']);
        
        $stmt = $conn->prepare("INSERT INTO parking_spaces (space_number, category_id, location_name, latitude, longitude, address, price_per_hour) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisddsd", $space_number, $category_id, $location_name, $latitude, $longitude, $address, $price_per_hour);
        
        if ($stmt->execute()) {
            $message = "Parking space added successfully!";
            logActivity($_SESSION['user_id'], 'add_space', "Added space: $space_number");
        } else {
            $error = "Error adding space: " . $conn->error;
        }
    }
    
    // Handle Edit Space
    if ($_POST['action'] === 'edit') {
        $space_id = intval($_POST['space_id']);
        $space_number = strtoupper(trim($_POST['space_number']));
        $category_id = intval($_POST['category_id']);
        $location_name = trim($_POST['location_name']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $address = trim($_POST['address']);
        $price_per_hour = floatval($_POST['price_per_hour']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE parking_spaces SET space_number=?, category_id=?, location_name=?, latitude=?, longitude=?, address=?, price_per_hour=?, status=? WHERE id=?");
        $stmt->bind_param("sisddsdsi", $space_number, $category_id, $location_name, $latitude, $longitude, $address, $price_per_hour, $status, $space_id);
        
        if ($stmt->execute()) {
            $message = "Parking space updated successfully!";
            logActivity($_SESSION['user_id'], 'edit_space', "Edited space ID: $space_id");
        } else {
            $error = "Error updating space: " . $conn->error;
        }
    }
    
    // Handle Delete Space
    if ($_POST['action'] === 'delete') {
        $space_id = intval($_POST['space_id']);
        
        // Check if space has active bookings
        $check = $conn->prepare("SELECT COUNT(*) as count FROM parking_bookings WHERE space_id = ? AND booking_status = 'active'");
        $check->bind_param("i", $space_id);
        $check->execute();
        $result = $check->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete space - it has active bookings.";
        } else {
            $stmt = $conn->prepare("DELETE FROM parking_spaces WHERE id=?");
            $stmt->bind_param("i", $space_id);
            
            if ($stmt->execute()) {
                $message = "Parking space deleted successfully!";
                logActivity($_SESSION['user_id'], 'delete_space', "Deleted space ID: $space_id");
            } else {
                $error = "Error deleting space: " . $conn->error;
            }
        }
    }
}

// Get all parking spaces
$spaces = $conn->query("
    SELECT ps.*, vc.category_name,
           (SELECT COUNT(*) FROM parking_bookings WHERE space_id = ps.id AND booking_status = 'active') as current_bookings
    FROM parking_spaces ps 
    JOIN vehicle_categories vc ON ps.category_id = vc.id 
    ORDER BY ps.id DESC
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
        $total_spaces = $conn->query("SELECT COUNT(*) as count FROM parking_spaces")->fetch_assoc()['count'];
        $available_spaces = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE is_available = 1 AND status = 'active'")->fetch_assoc()['count'];
        $occupied_spaces = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE is_available = 0 AND status = 'active'")->fetch_assoc()['count'];
        $maintenance_spaces = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'maintenance'")->fetch_assoc()['count'];
        ?>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-parking stat-icon"></i>
                <div class="stat-value"><?php echo $total_spaces; ?></div>
                <div class="stat-label">Total Spaces</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-value"><?php echo $available_spaces; ?></div>
                <div class="stat-label">Available</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-car stat-icon"></i>
                <div class="stat-value"><?php echo $occupied_spaces; ?></div>
                <div class="stat-label">Occupied</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-tools stat-icon"></i>
                <div class="stat-value"><?php echo $maintenance_spaces; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <i class="fas fa-list me-2"></i>Parking Spaces Management
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSpaceModal">
                <i class="fas fa-plus me-2"></i>Add New Space
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Space Number</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Price/Hour</th>
                            <th>Status</th>
                            <th>Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($space = $spaces->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $space['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($space['space_number']); ?></strong>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($space['location_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($space['address']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($space['category_name']); ?></td>
                                <td><strong>रू <?php echo number_format($space['price_per_hour'], 2); ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $space['status'] == 'active' ? 'success' : 
                                            ($space['status'] == 'maintenance' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($space['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($space['is_available']): ?>
                                        <span class="badge badge-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Occupied</span>
                                    <?php endif; ?>
                                    <br>
                                    <small><?php echo $space['current_bookings']; ?> active bookings</small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editSpace(<?php echo $space['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSpace(<?php echo $space['id']; ?>)">
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

<!-- Add Space Modal -->
<div class="modal fade" id="addSpaceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Parking Space</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Space Number *</label>
                            <input type="text" class="form-control" name="space_number" 
                                   placeholder="e.g., A001" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
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
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location Name *</label>
                        <input type="text" class="form-control" name="location_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="any" class="form-control" name="latitude" 
                                   id="latitude" value="27.7172">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="any" class="form-control" name="longitude" 
                                   id="longitude" value="85.3240">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Price Per Hour (रू) *</label>
                        <input type="number" step="0.01" class="form-control" name="price_per_hour" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Space</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Space Modal -->
<div class="modal fade" id="editSpaceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Parking Space</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="space_id" id="edit_space_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Space Number</label>
                            <input type="text" class="form-control" name="space_number" id="edit_space_number" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
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
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" class="form-control" name="location_name" id="edit_location_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="any" class="form-control" name="latitude" id="edit_latitude">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="any" class="form-control" name="longitude" id="edit_longitude">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price Per Hour (रू)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_hour" id="edit_price" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Space</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSpaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this parking space? This action cannot be undone.</p>
                <p class="text-danger"><i class="fas fa-info-circle me-1"></i>Spaces with active bookings cannot be deleted.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="space_id" id="delete_space_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Space</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSpace(spaceId) {
    $.ajax({
        url: 'get_space.php',
        method: 'POST',
        data: {space_id: spaceId},
        dataType: 'json',
        success: function(space) {
            $('#edit_space_id').val(space.id);
            $('#edit_space_number').val(space.space_number);
            $('#edit_category_id').val(space.category_id);
            $('#edit_location_name').val(space.location_name);
            $('#edit_latitude').val(space.latitude);
            $('#edit_longitude').val(space.longitude);
            $('#edit_address').val(space.address);
            $('#edit_price').val(space.price_per_hour);
            $('#edit_status').val(space.status);
            $('#editSpaceModal').modal('show');
        }
    });
}

function deleteSpace(spaceId) {
    $('#delete_space_id').val(spaceId);
    $('#deleteSpaceModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>