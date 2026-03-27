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
        $section_letter = strtoupper(trim($_POST['section_letter']));
        $category_id = intval($_POST['category_id']);
        $location_name = trim($_POST['location_name']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $address = trim($_POST['address']);
        $price_per_hour = floatval($_POST['price_per_hour']);
        $distance_from_entry = isset($_POST['distance_from_entry']) ? floatval($_POST['distance_from_entry']) : 0;
        $number_of_spaces = intval($_POST['number_of_spaces'] ?? 1);
        
        // Validate section letter (single letter)
        if (empty($section_letter) || strlen($section_letter) !== 1 || !ctype_alpha($section_letter)) {
            $error = "Section letter must be a single letter (A-Z)";
        } else {
            // Ensure number_of_spaces is at least 1
            $number_of_spaces = max(1, $number_of_spaces);
            
            // Get the next sequential number for this section
            $next_number_result = $conn->query("
                SELECT MAX(CAST(SUBSTRING(space_number, 2) AS UNSIGNED)) as max_num 
                FROM parking_spaces 
                WHERE space_number LIKE '$section_letter%' 
                AND SUBSTRING(space_number, 1, 1) = '$section_letter'
            ");
            $row = $next_number_result->fetch_assoc();
            $next_num = ($row['max_num'] ?? 0) + 1;
            
            $added_count = 0;
            $failed_count = 0;
            
            // Create multiple spaces with auto-generated numbers
            for ($i = 0; $i < $number_of_spaces; $i++) {
                // Auto-generate space number: A001, A002, A003, etc.
                $current_space_number = $section_letter . str_pad($next_num + $i, 3, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("INSERT INTO parking_spaces (space_number, category_id, location_name, latitude, longitude, address, price_per_hour, distance_from_entry) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisddsdd", $current_space_number, $category_id, $location_name, $latitude, $longitude, $address, $price_per_hour, $distance_from_entry);
                
                if ($stmt->execute()) {
                    $added_count++;
                    logActivity($_SESSION['user_id'], 'add_space', "Added space: $current_space_number");
                } else {
                    $failed_count++;
                    error_log("Error adding space $current_space_number: " . $conn->error);
                }
            }
            
            if ($added_count > 0) {
                $message = "Successfully added $added_count parking space(s)!";
                if ($failed_count > 0) {
                    $error = "Note: $failed_count space(s) failed to be added.";
                }
            } else {
                $error = "Error adding parking spaces: " . $conn->error;
            }
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
        $distance_from_entry = isset($_POST['distance_from_entry']) ? floatval($_POST['distance_from_entry']) : 0;
        $status = $_POST['status'];
        
        // Get current space details to identify related spaces
        $current_stmt = $conn->prepare("SELECT location_name, category_id FROM parking_spaces WHERE id = ?");
        $current_stmt->bind_param("i", $space_id);
        $current_stmt->execute();
        $current_space = $current_stmt->get_result()->fetch_assoc();
        
        if ($current_space) {
            $current_location = trim($current_space['location_name']);
            $current_category = $current_space['category_id'];

            // Update all spaces with the same location and category (case/space normalized matching)
            $update_stmt = $conn->prepare("UPDATE parking_spaces SET location_name=?, latitude=?, longitude=?, address=?, price_per_hour=?, distance_from_entry=?, status=? WHERE TRIM(LOWER(location_name)) = TRIM(LOWER(?)) AND category_id <=> ?");
            $update_stmt->bind_param("sddsddssi", $location_name, $latitude, $longitude, $address, $price_per_hour, $distance_from_entry, $status, $current_location, $current_category);

            if ($update_stmt->execute()) {
                if ($space_number !== '') {
                    $space_update_stmt = $conn->prepare("UPDATE parking_spaces SET space_number=? WHERE id=?");
                    $space_update_stmt->bind_param("si", $space_number, $space_id);
                    $space_update_stmt->execute();
                }

                $affected_rows = $update_stmt->affected_rows;
                if ($affected_rows <= 0) {
                    // fallback: update at least the target record if row values were unchanged or no matching group found
                    $single_update = $conn->prepare("UPDATE parking_spaces SET location_name=?, latitude=?, longitude=?, address=?, price_per_hour=?, distance_from_entry=?, status=? WHERE id=?");
                    $single_update->bind_param("sddsddsi", $location_name, $latitude, $longitude, $address, $price_per_hour, $distance_from_entry, $status, $space_id);
                    $single_update->execute();
                    $affected_rows = max($affected_rows, $single_update->affected_rows);
                }

                $message = "Successfully changed.";
                logActivity($_SESSION['user_id'], 'edit_space', "Edited $affected_rows space(s) at $current_location (category $current_category)");
            } else {
                $error = "Error updating spaces: " . $conn->error;
            }
        } else {
            $error = "Space not found.";
        }
    }
    
    // Handle Delete Space
    if ($_POST['action'] === 'delete') {
        $space_id = intval($_POST['space_id']);
        
        // Prevent deleting if there are active bookings for that space
        $booking_stmt = $conn->prepare("SELECT COUNT(*) as booking_count FROM parking_bookings WHERE space_id = ? AND booking_status = 'active'");
        $booking_stmt->bind_param("i", $space_id);
        $booking_stmt->execute();
        $booking_check = $booking_stmt->get_result()->fetch_assoc();

        if (!empty($booking_check['booking_count'])) {
            $error = "Cannot delete space because there are active bookings for this space. Please end active booking before deleting.";
        } else {
            // Clean up dependent child rows to satisfy FK constraints before deleting the space.
            // Remove all transactions for bookings of this space
            $txn_cleanup_stmt = $conn->prepare(
                "DELETE pt FROM parking_transactions pt 
                 JOIN parking_bookings pb ON pt.booking_id = pb.id 
                 WHERE pb.space_id = ?"
            );
            $txn_cleanup_stmt->bind_param("i", $space_id);
            $txn_cleanup_stmt->execute();

            // Remove all bookings for this space (non-active or completed/cancelled history)
            $booking_cleanup_stmt = $conn->prepare("DELETE FROM parking_bookings WHERE space_id = ?");
            $booking_cleanup_stmt->bind_param("i", $space_id);
            $booking_cleanup_stmt->execute();

            // Get the space being deleted to determine section
            $space_result = $conn->query("SELECT space_number FROM parking_spaces WHERE id = $space_id");
            $space_data = $space_result->fetch_assoc();
            $deleted_space_number = $space_data['space_number'] ?? '';
            
            // Extract section letter (first character)
            $section = substr($deleted_space_number, 0, 1);
            
            // Delete the space
            $stmt = $conn->prepare("DELETE FROM parking_spaces WHERE id=?");
            $stmt->bind_param("i", $space_id);
            
            if ($stmt->execute()) {
                $message = "Parking space deleted successfully!";

                // Auto-renumber: Get all remaining spaces in this section
                $renumber_result = $conn->query(
                    "SELECT id, space_number FROM parking_spaces 
                    WHERE SUBSTRING(space_number, 1, 1) = '$section' 
                    ORDER BY CAST(SUBSTRING(space_number, 2) AS UNSIGNED) ASC"
                );
                
                // Renumber them sequentially
                $counter = 1;
                while ($space = $renumber_result->fetch_assoc()) {
                    $new_number = $section . str_pad($counter, 3, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE parking_spaces SET space_number = '$new_number' WHERE id = " . intval($space['id']));
                    $counter++;
                }
                
                // Log the action
                logActivity($_SESSION['user_id'], 'delete_space', "Deleted space ID: $space_id (was $deleted_space_number) and renumbered section $section");
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
    ORDER BY
      SUBSTRING(ps.space_number, 1, 1) ASC,
      CAST(SUBSTRING(ps.space_number, 2) AS UNSIGNED) ASC
");

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM vehicle_categories WHERE status = 'active'");

// Location-level availability summary with category breakdown
$location_summary = $conn->query("
    SELECT 
        ps.location_name,
        COUNT(*) as total_spaces,
        SUM(ps.is_available = 1) as available_spaces,
        SUM(ps.is_available = 0) as occupied_spaces,
        COUNT(DISTINCT ps.category_id) as category_count,
        GROUP_CONCAT(DISTINCT vc.category_name ORDER BY vc.category_name SEPARATOR ', ') as categories
    FROM parking_spaces ps
    LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE ps.status = 'active'
    GROUP BY ps.location_name
    ORDER BY available_spaces DESC
");

// Get detailed category breakdown for each location
$location_details = [];
$detail_query = $conn->query("
    SELECT 
        ps.location_name,
        vc.category_name,
        COUNT(*) as total_spaces,
        SUM(ps.is_available = 1) as available_spaces,
        SUM(ps.is_available = 0) as occupied_spaces
    FROM parking_spaces ps
    LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
    WHERE ps.status = 'active'
    GROUP BY ps.location_name, vc.category_name
    ORDER BY ps.location_name, vc.category_name
");

while ($detail = $detail_query->fetch_assoc()) {
    $location_details[$detail['location_name']][] = $detail;
}

include 'includes/header.php';
?>

<style>
    .leaflet-container {
        background: #f0f8ff;
    }
    
    .form-label {
        font-weight: 500;
        color: #333;
    }
    
    .form-label.fw-bold {
        font-weight: 600;
    }
    
    .modal-body {
        max-height: 85vh;
        overflow-y: auto;
    }
</style>

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

    <!-- Location-level Availability -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-map-marker-alt me-2"></i>Availability By Location
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Total Spaces</th>
                            <th>Available</th>
                            <th>Occupied</th>
                            <th>Categories</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $location_summary->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['location_name'] ?: 'Unknown'); ?></td>
                                <td><?php echo intval($item['total_spaces']); ?></td>
                                <td><span class="badge badge-success"><?php echo intval($item['available_spaces']); ?></span></td>
                                <td><span class="badge badge-danger"><?php echo intval($item['occupied_spaces']); ?></span></td>
                                <td><?php echo intval($item['category_count']); ?> types</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo md5($item['location_name']); ?>" aria-expanded="false">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="p-0">
                                    <div class="collapse" id="details-<?php echo md5($item['location_name']); ?>">
                                        <div class="card card-body m-2">
                                            <h6>Category Breakdown for <?php echo htmlspecialchars($item['location_name']); ?></h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th>Total Spaces</th>
                                                            <th>Available</th>
                                                            <th>Occupied</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (isset($location_details[$item['location_name']])): ?>
                                                            <?php foreach ($location_details[$item['location_name']] as $detail): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($detail['category_name']); ?></td>
                                                                    <td><?php echo intval($detail['total_spaces']); ?></td>
                                                                    <td><span class="badge badge-success"><?php echo intval($detail['available_spaces']); ?></span></td>
                                                                    <td><span class="badge badge-danger"><?php echo intval($detail['occupied_spaces']); ?></span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
                        <?php $row_number = 1; while ($space = $spaces->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row_number++; ?></td>
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
                            <label class="form-label">Section Letter *</label>
                            <input type="text" class="form-control" name="section_letter" 
                                   placeholder="e.g., A, B, C" maxlength="1" 
                                   style="text-transform: uppercase;" required>
                            <small class="text-muted">Single letter (A-Z). System will auto-generate: A001, A002, A003, etc.</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Number of Spaces</label>
                            <input type="number" class="form-control" name="number_of_spaces" 
                                   value="1" min="1" max="100">
                            <small class="text-muted">Creates sequential spaces in this section</small>
                        </div>
                    </div>
                    
                    <div class="row">
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
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold" style="color: #007bff;">📍 Select on Map</label>
                            <div id="spaceMap" style="height: 320px; width: 100%; border: 2px solid #007bff; border-radius: 8px; background: #f0f8ff;"></div>
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

                    <div class="mb-3">
                        <label class="form-label">Distance from Entry (meters)</label>
                        <input type="number" step="0.1" class="form-control" name="distance_from_entry"
                               placeholder="e.g., 50.5" min="0" value="0">
                        <small class="text-muted">Distance from parking entry point in meters. Used for smart recommendations.</small>
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
                            <label class="form-label">Space Number (Auto-managed)</label>
                            <input type="text" class="form-control" name="space_number" id="edit_space_number" readonly>
                            <small class="text-muted">Space numbers are automatically managed by the system.</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-control" name="category_id" id="edit_category_id" required readonly>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Category cannot be changed individually. Edit affects all spaces in this category at this location.</small>
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
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold" style="color: #007bff;">📍 Select on Map</label>
                            <div id="editSpaceMap" style="height: 320px; width: 100%; border: 2px solid #007bff; border-radius: 8px; background: #f0f8ff;"></div>
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
                            <label class="form-label">Distance from Entry (meters)</label>
                            <input type="number" step="0.1" class="form-control" name="distance_from_entry" id="edit_distance_from_entry"
                                   placeholder="e.g., 50.5" min="0">
                            <small class="text-muted">Distance from parking entry point in meters.</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
                <p class="text-info"><i class="fas fa-info-circle me-1"></i><strong>Auto-renumbering:</strong> Other spaces in the same section will be automatically renumbered to maintain continuity.</p>
                <p class="text-danger"><i class="fas fa-warning me-1"></i>Spaces with active bookings cannot be deleted.</p>
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
            if (space.success) {
                $('#edit_space_id').val(space.id);
                $('#edit_space_number').val(space.space_number);
                $('#edit_category_id').val(space.category_id);
                $('#edit_location_name').val(space.location_name);
                $('#edit_latitude').val(space.latitude);
                $('#edit_longitude').val(space.longitude);
                $('#edit_address').val(space.address);
                $('#edit_price').val(space.price_per_hour);
                $('#edit_distance_from_entry').val(space.distance_from_entry);
                $('#edit_status').val(space.status);
                $('#editSpaceModal').modal('show');
            } else {
                alert('Error loading space data: ' + space.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error loading space data. Check console for details.');
            console.error('AJAX Error:', status, error, xhr.responseText);
        }
    });
}

function deleteSpace(spaceId) {
    $('#delete_space_id').val(spaceId);
    $('#deleteSpaceModal').modal('show');
}

var addSpaceMap, addSpaceMarker, editSpaceMap, editSpaceMarker, geocoder;

function ensureLeaflet() {
    if (!window.L) {
        alert('Leaflet JS is not loaded. Please check your internet connection.');
        throw new Error('Leaflet JS not loaded');
    }
}

function resetPlaceMarker(mapObj, markerVar, lat, lng) {
    if (markerVar) mapObj.removeLayer(markerVar);

    // Create red dot marker using Leaflet
    const redDotIcon = L.icon({
        iconUrl: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16]
    });

    const marker = L.marker([lat, lng], { icon: redDotIcon }).addTo(mapObj);
    mapObj.setView([lat, lng], 16);
    return marker;
}

function geocodeLatLngAndFill(lat, lng, locationInputSelector, addressInputSelector) {
    // Use Nominatim (OpenStreetMap) - 100% FREE
    const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;

    fetch(nominatimUrl)
        .then(response => response.json())
        .then(data => {
            if (data && data.display_name) {
                // Extract location name from address components
                let locationName = data.display_name;

                // Try to get a more user-friendly name
                if (data.address) {
                    const addr = data.address;
                    if (addr.neighbourhood) locationName = addr.neighbourhood;
                    else if (addr.suburb) locationName = addr.suburb;
                    else if (addr.city_district) locationName = addr.city_district;
                    else if (addr.city) locationName = addr.city;
                    else if (addr.town) locationName = addr.town;
                    else if (addr.village) locationName = addr.village;
                }

                $(locationInputSelector).val(locationName);
                $(addressInputSelector).val(data.display_name);
            }
        })
        .catch(error => {
            console.error('Geocoding error:', error);
            // Fallback: just show coordinates
            $(locationInputSelector).val(`Location (${lat.toFixed(4)}, ${lng.toFixed(4)})`);
            $(addressInputSelector).val(`Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`);
        });
}

function initAddSpaceMap() {
    ensureLeaflet();
    if (!addSpaceMap) {
        addSpaceMap = L.map('spaceMap').setView([27.7172, 85.3240], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(addSpaceMap);

        addSpaceMap.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            $('#latitude').val(lat.toFixed(6));
            $('#longitude').val(lng.toFixed(6));
            addSpaceMarker = resetPlaceMarker(addSpaceMap, addSpaceMarker, lat, lng);
            geocodeLatLngAndFill(lat, lng, '#location_name', 'textarea[name="address"]');
        });
    }
    var lat = parseFloat($('#latitude').val()) || 27.7172;
    var lng = parseFloat($('#longitude').val()) || 85.3240;
    addSpaceMarker = resetPlaceMarker(addSpaceMap, addSpaceMarker, lat, lng);
    addSpaceMap.setView([lat, lng], 13);
}

function initEditSpaceMap() {
    ensureLeaflet();
    if (!editSpaceMap) {
        editSpaceMap = L.map('editSpaceMap').setView([27.7172, 85.3240], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(editSpaceMap);

        editSpaceMap.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            $('#edit_latitude').val(lat.toFixed(6));
            $('#edit_longitude').val(lng.toFixed(6));
            editSpaceMarker = resetPlaceMarker(editSpaceMap, editSpaceMarker, lat, lng);
            geocodeLatLngAndFill(lat, lng, '#edit_location_name', '#edit_address');
        });
    }
    var lat = parseFloat($('#edit_latitude').val()) || 27.7172;
    var lng = parseFloat($('#edit_longitude').val()) || 85.3240;
    editSpaceMarker = resetPlaceMarker(editSpaceMap, editSpaceMarker, lat, lng);
    editSpaceMap.setView([lat, lng], 13);
}

$('#addSpaceModal').on('shown.bs.modal', function() {
    try { initAddSpaceMap(); } catch (e) { console.error(e); }
});

$('#editSpaceModal').on('shown.bs.modal', function() {
    try { initEditSpaceMap(); } catch (e) { console.error(e); }
});

$('#latitude, #longitude').on('change', function() {
    if (addSpaceMap) {
        var lat = parseFloat($('#latitude').val());
        var lng = parseFloat($('#longitude').val());
        if (!isNaN(lat) && !isNaN(lng)) {
            addSpaceMarker = resetPlaceMarker(addSpaceMap, addSpaceMarker, lat, lng);
            geocodeLatLngAndFill(lat, lng, '#location_name', 'textarea[name="address"]');
        }
    }
});

$('#edit_latitude, #edit_longitude').on('change', function() {
    if (editSpaceMap) {
        var lat = parseFloat($('#edit_latitude').val());
        var lng = parseFloat($('#edit_longitude').val());
        if (!isNaN(lat) && !isNaN(lng)) {
            editSpaceMarker = resetPlaceMarker(editSpaceMap, editSpaceMarker, lat, lng);
            geocodeLatLngAndFill(lat, lng, '#edit_location_name', '#edit_address');
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>