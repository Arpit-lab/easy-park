<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Anomaly Alerts';
$page_icon = 'exclamation-triangle';

$conn = getDB();

// Handle alert resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve') {
        $alert_id = intval($_POST['alert_id']);
        
        $stmt = $conn->prepare("
            UPDATE anomaly_alerts 
            SET status = 'resolved', resolved_at = NOW(), resolved_by = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $_SESSION['user_id'], $alert_id);
        $stmt->execute();
        
        logActivity($_SESSION['user_id'], 'resolve_alert', "Resolved anomaly alert #$alert_id");
    }
}

// Get alerts
$alerts = $conn->query("
    SELECT a.*, ps.space_number, u.full_name as resolver_name
    FROM anomaly_alerts a
    LEFT JOIN parking_spaces ps ON a.space_id = ps.id
    LEFT JOIN users u ON a.resolved_by = u.id
    ORDER BY 
        CASE a.status 
            WHEN 'new' THEN 1 
            WHEN 'investigating' THEN 2 
            ELSE 3 
        END,
        a.detected_at DESC
");

// Get statistics - FIXED: removed 'high_priority' which doesn't exist
$stats = $conn->query("
    SELECT 
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
        COUNT(CASE WHEN status = 'investigating' THEN 1 END) as investigating_count,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count,
        COUNT(CASE WHEN severity = 'high' AND status != 'resolved' THEN 1 END) as high_priority_count
    FROM anomaly_alerts
")->fetch_assoc();

include 'includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-exclamation-circle stat-icon"></i>
                <div class="stat-value"><?php echo $stats['new_count'] ?? 0; ?></div>
                <div class="stat-label">New Alerts</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-search stat-icon"></i>
                <div class="stat-value"><?php echo $stats['investigating_count'] ?? 0; ?></div>
                <div class="stat-label">Investigating</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-value"><?php echo $stats['resolved_count'] ?? 0; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-exclamation-triangle stat-icon"></i>
                <div class="stat-value"><?php echo $stats['high_priority_count'] ?? 0; ?></div>
                <div class="stat-label">High Priority</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <i class="fas fa-list me-2"></i>Anomaly Alerts
            </div>
            <button class="btn btn-warning btn-sm" onclick="runDetection()">
                <i class="fas fa-sync-alt me-2"></i>Run Detection
            </button>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-4" id="alertTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
                        All Alerts
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button">
                        New <span class="badge bg-danger ms-2"><?php echo $stats['new_count'] ?? 0; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="investigating-tab" data-bs-toggle="tab" data-bs-target="#investigating" type="button">
                        Investigating
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="resolved-tab" data-bs-toggle="tab" data-bs-target="#resolved" type="button">
                        Resolved
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="alertTabsContent">
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <?php displayAlerts($alerts, 'all'); ?>
                </div>
                <div class="tab-pane fade" id="new" role="tabpanel">
                    <?php displayAlerts($alerts, 'new'); ?>
                </div>
                <div class="tab-pane fade" id="investigating" role="tabpanel">
                    <?php displayAlerts($alerts, 'investigating'); ?>
                </div>
                <div class="tab-pane fade" id="resolved" role="tabpanel">
                    <?php displayAlerts($alerts, 'resolved'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function runDetection() {
    $.ajax({
        url: '../api/anomaly_detection.php',
        method: 'POST',
        data: {action: 'all'},
        success: function() {
            location.reload();
        }
    });
}

function resolveAlert(alertId) {
    if (confirm('Mark this alert as resolved?')) {
        $.ajax({
            url: 'anomaly_alerts.php',
            method: 'POST',
            data: {action: 'resolve', alert_id: alertId},
            success: function() {
                location.reload();
            }
        });
    }
}
</script>

<?php
function displayAlerts($alerts, $filter) {
    $alerts->data_seek(0);
    $has_alerts = false;
    
    while ($alert = $alerts->fetch_assoc()) {
        if ($filter != 'all' && $alert['status'] != $filter) {
            continue;
        }
        $has_alerts = true;
        
        $severity_class = $alert['severity'] == 'high' ? 'danger' : 
                         ($alert['severity'] == 'medium' ? 'warning' : 'info');
        ?>
        <div class="card mb-3 border-<?php echo $severity_class; ?>">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-1">
                        <span class="badge bg-<?php echo $severity_class; ?> p-2">
                            <?php echo ucfirst($alert['severity']); ?>
                        </span>
                    </div>
                    <div class="col-md-2">
                        <strong>Vehicle:</strong><br>
                        <?php echo htmlspecialchars($alert['vehicle_number'] ?: 'N/A'); ?>
                    </div>
                    <div class="col-md-2">
                        <strong>Space:</strong><br>
                        <?php echo htmlspecialchars($alert['space_number'] ?: 'N/A'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Type:</strong><br>
                        <?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($alert['description']); ?></small>
                    </div>
                    <div class="col-md-2">
                        <span class="badge bg-<?php 
                            echo $alert['status'] == 'new' ? 'danger' : 
                                ($alert['status'] == 'investigating' ? 'warning' : 'success'); 
                        ?> p-2">
                            <?php echo ucfirst($alert['status']); ?>
                        </span>
                        <br>
                        <small><?php echo date('d M H:i', strtotime($alert['detected_at'])); ?></small>
                    </div>
                    <div class="col-md-2 text-end">
                        <?php if ($alert['status'] != 'resolved'): ?>
                            <button class="btn btn-sm btn-success" onclick="resolveAlert(<?php echo $alert['id']; ?>)">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        <?php else: ?>
                            <small>Resolved by<br><?php echo htmlspecialchars($alert['resolver_name'] ?: 'System'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    if (!$has_alerts) {
        echo '<div class="alert alert-info">No alerts found.</div>';
    }
}
?>

<?php include 'includes/footer.php'; ?>