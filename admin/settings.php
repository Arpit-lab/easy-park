<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'System Settings';
$page_icon = 'cog';

$conn = getDB();
$message = '';
$error = '';

// Ensure system_settings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
if (!$table_check || $table_check->num_rows === 0) {
    $create_table_sql = "
        CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $conn->query($create_table_sql);
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $setting_key = trim($_POST['setting_key']);
    $setting_value = trim($_POST['setting_value']);
    
    if (empty($setting_key)) {
        $error = "Setting key cannot be empty";
    } else {
        // Check if setting exists
        $existing = $conn->query("SELECT * FROM system_settings WHERE setting_key = '$setting_key' LIMIT 1");
        
        if ($existing && $existing->num_rows > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $setting_value, $setting_key);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $setting_key, $setting_value);
        }
        
        if ($stmt->execute()) {
            $message = "Setting updated successfully!";
            logActivity($_SESSION['user_id'], 'update_setting', "Updated setting: $setting_key");
        } else {
            $error = "Error updating setting: " . $conn->error;
        }
    }
}

// Get all settings
$settings = [];
$settings_result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

include 'includes/header.php';
?>

<style>
    .settings-section {
        margin-bottom: 30px;
    }
    .setting-item {
        padding: 15px;
        border: 1px solid #eee;
        border-radius: 8px;
        margin-bottom: 10px;
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

    <div class="row">
        <div class="col-md-10 offset-md-1">
            <!-- System Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>System Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                            </p>
                            <p class="mb-2">
                                <strong>MySQL Version:</strong> <?php echo $conn->server_info; ?>
                            </p>
                            <p class="mb-2">
                                <strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>System Name:</strong> EasyPark Parking Management
                            </p>
                            <p class="mb-2">
                                <strong>Version:</strong> 1.0.0
                            </p>
                            <p class="mb-2">
                                <strong>Admin User:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parking Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-parking me-2"></i>Parking Configuration
                </div>
                <div class="card-body">
                    <form method="POST" class="settings-form">
                        <div class="setting-item">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Default Hourly Rate (रू)</strong></label>
                                    <small class="text-muted">Set default parking rate per hour</small>
                                </div>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="setting_value" 
                                           value="<?php echo htmlspecialchars($settings['default_hourly_rate'] ?? '50'); ?>" 
                                           placeholder="50" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <input type="hidden" name="action" value="update_settings">
                                    <input type="hidden" name="setting_key" value="default_hourly_rate">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form method="POST" class="settings-form">
                        <div class="setting-item">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Maximum Daily Hours</strong></label>
                                    <small class="text-muted">Maximum hours a vehicle can park</small>
                                </div>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="setting_value" 
                                           value="<?php echo htmlspecialchars($settings['max_daily_hours'] ?? '24'); ?>" 
                                           placeholder="24">
                                </div>
                                <div class="col-md-2">
                                    <input type="hidden" name="action" value="update_settings">
                                    <input type="hidden" name="setting_key" value="max_daily_hours">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Smart Recommendations -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-lightbulb me-2"></i>Smart Recommendations
                </div>
                <div class="card-body">
                    <form method="POST" class="settings-form">
                        <div class="setting-item">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Enable Smart Recommendations</strong></label>
                                    <small class="text-muted">Show AI-based parking suggestions</small>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-control" name="setting_value">
                                        <option value="yes" <?php echo ($settings['enable_smart_recommendations'] ?? 'yes') === 'yes' ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="no" <?php echo ($settings['enable_smart_recommendations'] ?? 'yes') === 'no' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="hidden" name="action" value="update_settings">
                                    <input type="hidden" name="setting_key" value="enable_smart_recommendations">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form method="POST" class="settings-form">
                        <div class="setting-item">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Show Alternative Options</strong></label>
                                    <small class="text-muted">Number of alternative parking spots</small>
                                </div>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="setting_value" 
                                           value="<?php echo htmlspecialchars($settings['alternative_options_count'] ?? '2'); ?>" 
                                           placeholder="2" min="1" max="5">
                                </div>
                                <div class="col-md-2">
                                    <input type="hidden" name="action" value="update_settings">
                                    <input type="hidden" name="setting_key" value="alternative_options_count">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Settings Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>All System Settings
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Setting Key</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($settings)): ?>
                                    <?php foreach ($settings as $key => $value): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($key); ?></code></td>
                                            <td><?php echo htmlspecialchars(strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">No settings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
