<?php
require_once 'includes/db_connection.php';

$conn = getDB();

// Add indexes to improve query performance
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_parking_bookings_user_status ON parking_bookings (user_id, booking_status)",
    "CREATE INDEX IF NOT EXISTS idx_parking_bookings_check_in ON parking_bookings (check_in)",
    "CREATE INDEX IF NOT EXISTS idx_parking_bookings_check_out ON parking_bookings (check_out)",
    "CREATE INDEX IF NOT EXISTS idx_parking_spaces_status_available ON parking_spaces (status, is_available)",
    "CREATE INDEX IF NOT EXISTS idx_parking_spaces_location ON parking_spaces (location_name)",
    "CREATE INDEX IF NOT EXISTS idx_parking_spaces_category ON parking_spaces (category_id)",
    "CREATE INDEX IF NOT EXISTS idx_vehicle_categories_status ON vehicle_categories (status)"
];

echo "<h2>Adding Database Indexes for Performance</h2>";
echo "<pre>";

foreach ($indexes as $index_sql) {
    if ($conn->query($index_sql)) {
        echo "✓ Index created: " . substr($index_sql, 0, 50) . "...\n";
    } else {
        echo "✗ Error creating index: " . $conn->error . "\n";
    }
}

echo "\nIndex optimization completed.\n";
echo "</pre>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>