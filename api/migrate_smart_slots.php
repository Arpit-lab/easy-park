<?php
/**
 * Database Migration: Add Smart Slot Recommendation Columns
 * 
 * This script adds the required columns for the smart parking recommendation system:
 * - distance_from_entry: Distance in meters from parking lot entry
 * - priority_level: Congestion indicator (0=low, 1=medium, 2=high, 3=very high)
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Only allow admin or CLI access
if (php_sapi_name() !== 'cli') {
    Session::requireAdmin();
}

$conn = getDB();
$errors = [];
$success = [];

try {
    // Check if distance_from_entry column exists
    $check_distance = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'distance_from_entry'");
    
    if ($check_distance && $check_distance->num_rows === 0) {
        echo "Adding distance_from_entry column...\n";
        $alter_distance = $conn->query("
            ALTER TABLE parking_spaces 
            ADD COLUMN distance_from_entry FLOAT DEFAULT 0 
            COMMENT 'Distance from parking lot entry in meters'
        ");
        
        if ($alter_distance) {
            $success[] = "✓ Column 'distance_from_entry' added successfully";
        } else {
            $errors[] = "✗ Failed to add distance_from_entry: " . $conn->error;
        }
    } else {
        $success[] = "✓ Column 'distance_from_entry' already exists";
    }
    
    // Check if priority_level column exists
    $check_priority = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'priority_level'");
    
    if ($check_priority && $check_priority->num_rows === 0) {
        echo "Adding priority_level column...\n";
        $alter_priority = $conn->query("
            ALTER TABLE parking_spaces 
            ADD COLUMN priority_level INT DEFAULT 1 
            COMMENT 'Congestion level (0=low, 1=medium, 2=high, 3=very high)'
        ");
        
        if ($alter_priority) {
            $success[] = "✓ Column 'priority_level' added successfully";
        } else {
            $errors[] = "✗ Failed to add priority_level: " . $conn->error;
        }
    } else {
        $success[] = "✓ Column 'priority_level' already exists";
    }
    
    // Set default values for existing spaces
    if (count($success) > 0) {
        // Assign default distances (25-50m based on space ID)
        $update_distance = $conn->query("
            UPDATE parking_spaces 
            SET distance_from_entry = 25 + (id % 26)
            WHERE distance_from_entry = 0
        ");
        
        if ($update_distance) {
            $success[] = "✓ Default distances populated for existing spaces";
        }
        
        // Log migration
        logActivity(1, 'database_migration', 'Smart slot recommendation columns added to parking_spaces');
    }
    
    // Output results
    echo "\n=== Database Migration Results ===\n";
    
    foreach ($success as $msg) {
        echo "$msg\n";
    }
    
    foreach ($errors as $msg) {
        echo "$msg\n";
    }
    
    // Return JSON for API calls
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => count($errors) === 0,
            'messages' => array_merge($success, $errors)
        ]);
    }
    
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

?>
