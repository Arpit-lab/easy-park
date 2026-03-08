<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Search Vehicle';
$page_icon = 'search';

$conn = getDB();
$search_results = null;
$search_term = '';

if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    if (!empty($search_term)) {
        $search_param = "%$search_term%";
        
        $stmt = $conn->prepare("
            SELECT v.*, vc.category_name,
                   (SELECT COUNT(*) FROM parking_bookings WHERE vehicle_id = v.id AND booking_status = 'active') as is_parked
            FROM vehicles v
            JOIN vehicle_categories vc ON v.category_id = vc.id
            WHERE v.vehicle_number LIKE ? 
               OR v.owner_name LIKE ? 
               OR v.owner_phone LIKE ?
               OR v.owner_email LIKE ?
            ORDER BY v.id DESC
        ");
        $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
        $stmt->execute();
        $search_results = $stmt->get_result();
    }
}

include 'includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Search Card -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search me-2"></i>Search Vehicles
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" name="search" 
                                   placeholder="Search by vehicle number, owner name, phone or email..."
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_results): ?>
        <!-- Results Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Search Results (<?php echo $search_results->num_rows; ?> found)
            </div>
            <div class="card-body">
                <?php if ($search_results->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Vehicle Number</th>
                                    <th>Category</th>
                                    <th>Owner</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($vehicle = $search_results->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($vehicle['category_name']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($vehicle['owner_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($vehicle['owner_phone'] ?: 'N/A'); ?></div>
                                            <small><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($vehicle['owner_email'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($vehicle['is_parked'] > 0): ?>
                                                <span class="badge badge-success">Parked</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewHistory(<?php echo $vehicle['id']; ?>)">
                                                <i class="fas fa-history"></i> History
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No vehicles found matching your search.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Vehicle History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vehicle History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
function viewHistory(vehicleId) {
    $('#historyContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#historyModal').modal('show');
    
    $.ajax({
        url: 'vehicle_history.php',
        method: 'POST',
        data: {vehicle_id: vehicleId},
        success: function(response) {
            $('#historyContent').html(response);
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>