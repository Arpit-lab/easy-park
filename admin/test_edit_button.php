<?php
require_once 'includes/session.php';
Session::requireAdmin();
require_once 'includes/functions.php';

$conn = getDB();
$page_title = 'Edit Button Test';
$page_icon = 'flask';

// Get a sample space
$space = $conn->query("
    SELECT id, space_number, location_name, distance_from_entry, priority_level 
    FROM parking_spaces 
    WHERE status = 'active'
    LIMIT 1
")->fetch_assoc();

include 'admin/includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="card">
        <div class="card-header">
            <i class="fas fa-flask me-2"></i>Edit Button Test
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Test Parking Space</h5>
                    <?php if ($space): ?>
                        <p><strong>Space ID:</strong> <?php echo $space['id']; ?></p>
                        <p><strong>Space Number:</strong> <?php echo $space['space_number']; ?></p>
                        <p><strong>Location:</strong> <?php echo $space['location_name']; ?></p>
                        <p><strong>Distance:</strong> <?php echo $space['distance_from_entry']; ?>m</p>
                        <p><strong>Priority:</strong> <?php echo $space['priority_level']; ?></p>
                        
                        <button class="btn btn-primary" onclick="testEditSlotParams(<?php echo $space['id']; ?>, <?php echo floatval($space['distance_from_entry']); ?>, <?php echo intval($space['priority_level']); ?>)">
                            <i class="fas fa-edit"></i> Test Edit Button
                        </button>
                    <?php else: ?>
                        <p class="text-danger">No parking spaces found!</p>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h5>Debug Info</h5>
                    <pre id="debugInfo" style="background: #f5f5f5; padding: 10px; border-radius: 5px;">
Click "Test Edit Button" to see what happens
                    </pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Slot Parameters Modal -->
<div class="modal fade" id="testEditSlotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Slot Parameters - TEST</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_slot_params">
                    <input type="hidden" name="space_id" id="test_edit_space_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Distance from Entry (meters)</label>
                        <input type="number" step="0.1" class="form-control" name="distance_from_entry" 
                               id="test_edit_distance" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Priority Level</label>
                        <select class="form-control" name="priority_level" id="test_edit_priority" required>
                            <option value="0">0 - Low</option>
                            <option value="1">1 - Medium</option>
                            <option value="2">2 - High</option>
                            <option value="3">3 - Very High</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function testEditSlotParams(spaceId, distance, priority) {
    const debug = document.getElementById('debugInfo');
    debug.innerHTML = 'Called: testEditSlotParams(' + spaceId + ', ' + distance + ', ' + priority + ')\n\n';
    
    // Check if elements exist
    const spaceIdInput = document.getElementById('test_edit_space_id');
    const distanceInput = document.getElementById('test_edit_distance');
    const prioritySelect = document.getElementById('test_edit_priority');
    const modal = document.getElementById('testEditSlotModal');
    
    debug.innerHTML += 'Space ID Input: ' + (spaceIdInput ? '✓ Found' : '✗ Not found') + '\n';
    debug.innerHTML += 'Distance Input: ' + (distanceInput ? '✓ Found' : '✗ Not found') + '\n';
    debug.innerHTML += 'Priority Select: ' + (prioritySelect ? '✓ Found' : '✗ Not found') + '\n';
    debug.innerHTML += 'Modal Element: ' + (modal ? '✓ Found' : '✗ Not found') + '\n';
    debug.innerHTML += 'Bootstrap Available: ' + (typeof bootstrap !== 'undefined' ? '✓ Yes' : '✗ No') + '\n\n';
    
    if (spaceIdInput) {
        spaceIdInput.value = spaceId;
        debug.innerHTML += 'Set space_id to: ' + spaceId + '\n';
    }
    
    if (distanceInput) {
        distanceInput.value = distance;
        debug.innerHTML += 'Set distance to: ' + distance + '\n';
    }
    
    if (prioritySelect) {
        prioritySelect.value = priority;
        debug.innerHTML += 'Set priority to: ' + priority + '\n';
    }
    
    if (modal && typeof bootstrap !== 'undefined') {
        try {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            debug.innerHTML += '\n✓ Modal opened successfully';
        } catch (error) {
            debug.innerHTML += '\n✗ Error opening modal: ' + error.message;
        }
    } else {
        debug.innerHTML += '\n✗ Cannot open modal - missing modal or Bootstrap';
    }
}
</script>

<?php include 'admin/includes/footer.php'; ?>
