<?php
require_once 'includes/config.php';
require_once 'includes/db_connection.php';

$conn = getDB();

echo "<h2>Database Analysis</h2>";
echo "<pre>";

// Check parking_spaces table structure
echo "\n=== PARKING_SPACES TABLE STRUCTURE ===\n";
$result = $conn->query("SHOW COLUMNS FROM parking_spaces");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ") - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "ERROR: " . $conn->error . "\n";
}

// Check if required columns exist
echo "\n=== CHECKING REQUIRED COLUMNS ===\n";
$distance_check = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'distance_from_entry'");
$priority_check = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'priority_level'");

echo "distance_from_entry: " . ($distance_check && $distance_check->num_rows > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";
echo "priority_level: " . ($priority_check && $priority_check->num_rows > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";

// Check parking_spaces data
echo "\n=== PARKING_SPACES DATA ===\n";
$spaces = $conn->query("SELECT id, space_number, location_name, distance_from_entry, priority_level, is_available FROM parking_spaces LIMIT 10");
if ($spaces && $spaces->num_rows > 0) {
    while ($space = $spaces->fetch_assoc()) {
        echo "ID: {$space['id']} | Space: {$space['space_number']} | Location: {$space['location_name']} | Distance: {$space['distance_from_entry']} | Priority: {$space['priority_level']} | Available: {$space['is_available']}\n";
    }
    echo "\nTotal spaces: " . ($conn->query("SELECT COUNT(*) as count FROM parking_spaces")->fetch_assoc()['count']) . "\n";
} else {
    echo "No parking spaces found or query error: " . $conn->error . "\n";
}

// Check locations
echo "\n=== AVAILABLE LOCATIONS ===\n";
$locations = $conn->query("SELECT DISTINCT location_name FROM parking_spaces WHERE status = 'active'");
if ($locations && $locations->num_rows > 0) {
    while ($loc = $locations->fetch_assoc()) {
        echo "- " . $loc['location_name'] . "\n";
    }
} else {
    echo "No locations found\n";
}

echo "</pre>";
?>
