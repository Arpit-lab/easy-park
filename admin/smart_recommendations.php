<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Smart Slot Recommendations';
$page_icon = 'lightbulb';

$conn = getDB();
$message = '';
$error = '';
$recommendations = null;

// Auto-migrate database columns if they don't exist
ensureSmartSlotColumns($conn);

// Handle slot parameter updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_slot_params') {
        $space_id = intval($_POST['space_id']);
        $distance = floatval($_POST['distance_from_entry']);
        $priority = intval($_POST['priority_level']);
        
        if ($distance < 0 || $priority < 0 || $priority > 3) {
            $error = "Invalid values: distance must be >= 0, priority 0-3";
        } else {
            $stmt = $conn->prepare("UPDATE parking_spaces SET distance_from_entry = ?, priority_level = ? WHERE id = ?");
            $stmt->bind_param("dii", $distance, $priority, $space_id);
            
            if ($stmt->execute()) {
                $message = "Slot parameters updated successfully";
                logActivity($_SESSION['user_id'], 'update_slot_params', "Updated space ID: $space_id");
            } else {
                $error = "Error updating slot parameters: " . $conn->error;
            }
        }
    }
}

// Get all parking spaces for location selection
$locations = $conn->query("SELECT DISTINCT location_name FROM parking_spaces WHERE status = 'active' ORDER BY location_name");

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

    <!-- How Smart Recommendation Works -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
        <div class="card-body">
            <h5 class="card-title"><i class="fas fa-brain me-2"></i>Smart Slot Recommendation Engine</h5>
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6>Scoring Formula:</h6>
                    <p class="mb-0"><strong>Score = Distance + (Priority × 5)</strong></p>
                    <small>Lower score = better recommendation</small>
                </div>
                <div class="col-md-6">
                    <h6>Requirements:</h6>
                    <ul class="mb-0 small">
                        <li><strong>distance_from_entry:</strong> Meters from entry point</li>
                        <li><strong>priority_level:</strong> 0=Low, 1=Medium, 2=High, 3=Very High</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recommendation Test Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-search me-2"></i>Test Recommendations
                </div>
                <div class="card-body">
                    <form id="recommendationForm" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Location</label>
                            <select class="form-control" id="location_select" required>
                                <option value="">Choose a location...</option>
                                <?php while ($loc = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_name']); ?>">
                                        <?php echo htmlspecialchars($loc['location_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="button" class="btn btn-primary w-100" onclick="getRecommendation()">
                            <i class="fas fa-lightbulb me-2"></i>Get Best Slot
                        </button>
                    </form>
                    
                    <!-- Recommendation Result -->
                    <div id="recommendationResult" class="mt-4"></div>
                </div>
            </div>

            <!-- Scoring Comparison -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Slot Score Breakdown
                </div>
                <div class="card-body" id="scoreBreakdown">
                    <p class="text-muted">Select a location and click "Get Best Slot" to see scoring details</p>
                </div>
            </div>
        </div>

        <!-- Slot Parameters Management -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog me-2"></i>Configure Slot Parameters
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm data-table">
                            <thead>
                                <tr>
                                    <th>Space</th>
                                    <th>Location</th>
                                    <th>Distance (m)</th>
                                    <th>Priority</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $spaces = $conn->query("
                                    SELECT id, space_number, location_name, distance_from_entry, priority_level 
                                    FROM parking_spaces 
                                    WHERE status = 'active'
                                    ORDER BY location_name, space_number
                                ");
                                
                                while ($space = $spaces->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($space['space_number']); ?></td>
                                        <td><?php echo htmlspecialchars($space['location_name']); ?></td>
                                        <td><?php echo floatval($space['distance_from_entry']); ?></td>
                                        <td>
                                            <?php 
                                            $priority_labels = ['Low', 'Medium', 'High', 'Very High'];
                                            $priority = intval($space['priority_level']);
                                            $priority_label = $priority_labels[min($priority, 3)];
                                            echo "<span class='badge " . 
                                                ($priority == 0 ? "bg-success" : 
                                                 ($priority == 1 ? "bg-info" : 
                                                  ($priority == 2 ? "bg-warning" : "bg-danger"))) . 
                                                "'>$priority_label</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editSlotParams(<?php echo $space['id']; ?>, <?php echo floatval($space['distance_from_entry']); ?>, <?php echo intval($space['priority_level']); ?>)">
                                                <i class="fas fa-edit"></i>
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
    </div>
</div>

<!-- Edit Slot Parameters Modal -->
<div class="modal fade" id="editSlotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Slot Parameters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_slot_params">
                    <input type="hidden" name="space_id" id="edit_space_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Distance from Entry (meters)</label>
                        <input type="number" step="0.1" class="form-control" name="distance_from_entry" 
                               id="edit_distance" min="0" required>
                        <small class="text-muted">Typical range: 5-100m</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Priority Level (Congestion)</label>
                        <select class="form-control" name="priority_level" id="edit_priority" required>
                            <option value="0">0 - Low (empty zone)</option>
                            <option value="1">1 - Medium (moderate traffic)</option>
                            <option value="2">2 - High (busy area)</option>
                            <option value="3">3 - Very High (congested zone)</option>
                        </select>
                        <small class="text-muted">Higher = more congestion</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Score Preview:</strong>
                        <div id="scorePreview" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Parameters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    setTimeout(function() {
        if (!$.fn.DataTable.isDataTable('.data-table')) {
            $('.data-table').DataTable({
                pageLength: 10,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        }
    }, 100);
});

function getRecommendation() {
    const location = document.getElementById('location_select').value;
    
    if (!location) {
        alert('Please select a location');
        return;
    }
    
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
                document.getElementById('recommendationResult').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>${response.message}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            let errorMsg = 'Error fetching recommendation';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.error) {
                    errorMsg = errorResponse.error;
                    if (errorResponse.debug) {
                        console.log('Debug info:', errorResponse.debug);
                    }
                }
            } catch (e) {
                errorMsg = xhr.responseText || error;
            }
            
            document.getElementById('recommendationResult').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${errorMsg}
                </div>
            `;
        }
    });
}

function displayRecommendation(response) {
    const slot = response.recommended_slot;
    const details = response.scoring_details;
    
    let html = `
        <div class="card border-success">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <i class="fas fa-thumbs-up me-2"></i>Recommended: Slot ${slot.space_number}
                </h5>
                <p class="card-text"><strong>Location:</strong> ${slot.location_name}</p>
                <p class="card-text"><strong>Explanation:</strong></p>
                <p class="card-text text-muted">${response.explanation}</p>
                
                <hr>
                
                <h6>Slot Details:</h6>
                <ul class="small mb-0">
                    <li><strong>Distance from entry:</strong> ${slot.distance_from_entry}m</li>
                    <li><strong>Congestion level:</strong> ${getPriorityLabel(slot.priority_level)}</li>
                    <li><strong>Score:</strong> ${slot.score}</li>
                    <li><strong>Price/hour:</strong> रू ${slot.price_per_hour}</li>
                    <li><strong>Current occupancy:</strong> ${slot.current_occupancy} vehicles</li>
                </ul>
            </div>
        </div>
        
        <div class="mt-3">
            <h6>Alternative Options:</h6>
            <div class="list-group">
    `;
    
    response.alternative_options.forEach((alt, index) => {
        html += `
                <div class="list-group-item">
                    <strong>Option ${index + 1}: Slot ${alt.space_number}</strong>
                    <br><small>Score: ${alt.score} | Distance: ${alt.distance_from_entry}m | Price: रू ${alt.price_per_hour}/hr</small>
                </div>
        `;
    });
    
    html += `
            </div>
        </div>
    `;
    
    document.getElementById('recommendationResult').innerHTML = html;
    
    // Display scoring details
    let scoreHtml = `
        <div class="bg-light p-3 rounded">
            <p class="mb-2"><strong>Formula:</strong> <code>${details.formula}</code></p>
            <p class="mb-2"><strong>Best Score:</strong> ${details.best_score}</p>
            <p class="mb-2"><strong>Average Score:</strong> ${details.average_score}</p>
            <p class="mb-0"><strong>Total Available:</strong> ${details.total_available_slots} slots</p>
        </div>
    `;
    
    document.getElementById('scoreBreakdown').innerHTML = scoreHtml;
}

function getPriorityLabel(priority) {
    const labels = ['Low', 'Medium', 'High', 'Very High'];
    return labels[Math.min(priority, 3)];
}

function editSlotParams(spaceId, distance, priority) {
    document.getElementById('edit_space_id').value = spaceId;
    document.getElementById('edit_distance').value = distance;
    document.getElementById('edit_priority').value = priority;
    updateScorePreview();
    new bootstrap.Modal(document.getElementById('editSlotModal')).show();
}

function updateScorePreview() {
    const distance = parseFloat(document.getElementById('edit_distance').value) || 0;
    const priority = parseInt(document.getElementById('edit_priority').value) || 0;
    const score = distance + (priority * 5);
    
    document.getElementById('scorePreview').innerHTML = `
        <code>${distance} + (${priority} × 5) = <strong>${score.toFixed(2)}</strong></code>
    `;
}

// Update score preview when inputs change
document.addEventListener('input', function(e) {
    if (e.target.id === 'edit_distance' || e.target.id === 'edit_priority') {
        updateScorePreview();
    }
}, true);

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => bsAlert.close(), 5000);
    });
}, 100);
</script>

<?php include 'includes/footer.php'; ?>
